<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);

$kode = $_GET['kode'] ?? '';
if($kode===''){
  header("Location: items.php");
  exit;
}

log_activity($pdo, 'DELETE_ITEM', "Menghapus item dengan kode: $kode");
$stmt = $pdo->prepare("DELETE FROM items WHERE kode=?");
$stmt->execute([$kode]);
// stok tidak dihapus biar history aman
header("Location: items.php");
exit;
