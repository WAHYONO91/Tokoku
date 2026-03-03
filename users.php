<?php
require_once __DIR__ . '/config.php';
require_access('USERS');
require_once __DIR__ . '/includes/header.php';

// Pastikan daftar modul di database up-to-date dengan MASTER
sync_modules($pdo);

$msg  = '';
$err  = '';
$meId = $_SESSION['user']['id'] ?? 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $act = $_POST['act'] ?? '';

    // Tambah user baru
    if ($act === 'add') {
        $u    = trim($_POST['username'] ?? '');
        $p    = $_POST['password'] ?? '';
        $role = $_POST['role'] ?? 'kasir';

        if ($u === '' || $p === '') {
            $err = 'Username dan password wajib diisi.';
        } else {
            $hash = password_hash($p, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO users (username, password_hash, role, is_active)
                    VALUES (?,?,?,1)
                ");
                $stmt->execute([$u, $hash, $role]);
                $msg = 'User baru berhasil ditambahkan.';
            } catch (Throwable $e) {
                $err = 'Gagal menambah user: ' . $e->getMessage();
            }
        }

    // Reset password
    } elseif ($act === 'reset') {
        $id = (int)($_POST['id'] ?? 0);
        $p  = $_POST['password'] ?? '';

        if ($id <= 0 || $p === '') {
            $err = 'ID user dan password baru wajib diisi.';
        } else {
            $hash = password_hash($p, PASSWORD_BCRYPT);
            try {
                $stmt = $pdo->prepare("UPDATE users SET password_hash=? WHERE id=?");
                $stmt->execute([$hash, $id]);
                $msg = 'Password user berhasil direset.';
            } catch (Throwable $e) {
                $err = 'Gagal reset password: ' . $e->getMessage();
            }
        }

    // Aktif / nonaktif
    } elseif ($act === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            $err = 'ID user tidak valid.';
        } elseif ($id === $meId) {
            $err = 'Tidak bisa menonaktifkan akun yang sedang dipakai.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET is_active = IF(is_active=1,0,1) WHERE id=?");
                $stmt->execute([$id]);
                $msg = 'Status user berhasil diubah.';
            } catch (Throwable $e) {
                $err = 'Gagal mengubah status user: ' . $e->getMessage();
            }
        }

    // Edit username & role
    } elseif ($act === 'edit') {
        $id    = (int)($_POST['id'] ?? 0);
        $u     = trim($_POST['username'] ?? '');
        $role  = $_POST['role'] ?? 'kasir';
        $perms = isset($_POST['permissions']) ? json_encode($_POST['permissions']) : '[]';

        if ($id <= 0 || $u === '') {
            $err = 'ID user dan username wajib diisi.';
        } else {
            try {
                $stmt = $pdo->prepare("UPDATE users SET username=?, role=?, permissions=? WHERE id=?");
                $stmt->execute([$u, $role, $perms, $id]);
                $msg = 'Data user berhasil diperbarui.';
            } catch (Throwable $e) {
                $err = 'Gagal mengedit user: ' . $e->getMessage();
            }
        }

    // Hapus user
    } elseif ($act === 'delete') {
        $id = (int)($_POST['id'] ?? 0);

        if ($id <= 0) {
            $err = 'ID user tidak valid.';
        } elseif ($id === $meId) {
            $err = 'Tidak bisa menghapus user yang sedang login.';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id=?");
                $stmt->execute([$id]);
                if ($stmt->rowCount() > 0) {
                    $msg = 'User berhasil dihapus.';
                } else {
                    $err = 'User tidak ditemukan atau sudah dihapus.';
                }
            } catch (Throwable $e) {
                $err = 'Gagal menghapus user: ' . $e->getMessage();
            }
        }
    }
}

$rows = $pdo->query("
    SELECT id, username, role, permissions, is_active, created_at
    FROM users
    ORDER BY id DESC
")->fetchAll();

// Ambil daftar modul untuk checkbox
$allModules = $pdo->query("SELECT module_code, module_name FROM modules ORDER BY module_name ASC")->fetchAll();
?>

<style>
/* ===== CSS Variables Users ===== */
:root {
  --usr-card-bg:   #020617;
  --usr-card-bd:   #1f2937;
  --usr-muted:     #94a3b8;
  --usr-btn-bg:    #0b1220;
  --usr-btn-color: #e5e7eb;
  --usr-btn-bd:    #1f2937;
  --usr-det-bg:    #020617;
  --usr-det-open:  #0b162b;
  --usr-perm-bd:   #374151;
  --usr-badge-bg:  rgba(15,118,110,.25);
  --usr-badge-bd:  rgba(45,212,191,.6);
  --usr-badge-color: #a5f3fc;
}
[data-theme="light"] {
  --usr-card-bg:   #f8fafc;
  --usr-card-bd:   #cbd5e1;
  --usr-muted:     #475569;
  --usr-btn-bg:    #e2e8f0;
  --usr-btn-color: #1e293b;
  --usr-btn-bd:    #94a3b8;
  --usr-det-bg:    #f1f5f9;
  --usr-det-open:  #e0f2fe;
  --usr-perm-bd:   #94a3b8;
  --usr-badge-bg:  rgba(2,132,199,.12);
  --usr-badge-bd:  rgba(2,132,199,.5);
  --usr-badge-color: #0369a1;
}

.user-page h3 {
  margin-bottom: .3rem;
}
.user-page .subtext {
  font-size: .8rem;
  color: var(--usr-muted);
  margin-bottom: .7rem;
}
.user-page .banner {
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:.5rem;
  margin-bottom:.6rem;
}
.user-page .badge-total {
  font-size:.75rem;
  padding:.2rem .5rem;
  border-radius:999px;
  background: var(--usr-badge-bg);
  border:1px solid var(--usr-badge-bd);
  color: var(--usr-badge-color);
}
.user-page .alert-ok,
.user-page .alert-err {
  display:block;
  margin:.4rem 0;
  padding:.4rem .6rem;
  border-radius:.45rem;
  font-size:.8rem;
}
.user-page .alert-ok {
  background:#16a34a;
  color:#ecfdf5;
}
.user-page .alert-err {
  background:#fee2e2;
  color:#991b1b;
}

.user-add-card {
  border-radius:.7rem;
  border:1px solid var(--usr-card-bd);
  padding:.75rem .8rem;
  background: var(--usr-card-bg);
  margin-bottom:.8rem;
}
.user-add-card summary {
  cursor:pointer;
  font-size:.86rem;
}
.user-add-card summary::marker {
  color:#38bdf8;
}
.user-add-card form.grid {
  margin-top:.6rem;
  max-width:420px;
}
.user-add-card label {
  margin-bottom:.4rem;
}

/* Override input dalam tema cerah */
[data-theme="light"] .user-add-card input,
[data-theme="light"] .user-add-card select,
[data-theme="light"] .user-inline-details input,
[data-theme="light"] .user-inline-details select {
  background-color: #ffffff !important;
  color: #0f172a !important;
  border-color: #94a3b8 !important;
}

.user-table-actions {
  display:flex;
  flex-wrap:wrap;
  gap:.25rem;
}
.user-table-actions form {
  display:inline;
}
.user-btn {
  font-size:.72rem;
  padding:.25rem .5rem;
  border-radius:.35rem;
  border:1px solid var(--usr-btn-bd);
  background: var(--usr-btn-bg);
  color: var(--usr-btn-color);
  cursor:pointer;
}
.user-btn.danger {
  background:#b91c1c;
  border-color:#fca5a5;
  color:#fff;
}
.user-btn.soft {
  background: var(--usr-det-bg);
}
.user-badge-role {
  font-size:.7rem;
  padding:.08rem .35rem;
  border-radius:999px;
  border:1px solid rgba(148,163,184,.5);
}
.user-badge-role.admin {
  border-color:rgba(249,115,22,.8);
  color:#fed7aa;
}
.user-badge-role.kasir {
  border-color:rgba(59,130,246,.7);
  color:#bfdbfe;
}
[data-theme="light"] .user-badge-role.admin {
  border-color:rgba(234,88,12,.8);
  color:#9a3412;
}
[data-theme="light"] .user-badge-role.kasir {
  border-color:rgba(37,99,235,.7);
  color:#1e3a8a;
}
.user-badge-status {
  font-size:.7rem;
  padding:.08rem .35rem;
  border-radius:999px;
}
.user-badge-status.on {
  background:rgba(22,163,74,.2);
  color:#bbf7d0;
}
.user-badge-status.off {
  background:rgba(239,68,68,.12);
  color:#fecaca;
}
[data-theme="light"] .user-badge-status.on {
  background:rgba(22,163,74,.15);
  color:#15803d;
}
[data-theme="light"] .user-badge-status.off {
  background:rgba(239,68,68,.1);
  color:#b91c1c;
}
.user-inline-details {
  display:inline-block;
  margin-left:.18rem;
}
.user-inline-details summary {
  cursor:pointer;
  display:inline-block;
  font-size:.7rem;
  padding:.18rem .4rem;
  border-radius:.35rem;
  border:1px solid var(--usr-card-bd);
  background: var(--usr-det-bg);
}
.user-inline-details[open] summary {
  background: var(--usr-det-open);
}
.user-inline-details form.grid {
  margin-top:.4rem;
  min-width:260px;
  font-size:.78rem;
}
.user-inline-details label {
  margin-bottom:.25rem;
}
.user-inline-details input,
.user-inline-details select {
  font-size:.8rem;
  padding:.25rem .35rem;
}
.user-inline-details button {
  font-size:.78rem;
  padding:.28rem .45rem;
}
.perm-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
  gap: 0.3rem;
  margin: 0.5rem 0;
  border: 1px solid var(--usr-perm-bd);
  padding: 0.5rem;
  border-radius: 0.4rem;
  max-height: 200px;
  overflow-y: auto;
}
.perm-item {
  font-size: 0.7rem;
  display: flex !important;
  align-items: center;
  gap: 0.3rem;
  margin-bottom: 0 !important;
}
.perm-item input {
  margin-bottom: 0 !important;
  width: auto !important;
}
</style>

<article class="user-page">
  <div class="banner">
    <div>
      <h3>Manajemen Users</h3>
      <div class="subtext">Atur akun admin & kasir yang bisa mengakses aplikasi PelangiMart.</div>
    </div>
    <div class="badge-total">
      Total user: <?= count($rows) ?>
    </div>
  </div>

  <?php if ($msg): ?>
    <mark class="alert-ok"><?= htmlspecialchars($msg) ?></mark>
  <?php endif; ?>
  <?php if ($err): ?>
    <mark class="alert-err"><?= htmlspecialchars($err) ?></mark>
  <?php endif; ?>

  <!-- Kartu tambah user -->
  <div class="user-add-card">
    <details>
      <summary><strong>➕ Tambah User Baru</strong></summary>
      <form method="post" class="grid">
        <input type="hidden" name="act" value="add">
        <label>Username
          <input name="username" required>
        </label>
        <label>Password
          <input name="password" type="password" required>
        </label>
        <label>Role
          <select name="role">
            <option value="kasir">Kasir</option>
            <option value="admin">Admin</option>
          </select>
        </label>
        <button type="submit">Simpan User Baru</button>
      </form>
    </details>
  </div>

  <table class="table-small">
    <thead>
      <tr>
        <th style="width:50px;">ID</th>
        <th>Username</th>
        <th style="width:90px;">Role</th>
        <th style="width:90px;">Status</th>
        <th style="width:150px;">Dibuat</th>
        <th class="no-print" style="width:260px;">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="6">Belum ada data user.</td></tr>
      <?php else: ?>
        <?php foreach ($rows as $r): ?>
          <tr>
            <td><?= (int)$r['id'] ?></td>
            <td><?= htmlspecialchars($r['username']) ?></td>
            <td>
              <span class="user-badge-role <?= $r['role']==='admin' ? 'admin' : 'kasir' ?>">
                <?= htmlspecialchars(ucfirst($r['role'])) ?>
              </span>
            </td>
            <td>
              <?php if ($r['is_active']): ?>
                <span class="user-badge-status on">Aktif</span>
              <?php else: ?>
                <span class="user-badge-status off">Nonaktif</span>
              <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($r['created_at']) ?></td>
            <td class="no-print">
              <div class="user-table-actions">

                <!-- Toggle aktif/nonaktif -->
                <form method="post">
                  <input type="hidden" name="act" value="toggle">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <button type="submit" class="user-btn soft">
                    <?= $r['is_active'] ? 'Nonaktifkan' : 'Aktifkan' ?>
                  </button>
                </form>

                <!-- Edit -->
                <details class="user-inline-details">
                  <summary>Edit</summary>
                  <form method="post" class="grid">
                    <input type="hidden" name="act" value="edit">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <label>Username
                      <input name="username"
                             value="<?= htmlspecialchars($r['username']) ?>"
                             required>
                    </label>
                    <label>Role
                      <select name="role">
                        <option value="kasir" <?= $r['role']==='kasir'?'selected':''; ?>>Kasir</option>
                        <option value="admin" <?= $r['role']==='admin'?'selected':''; ?>>Admin</option>
                      </select>
                    </label>
                    <label style="font-size:0.75rem; font-weight:bold; margin-top:0.5rem;">Hak Akses Modul (Kasir Only):</label>
                    <div class="perm-grid">
                      <?php 
                        $userPerms = json_decode($r['permissions'] ?? '[]', true);
                        foreach ($allModules as $m): 
                      ?>
                        <label class="perm-item">
                          <input type="checkbox" name="permissions[]" value="<?= htmlspecialchars($m['module_code']) ?>"
                                 <?= in_array($m['module_code'], $userPerms) ? 'checked' : '' ?>>
                          <?= htmlspecialchars($m['module_name']) ?>
                        </label>
                      <?php endforeach; ?>
                    </div>
                    <button type="submit" class="user-btn">Simpan Perubahan</button>
                  </form>
                </details>

                <!-- Reset password -->
                <details class="user-inline-details">
                  <summary>Reset PW</summary>
                  <form method="post" class="grid">
                    <input type="hidden" name="act" value="reset">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <label>Password Baru
                      <input name="password" type="password" required>
                    </label>
                    <button type="submit" class="user-btn">Set Password</button>
                  </form>
                </details>

                <!-- Hapus -->
                <?php if ((int)$r['id'] !== $meId): ?>
                  <form method="post"
                        onsubmit="return confirm('Hapus user <?= htmlspecialchars($r['username']) ?>? Tindakan tidak bisa dibatalkan.');">
                    <input type="hidden" name="act" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                    <button type="submit" class="user-btn danger">
                      Hapus
                    </button>
                  </form>
                <?php endif; ?>

              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</article>

<?php include __DIR__ . '/includes/footer.php'; ?>
