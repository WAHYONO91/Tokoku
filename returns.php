<?php
require_once __DIR__.'/includes/header.php';
require_access('RETURNS');
// Kasir & admin boleh retur
// Step 1: input sale_id
if($_SERVER['REQUEST_METHOD']!=='POST'){
?>
<article>
  <h3>Retur Penjualan</h3>
  <form method="post">
    <label>Nomor Faktur Penjualan
      <input name="sale_id" type="number" required>
    </label>
    <button>Ambil Data</button>
  </form>
</article>
<?php include __DIR__.'/includes/footer.php'; exit; } 

$sale_id = (int)($_POST['sale_id'] ?? 0);
$stmt = $pdo->prepare("SELECT s.*, u.username, m.nama as member_nama FROM sales s
  JOIN users u ON u.id=s.user_id
  LEFT JOIN members m ON m.kode=s.member_kode
  WHERE s.id=?");
$stmt->execute([$sale_id]);
$sale = $stmt->fetch();
if(!$sale){ echo "<mark>Faktur tidak ditemukan</mark>"; include __DIR__.'/includes/footer.php'; exit; }

$items = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id=?");
$items->execute([$sale_id]);
$items = $items->fetchAll();
?>
<article>
  <h3>Retur Penjualan #<?=$sale_id?></h3>
  <p><strong>Tanggal</strong>: <?=$sale['tanggal']?> &nbsp; <strong>Kasir</strong>: <?=$sale['username']?> &nbsp; <strong>Member</strong>: <?=htmlspecialchars($sale['member_nama']??'-')?></p>
  <form method="post" action="returns_save.php" onsubmit="return doSave(event)">
    <input type="hidden" name="sale_id" value="<?=$sale_id?>">
    <table class="table-small" id="tbl">
      <thead><tr><th>Kode</th><th>Nama</th><th class="right">Qty Dibeli</th><th class="right">Qty Retur</th><th class="right">Harga</th><th class="right">Total Retur</th></tr></thead>
      <tbody>
      <?php foreach($items as $it): ?>
        <tr>
          <td><?=htmlspecialchars($it['item_kode'])?><input type="hidden" name="kode[]" value="<?=htmlspecialchars($it['item_kode'])?>"></td>
          <td><?=htmlspecialchars($it['nama_item'])?><input type="hidden" name="nama[]" value="<?=htmlspecialchars($it['nama_item'])?>"></td>
          <td class="right"><?=$it['qty']?></td>
          <td class="right"><input type="number" name="qty[]" min="0" max="<?=$it['qty']?>" value="0" data-price="<?=$it['harga_satuan']?>" data-max="<?=$it['qty']?>" class="qty-ret"></td>
          <td class="right"><?=rupiah($it['harga_satuan'])?><input type="hidden" name="harga[]" value="<?=$it['harga_satuan']?>"></td>
          <td class="right total-ret">0</td>
        </tr>
      <?php endforeach; ?>
      </tbody>
      <tfoot><tr><th colspan="5" class="right">Total Refund</th><th class="right" id="grand">0</th></tr></tfoot>
    </table>
    <button>Simpan Retur</button>
  </form>
</article>
<script>
function fmt(n){ return new Intl.NumberFormat('id-ID').format(n); }
function recalc(){
  let grand=0;
  document.querySelectorAll('.qty-ret').forEach(inp=>{
    const price=parseInt(inp.dataset.price||'0');
    const qty=parseInt(inp.value||'0');
    const tot=price*qty; grand+=tot;
    inp.closest('tr').querySelector('.total-ret').textContent = fmt(tot);
  });
  document.getElementById('grand').textContent = fmt(grand);
}
document.querySelectorAll('.qty-ret').forEach(inp=>{
  inp.oninput = ()=>{
    const max=parseInt(inp.dataset.max||'0'); let v=parseInt(inp.value||'0');
    if(v<0) v=0; if(v>max) v=max; inp.value=v; recalc();
  };
});
recalc();
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
