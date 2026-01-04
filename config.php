<?php
// ===== Session =====
if (session_status() === PHP_SESSION_NONE) {
    // (Opsional) Perketat cookie session di produksi
    // session_set_cookie_params([
    //     'httponly' => true,
    //     'samesite' => 'Lax',
    //     'secure'   => isset($_SERVER['HTTPS']),
    // ]);
    session_start();
}

// ===== Timezone PHP (WIB) =====
date_default_timezone_set('Asia/Jakarta');

// ===== PDO (MySQL) =====
$dsn  = 'mysql:host=127.0.0.1;dbname=tokoapp;charset=utf8mb4';
$user = 'root';
$pass = 'Banyumas11#';

$pdo = new PDO($dsn, $user, $pass, [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false, // gunakan prepared statement native
]);

// Pastikan sesi MySQL ikut WIB
// Catatan: jika server MySQL sudah di-set ke +07:00, baris ini tetap aman.
$pdo->exec("SET time_zone = '+07:00'");

// (Opsional) Konsistenkan mode SQL jika dibutuhkan
// $pdo->exec("SET sql_mode=''");

// ===============================
// MUAT SEMUA HELPER DARI functions.php
// ===============================
require_once __DIR__.'/functions.php';
