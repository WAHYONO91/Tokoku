<?php
// /tokoapp/api/search_items.php

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');

// Ambil keyword dari query string
$q = isset($_GET['q']) ? trim($_GET['q']) : '';

if ($q === '') {
    echo json_encode([]);
    exit;
}

// Pakai LIKE supaya bisa cari sebagian (contoh: "akua")
$like = '%' . $q . '%';

// Query ke tabel items, mencontoh struktur get_item.php
// Tambahkan kolom barcode karena dipakai di popup
$sql = '
    SELECT 
        kode,
        barcode,
        nama,
        harga_jual1,
        harga_jual2,
        harga_jual3,
        harga_jual4
    FROM items
    WHERE 
        kode    LIKE ?
        OR barcode LIKE ?
        OR nama   LIKE ?
    ORDER BY nama ASC
    LIMIT 200
';

try {
    $st = $pdo->prepare($sql);
    $st->execute([$like, $like, $like]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    if (!$rows) {
        echo json_encode([]);
        exit;
    }

    echo json_encode($rows);
} catch (Throwable $e) {
    // Kalau ada error SQL / koneksi, kembalikan array kosong
    http_response_code(500);
    echo json_encode([]);
}
