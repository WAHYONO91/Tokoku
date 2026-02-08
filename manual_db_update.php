<?php
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_sql'])) {
    $sql = trim($_POST['sql_query'] ?? '');
    
    if (empty($sql)) {
        $error = "Query tidak boleh kosong.";
    } else {
        // Basic safety check: prevent unintended destruction if possible (though admin can do anything)
        // We rely on the user being admin and knowing what they are doing.
        
        try {
            $pdo->beginTransaction();
            
            // Support multiple queries separated by semicolon if needed, 
            // but PDO::exec usually runs one statement. 
            // For safety and simplicity in error handling, we recommend one block.
            // However, to support advanced scripts, we can try to run it.
            // Note: PDO::exec/query with multiple statements might depend on driver settings.
            // Let's stick to standard execution.
            
            $pdo->exec($sql);
            $pdo->commit();
            $message = "Query berhasil dieksekusi.";
        } catch (PDOException $e) {
            $pdo->rollBack();
            $error = "Gagal mengeksekusi query. Perubahan dibatalkan (Rollback).<br>Error: " . htmlspecialchars($e->getMessage());
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<article>
    <header>
        <hgroup>
            <h3>Update Database Manual</h3>
            <p>Alat bantu untuk menjalankan perintah SQL secara manual dan aman.</p>
        </hgroup>
    </header>

    <div style="background: #fff3cd; color: #856404; padding: 1rem; border-radius: 0.5rem; margin-bottom: 1rem; border: 1px solid #ffeeba;">
        <strong>PERHATIAN!</strong><br>
        <ul>
            <li>Pastikan Anda sudah melakukan <strong>BACKUP DATABASE</strong> sebelum menjalankan query apa pun.</li>
            <li>Gunakan perintah yang aman seperti <code>IF NOT EXISTS</code> untuk menghindari error jika tabel/kolom sudah ada.</li>
            <li>Kesalahan query dapat merusak data aplikasi.</li>
        </ul>
        <div style="margin-top: 0.5rem;">
            <a href="backup_tokoapp.php?mode=save" target="_blank" class="contrast" style="text-decoration: none; font-weight: bold;">
                💾 Backup Database Sekarang (Tab Baru)
            </a>
        </div>
    </div>

    <!-- Form Input SQL -->
    <form method="post">
        <label for="sql_query"><strong>SQL Query:</strong></label>
        <textarea name="sql_query" id="sql_query" rows="10" style="font-family: monospace; width: 100%; white-space: pre;" placeholder="Contoh: ALTER TABLE settings ADD COLUMN IF NOT EXISTS theme VARCHAR(20) DEFAULT 'dark';"><?= htmlspecialchars($_POST['sql_query'] ?? '') ?></textarea>
        
        <div style="margin-top: 1rem;">
            <button type="submit" name="execute_sql" class="primary" onclick="return confirm('Apakah Anda yakin ingin menjalankan query ini? Pastikan sudah backup!');">
                Jalankan Query
            </button>
        </div>
    </form>

    <!-- Hasil Eksekusi -->
    <?php if ($message): ?>
        <div style="margin-top: 1rem; padding: 1rem; background: #d1e7dd; color: #0f5132; border-radius: 0.5rem; border: 1px solid #badbcc;">
            <?= $message ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="margin-top: 1rem; padding: 1rem; background: #f8d7da; color: #842029; border-radius: 0.5rem; border: 1px solid #f5c2c7;">
            <?= $error ?>
        </div>
    <?php endif; ?>

    <hr>

    <!-- Cheat Sheet / Contoh -->
    <details>
        <summary><strong>Contoh Query Aman (Cheat Sheet)</strong></summary>
        <div style="background: #1e293b; padding: 1rem; border-radius: 0.5rem; margin-top: 0.5rem; font-family: monospace; font-size: 0.9rem;">
            <p style="color: #94a3b8; margin-bottom: 0.5rem;">// Menambah kolom jika belum ada</p>
            <code style="color: #a5b4fc; display: block; margin-bottom: 1rem;">
                ALTER TABLE nama_tabel ADD COLUMN IF NOT EXISTS nama_kolom VARCHAR(50) AFTER id;
            </code>

            <p style="color: #94a3b8; margin-bottom: 0.5rem;">// Membuat tabel baru jika belum ada</p>
            <code style="color: #a5b4fc; display: block; margin-bottom: 1rem;">
                CREATE TABLE IF NOT EXISTS nama_tabel_baru (<br>
                &nbsp;&nbsp;id INT AUTO_INCREMENT PRIMARY KEY,<br>
                &nbsp;&nbsp;nama VARCHAR(100) NOT NULL,<br>
                &nbsp;&nbsp;created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP<br>
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            </code>

            <p style="color: #94a3b8; margin-bottom: 0.5rem;">// Menambah index</p>
            <code style="color: #a5b4fc; display: block; margin-bottom: 1rem;">
                <!-- MySQL lama tidak support IF NOT EXISTS untuk INDEX, gunakan try-catch atau prosedur khusus, 
                     tapi untuk manual, jika error "Duplicate key" berarti sudah ada. aman. -->
                CREATE INDEX idx_nama ON nama_tabel(nama_kolom);
            </code>
        </div>
    </details>

</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
