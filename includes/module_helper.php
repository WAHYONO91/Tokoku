<?php
/**
 * Helper: cek apakah modul aktif
 * Menggunakan PDO ($pdo) dari config.php
 */
function module_active(string $code): bool
{
    // ambil $pdo dari scope global
    global $pdo;

    // 2. Kalau user belum login -> anggap modul mati
    if (!isset($_SESSION['user'])) {
        return false;
    }

    // 3. ADMIN selalu punya akses ke semua modul (Override)
    if (($_SESSION['user']['role'] ?? '') === 'admin') {
        // Tetap cek apakah modul ini aktif secara sistem (opsional, tapi sebaiknya admin tetap bisa lihat meski modul nonaktif?)
        // Untuk amannya, admin bisa lakukan segalanya.
    }

    // 4. Ambil status aktif modul secara sistem
    $stmt = $pdo->prepare("SELECT is_active FROM modules WHERE module_code = :code LIMIT 1");
    $stmt->execute(['code' => $code]);
    $mod = $stmt->fetch();
    if (!$mod || (int)$mod['is_active'] !== 1) {
        return false;
    }

    // 5. Jika role bukan admin, cek permission spesifik user
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        $userId = $_SESSION['user']['id'];
        $stPerm = $pdo->prepare("SELECT permissions FROM users WHERE id = ?");
        $stPerm->execute([$userId]);
        $permJson = $stPerm->fetchColumn();
        
        if ($permJson) {
            $allowed = json_decode($permJson, true);
            if (is_array($allowed) && in_array($code, $allowed)) {
                return true;
            }
        }
        return false; // Tidak ada izin spesifik
    }

    return true; // Admin bypass
}
