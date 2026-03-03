<?php
// =====================
// AUTH & SESSION HELPER
// =====================

// ===== REMEMBER ME =====
define('REMEMBER_COOKIE_NAME', 'tokoapp_remember');
define('REMEMBER_DAYS',        30);

/**
 * Buat & simpan token remember me ke DB + set cookie
 */
function remember_me_set(PDO $pdo, int $user_id): void {
    // Migrasi tabel kalau belum ada
    try {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS remember_tokens (
                id         INT AUTO_INCREMENT PRIMARY KEY,
                user_id    INT NOT NULL,
                token_hash VARCHAR(64) NOT NULL,
                expires_at DATETIME NOT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_token (token_hash),
                INDEX idx_user  (user_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    } catch (Throwable $e) {}

    // Hapus token lama milik user ini
    try {
        $pdo->prepare("DELETE FROM remember_tokens WHERE user_id = ?")->execute([$user_id]);
    } catch (Throwable $e) {}

    // Buat token baru
    $raw_token  = bin2hex(random_bytes(32));       // 64 karakter hex
    $token_hash = hash('sha256', $raw_token);
    $expires    = date('Y-m-d H:i:s', time() + (REMEMBER_DAYS * 86400));

    try {
        $pdo->prepare("INSERT INTO remember_tokens (user_id, token_hash, expires_at) VALUES (?, ?, ?)")
            ->execute([$user_id, $token_hash, $expires]);
    } catch (Throwable $e) { return; }

    // Set cookie (HttpOnly untuk keamanan)
    setcookie(
        REMEMBER_COOKIE_NAME,
        $raw_token,
        [
            'expires'  => time() + (REMEMBER_DAYS * 86400),
            'path'     => '/tokoapp/',
            'httponly' => true,
            'samesite' => 'Lax',
        ]
    );
}

/**
 * Hapus token remember me dari DB + hapus cookie
 */
function remember_me_clear(PDO $pdo): void {
    $raw_token = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if ($raw_token !== '') {
        $hash = hash('sha256', $raw_token);
        try {
            $pdo->prepare("DELETE FROM remember_tokens WHERE token_hash = ?")->execute([$hash]);
        } catch (Throwable $e) {}
    }
    // Hapus cookie
    setcookie(REMEMBER_COOKIE_NAME, '', [
        'expires'  => time() - 3600,
        'path'     => '/tokoapp/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

/**
 * Cek cookie remember me dan auto-login jika valid.
 * Panggil ini di awal require_login() / require_access() sebelum cek session.
 */
function remember_me_auto_login(PDO $pdo): void {
    if (isset($_SESSION['user'])) return;  // sudah login

    $raw_token = $_COOKIE[REMEMBER_COOKIE_NAME] ?? '';
    if ($raw_token === '') return;

    $hash = hash('sha256', $raw_token);

    try {
        $st = $pdo->prepare("
            SELECT rt.user_id, rt.expires_at, u.username, u.role
            FROM remember_tokens rt
            JOIN users u ON u.id = rt.user_id
            WHERE rt.token_hash = ?
              AND u.is_active = 1
            LIMIT 1
        ");
        $st->execute([$hash]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) { return; }

    if (!$row) { remember_me_clear($pdo); return; }

    // Cek kadaluarsa
    if (strtotime($row['expires_at']) < time()) {
        remember_me_clear($pdo);
        return;
    }

    // Auto-login: set session
    $_SESSION['user'] = [
        'id'       => (int)$row['user_id'],
        'username' => $row['username'],
        'role'     => $row['role'],
    ];

    // Perpanjang token (rolling renewal)
    remember_me_set($pdo, (int)$row['user_id']);
}
// ===== END REMEMBER ME =====

function is_logged_in() {
  return isset($_SESSION['user']);
}

function require_login() {
  global $pdo;
  remember_me_auto_login($pdo);
  if (!is_logged_in()) {
    header('Location: /tokoapp/auth/login.php');
    exit;
  }
}

function require_role($roles = []) {
  if (!is_logged_in()) {
    header('Location: /tokoapp/auth/login.php');
    exit;
  }

  if (!in_array($_SESSION['user']['role'], $roles)) {
    http_response_code(403);
    echo "<h3>Akses ditolak</h3>";
    exit;
  }
}

/**
 * Require specific module access
 */
function require_access(string $moduleCode) {
  global $pdo;
  remember_me_auto_login($pdo);
  if (!is_logged_in()) {
    header('Location: /tokoapp/auth/login.php');
    exit;
  }

  if (!module_active($moduleCode)) {
    http_response_code(403);
    echo "<div style='padding:2rem; text-align:center;'>
            <h3>Akses Ditolak</h3>
            <p>Anda tidak memiliki izin untuk mengakses modul <strong>$moduleCode</strong>.</p>
            <a href='/tokoapp/index.php'>Kembali ke Dashboard</a>
          </div>";
    exit;
  }
}

// =====================
// FORMAT & SETTINGS
// =====================

function rupiah($number) {
  return 'Rp ' . number_format((float)$number, 0, ',', '.');
}

function get_setting(PDO $pdo, int $id = 1) {
  $stmt = $pdo->prepare("SELECT * FROM settings WHERE id=?");
  $stmt->execute([$id]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ============================================================
 * INVENTORY HELPERS (item_stocks & items)
 * ============================================================
 */

function ensure_stock_rows(PDO $pdo, string $item_kode): void {
  $stmt = $pdo->prepare("
    INSERT INTO item_stocks (item_kode, location, qty)
    VALUES (?, 'gudang', 0)
    ON DUPLICATE KEY UPDATE qty = qty
  ");
  $stmt->execute([$item_kode]);

  $stmt = $pdo->prepare("
    INSERT INTO item_stocks (item_kode, location, qty)
    VALUES (?, 'toko', 0)
    ON DUPLICATE KEY UPDATE qty = qty
  ");
  $stmt->execute([$item_kode]);
}

function get_stock(PDO $pdo, string $item_kode, string $location): int {
  $stmt = $pdo->prepare("SELECT qty FROM item_stocks WHERE item_kode=? AND location=?");
  $stmt->execute([$item_kode, $location]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ? (int)$row['qty'] : 0;
}

function adjust_stock(PDO $pdo, string $item_kode, string $location, int $delta_qty): void {
  ensure_stock_rows($pdo, $item_kode);
  $stmt = $pdo->prepare("UPDATE item_stocks SET qty = qty + ? WHERE item_kode=? AND location=?");
  $stmt->execute([(int)$delta_qty, $item_kode, $location]);
}

/**
 * Update harga beli item dari transaksi pembelian.
 * - HANYA mengubah kolom `harga_beli`.
 * - Tidak menyentuh harga_jual1–4 (biar full manual).
 */
function auto_update_prices_from_purchase(PDO $pdo, string $item_kode, int $harga_beli): void {
  if ($harga_beli <= 0) return;

  $stmt = $pdo->prepare("
    UPDATE items
    SET harga_beli = ?
    WHERE kode = ?
  ");
  $stmt->execute([$harga_beli, $item_kode]);
}
/* ============================================================
 * MEMBER HELPERS (for popup picker / search)
 * ============================================================
 */

/**
 * HTML escape helper
 */
if (!function_exists('h')) {
  function h($s): string {
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
  }
}

/**
 * Ambil list member untuk popup cari/pick.
 * - $q: keyword (kode/nama)
 * - $limit: default 500
 */
function get_members(PDO $pdo, string $q = '', int $limit = 500): array {
  $q = trim($q);
  $limit = max(1, min(2000, (int)$limit)); // safety

  $params = [];
  $sql = "SELECT kode, nama, jenis, poin, created_at FROM members";

  if ($q !== '') {
    $sql .= " WHERE kode LIKE ? OR nama LIKE ?";
    $params[] = "%{$q}%";
    $params[] = "%{$q}%";
  }

  $sql .= " ORDER BY created_at DESC LIMIT {$limit}";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  return $st->fetchAll(PDO::FETCH_ASSOC);
}
// ===== Modul Manajemen (Feature Toggle) =====
require_once __DIR__ . '/includes/module_helper.php';

/**
 * Log activity to audit_logs table
 */
function log_activity(PDO $pdo, string $action, string $description): void {
  if (!isset($_SESSION['user'])) return;
  
  $user_id = $_SESSION['user']['id'] ?? 0;
  $username = $_SESSION['user']['username'] ?? 'unknown';
  
  try {
    $stmt = $pdo->prepare("INSERT INTO audit_logs (user_id, username, action, description) VALUES (?, ?, ?, ?)");
    $stmt->execute([$user_id, $username, $action, $description]);
  } catch (PDOException $e) {
    // Ignored here, handled by centralized updater
  }
}
