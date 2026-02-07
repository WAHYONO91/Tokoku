<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin','kasir']);
require_once __DIR__.'/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
  header("Location: sales_report.php");
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if(!$sale){
  echo "Transaksi tidak ditemukan.";
  exit;
}

if (($sale['status'] ?? 'OK') !== 'OK') {
  header("Location: sales_report.php");
  exit;
}

$itemsStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

try {
  $pdo->beginTransaction();

  // kembalikan stok ke lokasi 'toko'
  foreach($items as $it){
    adjust_stock($pdo, $it['item_kode'], 'toko', (int)$it['qty']);
  }

  // update status
  $upd = $pdo->prepare("UPDATE sales SET status='CANCEL' WHERE id=?");
  $upd->execute([$id]);

  log_activity($pdo, 'CANCEL_SALE', "Membatalkan transaksi ID: $id (Member: " . ($sale['member_kode'] ?? 'Umum') . ")");

  $pdo->commit();
} catch (Exception $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo "Gagal membatalkan: ".htmlspecialchars($e->getMessage());
  exit;
}

header("Location: sales_report.php");
exit;
