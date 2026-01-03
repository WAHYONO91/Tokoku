<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);
require_once __DIR__.'/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id<=0){ header("Location: purchases_report.php"); exit; }

$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id=?");
$stmt->execute([$id]);
$purchase = $stmt->fetch();
if(!$purchase){ header("Location: purchases_report.php"); exit; }

$location = $purchase['location'] ?? 'gudang';

$itemsStmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id=?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

try{
  $pdo->beginTransaction();
  foreach($items as $it){
    adjust_stock($pdo, $it['item_kode'], $location, - (int)$it['qty']);
  }
  $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id=?")->execute([$id]);
  $pdo->prepare("DELETE FROM purchases WHERE id=?")->execute([$id]);
  $pdo->commit();
} catch(Exception $e){
  if($pdo->inTransaction()) $pdo->rollBack();
  echo "Gagal hapus pembelian: ".htmlspecialchars($e->getMessage());
  exit;
}

header("Location: purchases_report.php");
exit;
