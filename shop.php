<?php
require_once __DIR__ . '/config.php';

// Enable session for cart if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$sql .= " GROUP BY i.kode ORDER BY (stok_toko + stok_gudang) DESC, i.nama ASC LIMIT 40";

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
/* Modern Shop Layout */
.shop-container {
    padding: 0;
}
.section-title {
    font-size: 1.25rem;
    font-weight: 700;
    margin-bottom: 1rem;
    color: var(--text-main);
    display: flex;
    align-items: center;
    gap: 0.5rem;
}

/* Category Bubbles */
.category-container {
    background: var(--card-bg);
    border-radius: 8px;
    padding: 1.25rem;
    margin-bottom: 2rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}
.category-scroll {
    display: flex;
    gap: 1.5rem;
    overflow-x: auto;
    padding-bottom: 0.5rem;
    scrollbar-width: thin;
}
.category-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 0.5rem;
    cursor: pointer;
    text-decoration: none !important;
    min-width: 80px;
    transition: transform 0.2s;
}
.category-item:hover { transform: translateY(-2px); }
.category-icon {
    width: 50px;
    height: 50px;
    background: #f1f5f9;
    border-radius: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.5rem;
    border: 1px solid var(--card-bd);
}
.category-item.active .category-icon {
    background: #ffedd5;
    border-color: var(--brand-color);
    color: var(--brand-color);
}
[data-theme="dark"] .category-icon { background: #1f2937; }
[data-theme="dark"] .category-item.active .category-icon { background: #431407; }

.category-label {
    font-size: 0.85rem;
    color: var(--text-main);
    text-align: center;
    line-height: 1.2;
    display: -webkit-box;
    -webkit-line-clamp: 2;
    -webkit-box-orient: vertical;
    overflow: hidden;
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 1rem;
}
.product-card {
    background: var(--card-bg);
    border: 1px solid var(--card-bd);
    border-radius: 4px;
    display: flex;
    flex-direction: column;
    transition: transform 0.2s, box-shadow 0.2s;
    overflow: hidden;
    position: relative;
    text-decoration: none !important;
}
.product-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    border-color: var(--brand-color);
}
.product-image-wrapper {
    width: 100%;
    aspect-ratio: 1 / 1; /* Square images */
    background: var(--input-bg);
    overflow: hidden;
    position: relative;
    display: flex;
    align-items: center;
    justify-content: center;
    color: var(--text-muted);
}
.product-image {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.product-info {
    padding: 0.75rem;
    display: flex;
    flex-direction: column;
    flex: 1;
}
.product-name {
    font-size: 0.9rem;
    line-height: 1.4;
    color: var(--text-main);
    margin-bottom: 0.5rem;
    display: -webkit-box;
    -webkit-line-clamp: 2; /* 2 line limit */
    -webkit-box-orient: vertical;
    overflow: hidden;
    flex: 1;
}
.product-price {
    color: var(--brand-color);
    font-size: 1.1rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
}
.product-meta {
    font-size: 0.75rem;
    color: var(--text-muted);
    display: flex;
    justify-content: space-between;
    margin-bottom: 0.75rem;
}
.add-tocart-btn {
    width: 100%;
    padding: 0.4rem;
    font-size: 0.85rem;
    background: var(--brand-color);
    color: white;
    border: none;
    border-radius: 2px;
    cursor: pointer;
    font-weight: 600;
    transition: background 0.2s;
}
.add-tocart-btn:hover { background: var(--brand-color-hover); }
.add-tocart-btn:disabled { background: var(--text-muted); cursor: not-allowed; }

.badge-overlay {
    position: absolute;
    top: 0;
    left: 0;
    background: var(--brand-color);
    color: white;
    font-size: 0.7rem;
    font-weight: 700;
    padding: 0.2rem 0.4rem;
    border-bottom-right-radius: 4px;
    z-index: 10;
}

/* Mobile Adjustments */
@media (max-width: 600px) {
    .product-grid {
        grid-template-columns: repeat(2, 1fr); /* Force 2 columns on small screens */
        gap: 0.5rem;
    }
    .product-info {
        padding: 0.5rem;
    }
    .product-name {
        font-size: 0.8rem;
        margin-bottom: 0.25rem;
    }
    .product-price {
        font-size: 0.95rem;
    }
    .product-meta {
        font-size: 0.65rem;
        margin-bottom: 0.5rem;
    }
    .add-tocart-btn {
        padding: 0.35rem;
        font-size: 0.75rem;
    }
    .category-container {
        padding: 0.75rem;
        margin-bottom: 1rem;
    }
    .category-scroll {
        gap: 1rem;
    }
    .category-icon {
        width: 42px;
        height: 42px;
        font-size: 1.2rem;
    }
    .category-label {
        font-size: 0.75rem;
    }
    .badge-overlay {
        font-size: 0.6rem;
        padding: 0.15rem 0.3rem;
    }
}
</style>

<div class="shop-container">

    <?php if (isset($_GET['added'])): ?>
        <mark style="display:block;margin-bottom:1rem;background:#10b981;color:#fff;padding:0.5rem;border-radius:4px;text-align:center;">
            ✔️ Barang berhasil ditambahkan ke keranjang belanja!
        </mark>
    <?php endif; ?>

    <!-- SEARCH SECTION -->
    <section class="search-body-container" style="margin-bottom: 2rem;">
        <form action="shop.php" method="get" style="display:flex; background:var(--card-bg); padding:0.75rem; border-radius:8px; border:1px solid var(--card-bd); gap:0.5rem; margin:0;">
            <?php if ($filter_kat !== ''): ?>
                <input type="hidden" name="kategori" value="<?= htmlspecialchars($filter_kat) ?>">
            <?php endif; ?>
            <input type="text" name="q" placeholder="Masukkan nama barang yang dicari..." value="<?= htmlspecialchars($q) ?>" style="margin:0; flex:1; background:var(--input-bg); border-color:var(--card-bd);">
            <button type="submit" style="width:auto; margin:0; background:var(--brand-color); border:none; padding:0 1.5rem;">Cari</button>
        </form>
    </section>

    <!-- CATEGORIES ROW -->
    <section class="category-container">
        <div class="section-title">📂 Kategori</div>
        <div class="category-scroll">
            <a href="shop.php" class="category-item <?= $filter_kat === '' ? 'active' : '' ?>">
                <div class="category-icon">🏷️</div>
                <div class="category-label">Semua<br>Kategori</div>
            </a>
            <?php 
            // Simple generic icons for categories based on index
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
    <section>
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
                <div style="grid-column: 1 / -1; padding: 3rem; text-align: center; color: var(--text-muted); background: var(--card-bg); border-radius:8px;">
                    <div style="font-size: 3rem; margin-bottom:1rem;">🛒</div>
                    <p>Maaf, tidak ada produk yang ditemukan.</p>
                    <a href="shop.php" class="secondary outline" role="button">Kembali ke Katalog</a>
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
                                <span>No Image</span>
                            <?php endif; ?>
                        </div>

                        <div class="product-info">
                            <div class="product-name" title="<?= htmlspecialchars($item['nama']) ?>">
                                <?= htmlspecialchars($item['nama']) ?>
                            </div>
                            
                            <div class="product-price"><?= rupiah($item['harga_jual1']) ?></div>
                            
                            <div class="product-meta">
                                <span>Tersisa: <?= $stok ?> <?= htmlspecialchars($item['unit_code']) ?></span>
                            </div>

                            <?php if ($stok > 0): ?>
                                <form method="post" action="shop.php" style="margin:0;">
                                    <input type="hidden" name="action" value="add_to_cart">
                                    <input type="hidden" name="item_kode" value="<?= htmlspecialchars($item['kode']) ?>">
                                    <input type="hidden" name="qty" value="1">
                                    <button type="submit" class="add-tocart-btn">Tambah ke Keranjang</button>
                                </form>
                            <?php else: ?>
                                <button disabled class="add-tocart-btn">Stok Habis</button>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>
