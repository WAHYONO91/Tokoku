<?php
require_once __DIR__.'/config.php';
require_access('TAGIHAN_SUPPLIER');
require_once __DIR__.'/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: supplier_debts.php');
    exit;
}

$purchase_id   = $_POST['purchase_id'] ?? 0;
$supplier_kode = $_POST['supplier_kode'] ?? '';
$jumlah        = (int)($_POST['jumlah'] ?? 0);
$metode        = $_POST['metode'] ?? 'Tunai';
$keterangan    = $_POST['keterangan'] ?? '';
$user          = $_SESSION['user']['username'] ?? 'admin';

if ($jumlah <= 0 || !$purchase_id || !$supplier_kode) {
    header('Location: supplier_debts.php?err=Data tidak valid&sup='.urlencode($supplier_kode));
    exit;
}

try {
    $pdo->beginTransaction();

    // 1. Ambil data purchase untuk cek sisa
    $st = $pdo->prepare("SELECT sisa, invoice_no FROM purchases WHERE id = ? FOR UPDATE");
    $st->execute([$purchase_id]);
    $p = $st->fetch(PDO::FETCH_ASSOC);

    if (!$p) throw new Exception("Invoice tidak ditemukan.");
    if ($jumlah > $p['sisa']) throw new Exception("Jumlah bayar melebihi sisa hutang.");

    // 2. Insert ke supplier_payments
    $stPay = $pdo->prepare("
        INSERT INTO supplier_payments (tanggal, supplier_kode, purchase_id, jumlah, metode, keterangan, created_by)
        VALUES (NOW(), ?, ?, ?, ?, ?, ?)
    ");
    $stPay->execute([$supplier_kode, $purchase_id, $jumlah, $metode, $keterangan, $user]);

    // 3. Update nominal di purchases
    $newSisa = $p['sisa'] - $jumlah;
    $isLunas = ($newSisa <= 0) ? 1 : 0;

    $stUpd = $pdo->prepare("
        UPDATE purchases 
        SET bayar = bayar + ?, sisa = ?, status_lunas = ? 
        WHERE id = ?
    ");
    $stUpd->execute([$jumlah, $newSisa, $isLunas, $purchase_id]);

    // 4. Log activity
    log_activity($pdo, 'SUPPLIER_PAYMENT', "Bayar hutang supplier {$supplier_kode} sebesar ".number_format($jumlah)." untuk faktur {$p['invoice_no']}");

    $pdo->commit();
    header('Location: supplier_debts.php?msg=Pembayaran berhasil disimpan&sup='.urlencode($supplier_kode));

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    header('Location: supplier_debts.php?err='.urlencode($e->getMessage()).'&sup='.urlencode($supplier_kode));
}
