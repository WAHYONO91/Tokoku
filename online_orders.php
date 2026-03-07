<?php
require_once __DIR__ . '/config.php';
require_login(); // Requires Admin or Kasir
require_once __DIR__ . '/includes/header.php';

require_access('ONLINE_ORDERS');

// Filter
$status = $_GET['status'] ?? '';
$payment = $_GET['payment'] ?? '';

$sql = "SELECT * FROM online_orders WHERE 1=1";
$params = [];

if ($status !== '') {
    $sql .= " AND status = ?";
    $params[] = $status;
}
if ($payment !== '') {
    $sql .= " AND payment_status = ?";
    $params[] = $payment;
}

$sql .= " ORDER BY id DESC LIMIT 200";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

function statusColor($s) {
    return match(strtoupper($s)) {
        'PENDING'   => '#eab308',
        'PROCESSED' => '#3b82f6',
        'SENT'      => '#10b981',
        'CANCELLED' => '#dc2626',
        default     => '#64748b'
    };
}
function payColor($p) {
    return match(strtoupper($p)) {
        'PAID'   => '#10b981',
        'UNPAID' => '#dc2626',
        default  => '#64748b'
    };
}
?>

<article>
    <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom:1.5rem;">
        <h3>🛒 Pesanan Online</h3>
        <a href="shop.php" target="_blank" class="secondary" style="font-size:0.9rem;">Lihat Toko Publik ↗️</a>
    </div>

    <form method="get" style="display:flex; gap:0.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
        <select name="status" style="width:auto; margin:0; padding:0.4rem;">
            <option value="">Semua Status Order</option>
            <option value="PENDING" <?= $status==='PENDING'?'selected':'' ?>>PENDING</option>
            <option value="PROCESSED" <?= $status==='PROCESSED'?'selected':'' ?>>PROCESSED</option>
            <option value="SENT" <?= $status==='SENT'?'selected':'' ?>>SENT</option>
            <option value="CANCELLED" <?= $status==='CANCELLED'?'selected':'' ?>>CANCELLED</option>
        </select>
        <select name="payment" style="width:auto; margin:0; padding:0.4rem;">
            <option value="">Semua Status Bayar</option>
            <option value="UNPAID" <?= $payment==='UNPAID'?'selected':'' ?>>UNPAID</option>
            <option value="PAID" <?= $payment==='PAID'?'selected':'' ?>>PAID</option>
        </select>
        <button type="submit" style="width:auto; margin:0; padding:0.4rem 1rem;">Filter</button>
        <?php if ($status || $payment): ?>
            <a href="online_orders.php" role="button" class="secondary outline" style="padding:0.4rem 1rem; margin:0;">Reset</a>
        <?php endif; ?>
    </form>

    <div style="overflow-x:auto;">
        <table class="table-small" style="min-width: 800px;">
            <thead>
                <tr>
                    <th>#ID</th>
                    <th>Tanggal</th>
                    <th>Penerima</th>
                    <th>Metode</th>
                    <th class="right">Total</th>
                    <th class="center">Status Bayar</th>
                    <th class="center">Status Order</th>
                    <th class="center">Aksi</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($orders)): ?>
                    <tr><td colspan="8">Belum ada pesanan online.</td></tr>
                <?php else: ?>
                    <?php foreach ($orders as $o): ?>
                        <tr>
                            <td><b><?= str_pad($o['id'], 5, '0', STR_PAD_LEFT) ?></b></td>
                            <td><?= date('d-m-Y H:i', strtotime($o['tanggal'])) ?></td>
                            <td><?= htmlspecialchars($o['guest_name']) ?><br><small class="muted"><?= htmlspecialchars($o['guest_phone']) ?></small></td>
                            <td><?= htmlspecialchars($o['payment_method']) ?></td>
                            <td class="right" style="font-weight:600;"><?= rupiah($o['total']) ?></td>
                            <td class="center">
                                <span style="background:<?= payColor($o['payment_status']) ?>; color:#fff; padding:0.15rem 0.4rem; border-radius:4px; font-size:0.75rem;">
                                    <?= htmlspecialchars($o['payment_status']) ?>
                                </span>
                            </td>
                            <td class="center">
                                <span style="background:<?= statusColor($o['status']) ?>; color:#fff; padding:0.15rem 0.4rem; border-radius:4px; font-size:0.75rem;">
                                    <?= htmlspecialchars($o['status']) ?>
                                </span>
                            </td>
                            <td class="center">
                                <a href="online_order_view.php?id=<?= $o['id'] ?>" class="secondary outline" style="padding:0.2rem 0.5rem; font-size:0.8rem;">Detail & Proses</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
