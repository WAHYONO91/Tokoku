<?php
/**
 * Helper: cek apakah modul aktif
 * Menggunakan PDO ($pdo) dari config.php
 */
function module_active(string $code): bool
{
    // ambil $pdo dari scope global
    global $pdo;

    // kalau koneksi belum ada → anggap modul mati
    if (!isset($pdo)) {
        return false;
    }

    $stmt = $pdo->prepare(
        "SELECT is_active
         FROM modules
         WHERE module_code = :code
         LIMIT 1"
    );

    $stmt->execute(['code' => $code]);
    $row = $stmt->fetch();

    return $row && (int)$row['is_active'] === 1;
}
