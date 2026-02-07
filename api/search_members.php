<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../functions.php';

require_access('DASHBOARD');

header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');

// ===== helpers: cek kolom aman =====
function get_table_columns(PDO $pdo, string $table): array {
  static $cache = [];
  $key = strtolower($table);
  if (isset($cache[$key])) return $cache[$key];

  $cols = [];
  try {
    $st = $pdo->query("SHOW COLUMNS FROM `$table`");
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      if (!empty($r['Field'])) $cols[strtolower($r['Field'])] = true;
    }
  } catch (Throwable $e) {}
  $cache[$key] = $cols;
  return $cols;
}
function col_exists(PDO $pdo, string $table, string $col): bool {
  $cols = get_table_columns($pdo, $table);
  return isset($cols[strtolower($col)]);
}
function pick_order_by(PDO $pdo, string $table): string {
  if (col_exists($pdo, $table, 'created_at')) return "ORDER BY created_at DESC";
  if (col_exists($pdo, $table, 'id')) return "ORDER BY id DESC";
  if (col_exists($pdo, $table, 'kode')) return "ORDER BY kode ASC";
  return "";
}

try {
  $table = 'members';

  // WHERE disusun sesuai kolom yang benar-benar ada (biar tidak error)
  $whereParts = [];
  foreach (['kode','nama','alamat','telp','tlp','hp','no_hp','phone'] as $c) {
    if (col_exists($pdo, $table, $c)) $whereParts[] = "$c LIKE :kw";
  }

  $orderBy = pick_order_by($pdo, $table);
  if ($orderBy === '') $orderBy = "ORDER BY kode ASC";

  $sql = "SELECT * FROM `$table`";
  $params = [];

  if ($q !== '' && $whereParts) {
    $sql .= " WHERE (" . implode(" OR ", $whereParts) . ")";
    $params[':kw'] = "%{$q}%";
  }

  $sql .= " $orderBy LIMIT 200";

  $st = $pdo->prepare($sql);
  $st->execute($params);

  $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

  // Normalisasi output: kode, nama, telp, poin, jenis
  $out = [];
  foreach ($rows as $r) {
    // poin: points > poin > point
    $poin = 0;
    if (array_key_exists('points', $r)) $poin = (int)$r['points'];
    elseif (array_key_exists('poin', $r)) $poin = (int)$r['poin'];
    elseif (array_key_exists('point', $r)) $poin = (int)$r['point'];

    // telp: telp > tlp > hp > no_hp > phone
    $telp = '';
    foreach (['telp','tlp','hp','no_hp','phone'] as $c) {
      if (isset($r[$c]) && trim((string)$r[$c]) !== '') { $telp = (string)$r[$c]; break; }
    }

    $jenis = isset($r['jenis']) ? strtolower(trim((string)$r['jenis'])) : '';

    $out[] = [
      'kode'  => (string)($r['kode'] ?? ''),
      'nama'  => (string)($r['nama'] ?? ''),
      'telp'  => $telp,
      'poin'  => $poin,
      'jenis' => $jenis,
    ];
  }

  echo json_encode($out);
} catch (Throwable $e) {
  echo json_encode([]);
}
