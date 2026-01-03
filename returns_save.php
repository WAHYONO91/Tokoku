<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
require_login();

$sale_id = (int)($_POST['sale_id'] ?? 0);
$kode = $_POST['kode'] ?? [];
$nama = $_POST['nama'] ?? [];
$qty = $_POST['qty'] ?? [];
$harga = $_POST['harga'] ?? [];

// Ambil header sale untuk member
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id=?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();
if(!$sale){ die('Faktur tidak ditemukan'); }

$total_refund = 0;
$lines = [];
for($i=0; $i<count($kode); $i++){
  $q = max(0, (int)$qty[$i]);
  if($q<=0) continue;
  $h = (int)$harga[$i];
  $lines[] = ['kode'=>$kode[$i], 'nama'=>$nama[$i], 'qty'=>$q, 'harga'=>$h, 'total'=>$q*$h];
  $total_refund += $q*$h;
}
if(!$lines){ die('Tidak ada item retur'); }

$pdo->beginTransaction();
try{
  $stmt = $pdo->prepare("INSERT INTO sales_returns(sale_id,user_id,member_kode,total_refund) VALUES(?,?,?,?)");
  $stmt->execute([$sale_id, $_SESSION['user']['id'], $sale['member_kode'], $total_refund]);
  $rid = $pdo->lastInsertId();
  $stmtDet = $pdo->prepare("INSERT INTO sales_return_items(return_id,item_kode,qty,harga_satuan,total) VALUES(?,?,?,?,?)");
  foreach($lines as $ln){
    $stmtDet->execute([$rid, $ln['kode'], $ln['qty'], $ln['harga'], $ln['total']]);
    // Stok kembali ke Toko
    adjust_stock($pdo, $ln['kode'], 'toko', (int)$ln['qty']);
  }
  // Kurangi poin member jika ada
  if($sale['member_kode']){
    $set = get_setting($pdo,1);
    $minus = (int) floor($total_refund * (float)$set['points_per_rupiah']);
    if($minus>0){
      $pdo->prepare("UPDATE members SET poin = GREATEST(0, poin - ?) WHERE kode=?")->execute([$minus, $sale['member_kode']]);
    }
  }
  $pdo->commit();
  header('Location: /tokoapp/return_view.php?id='.$rid);
  exit;
} catch(Exception $e){
  $pdo->rollBack();
  die('Gagal simpan retur: '.$e->getMessage());
}
