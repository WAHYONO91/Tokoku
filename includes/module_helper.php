<?php
// Master Modul TokoApp (Samakan dengan module_management.php)
$MENU_MASTER = [
    'DASHBOARD'        => 'Dashboard',
    'INVENTORY'        => 'Master Barang',
    'MEMBER'           => 'Member',
    'REDEEM'           => 'Tukar Poin',
    'SUPPLIER'         => 'Supplier',
    'PURCHASE'         => 'Pembelian',
    'STOCK'            => 'Mutasi Stok',
    'POS_DISPLAY'      => 'POS Display',
    'REPORT_STOCK'     => 'Laporan Stok',
    'REPORT_SALES'     => 'Laporan Penjualan',
    'REPORT_PURCHASE'  => 'Laporan Pembelian',
    'CASH_IN'          => 'Penerimaan Kas',
    'CASH_OUT'         => 'Pengeluaran Kas',
    'CASHIER'          => 'Kas Kasir',
    'PIUTANG'          => 'Piutang Member',
    'RETURNS'          => 'Retur Barang',
    'TAGIHAN_MEMBER'   => 'Tagihan Member',
    'TAGIHAN_SUPPLIER' => 'Tagihan Supplier',
    'SETTINGS'         => 'Pengaturan',
    'USERS'            => 'Users',
    'AUDIT_TRAIL'      => 'Audit Trail',
    'BACKUP'           => 'Backup Database',
    'MODULE_MGMT'      => 'Manajemen Modul',
];

function sync_modules(PDO $pdo) {
    global $MENU_MASTER;
    $check  = $pdo->prepare("SELECT COUNT(*) FROM modules WHERE module_code=:c");
    $insert = $pdo->prepare("INSERT INTO modules (module_code, module_name, is_active) VALUES (:c, :n, 1)");
    foreach ($MENU_MASTER as $code => $name) {
        $check->execute(['c'=>$code]);
        if ($check->fetchColumn() == 0) {
            $insert->execute(['c'=>$code,'n'=>$name]);
        }
    }
}

function module_active(string $code): bool
{
    global $pdo;
    static $userCache = null;
    static $moduleCache = null;

    if (!isset($_SESSION['user'])) return false;

    // 1. Ambil status aktif modul secara sistem (Cache per request)
    if ($moduleCache === null) {
        $moduleCache = $pdo->query("SELECT module_code, is_active FROM modules")->fetchAll(PDO::FETCH_KEY_PAIR);
    }
    
    // Jika modul belum terdaftar di tabel, default aktif untuk Admin
    if (!isset($moduleCache[$code])) {
        return (($_SESSION['user']['role'] ?? '') === 'admin');
    }

    if ((int)$moduleCache[$code] !== 1) {
        return false;
    }

    // 2. Jika bukan admin, cek permission spesifik user (Cache per request)
    if (($_SESSION['user']['role'] ?? '') !== 'admin') {
        if ($userCache === null) {
            $userId = $_SESSION['user']['id'];
            $stPerm = $pdo->prepare("SELECT permissions FROM users WHERE id = ?");
            $stPerm->execute([$userId]);
            $permJson = $stPerm->fetchColumn();
            $userCache = json_decode($permJson ?: '[]', true);
            if (!is_array($userCache)) $userCache = [];
        }
        
        return in_array($code, $userCache);
    }

    return true; // Admin bypass
}
