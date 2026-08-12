<?php
require_once __DIR__ . '/config.php';

// Enable session for cart if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cart = $_SESSION['cart'] ?? [];

// Handle update or delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Handle Delete Item from main form button
    if (isset($_POST['delete_item'])) {
        $del_kode = $_POST['delete_item'];
        unset($_SESSION['cart'][$del_kode]);
        // Also remove custom price for this item if it exists
        if (isset($_SESSION['cart_custom_prices'][$del_kode])) {
            unset($_SESSION['cart_custom_prices'][$del_kode]);
        }
        header('Location: cart.php?deleted=1');
        exit;
    }

    if (isset($_POST['action']) && $_POST['action'] === 'update') {
        foreach ($_POST['qty'] as $kode => $qty) {
            $qty = (int)$qty;
            if ($qty > 0) {
                $_SESSION['cart'][$kode] = $qty;
            } else {
                unset($_SESSION['cart'][$kode]);
            }
        }
        header('Location: cart.php?updated=1');
        exit;
    }
}

// Fetch item details for items in cart
$cart_items = [];
$total = 0;

if (!empty($cart)) {
    $in = str_repeat('?,', count($cart) - 1) . '?';
    $stmt = $pdo->prepare("SELECT kode, nama, gambar, harga_jual1, unit_code FROM items WHERE kode IN ($in)");
    $stmt->execute(array_keys($cart));
    $items_db = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items_db as $item) {
        $kode = $item['kode'];
        $qty  = $cart[$kode];
        // Use custom promo price if set, otherwise normal selling price
        $custom_prices = $_SESSION['cart_custom_prices'] ?? [];
        $harga = isset($custom_prices[$kode]) ? (int)$custom_prices[$kode] : $item['harga_jual1'];
        $is_promo = isset($custom_prices[$kode]);
        $subtotal = $qty * $harga;
        $total += $subtotal;

        $item['harga_jual1'] = $harga;  // override for display
        $item['qty']         = $qty;
        $item['subtotal']    = $subtotal;
        $item['is_promo']    = $is_promo;
        $cart_items[] = $item;
    }
}

$page_title = "Keranjang Belanja - " . ($store_name ?? 'TokoAPP');
require_once __DIR__ . '/includes/shop_header.php';
?>

<article>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h2>🛒 Keranjang Belanja</h2>
        <a href="shop.php" class="secondary">🔙 Lanjut Belanja</a>
    </div>

    <?php if (isset($_GET['updated'])): ?>
        <mark style="display:block;margin-bottom:1rem;background:#10b981;color:#fff;">✔️ Keranjang diperbarui.</mark>
    <?php endif; ?>
    <?php if (isset($_GET['deleted'])): ?>
        <mark style="display:block;margin-bottom:1rem;background:#dc2626;color:#fff;">🗑️ Barang dihapus dari keranjang.</mark>
    <?php endif; ?>
    <?php if (isset($_GET['promo_added'])): ?>
        <mark style="display:block;margin-bottom:1.2rem;background:linear-gradient(135deg,#f59e0b,#d97706);color:#fff;padding:0.8rem 1rem;border-radius:10px;font-weight:700;">
            🎉 Selamat! Barang promo berhasil masuk ke keranjang dengan harga spesial!
        </mark>
    <?php endif; ?>
    <?php if (!empty($_SESSION['claimed_promo'])): ?>
        <div style="background:rgba(245,158,11,0.12); border:1px solid rgba(245,158,11,0.35); border-radius:10px; padding:0.65rem 1rem; margin-bottom:1rem; font-size:0.85rem; display:flex; justify-content:space-between; align-items:center;">
            <span>🎟️ <strong>Promo aktif:</strong> <?= htmlspecialchars($_SESSION['claimed_promo']) ?></span>
            <a href="shop.php?cancel_promo=1" style="font-size:0.75rem; color:#ef4444;">✕ Batalkan</a>
        </div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>
        <div style="text-align:center; padding: 3rem;">
            <h3>Keranjang masih kosong</h3>
            <p>Yuk, cari barang-barang yang Anda butuhkan!</p>
            <br>
            <a href="shop.php" role="button">Mulai Belanja</a>
        </div>
    <?php else: ?>
        <form method="post" action="cart.php">
            <input type="hidden" name="action" value="update">
            <style>
                .cart-table th, .cart-table td { padding: 0.5rem; }
                @media (max-width: 600px) {
                    .cart-table thead { display: none; }
                    .cart-table tr { 
                        display: block; 
                        margin-bottom: 1rem; 
                        border: 1px solid var(--card-bd);
                        border-radius: 8px;
                        padding: 0.5rem;
                        background: var(--card-bg);
                    }
                    .cart-table td { 
                        display: flex; 
                        justify-content: space-between; 
                        align-items: center;
                        border: none;
                        padding: 0.25rem 0.5rem;
                        text-align: right !important;
                    }
                    .cart-table td:before {
                        content: attr(data-label);
                        font-weight: 700;
                        margin-right: 1rem;
                        text-align: left;
                        font-size: 0.8rem;
                        color: var(--text-muted);
                    }
                    .cart-table td:first-child { justify-content: center; }
                    .cart-table td:first-child:before { content: none; }
                }
            </style>
            <div style="overflow-x:auto;">
                <table class="table-small cart-table">
                    <thead>
                        <tr>
                            <th>Gambar</th>
                            <th>Produk</th>
                            <th class="right">Harga</th>
                            <th class="center" style="width:120px;">Qty</th>
                            <th class="right">Subtotal</th>
                            <th class="center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($cart_items as $item): ?>
                            <tr>
                                <td data-label="Gambar">
                                    <?php if (!empty($item['gambar']) && file_exists(__DIR__ . '/uploads/items/' . $item['gambar'])): ?>
                                        <img src="uploads/items/<?= htmlspecialchars($item['gambar']) ?>" alt="img" style="max-width: 60px; border-radius: 4px;">
                                    <?php else: ?>
                                        <div style="width:60px; height:40px; background:#1e293b; color:#94a3b8; display:flex; align-items:center; justify-content:center; font-size:10px; border-radius:4px;">No IMG</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Produk">
                                    <?= htmlspecialchars($item['nama']) ?>
                                    <?php if (!empty($item['is_promo'])): ?>
                                        <span style="display:inline-block; margin-left:0.4rem; background:#f59e0b; color:#fff; font-size:0.65rem; font-weight:800; padding:0.1rem 0.45rem; border-radius:99px;">🔥 PROMO</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Harga" class="right">
                                    <?= rupiah($item['harga_jual1']) ?>
                                    <?php if (!empty($item['is_promo'])): ?>
                                        <div style="font-size:0.7rem; color:#10b981; font-weight:700;">Harga Promo</div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Qty" class="center">
                                    <input type="number" name="qty[<?= htmlspecialchars($item['kode']) ?>]" value="<?= $item['qty'] ?>" min="1" style="width:80px; margin:0; padding: 0.3rem;">
                                    <span style="font-size:0.8rem; color:var(--text-muted, #94a3b8); display:block;"><?= htmlspecialchars($item['unit_code']) ?></span>
                                </td>
                                <td data-label="Subtotal" class="right" style="font-weight:600;"><?= rupiah($item['subtotal']) ?></td>
                                <td data-label="Aksi" class="center">
                                    <button type="submit" name="delete_item" value="<?= htmlspecialchars($item['kode']) ?>" 
                                            class="secondary" 
                                            style="padding:0.2rem 0.5rem; font-size:0.8rem; background:#dc2626; border-color:#dc2626; color:#fff; width:auto; margin:0;"
                                            onclick="return confirm('Hapus barang ini?');">Hapus</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="right"><strong>Total Belanja:</strong></td>
                            <td class="right"><strong style="font-size:1.25rem; color:#10b981;"><?= rupiah($total) ?></strong></td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div style="display:flex; justify-content:flex-end; gap:1rem; margin-top:1.5rem;">
                <button type="submit" class="secondary outline" style="width:auto;">🔄 Update Keranjang</button>
                <a href="checkout.php" role="button" style="width:auto;">Lanjut Pembayaran ➡️</a>
            </div>
        </form>
    <?php endif; ?>
</article>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>
