<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_access('REPORT_STOCK');

/* ======================
   Helper: cek tabel & kolom (cache)
   ====================== */
function table_exists(PDO $pdo, string $table): bool {
  static $cache = [];
  if (array_key_exists($table, $cache)) return $cache[$table];

  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = :t
    LIMIT 1
  ");
  $st->execute([':t'=>$table]);
  $cache[$table] = (bool)$st->fetchColumn();
  return $cache[$table];
}

function column_exists(PDO $pdo, string $table, string $col): bool {
  static $cache = [];
  $key = $table.'.'.$col;
  if (array_key_exists($key, $cache)) return $cache[$key];

  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
    LIMIT 1
  ");
  $st->execute([':t'=>$table, ':c'=>$col]);
  $cache[$key] = (bool)$st->fetchColumn();
  return $cache[$key];
}

/**
 * Filter & Export handling
 */
$search    = trim($_GET['q'] ?? '');
$limitOpt  = $_GET['limit'] ?? '100'; // '100', '500', 'all'
$export    = isset($_GET['export']) && $_GET['export'] === '1';
$supplierK = trim($_GET['supplier'] ?? ''); // filter by suppliers.kode

// Helper angka (pastikan integer)
if (!function_exists('to_int')) {
  function to_int($v){ return (int)($v ?? 0); }
}

/**
 * Cek apakah supplier join tersedia
 * items.supplier_kode = suppliers.kode -> suppliers.nama
 */
$canJoinSupplier =
  table_exists($pdo, 'suppliers') &&
  column_exists($pdo, 'items', 'supplier_kode') &&
  column_exists($pdo, 'suppliers', 'kode') &&
  column_exists($pdo, 'suppliers', 'nama');

// List supplier untuk dropdown (kalau tersedia)
$suppliers = [];
if ($canJoinSupplier) {
  $suppliers = $pdo->query("SELECT kode, nama FROM suppliers ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);
}

// ======================
// Build WHERE & LIMIT
// ======================
$whereSql = " WHERE 1=1 ";
$params   = [];

if ($search !== '') {
  $whereSql .= " AND (i.kode LIKE :q1 OR i.nama LIKE :q2)";
  $params['q1'] = '%'.$search.'%';
  $params['q2'] = '%'.$search.'%';
}

if ($supplierK !== '') {
  if ($canJoinSupplier) {
    // filter supplier by kode
    $whereSql .= " AND i.supplier_kode = :supk";
    $params['supk'] = $supplierK;
  } else {
    // fallback: kalau tabel suppliers tidak ada, pakai unit sebagai “supplier”
    $whereSql .= " AND i.unit LIKE :supname";
    $params['supname'] = '%'.$supplierK.'%'; // di mode ini supplierK dianggap teks
  }
}

// Hitung total item (untuk info "X dari Y")
$countSql  = "SELECT COUNT(*) FROM items i".$whereSql;
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total_items = (int)$countStmt->fetchColumn();

// Limit tampilan
$limitSql = '';
if (in_array($limitOpt, ['100','500'], true)) {
  $limitSql = " LIMIT ".(int)$limitOpt;
} // 'all' → tanpa LIMIT

// Ambil data item + supplier_name (sama seperti index.php)
if ($canJoinSupplier) {
  $listSql = "
    SELECT
      i.*,
      COALESCE(NULLIF(s.nama,''), NULLIF(i.unit,''), 'Supplier Umum') AS supplier_name
    FROM items i
    LEFT JOIN suppliers s ON s.kode = i.supplier_kode
    $whereSql
    ORDER BY i.nama
    $limitSql
  ";
} else {
  $listSql = "
    SELECT
      i.*,
      COALESCE(NULLIF(i.unit,''), 'Supplier Umum') AS supplier_name
    FROM items i
    $whereSql
    ORDER BY i.nama
    $limitSql
  ";
}

$listStmt = $pdo->prepare($listSql);
$listStmt->execute($params);
$items = $listStmt->fetchAll(PDO::FETCH_ASSOC);
$current_count = count($items);

// Agregat total
$total_aset      = 0;
$total_omzet_h1  = 0;
$total_omzet_h2  = 0;
$total_omzet_h3  = 0;
$total_omzet_h4  = 0;

/**
 * MODE EXPORT EXCEL
 */
if ($export) {
  header("Content-Type: application/vnd.ms-excel; charset=utf-8");
  header("Content-Disposition: attachment; filename=\"laporan_stok_".date('Ymd_His').".xls\"");
  header("Pragma: no-cache");
  header("Expires: 0");

  echo "<table border=\"1\">";
  echo "<thead>
    <tr>
      <th rowspan=\"2\">Kode</th>
      <th rowspan=\"2\">Nama</th>
      <th rowspan=\"2\">Supplier</th>
      <th rowspan=\"2\">Gudang</th>
      <th rowspan=\"2\">Toko</th>
      <th rowspan=\"2\">Total Qty</th>
      <th rowspan=\"2\">Min Stok</th>
      <th rowspan=\"2\">Harga Beli</th>
      <th colspan=\"4\">Harga Jual</th>
      <th rowspan=\"2\">Nilai Aset (Total Qty × Harga Beli)</th>
      <th colspan=\"4\">Potensi Omzet (Total Qty × HJ)</th>
    </tr>
    <tr>
      <th>H1</th><th>H2</th><th>H3</th><th>H4</th>
      <th>H1</th><th>H2</th><th>H3</th><th>H4</th>
    </tr>
  </thead><tbody>";

  if (!$items) {
    echo "<tr><td colspan=\"16\">Belum ada data.</td></tr>";
  } else {
    foreach($items as $it){
      $kode = $it['kode'];

      $g = get_stock($pdo, $kode, 'gudang');
      $t = get_stock($pdo, $kode, 'toko');
      $qty_total = to_int($g) + to_int($t);

      $hb  = to_int($it['harga_beli']  ?? 0);
      $h1  = to_int($it['harga_jual1'] ?? 0);
      $h2  = to_int($it['harga_jual2'] ?? 0);
      $h3  = to_int($it['harga_jual3'] ?? 0);
      $h4  = to_int($it['harga_jual4'] ?? 0);

      $supplier = $it['supplier_name'] ?? 'Supplier Umum';

      $nilai_aset = $qty_total * $hb;

      $omzet_h1 = $qty_total * $h1;
      $omzet_h2 = $qty_total * $h2;
      $omzet_h3 = $qty_total * $h3;
      $omzet_h4 = $qty_total * $h4;

      $total_aset     += $nilai_aset;
      $total_omzet_h1 += $omzet_h1;
      $total_omzet_h2 += $omzet_h2;
      $total_omzet_h3 += $omzet_h3;
      $total_omzet_h4 += $omzet_h4;

      echo "<tr>";
      echo "<td>".htmlspecialchars($kode)."</td>";
      echo "<td>".htmlspecialchars($it['nama'])."</td>";
      echo "<td>".htmlspecialchars($supplier)."</td>";
      echo "<td>".(int)$g."</td>";
      echo "<td>".(int)$t."</td>";
      echo "<td><strong>".$qty_total."</strong></td>";
      echo "<td>".(int)($it['min_stock'] ?? 0)."</td>";
      echo "<td>".$hb."</td>";
      echo "<td>".$h1."</td>";
      echo "<td>".$h2."</td>";
      echo "<td>".$h3."</td>";
      echo "<td>".$h4."</td>";
      echo "<td><strong>".$nilai_aset."</strong></td>";
      echo "<td>".$omzet_h1."</td>";
      echo "<td>".$omzet_h2."</td>";
      echo "<td>".$omzet_h3."</td>";
      echo "<td>".$omzet_h4."</td>";
      echo "</tr>";
    }
  }

  echo "</tbody><tfoot>
    <tr>
      <th colspan=\"7\">TOTAL</th>
      <th>—</th><th>—</th><th>—</th><th>—</th><th>—</th>
      <th><strong>".$total_aset."</strong></th>
      <th><strong>".$total_omzet_h1."</strong></th>
      <th><strong>".$total_omzet_h2."</strong></th>
      <th><strong>".$total_omzet_h3."</strong></th>
      <th><strong>".$total_omzet_h4."</strong></th>
    </tr>
  </tfoot></table>";
  exit;
}

/**
 * MODE TAMPIL DI BROWSER
 */
require_once __DIR__.'/includes/header.php';
?>
<article>
  <h3>Laporan Stok</h3>
  <p>Menampilkan stok gudang dan toko, supplier, harga, nilai aset, serta potensi omzet per level harga.</p>

  <!-- Filter & Export -->
  <form method="get" class="no-print" style="margin-bottom:.75rem;display:flex;flex-wrap:wrap;gap:.6rem;align-items:flex-end;">
    <div>
      <label>Cari Barang<br>
        <input type="text"
               name="q"
               value="<?= htmlspecialchars($search) ?>"
               placeholder="Kode / Nama barang"
               style="min-width:220px;">
      </label>
    </div>

    <div>
      <label>Supplier<br>
        <?php if($canJoinSupplier): ?>
          <select name="supplier" style="min-width:220px;">
            <option value="">Semua Supplier</option>
            <?php foreach($suppliers as $s): ?>
              <option value="<?= htmlspecialchars($s['kode']) ?>"
                <?= $supplierK===(string)$s['kode'] ? 'selected' : '' ?>>
                <?= htmlspecialchars($s['nama']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        <?php else: ?>
          <!-- fallback kalau tabel suppliers tidak ada -->
          <input type="text" name="supplier" value="<?= htmlspecialchars($supplierK) ?>" placeholder="Nama supplier (unit)">
        <?php endif; ?>
      </label>
    </div>

    <div>
      <label>Tampilkan<br>
        <select name="limit">
          <option value="100" <?= $limitOpt==='100'?'selected':''; ?>>100</option>
          <option value="500" <?= $limitOpt==='500'?'selected':''; ?>>500</option>
          <option value="all" <?= $limitOpt==='all'?'selected':''; ?>>Semua</option>
        </select>
      </label>
    </div>

    <div>
      <button type="submit">Terapkan</button>
    </div>

    <div>
      <button type="submit" name="export" value="1">Export Excel</button>
    </div>
  </form>

  <p class="no-print" style="margin-top:-.3rem;margin-bottom:.6rem;font-size:.85rem;">
    Menampilkan <strong><?= number_format($current_count,0,',','.') ?></strong>
    dari total <strong><?= number_format($total_items,0,',','.') ?></strong> item.
  </p>

  <div style="overflow:auto">
    <table class="table-small" style="min-width:1200px">
      <thead>
        <tr>
          <th rowspan="2">Kode</th>
          <th rowspan="2">Nama</th>
          <th rowspan="2">Supplier</th>
          <th class="right" rowspan="2">Gudang</th>
          <th class="right" rowspan="2">Toko</th>
          <th class="right" rowspan="2">Total Qty</th>
          <th class="right" rowspan="2">Min Stok</th>
          <th class="right" rowspan="2">Harga Beli</th>
          <th class="right" colspan="4">Harga Jual</th>
          <th class="right" rowspan="2">Nilai Aset<br><small>Total Qty × Harga Beli</small></th>
          <th class="right" colspan="4">Potensi Omzet (Total Qty × HJ)</th>
        </tr>
        <tr>
          <th class="right">H1</th>
          <th class="right">H2</th>
          <th class="right">H3</th>
          <th class="right">H4</th>
          <th class="right">H1</th>
          <th class="right">H2</th>
          <th class="right">H3</th>
          <th class="right">H4</th>
        </tr>
      </thead>

      <tbody>
        <?php if(!$items): ?>
          <tr><td colspan="16">Belum ada data.</td></tr>
        <?php else: ?>
          <?php foreach($items as $it):
            $kode = $it['kode'];

            $g = get_stock($pdo, $kode, 'gudang');
            $t = get_stock($pdo, $kode, 'toko');
            $qty_total = to_int($g) + to_int($t);

            $hb  = to_int($it['harga_beli']  ?? 0);
            $h1  = to_int($it['harga_jual1'] ?? 0);
            $h2  = to_int($it['harga_jual2'] ?? 0);
            $h3  = to_int($it['harga_jual3'] ?? 0);
            $h4  = to_int($it['harga_jual4'] ?? 0);

            $supplier = $it['supplier_name'] ?? 'Supplier Umum';

            $nilai_aset = $qty_total * $hb;

            $omzet_h1 = $qty_total * $h1;
            $omzet_h2 = $qty_total * $h2;
            $omzet_h3 = $qty_total * $h3;
            $omzet_h4 = $qty_total * $h4;

            $total_aset     += $nilai_aset;
            $total_omzet_h1 += $omzet_h1;
            $total_omzet_h2 += $omzet_h2;
            $total_omzet_h3 += $omzet_h3;
            $total_omzet_h4 += $omzet_h4;
          ?>
            <tr>
              <td><?= htmlspecialchars($kode) ?></td>
              <td><?= htmlspecialchars($it['nama']) ?></td>
              <td><?= htmlspecialchars($supplier) ?></td>
              <td class="right"><?= number_format((int)$g, 0, ',', '.') ?></td>
              <td class="right"><?= number_format((int)$t, 0, ',', '.') ?></td>
              <td class="right"><strong><?= number_format((int)$qty_total, 0, ',', '.') ?></strong></td>
              <td class="right"><?= number_format((int)($it['min_stock'] ?? 0), 0, ',', '.') ?></td>

              <td class="right"><?= number_format($hb, 0, ',', '.') ?></td>

              <td class="right"><?= number_format($h1, 0, ',', '.') ?></td>
              <td class="right"><?= number_format($h2, 0, ',', '.') ?></td>
              <td class="right"><?= number_format($h3, 0, ',', '.') ?></td>
              <td class="right"><?= number_format($h4, 0, ',', '.') ?></td>

              <td class="right"><strong><?= number_format($nilai_aset, 0, ',', '.') ?></strong></td>

              <td class="right"><?= number_format($omzet_h1, 0, ',', '.') ?></td>
              <td class="right"><?= number_format($omzet_h2, 0, ',', '.') ?></td>
              <td class="right"><?= number_format($omzet_h3, 0, ',', '.') ?></td>
              <td class="right"><?= number_format($omzet_h4, 0, ',', '.') ?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>

      <tfoot>
        <tr>
          <th colspan="7" class="right">TOTAL</th>
          <th class="right">—</th>
          <th class="right">—</th>
          <th class="right">—</th>
          <th class="right">—</th>
          <th class="right">—</th>
          <th class="right"><strong><?= number_format($total_aset, 0, ',', '.') ?></strong></th>
          <th class="right"><strong><?= number_format($total_omzet_h1, 0, ',', '.') ?></strong></th>
          <th class="right"><strong><?= number_format($total_omzet_h2, 0, ',', '.') ?></strong></th>
          <th class="right"><strong><?= number_format($total_omzet_h3, 0, ',', '.') ?></strong></th>
          <th class="right"><strong><?= number_format($total_omzet_h4, 0, ',', '.') ?></strong></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="no-print" style="margin-top:.6rem;">
    <button type="button" onclick="window.print()">Print</button>
  </p>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
