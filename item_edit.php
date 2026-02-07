<?php
require_once __DIR__.'/config.php';
require_access('INVENTORY');
require_once __DIR__.'/includes/header.php';

// Ambil kode dari POST dulu, kalau tidak ada baru dari GET
$kode = $_POST['kode'] ?? ($_GET['kode'] ?? '');

if ($kode === '') {
  echo "<article><mark>Kode tidak ditemukan.</mark></article>";
  include __DIR__.'/includes/footer.php';
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM items WHERE kode = ?");
$stmt->execute([$kode]);
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
  echo "<article><mark>Data barang tidak ditemukan.</mark></article>";
  include __DIR__.'/includes/footer.php';
  exit;
}

$err = '';
$ok  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $nama        = trim($_POST['nama'] ?? '');
  $unit        = trim($_POST['unit'] ?? 'pcs');
  $harga_beli  = (int)($_POST['harga_beli']  ?? 0);
  $h1          = (int)($_POST['harga_jual1'] ?? 0);
  $h2          = (int)($_POST['harga_jual2'] ?? 0);
  $h3          = (int)($_POST['harga_jual3'] ?? 0);
  $h4          = (int)($_POST['harga_jual4'] ?? 0);
  $min_stock   = (int)($_POST['min_stock']   ?? 0);

  if ($nama === '') {
    $err = 'Nama tidak boleh kosong.';
  } else {
    // Tambah updated_at supaya ikut berubah
    $upd = $pdo->prepare("
      UPDATE items
      SET nama = ?,
          unit = ?,
          harga_beli = ?,
          harga_jual1 = ?,
          harga_jual2 = ?,
          harga_jual3 = ?,
          harga_jual4 = ?,
          min_stock = ?,
          updated_at = NOW()
      WHERE kode = ?
    ");

    $upd->execute([
      $nama,
      $unit,
      $harga_beli,
      $h1,
      $h2,
      $h3,
      $h4,
      $min_stock,
      $kode
    ]);

    // Cek apakah benar-benar ada baris yang ter-update
    if ($upd->rowCount() > 0) {
      $ok = 'Data barang diperbarui.';
    } else {
      // Biasanya karena nilainya sama persis seperti sebelumnya,
      // atau kode tidak cocok
      $ok = 'Tidak ada data yang berubah (nilainya sama atau kode tidak cocok).';
    }
  }

  // refresh data dari database
  $stmt->execute([$kode]);
  $item = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>
<article>
  <h3>Edit Barang</h3>

  <?php if ($err): ?>
    <mark><?= htmlspecialchars($err) ?></mark>
  <?php endif; ?>

  <?php if ($ok): ?>
    <mark style="background:#16a34a;color:#fff;"><?= htmlspecialchars($ok) ?></mark>
  <?php endif; ?>

  <form method="post">
    <!-- Kode ditampilkan (read-only) DAN dikirim lewat hidden input -->
    <label>Kode
      <input type="text" value="<?= htmlspecialchars($item['kode']) ?>" readonly>
    </label>
    <input type="hidden" name="kode" value="<?= htmlspecialchars($item['kode']) ?>">

    <label>Nama
      <input type="text" name="nama" value="<?= htmlspecialchars($item['nama']) ?>">
    </label>

    <label>Satuan
      <select name="unit">
        <?php
          $units = ['pcs','kg','liter','dus','pak'];
          $currentUnit = $item['unit'] ?? 'pcs';
          foreach ($units as $u) {
            $sel = ($currentUnit === $u) ? 'selected' : '';
            echo "<option value=\"$u\" $sel>$u</option>";
          }
        ?>
      </select>
    </label>

    <div class="grid">
      <label>Harga Beli
        <input type="number" name="harga_beli" value="<?= (int)$item['harga_beli'] ?>">
      </label>
      <label>Harga Jual 1
        <input type="number" name="harga_jual1" value="<?= (int)$item['harga_jual1'] ?>">
      </label>
      <label>Harga Jual 2
        <input type="number" name="harga_jual2" value="<?= (int)$item['harga_jual2'] ?>">
      </label>
      <label>Harga Jual 3
        <input type="number" name="harga_jual3" value="<?= (int)$item['harga_jual3'] ?>">
      </label>
      <label>Harga Jual 4
        <input type="number" name="harga_jual4" value="<?= (int)$item['harga_jual4'] ?>">
      </label>
      <label>Min Stok
        <input type="number" name="min_stock" value="<?= (int)$item['min_stock'] ?>">
      </label>
    </div>

    <button type="submit">Simpan</button>
    <a href="items.php" class="secondary">Kembali</a>
  </form>
</article>

<?php include __DIR__.'/includes/footer.php'; ?>
