<?php
// /tokoapp/ar_pay.php — Handler pelunasan Piutang Sales
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();
require_role(['admin','kasir']);

// Pastikan tidak ada output sebelum header
if (session_status() === PHP_SESSION_NONE) { session_start(); }

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    throw new RuntimeException('Metode tidak diizinkan.');
  }

  $ar_id   = (int)($_POST['ar_id'] ?? 0);
  $pay_date= trim($_POST['pay_date'] ?? '');
  $method  = trim($_POST['method'] ?? 'cash'); // cash|bank|sales_offset
  $amount  = max(0, (int)($_POST['amount'] ?? 0));
  $note    = trim($_POST['note'] ?? '');

  if ($ar_id <= 0 || $pay_date === '' || $amount <= 0) {
    throw new RuntimeException('Data pembayaran tidak lengkap.');
  }

  // Ambil AR
  $stmt = $pdo->prepare("
    SELECT ar.*, p.invoice_no, s.nama AS supplier_nama
    FROM sales_ar ar
    JOIN purchases p ON p.id = ar.purchase_id
    LEFT JOIN suppliers s ON s.kode = ar.supplier_kode
    WHERE ar.id = :id
    FOR UPDATE
  ");
  $pdo->beginTransaction();
  $stmt->execute([':id'=>$ar_id]);
  $ar = $stmt->fetch(PDO::FETCH_ASSOC);
  if (!$ar) { throw new RuntimeException('Piutang tidak ditemukan.'); }

  // Hitung remain saat ini
  $stmt2 = $pdo->prepare("SELECT COALESCE(SUM(amount),0) FROM ar_payments WHERE ar_id = :id");
  $stmt2->execute([':id'=>$ar_id]);
  $paid_total = (int)$stmt2->fetchColumn();

  $remain = max(0, (int)$ar['amount'] - $paid_total);
  if ($remain <= 0 || $ar['status'] === 'PAID') {
    throw new RuntimeException('Piutang sudah lunas.');
  }
  if ($amount > $remain) {
    throw new RuntimeException('Nominal melebihi sisa piutang.');
  }

  $user_id = $_SESSION['user']['id'] ?? null;

  // 1) Insert pembayaran
  $stmtPay = $pdo->prepare("
    INSERT INTO ar_payments (ar_id, pay_date, method, amount, note, user_id, created_at)
    VALUES (:ar_id, :pay_date, :method, :amount, :note, :user_id, NOW())
  ");
  $stmtPay->execute([
    ':ar_id'   => $ar_id,
    ':pay_date'=> $pay_date,
    ':method'  => $method,
    ':amount'  => $amount,
    ':note'    => $note,
    ':user_id' => $user_id
  ]);

  // 2) Kas masuk (untuk cash/bank)
  if (in_array($method, ['cash','bank'], true)) {
    $stmtCash = $pdo->prepare("
      INSERT INTO cash_ledger (tanggal, shift, user_id, direction, type, amount, note, created_at)
      VALUES (:tanggal, :shift, :user_id, 'IN', 'AR_RECEIPT', :amount, :note, NOW())
    ");
    $shift = null;
    $noteCash = 'Pelunasan AR '.$ar['invoice_no'].' ('.$method.')';
    $stmtCash->execute([
      ':tanggal' => $pay_date,
      ':shift'   => $shift,
      ':user_id' => $user_id,
      ':amount'  => $amount,
      ':note'    => $noteCash
    ]);
  }

  // 3) Update status sales_ar
  $paid_total_new = $paid_total + $amount;
  $remain_new     = max(0, (int)$ar['amount'] - $paid_total_new);
  $new_status     = ($remain_new === 0) ? 'PAID' : 'PARTIAL';

  $stmtUpd = $pdo->prepare("UPDATE sales_ar SET status=:st, updated_at=NOW() WHERE id=:id");
  $stmtUpd->execute([':st'=>$new_status, ':id'=>$ar_id]);

  $pdo->commit();

  $_SESSION['flash'] = 'Pembayaran tersimpan. Sisa: Rp '.number_format($remain_new,0,',','.');
  header('Location: sales_ar_list.php?from='.$ar['due_date'].'&to='.$ar['due_date'].'&status='.$new_status);
  exit;

} catch (Throwable $e) {
  if ($pdo && $pdo->inTransaction()) { $pdo->rollBack(); }
  $_SESSION['flash'] = 'Gagal mencatat pembayaran: '.$e->getMessage();
  header('Location: sales_ar_list.php');
  exit;
}
