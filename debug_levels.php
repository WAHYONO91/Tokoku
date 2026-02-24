<?php
require 'config.php';
$stmt = $pdo->query("SELECT id, sale_id, item_kode, level, harga FROM sale_items ORDER BY id DESC LIMIT 10");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
echo "LAST 10 SALE ITEMS:\n";
foreach($rows as $r) {
    echo "ID: {$r['id']}, SaleID: {$r['sale_id']}, Kode: {$r['item_kode']}, Level: {$r['level']}, Harga: {$r['harga']}\n";
}
