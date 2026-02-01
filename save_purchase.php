<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin','kasir']);
require_once __DIR__.'/functions.php';

/* ========= Utilities: cek kolom & insert dinamis ========= */
function table_has_col(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE()
      AND table_name = :t
      AND column_name = :c
    LIMIT 1
  ");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

function build_insert_sql(PDO $pdo, string $table, array $data): array {
  $cols = []; $vals = []; $params = [];
  foreach ($data as $col => $spec) {
    if (!table_has_col($pdo, $table, $col)) continue;
    $isExpr = !empty($spec['expr']);
    $val    = $spec['value'] ?? null;
    $cols[] = $col;
    if ($isExpr) {
      $vals[] = $val;
    } else {
      $ph = ':'.$col;
      $vals[] = $ph;
      $params[$ph] = $val;
    }
  }
  if (empty($cols)) {
    throw new RuntimeException("Tidak ada kolom valid untuk INSERT ke tabel {$table}.");
  }
  $sql = "INSERT INTO {$table} (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
  return ['sql'=>$sql, 'params'=>$params];
}

/* ========= Ambil payload dari form ========= */
$data = json_decode($_POST['payload'] ?? '', true);
if (!$data || !isset($data['items'])) {
  echo "<article><mark>Data pembelian kosong.</mark><p><a href=\"/tokoapp/purchases.php\">Kembali</a></p></article>";
  exit;
}

$invoice_no    = $data['invoice_no']    ?? null;
$supplier_kode = $data['supplier_kode'] ?? null;
$location      = ($data['location'] ?? 'gudang') === 'toko' ? 'toko' : 'gudang';
$purchase_date = $data['purchase_date'] ?? date('Y-m-d');
$discount      = isset($data['discount']) ? (int)$data['discount'] : 0;
$tax           = isset($data['tax'])      ? (int)$data['tax']      : 0;
$items         = $data['items'];

$subtotal = 0;
foreach ($items as $it) {
  $qty = max(0, (int)($it['qty'] ?? 0));
  $hb  = max(0, (int)($it['harga_beli'] ?? 0));
  $subtotal += $qty * $hb;
}
$total = max(0, $subtotal - $discount + $tax);

$user = $_SESSION['user']['username'] ?? 'kasir';

/* ========= Mapping supplier_id jika diperlukan ========= */
$supplier_id = null;
try {
  // hanya mapping kalau:
  // - supplier_kode ada
  // - items punya supplier_id
  // - suppliers punya kolom id dan kode (umumnya)
  if (!empty($supplier_kode) && table_has_col($pdo, 'items', 'supplier_id')) {
    $hasSupId   = table_has_col($pdo, 'suppliers', 'id');
    $hasSupKode = table_has_col($pdo, 'suppliers', 'kode');
    if ($hasSupId && $hasSupKode) {
      $stSup = $pdo->prepare("SELECT id FROM suppliers WHERE kode = :k LIMIT 1");
      $stSup->execute([':k' => $supplier_kode]);
      $supplier_id = $stSup->fetchColumn();
      if ($supplier_id !== false && $supplier_id !== null) {
        $supplier_id = (int)$supplier_id;
      } else {
        $supplier_id = null;
      }
    }
  }
} catch (Throwable $e) {
  $supplier_id = null; // fallback: tidak memblok proses pembelian
}

try {
  $pdo->beginTransaction();

  /* ========== HEADER: purchases (dinamis) ========== */
  $purchaseInsert = build_insert_sql($pdo, 'purchases', [
    'invoice_no'    => ['value'=>$invoice_no],
    'supplier_kode' => ['value'=>$supplier_kode],
    'location'      => ['value'=>$location],
    'purchase_date' => ['value'=>$purchase_date],
    'subtotal'      => ['value'=>$subtotal],
    'discount'      => ['value'=>$discount],
    'tax'           => ['value'=>$tax],
    'total'         => ['value'=>$total],
    'created_by'    => ['value'=>$user],
    'created_at'    => ['value'=>'NOW()', 'expr'=>true],
  ]);
  $stmtH = $pdo->prepare($purchaseInsert['sql']);
  $stmtH->execute($purchaseInsert['params']);
  $purchase_id = (int)$pdo->lastInsertId();

  /* ========== DETAIL: purchase_items (dinamis) ========== */
  foreach ($items as $it) {
    $kode = $it['kode'];
    $nama = $it['nama'];
    $unit = $it['unit'] ?? 'pcs';
    $qty  = max(0, (int)$it['qty']);
    $hb   = max(0, (int)$it['harga_beli']);
    $lineTotal = $qty * $hb;

    // harga jual dari payload – manual penuh, 0 = "kosong"
    $hj1 = isset($it['harga_jual1']) ? max(0, (int)$it['harga_jual1']) : 0;
    $hj2 = isset($it['harga_jual2']) ? max(0, (int)$it['harga_jual2']) : 0;
    $hj3 = isset($it['harga_jual3']) ? max(0, (int)$it['harga_jual3']) : 0;
    $hj4 = isset($it['harga_jual4']) ? max(0, (int)$it['harga_jual4']) : 0;

    // insert ke purchase_items
    $detailInsert = build_insert_sql($pdo, 'purchase_items', [
      'purchase_id' => ['value'=>$purchase_id],
      'item_kode'   => ['value'=>$kode],
      'nama'        => ['value'=>$nama],
      'unit'        => ['value'=>$unit],
      'qty'         => ['value'=>$qty],
      'harga_beli'  => ['value'=>$hb],
      'total'       => ['value'=>$lineTotal],
      'created_at'  => ['value'=>'NOW()', 'expr'=>true],
    ]);
    $stmtD = $pdo->prepare($detailInsert['sql']);
    $stmtD->execute($detailInsert['params']);

    // Tambah stok
    adjust_stock($pdo, $kode, $location, $qty);

    // Update master items:
    // - harga_beli + harga_jual1..4
    // - + supplier terbaru berdasarkan transaksi (INI PERBAIKANNYA)
    $set    = [];
    $params = [':kode'=>$kode];

    // ====== UPDATE SUPPLIER TERAKHIR ======
    // Prioritas: supplier_kode (kalau ada kolomnya), kalau tidak ada baru supplier_id
    if (!empty($supplier_kode) && table_has_col($pdo, 'items', 'supplier_kode')) {
      $set[] = "supplier_kode = :supk";
      $params[':supk'] = $supplier_kode;
    } elseif (!empty($supplier_id) && table_has_col($pdo, 'items', 'supplier_id')) {
      $set[] = "supplier_id = :supid";
      $params[':supid'] = $supplier_id;
    }

    // ====== UPDATE HARGA ======
    if (table_has_col($pdo, 'items', 'harga_beli')) {
      $set[] = "harga_beli = :hb";
      $params[':hb'] = $hb;
    }
    if (table_has_col($pdo, 'items', 'harga_jual1')) {
      $set[] = "harga_jual1 = :h1";
      $params[':h1'] = $hj1;
    }
    if (table_has_col($pdo, 'items', 'harga_jual2')) {
      $set[] = "harga_jual2 = :h2";
      $params[':h2'] = $hj2;
    }
    if (table_has_col($pdo, 'items', 'harga_jual3')) {
      $set[] = "harga_jual3 = :h3";
      $params[':h3'] = $hj3;
    }
    if (table_has_col($pdo, 'items', 'harga_jual4')) {
      $set[] = "harga_jual4 = :h4";
      $params[':h4'] = $hj4;
    }

    if (!empty($set)) {
      $sqlUpdate = "UPDATE items SET ".implode(', ', $set)." WHERE kode = :kode";
      $pdo->prepare($sqlUpdate)->execute($params);
    }
  }

  $pdo->commit();

} catch (Throwable $e) {
  if ($pdo->inTransaction()) $pdo->rollBack();
  echo "<article><mark>Gagal simpan pembelian: ".htmlspecialchars($e->getMessage())."</mark><p><a href=\"/tokoapp/purchases.php\">Kembali</a></p></article>";
  exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pembelian Disimpan</title>
  <link rel="stylesheet" href="/tokoapp/assets/vendor/pico/pico.min.css">
</head>
<body>
<main class="container">
  <article>
    <h3>Pembelian Disimpan</h3>
    <p>No. Faktur: <strong><?=htmlspecialchars($invoice_no)?></strong></p>
    <p>Total: <strong><?=number_format($total,0,',','.')?></strong></p>
    <p style="color:#10b981">
      <small>
        Harga jual barang disimpan sesuai input pada form pembelian (termasuk jika diisi 0).<br>
        Supplier terakhir pada master barang juga diperbarui sesuai supplier transaksi (jika kolom tersedia).
      </small>
    </p>
    <a href="/tokoapp/purchases.php" class="contrast">Input Pembelian Lagi</a>
    <a href="/tokoapp/purchases_report.php" class="secondary">Lihat Laporan Pembelian</a>
  </article>
</main>
</body>
</html>
