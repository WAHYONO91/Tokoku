<?php
require_once __DIR__.'/config.php';
require_access('INVENTORY');

// Filter: cetak satu kode atau semua
$single = $_GET['single'] ?? '';
$showImg = isset($_GET['img']) && $_GET['img'] === '1'; // ?img=1 untuk tampilkan gambar barcode

if ($single) {
  $stmt = $pdo->prepare("SELECT * FROM items WHERE kode=?");
  $stmt->execute([$single]);
  $items = $stmt->fetchAll();
} else {
  $items = $pdo->query("SELECT * FROM items ORDER BY nama")->fetchAll();
}

$setting = $pdo->query("SELECT store_name, store_address FROM settings WHERE id=1")->fetch();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Data Barang</title>
  <style>
    body{font-family:Arial,Helvetica,sans-serif;font-size:12px;}
    h2,h3{text-align:center;margin:0;}
    table{width:100%;border-collapse:collapse;margin-top:1rem;}
    th,td{border:1px solid #000;padding:3px 4px;vertical-align:top}
    .right{text-align:right;}
    .barcode-box{display:flex;flex-direction:column;gap:4px;align-items:flex-start}
    .barcode-img{max-height:42px} /* skala kecil agar muat di kertas */
    @media print {.no-print{display:none;}}
  </style>
  <script src="/tokoapp/assets/vendor/JsBarcode.all.min.js"></script>
</head>
<body>
  <h2><?=htmlspecialchars($setting['store_name'] ?? 'TOKO')?></h2>
  <?php if(!empty($setting['store_address'])): ?>
    <div style="text-align:center;white-space:pre-line;"><?=nl2br(htmlspecialchars($setting['store_address']))?></div>
  <?php endif; ?>
  <h3>Data Barang</h3>

  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>Kode</th>
        <th>Barcode</th>
        <th>Nama</th>
        <th>Satuan</th>
        <th>Hrg Beli</th>
        <th>H1</th>
        <th>H2</th>
        <th>H3</th>
        <th>H4</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$items): ?>
        <tr><td colspan="10" style="text-align:center">Tidak ada data</td></tr>
      <?php else: ?>
        <?php $no=1; foreach($items as $it): 
          $unit = $it['unit_code'] ?? ($it['unit'] ?? 'PCS');
          $barcode = trim((string)($it['barcode'] ?? ''));
          // URL gambar barcode (CODE128) dari bwip-js (opsional)
          $barcodeUrl = $barcode !== '' 
            ? ''.rawurlencode($barcode)
            : '';
        ?>
        <tr>
          <td><?= $no++ ?></td>
          <td><?= htmlspecialchars($it['kode']) ?></td>
          <td>
            <?php if($barcode !== ''): ?>
              <div class="barcode-box">
                <div><?= htmlspecialchars($barcode) ?></div>
                <?php if($showImg): ?>
                  <svg class="barcode" data-code="<?= $barcodeUrl ?>"></svg>
                <?php endif; ?>
              </div>
            <?php endif; ?>
          </td>
          <td><?= htmlspecialchars($it['nama']) ?></td>
          <td><?= htmlspecialchars($unit) ?></td>
          <td class="right"><?= number_format((int)$it['harga_beli'],0,',','.') ?></td>
          <td class="right"><?= number_format((int)$it['harga_jual1'],0,',','.') ?></td>
          <td class="right"><?= number_format((int)$it['harga_jual2'],0,',','.') ?></td>
          <td class="right"><?= number_format((int)$it['harga_jual3'],0,',','.') ?></td>
          <td class="right"><?= number_format((int)$it['harga_jual4'],0,',','.') ?></td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>

  <p class="no-print" style="margin-top:10px;display:flex;gap:.5rem;flex-wrap:wrap">
    <button onclick="window.print()">Print / PDF</button>
    <?php if($showImg): ?>
      <a href="?<?= $single ? 'single='.urlencode($single).'&' : '' ?>img=0" class="secondary">Sembunyikan Gambar Barcode</a>
    <?php else: ?>
      <a href="?<?= $single ? 'single='.urlencode($single).'&' : '' ?>img=1" class="secondary">Tampilkan Gambar Barcode</a>
    <?php endif; ?>
  </p>
<script>
(function(){
  if (typeof JsBarcode === 'undefined') { console.warn('JsBarcode belum ada di vendor lokal. Barcode tidak dirender.'); return; }
  document.querySelectorAll('svg.barcode').forEach(function(el){
    var code = el.getAttribute('data-code') || '';
    // Bersihin URL-encoding kalau masih kebawa
    try { code = decodeURIComponent(code); } catch(e){}
    JsBarcode(el, code, {format:'CODE128', displayValue:true, fontSize:14, height:60, margin:2});
  });
})();
</script>
</body>
</html>
