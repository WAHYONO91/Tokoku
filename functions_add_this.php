<?php
// Tambahan ini tempel ke akhir functions.php
function auto_update_prices_from_purchase($pdo, $item_kode, $harga_beli){
    $harga_beli = (int)$harga_beli;
    if ($harga_beli <= 0) return;

    $stmt = $pdo->prepare("SELECT harga_jual1, harga_jual2, harga_jual3, harga_jual4 FROM items WHERE kode=?");
    $stmt->execute([$item_kode]);
    $row = $stmt->fetch();
    if (!$row) return;

    $jual1 = (int)round($harga_beli * 1.40);
    $jual2 = (int)round($harga_beli * 1.35);
    $jual3 = (int)round($harga_beli * 1.30);
    $jual4 = (int)round($harga_beli * 1.25);

    $upd = $pdo->prepare("
        UPDATE items
        SET
          harga_jual1 = IF(harga_jual1 IS NULL OR harga_jual1 = 0, ?, harga_jual1),
          harga_jual2 = IF(harga_jual2 IS NULL OR harga_jual2 = 0, ?, harga_jual2),
          harga_jual3 = IF(harga_jual3 IS NULL OR harga_jual3 = 0, ?, harga_jual3),
          harga_jual4 = IF(harga_jual4 IS NULL OR harga_jual4 = 0, ?, harga_jual4),
          harga_beli  = ?
        WHERE kode = ?
    ");
    $upd->execute([$jual1, $jual2, $jual3, $jual4, $harga_beli, $item_kode]);
}
