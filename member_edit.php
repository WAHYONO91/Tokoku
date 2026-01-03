<?php
require_once __DIR__.'/config.php';
require_login();

// --- Ambil parameter kode (kode lama) ---
$kode = trim($_GET['kode'] ?? '');
if ($kode === '') {
  header('Location: members.php');
  exit;
}

$err = ''; $ok = '';

// helper untuk membaca field fleksibel
function get_val($arr, $keys, $default='') {
  foreach ((array)$keys as $k) {
    if (isset($arr[$k]) && $arr[$k] !== null) return $arr[$k];
  }
  return $default;
}

// --- Ambil data existing member (berdasarkan kode lama) ---
$stmt = $pdo->prepare("SELECT * FROM members WHERE kode = ? LIMIT 1");
$stmt->execute([$kode]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
  $err = 'Member tidak ditemukan.';
}

// default form
$form = [
  'old_kode' => $kode,
  'kode'     => $member['kode'] ?? $kode,
  'nama'     => $member['nama'] ?? '',
  'alamat'   => $member['alamat'] ?? '',
  'telp'     => $member ? get_val($member, ['telp','tlp','phone'], '') : '',
  'points'   => $member ? (int)get_val($member, ['points','poin','point'], 0) : 0,
  'jenis'    => $member ? strtolower(trim(get_val($member, ['jenis'], 'umum'))) : 'umum',
];

if (!in_array($form['jenis'], ['umum','grosir'], true)) $form['jenis'] = 'umum';

// --- Proses update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $form = [
    'old_kode' => trim($_POST['old_kode'] ?? $kode),   // kode lama untuk WHERE
    'kode'     => trim($_POST['kode'] ?? ''),          // kode baru (boleh berubah)
    'nama'     => trim($_POST['nama'] ?? ''),
    'alamat'   => trim($_POST['alamat'] ?? ''),
    'telp'     => trim($_POST['telp'] ?? ''),
    'points'   => (int)($_POST['points'] ?? 0),
    'jenis'    => strtolower(trim($_POST['jenis'] ?? 'umum')),
  ];

  if (!in_array($form['jenis'], ['umum','grosir'], true)) $form['jenis'] = 'umum';

  // Validasi wajib
  if ($form['kode'] === '') {
    $err = 'Kode member wajib diisi.';
  } elseif ($form['nama'] === '') {
    $err = 'Nama wajib diisi.';
  }

  // Cek duplikat kode jika kode berubah
  if ($err === '' && $form['kode'] !== $form['old_kode']) {
    $cek = $pdo->prepare("SELECT COUNT(*) FROM members WHERE kode = ?");
    $cek->execute([$form['kode']]);
    if ((int)$cek->fetchColumn() > 0) {
      $err = 'Kode member sudah digunakan. Silakan pakai kode lain.';
    }
  }

  if ($err === '') {
    try {
      // Skema kolom "benar"
      $q = "UPDATE members
            SET kode=?, nama=?, jenis=?, alamat=?, telp=?, points=?
            WHERE kode=?";
      $stmt = $pdo->prepare($q);
      $stmt->execute([
        $form['kode'],
        $form['nama'],
        $form['jenis'],
        $form['alamat'],
        $form['telp'],
        $form['points'],
        $form['old_kode']
      ]);
      $ok = 'Perubahan member berhasil disimpan.';
    } catch (PDOException $e1) {
      try {
        // Fallback: skema lama (tlp, poin) + jenis ada
        $q2 = "UPDATE members
               SET kode=?, nama=?, jenis=?, alamat=?, tlp=?, poin=?
               WHERE kode=?";
        $stmt2 = $pdo->prepare($q2);
        $stmt2->execute([
          $form['kode'],
          $form['nama'],
          $form['jenis'],
          $form['alamat'],
          $form['telp'],
          $form['points'],
          $form['old_kode']
        ]);
        $ok = 'Perubahan member berhasil disimpan (skema lama).';
      } catch (PDOException $e2) {
        try {
          // Fallback terakhir: skema lama tanpa kolom 'jenis'
          $q3 = "UPDATE members
                 SET kode=?, nama=?, alamat=?, tlp=?, poin=?
                 WHERE kode=?";
          $stmt3 = $pdo->prepare($q3);
          $stmt3->execute([
            $form['kode'],
            $form['nama'],
            $form['alamat'],
            $form['telp'],
            $form['points'],
            $form['old_kode']
          ]);
          $ok = 'Perubahan member berhasil disimpan (tanpa kolom jenis).';
        } catch (PDOException $e3) {
          $err = 'Gagal menyimpan perubahan: '.$e3->getMessage();
        }
      }
    }

    // Kalau kode berubah, redirect ke URL baru (HARUS sebelum include header)
    if ($ok && $form['kode'] !== $form['old_kode']) {
      header('Location: member_edit.php?kode='.urlencode($form['kode']).'&ok=1');
      exit;
    }

    // reload data dari DB setelah update sukses (kode tidak berubah)
    if ($ok) {
      $stmt = $pdo->prepare("SELECT * FROM members WHERE kode = ? LIMIT 1");
      $stmt->execute([$form['kode']]);
      $member = $stmt->fetch(PDO::FETCH_ASSOC);

      if ($member) {
        $form['old_kode'] = $member['kode'] ?? $form['kode'];
        $form['kode']     = $member['kode'] ?? $form['kode'];
        $form['nama']     = $member['nama'] ?? $form['nama'];
        $form['alamat']   = $member['alamat'] ?? $form['alamat'];
        $form['telp']     = get_val($member, ['telp','tlp','phone'], $form['telp']);
        $form['points']   = (int)get_val($member, ['points','poin','point'], $form['points']);
        $form['jenis']    = strtolower(trim(get_val($member, ['jenis'], $form['jenis'])));
        if (!in_array($form['jenis'], ['umum','grosir'], true)) $form['jenis'] = 'umum';
      }
    }
  }
}

// pesan ok dari redirect setelah ubah kode
if (isset($_GET['ok']) && $_GET['ok'] == '1') {
  $ok = $ok ?: 'Perubahan member berhasil disimpan.';
}

// BARU include header di bawah (supaya header() aman)
require_once __DIR__.'/includes/header.php';
?>

<article>
  <h3>Edit Member</h3>

  <?php if($err): ?>
    <mark style="display:block;margin-bottom:.6rem;"><?=htmlspecialchars($err)?></mark>
  <?php endif; ?>

  <?php if($ok): ?>
    <mark style="display:block;margin-bottom:.6rem;background:#16a34a;color:#fff;">✅ <?=htmlspecialchars($ok)?></mark>
  <?php endif; ?>

  <?php if($member): ?>
    <form method="post" id="memberForm" autocomplete="off">
      <input type="hidden" name="old_kode" value="<?=htmlspecialchars($form['old_kode'] ?? $kode)?>">

      <div class="grid">
        <label>Kode Member
          <input id="kode" type="text" name="kode" required value="<?=htmlspecialchars($form['kode'] ?? '')?>">
        </label>

        <label>Nama Member
          <input id="nama" type="text" name="nama" required value="<?=htmlspecialchars($form['nama'] ?? '')?>">
        </label>
      </div>

      <div class="grid">
        <label>Jenis Pelanggan
          <?php $valJenis = $form['jenis'] ?? 'umum'; ?>
          <select id="jenis" name="jenis">
            <option value="umum"   <?= $valJenis==='umum'?'selected':''; ?>>Umum</option>
            <option value="grosir" <?= $valJenis==='grosir'?'selected':''; ?>>Grosir</option>
          </select>
        </label>

        <label>Telepon / HP
          <input id="telp" type="text" name="telp" value="<?=htmlspecialchars($form['telp'] ?? '')?>">
        </label>
      </div>

      <label>Alamat
        <textarea id="alamat" name="alamat" rows="2"><?=htmlspecialchars($form['alamat'] ?? '')?></textarea>
      </label>

      <div class="grid">
        <label>Poin
          <input id="points" type="number" name="points" min="0" value="<?=htmlspecialchars((string)($form['points'] ?? 0))?>">
        </label>
      </div>

      <button id="btnSimpan" type="submit">Simpan Perubahan</button>
      <a id="btnKembali" href="members.php" class="secondary">Kembali</a>
    </form>

    <script>
    (() => {
      const fields = [
        document.querySelector('#kode'),
        document.querySelector('#nama'),
        document.querySelector('#jenis'),
        document.querySelector('#telp'),
        document.querySelector('#alamat'),
        document.querySelector('#points'),
        document.querySelector('#btnSimpan'),
        document.querySelector('#btnKembali'),
      ].filter(Boolean);

      const idx = el => fields.indexOf(el);

      function focusTo(i){
        const el = fields[i];
        if(!el) return;
        el.focus();
        if (el.tagName === 'INPUT' && el.select) el.select();
      }

      window.addEventListener('load', () => focusTo(0));

      document.addEventListener('keydown', e => {
        const el = document.activeElement;
        const i = idx(el);
        if(i === -1) return;

        if (e.key === 'ArrowDown') {
          e.preventDefault();
          focusTo(Math.min(fields.length - 1, i + 1));
          return;
        }
        if (e.key === 'ArrowUp') {
          e.preventDefault();
          focusTo(Math.max(0, i - 1));
          return;
        }

        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
          // INPUT: biarkan kiri/kanan untuk gerak kursor
          if (el && el.tagName === 'INPUT') return;

          // selain INPUT (select/textarea/button/link): kiri/kanan = navigasi
          e.preventDefault();
          if (e.key === 'ArrowRight') focusTo(Math.min(fields.length - 1, i + 1));
          else focusTo(Math.max(0, i - 1));
        }
      });
    })();
    </script>
  <?php endif; ?>
</article>

<?php include __DIR__.'/includes/footer.php'; ?>
