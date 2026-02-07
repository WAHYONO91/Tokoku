<?php
require_once __DIR__ . '/config.php';
require_login();
require_role(['admin']);
require_once __DIR__ . '/includes/updater.php';

$logs = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_update'])) {
    $logs = run_app_updates($pdo);
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
    <form method="post">
      <p>Penting: Sangat disarankan untuk mem-backup database sebelum melakukan pembaruan besar.</p>
      <button type="submit" name="run_update" class="contrast">Jalankan Pembaruan Sekarang</button>
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
