<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_login();
require_role(['admin','kasir']);

$id  = (int)($_POST['id'] ?? 0);
$amt = (float)($_POST['amount'] ?? 0);

if ($id <= 0) {
    http_response_code(400);
    die('ID tidak valid');
}
if ($amt <= 0) {
    http_response_code(400);
    die('Nominal harus lebih dari 0');
}

$cashier = $_SESSION['user']['username'] ?? ($_SESSION['username'] ?? 'kasir');

try {
    $pdo->beginTransaction();

    // Lock row piutang
    $stmt = $pdo->prepare("SELECT * FROM member_ar WHERE id = ? FOR UPDATE");
    $stmt->execute([$id]);
    $ar = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$ar) {
        throw new Exception('Data piutang tidak ditemukan');
    }

    $remaining0 = (float)$ar['remaining'];
    if ($amt > $remaining0) {
        throw new Exception('Nominal melebihi sisa piutang');
    }

    // Simpan riwayat pembayaran
    $ins = $pdo->prepare("
        INSERT INTO member_ar_payments (ar_id, amount, cashier)
        VALUES (?, ?, ?)
    ");
    $ins->execute([$id, $amt, $cashier]);

    // Update piutang
    $remaining = $remaining0 - $amt;
    if ($remaining < 0) $remaining = 0;

    $status = ($remaining <= 0.00001) ? 'PAID' : 'OPEN';

    $up = $pdo->prepare("
        UPDATE member_ar
        SET paid = paid + ?, remaining = ?, status = ?
        WHERE id = ?
    ");
    $up->execute([$amt, $remaining, $status, $id]);

    $pdo->commit();

    // Redirect ke receipt
    $amt_q = rawurlencode((string)$amt);
    header("Location: member_ar_receipt.php?id={$id}&amt={$amt_q}");
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(400);
    die('Gagal proses pembayaran: ' . $e->getMessage());
}
