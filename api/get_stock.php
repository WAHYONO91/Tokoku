<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';
require_login();

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if ($q === '') {
  echo json_encode(['ok'=>false,'message'=>'q kosong']); exit;
}

// normalisasi: q bisa kode atau barcode
$st = $pdo->prepare("SELECT kode FROM items WHERE kode=? OR barcode=? LIMIT 1");
$st->execute([$q, $q]);
$kode = $st->fetchColumn();

if (!$kode) {
  echo json_encode(['ok'=>false,'message'=>'item tidak ditemukan']); exit;
}

$gudang = get_stock($pdo, $kode, 'gudang');
$toko   = get_stock($pdo, $kode, 'toko');

echo json_encode([
  'ok'     => true,
  'kode'   => $kode,
  'gudang' => (int)$gudang,
  'toko'   => (int)$toko
]);
