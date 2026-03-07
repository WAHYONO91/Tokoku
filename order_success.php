<?php
require_once __DIR__ . '/config.php';

$order_id = (int)($_GET['id'] ?? 0);

if ($order_id <= 0) {
    header('Location: shop.php');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM online_orders WHERE id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<article><mark>Pesanan tidak ditemukan.</mark></article>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

$stmtItems = $pdo->prepare("SELECT * FROM online_order_items WHERE order_id = ?");
$stmtItems->execute([$order_id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

$setting = $pdo->query("SELECT qris_url FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC);
$qris_url = !empty($setting['qris_url']) ? $setting['qris_url'] : '/tokoapp/assets/qris_placeholder.png';

$page_title = "Pesanan Berhasil - " . ($store_name ?? 'TokoAPP');
require_once __DIR__ . '/includes/shop_header.php';
?>

<article style="max-width:600px; margin: 0 auto; text-align:center;">
    <div style="font-size:3rem; margin-bottom:1rem;">🎉</div>
    <h2 style="margin-bottom:0.5rem; color:#10b981;">Pesanan Berhasil Dibuat!</h2>
    <p>Terima kasih <strong><?= htmlspecialchars($order['guest_name']) ?></strong>, pesanan Anda sedang kami proses.</p>

    <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1.25rem; margin:1.5rem 0; text-align:left;">
        <h4 style="margin-top:0;">Detail Pesanan #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h4>
        <div style="display:grid; grid-template-columns:120px 1fr; gap:0.5rem; font-size:0.9rem; margin-bottom:1rem;">
            <div class="muted">Tanggal:</div><div><?= htmlspecialchars(date('d-m-Y H:i', strtotime($order['tanggal']))) ?></div>
            <div class="muted">Penerima:</div><div><?= htmlspecialchars($order['guest_name']) ?> <br><?= htmlspecialchars($order['guest_phone']) ?></div>
            <div class="muted">Alamat:</div><div><?= nl2br(htmlspecialchars($order['guest_address'])) ?></div>
            <?php if (!empty($order['note'])): ?>
                <div class="muted">Catatan:</div><div><?= htmlspecialchars($order['note']) ?></div>
            <?php endif; ?>
            <div class="muted">Metode:</div><div><strong><?= htmlspecialchars($order['payment_method']) ?></strong></div>
            <div class="muted">Total:</div><div style="font-weight:700; color:#10b981; font-size:1.1rem;"><?= rupiah($order['total']) ?></div>
        </div>

        <h5 style="margin-bottom:0.5rem;">Produk yang dibeli:</h5>
        <ul style="list-style:none; padding:0; margin:0; font-size:0.9rem;">
            <?php foreach ($items as $item): ?>
                <li style="display:flex; justify-content:space-between; border-top:1px dashed var(--card-bd, #1f2937); padding-top:0.3rem; margin-top:0.3rem;">
                    <span><?= htmlspecialchars($item['nama_item']) ?> <small class="muted">x<?= $item['qty'] ?></small></span>
                    <span><?= rupiah($item['total']) ?></span>
                </li>
            <?php endforeach; ?>
        </ul>
    </div>

    <?php if ($order['payment_method'] === 'QRIS'): ?>
        <div style="background:#f0fdf4; border:1px solid #16a34a; border-radius:8px; padding:1.25rem; color:#064e3b; margin-bottom:1.5rem;">
            <h4 style="margin-top:0; color:#16a34a;">Instruksi Pembayaran QRIS</h4>
            <p style="font-size:0.9rem;">Silakan scan QR Code di bawah ini menggunakan aplikasi Mobile Banking atau e-Wallet Anda (Gopay, OVO, Dana, LinkAja, BCA, dll).</p>
            
            <div style="background:#fff; padding:1rem; border-radius:8px; display:inline-block; margin-bottom:1rem;">
                <img src="<?= htmlspecialchars($qris_url) ?>" alt="QRIS Code" style="max-width:200px; display:block; margin:0 auto;" onerror="this.src='data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'200\' height=\'200\'><rect width=\'200\' height=\'200\' fill=\'%23eee\'/><text x=\'100\' y=\'100\' font-family=\'Arial\' font-size=\'14\' text-anchor=\'middle\' fill=\'%23999\' dy=\'.3em\'>QRIS IMAGE</text></svg>';">
            </div>

            <p style="font-size:0.9rem; font-weight:bold;">Total Pembayaran: <?= rupiah($order['total']) ?></p>
            <p style="font-size:0.85rem; margin-bottom:0;">Pesanan akan diproses HANYA setelah pembayaran berhasil diverifikasi oleh admin.</p>
        </div>
    <?php elseif ($order['payment_method'] === 'COD'): ?>
        <div style="background:#f8fafc; border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
            <p style="margin:0; font-size:0.95rem;">Anda memilih pembayaran <strong>Bayar di Tempat (COD)</strong>. Siapkan uang tunai sejumlah <strong style="color:#10b981;"><?= rupiah($order['total']) ?></strong> saat kurir tiba.</p>
        </div>
    <?php endif; ?>

    <a href="shop.php" role="button" class="secondary">🔙 Kembali ke Toko</a>
</article>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>
