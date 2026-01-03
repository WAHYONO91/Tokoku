<?php  
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../config.php';

$logged_in = isset($_SESSION['user']);
$role      = $logged_in ? ($_SESSION['user']['role'] ?? null) : null;
?>
<!doctype html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>PelangiMart</title>

  <!-- favicon / icon tab -->
  <link rel="icon" type="image/png" href="uploads/logo.jpg">

  <!-- PWA: manifest + theme color (PAKAI PATH RELATIF) -->
  <link rel="manifest" href="manifest.webmanifest">
  <meta name="theme-color" content="#0f172a">

  <!-- PWA: iOS (opsional) -->
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <link rel="apple-touch-icon" href="uploads/logo-192.png">

  <link rel="stylesheet" href="/tokoapp/assets/vendor/pico/pico.min.css">
  <style>
    :root {
      --pico-background-color: #0f172a;
      --pico-card-background-color: #111827;
      --pico-font-size: 18px;
    }
    html { font-size: 18px; }
    body {
      background:#0f172a;
      color:#e2e8f0;
      line-height:1.4;
    }
    nav.topbar{
      background:#020617;
      border-bottom:1px solid #1f2937;
      padding:.35rem .9rem;
      display:flex;
      justify-content:space-between;
      align-items:center;
      gap:.5rem;
      min-height:42px;
    }
    .brand{
      font-weight:600;
      font-size:.9rem;
      letter-spacing:.02em;
    }
    .top-right{
      display:flex;
      align-items:center;
      gap:.5rem;
      font-size:.7rem;
      color:#94a3b8;
    }
    .clock-badge{
      background:rgba(15,23,42,.35);
      border:1px solid rgba(148,163,184,.12);
      border-radius:.4rem;
      padding:.15rem .5rem;
      display:flex;
      gap:.35rem;
      align-items:center;
    }
    .menu-wrap{
      display:flex;
      gap:.4rem;
      flex-wrap:wrap;
      margin:.55rem .75rem .5rem;
    }
    .menu-card{
      background:#111827;
      border:1px solid #1f2937;
      border-radius:.6rem;
      padding:.35rem .5rem .35rem .4rem;
      display:flex;
      gap:.35rem;
      align-items:center;
      color:#e2e8f0;
      text-decoration:none;
      transition:all .12s ease-out;
      font-size:.90rem;
    }
    .menu-card:hover{
      background:#1f2937;
      transform:translateY(-1px);
    }
    .menu-icon{
      width:1.05rem;
      text-align:center;
      font-size:.8rem;
    }
    main.container{
      max-width:1180px;
      margin:0 auto;
      padding:.4rem .75rem 1.1rem;
    }
    table{
      width:100%;
      border-collapse:collapse;
      font-size:.72rem;
    }
    .table-small th,.table-small td{
      border:1px solid #1f2937;
      padding:.35rem .4rem;
    }
    .right{text-align:right;}
    article{padding:.55rem .35rem;}
    h3{font-size:1rem;margin-bottom:.45rem;}
    @media (max-width: 780px){
      .menu-wrap{gap:.3rem;}
      .menu-card{font-size:.68rem;}
      nav.topbar{flex-wrap:wrap;justify-content:flex-start;}
    }
    @media print{
      nav.topbar,.menu-wrap,.no-print{display:none!important;}
      body{background:#fff;color:#000;font-size:12px;}
      main.container{max-width:none;padding:0;}
    }
  </style>
</head>
<body>
<nav class="topbar">
  <div class="brand">Pelangi | Mart (Belanja Mudah Harga Bersahabat )</div>
  <div class="top-right">
    <span id="dateNow"></span>
    <span class="clock-badge">
      🕒 <span id="clockNow">--:--:--</span>
    </span>
    <?php if($logged_in): ?>
      <span><?= htmlspecialchars($_SESSION['user']['username'] ?? '') ?> (<?= htmlspecialchars($role ?? '-') ?>)</span>
      <a href="auth/logout.php" class="secondary" style="font-size:.68rem;padding:.1rem .4rem;">Logout</a>
    <?php endif; ?>
  </div>
</nav>

<?php if($logged_in): ?>
<div class="menu-wrap">
  <?php if ($role === 'admin'): ?>
    <a class="menu-card" href="index.php">
      <span class="menu-icon">📊</span><span>Dashboard</span>
    </a>
    <a class="menu-card" href="items.php">
      <span class="menu-icon">📦</span><span>Master Barang</span>
    </a>
    <a class="menu-card" href="members.php">
      <span class="menu-icon">🧑‍💳</span><span>Member</span>
    </a>
    <a class="menu-card" href="member_redeem_list.php">
      <span class="menu-icon">🎁</span><span>Tukar Poin</span>
    </a>
    <a class="menu-card" href="suppliers.php">
      <span class="menu-icon">🚚</span><span>Supplier</span>
    </a>
    <a class="menu-card" href="purchases.php">
      <span class="menu-icon">🧾</span><span>Pembelian</span>
    </a>
    <a class="menu-card" href="stock_transfer.php">
      <span class="menu-icon">🔁</span><span>Mutasi</span>
    </a>
    <!--a class="menu-card" href="pos.php">
      <span class="menu-icon">🛒</span><span>POS</span>
    </a-->
    <a class="menu-card" href="pos_display.php" target="_blank" rel="noopener">
      <span class="menu-icon">🖥️</span><span>POS Display</span>
    </a>
    <a class="menu-card" href="stock_report.php">
      <span class="menu-icon">📋</span><span>Stok</span>
    </a>
    <a class="menu-card" href="purchases_report.php">
      <span class="menu-icon">📑</span><span>Lap. Pembelian</span>
    </a>
    <a class="menu-card" href="sales_report.php">
      <span class="menu-icon">📈</span><span>Lap. Penjualan</span>
    </a>
    <a class="menu-card" href="cash_in.php">
      <span class="menu-icon">➕</span><span>Penerimaan Kas</span>
    </a>
    <a class="menu-card" href="cash_out.php">
      <span class="menu-icon">➖</span><span>Pengeluaran Kas</span>
    </a>
    <a class="menu-card" href="cashier_cash.php">
      <span class="menu-icon">💵</span><span>Kas Kasir</span>
    </a>
    <a class="menu-card" href="settings.php">
      <span class="menu-icon">⚙️</span><span>Pengaturan</span>
    </a>
    <a class="menu-card" href="users.php">
      <span class="menu-icon">👥</span><span>Users</span>
    </a>
    <a class="menu-card" href="backup_tokoapp.php" target="_blank" rel="noopener">
      <span class="menu-icon">💾</span><span>Backup DB</span>
    </a>
  <?php elseif ($role === 'kasir'): ?>
    <a class="menu-card" href="index.php">
      <span class="menu-icon">📊</span><span>Dashboard</span>
    </a>
    <a class="menu-card" href="pos_display.php" target="_blank" rel="noopener">
      <span class="menu-icon">🖥️</span><span>POS Display</span>
    </a>
    <a class="menu-card" href="cash_in.php">
      <span class="menu-icon">➕</span><span>Penerimaan Kas</span>
    </a>
    <a class="menu-card" href="cash_out.php">
      <span class="menu-icon">➖</span><span>Pengeluaran Kas</span>
    </a>
    <a class="menu-card" href="items.php">
      <span class="menu-icon">📦</span><span>Master Barang</span>
    </a>
    <a class="menu-card" href="purchases.php">
      <span class="menu-icon">🧾</span><span>Pembelian</span>
    </a>
    <a class="menu-card" href="stock_transfer.php">
      <span class="menu-icon">🔁</span><span>Mutasi</span>
    </a>
    <a class="menu-card" href="auth/logout.php">
      <span class="menu-icon">🚪</span><span>Logout</span>
    </a>
  <?php endif; ?>
</div>
<?php endif; ?>

<main class="container">
<script>
(function(){
  const clockEl = document.getElementById('clockNow');
  const dateEl  = document.getElementById('dateNow');
  function pad(n){ return n<10 ? '0'+n : n; }
  function tick(){
    const now = new Date();
    const h = pad(now.getHours());
    const m = pad(now.getMinutes());
    const s = pad(now.getSeconds());
    if (clockEl) clockEl.textContent = h+':'+m+':'+s;
    if (dateEl) {
      const opts = { weekday:'long', year:'numeric', month:'long', day:'numeric' };
      dateEl.textContent = now.toLocaleDateString('id-ID', opts);
    }
  }
  tick();
  setInterval(tick, 1000);
})();

// ====== PWA: REGISTER SERVICE WORKER (PATH RELATIF) ======
if ('serviceWorker' in navigator) {
  window.addEventListener('load', function() {
    navigator.serviceWorker
      .register('service-worker.js')
      .catch(function(err){
        console.error('SW registration failed:', err);
      });
  });
}
</script>
