<?php
require_once __DIR__.'/config.php';
require_login();
require_once __DIR__.'/includes/header.php';

$err=''; $ok='';

if($_SERVER['REQUEST_METHOD']==='POST'){
  $kode   = trim($_POST['kode']  ?? '');
  $nama   = trim($_POST['nama']  ?? '');
  $alamat = trim($_POST['alamat']?? '');
  $telp   = trim($_POST['telp']  ?? '');
  $points = (int)($_POST['points'] ?? 0);

  $jenis  = strtolower(trim($_POST['jenis'] ?? 'umum'));
  if(!in_array($jenis, ['umum','grosir'], true)){
    $jenis = 'umum';
  }

  if($kode==='' || $nama===''){
    $err = 'Kode dan Nama wajib diisi.';
  } else {
    try{
      $stmt = $pdo->prepare("
        INSERT INTO members (kode, nama, jenis, alamat, telp, points, created_at)
        VALUES (?,?,?,?,?,?,NOW())
      ");
      $stmt->execute([$kode,$nama,$jenis,$alamat,$telp,$points]);
      $ok = 'Member berhasil ditambahkan.';
    } catch(PDOException $e){
      $err = 'Gagal simpan member: '.$e->getMessage();
    }
  }
}
?>

<article>
<h3>Tambah Member</h3>

<?php if($err): ?>
<mark><?=htmlspecialchars($err)?></mark>
<?php endif; ?>

<?php if($ok): ?>
<mark style="background:#16a34a;color:#fff;"><?=htmlspecialchars($ok)?></mark>
<?php endif; ?>

<form method="post" id="memberForm">
  <div class="grid">
    <label>Kode
      <input id="kode" name="kode" required>
    </label>
    <label>Nama
      <input id="nama" name="nama" required>
    </label>
  </div>

  <div class="grid">
    <label>Jenis
      <select id="jenis" name="jenis">
        <option value="umum">Umum</option>
        <option value="grosir">Grosir</option>
      </select>
    </label>

    <label>Telepon
      <input id="telp" name="telp">
    </label>
  </div>

  <label>Alamat
    <textarea id="alamat" name="alamat" rows="2"></textarea>
  </label>

  <label>Poin Awal
    <input id="points" type="number" name="points" value="0">
  </label>

  <button id="btnSimpan">Simpan</button>
  <a id="btnKembali" href="members.php">Kembali</a>
</form>

<script>
(() => {
  const fields = [
    kode, nama, jenis, telp, alamat, points, btnSimpan, btnKembali
  ];

  const indexOf = el => fields.indexOf(el);

  function focusTo(i){
    const el = fields[i];
    if(!el) return;
    el.focus();
    if(el.select) el.select();
  }

  window.onload = () => focusTo(0);

  document.addEventListener('keydown', e => {
    const el = document.activeElement;
    const i = indexOf(el);
    if(i === -1) return;

    switch(e.key){
      case 'ArrowDown':
        e.preventDefault();
        focusTo(Math.min(fields.length-1, i+1));
        break;

      case 'ArrowUp':
        e.preventDefault();
        focusTo(Math.max(0, i-1));
        break;

      case 'ArrowLeft':
      case 'ArrowRight':
        // hanya input text/number yang pakai kursor
        if(el.tagName === 'INPUT') return;
        e.preventDefault();
        focusTo(e.key === 'ArrowRight'
          ? Math.min(fields.length-1, i+1)
          : Math.max(0, i-1)
        );
        break;
    }
  });
})();
</script>
</article>

<?php include __DIR__.'/includes/footer.php'; ?>
