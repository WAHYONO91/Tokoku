<?php
// includes/shop_header.php
$app_theme = 'light';
$app_logo = '/tokoapp/uploads/logo.png';
try {
    $setting = $pdo->query("SELECT store_name, logo_url, theme FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
    if ($setting) {
        $store_name = $setting['store_name'] ?: 'TokoAPP';
        $app_theme = $setting['theme'] ?: 'light';
        if (!empty($setting['logo_url'])) $app_logo = $setting['logo_url'];
    }
} catch (Exception $e) {}

// Calculate Cart count for header
$cart_count = 0;
if (isset($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $c_kode => $c_qty) {
        $cart_count += $c_qty;
    }
}
?>
<!doctype html>
<html lang="id" data-theme="<?= htmlspecialchars($app_theme) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($page_title ?? 'Shop') ?></title>
    <link rel="icon" type="image/png" href="<?= htmlspecialchars($app_logo) ?>">
    <link rel="stylesheet" href="/tokoapp/assets/vendor/pico/pico.min.css">
    <style>
        :root {
            --card-bg: #ffffff;
            --card-bd: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --input-bg: #f8fafc;
            --brand-color: #f97316; /* Shopee-like orange */
            --brand-color-hover: #ea580c;
        }
        [data-theme="dark"] {
            --card-bg: #111827;
            --card-bd: #1f2937;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --input-bg: #0f172a;
            --brand-color: #f97316;
            --brand-color-hover: #ea580c;
        }
        body { 
            padding: 0; 
            margin: 0;
            color: var(--text-main); 
            background: var(--input-bg);
            font-family: system-ui, -apple-system, sans-serif;
        }
        
        /* Modern Marketplace Header */
        .marketplace-header {
            background: var(--brand-color);
            padding: 0.75rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .header-container {
            display: flex; 
            justify-content: space-between; 
            align-items: center;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
            gap: 2rem;
        }
        .brand { 
            font-size: 1.5rem; 
            font-weight: 700; 
            color: #ffffff; 
            text-decoration: none !important; 
            display: flex;
            align-items: center;
            gap: 0.5rem;
            white-space: nowrap;
        }
        .brand:hover { color: #fdfaf6; }

        .header-actions {
            display: flex;
            align-items: center;
            gap: 1.25rem;
        }
        .header-actions a {
            color: #ffffff;
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.4rem;
        }
        .header-actions a:hover { opacity: 0.8; }
        .cart-icon {
            font-size: 1.25rem;
            position: relative;
        }
        .cart-badge {
            position: absolute;
            top: -5px;
            right: -10px;
            background: #ffffff;
            color: var(--brand-color);
            font-size: 0.7rem;
            font-weight: 800;
            padding: 0.1rem 0.4rem;
            border-radius: 12px;
            line-height: 1;
        }
        
        /* Main Container setup */
        main.container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .header-container {
                flex-wrap: wrap;
                gap: 1rem;
                padding: 0.5rem 1rem;
            }
            .brand {
                order: 1;
                font-size: 1.25rem;
            }
            .header-actions {
                order: 2;
                gap: 0.75rem;
            }
            .search-container {
                order: 3;
                flex: none;
                width: 100%; /* Search bar takes full width on mobile */
                max-width: none;
            }
            .marketplace-header {
                padding: 0.5rem 0;
            }
            .header-actions a {
                font-size: 0.85rem;
            }
            .cart-icon {
                font-size: 1.1rem;
            }
        }
    </style>
</head>
<body>
    <header class="marketplace-header">
        <div class="header-container">
            <!-- Brand Logo -->
            <a href="shop.php" class="brand">
                <img src="<?= htmlspecialchars($app_logo) ?>" alt="Logo" style="height:32px; width:auto; border-radius:4px; background:#fff; padding:2px;">
                <?= htmlspecialchars($store_name ?? 'TokoAPP') ?>
            </a>
            
            <!-- User Actions -->
            <div class="header-actions">
                <?php if (isset($_SESSION['member'])): ?>
                    <a href="member_profile.php">👤 <?= htmlspecialchars($_SESSION['member']['nama']) ?></a>
                <?php else: ?>
                    <a href="member_login.php">Daftar | Login</a>
                <?php endif; ?>
                
                <a href="cart.php" class="cart-icon" title="Keranjang Belanja">
                    🛒
                    <?php if($cart_count > 0): ?>
                        <span class="cart-badge"><?= $cart_count ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </header>
    <main class="container">
