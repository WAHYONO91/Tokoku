<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);
require_once __DIR__.'/includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

/* ======================================================
   Helper angka untuk count/qty (rupiah() sudah ada di functions.php)
   ====================================================== */
if (!function_exists('angka')) {
  function angka($n){
    return number_format((int)$n, 0, ',', '.');
  }
}

/* ======================================================
   1) LIST TRANSAKSI PEMBELIAN
   ====================================================== */
$stmt = $pdo->prepare("
  SELECT p.id, p.invoice_no, p.supplier_kode, p.total, p.location, p.created_at,
         s.nama AS supplier_nama
  FROM purchases p
  LEFT JOIN suppliers s ON s.kode = p.supplier_kode
  WHERE DATE(p.created_at) BETWEEN ? AND ?
  ORDER BY p.created_at DESC
");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_sum = 0;
foreach($rows as $r){ $total_sum += (int)($r['total'] ?? 0); }

/* ======================================================
   2) RINGKASAN PER SUPPLIER
   ====================================================== */
$qSupplier = $pdo->prepare("
  SELECT 
    p.supplier_kode,
    COALESCE(s.nama, p.supplier_kode) AS supplier_nama,
    COUNT(*) AS trx_count,
    SUM(p.total) AS total_sum
  FROM purchases p
  LEFT JOIN suppliers s ON s.kode = p.supplier_kode
  WHERE DATE(p.created_at) BETWEEN ? AND ?
  GROUP BY p.supplier_kode, COALESCE(s.nama, p.supplier_kode)
  ORDER BY total_sum DESC
");
$qSupplier->execute([$from, $to]);
$sumSupplier = $qSupplier->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   3) RINGKASAN PER LOKASI
   ====================================================== */
$qLocation = $pdo->prepare("
  SELECT 
    COALESCE(p.location, '-') AS location,
    COUNT(*) AS trx_count,
    SUM(p.total) AS total_sum
  FROM purchases p
  WHERE DATE(p.created_at) BETWEEN ? AND ?
  GROUP BY COALESCE(p.location, '-')
  ORDER BY total_sum DESC
");
$qLocation->execute([$from, $to]);
$sumLocation = $qLocation->fetchAll(PDO::FETCH_ASSOC);

/* ======================================================
   4) RINGKASAN PER BARANG (pakai purchase_items langsung)
   Struktur purchase_items kamu:
   - purchase_id, item_kode, nama, unit, qty, harga_beli
   ====================================================== */
$sumItem = [];
try {
  $qItem = $pdo->prepare("
    SELECT
      pi.item_kode AS kode,
      MAX(pi.nama) AS nama,
      MAX(pi.unit) AS unit,
      SUM(pi.qty) AS qty_sum,
      SUM(pi.qty * pi.harga_beli) AS nilai_sum
    FROM purchase_items pi
    JOIN purchases p ON p.id = pi.purchase_id
    WHERE DATE(p.created_at) BETWEEN ? AND ?
    GROUP BY pi.item_kode
    ORDER BY nilai_sum DESC
  ");
  $qItem->execute([$from, $to]);
  $sumItem = $qItem->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $sumItem = [];
}
?>
<style>
.print-section { background: rgba(15,23,42,.15); padding: .6rem; border-radius: .5rem; margin-top: .75rem; }
.print-actions { display:flex; gap:.5rem; flex-wrap:wrap; margin:.35rem 0 .6rem; }

@media print {
  body * { visibility: hidden !important; }
  .print-area, .print-area * { visibility: visible !important; }
  .print-area { position: absolute; left: 0; top: 0; width: 100%; }
  .no-print { display: none !important; }
}
</style>

<article>
  <h3>Laporan Pembelian</h3>

  <form method="get" class="no-print" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.7rem;">
    <label>Dari
      <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
    </label>
    <label>Sampai
      <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
    </label>
    <button type="submit">Tampilkan</button>
    <button type="button" onclick="window.print()">Print Semua</button>
  </form>

  <!-- =========================
       RINGKASAN PER SUPPLIER
       ========================= -->
  <div id="printSupplier" class="print-section">
    <div class="print-actions no-print">
      <button type="button" onclick="printSection('printSupplier')">Cetak Ringkasan Supplier</button>
    </div>

    <h4 style="margin:.2rem 0 .4rem;">Ringkasan per Supplier</h4>
    <div style="font-size:.85rem;color:#64748b;margin-bottom:.4rem;">
      Periode: <?=htmlspecialchars($from)?> s/d <?=htmlspecialchars($to)?>
    </div>

    <table class="table-small">
      <thead>
        <tr>
          <th>Supplier</th>
          <th class="right">Jumlah Transaksi</th>
          <th class="right">Total Pembelian (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$sumSupplier): ?>
          <tr><td colspan="3">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach($sumSupplier as $s): ?>
            <tr>
              <td><?=htmlspecialchars($s['supplier_nama'] ?? $s['supplier_kode'])?></td>
              <td class="right"><?=angka($s['trx_count'] ?? 0)?></td>
              <td class="right"><?=rupiah($s['total_sum'] ?? 0)?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- =========================
       RINGKASAN PER LOKASI
       ========================= -->
  <div id="printLocation" class="print-section">
    <div class="print-actions no-print">
      <button type="button" onclick="printSection('printLocation')">Cetak Ringkasan Lokasi</button>
    </div>

    <h4 style="margin:.2rem 0 .4rem;">Ringkasan per Lokasi</h4>
    <div style="font-size:.85rem;color:#64748b;margin-bottom:.4rem;">
      Periode: <?=htmlspecialchars($from)?> s/d <?=htmlspecialchars($to)?>
    </div>

    <table class="table-small">
      <thead>
        <tr>
          <th>Lokasi</th>
          <th class="right">Jumlah Transaksi</th>
          <th class="right">Total Pembelian (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$sumLocation): ?>
          <tr><td colspan="3">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach($sumLocation as $l): ?>
            <tr>
              <td><?=htmlspecialchars($l['location'] ?? '-')?></td>
              <td class="right"><?=angka($l['trx_count'] ?? 0)?></td>
              <td class="right"><?=rupiah($l['total_sum'] ?? 0)?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- =========================
       RINGKASAN PER BARANG
       ========================= -->
  <div id="printItem" class="print-section">
    <div class="print-actions no-print">
      <button type="button" onclick="printSection('printItem')">Cetak Ringkasan Barang</button>
    </div>

    <h4 style="margin:.2rem 0 .4rem;">Ringkasan per Barang</h4>
    <div style="font-size:.85rem;color:#64748b;margin-bottom:.4rem;">
      Periode: <?=htmlspecialchars($from)?> s/d <?=htmlspecialchars($to)?>
    </div>

    <table class="table-small">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama Barang</th>
          <th>Satuan</th>
          <th class="right">Total Qty</th>
          <th class="right">Total Nilai (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$sumItem): ?>
          <tr><td colspan="5">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach($sumItem as $it): ?>
            <tr>
              <td><?=htmlspecialchars($it['kode'] ?? '-')?></td>
              <td><?=htmlspecialchars($it['nama'] ?? ($it['kode'] ?? '-'))?></td>
              <td><?=htmlspecialchars($it['unit'] ?? '-')?></td>
              <td class="right"><?=angka($it['qty_sum'] ?? 0)?></td>
              <td class="right"><?=rupiah($it['nilai_sum'] ?? 0)?></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- =========================
       LIST TRANSAKSI DETAIL
       ========================= -->
  <div id="printAll" class="print-section">
    <div class="print-actions no-print">
      <button type="button" onclick="printSection('printAll')">Cetak Daftar Transaksi</button>
    </div>

    <h4 style="margin:.2rem 0 .4rem;">Daftar Transaksi</h4>
    <div style="font-size:.85rem;color:#64748b;margin-bottom:.4rem;">
      Periode: <?=htmlspecialchars($from)?> s/d <?=htmlspecialchars($to)?>
    </div>

    <table class="table-small">
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>No. Faktur</th>
          <th>Supplier</th>
          <th>Lokasi</th>
          <th class="right">Total (Rp)</th>
          <th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="6">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach($rows as $r): ?>
            <tr>
              <td><?=date('d-m-Y H:i', strtotime($r['created_at']))?></td>
              <td><?=htmlspecialchars($r['invoice_no'] ?: ('#'.$r['id']))?></td>
              <td><?=htmlspecialchars($r['supplier_nama'] ?? $r['supplier_kode'])?></td>
              <td><?=htmlspecialchars($r['location'] ?? '-')?></td>
              <td class="right"><?=rupiah($r['total'] ?? 0)?></td>
              <td class="no-print" style="white-space:nowrap;display:flex;gap:.35rem;">
                <a href="/tokoapp/purchase_print.php?id=<?= (int)$r['id'] ?>" target="_blank">Cetak</a>
                <a href="/tokoapp/purchase_edit.php?id=<?= (int)$r['id'] ?>">Ubah</a>
                <a href="/tokoapp/purchase_delete.php?id=<?= (int)$r['id'] ?>" onclick="return confirm('Hapus pembelian ini? stok akan dikembalikan.');">Hapus</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="right">Total</th>
          <th class="right"><?=rupiah($total_sum)?></th>
          <th class="no-print"></th>
        </tr>
      </tfoot>
    </table>
  </div>

</article>

<script>
function printSection(id){
  const el = document.getElementById(id);
  if(!el) return;

  const sections = document.querySelectorAll('.print-section');
  sections.forEach(x => x.dataset._oldClass = x.className);

  // nonaktifkan semua
  sections.forEach(x => x.classList.remove('print-area'));

  // aktifkan target
  el.classList.add('print-area');

  window.print();

  // restore
  sections.forEach(x => {
    x.className = x.dataset._oldClass || x.className;
    delete x.dataset._oldClass;
  });
}
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
