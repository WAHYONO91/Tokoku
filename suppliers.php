<?php
require_once __DIR__.'/config.php';
require_access('SUPPLIER');

// ===== Helper: cek apakah kolom ada =====
function table_has_column(PDO $pdo, string $table, string $column): bool {
  static $cache = [];
  $key = $table.'|'.$column;
  if (isset($cache[$key])) return $cache[$key];
  $stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = ?
      AND COLUMN_NAME = ?
  ");
  $stmt->execute([$table, $column]);
  $cache[$key] = ((int)$stmt->fetchColumn() > 0);
  return $cache[$key];
}

$hasCreated = table_has_column($pdo, 'suppliers', 'created_at');
$hasUpdated = table_has_column($pdo, 'suppliers', 'updated_at');

$msg=''; $err='';

// ===== HAPUS (GET ?delete=KODE) =====
if (isset($_GET['delete'])) {
  $delKode = trim($_GET['delete']);
  if ($delKode !== '') {
    try {
      $stmt = $pdo->prepare("DELETE FROM suppliers WHERE kode = ?");
      $stmt->execute([$delKode]);
      if ($stmt->rowCount() > 0) {
        header('Location: '.$_SERVER['PHP_SELF'].'?saved=deleted');
        exit;
      } else {
        $err = 'Data tidak ditemukan.';
      }
    } catch (Throwable $th) {
      $err = 'Gagal menghapus: '.$th->getMessage();
    }
  }
}

// ===== MODE EDIT (GET ?edit=KODE) =====
$editKode = isset($_GET['edit']) ? trim($_GET['edit']) : '';
$editRow  = null;
if ($editKode !== '') {
  $st = $pdo->prepare("SELECT * FROM suppliers WHERE kode=?");
  $st->execute([$editKode]);
  $editRow = $st->fetch(PDO::FETCH_ASSOC);
  if (!$editRow) $err = "Data untuk kode '".htmlspecialchars($editKode)."' tidak ditemukan.";
}

// ===== SIMPAN / UPDATE (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $original_kode = trim($_POST['original_kode'] ?? ''); // untuk edit
  $kode   = trim($_POST['kode'] ?? '');
  $nama   = trim($_POST['nama'] ?? '');
  $alamat = trim($_POST['alamat'] ?? '');
  $tlp    = trim($_POST['tlp'] ?? '');

  if ($kode === '' || $nama === '') {
    $err = 'Kode dan Nama wajib diisi.';
  } else {
    try {
      // ============================
      // VALIDASI: KODE TIDAK BOLEH DUPLIKAT
      // ============================
      if ($original_kode === '') {
        // MODE TAMBAH: kalau kode sudah ada -> TOLAK
        $ck = $pdo->prepare("SELECT 1 FROM suppliers WHERE kode=? LIMIT 1");
        $ck->execute([$kode]);
        if ($ck->fetchColumn()) {
          $err = 'Kode supplier sudah ada. Gunakan kode lain.';
        }
      } else {
        // MODE EDIT: kalau ganti kode, pastikan kode baru belum dipakai supplier lain
        if ($kode !== $original_kode) {
          $ck = $pdo->prepare("SELECT 1 FROM suppliers WHERE kode=? LIMIT 1");
          $ck->execute([$kode]);
          if ($ck->fetchColumn()) {
            $err = 'Kode supplier sudah dipakai supplier lain. Gunakan kode lain.';
          }
        }
      }

      if ($err === '') {
        if ($original_kode !== '') {
          // UPDATE
          if ($kode !== $original_kode) {
            // ganti primary key
            $sql = "UPDATE suppliers SET kode=?, nama=?, alamat=?, tlp=? ".
                   ($hasUpdated ? ", updated_at=NOW() " : " ").
                   "WHERE kode=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$kode,$nama,$alamat,$tlp,$original_kode]);
          } else {
            // update biasa (kode tetap)
            $sql = "UPDATE suppliers SET nama=?, alamat=?, tlp=? ".
                   ($hasUpdated ? ", updated_at=NOW() " : " ").
                   "WHERE kode=?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nama,$alamat,$tlp,$kode]);
          }
        } else {
          // INSERT (TANPA UPSERT)
          if ($hasCreated || $hasUpdated) {
            $sql = "INSERT INTO suppliers (kode, nama, alamat, tlp".
                   ($hasCreated ? ", created_at" : "").
                   ($hasUpdated ? ", updated_at" : "").
                   ") VALUES (?, ?, ?, ?".
                   ($hasCreated ? ", NOW()" : "").
                   ($hasUpdated ? ", NOW()" : "").
                   ")";
          } else {
            $sql = "INSERT INTO suppliers (kode, nama, alamat, tlp) VALUES (?, ?, ?, ?)";
          }

          $stmt = $pdo->prepare($sql);
          $stmt->execute([$kode,$nama,$alamat,$tlp]);
        }

        // Redirect PRG agar tidak dobel saat refresh
        header('Location: '.$_SERVER['PHP_SELF'].'?saved=1');
        exit;
      }

    } catch (Throwable $th) {
      $err = 'Gagal menyimpan: '.$th->getMessage();
    }
  }
}

// pesan notifikasi
if (isset($_GET['saved'])) {
  $msg = ($_GET['saved']==='deleted') ? '🗑️ Data supplier dihapus.' : '✅ Data supplier tersimpan.';
}

$order = ' ORDER BY ';
if ($hasUpdated) $order .= 's.updated_at DESC, ';
if ($hasCreated) $order .= 's.created_at DESC, ';
$order .= 's.kode ASC';

// Cek apakah kolom sisa sudah ada di tabel purchases (agar tidak error jika belum update)
$hasSisa = false;
try {
    $stCheck = $pdo->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'purchases' AND COLUMN_NAME = 'sisa'");
    $stCheck->execute();
    $hasSisa = ((int)$stCheck->fetchColumn() > 0);
} catch (Throwable $e) { $hasSisa = false; }

$sql = "SELECT s.*, ";
if ($hasSisa) {
    $sql .= "(SELECT SUM(sisa) FROM purchases WHERE supplier_kode = s.kode AND status_lunas = 0) as total_hutang ";
} else {
    $sql .= "0 as total_hutang ";
}
$sql .= "FROM suppliers s " . $order;

$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

// helper nilai telepon
function telp_val(array $r): string {
  return $r['tlp'] ?? ($r['telp'] ?? ($r['phone'] ?? ''));
}

// =============================
// BARU INCLUDE HEADER DI SINI ✅
// =============================
require_once __DIR__.'/includes/header.php';
?>
<style>
  :root {
    --card-bg: #0f172a;
    --card-bd: #1f2937;
    --text-main: #e2e8f0;
    --text-muted: #94a3b8;
    --input-bg: #0f172a;
    --input-bd: #1f2937;
  }
  [data-theme="light"] {
    --card-bg: #ffffff;
    --card-bd: #cbd5e1;
    --text-main: #0f172a;
    --text-muted: #475569;
    --input-bg: #ffffff;
    --input-bd: #94a3b8;
  }
  .form-card{border:1px solid var(--card-bd);border-radius:12px;padding:1rem;background:var(--card-bg);margin-bottom:1rem;color:var(--text-main)}
  .form-card label{display:block;margin-bottom:.2rem;font-weight:600;color:var(--text-main)}
  .form-card input{background:var(--input-bg) !important;border:1px solid var(--input-bd) !important;color:var(--text-main) !important}
  .form-card small{color:var(--text-muted);font-size:.78rem;display:block;margin-top:.2rem}
  .grid-2{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:.8rem}
  .form-actions{display:flex;gap:.6rem;flex-wrap:wrap;margin-top:.5rem}
  .table-actions{white-space:nowrap;display:flex;gap:.4rem}
</style>

<article>
  <h3>Master Supplier</h3>

  <?php if ($msg): ?>
    <mark style="display:block;margin:.6rem 0;background:#16a34a;color:#fff"><?= htmlspecialchars($msg) ?></mark>
  <?php endif; ?>
  <?php if ($err): ?>
    <mark style="display:block;margin:.6rem 0;background:#fee2e2;color:#b91c1c"><?= htmlspecialchars($err) ?></mark>
  <?php endif; ?>

  <!-- FORM (tambah/edit) -->
  <form method="post" class="form-card" autocomplete="off">
    <input type="hidden" name="original_kode" value="<?= htmlspecialchars($editRow['kode'] ?? '') ?>">
    <div class="grid-2">
      <label>Kode
        <input name="kode" required value="<?= htmlspecialchars($editRow['kode'] ?? '') ?>" placeholder="Mis. SUP-001">
        <small><?= $editRow ? 'Mode edit: mengubah data supplier ini' : 'Kode unik supplier (tidak boleh sama)' ?></small>
      </label>
      <label>Nama
        <input name="nama" required value="<?= htmlspecialchars($editRow['nama'] ?? '') ?>">
      </label>
      <label>Alamat
        <input name="alamat" value="<?= htmlspecialchars($editRow['alamat'] ?? '') ?>">
      </label>
      <label>Telepon
        <input name="tlp" value="<?= htmlspecialchars(telp_val($editRow ?? [])) ?>">
      </label>
    </div>
    <div class="form-actions">
      <button type="submit"><?= $editRow ? 'Update' : 'Simpan' ?></button>
      <?php if ($editRow): ?>
        <a class="secondary" href="suppliers.php">Batal Edit</a>
      <?php endif; ?>
    </div>
  </form>

  <!-- TABEL LIST -->
  <table class="table-small" style="margin-top:.8rem">
    <thead>
      <tr>
        <th>Kode</th>
        <th>Nama</th>
        <th>Alamat</th>
        <th>Telepon</th>
        <th class="right">Total Hutang</th>
        <th class="no-print">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5">Belum ada data supplier.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars($r['kode']) ?></td>
            <td><?= htmlspecialchars($r['nama']) ?></td>
            <td><?= htmlspecialchars($r['alamat'] ?? '') ?></td>
            <td><?= htmlspecialchars(telp_val($r)) ?></td>
            <td class="right" style="color:<?= $r['total_hutang'] > 0 ? '#ef4444' : 'inherit' ?>; font-weight:<?= $r['total_hutang'] > 0 ? 'bold' : 'normal' ?>">
                <?= number_format($r['total_hutang'] ?? 0, 0, ',', '.') ?>
            </td>
            <td class="no-print table-actions">
              <a href="suppliers.php?edit=<?= urlencode($r['kode']) ?>">Edit</a>
              <?php if (($r['total_hutang'] ?? 0) > 0): ?>
                <a href="supplier_debts.php?sup=<?= urlencode($r['kode']) ?>" style="color:#3b82f6">Bayar</a>
              <?php endif; ?>
              <a href="suppliers.php?delete=<?= urlencode($r['kode']) ?>"
                 onclick="return confirm('Hapus supplier ini? Tindakan tidak dapat dibatalkan.');"
                 style="color:#dc2626">Hapus</a>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</article>

<?php include __DIR__.'/includes/footer.php'; ?>
