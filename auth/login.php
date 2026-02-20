<?php
// auth/login.php
require_once __DIR__ . '/../config.php';

$err = '';

// Ambil data pengaturan toko
$setting = $pdo->query("SELECT store_name, logo_url FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
$store_name = $setting['store_name'] ?? 'TokoAPP';
$app_logo = !empty($setting['logo_url']) ? $setting['logo_url'] : '/tokoapp/uploads/logo.png';

// cari background dinamis dari /uploads
$bgUrl    = '';
$uploadDir = __DIR__ . '/../uploads';
$webBase   = '/tokoapp/uploads/';

if (is_dir($uploadDir)) {
    $imgs = glob($uploadDir . '/*.{jpg,jpeg,png,JPG,JPEG,PNG}', GLOB_BRACE);
    if ($imgs && count($imgs) > 0) {
        $fname = basename($imgs[0]);
        $bgUrl = $webBase . $fname;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $u = trim($_POST['username'] ?? '');
    $p = $_POST['password'] ?? '';

    $st = $pdo->prepare('SELECT * FROM users WHERE username = ? AND is_active = 1');
    $st->execute([$u]);
    $user = $st->fetch();

    if ($user && password_verify($p, $user['password_hash'])) {
        $_SESSION['user'] = [
            'id'       => $user['id'],
            'username' => $user['username'],
            'role'     => $user['role']
        ];
        log_activity($pdo, 'LOGIN', 'User berhasil login ke sistem.');
        // setelah login masuk ke dashboard utama
        header('Location: /tokoapp/index.php');
        exit;
    } else {
        $err = 'Username/password salah atau user nonaktif.';
    }
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($store_name) ?> - Login</title>
  <meta name="viewport" content="width=device-width,initial-scale=1">

  <!-- PWA manifest & icon -->
  <link rel="manifest" href="/tokoapp/manifest.webmanifest">
  <meta name="theme-color" content="#0f172a">
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($app_logo) ?>">

  <link rel="stylesheet" href="/tokoapp/assets/vendor/pico/pico.min.css">
  <style>
    :root {
      --pico-font-size: 15px;
    }
    body {
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: #0f172a;
      <?php if ($bgUrl): ?>
      background-image:
        linear-gradient(120deg, rgba(15,23,42,.78), rgba(15,23,42,.35)),
        url("<?= htmlspecialchars($bgUrl) ?>");
      background-size: cover;
      background-position: center;
      <?php endif; ?>
    }
    .login-wrap {
      width: min(460px, 92vw);
      background: rgba(15, 23, 42, .86);
      border: 1px solid rgba(148, 163, 184, .4);
      border-radius: 1rem;
      padding: 1.2rem 1.4rem 1.1rem;
      box-shadow: 0 12px 36px rgba(0,0,0,.4);
      backdrop-filter: blur(6px);
    }
    h3 {
      margin-bottom: .4rem;
    }
    .sub {
      font-size: .8rem;
      color: #94a3b8;
      margin-bottom: .9rem;
    }
    form.grid label {
      margin-bottom: .5rem;
    }
    input {
      height: 38px;
    }
    button {
      margin-top: .3rem;
    }
    .err {
      background: #b91c1c;
      color: #fff;
      border-radius: .35rem;
      padding: .35rem .6rem;
      margin-bottom: .65rem;
      font-size: .78rem;
    }
    .app-title {
      font-weight: 700;
      font-size: 1.05rem;
    }
    .top-mini {
      font-size: .7rem;
      text-align: center;
      margin-bottom: .4rem;
      color: #cbd5f5;
    }
    .logo-img {
      height: 56px;
      display: block;
      margin: 0 auto .5rem;
      object-fit: contain;
    }
  </style>
</head>
<body>
  <main class="login-wrap">
    <img src="<?= htmlspecialchars($app_logo) ?>" alt="Logo <?= htmlspecialchars($store_name) ?>" class="logo-img">

    <div class="top-mini">Selamat datang di</div>
    <div class="app-title">Aplikasi <?= htmlspecialchars($store_name) ?></div>
    <p class="sub">Silakan login dengan akun yang sudah terdaftar.</p>

    <?php if ($err): ?>
      <div class="err"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <form method="post" class="grid" autocomplete="on">
      <label>Username
        <input name="username" autocomplete="username" required>
      </label>
      <label>Password
        <input name="password" type="password" autocomplete="current-password" required>
      </label>
      <button type="submit">Masuk</button>
    </form>
  </main>

  <script>
    // register service worker untuk PWA
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', () => {
        navigator.serviceWorker
          .register('/tokoapp/service-worker.js')
          .catch(err => console.error('SW register error:', err));
      });
    }
  </script>
</body>
</html>
