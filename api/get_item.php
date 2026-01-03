<?php
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// Ambil query (bisa kode atau barcode)
$q = isset($_GET['q']) ? trim($_GET['q']) : '';
if ($q === '') {
    echo '{}';
    exit;
}

// QUERY UTAMA: sama seperti versi lama, tapi tambahkan harga_beli
// dan tetap pakai positional parameter (?), ini yang tadi sudah pasti jalan
$sql = 'SELECT 
          kode,
          nama,
          unit_code,
          harga_beli,
          harga_jual1,
          harga_jual2,
          harga_jual3,
          harga_jual4
        FROM items
        WHERE kode = ? OR barcode = ?
        LIMIT 1';

$st = $pdo->prepare($sql);
$st->execute([$q, $q]);
$r = $st->fetch(PDO::FETCH_ASSOC);

// Kalau barang tidak ketemu → kirim objek kosong
if (!$r) {
    echo '{}';
    exit;
}

// Tambah informasi stok gudang & toko (kalau fungsi tersedia)
if (function_exists('get_stock')) {
    $stok_gudang = (int) get_stock($pdo, $r['kode'], 'gudang');
    $stok_toko   = (int) get_stock($pdo, $r['kode'], 'toko');
} else {
    // fallback kalau get_stock belum ada
    $stok_gudang = 0;
    $stok_toko   = 0;
}

$r['stok_gudang'] = $stok_gudang;
$r['stok_toko']   = $stok_toko;

// Kirim JSON ke frontend (POS & pembelian)
echo json_encode($r);
