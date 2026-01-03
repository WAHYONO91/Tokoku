<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, u.username, m.nama as member_nama, m.kode as member_kode FROM sales s
  JOIN users u ON u.id = s.user_id
  LEFT JOIN members m ON m.kode = s.member_kode
  WHERE s.id=?");
$stmt->execute([$id]);
$sale = $stmt->fetch();
if(!$sale){ die('Data tidak ditemukan'); }

$items = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?");
$items->execute([$id]);
$items = $items->fetchAll();
?>
<?php include __DIR__.'/includes/header.php'; ?>
<article>
  <h3>Faktur Penjualan</h3>
  <p><strong>No</strong>: <?=$sale['id']?> &nbsp; <strong>Tanggal</strong>: <?=$sale['tanggal']?> &nbsp; <strong>Kasir</strong>: <?=$sale['username']?> &nbsp; <strong>Shift</strong>: <?=$sale['shift']?></p>
  <?php if($sale['member_kode']): ?>
    <p><strong>Member</strong>: <?=htmlspecialchars($sale['member_kode'])?> - <?=htmlspecialchars($sale['member_nama'])?></p>
  <?php endif; ?>
  <table class="table-small">
    <thead><tr><th>Kode</th><th>Nama</th><th class="right">Qty</th><th class="right">Harga</th><th class="right">Total</th></tr></thead>
    <tbody>
      <?php foreach($items as $it): ?>
        <tr>
          <td><?=htmlspecialchars($it['item_kode'])?></td>
          <td><?=htmlspecialchars($it['nama_item'])?></td>
          <td class="right"><?=$it['qty']?></td>
          <td class="right"><?=rupiah($it['harga_satuan'])?></td>
          <td class="right"><?=rupiah($it['total'])?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr><th colspan="4" class="right">Subtotal</th><th class="right"><?=rupiah($sale['subtotal'])?></th></tr>
      <tr><th colspan="4" class="right">Diskon</th><th class="right"><?=rupiah($sale['discount'])?></th></tr>
      <tr><th colspan="4" class="right">PPN/Pajak</th><th class="right"><?=rupiah($sale['tax'])?></th></tr>
      <tr><th colspan="4" class="right">Total</th><th class="right"><?=rupiah($sale['total'])?></th></tr>
      <tr><th colspan="4" class="right">Tunai</th><th class="right"><?=rupiah($sale['tunai'])?></th></tr>
      <tr><th colspan="4" class="right">Kembalian</th><th class="right"><?=rupiah($sale['kembalian'])?></th></tr>
      <tr><th colspan="4" class="right">Poin Didapat</th><th class="right"><?=$sale['poin_didapat']?></th></tr>
    </tfoot>
  </table>
  <button class="no-print" onclick="window.print()">Cetak</button>
  <a class="no-print" href="/tokoapp/pos.php">Kembali ke POS</a>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
