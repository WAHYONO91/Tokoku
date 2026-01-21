<?php
// Cek apakah user sudah login
function is_logged_in() {
  return isset($_SESSION['user']);
}

// Wajib login, kalau belum akan diarahkan ke halaman login
function require_login() {
  if (!is_logged_in()) {
    header('Location: /tokoapp/auth/login.php');
    exit;
  }
}

// Wajib punya role tertentu (misalnya ['admin','kasir'])
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

// Format angka ke Rupiah (Rp 10.000)
function rupiah($number) {
  return 'Rp ' . number_format($number, 0, ',', '.');
}

// Ambil pengaturan dari tabel settings
function get_setting($pdo, $id = 1) {
  $stmt = $pdo->prepare("SELECT * FROM settings WHERE id=?");
  $stmt->execute([$id]);
  return $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ============================================================
 * Inventory helpers
 * ============================================================
 */

// Pastikan baris stok untuk item tertentu ada di lokasi 'gudang' dan 'toko'
function ensure_stock_rows($pdo, $item_kode){
  // stok gudang
  $stmt = $pdo->prepare("
    INSERT INTO item_stocks (item_kode, location, qty)
    VALUES (?, 'gudang', 0)
    ON DUPLICATE KEY UPDATE qty = qty
  ");
  $stmt->execute([$item_kode]);

  // stok toko
  $stmt = $pdo->prepare("
    INSERT INTO item_stocks (item_kode, location, qty)
    VALUES (?, 'toko', 0)
    ON DUPLICATE KEY UPDATE qty = qty
  ");
  $stmt->execute([$item_kode]);
}

// Ambil stok di lokasi tertentu
function get_stock($pdo, $item_kode, $location){
  $stmt = $pdo->prepare("SELECT qty FROM item_stocks WHERE item_kode=? AND location=?");
  $stmt->execute([$item_kode, $location]);
  $row = $stmt->fetch(PDO::FETCH_ASSOC);
  return $row ? (int)$row['qty'] : 0;
}

// Sesuaikan stok (tambah/kurang) di lokasi tertentu
function adjust_stock($pdo, $item_kode, $location, $delta_qty){
  ensure_stock_rows($pdo, $item_kode);
  $stmt = $pdo->prepare("UPDATE item_stocks SET qty = qty + ? WHERE item_kode=? AND location=?");
  $stmt->execute([(int)$delta_qty, $item_kode, $location]);
}

/**
 * Auto update harga jual dari harga beli terakhir
 * Dipakai saat pembelian barang (purchase) supaya harga jual H1–H4
 * otomatis terisi jika masih 0.
 *
 * Rumus (boleh kamu ubah nanti):
 *  - H1 = 140% dari harga beli
 *  - H2 = 135% dari harga beli
 *  - H3 = 130% dari harga beli
 *  - H4 = 125% dari harga beli
 */
function auto_update_prices_from_purchase(PDO $pdo, string $item_kode, int $harga_beli): void {
    if ($harga_beli <= 0) return;

    $stmt = $pdo->prepare("
        SELECT harga_jual1, harga_jual2, harga_jual3, harga_jual4
        FROM items
        WHERE kode = ?
    ");
    $stmt->execute([$item_kode]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) return;

    // Hitung harga jual default
    $new1 = (int)round($harga_beli * 1.40);
    $new2 = (int)round($harga_beli * 1.35);
    $new3 = (int)round($harga_beli * 1.30);
    $new4 = (int)round($harga_beli * 1.25);

    $upd = $pdo->prepare("
        UPDATE items
        SET 
            harga_jual1 = IF(harga_jual1 IS NULL OR harga_jual1 = 0, ?, harga_jual1),
            harga_jual2 = IF(harga_jual2 IS NULL OR harga_jual2 = 0, ?, harga_jual2),
            harga_jual3 = IF(harga_jual3 IS NULL OR harga_jual3 = 0, ?, harga_jual3),
            harga_jual4 = IF(harga_jual4 IS NULL OR harga_jual4 = 0, ?, harga_jual4),
            harga_beli  = ?
        WHERE kode = ?
    ");
    $upd->execute([$new1, $new2, $new3, $new4, $harga_beli, $item_kode]);
}
