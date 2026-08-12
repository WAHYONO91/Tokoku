<?php
require_once __DIR__ . '/config.php';

// Enable session for cart if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== AUTO MIGRATION FOR ADS & POPUPS =====
try {
    // Ensure shop_ads table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shop_ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT NULL,
            link VARCHAR(255) NULL,
            image VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    // Ensure shop_popups table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shop_popups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            content TEXT NULL,
            link VARCHAR(255) NULL,
            image VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Alter tables safely to add columns if they don't exist
    try {
        $pdo->exec("ALTER TABLE shop_popups ADD COLUMN item_kode VARCHAR(50) NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE shop_popups ADD COLUMN promo_price BIGINT NULL DEFAULT 0");
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    // Ignore migration error if already exists
}

// Load all settings
$setting = $pdo->query("SELECT * FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$store_name = $setting['store_name'] ?? 'TokoAPP';
$app_theme = $setting['theme'] ?? 'light';

// Fetch all active banners from table
$active_ads = $pdo->query("SELECT * FROM shop_ads WHERE is_active = 1 ORDER BY sort_order ASC, id DESC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
$valid_ads = [];
foreach ($active_ads as $ad) {
    if (!empty($ad['image']) && file_exists(__DIR__ . '/' . $ad['image'])) {
        $valid_ads[] = $ad;
    }
}

// Fetch active popup from new table (join items for promo item info)
$active_popup = $pdo->query("
    SELECT p.*, i.nama AS item_nama, i.harga_jual1 AS harga_asli
    FROM shop_popups p
    LEFT JOIN items i ON p.item_kode = i.kode
    WHERE p.is_active = 1
    ORDER BY p.id DESC LIMIT 1
")->fetch(PDO::FETCH_ASSOC) ?: [];
$shop_popup_image   = $active_popup['image']      ?? '';
$shop_popup_title   = $active_popup['title']      ?? '';
$shop_popup_content = $active_popup['content']    ?? '';
$shop_popup_link    = $active_popup['link']       ?? '';
$shop_popup_item    = $active_popup['item_kode']  ?? '';
$shop_popup_pprice  = (int)($active_popup['promo_price'] ?? 0);
$shop_popup_iname   = $active_popup['item_nama']  ?? '';
$shop_popup_horig   = (int)($active_popup['harga_asli']  ?? 0);

// Validate active popup: must exist, and if it links to an item, that item must exist (item_nama is not null)
$shop_popup_active  = (!empty($active_popup) && (empty($shop_popup_item) || !empty($shop_popup_iname))) ? 1 : 0;

// Handle Add to Cart
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
    $item_kode = trim($_POST['item_kode'] ?? '');
    $qty = (int)($_POST['qty'] ?? 1);
    
    if ($item_kode !== '' && $qty > 0) {
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        
        if (isset($_SESSION['cart'][$item_kode])) {
            $_SESSION['cart'][$item_kode] += $qty;
        } else {
            $_SESSION['cart'][$item_kode] = $qty;
        }
    }
    // Redirect to prevent form resubmission
    header('Location: shop.php?added=1');
    exit;
}

// ===== HANDLE PROMO CLAIM =====
if (isset($_GET['claim_promo'])) {
    if (!empty($shop_popup_item) && $shop_popup_pprice > 0) {
        // Add promo item to cart
        if (!isset($_SESSION['cart'])) {
            $_SESSION['cart'] = [];
        }
        // Only add if not already in cart from this promo
        if (!isset($_SESSION['cart'][$shop_popup_item])) {
            $_SESSION['cart'][$shop_popup_item] = 1;
        }
        // Store custom promo price in session
        if (!isset($_SESSION['cart_custom_prices'])) {
            $_SESSION['cart_custom_prices'] = [];
        }
        $_SESSION['cart_custom_prices'][$shop_popup_item] = $shop_popup_pprice;
        // Tag claimed promo
        $_SESSION['claimed_promo'] = $shop_popup_title . ' — ' . $shop_popup_iname;
        // Redirect to cart to review
        header('Location: cart.php?promo_added=1');
        exit;
    } else {
        // Fallback: just tag the promo name (no item linked)
        $claimTitle = !empty($shop_popup_title) ? $shop_popup_title : 'Promo Spesial';
        $_SESSION['claimed_promo'] = $claimTitle;
        header('Location: shop.php?promo_claimed=1');
        exit;
    }
}
if (isset($_GET['cancel_promo'])) {
    unset($_SESSION['claimed_promo']);
    unset($_SESSION['cart_custom_prices']);
    header('Location: shop.php');
    exit;
}

// Check active promo claim
$claimed_promo = $_SESSION['claimed_promo'] ?? '';

$q = trim($_GET['q'] ?? '');
$filter_kat = trim($_GET['kategori'] ?? '');

$sql = "
  SELECT i.*, 
    SUM(CASE WHEN s.location = 'toko' THEN s.qty ELSE 0 END) AS stok_toko,
    SUM(CASE WHEN s.location = 'gudang' THEN s.qty ELSE 0 END) AS stok_gudang
  FROM items i
  LEFT JOIN item_stocks s ON s.item_kode = i.kode
  WHERE 1
";
$params = [];

if ($q !== '') {
    $sql .= " AND (i.kode LIKE ? OR i.nama LIKE ?)";
    $params[] = "%$q%";
    $params[] = "%$q%";
}
if ($filter_kat !== '') {
    $sql .= " AND i.kategori = ?";
    $params[] = $filter_kat;
}

$sql .= " GROUP BY i.kode 
              ORDER BY (
                SUM(CASE WHEN s.location = 'toko' THEN s.qty ELSE 0 END) + 
                SUM(CASE WHEN s.location = 'gudang' THEN s.qty ELSE 0 END)
              ) DESC, i.nama ASC LIMIT 40";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$items = $stmt->fetchAll();

// Get Categories for filter
$cats = $pdo->query("SELECT DISTINCT kategori FROM items WHERE kategori IS NOT NULL AND kategori != '' ORDER BY kategori")->fetchAll(PDO::FETCH_COLUMN);

// Calculate Cart count
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $kode => $qty) {
        $cart_count += $qty;
    }
}

$page_title = "Online Shop - " . ($store_name ?? 'TokoAPP');
require_once __DIR__ . '/includes/shop_header.php';
?>

<style>
/* Modern & Premium Marketplace Redesign */
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700;800&display=swap');

:root {
    --shop-font: 'Outfit', system-ui, -apple-system, sans-serif;
    --card-shadow: 0 10px 30px -10px rgba(0, 0, 0, 0.08);
    --card-shadow-hover: 0 20px 40px -15px rgba(249, 115, 22, 0.15);
    --transition-smooth: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    --gradient-banner: linear-gradient(135deg, #4f46e5 0%, #7c3aed 50%, #db2777 100%);
    --gradient-gold: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

body {
    font-family: var(--shop-font);
}

.shop-container {
    padding: 0;
}

.section-title {
    font-size: 1.35rem;
    font-weight: 700;
    margin-bottom: 1.25rem;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.6rem;
}

/* Beautiful Hero Banner */
.hero-banner {
    position: relative;
    border-radius: 16px;
    height: 300px;
    margin-bottom: 2rem;
    overflow: hidden;
    display: flex;
    align-items: center;
    box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.1);
}
.hero-banner-bg {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-size: cover;
    background-position: center;
    transition: transform 0.5s ease;
}
.hero-banner:hover .hero-banner-bg {
    transform: scale(1.03);
}
.hero-banner-overlay {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: linear-gradient(90deg, rgba(15, 23, 42, 0.85) 0%, rgba(15, 23, 42, 0.4) 60%, rgba(15, 23, 42, 0.2) 100%);
}
.hero-banner-content {
    position: relative;
    z-index: 2;
    padding: 2.5rem;
    max-width: 550px;
    color: white;
}
.hero-banner-title {
    font-size: 2.2rem;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 0.75rem;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.3);
}
.hero-banner-desc {
    font-size: 1.05rem;
    opacity: 0.9;
    margin-bottom: 1.5rem;
    line-height: 1.4;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
}
.hero-banner-btn {
    background: var(--brand-color) !important;
    color: white !important;
    border: none !important;
    border-radius: 30px !important;
    padding: 0.65rem 1.75rem !important;
    font-weight: 700 !important;
    font-size: 0.9rem !important;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    box-shadow: 0 4px 15px rgba(249, 115, 22, 0.4) !important;
    transition: var(--transition-smooth);
    text-decoration: none !important;
}
.hero-banner-btn:hover {
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(249, 115, 22, 0.6) !important;
    background: var(--brand-color-hover) !important;
}

/* Category Bubbles Style */
.category-container {
    background: var(--card-bg);
    border: 1px solid var(--card-bd);
    border-radius: 16px;
    padding: 1.5rem;
    margin-bottom: 2rem;
    box-shadow: var(--card-shadow);
}
.category-scroll {
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    scrollbar-width: none; /* Firefox */
}
.category-scroll::-webkit-scrollbar {
    display: none; /* Chrome/Safari */
}
.category-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.6rem;
    cursor: pointer;
    text-decoration: none !important;
    min-width: 90px;
    transition: var(--transition-smooth);
}
.category-item:hover {
    transform: translateY(-4px);
}
.category-icon {
    width: 60px;
    height: 60px;
    background: var(--input-bg);
    border-radius: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.6rem;
    border: 2px solid var(--card-bd);
    transition: var(--transition-smooth);
}
.category-item:hover .category-icon {
    border-color: var(--brand-color);
    box-shadow: 0 4px 12px rgba(249, 115, 22, 0.1);
}
.category-item.active .category-icon {
    background: #ffedd5;
    border-color: var(--brand-color);
    color: var(--brand-color);
    box-shadow: 0 6px 16px rgba(249, 115, 22, 0.2);
}
[data-theme="dark"] .category-icon { background: #1f2937; }
[data-theme="dark"] .category-item.active .category-icon { background: #431407; }

.category-label {
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-main);
    text-align: center;
    line-height: 1.2;
}

/* Modern Search Box */
.search-body-container {
    margin-bottom: 2rem;
}
.search-body-container form {
    display: flex;
    background: var(--card-bg);
    padding: 0.5rem;
    border-radius: 12px;
    border: 1px solid var(--card-bd);
    box-shadow: var(--card-shadow);
    gap: 0.5rem;
    margin: 0;
}
.search-body-container input {
    margin: 0;
    flex: 1;
    background: var(--input-bg);
    border: 1px solid transparent;
    border-radius: 8px;
    padding: 0.75rem 1rem;
    font-size: 0.95rem;
    transition: var(--transition-smooth);
}
.search-body-container input:focus {
    border-color: var(--brand-color);
    box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
}
.search-body-container button {
    width: auto;
    margin: 0;
    background: var(--brand-color) !important;
    border: none !important;
    padding: 0 1.75rem !important;
    border-radius: 8px !important;
    font-weight: 700;
    color: white !important;
    transition: var(--transition-smooth);
}
.search-body-container button:hover {
    background: var(--brand-color-hover) !important;
}

/* Premium E-Commerce Product Grid (Tokopedia/Shopee Style - Compact Images) */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr));
    gap: 1rem;
}
.product-card {
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--card-bd, #e2e8f0);
    border-radius: 12px;
    display: flex;
    flex-direction: column;
    transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    overflow: hidden;
    position: relative;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.03);
    text-decoration: none !important;
}
.product-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px -8px rgba(249, 115, 22, 0.18);
    border-color: var(--brand-color, #f97316);
}
.product-image-wrapper {
    width: 100%;
    aspect-ratio: 1 / 1;
    background: var(--input-bg, #f8fafc);
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.35rem;
}
.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
    border-radius: 8px;
    transition: transform 0.3s ease;
}
.product-card:hover .product-image {
    transform: scale(1.05);
}
.product-info {
    padding: 0.65rem 0.75rem 0.75rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.product-name {
    font-size: 0.82rem;
    font-weight: 600;
    line-height: 1.35;
    color: var(--text-main);
    margin-bottom: 0.35rem;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
    height: 2.25rem;
    flex: none;
}
.product-price {
    color: var(--brand-color, #f97316);
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: 0.35rem;
    letter-spacing: -0.01em;
}
.product-meta {
    font-size: 0.72rem;
    color: var(--text-muted);
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 0.6rem;
    font-weight: 500;
}
.stock-badge {
    font-size: 0.68rem;
    font-weight: 700;
    padding: 0.15rem 0.45rem;
    border-radius: 99px;
    display: inline-flex;
    align-items: center;
    gap: 0.2rem;
}
.stock-badge.ready {
    background: rgba(16, 185, 129, 0.12);
    color: #10b981;
}
.stock-badge.low {
    background: rgba(245, 158, 11, 0.12);
    color: #f59e0b;
}
.stock-badge.empty {
    background: rgba(239, 68, 68, 0.12);
    color: #ef4444;
}

.add-tocart-btn {
    width: 100%;
    padding: 0.45rem 0.5rem !important;
    font-size: 0.78rem !important;
    background: var(--brand-color, #f97316) !important;
    color: white !important;
    border: none !important;
    border-radius: 8px !important;
    cursor: pointer;
    font-weight: 700 !important;
    transition: var(--transition-smooth);
    margin: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.35rem;
}
.add-tocart-btn:hover { background: var(--brand-color-hover, #ea580c) !important; }
.add-tocart-btn:disabled { background: var(--text-muted) !important; cursor: not-allowed; opacity: 0.7; }

.badge-overlay {
    position: absolute;
    top: 6px;
    left: 6px;
    background: rgba(249, 115, 22, 0.9);
    backdrop-filter: blur(4px);
    color: white;
    font-size: 0.65rem;
    font-weight: 700;
    padding: 0.15rem 0.5rem;
    border-radius: 6px;
    z-index: 10;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.15);
}

/* Premium Marketing Popup Modal */
.popup-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(15, 23, 42, 0.55);
    backdrop-filter: blur(8px);
    -webkit-backdrop-filter: blur(8px);
    z-index: 100000;
    display: flex;
    align-items: center;
    justify-content: center;
    opacity: 0;
    pointer-events: none;
    transition: opacity 0.3s ease;
}
.popup-overlay.show {
    opacity: 1;
    pointer-events: auto;
}
.popup-card {
    width: 90%;
    max-width: 440px;
    background: var(--card-bg);
    border: 1px solid var(--card-bd);
    border-radius: 24px;
    overflow: hidden;
    box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
    transform: scale(0.9);
    transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
}
.popup-overlay.show .popup-card {
    transform: scale(1);
}
.popup-img-wrapper {
    width: 100%;
    height: 220px;
    background-size: cover;
    background-position: center;
    position: relative;
    background-color: var(--input-bg);
}
.popup-close-btn {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(15, 23, 42, 0.6);
    color: white;
    border: none;
    border-radius: 50%;
    width: 32px;
    height: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.1rem;
    transition: var(--transition-smooth);
    z-index: 10;
}
.popup-close-btn:hover {
    background: rgba(15, 23, 42, 0.8);
    transform: scale(1.05);
}
.popup-body {
    padding: 2rem;
    text-align: center;
}
.popup-title {
    font-size: 1.45rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: var(--text-main);
}
.popup-text {
    font-size: 0.9rem;
    color: var(--text-muted);
    margin-bottom: 1.5rem;
    line-height: 1.5;
}
.popup-btn {
    width: 100%;
    background: var(--brand-color);
    color: white !important;
    border: none;
    border-radius: 12px;
    padding: 0.75rem;
    font-weight: 700;
    box-shadow: 0 4px 14px rgba(249, 115, 22, 0.3);
    transition: var(--transition-smooth);
    display: block;
    text-decoration: none !important;
}
.popup-btn:hover {
    background: var(--brand-color-hover);
    transform: translateY(-2px);
    box-shadow: 0 6px 18px rgba(249, 115, 22, 0.4);
}

/* Mobile Adjustments */
@media (max-width: 768px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr);
        gap: 0.75rem;
    }
    .product-info {
        padding: 0.75rem;
    }
    .product-name {
        font-size: 0.85rem;
        height: 2.4rem;
        -webkit-line-clamp: 2;
    }
    .product-price {
        font-size: 1.05rem;
    }
    .product-meta {
        font-size: 0.7rem;
        margin-bottom: 0.6rem;
    }
    .add-tocart-btn {
        padding: 0.45rem !important;
        font-size: 0.75rem !important;
    }
    .hero-banner {
        height: 220px;
        border-radius: 12px;
    }
    .hero-banner-content {
        padding: 1.5rem;
    }
    .hero-banner-title {
        font-size: 1.5rem;
    }
    .hero-banner-desc {
        font-size: 0.85rem;
        margin-bottom: 1rem;
    }
    .hero-banner-btn {
        padding: 0.5rem 1.25rem !important;
        font-size: 0.8rem !important;
    }
    .category-container {
        padding: 1rem;
    }
    .category-scroll {
        gap: 1rem;
    }
    .category-icon {
        width: 50px;
        height: 50px;
        font-size: 1.3rem;
    }
    .category-label {
        font-size: 0.75rem;
    }
}

/* Hero Banner Carousel Styles */
.hero-banner-carousel {
    position: relative;
    border-radius: 16px;
    height: 300px;
    margin-bottom: 2rem;
    overflow: hidden;
    box-shadow: 0 15px 35px -10px rgba(0, 0, 0, 0.1);
}
.carousel-track {
    width: 100%;
    height: 100%;
    position: relative;
}
.carousel-slide {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    opacity: 0;
    visibility: hidden;
    transition: opacity 0.6s ease-in-out, visibility 0.6s ease-in-out;
    display: flex;
    align-items: center;
}
.carousel-slide.active {
    opacity: 1;
    visibility: visible;
    z-index: 1;
}
.carousel-control {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(15, 23, 42, 0.5);
    color: white;
    border: none;
    border-radius: 50%;
    width: 40px;
    height: 40px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 1.2rem;
    font-weight: bold;
    z-index: 10;
    transition: var(--transition-smooth);
    opacity: 0;
}
.hero-banner-carousel:hover .carousel-control {
    opacity: 1;
}
.carousel-control:hover {
    background: rgba(15, 23, 42, 0.8);
    transform: translateY(-50%) scale(1.05);
}
.carousel-control.prev {
    left: 20px;
}
.carousel-control.next {
    right: 20px;
}
.carousel-indicators {
    position: absolute;
    bottom: 15px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    gap: 8px;
    z-index: 10;
}
.carousel-dot {
    width: 10px;
    height: 10px;
    border-radius: 50%;
    background: rgba(255, 255, 255, 0.5);
    cursor: pointer;
    transition: var(--transition-smooth);
}
.carousel-dot.active {
    background: white;
    width: 24px;
    border-radius: 5px;
}
@media (max-width: 768px) {
    .hero-banner-carousel {
        height: 220px;
        border-radius: 12px;
    }
    .carousel-control {
        width: 32px;
        height: 32px;
        font-size: 0.9rem;
    }
    .carousel-control.prev { left: 10px; }
    .carousel-control.next { right: 10px; }
}
</style>

<div class="shop-container">

    <?php if (isset($_GET['added'])): ?>
        <mark style="display:block;margin-bottom:1rem;background:#10b981;color:#fff;padding:0.75rem;border-radius:12px;text-align:center;font-weight:600;box-shadow: 0 4px 12px rgba(16,185,129,0.2);">
            ✔️ Barang berhasil ditambahkan ke keranjang belanja!
        </mark>
    <?php endif; ?>

    <!-- PROMO CLAIMED BANNER -->
    <?php if (!empty($claimed_promo)): ?>
    <div style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); border-radius: 14px; padding: 1rem 1.5rem; margin-bottom: 1.25rem; display: flex; align-items: center; justify-content: space-between; gap: 1rem; box-shadow: 0 6px 20px rgba(245, 158, 11, 0.35); flex-wrap: wrap;">
        <div style="display:flex; align-items:center; gap:0.75rem; color:#fff;">
            <span style="font-size:1.8rem;">🎟️</span>
            <div>
                <div style="font-weight:800; font-size:1rem; text-shadow:0 1px 2px rgba(0,0,0,0.2);">Promo Berhasil Diklaim!</div>
                <div style="font-size:0.85rem; opacity:0.9;"><?= htmlspecialchars($claimed_promo) ?> &mdash; Kasir akan memberikan potongan harga saat proses pembayaran.</div>
            </div>
        </div>
        <div style="display:flex; gap:0.5rem; flex-wrap: wrap;">
            <a href="cart.php" style="background:rgba(255,255,255,0.2); color:#fff; border:2px solid rgba(255,255,255,0.6); border-radius:8px; padding:0.45rem 1rem; font-weight:700; font-size:0.82rem; text-decoration:none; display:inline-flex; align-items:center; gap:0.3rem; transition: all 0.2s;">🛒 Ke Keranjang</a>
            <a href="shop.php?cancel_promo=1" style="background:rgba(0,0,0,0.15); color:#fff; border:1px solid rgba(255,255,255,0.3); border-radius:8px; padding:0.45rem 0.75rem; font-weight:600; font-size:0.78rem; text-decoration:none; display:inline-flex; align-items:center;">✕ Batalkan</a>
        </div>
    </div>
    <?php endif; ?>

    <!-- HERO PROMO ADVERTISEMENT BANNER/CAROUSEL -->
    <?php if (count($valid_ads) > 1): ?>
        <section class="hero-banner-carousel" id="heroCarousel">
            <div class="carousel-track">
                <?php foreach ($valid_ads as $index => $ad): ?>
                    <div class="carousel-slide <?= $index === 0 ? 'active' : '' ?>">
                        <div class="hero-banner-bg" style="background-image: url('<?= htmlspecialchars($ad['image']) ?>');"></div>
                        <div class="hero-banner-overlay"></div>
                        <div class="hero-banner-content">
                            <h1 class="hero-banner-title"><?= htmlspecialchars($ad['title'] ?: 'Dapatkan Promo Spesial!') ?></h1>
                            <p class="hero-banner-desc"><?= htmlspecialchars($ad['description'] ?: 'Temukan ribuan penawaran menarik khusus untuk Anda hari ini.') ?></p>
                            <?php if (!empty($ad['link'])): ?>
                                <a href="<?= htmlspecialchars($ad['link']) ?>" class="hero-banner-btn">Belanja Sekarang 🛍️</a>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            <!-- Carousel Controls -->
            <button class="carousel-control prev" onclick="moveSlide(-1)">&lt;</button>
            <button class="carousel-control next" onclick="moveSlide(1)">&gt;</button>
            <!-- Carousel Indicators -->
            <div class="carousel-indicators">
                <?php for ($i = 0; $i < count($valid_ads); $i++): ?>
                    <span class="carousel-dot <?= $i === 0 ? 'active' : '' ?>" onclick="setSlide(<?= $i ?>)"></span>
                <?php endfor; ?>
            </div>
        </section>
    <?php elseif (count($valid_ads) === 1): ?>
        <?php 
        $ad = $valid_ads[0];
        $shop_ad_image = $ad['image'] ?? '';
        ?>
        <section class="hero-banner">
            <div class="hero-banner-bg" style="background-image: url('<?= htmlspecialchars($shop_ad_image) ?>');"></div>
            <div class="hero-banner-overlay"></div>
            <div class="hero-banner-content">
                <h1 class="hero-banner-title"><?= htmlspecialchars($ad['title'] ?: 'Dapatkan Promo Spesial!') ?></h1>
                <p class="hero-banner-desc"><?= htmlspecialchars($ad['description'] ?: 'Temukan ribuan penawaran menarik khusus untuk Anda hari ini.') ?></p>
                <?php if (!empty($ad['link'])): ?>
                    <a href="<?= htmlspecialchars($ad['link']) ?>" class="hero-banner-btn">Belanja Sekarang 🛍️</a>
                <?php endif; ?>
            </div>
        </section>
    <?php else: ?>
        <!-- Default elegant gradient banner if no ad uploaded -->
        <section class="hero-banner">
            <div class="hero-banner-bg" style="background: var(--gradient-banner);"></div>
            <div class="hero-banner-overlay" style="background: transparent;"></div>
            <div class="hero-banner-content">
                <h1 class="hero-banner-title">Selamat Belanja di <?= htmlspecialchars($store_name) ?>!</h1>
                <p class="hero-banner-desc">Kami menyediakan produk berkualitas terbaik dengan harga yang bersahabat untuk Anda.</p>
                <a href="#katalog" class="hero-banner-btn">Lihat Produk Pilihan 👇</a>
            </div>
        </section>
    <?php endif; ?>

    <!-- SEARCH SECTION -->
    <section class="search-body-container">
        <form action="shop.php" method="get">
            <?php if ($filter_kat !== ''): ?>
                <input type="hidden" name="kategori" value="<?= htmlspecialchars($filter_kat) ?>">
            <?php endif; ?>
            <input type="text" name="q" placeholder="Cari barang favorit Anda di sini..." value="<?= htmlspecialchars($q) ?>">
            <button type="submit">Cari</button>
        </form>
    </section>

    <!-- CATEGORIES ROW -->
    <section class="category-container">
        <div class="section-title">📂 Kategori Belanja</div>
        <div class="category-scroll">
            <a href="shop.php" class="category-item <?= $filter_kat === '' ? 'active' : '' ?>">
                <div class="category-icon">🏷️</div>
                <div class="category-label">Semua</div>
            </a>
            <?php 
            $icons = ['📦','🍔','👕','📱','💄','⚽','🚗','📚','🧸','🛠️'];
            $i = 0;
            foreach ($cats as $c): 
                $icon = $icons[$i % count($icons)];
                $i++;
                $isActive = ($filter_kat === $c) ? 'active' : '';
            ?>
                <a href="shop.php?kategori=<?= urlencode($c) ?>" class="category-item <?= $isActive ?>">
                    <div class="category-icon"><?= $icon ?></div>
                    <div class="category-label"><?= htmlspecialchars($c) ?></div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <!-- PRODUCT CATALOG -->
    <section id="katalog">
        <div class="section-title">
            🔥 Produk Pilihan
            <?php if ($q !== ''): ?>
                <span style="font-size:0.9rem; font-weight:normal; color:var(--text-muted);">
                    - Hasil pencarian "<?= htmlspecialchars($q) ?>"
                </span>
            <?php endif; ?>
        </div>

        <div class="product-grid">
            <?php if (empty($items)): ?>
                <div style="grid-column: 1 / -1; padding: 4rem 2rem; text-align: center; color: var(--text-muted); background: var(--card-bg); border-radius:16px; border:1px solid var(--card-bd); box-shadow: var(--card-shadow);">
                    <div style="font-size: 3.5rem; margin-bottom:1rem;">🛒</div>
                    <p style="font-weight: 600; font-size:1.1rem; color: var(--text-main);">Maaf, tidak ada produk yang ditemukan.</p>
                    <p style="font-size:0.9rem; margin-bottom:1.5rem;">Coba cari dengan kata kunci lain atau bersihkan filter.</p>
                    <a href="shop.php" class="secondary outline" role="button" style="border-radius:10px; font-weight:700;">Kembali ke Katalog</a>
                </div>
            <?php else: ?>
                <?php foreach ($items as $item): 
                    $stok = (int)$item['stok_toko'] + (int)$item['stok_gudang'];
                ?>
                    <div class="product-card">
                        <?php if (!empty($item['kategori'])): ?>
                            <div class="badge-overlay"><?= htmlspecialchars($item['kategori']) ?></div>
                        <?php endif; ?>

                        <div class="product-image-wrapper">
                            <?php if (!empty($item['gambar']) && file_exists(__DIR__ . '/uploads/items/' . $item['gambar'])): ?>
                                <img src="uploads/items/<?= htmlspecialchars($item['gambar']) ?>" alt="<?= htmlspecialchars($item['nama']) ?>" class="product-image" loading="lazy">
                            <?php else: ?>
                                <div style="display:flex; flex-direction:column; align-items:center; justify-content:center; gap:0.2rem; color:var(--text-muted);">
                                    <span style="font-size:1.6rem;">📦</span>
                                    <span style="font-weight:600; font-size:0.7rem;">No Image</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <div class="product-name" title="<?= htmlspecialchars($item['nama']) ?>">
                                <?= htmlspecialchars($item['nama']) ?>
                            </div>
                            
                            <div class="product-price"><?= rupiah($item['harga_jual1']) ?></div>
                            
                            <div class="product-meta">
                                <?php if ($stok > 5): ?>
                                    <span class="stock-badge ready">Stok <?= $stok ?> <?= htmlspecialchars($item['unit_code'] ?: 'Pcs') ?></span>
                                <?php elseif ($stok > 0): ?>
                                    <span class="stock-badge low">Sisa <?= $stok ?> <?= htmlspecialchars($item['unit_code'] ?: 'Pcs') ?></span>
                                <?php else: ?>
                                    <span class="stock-badge empty">Habis</span>
                                <?php endif; ?>
                                <span style="font-size:0.68rem; color:var(--text-muted);">Toko</span>
                            </div>

                            <?php if ($stok > 0): ?>
                                <form method="post" action="shop.php" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="item_kode" value="<?= htmlspecialchars($item['kode']) ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="add-tocart-btn">🛒 + Beli</button>
                                </form>
                            <?php else: ?>
                                <button disabled class="add-tocart-btn">Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<!-- VISITOR PROMOTION POPUP MODAL -->
<?php if ($shop_popup_active === 1 && !empty($shop_popup_title) && empty($claimed_promo)): ?>
    <div class="popup-overlay" id="promoPopupOverlay">
        <div class="popup-card">
            <?php
            $popupBg = (!empty($shop_popup_image) && file_exists(__DIR__ . '/' . $shop_popup_image))
                ? htmlspecialchars($shop_popup_image)
                : '';
            ?>
            <?php if ($popupBg): ?>
            <div class="popup-img-wrapper" style="background-image: url('<?= $popupBg ?>');">
                <button class="popup-close-btn" onclick="closePromoPopup()">&times;</button>
            </div>
            <?php else: ?>
            <div style="height:12px; background: linear-gradient(90deg,#f59e0b,#d97706); position:relative;">
                <button class="popup-close-btn" style="top:10px; right:10px; position:absolute;" onclick="closePromoPopup()">&times;</button>
            </div>
            <?php endif; ?>
            <div class="popup-body">
                <div style="font-size:2.5rem; margin-bottom:0.5rem;">🎁</div>
                <h3 class="popup-title"><?= htmlspecialchars($shop_popup_title) ?></h3>
                <?php if (!empty($shop_popup_content)): ?>
                <p class="popup-text"><?= nl2br(htmlspecialchars($shop_popup_content)) ?></p>
                <?php endif; ?>

                <?php if (!empty($shop_popup_item) && $shop_popup_pprice > 0): ?>
                <!-- Promo Item Detail Card -->
                <div style="background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.35); border-radius:12px; padding:0.9rem 1rem; margin:0.75rem 0 1rem; text-align:left;">
                    <div style="font-size:0.75rem; font-weight:700; text-transform:uppercase; color:#f59e0b; margin-bottom:0.4rem; letter-spacing:0.05em;">🛍️ Barang Promo Eksklusif</div>
                    <div style="font-weight:800; font-size:1rem; margin-bottom:0.5rem;"><?= htmlspecialchars($shop_popup_iname) ?></div>
                    <div style="display:flex; gap:1rem; align-items:center;">
                        <?php if ($shop_popup_horig > $shop_popup_pprice): ?>
                        <div style="font-size:0.82rem; color:#94a3b8; text-decoration:line-through;"><?= rupiah($shop_popup_horig) ?></div>
                        <?php endif; ?>
                        <div style="font-size:1.35rem; font-weight:900; color:#f59e0b;"><?= rupiah($shop_popup_pprice) ?></div>
                        <?php if ($shop_popup_horig > $shop_popup_pprice): ?>
                        <div style="background:#10b981; color:#fff; font-size:0.7rem; font-weight:800; padding:0.15rem 0.5rem; border-radius:99px;">
                            Hemat <?= rupiah($shop_popup_horig - $shop_popup_pprice) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <a href="shop.php?claim_promo=1" class="popup-btn" style="display:block; margin-bottom:0.5rem; background:linear-gradient(135deg,#f59e0b,#d97706);">
                    🛒 Klaim Promo & Masuk Keranjang!
                </a>
                <?php else: ?>
                <!-- No item linked — legacy claim -->
                <a href="shop.php?claim_promo=1" class="popup-btn" style="display:block; margin-bottom:0.5rem;">
                    🎟️ Klaim Promo Sekarang!
                </a>
                <?php endif; ?>

                <button onclick="closePromoPopup()" style="width:100%; background:transparent; border:1px solid var(--card-bd); color:var(--text-muted); border-radius:8px; padding:0.55rem; font-size:0.82rem; cursor:pointer; margin-top:0.25rem;">
                    Tutup &amp; Belanja Dulu
                </button>
            </div>
        </div>
    </div>

    <script>
    // Show popup once per browser session — but not if promo already claimed
    document.addEventListener("DOMContentLoaded", function() {
        const alreadyClosed = sessionStorage.getItem("promo_popup_closed");
        if (!alreadyClosed) {
            setTimeout(function() {
                const overlay = document.getElementById("promoPopupOverlay");
                if (overlay) overlay.classList.add("show");
            }, 900);
        }
    });

    function closePromoPopup() {
        const overlay = document.getElementById("promoPopupOverlay");
        if (overlay) overlay.classList.remove("show");
        sessionStorage.setItem("promo_popup_closed", "true");
    }
    </script>
<?php endif; ?>

<!-- HERO BANNER CAROUSEL SCRIPT -->
<script>
let currentSlideIndex = 0;
const slides = document.querySelectorAll('#heroCarousel .carousel-slide');
const dots = document.querySelectorAll('#heroCarousel .carousel-dot');
let slideInterval;

function showSlide(index) {
    if (slides.length === 0) return;
    
    // Reset active classes
    slides.forEach(slide => slide.classList.remove('active'));
    dots.forEach(dot => dot.classList.remove('active'));
    
    // Boundary check
    if (index >= slides.length) {
        currentSlideIndex = 0;
    } else if (index < 0) {
        currentSlideIndex = slides.length - 1;
    } else {
        currentSlideIndex = index;
    }
    
    slides[currentSlideIndex].classList.add('active');
    if (dots[currentSlideIndex]) {
        dots[currentSlideIndex].classList.add('active');
    }
}

function moveSlide(direction) {
    showSlide(currentSlideIndex + direction);
    resetInterval();
}

function setSlide(index) {
    showSlide(index);
    resetInterval();
}

function startSlideShow() {
    if (slides.length > 1) {
        slideInterval = setInterval(() => {
            showSlide(currentSlideIndex + 1);
        }, 5000);
    }
}

function resetInterval() {
    clearInterval(slideInterval);
    startSlideShow();
}

document.addEventListener("DOMContentLoaded", () => {
    startSlideShow();
});
</script>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>

