<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function out(array $arr, int $code = 200): void {
  http_response_code($code);
  echo json_encode($arr, JSON_UNESCAPED_UNICODE);
  exit;
}

$q = trim((string)($_GET['q'] ?? ''));
if ($q === '') out(['ok'=>false, 'msg'=>'empty'], 400);

// batasi panjang input biar nggak jadi beban
if (mb_strlen($q) > 80) out(['ok'=>false, 'msg'=>'query too long'], 400);

// ===== mini-cache 2 detik (ngurangi spam fetch saat user ngetik) =====
$cacheKey  = 'item_stock_' . md5($q);
$cacheFile = sys_get_temp_dir() . DIRECTORY_SEPARATOR . $cacheKey . '.json';
if (is_file($cacheFile) && (time() - filemtime($cacheFile) <= 2)) {
  readfile($cacheFile);
  exit;
}

try {
  // 1) prioritas exact kode/barcode
  $st = $pdo->prepare("SELECT kode, nama, barcode
                       FROM items
                       WHERE kode = ? OR barcode = ?
                       LIMIT 1");
  $st->execute([$q, $q]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  // 2) fallback: nama LIKE (biar bisa ketik nama barang)
  if (!$row) {
    $like = '%'.$q.'%';
    $st2 = $pdo->prepare("SELECT kode, nama, barcode
                          FROM items
                          WHERE nama LIKE ?
                          ORDER BY
                            CASE WHEN nama LIKE ? THEN 0 ELSE 1 END,
                            LENGTH(nama) ASC
                          LIMIT 1");
    $st2->execute([$like, $q.'%']);
    $row = $st2->fetch(PDO::FETCH_ASSOC);
  }

  if (!$row) out(['ok'=>false, 'msg'=>'not found'], 404);

  $kode    = (string)($row['kode'] ?? '');
  $nama    = (string)($row['nama'] ?? '');
  $barcode = (string)($row['barcode'] ?? '');

  $gudang = (int)get_stock($pdo, $kode, 'gudang');
  $toko   = (int)get_stock($pdo, $kode, 'toko');

  $resp = [
    'ok'      => true,
    'q'       => $q,
    'kode'    => $kode,
    'barcode' => $barcode,
    'nama'    => $nama,
    'stocks'  => [
      'gudang' => $gudang,
      'toko'   => $toko
    ],
    'ts'      => date('c')
  ];

  @file_put_contents($cacheFile, json_encode($resp, JSON_UNESCAPED_UNICODE));

  out($resp, 200);

} catch (Throwable $e) {
  out(['ok'=>false, 'msg'=>'server error', 'detail'=>$e->getMessage()], 500);
}
