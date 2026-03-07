<?php
require_once __DIR__.'/config.php';
require_access('INVENTORY');
require_once __DIR__.'/includes/header.php';

$err=''; $ok='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $kode = trim($_POST['kode'] ?? '');
  $nama = trim($_POST['nama'] ?? '');
  $unit = trim($_POST['unit'] ?? 'pcs');
  $harga_beli = (int)($_POST['harga_beli'] ?? 0);
  $h1 = (int)($_POST['harga_jual1'] ?? 0);
  $h2 = (int)($_POST['harga_jual2'] ?? 0);
  $h3 = (int)($_POST['harga_jual3'] ?? 0);
  $h4 = (int)($_POST['harga_jual4'] ?? 0);
  $min_stock = (int)($_POST['min_stock'] ?? 0);

  $kategori = trim($_POST['kategori'] ?? '');

  if($kode==='' || $nama===''){
    $err = 'Kode dan nama wajib diisi.';
  } else {
    // Handle image upload
    $gambar = '';
    if (isset($_FILES['gambar']) && $_FILES['gambar']['error'] === UPLOAD_ERR_OK) {
        $tmp = $_FILES['gambar']['tmp_name'];
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $kode) . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/uploads/items/' . $filename;
        if (!is_dir(__DIR__ . '/uploads/items/')) {
            mkdir(__DIR__ . '/uploads/items/', 0777, true);
        }
        if (move_uploaded_file($tmp, $dest)) {
            $gambar = $filename;
        }
    }

    try{
      $stmt = $pdo->prepare("INSERT INTO items(kode,nama,kategori,gambar,unit,harga_beli,harga_jual1,harga_jual2,harga_jual3,harga_jual4,min_stock,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,NOW())");
      $stmt->execute([$kode,$nama,$kategori,$gambar,$unit,$harga_beli,$h1,$h2,$h3,$h4,$min_stock]);
      // buat stok gudang & toko 0
      ensure_stock_rows($pdo, $kode);
      $ok = 'Barang berhasil disimpan.';
    } catch(PDOException $e){
      $err = 'Gagal simpan: '.$e->getMessage();
    }
  }
}
?>
<article>
  <h3>Tambah Barang</h3>
  <?php if($err): ?><mark><?=htmlspecialchars($err)?></mark><?php endif; ?>
  <?php if($ok): ?><mark style="background:#16a34a;color:#fff;"><?=htmlspecialchars($ok)?></mark><?php endif; ?>
  <form method="post" enctype="multipart/form-data">
    <div class="grid">
      <label>Kode
        <input type="text" name="kode" required>
      </label>
      <label>Nama
        <input type="text" name="nama" required>
      </label>
      <label>Kategori
        <input type="text" name="kategori" placeholder="Contoh: ATK, Sembako, dll" list="catList">
        <datalist id="catList">
          <?php
          try {
              $cats = $pdo->query("SELECT DISTINCT kategori FROM items WHERE kategori IS NOT NULL AND kategori != ''")->fetchAll(PDO::FETCH_COLUMN);
              foreach ($cats as $c) echo "<option value=\"".htmlspecialchars($c)."\">";
          } catch(Exception $e) {}
          ?>
        </datalist>
      </label>
      <label>Gambar
        <input type="file" name="gambar" accept="image/*">
      </label>
      <label>Satuan
        <select name="unit">
          <option value="pcs">pcs</option>
          <option value="kg">kg</option>
          <option value="liter">liter</option>
          <option value="dus">dus</option>
          <option value="pak">pak</option>
        </select>
      </label>
    </div>
    <div class="grid">
      <label>Harga Beli
        <input type="number" name="harga_beli" value="0" min="0">
      </label>
      <label>Harga Jual 1
        <input type="number" name="harga_jual1" value="0" min="0">
      </label>
      <label>Harga Jual 2
        <input type="number" name="harga_jual2" value="0" min="0">
      </label>
      <label>Harga Jual 3
        <input type="number" name="harga_jual3" value="0" min="0">
      </label>
      <label>Harga Jual 4
        <input type="number" name="harga_jual4" value="0" min="0">
      </label>
      <label>Min Stok
        <input type="number" name="min_stock" value="0" min="0">
      </label>
    </div>
    <button type="submit">Simpan</button>
    <a href="items.php" class="secondary">Kembali</a>
  </form>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
