<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);

header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=items.csv');

$output = fopen('php://output', 'w');
fputcsv($output, ['kode','nama','unit','harga_beli','harga_jual1','harga_jual2','harga_jual3','harga_jual4']);
$stmt = $pdo->query("SELECT kode,nama,unit,harga_beli,harga_jual1,harga_jual2,harga_jual3,harga_jual4 FROM items ORDER BY nama");
while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
  fputcsv($output, $row);
}
fclose($output);
exit;
