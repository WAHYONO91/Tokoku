<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/config.php';

/* ================= AUTH ================= */
if (!isset($_SESSION['user'])) {
    header('Location: auth/login.php');
    exit;
}
$role = $_SESSION['user']['role'] ?? '';
if (!in_array($role, ['admin','kasir'], true)) {
    http_response_code(403);
    die('Akses ditolak');
}

/* ================= MODUL ================= */
if (!module_active('TAGIHAN_MEMBER')) {
    http_response_code(403);
    die('Modul Tagihan Member dinonaktifkan');
}

/* ================= HELPER ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', '.'); }

/* ================= DATA ================= */
$stmt = $pdo->query("
SELECT
  m.id,
  m.kode,
  m.nama,
  SUM(ar.remaining) AS total_tagihan,
  COUNT(ar.id) AS jumlah_invoice,
  SUM(CASE WHEN ar.due_date < CURDATE() THEN 1 ELSE 0 END) AS overdue_cnt
FROM members m
JOIN member_ar ar ON ar.member_id = m.id
WHERE ar.status = 'OPEN'
GROUP BY m.id, m.kode, m.nama
HAVING total_tagihan > 0
ORDER BY total_tagihan DESC
");
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= HEADER ================= */
require_once __DIR__ . '/includes/header.php';
?>

<article>
  <h3>📌 Tagihan Member</h3>
  <p style="font-size:.8rem;color:#94a3b8;">
    Rekap total piutang <b>OPEN</b> per member dari seluruh transaksi.
  </p>

  <div style="overflow:auto;">
    <table class="table-small">
      <thead>
        <tr>
          <th>Member</th>
          <th class="right">Total Tagihan</th>
          <th>Invoice</th>
          <th>Overdue</th>
          <th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if (!$rows): ?>
        <tr><td colspan="5">Tidak ada tagihan.</td></tr>
      <?php else: foreach ($rows as $r): ?>
        <tr>
          <td><?=h($r['kode'])?> — <?=h($r['nama'])?></td>
          <td class="right"><b><?=money($r['total_tagihan'])?></b></td>
          <td><?= (int)$r['jumlah_invoice'] ?></td>
          <td><?= (int)$r['overdue_cnt'] ?></td>
          <td class="no-print">
            <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
              <!-- DETAIL -->
              <a class="menu-card"
                 href="member_ar_list.php?q=<?=urlencode($r['kode'])?>">
                🔍 Detail
              </a>

              <!-- CETAK TAGIHAN (ARAHKAN KE LETTER) -->
              <a class="menu-card"
                 target="_blank"
                 rel="noopener"
                 href="member_ar_letter.php?member_id=<?=$r['id']?>&scope=member">
                🖨 Cetak Tagihan
              </a>
            </div>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
