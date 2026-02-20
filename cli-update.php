<?php
/**
 * cli-update.php
 * Wrapper untuk menjalankan update aplikasi melalui CLI (Command Line Interface).
 */

if (PHP_SAPI !== 'cli') {
    die("Error: Skrip ini hanya bisa dijalankan melalui CLI.\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/includes/updater.php';

echo "=== TokoApp CLI Updater ===\n";
echo "Memulai proses update...\n";

try {
    $logs = run_app_updates($pdo);
    foreach ($logs as $log) {
        echo $log . "\n";
    }
    echo "Proses update selesai.\n";
} catch (Exception $e) {
    echo "ERROR KRITIKAL: " . $e->getMessage() . "\n";
    exit(1);
}
