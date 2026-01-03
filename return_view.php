<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/includes/functions.php';
require_login();
$id = (int)($_GET['id'] ?? 0);
$stmt = $pdo->prepare("SELECT r.*, u.username, r.member_kode, m.nama as member_nama FROM sales_returns r
  JOIN users u ON u.id=r.user_id
  LEFT JOIN members m ON m.kode=r.member_kode
  WHERE r.id=?");
$stmt->execute([$id]);
$hdr = $stmt->fetch();
if(!$hdr){ die('Data tidak ditemukan'); }
$det = $pdo->prepare("SELECT * FROM sales_return_items WHERE return_id=?");
$det->execute([$id]);
$items = $det->fetchAll();
?>
<?php include __DIR__.'/includes/header.php'; ?>
<article>
  <h3>Retur Penjualan</h3>
  <p><strong>No</strong>: <?=$hdr['id']?> &nbsp; <strong>Tanggal</strong>: <?=$hdr['tanggal']?> &nbsp; <strong>User</strong>: <?=$hdr['username']?></p>
  <?php if($hdr['member_kode']): ?>
    <p><strong>Member</strong>: <?=htmlspecialchars($hdr['member_kode'])?> - <?=htmlspecialchars($hdr['member_nama'])?></p>
  <?php endif; ?>
  <table class="table-small">
    <thead><tr><th>Kode</th><th>Nama</th><th class="right">Qty</th><th class="right">Harga</th><th class="right">Total</th></tr></thead>
    <tbody>
      <?php foreach($items as $it): ?>
      <tr>
        <td><?=htmlspecialchars($it['item_kode'])?></td>
        <td><?=htmlspecialchars($it['item_kode'])?></td>
        <td class="right"><?=$it['qty']?></td>
        <td class="right"><?=rupiah($it['harga_satuan'])?></td>
        <td class="right"><?=rupiah($it['total'])?></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot><tr><th colspan="4" class="right">Total Refund</th><th class="right"><?=rupiah($hdr['total_refund'])?></th></tr></tfoot>
  </table>
  <button class="no-print" onclick="window.print()">Cetak</button>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
