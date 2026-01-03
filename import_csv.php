<?php
require_once __DIR__.'/includes/header.php';
require_login();
require_role(['admin']);

if($_SERVER['REQUEST_METHOD']==='POST' && isset($_FILES['csv'])){
  $loc = $_POST['location'] ?? '';
  $tmp = $_FILES['csv']['tmp_name'];
  $rows = array_map('str_getcsv', file($tmp));
  $header = array_map('trim', array_shift($rows));
  // Expected columns: kode, nama, harga_beli, harga_jual1, harga_jual2, harga_jual3, harga_jual4, min_stock, stok_gudang, stok_toko
  $count=0;
  foreach($rows as $r){
    $d = array_combine($header, $r);
    if(!$d) continue;
    $kode = trim($d['kode'] ?? '');
    if($kode==='') continue;
    $stmt = $pdo->prepare("INSERT INTO items(kode, nama, harga_beli, harga_jual1, harga_jual2, harga_jual3, harga_jual4, min_stock)
      VALUES(?,?,?,?,?,?,?,?)
      ON DUPLICATE KEY UPDATE nama=VALUES(nama), harga_beli=VALUES(harga_beli), harga_jual1=VALUES(harga_jual1),
      harga_jual2=VALUES(harga_jual2), harga_jual3=VALUES(harga_jual3), harga_jual4=VALUES(harga_jual4), min_stock=VALUES(min_stock)");
    $stmt->execute([
      $kode,
      $d['nama'] ?? $kode,
      (int)($d['harga_beli']??0),
      (int)($d['harga_jual1']??0),
      (int)($d['harga_jual2']??0),
      (int)($d['harga_jual3']??0),
      (int)($d['harga_jual4']??0),
      (int)($d['min_stock']??0),
    ]);
    ensure_stock_rows($pdo, $kode);
    if(isset($d['stok_gudang'])){
      $pdo->prepare("UPDATE item_stocks SET qty=? WHERE item_kode=? AND location='gudang'")->execute([(int)$d['stok_gudang'], $kode]);
    }
    if(isset($d['stok_toko'])){
      $pdo->prepare("UPDATE item_stocks SET qty=? WHERE item_kode=? AND location='toko'")->execute([(int)$d['stok_toko'], $kode]);
    }
    $count++;
  }
  echo "<mark>Import selesai: $count baris.</mark>";
}
?>
<article>
  <h3>Import CSV (Barang & Stok)</h3>
  <p>Header kolom yang didukung (urut bebas): <code>kode, nama, harga_beli, harga_jual1, harga_jual2, harga_jual3, harga_jual4, min_stock, stok_gudang, stok_toko</code></p>
  <form method="post" enctype="multipart/form-data">
    <label>File CSV <input type="file" name="csv" accept=".csv" required></label>
    <button>Import</button>
  </form>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
