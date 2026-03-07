<?php
require_once __DIR__ . '/config.php';

// Enable session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (isset($_SESSION['member'])) {
    header('Location: shop.php');
    exit;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate Limiting Setup
$ip_address = $_SERVER['REMOTE_ADDR'];
$time_window = 15 * 60; // 15 minutes
$max_attempts = 5;

// Clean up old attempts
if (isset($_SESSION['login_attempts'])) {
    foreach ($_SESSION['login_attempts'] as $ip => $data) {
        if (time() - $data['time'] > $time_window) {
            unset($_SESSION['login_attempts'][$ip]);
        }
    }
}

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Check
    $csrf_token = $_POST['csrf_token'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $err = 'Invalid Security Token. Silakan refresh halaman.';
    } else {
        // Rate Limiting Check
        $current_attempts = $_SESSION['login_attempts'][$ip_address]['count'] ?? 0;
        $last_attempt_time = $_SESSION['login_attempts'][$ip_address]['time'] ?? 0;

        if ($current_attempts >= $max_attempts && (time() - $last_attempt_time) < $time_window) {
            $remaining_minutes = ceil(($time_window - (time() - $last_attempt_time)) / 60);
            $err = "Terlalu banyak percobaan gagal. Silakan coba lagi dalam $remaining_minutes menit.";
        } else {
            $kode = trim($_POST['kode'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($kode === '' || $password === '') {
                $err = 'Kode Member dan Password harus diisi.';
            } else {
                $stmt = $pdo->prepare("SELECT * FROM members WHERE kode = ?");
                $stmt->execute([$kode]);
                $member = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($member) {
                    if (!empty($member['password_hash']) && password_verify($password, $member['password_hash'])) {
                        // Success -> Reset attempts
                        unset($_SESSION['login_attempts'][$ip_address]);
                        
                        $_SESSION['member'] = [
                            'kode' => $member['kode'],
                            'nama' => $member['nama']
                        ];
                        // Regenerate token on login to prevent session fixation
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                        header('Location: shop.php');
                        exit;
                    } else if (empty($member['password_hash'])) {
                        $err = 'Akun ini belum memiliki password. Silakan hubungi Kasir/Admin untuk mengaturnya.';
                    } else {
                        $err = 'Kode Member atau Password salah.';
                    }
                } else {
                    $err = 'Kode Member tidak ditemukan.';
                }

                // Failed attempt -> Increment counter
                if ($err !== '') {
                    if (!isset($_SESSION['login_attempts'][$ip_address])) {
                        $_SESSION['login_attempts'][$ip_address] = [
                            'count' => 1,
                            'time' => time()
                        ];
                    } else {
                        $_SESSION['login_attempts'][$ip_address]['count']++;
                        $_SESSION['login_attempts'][$ip_address]['time'] = time();
                    }
                }
            }
        }
    }
}

$page_title = "Login Member - " . ($store_name ?? 'TokoAPP');
require_once __DIR__ . '/includes/shop_header.php';
?>

<div class="login-wrap">
    <h3 style="text-align:center; margin-bottom: 1.5rem;">🔑 Login Member</h3>
    
    <?php if ($err): ?>
        <mark style="display:block;margin-bottom:1rem;background:#dc2626;color:#fff;">⚠️ <?= htmlspecialchars($err) ?></mark>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
        
        <label>Kode Member
            <input type="text" name="kode" required value="<?= htmlspecialchars($_POST['kode'] ?? '') ?>">
        </label>
        
        <label>Password
            <input type="password" name="password" required>
        </label>
        
        <button type="submit" style="width:100%; margin-top: 1rem;">Masuk</button>
    </form>
    
    <div style="text-align:center; margin-top: 1.5rem; font-size: 0.9rem;" class="muted">
        Belum punya akun / password? <br>Silakan daftar atau atur di kasir toko kami.
    </div>
</div>

    </main>
</body>
</html>
