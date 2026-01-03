<?php
require_once __DIR__.'/../config.php';
require_login();

// 7 hari terakhir
$st = $pdo->prepare("
  SELECT DATE(created_at) AS tgl, COALESCE(SUM(total),0) AS ttl
  FROM sales
  WHERE status='OK' AND DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
  GROUP BY DATE(created_at)
  ORDER BY DATE(created_at)
");
$st->execute();
$rows = $st->fetchAll();

// bikin array tanggal 7 hari ke belakang biar kalau gak ada transaksi tetap 0
$labels = [];
$values = [];
for($i=6; $i>=0; $i--){
    $d = date('Y-m-d', strtotime("-$i day"));
    $labels[] = date('d/m', strtotime($d));
    // cari di rows
    $found = 0;
    foreach($rows as $r){
        if ($r['tgl'] === $d){
            $found = (int)$r['ttl'];
            break;
        }
    }
    $values[] = $found;
}

header('Content-Type: application/json');
echo json_encode([
    'labels' => $labels,
    'values' => $values
]);
