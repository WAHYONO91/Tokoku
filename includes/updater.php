<?php
/**
 * includes/updater.php
 * Sistem migrasi database otomatis untuk TokoApp.
 */

class MigrationManager {
    private $pdo;
    private $log = [];

    public function __construct(PDO $pdo) {
        $this->pdo = $pdo;
        $this->ensureMigrationsTable();
    }

    private function ensureMigrationsTable() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS migrations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                migration_name VARCHAR(100) UNIQUE NOT NULL,
                applied_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function isApplied($name) {
        $st = $this->pdo->prepare("SELECT COUNT(*) FROM migrations WHERE migration_name = ?");
        $st->execute([$name]);
        return $st->fetchColumn() > 0;
    }

    public function apply($name, callable $logic) {
        if ($this->isApplied($name)) return false;

        try {
            $logic($this->pdo);
            $st = $this->pdo->prepare("INSERT INTO migrations (migration_name) VALUES (?)");
            $st->execute([$name]);
            $this->log[] = "SUCCESS: Applied migration [$name]";
            return true;
        } catch (Exception $e) {
            $this->log[] = "ERROR: Failed migration [$name]: " . $e->getMessage();
            return false;
        }
    }

    private function findGitPath() {
        // 1. Cek global git via shell
        $version = shell_exec("git --version 2>&1");
        if ($version && strpos($version, 'git version') !== false) {
            return 'git';
        }

        // 2. Cek lokasi umum di Windows (jika PATH tidak terdeteksi oleh Apache/PHP)
        $candidates = [
            'C:\\Program Files\\Git\\bin\\git.exe',
            'C:\\Program Files\\Git\\cmd\\git.exe',
            'C:\\Program Files (x86)\\Git\\bin\\git.exe',
            'C:\\Program Files (x86)\\Git\\cmd\\git.exe',
            // Tambahkan lokasi lain jika perlu (misal user install di D:)
        ];

        foreach ($candidates as $p) {
            if (file_exists($p)) {
                 // Bungkus path dengan quote untuk safety space
                return '"' . $p . '"';
            }
        }
        
        return null;
    }

    public function runGitPull() {
        $this->log[] = "INFO: Attempting to pull latest code from GitHub...";
        
        $gitPath = $this->findGitPath();

        if (!$gitPath) {
            $this->log[] = "ERROR: Git tidak ditemukan di PATH sistem maupun lokasi default. Mohon cek instalasi Git atau tambahkan ke PATH ENV.";
            return false;
        }

        // Jalankan git pull dengan konfig safe.directory sementara untuk menghindari error ownership
        $cmd = "$gitPath -c safe.directory=* pull origin main 2>&1";
        $output = shell_exec($cmd);
        
        if ($output) {
            $this->log[] = "GIT OUTPUT: " . trim($output);
            if (strpos($output, 'Updating') !== false || strpos($output, 'Already up to date') !== false) {
                return true;
            } else {
                return false;
            }
        }
        
        $this->log[] = "ERROR: Tidak ada output dari perintah git pull.";
        return false;
    }

    public function getLogs() {
        return $this->log;
    }
}

/**
 * Fungsi utama untuk menjalankan update
 */
function run_app_updates(PDO $pdo) {
    $mgr = new MigrationManager($pdo);

    // 1. Tambah kolom THEME (Maret 2025)
    $mgr->apply('2025_03_07_add_theme_to_settings', function($pdo) {
        $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS theme VARCHAR(20) DEFAULT 'dark'");
    });

    // 2. Buat tabel AUDIT_LOGS
    $mgr->apply('2025_03_07_create_audit_logs_table', function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS audit_logs (
            id BIGINT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            username VARCHAR(50) NOT NULL,
            action VARCHAR(50) NOT NULL,
            description TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    });

    // 3. Buat tabel STOCK_MUTATIONS (jika belum ada)
    $mgr->apply('2025_03_07_create_stock_mutations_table', function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS stock_mutations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            item_kode VARCHAR(64) NOT NULL,
            from_loc  VARCHAR(32) NOT NULL,
            to_loc    VARCHAR(32) NOT NULL,
            qty       INT NOT NULL DEFAULT 0,
            created_by VARCHAR(64) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX (item_kode),
            INDEX (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    });

    // 4. Update tabel SUPPLIERS (tambah kode jika kolom id yang ada, atau sebaliknya)
    $mgr->apply('2025_03_07_sync_supplier_columns', function($pdo) {
        // Kita pastikan ada kolom kode dan hutang_awal
        $pdo->exec("ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS kode VARCHAR(50) AFTER id");
        $pdo->exec("ALTER TABLE suppliers ADD COLUMN IF NOT EXISTS hutang_awal BIGINT DEFAULT 0 AFTER tlp");
        // Update kode = id jika kode masih kosong
        $pdo->exec("UPDATE suppliers SET kode = id WHERE kode IS NULL OR kode = ''");
    });

    // 5. Update tabel PURCHASES (tambah bayar, sisa, supplier_kode)
    $mgr->apply('2025_03_07_add_debt_cols_to_purchases', function($pdo) {
        $pdo->exec("ALTER TABLE purchases ADD COLUMN IF NOT EXISTS supplier_kode VARCHAR(50) AFTER supplier_id");
        $pdo->exec("ALTER TABLE purchases ADD COLUMN IF NOT EXISTS bayar BIGINT DEFAULT 0 AFTER total");
        $pdo->exec("ALTER TABLE purchases ADD COLUMN IF NOT EXISTS sisa BIGINT DEFAULT 0 AFTER bayar");
        $pdo->exec("ALTER TABLE purchases ADD COLUMN IF NOT EXISTS status_lunas TINYINT(1) DEFAULT 1 AFTER sisa");
    });

    // 6. Buat tabel SUPPLIER_PAYMENTS
    $mgr->apply('2025_03_07_create_supplier_payments_table', function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS supplier_payments (
            id INT AUTO_INCREMENT PRIMARY KEY,
            tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
            supplier_kode VARCHAR(50) NOT NULL,
            purchase_id BIGINT DEFAULT NULL,
            jumlah BIGINT NOT NULL DEFAULT 0,
            metode VARCHAR(50) DEFAULT 'Tunai',
            keterangan TEXT,
            created_by VARCHAR(50),
            INDEX (supplier_kode),
            INDEX (purchase_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    });

    // 8. Buat tabel HELD_TRANSACTIONS (transaksi tunda)
    $mgr->apply('2025_03_07_create_held_transactions_table', function($pdo) {
        $pdo->exec("CREATE TABLE IF NOT EXISTS held_transactions (
            id INT AUTO_INCREMENT PRIMARY KEY,
            slot TINYINT NOT NULL,
            user_id INT NULL,
            state_json LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            KEY idx_slot (slot),
            KEY idx_user_slot (user_id, slot)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    });

    return $mgr->getLogs();
}
