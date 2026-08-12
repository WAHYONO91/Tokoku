<?php
require_once __DIR__.'/config.php';
require_access('PURCHASE');
require_once __DIR__.'/functions.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id<=0){ echo "ID tidak valid"; exit; }

$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id=?");
$stmt->execute([$id]);
$purchase = $stmt->fetch();
if(!$purchase){ echo "Pembelian tidak ditemukan."; exit; }

$itemsStmt = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id=?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll();

$setting = $pdo->query("SELECT store_name, store_address, store_phone FROM settings WHERE id=1")->fetch();
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Pembelian</title>
  <link rel="stylesheet" href="/tokoapp/assets/vendor/pico/pico.min.css">
  <script src="/tokoapp/assets/vendor/nota_exporter.js"></script>
  <style>
    body{background:#fff;color:#000;padding-top:15px;}
    .doc{max-width:800px;margin:0 auto;}
    table{width:100%;border-collapse:collapse;}
    th,td{border:1px solid #000;padding:4px 6px;font-size:13px;}
    .right{text-align:right;}
    @media print {.no-print{display:none !important;}}
  </style>
</head>
<body>
<div class="doc" id="purchaseDoc">
  <div class="no-print" style="margin-bottom:15px; display:flex; gap:10px; flex-wrap:wrap; align-items:center; background:#f8fafc; padding:10px 14px; border-radius:6px; border:1px solid #e2e8f0;">
    <strong style="margin-right:auto;">📄 Preview Pembelian</strong>
    <button type="button" onclick="window.print()" style="background:#2563eb; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">🖨️ Cetak</button>
    <button type="button" onclick="NotaExporter.downloadJPG(document.getElementById('purchaseDoc'), 'Pembelian_<?= htmlspecialchars($purchase['invoice_no'] ?: $purchase['id']) ?>.jpg')" style="background:#059669; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">🖼️ Simpan JPG</button>
    <button type="button" onclick="NotaExporter.downloadPDF(document.getElementById('purchaseDoc'), 'Pembelian_<?= htmlspecialchars($purchase['invoice_no'] ?: $purchase['id']) ?>.pdf')" style="background:#d97706; color:#fff; border:none; padding:6px 12px; border-radius:4px; cursor:pointer;">📄 Simpan PDF</button>
    <a href="/tokoapp/purchases_report.php" style="background:#64748b; color:#fff; padding:6px 12px; border-radius:4px; text-decoration:none; display:inline-block;">⬅️ Laporan Pembelian</a>
  </div>
  <h3><?=htmlspecialchars($setting['store_name'] ?? 'TOKO')?></h3>
  <?php if(!empty($setting['store_address'])): ?>
    <div><?=nl2br(htmlspecialchars($setting['store_address']))?></div>
  <?php endif; ?>
  <?php if(!empty($setting['store_phone'])): ?>
    <div>Telp: <?=htmlspecialchars($setting['store_phone'])?></div>
  <?php endif; ?>
  <hr>
  <h4>Faktur Pembelian</h4>
  <p>
    No: <?=htmlspecialchars($purchase['invoice_no'] ?: ('#'.$purchase['id']))?><br>
    Tanggal: <?=date('d-m-Y H:i', strtotime($purchase['created_at']))?><br>
    Supplier: <?=htmlspecialchars($purchase['supplier_kode'] ?? '-')?><br>
    Lokasi: <?=htmlspecialchars($purchase['location'] ?? '-')?>
  </p>
  <table>
    <thead>
      <tr>
        <th>No</th>
        <th>Kode</th>
        <th>Nama Barang</th>
        <th>Satuan</th>
        <th class="right">Qty</th>
        <th class="right">Harga Beli</th>
        <th class="right">Total</th>
      </tr>
    </thead>
    <tbody>
      <?php $no=1;$subtotal=0; foreach($items as $it): $line=(int)$it['qty']*(int)$it['harga_beli']; $subtotal+=$line; ?>
      <tr>
        <td><?=$no++?></td>
        <td><?=htmlspecialchars($it['item_kode'])?></td>
        <td><?=htmlspecialchars($it['nama'])?></td>
        <td><?=htmlspecialchars($it['unit'] ?? 'pcs')?></td>
        <td class="right"><?= (int)$it['qty'] ?></td>
        <td class="right"><?=number_format((int)$it['harga_beli'],0,',','.')?></td>
        <td class="right"><?=number_format($line,0,',','.')?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="6" class="right">Subtotal</th><th class="right"><?=number_format($subtotal,0,',','.')?></th></tr>
      <tr><th colspan="6" class="right">Diskon</th><th class="right"><?=number_format((int)$purchase['discount'],0,',','.')?></th></tr>
      <tr><th colspan="6" class="right">PPN</th><th class="right"><?=number_format((int)$purchase['tax'],0,',','.')?></th></tr>
      <tr><th colspan="6" class="right">Total</th><th class="right"><?=number_format((int)$purchase['total'],0,',','.')?></th></tr>
    </tfoot>
  </table>
</div>
</body>
</html>
