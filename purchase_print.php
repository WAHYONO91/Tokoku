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
  <style>
    body{background:#fff;color:#000;}
    .doc{max-width:800px;margin:0 auto;}
    table{width:100%;border-collapse:collapse;}
    th,td{border:1px solid #000;padding:4px 6px;font-size:13px;}
    .right{text-align:right;}
    @media print {.no-print{display:none;}}
  </style>
</head>
<body onload="window.print()">
<div class="doc">
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
  <p class="no-print" style="margin-top:1rem;"><a href="/tokoapp/purchases_report.php">Kembali</a></p>
</div>
</body>
</html>
