<?php
// ===== Session =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== Config (PDO + helpers) =====
require_once __DIR__ . '/../config.php';

// ===== Auth =====
$logged_in = isset($_SESSION['user']);
$role      = $logged_in ? ($_SESSION['user']['role'] ?? null) : null;
// ===== Fetch Store Settings for Header =====
try {
    $header_setting = $pdo->query("SELECT store_name, theme FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (PDOException $e) {
    // Fallsback if table/columns don't exist yet
    $header_setting = ['store_name' => 'TokoAPP', 'theme' => 'dark'];
}
$store_name = $header_setting['store_name'] ?? 'TokoAPP';
$app_theme = $header_setting['theme'] ?? 'dark';
?>
<!doctype html>
<html lang="id" data-theme="<?= htmlspecialchars($app_theme) ?>">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($store_name) ?></title>

  <link rel="icon" type="image/png" href="uploads/logo.jpg">

  <!-- PWA -->
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="#0f172a">

  <!-- Pico CSS -->
  <link rel="stylesheet" href="/tokoapp/assets/vendor/pico/pico.min.css">

  <style>
    :root {
      --pico-font-size:18px;
    }
    <?php if ($app_theme === 'dark'): ?>
    :root {
      --pico-background-color:#0f172a;
      --pico-card-background-color:#111827;
    }
    body{background:#0f172a;color:#e2e8f0;line-height:1.4}
    nav.topbar{background:#020617;border-bottom:1px solid #1f2937;}
    <?php else: ?>
    body{line-height:1.4}
    nav.topbar{background:#f8fafc;border-bottom:1px solid #e2e8f0; color: #1e293b}
    .top-right{color:#64748b !important}
    <?php endif; ?>

    html{font-size:18px}
    nav.topbar{
      padding:.35rem .9rem;display:flex;justify-content:space-between;align-items:center
    }
    .brand{font-weight:600;font-size:.9rem}
    .top-right{display:flex;gap:.5rem;font-size:.7rem;color:#94a3b8;align-items:center}
    .clock-badge{
      background:rgba(15,23,42,.35);border:1px solid rgba(148,163,184,.12);
      border-radius:.4rem;padding:.15rem .5rem;display:flex;gap:.35rem
    }
    .menu-wrap{display:flex;gap:.4rem;flex-wrap:wrap;margin:.55rem .75rem .5rem}
    .menu-card{
      background:#111827;border:1px solid #1f2937;border-radius:.6rem;
      padding:.35rem .5rem .35rem .4rem;display:flex;gap:.35rem;
      align-items:center;color:#e2e8f0;text-decoration:none;
      transition:all .12s ease-out;font-size:.9rem
    }
    .menu-card:hover{background:#1f2937;transform:translateY(-1px)}
    .menu-icon{width:1.05rem;text-align:center;font-size:.8rem}

    /* ===== RESPONSIVE ===== */
    @media (max-width: 600px) {
      nav.topbar {
        flex-direction: column;
        align-items: flex-start;
        padding: .5rem .75rem;
        gap: .3rem;
      }
      .top-right {
        flex-wrap: wrap;
        width: 100%;
        justify-content: space-between;
      }
      .menu-wrap {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: .4rem;
        margin: .5rem .5rem 1rem;
      }
      .menu-card {
        flex-direction: column;
        text-align: center;
        padding: .5rem .25rem;
        font-size: .75rem;
        gap: .2rem;
      }
      .menu-icon {
        font-size: 1.1rem;
        margin-bottom: 2px;
      }
      article {
        padding: 1rem .75rem;
      }
      /* Global helper: make tables scrollable on mobile */
      table {
        display: block;
        width: 100%;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
      }
    }

    @media (max-width: 400px) {
      .menu-wrap {
        grid-template-columns: repeat(2, 1fr);
      }
    }
  </style>
</head>

<body>

<!-- ===== TOP BAR ===== -->
<nav class="topbar">
  <div class="brand"><?= htmlspecialchars($store_name) ?></div>
  <div class="top-right">
    <span id="dateNow"></span>
    <span class="clock-badge">🕒 <span id="clockNow">--:--:--</span></span>
    <?php if($logged_in): ?>
      <span><?= htmlspecialchars($_SESSION['user']['username'] ?? '') ?> (<?= htmlspecialchars($role ?? '-') ?>)</span>
      <a href="auth/logout.php" role="button" class="secondary"
         style="font-size:.65rem;padding:.1rem .4rem;">Logout</a>
    <?php endif; ?>
  </div>
</nav>

<!-- ===== MENU ===== -->
<?php if($logged_in): ?>
<div class="menu-wrap">

<?php if ($role === 'admin'): ?>

  <?php if (module_active('DASHBOARD')): ?>
  <a class="menu-card" href="index.php"><span class="menu-icon">📊</span>Dashboard</a>
  <?php endif; ?>

  <?php if (module_active('INVENTORY')): ?>
  <a class="menu-card" href="items.php"><span class="menu-icon">📦</span>Master Barang</a>
  <?php endif; ?>

  <?php if (module_active('MEMBER')): ?>
  <a class="menu-card" href="members.php"><span class="menu-icon">🧑‍💳</span>Member</a>
  <?php endif; ?>

  <?php if (module_active('REDEEM')): ?>
  <a class="menu-card" href="member_redeem_list.php"><span class="menu-icon">🎁</span>Tukar Poin</a>
  <?php endif; ?>

  <?php if (module_active('SUPPLIER')): ?>
  <a class="menu-card" href="suppliers.php"><span class="menu-icon">🚚</span>Supplier</a>
  <?php endif; ?>

  <?php if (module_active('PURCHASE')): ?>
  <a class="menu-card" href="purchases.php"><span class="menu-icon">🧾</span>Pembelian</a>
  <?php endif; ?>

  <?php if (module_active('STOCK')): ?>
  <a class="menu-card" href="stock_transfer.php"><span class="menu-icon">🔁</span>Mutasi</a>
  <?php endif; ?>

  <?php if (module_active('POS_DISPLAY')): ?>
  <a class="menu-card" href="pos_display.php" target="_blank"><span class="menu-icon">🖥️</span>POS Display</a>
  <?php endif; ?>

  <?php if (module_active('REPORT_STOCK')): ?>
  <a class="menu-card" href="stock_report.php"><span class="menu-icon">📋</span>Lap. Stok</a>
  <?php endif; ?>

  <?php if (module_active('REPORT_SALES')): ?>
  <a class="menu-card" href="sales_report.php"><span class="menu-icon">📈</span>Lap. Penjualan</a>
  <?php endif; ?>

  <?php if (module_active('REPORT_PURCHASE')): ?>
  <a class="menu-card" href="purchases_report.php"><span class="menu-icon">📑</span>Lap. Pembelian</a>
  <?php endif; ?>

  <?php if (module_active('CASH_IN')): ?>
  <a class="menu-card" href="cash_in.php"><span class="menu-icon">➕</span>Penerimaan Kas</a>
  <?php endif; ?>

  <?php if (module_active('CASH_OUT')): ?>
  <a class="menu-card" href="cash_out.php"><span class="menu-icon">➖</span>Pengeluaran Kas</a>
  <?php endif; ?>

  <?php if (module_active('CASHIER')): ?>
  <a class="menu-card" href="cashier_cash.php"><span class="menu-icon">💵</span>Kas Kasir</a>
  <?php endif; ?>

  <?php if (module_active('PIUTANG')): ?>
  <a class="menu-card" href="member_ar_list.php"><span class="menu-icon">🧾</span>Piutang Member</a>
  <?php endif; ?>
  
  <?php if (module_active('TAGIHAN_MEMBER')): ?>
<a class="menu-card" href="member_ar_billing.php">
  <span class="menu-icon">📌</span><span>Tagihan Member</span>
</a>
<?php endif; ?>

<?php if (module_active('TAGIHAN_SUPPLIER')): ?>
<a class="menu-card" href="supplier_debts.php">
  <span class="menu-icon">🚚💸</span><span>Tagihan Supplier</span>
</a>
<?php endif; ?>


  <?php if (module_active('SETTINGS')): ?>
  <a class="menu-card" href="settings.php"><span class="menu-icon">⚙️</span>Pengaturan</a>
  <?php endif; ?>

  <?php if (module_active('MODULE_MGMT')): ?>
<a class="menu-card" href="modules/module_management.php">
  <span class="menu-icon">🧩</span><span>Manajemen Modul</span>
</a>
<?php endif; ?>

<a class="menu-card" href="admin_update.php"><span class="menu-icon">🚀</span>Update Sistem</a>


  <?php if (module_active('USERS')): ?>
  <a class="menu-card" href="users.php"><span class="menu-icon">👥</span>Users</a>
  <?php endif; ?>

  <?php if (module_active('AUDIT_TRAIL')): ?>
  <a class="menu-card" href="audit_trail.php"><span class="menu-icon">📜</span>Audit Trail</a>
  <?php endif; ?>

  <?php if (module_active('BACKUP')): ?>
  <a class="menu-card" href="backup_tokoapp.php" target="_blank"><span class="menu-icon">💾</span>Backup DB</a>
  <?php endif; ?>

<?php elseif ($role === 'kasir'): ?>

  <?php if (module_active('DASHBOARD')): ?>
  <a class="menu-card" href="index.php"><span class="menu-icon">📊</span>Dashboard</a>
  <?php endif; ?>

  <?php if (module_active('POS_DISPLAY')): ?>
  <a class="menu-card" href="pos_display.php" target="_blank"><span class="menu-icon">🖥️</span>POS Display</a>
  <?php endif; ?>

  <?php if (module_active('CASH_IN')): ?>
  <a class="menu-card" href="cash_in.php"><span class="menu-icon">➕</span>Penerimaan Kas</a>
  <?php endif; ?>

  <?php if (module_active('CASH_OUT')): ?>
  <a class="menu-card" href="cash_out.php"><span class="menu-icon">➖</span>Pengeluaran Kas</a>
  <?php endif; ?>

<?php endif; ?>

</div>
<?php endif; ?>

<script>
(function(){
  const c=document.getElementById('clockNow'),d=document.getElementById('dateNow');
  function p(n){return n<10?'0'+n:n}
  function t(){
    const x=new Date();
    if(c) c.textContent=p(x.getHours())+':'+p(x.getMinutes())+':'+p(x.getSeconds());
    if(d) d.textContent=x.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
  }
  t(); setInterval(t,1000);
})();
</script>
