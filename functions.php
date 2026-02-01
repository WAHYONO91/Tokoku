<?php
// =====================
// AUTH & SESSION HELPER
// =====================

function is_logged_in() {
  return isset($_SESSION['user']);
}

function require_login() {
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
