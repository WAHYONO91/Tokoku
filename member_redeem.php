<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin','kasir']); // kasir boleh tukar poin
require_once __DIR__.'/includes/header.php';

$kode = $_GET['kode'] ?? '';
$kode = trim($kode);

if ($kode === '') {
  echo "<article><mark>Kode member tidak valid.</mark><p><a href='members.php'>Kembali</a></p></article>";
  include __DIR__.'/includes/footer.php';
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM members WHERE kode=? LIMIT 1");
$stmt->execute([$kode]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if(!$member){
  echo "<article><mark>Member tidak ditemukan.</mark><p><a href='members.php'>Kembali</a></p></article>";
  include __DIR__.'/includes/footer.php';
  exit;
}

// Helper ambil nilai fleksibel
function get_val($arr, $keys, $default='') {
  foreach ((array)$keys as $k) {
    if (array_key_exists($k, $arr) && $arr[$k] !== null) return $arr[$k];
  }
  return $default;
}

// Ambil poin dan jenis dengan fallback
$points = (int) get_val($member, ['points','poin','point'], 0);

$jenis = strtolower(trim(get_val($member, ['jenis'], 'umum')));
if (!in_array($jenis, ['umum','grosir'], true)) {
  $jenis = 'umum';
}

// Warna badge jenis
$badgeBg = ($jenis === 'grosir') ? '#1d4ed8' : '#6b7280';
$badgeTx = '#fff';
?>
<article>
  <h3>Tukar Poin Member</h3>
  <p>Kamu sedang menukar poin untuk:</p>
  <ul>
    <li><strong>Kode:</strong> <?=htmlspecialchars($member['kode'] ?? '')?></li>
    <li><strong>Nama:</strong> <?=htmlspecialchars($member['nama'] ?? '')?></li>
    <li><strong>Jenis:</strong>
      <span class="badge-jenis" style="background:<?= $badgeBg ?>;color:<?= $badgeTx ?>;padding:.2rem .5rem;border-radius:.4rem;font-size:.85rem;">
        <?= htmlspecialchars(ucfirst($jenis)) ?>
      </span>
    </li>
    <li><strong>Poin saat ini:</strong> <?=number_format($points,0,',','.')?></li>
  </ul>

  <form method="post" action="member_redeem_save.php">
    <input type="hidden" name="kode" value="<?=htmlspecialchars($member['kode'] ?? '')?>">
    <label>Jumlah poin yang ditukar
      <input type="number" name="redeem_points" min="1" max="<?= $points ?>" required>
      <small>Masukkan poin yang mau dikurangi dari member ini.</small>
    </label>
    <label>Keterangan penukaran
      <textarea name="description" rows="2" placeholder="misal: ditukar dengan gula 1kg, promo harian"></textarea>
    </label>
    <label>Waktu penukaran
      <input type="datetime-local" name="redeemed_at" value="<?=date('Y-m-d\TH:i')?>">
    </label>

    <!-- (Opsional) kirimkan jenis sebagai informasi tambahan ke handler -->
    <input type="hidden" name="jenis" value="<?=htmlspecialchars($jenis)?>">

    <button type="submit">Simpan Penukaran</button>
    <a href="members.php" class="secondary">Batal</a>
  </form>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
