<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT p.*, u.username, s.nama as supplier_nama FROM purchases p
  JOIN users u ON u.id=p.user_id
  LEFT JOIN suppliers s ON s.id=p.supplier_id
  WHERE p.id=?");
$stmt->execute([$id]);
$hdr = $stmt->fetch();
if(!$hdr){ die('Data tidak ditemukan'); }
$det = $pdo->prepare("SELECT * FROM purchase_items WHERE purchase_id=?");
$det->execute([$id]);
$items = $det->fetchAll();
?>
<?php include __DIR__.'/includes/header.php'; ?>
<article>
  <h3>Detail Pembelian</h3>
  <p><strong>No</strong>: <?=$hdr['id']?> &nbsp; <strong>Tanggal</strong>: <?=$hdr['tanggal']?> &nbsp; <strong>User</strong>: <?=$hdr['username']?> &nbsp; <strong>Lokasi</strong>: <?=$hdr['location']?></p>
  <p><strong>Supplier</strong>: <?=htmlspecialchars($hdr['supplier_nama'] ?? '-')?></p>
  <table class="table-small">
    <thead><tr><th>Kode</th><th>Nama</th><th class="right">Qty</th><th class="right">Harga Beli</th><th class="right">Total</th></tr></thead>
    <tbody>
      <?php foreach($items as $it): ?>
      <tr>
        <td><?=htmlspecialchars($it['item_kode'])?></td>
        <td><?=htmlspecialchars($it['nama_item'])?></td>
        <td class="right"><?=$it['qty']?></td>
        <td class="right"><?=rupiah($it['harga_beli'])?></td>
        <td class="right"><?=rupiah($it['total'])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="4" class="right">Total</th><th class="right"><?=rupiah($hdr['total'])?></th></tr></tfoot>
  </table>
  <button class="no-print" onclick="window.print()">Cetak</button>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
