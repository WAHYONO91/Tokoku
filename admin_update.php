<?php
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
require_once __DIR__ . '/includes/updater.php';

$logs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $mgr = new MigrationManager($pdo);
    if (isset($_POST['run_db_update'])) {
        $logs = run_app_updates($pdo);
    } elseif (isset($_POST['run_code_update'])) {
        $mgr->runGitPull();
        // Setelah pull kode, jalankan juga update DB otomatis karena file updater.php mungkin berubah
        run_app_updates($pdo);
        $logs = $mgr->getLogs();
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<article>
  <header>
    <hgroup>
      <h3>Pembaruan Sistem</h3>
      <p>Cek dan perbarui skema database serta fitur aplikasi secara otomatis.</p>
    </hgroup>
  </header>

  <section>
    <form method="post" style="display:flex; gap:1rem; flex-wrap:wrap;">
      <div style="flex:1; min-width:300px; border:1px solid #1e293b; padding:1rem; border-radius:0.5rem;">
        <h6>1. Update Kode (GitHub)</h6>
        <p><small>Menarik file terbaru dari repositori GitHub.</small></p>
        <button type="submit" name="run_code_update" class="contrast">Tarik Kode Terbaru</button>
      </div>

      <div style="flex:1; min-width:300px; border:1px solid #1e293b; padding:1rem; border-radius:0.5rem;">
        <h6>2. Update Database</h6>
        <p><small>Menyesuaikan skema tabel dengan fitur terbaru.</small></p>
        <button type="submit" name="run_db_update" class="secondary">Jalankan Migrasi DB</button>
      </div>
    </form>
  </section>

  <?php if (!empty($logs)): ?>
    <section>
      <h5>Hasil Pembaruan:</h5>
      <ul style="font-size: 0.85rem; font-family: monospace; background: #020617; padding: 1rem; border-radius: 0.5rem; list-style: none;">
        <?php foreach ($logs as $log): ?>
          <li style="color: <?= strpos($log, 'ERROR') !== false ? '#ef4444' : '#22c55e' ?>;">
            <?= htmlspecialchars($log) ?>
          </li>
        <?php endforeach; ?>
        <?php if (count($logs) === 0): ?>
          <li style="color: #94a3b8;">Aplikasi sudah di versi terbaru. Tidak ada perubahan yang diperlukan.</li>
        <?php endif; ?>
      </ul>
    </section>
  <?php endif; ?>

  <footer>
    <small>Versi Updater: 1.0.0 (Maret 2025)</small>
  </footer>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
