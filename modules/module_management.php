<?php
require_once __DIR__ . '/../config.php';

// ===== AUTH & FAILSAFE =====
if (!isset($_SESSION['user'])) {
    die('Akses ditolak');
}
if (!module_active('MODULE_MGMT') && ($_SESSION['user']['role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Akses Manajemen Modul dinonaktifkan');
}

if (!isset($pdo)) {
    die('Koneksi database tidak ditemukan');
}

// ambil setelan tema
try {
    $header_setting = $pdo->query("SELECT theme FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    $header_setting = ['theme' => 'dark'];
}
$app_theme = $header_setting['theme'] ?? 'dark';

// Pastikan daftar modul di database up-to-date dengan MASTER (dari module_helper)
sync_modules($pdo);
$menuMaster = $MENU_MASTER; 

/**
 * FETCH MODULES
 */
$modules = $pdo
    ->query("SELECT * FROM modules ORDER BY module_name")
    ->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="<?= htmlspecialchars($app_theme) ?>">
<head>
<meta charset="UTF-8">
<title>Manajemen Modul</title>

<link rel="stylesheet" href="../assets/vendor/pico/pico.min.css">

<style>
:root {
    --bg-page: #0f172a;
    --text-main: #e5e7eb;
}
[data-theme="light"] {
    --bg-page: #f1f5f9;
    --text-main: #0f172a;
}
body { background: var(--bg-page); color: var(--text-main); }
.table-mini { font-size:.72rem; }
.table-mini th, .table-mini td {
    padding:.25rem .4rem;
    white-space:nowrap;
}
.status-on  { color:#22c55e; font-weight:600; }
.status-off { color:#ef4444; text-decoration:line-through; }
.btn-mini   { font-size:.7rem; padding:.2rem .45rem; }
.topbar {
    display:flex;
    justify-content:space-between;
    align-items:center;
    margin-bottom:.6rem;
}
</style>
</head>

<body>

<main class="container">

<div class="topbar">
  <strong>🧩 Manajemen Modul</strong>
  <a href="../index.php" role="button" class="secondary btn-mini">
    ← Menu Utama
  </a>
</div>

<article>

<table role="grid" class="table-mini">
<thead>
<tr>
  <th>Modul</th>
  <th>Status</th>
  <th>Aksi</th>
</tr>
</thead>
<tbody>
<?php foreach ($modules as $m): ?>
<tr>
  <td><?= htmlspecialchars($m['module_name']) ?></td>
  <td>
    <?= $m['is_active']
        ? '<span class="status-on">Aktif</span>'
        : '<span class="status-off">Nonaktif</span>' ?>
  </td>
  <td>
    <a
      href="toggle_module.php?id=<?= (int)$m['id'] ?>"
      role="button"
      class="btn-mini <?= $m['is_active']?'secondary':'' ?>">
      <?= $m['is_active']?'Nonaktifkan':'Aktifkan' ?>
    </a>
  </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>

</article>

</main>

</body>
</html>
