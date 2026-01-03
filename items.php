<?php
require_once __DIR__ . '/config.php';
require_login(); // semua user login boleh akses

// ========================= MODE PICKER (POPUP) =========================
$isPicker = (isset($_GET['pick']) && $_GET['pick'] == '1');

require_once __DIR__ . '/includes/header.php';

$currentRole = $_SESSION['user']['role'] ?? '';
$isAdmin     = ($currentRole === 'admin');

$msg = '';
$err = '';

// ========================= HAPUS (HANYA ADMIN) =========================
// NOTE: di mode picker, kita kunci CRUD agar tidak ada salah klik saat popup.
if (!$isPicker && isset($_GET['delete'])) {
  if (!$isAdmin) {
    http_response_code(403);
    $err = 'Akses ditolak: hanya admin yang boleh menghapus data barang.';
  } else {
    $delKode = trim($_GET['delete']);
    if ($delKode !== '') {
      try {
        $stmt = $pdo->prepare("DELETE FROM items WHERE kode = ?");
        $stmt->execute([$delKode]);
        if ($stmt->rowCount() > 0) {
          $msg = "✅ Barang dengan kode " . htmlspecialchars($delKode) . " telah dihapus.";
        } else {
          $err = "Barang dengan kode " . htmlspecialchars($delKode) . " tidak ditemukan.";
        }
      } catch (Throwable $th) {
        $err = 'Gagal menghapus barang: ' . $th->getMessage();
      }
    }
  }
}

// ========================= MODE EDIT: PREFILL =========================
$editKode = (!$isPicker && isset($_GET['edit'])) ? trim($_GET['edit']) : '';
$editRow  = null;
if (!$isPicker && $editKode !== '') {
  $stmt = $pdo->prepare("SELECT * FROM items WHERE kode = ?");
  $stmt->execute([$editKode]);
  $editRow = $stmt->fetch();
  if (!$editRow) {
    $err = "Data untuk kode '" . htmlspecialchars($editKode) . "' tidak ditemukan.";
  } else {
    // ambil stok toko & gudang dari item_stocks
    $stokToko   = 0;
    $stokGudang = 0;
    $st2 = $pdo->prepare("SELECT location, qty FROM item_stocks WHERE item_kode = ?");
    $st2->execute([$editKode]);
    while ($stRow = $st2->fetch()) {
      if ($stRow['location'] === 'toko') {
        $stokToko = (int)$stRow['qty'];
      } elseif ($stRow['location'] === 'gudang') {
        $stokGudang = (int)$stRow['qty'];
      }
    }
    $editRow['stok_toko']   = $stokToko;
    $editRow['stok_gudang'] = $stokGudang;
  }
}

// ========================= SIMPAN (ADD/UPDATE) =========================
if (!$isPicker && $_SERVER['REQUEST_METHOD'] === 'POST') {
  $original_kode  = trim($_POST['original_kode'] ?? ''); // untuk edit
  $kode           = trim($_POST['kode'] ?? '');
  $barcode        = trim($_POST['barcode'] ?? '');
  $nama           = trim($_POST['nama'] ?? '');
  $supplier_kode  = trim($_POST['supplier_kode'] ?? '');
  $unit_code      = trim($_POST['unit_code'] ?? '');

  // ======= BLOK ANGKA: boleh kosong, boleh 0, dan tidak boleh minus =======
  $harga_beli  = (!isset($_POST['harga_beli'])  || $_POST['harga_beli']  === '') ? 0 : max(0, (int)$_POST['harga_beli']);
  $harga_jual1 = (!isset($_POST['harga_jual1']) || $_POST['harga_jual1'] === '') ? 0 : max(0, (int)$_POST['harga_jual1']);
  $harga_jual2 = (!isset($_POST['harga_jual2']) || $_POST['harga_jual2'] === '') ? 0 : max(0, (int)$_POST['harga_jual2']);
  $harga_jual3 = (!isset($_POST['harga_jual3']) || $_POST['harga_jual3'] === '') ? 0 : max(0, (int)$_POST['harga_jual3']);
  $harga_jual4 = (!isset($_POST['harga_jual4']) || $_POST['harga_jual4'] === '') ? 0 : max(0, (int)$_POST['harga_jual4']);

  $min_stock   = (!isset($_POST['min_stock'])   || $_POST['min_stock']   === '') ? 0 : max(0, (int)$_POST['min_stock']);
  $stok_toko   = (!isset($_POST['stok_toko'])   || $_POST['stok_toko']   === '') ? 0 : max(0, (int)$_POST['stok_toko']);
  $stok_gudang = (!isset($_POST['stok_gudang']) || $_POST['stok_gudang'] === '') ? 0 : max(0, (int)$_POST['stok_gudang']);
  // ======================================================================

  if ($kode === '' || $nama === '' || $unit_code === '') {
    $err = 'Kolom Kode, Nama, dan Unit wajib diisi.';
  } else {
    try {
      if ($original_kode !== '' && $original_kode !== $kode) {
        // === UPDATE DENGAN GANTI PRIMARY KEY (kode) ===
        $stmt = $pdo->prepare("
          UPDATE items
             SET kode          = ?,
                 barcode       = ?,
                 nama          = ?,
                 supplier_kode = ?,
                 unit_code     = ?,
                 harga_beli    = ?,
                 harga_jual1   = ?,
                 harga_jual2   = ?,
                 harga_jual3   = ?,
                 harga_jual4   = ?,
                 min_stock     = ?,
                 updated_at    = NOW()
           WHERE kode = ?
        ");
        $stmt->execute([
          $kode,
          $barcode,
          $nama,
          $supplier_kode !== '' ? $supplier_kode : null,
          $unit_code,
          $harga_beli,
          $harga_jual1,
          $harga_jual2,
          $harga_jual3,
          $harga_jual4,
          $min_stock,
          $original_kode
        ]);

        require_once __DIR__ . '/functions.php';
        ensure_stock_rows($pdo, $kode);

        // update stok di item_stocks
        $st = $pdo->prepare("UPDATE item_stocks SET qty = ? WHERE item_kode = ? AND location = ?");
        $st->execute([$stok_toko,   $kode, 'toko']);
        $st->execute([$stok_gudang, $kode, 'gudang']);

        $msg = '✅ Data barang berhasil diupdate (kode berubah).';
        $editRow = null; $editKode = '';
      } else {
        // === INSERT / UPDATE BIASA (kode sama atau tambah baru) ===
        $stmt = $pdo->prepare("
          INSERT INTO items
            (kode, barcode, nama, supplier_kode, unit_code,
             harga_beli, harga_jual1, harga_jual2, harga_jual3, harga_jual4,
             min_stock, created_at, updated_at)
          VALUES
            (?,?,?,?,?,
             ?,?,?,?,?,
             ?, NOW(), NOW())
          ON DUPLICATE KEY UPDATE
            barcode       = VALUES(barcode),
            nama          = VALUES(nama),
            supplier_kode = VALUES(supplier_kode),
            unit_code     = VALUES(unit_code),
            harga_beli    = VALUES(harga_beli),
            harga_jual1   = VALUES(harga_jual1),
            harga_jual2   = VALUES(harga_jual2),
            harga_jual3   = VALUES(harga_jual3),
            harga_jual4   = VALUES(harga_jual4),
            min_stock     = VALUES(min_stock),
            updated_at    = NOW()
        ");
        $stmt->execute([
          $kode,
          $barcode,
          $nama,
          $supplier_kode !== '' ? $supplier_kode : null,
          $unit_code,
          $harga_beli,
          $harga_jual1,
          $harga_jual2,
          $harga_jual3,
          $harga_jual4,
          $min_stock
        ]);

        require_once __DIR__ . '/functions.php';
        ensure_stock_rows($pdo, $kode);

        // update stok di item_stocks (setelah dipastikan baris stok ada)
        $st = $pdo->prepare("UPDATE item_stocks SET qty = ? WHERE item_kode = ? AND location = ?");
        $st->execute([$stok_toko,   $kode, 'toko']);
        $st->execute([$stok_gudang, $kode, 'gudang']);

        $msg = '✅ Data barang tersimpan.';
        $editRow = null; $editKode = '';
      }
    } catch (Throwable $th) {
      $err = 'Gagal menyimpan data: ' . $th->getMessage();
    }
  }
}

// ========================= DATA REFERENSI & LIST =========================
$units = $pdo->query('SELECT code, name FROM units ORDER BY name')->fetchAll();
$suppliers = $pdo->query('SELECT kode, nama FROM suppliers ORDER BY nama')->fetchAll();

// map kode supplier -> nama supplier
$supplierMap = [];
foreach ($suppliers as $s) {
  $supplierMap[$s['kode']] = $s['nama'];
}

// Filter cari & limit tampilan
$q = trim($_GET['q'] ?? '');
$limitParam = $_GET['limit'] ?? '100';
$limitMap = [
  '100'  => 100,
  '200'  => 200,
  '500'  => 500,
  'all'  => 0,
];
$perPage = isset($limitMap[$limitParam]) ? $limitMap[$limitParam] : 100;

// ambil list barang + stok toko & gudang + tgl masuk
$sql = "
  SELECT
    i.*,
    i.created_at AS tgl_masuk,
    SUM(CASE WHEN s.location = 'toko'   THEN s.qty ELSE 0 END) AS stok_toko,
    SUM(CASE WHEN s.location = 'gudang' THEN s.qty ELSE 0 END) AS stok_gudang
  FROM items i
  LEFT JOIN item_stocks s ON s.item_kode = i.kode
  WHERE 1
";
$params = [];

if ($q !== '') {
  $sql .= " AND (i.kode LIKE ? OR i.barcode LIKE ? OR i.nama LIKE ?)";
  $like = "%{$q}%";
  $params[] = $like;
  $params[] = $like;
  $params[] = $like;
}

$sql .= " GROUP BY i.kode ORDER BY i.updated_at DESC, i.created_at DESC";

if ($perPage > 0) {
  $sql .= " LIMIT " . (int)$perPage;
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Helper prefill form
function val($row, $key, $default=''){
  if (!$row) return $default;
  return isset($row[$key]) ? $row[$key] : $default;
}
?>
<style>
.form-card{
  border:1px solid #1f2937;border-radius:12px;padding:1rem;background:#0f172a;margin-bottom:1rem
}
.grid-2{
  display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.8rem
}
.form-actions{
  display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.5rem
}
.table-actions{white-space:nowrap;display:flex;gap:.35rem}
@media (max-width:640px){ .grid-2{grid-template-columns:1fr} }

/* CSS untuk Tombol Cetak */
.btn-print-barcode {
  background-color: #4CAF50;
  color: white;
  padding: 5px 10px;
  border: none;
  border-radius: 5px;
  cursor: pointer;
  font-size: 12px;
  text-align: center;
  display: inline-block;
  width: 100%;
}

.table-actions {
  display: flex;
  justify-content: center;
  gap: .35rem;
  width: 100%;
}

table td .btn-print-barcode {
  width: auto;
  font-size: 12px;
  padding: 6px 12px;
}

/* ==== Picker UX ==== */
<?php if ($isPicker): ?>
.picker-hint{
  background:#0b1220;
  border:1px solid #1f2937;
  padding:.75rem;
  border-radius:10px;
  margin:.6rem 0 1rem 0;
}
.table-small tbody tr[data-kode]{
  cursor:pointer;
}
.table-small tbody tr.picked{
  outline:2px solid #60a5fa;
  outline-offset:-2px;
}
<?php endif; ?>
</style>

<article>
  <h3><?= $isPicker ? 'Pilih Barang' : 'Master Data Barang' ?></h3>

  <?php if ($msg && !$isPicker): ?>
    <mark style="display:block;margin:.6rem 0;background:#16a34a;color:#fff"><?= htmlspecialchars($msg) ?></mark>
  <?php endif; ?>
  <?php if ($err && !$isPicker): ?>
    <mark style="display:block;margin:.6rem 0;background:#fee2e2;color:#b91c1c"><?= htmlspecialchars($err) ?></mark>
  <?php endif; ?>

  <?php if ($isPicker): ?>
    <div class="picker-hint">
      <strong>Mode POS Picker:</strong> Double click baris untuk memilih barang dan mengirim <em>barcode</em> (jika ada) ke POS.
      <div style="opacity:.8;margin-top:.25rem;">Shortcut: Enter = pilih baris aktif, Esc = tutup popup.</div>
    </div>
  <?php else: ?>
    <div class="no-print" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem;">
      <a href="items_import.php" class="secondary">⬆️ Import CSV/Excel</a>
      <a href="items_export_csv.php" class="secondary">⬇️ Export CSV</a>
      <a href="items_print.php" target="_blank" class="secondary">🖨 Cetak / PDF</a>
    </div>
  <?php endif; ?>

  <!-- Filter Cari & Limit -->
  <form method="get" class="no-print" style="margin-bottom:.7rem;display:flex;gap:.5rem;flex-wrap:wrap;align-items:center">
    <?php if ($isPicker): ?>
      <input type="hidden" name="pick" value="1">
    <?php endif; ?>

    <input type="text"
           name="q"
           value="<?= htmlspecialchars($q) ?>"
           placeholder="Cari kode / nama / barcode..."
           style="min-width:220px;">
    <select name="limit">
      <option value="100" <?= $limitParam==='100' ? 'selected' : '' ?>>Tampilkan 100</option>
      <option value="200" <?= $limitParam==='200' ? 'selected' : '' ?>>Tampilkan 200</option>
      <option value="500" <?= $limitParam==='500' ? 'selected' : '' ?>>Tampilkan 500</option>
      <option value="all" <?= $limitParam==='all' ? 'selected' : '' ?>>Tampilkan semua</option>
    </select>
    <button type="submit">Filter</button>

    <?php if ($isPicker): ?>
      <a href="items.php?pick=1" class="secondary">Reset</a>
    <?php else: ?>
      <a href="items.php" class="secondary">Reset</a>
    <?php endif; ?>
  </form>

  <?php if (!$isPicker): ?>
  <!-- ===== KARTU FORM (RAPI) ===== -->
  <form method="post" class="form-card" autocomplete="off">
    <input type="hidden" name="original_kode" value="<?= htmlspecialchars(val($editRow,'kode','')) ?>">

    <div class="grid-2">
      <label>Kode
        <input name="kode" required value="<?= htmlspecialchars(val($editRow,'kode','')) ?>" placeholder="Mis. BRG-001">
        <small>Kode unik barang. Boleh diubah saat edit.</small>
      </label>
      <label>Barcode
        <input name="barcode" value="<?= htmlspecialchars(val($editRow,'barcode','')) ?>" placeholder="Opsional (untuk cetak barcode)">
      </label>
      <label>Nama
        <input name="nama" required value="<?= htmlspecialchars(val($editRow,'nama','')) ?>" placeholder="Nama barang">
      </label>
      <label>Supplier
        <select name="supplier_kode">
          <option value="">-- Pilih Supplier (opsional) --</option>
          <?php
          $selSupp = val($editRow, 'supplier_kode', '');
          foreach ($suppliers as $s):
            $selected = ($s['kode'] === $selSupp) ? 'selected' : '';
          ?>
            <option value="<?= htmlspecialchars($s['kode']) ?>" <?= $selected ?>>
              <?= htmlspecialchars($s['kode'] . ' - ' . $s['nama']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Unit
        <select name="unit_code" required>
          <?php
          $selUnit = val($editRow,'unit_code','');
          foreach($units as $u):
            $sel = ($u['code'] === $selUnit) ? 'selected' : '';
          ?>
            <option value="<?= htmlspecialchars($u['code']) ?>" <?= $sel ?>><?= htmlspecialchars($u['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Harga Beli
        <input type="number" name="harga_beli" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'harga_beli','')) ?>">
      </label>
      <label>Harga Jual 1
        <input type="number" name="harga_jual1" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'harga_jual1','')) ?>">
      </label>
      <label>Harga Jual 2
        <input type="number" name="harga_jual2" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'harga_jual2','')) ?>">
      </label>
      <label>Harga Jual 3
        <input type="number" name="harga_jual3" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'harga_jual3','')) ?>">
      </label>
      <label>Harga Jual 4
        <input type="number" name="harga_jual4" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'harga_jual4','')) ?>">
      </label>
      <label>Min Stok
        <input type="number" name="min_stock" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'min_stock','')) ?>">
      </label>
      <label>Stok Toko
        <input type="number" name="stok_toko" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'stok_toko','')) ?>">
      </label>
      <label>Stok Gudang
        <input type="number" name="stok_gudang" min="0"
               value="<?= htmlspecialchars((string)val($editRow,'stok_gudang','')) ?>">
      </label>
    </div>

    <div class="form-actions">
      <button type="submit"><?= $editRow ? 'Update' : 'Simpan' ?></button>
      <?php if ($editRow): ?>
        <a class="secondary" href="items.php">Batal Edit</a>
      <?php endif; ?>
    </div>
  </form>
  <?php endif; ?>

  <!-- ===== TABEL LIST ===== -->
  <table class="table-small" id="itemsTable">
    <thead>
      <tr>
        <th>Kode</th>
        <th>Nama</th>
        <th>Supplier</th>
        <th>Unit</th>
        <th>Tgl Masuk</th>
        <th class="right">HB</th>
        <th class="right">H1</th>
        <th class="right">H2</th>
        <th class="right">H3</th>
        <th class="right">H4</th>
        <th class="right">Stok Toko</th>
        <th class="right">Stok Gudang</th>
        <?php if (!$isPicker): ?>
          <th class="no-print">Aksi</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="<?= $isPicker ? '12' : '13' ?>">Belum ada data barang.</td></tr>
      <?php else: ?>
        <?php foreach($rows as $r): ?>
          <?php
            $sk = $r['supplier_kode'] ?? '';
            $supplierLabel = $sk !== '' ? ($supplierMap[$sk] ?? $sk) : '';

            $tglMasuk = $r['tgl_masuk'] ?? ($r['created_at'] ?? null);
            $tglMasukFmt = $tglMasuk ? date('d-m-Y', strtotime($tglMasuk)) : '-';

            $kodeRow = (string)($r['kode'] ?? '');
            $barcodeRow = (string)($r['barcode'] ?? '');
          ?>
          <tr
            data-kode="<?= htmlspecialchars($kodeRow) ?>"
            data-barcode="<?= htmlspecialchars($barcodeRow) ?>"
            title="<?= $isPicker ? 'Double click untuk pilih' : '' ?>"
          >
            <td><?= htmlspecialchars($kodeRow) ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td><?= htmlspecialchars($supplierLabel) ?></td>
            <td><?= htmlspecialchars($r['unit_code']) ?></td>
            <td><?= htmlspecialchars($tglMasukFmt) ?></td>
            <td class="right"><?= number_format((int)$r['harga_beli'], 0, ',', '.') ?></td>
            <td class="right"><?= number_format((int)$r['harga_jual1'], 0, ',', '.') ?></td>
            <td class="right"><?= number_format((int)$r['harga_jual2'], 0, ',', '.') ?></td>
            <td class="right"><?= number_format((int)$r['harga_jual3'], 0, ',', '.') ?></td>
            <td class="right"><?= number_format((int)$r['harga_jual4'], 0, ',', '.') ?></td>
            <td class="right"><?= (int)($r['stok_toko'] ?? 0) ?></td>
            <td class="right"><?= (int)($r['stok_gudang'] ?? 0) ?></td>

            <?php if (!$isPicker): ?>
            <td class="no-print table-actions">
              <a href="items.php?edit=<?= urlencode($kodeRow) ?>">Edit</a>
              <?php if ($isAdmin): ?>
                <a href="items.php?delete=<?= urlencode($kodeRow) ?>"
                   onclick="return confirm('Hapus barang ini? Tindakan tidak dapat dibatalkan.');"
                   style="color:#dc2626">Hapus</a>
              <?php else: ?>
                <span style="opacity:.6;cursor:not-allowed">Hapus</span>
              <?php endif; ?>

              <button type="button"
                      class="btn-print-barcode"
                      onclick="printBarcode('<?= htmlspecialchars($kodeRow) ?>','<?= htmlspecialchars($barcodeRow) ?>')">
                Cetak
              </button>
            </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</article>

<?php if (!$isPicker): ?>
<script>
/**
 * Cetak 50 barcode per item pada kertas F4.
 * - layout: grid 5 kolom x 10 baris = 50 label
 * - menggunakan JsBarcode di jendela baru
 */
function printBarcode(kode, barcode) {
  var win = window.open('', '_blank');

  win.document.write('<!DOCTYPE html>');
  win.document.write('<html><head><meta charset="utf-8">');
  win.document.write('<title>Cetak Barcode ' + kode + '</title>');
  win.document.write('<style>');
  win.document.write('@page { size: 210mm 330mm; margin: 10mm; }'); // F4 portrait
  win.document.write('body { font-family: Arial, sans-serif; font-size: 10pt; }');
  win.document.write('h3 { text-align:center; margin-bottom:8mm; }');
  win.document.write('.labels { display:grid; grid-template-columns:repeat(5, 1fr); gap:4mm; }');
  win.document.write('.label { border:1px dashed #ccc; padding:2mm; text-align:center; }');
  win.document.write('.label svg { width:100%; height:40mm; }');
  win.document.write('.label div { margin-top:2mm; font-size:9pt; }');
  win.document.write('</style>');
  win.document.write('</head><body>');
  win.document.write('<h3>Barcode untuk Produk</h3>');
  win.document.write('<div class="labels">');

  // 50 label
  for (var i = 0; i < 50; i++) {
    win.document.write('<div class="label"><svg class="barcode"></svg><div>' + kode + '</div></div>');
  }

  win.document.write('</div>');
  win.document.write('<script src="/tokoapp/assets/vendor/JsBarcode.all.min.js"><\/script>');
  win.document.write('<script>');
  win.document.write('window.onload = function(){');
  win.document.write('  var value = ' + JSON.stringify(barcode) + ';');
  win.document.write('  if(!value){ value = ' + JSON.stringify(kode) + '; }'); // fallback jika barcode kosong
  win.document.write('  var svgs = document.querySelectorAll("svg.barcode");');
  win.document.write('  svgs.forEach(function(el){');
  win.document.write('    JsBarcode(el, value, { format:"CODE128", displayValue:true, fontSize:10, height:40 });');
  win.document.write('  });');
  win.document.write('  window.print();');
  win.document.write('};');
  win.document.write('<\/script>');
  win.document.write('</body></html>');

  win.document.close();
}
</script>
<?php endif; ?>

<?php if ($isPicker): ?>
<script>
(function(){
  // ========== MODE PICKER ==========
  const table = document.getElementById('itemsTable');
  if(!table) return;

  const tbody = table.querySelector('tbody');
  if(!tbody) return;

  let activeRow = null;

  function setActiveRow(tr){
    if(activeRow) activeRow.classList.remove('picked');
    activeRow = tr;
    if(activeRow) activeRow.classList.add('picked');
  }

  function pickRow(tr){
    if(!tr) return;

    const barcode = (tr.dataset.barcode || '').trim();
    const kode    = (tr.dataset.kode || '').trim();

    // Kirim barcode jika ada, jika kosong fallback ke kode
    const valueToSend = barcode || kode;
    if(!valueToSend) return;

    if (window.opener && typeof window.opener.setItemFromPicker === 'function') {
      window.opener.setItemFromPicker(valueToSend);
      window.close();
      return;
    }

    alert('POS belum terbuka atau tidak bisa menerima item.');
  }

  // klik sekali: aktifkan row
  tbody.addEventListener('click', (e)=>{
    const tr = e.target.closest('tr[data-kode]');
    if(!tr) return;
    setActiveRow(tr);
  });

  // double click: pilih
  tbody.addEventListener('dblclick', (e)=>{
    const tr = e.target.closest('tr[data-kode]');
    if(!tr) return;
    setActiveRow(tr);
    pickRow(tr);
  });

  // shortcut keyboard: Enter pilih, Esc tutup
  window.addEventListener('keydown', (e)=>{
    if(e.key === 'Escape'){
      window.close();
      return;
    }
    if(e.key === 'Enter'){
      if(activeRow){
        e.preventDefault();
        pickRow(activeRow);
      }
      return;
    }
  });

  // auto pilih baris pertama (jika ada) supaya Enter langsung bekerja
  const first = tbody.querySelector('tr[data-kode]');
  if(first) setActiveRow(first);

  // fokus ke input filter jika ada
  const q = document.querySelector('input[name="q"]');
  if(q){ q.focus(); q.select && q.select(); }
})();
</script>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
