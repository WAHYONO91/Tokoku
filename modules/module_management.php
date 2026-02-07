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

/**
 * MASTER MENU TOKOAPP
 */
$menuMaster = [
    'DASHBOARD'        => 'Dashboard',
    'INVENTORY'        => 'Master Barang',
    'MEMBER'           => 'Member',
    'REDEEM'           => 'Tukar Poin',
    'SUPPLIER'         => 'Supplier',
    'PURCHASE'         => 'Pembelian',
    'STOCK'            => 'Mutasi Stok',
    'POS_DISPLAY'      => 'POS Display',
    'REPORT_STOCK'     => 'Laporan Stok',
    'REPORT_SALES'     => 'Laporan Penjualan',
    'REPORT_PURCHASE'  => 'Laporan Pembelian',
    'CASH_IN'          => 'Penerimaan Kas',
    'CASH_OUT'         => 'Pengeluaran Kas',
    'CASHIER'          => 'Kas Kasir',
    'PIUTANG'          => 'Piutang Member',
    'RETURNS'          => 'Retur Barang',

    // 🔽 MODUL BARU
    'TAGIHAN_MEMBER'   => 'Tagihan Member',
    'TAGIHAN_SUPPLIER' => 'Tagihan Supplier',

    'SETTINGS'         => 'Pengaturan',
    'USERS'            => 'Users',
    'AUDIT_TRAIL'      => 'Audit Trail',
    'BACKUP'           => 'Backup Database',
    'MODULE_MGMT'      => 'Manajemen Modul',
];


/**
 * AUTO SYNC MODULE
 */
$check  = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE module_code=:c");
$insert = $pdo->prepare(
    "INSERT INTO modules (module_code, module_name, is_active)
     VALUES (:c, :n, 1)"
);

foreach ($menuMaster as $code => $name) {
    $check->execute(['c'=>$code]);
    if ($check->fetchColumn() == 0) {
        $insert->execute(['c'=>$code,'n'=>$name]);
    }
}

/**
 * FETCH MODULES
 */
$modules = $pdo
    ->query("SELECT * FROM modules ORDER BY module_name")
    ->fetchAll();
?>
<!DOCTYPE html>
<html lang="id" data-theme="dark">
<head>
<meta charset="UTF-8">
<title>Manajemen Modul</title>

<link rel="stylesheet" href="../assets/vendor/pico/pico.min.css">

<style>
body { background:#0f172a; color:#e5e7eb; }
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
