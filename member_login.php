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

// Fetch store settings for branding
$setting = $pdo->query("SELECT store_name, logo_url FROM settings WHERE id = 1")->fetch(PDO::FETCH_ASSOC) ?: [];
$store_name = $setting['store_name'] ?? 'TokoAPP';
$logo_url   = $setting['logo_url']   ?? '';

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate Limiting Setup
$ip_address   = $_SERVER['REMOTE_ADDR'];
$time_window  = 15 * 60; // 15 minutes
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
        $err = 'Token Keamanan tidak valid. Silakan refresh halaman.';
    } else {
        // Rate Limiting Check
        $current_attempts  = $_SESSION['login_attempts'][$ip_address]['count'] ?? 0;
        $last_attempt_time = $_SESSION['login_attempts'][$ip_address]['time'] ?? 0;

        if ($current_attempts >= $max_attempts && (time() - $last_attempt_time) < $time_window) {
            $remaining_minutes = ceil(($time_window - (time() - $last_attempt_time)) / 60);
            $err = "Terlalu banyak percobaan gagal. Silakan coba lagi dalam $remaining_minutes menit.";
        } else {
            $kode     = trim($_POST['kode'] ?? '');
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
                            'time'  => time()
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

$page_title = "Login Member - " . htmlspecialchars($store_name);
require_once __DIR__ . '/includes/shop_header.php';
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&display=swap');

.member-login-container {
    display: flex;
    justify-content: center;
    align-items: center;
    min-height: calc(100vh - 160px);
    padding: 2.5rem 1rem;
}

.member-login-card {
    width: 100%;
    max-width: 440px;
    background: var(--card-bg, #ffffff);
    border: 1px solid var(--card-bd, #e2e8f0);
    border-radius: 24px;
    padding: 2.5rem 2rem;
    box-shadow: 0 20px 45px -10px rgba(0, 0, 0, 0.08);
    position: relative;
    overflow: hidden;
    transition: all 0.3s ease;
}

.member-login-header {
    text-align: center;
    margin-bottom: 2rem;
}

.member-brand-logo {
    max-height: 65px;
    margin-bottom: 1rem;
    object-fit: contain;
}

.member-brand-icon {
    font-size: 3rem;
    margin-bottom: 0.5rem;
    display: inline-block;
}

.member-login-title {
    font-size: 1.6rem;
    font-weight: 800;
    color: var(--text-main, #0f172a);
    margin-bottom: 0.35rem;
    letter-spacing: -0.02em;
}

.member-login-sub {
    font-size: 0.9rem;
    color: var(--text-muted, #64748b);
    line-height: 1.4;
}

/* Alert Styling */
.member-alert {
    background: rgba(239, 68, 68, 0.1);
    border: 1px solid rgba(239, 68, 68, 0.3);
    color: #ef4444;
    padding: 0.85rem 1rem;
    border-radius: 12px;
    font-size: 0.88rem;
    font-weight: 500;
    margin-bottom: 1.5rem;
    display: flex;
    align-items: center;
    gap: 0.6rem;
    animation: fadeInShake 0.4s ease-in-out;
}

@keyframes fadeInShake {
    0% { transform: translateX(0); opacity: 0; }
    20% { transform: translateX(-6px); opacity: 1; }
    40% { transform: translateX(6px); }
    60% { transform: translateX(-3px); }
    80% { transform: translateX(3px); }
    100% { transform: translateX(0); }
}

/* Custom Input Groups */
.input-group {
    margin-bottom: 1.25rem;
    position: relative;
}

.input-label {
    display: block;
    font-size: 0.85rem;
    font-weight: 600;
    color: var(--text-main, #0f172a);
    margin-bottom: 0.4rem;
}

.input-with-icon {
    position: relative;
    display: flex;
    align-items: center;
}

.input-icon {
    position: absolute;
    left: 1rem;
    font-size: 1.1rem;
    color: var(--text-muted, #94a3b8);
    pointer-events: none;
}

.custom-field {
    width: 100%;
    padding: 0.75rem 1rem 0.75rem 2.8rem !important;
    font-size: 0.95rem;
    background: var(--input-bg, #f8fafc) !important;
    border: 1.5px solid var(--card-bd, #cbd5e1) !important;
    border-radius: 12px !important;
    color: var(--text-main, #0f172a) !important;
    transition: all 0.25s ease;
    margin: 0 !important;
}

.custom-field:focus {
    border-color: var(--brand-color, #f97316) !important;
    box-shadow: 0 0 0 4px rgba(249, 115, 22, 0.15) !important;
    background: var(--card-bg, #ffffff) !important;
}

.toggle-password {
    position: absolute;
    right: 0.85rem;
    background: transparent;
    border: none;
    cursor: pointer;
    font-size: 1.1rem;
    color: var(--text-muted, #94a3b8);
    padding: 0.2rem 0.4rem;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 6px;
    transition: color 0.2s ease;
}
.toggle-password:hover {
    color: var(--brand-color, #f97316);
}

.btn-submit-member {
    width: 100%;
    padding: 0.85rem !important;
    font-size: 1rem !important;
    font-weight: 700 !important;
    background: linear-gradient(135deg, var(--brand-color, #f97316), var(--brand-color-hover, #ea580c)) !important;
    color: #ffffff !important;
    border: none !important;
    border-radius: 12px !important;
    cursor: pointer;
    box-shadow: 0 8px 20px -4px rgba(249, 115, 22, 0.4) !important;
    transition: all 0.25s ease;
    margin-top: 0.5rem;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 0.5rem;
}

.btn-submit-member:hover {
    transform: translateY(-2px);
    box-shadow: 0 12px 25px -4px rgba(249, 115, 22, 0.5) !important;
}

.member-features {
    margin-top: 2rem;
    padding-top: 1.5rem;
    border-top: 1px dashed var(--card-bd, #e2e8f0);
}

.feature-title {
    font-size: 0.78rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    color: var(--text-muted, #64748b);
    margin-bottom: 0.85rem;
    text-align: center;
}

.feature-list {
    display: flex;
    flex-direction: column;
    gap: 0.6rem;
}

.feature-item {
    display: flex;
    align-items: center;
    gap: 0.6rem;
    font-size: 0.83rem;
    color: var(--text-main, #334155);
}

.member-footer {
    text-align: center;
    margin-top: 1.5rem;
    font-size: 0.85rem;
    color: var(--text-muted, #64748b);
    line-height: 1.5;
}

.member-footer a {
    color: var(--brand-color, #f97316);
    font-weight: 700;
    text-decoration: none;
}
.member-footer a:hover {
    text-decoration: underline;
}

@media (max-width: 480px) {
    .member-login-card {
        padding: 1.75rem 1.25rem;
        border-radius: 18px;
    }
}
</style>

<div class="member-login-container">
    <div class="member-login-card">
        <div class="member-login-header">
            <?php if (!empty($logo_url) && file_exists(__DIR__ . '/' . $logo_url)): ?>
                <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo Toko" class="member-brand-logo">
            <?php else: ?>
                <div class="member-brand-icon">🛍️</div>
            <?php endif; ?>
            
            <div class="member-login-title">Login Member</div>
            <div class="member-login-sub">Masuk untuk menikmati poin belanja &amp; promo menarik di <strong><?= htmlspecialchars($store_name) ?></strong></div>
        </div>

        <?php if ($err): ?>
            <div class="member-alert">
                <span>⚠️</span>
                <div><?= htmlspecialchars($err) ?></div>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="off">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            
            <div class="input-group">
                <label class="input-label" for="memberKode">Kode Member</label>
                <div class="input-with-icon">
                    <span class="input-icon">👤</span>
                    <input type="text" id="memberKode" name="kode" class="custom-field" required placeholder="Masukkan Kode Member Anda" value="<?= htmlspecialchars($_POST['kode'] ?? '') ?>">
                </div>
            </div>
            
            <div class="input-group">
                <label class="input-label" for="memberPassword">Password</label>
                <div class="input-with-icon">
                    <span class="input-icon">🔒</span>
                    <input type="password" id="memberPassword" name="password" class="custom-field" required placeholder="Masukkan Password Anda">
                    <button type="button" class="toggle-password" onclick="togglePasswordVisibility()" title="Tampilkan/Sembunyikan Password">👁️</button>
                </div>
            </div>
            
            <button type="submit" class="btn-submit-member">
                🔑 Masuk Akun Member
            </button>
        </form>

        <div class="member-features">
            <div class="feature-title">Keuntungan Member Toko</div>
            <div class="feature-list">
                <div class="feature-item"><span>🎁</span> <span>Kumpulkan Poin Belanja di setiap transaksi</span></div>
                <div class="feature-item"><span>🎟️</span> <span>Tukarkan Poin dengan Diskon &amp; Hadiah</span></div>
                <div class="feature-item"><span>🚀</span> <span>Proses Checkout Belanja Online Lebih Cepat</span></div>
            </div>
        </div>

        <div class="member-footer">
            Belum punya password atau akun? <br>
            Silakan minta bantuan ke <strong>Kasir Toko</strong> saat bertransaksi.
        </div>
    </div>
</div>

<script>
function togglePasswordVisibility() {
    const input = document.getElementById('memberPassword');
    if (!input) return;
    if (input.type === 'password') {
        input.type = 'text';
    } else {
        input.type = 'password';
    }
}
</script>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>
