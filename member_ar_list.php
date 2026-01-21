<?php
// member_ar_list.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/config.php';

// Wajib login
if (!isset($_SESSION['user'])) {
    header('Location: auth/login.php');
    exit;
}

$role = $_SESSION['user']['role'] ?? null;
// Admin & kasir boleh akses
if (!in_array($role, ['admin', 'kasir'], true)) {
    http_response_code(403);
    echo "Akses ditolak.";
    exit;
}

// ===== Filters (opsional) =====
$q        = trim($_GET['q'] ?? '');
$status   = strtoupper(trim($_GET['status'] ?? '')); // OPEN / PAID / ''
$overdue  = isset($_GET['overdue']) ? (int)$_GET['overdue'] : 0; // 1 = overdue only

$where = [];
$params = [];

if ($q !== '') {
    // cari invoice / kode / nama member (pakai placeholder beda biar aman)
    $where[] = "(ar.invoice_no LIKE :q1 OR m.kode LIKE :q2 OR m.nama LIKE :q3)";
    $like = '%' . $q . '%';
    $params[':q1'] = $like;
    $params[':q2'] = $like;
    $params[':q3'] = $like;
}


if (in_array($status, ['OPEN', 'PAID'], true)) {
    $where[] = "ar.status = :status";
    $params[':status'] = $status;
}

if ($overdue === 1) {
    $where[] = "(ar.status = 'OPEN' AND ar.due_date < CURDATE())";
}

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ===== Data =====
$sql = "
SELECT
  ar.id,
  ar.invoice_no,
  ar.total,
  ar.paid,
  ar.remaining,
  ar.due_date,
  ar.status,
  ar.created_at,
  m.kode AS member_kode,
  m.nama AS member_nama,
  CASE
    WHEN ar.status='OPEN' AND ar.due_date < CURDATE() THEN 1
    ELSE 0
  END AS is_overdue
FROM member_ar ar
JOIN members m ON m.id = ar.member_id
{$whereSql}
ORDER BY ar.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ===== Summary =====
$sumTotal = 0;
$sumPaid  = 0;
$sumRem   = 0;
$cntOpen  = 0;
$cntOver  = 0;

foreach ($rows as $r) {
    $sumTotal += (float)$r['total'];
    $sumPaid  += (float)$r['paid'];
    $sumRem   += (float)$r['remaining'];
    if (($r['status'] ?? '') === 'OPEN') $cntOpen++;
    if ((int)$r['is_overdue'] === 1) $cntOver++;
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', '.'); }

?>
<?php require_once __DIR__ . '/includes/header.php'; ?>

<article>
  <h3>Piutang Member</h3>

  <form method="get" class="no-print" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:end;margin-bottom:.6rem;">
    <label style="margin:0;min-width:220px;">
      Cari (Invoice / Kode / Nama)
      <input type="text" name="q" value="<?=h($q)?>" placeholder="mis. INV-001 / MB001 / Budi">
    </label>

    <label style="margin:0;min-width:160px;">
      Status
      <select name="status">
        <option value="" <?= $status===''?'selected':''; ?>>Semua</option>
        <option value="OPEN" <?= $status==='OPEN'?'selected':''; ?>>Belum Lunas</option>
        <option value="PAID" <?= $status==='PAID'?'selected':''; ?>>Lunas</option>
      </select>
    </label>

    <label style="margin:0;">
      <input type="checkbox" name="overdue" value="1" <?= $overdue===1?'checked':''; ?>>
      Jatuh tempo saja
    </label>

    <button type="submit" class="primary">Terapkan</button>
    <a href="member_ar_list.php" class="secondary" style="text-decoration:none;">Reset</a>
  </form>

  <div class="no-print" style="display:flex;gap:.6rem;flex-wrap:wrap;margin:.4rem 0 .8rem;">
    <div class="menu-card" style="cursor:default;">
      <span class="menu-icon">📌</span>
      <span>Total Piutang: <b><?=money($sumTotal)?></b></span>
    </div>
    <div class="menu-card" style="cursor:default;">
      <span class="menu-icon">✅</span>
      <span>Total Terbayar: <b><?=money($sumPaid)?></b></span>
    </div>
    <div class="menu-card" style="cursor:default;">
      <span class="menu-icon">🧾</span>
      <span>Sisa: <b><?=money($sumRem)?></b></span>
    </div>
    <div class="menu-card" style="cursor:default;">
      <span class="menu-icon">⏳</span>
      <span>Open: <b><?= (int)$cntOpen ?></b></span>
    </div>
    <div class="menu-card" style="cursor:default;">
      <span class="menu-icon">⚠️</span>
      <span>Overdue: <b><?= (int)$cntOver ?></b></span>
    </div>
    <a href="#" onclick="window.print();return false;" class="menu-card no-print">
      <span class="menu-icon">🖨️</span><span>Print</span>
    </a>
  </div>

  <div style="overflow:auto;">
    <table class="table-small">
      <thead>
        <tr>
          <th style="white-space:nowrap;">Tanggal</th>
          <th style="white-space:nowrap;">Invoice</th>
          <th style="white-space:nowrap;">Member</th>
          <th class="right" style="white-space:nowrap;">Total</th>
          <th class="right" style="white-space:nowrap;">Terbayar</th>
          <th class="right" style="white-space:nowrap;">Sisa</th>
          <th style="white-space:nowrap;">Jatuh Tempo</th>
          <th style="white-space:nowrap;">Status</th>
          <th class="no-print" style="white-space:nowrap;">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $isOver = ((int)$r['is_overdue'] === 1);
              $badge = $r['status'] === 'PAID' ? '✅ LUNAS' : ($isOver ? '⚠️ OVERDUE' : '⏳ OPEN');
            ?>
            <tr>
              <td><?=h(date('Y-m-d', strtotime($r['created_at'])))?></td>
              <td><?=h($r['invoice_no'])?></td>
              <td><?=h($r['member_kode'])?> — <?=h($r['member_nama'])?></td>
              <td class="right"><?=money($r['total'])?></td>
              <td class="right"><?=money($r['paid'])?></td>
              <td class="right"><b><?=money($r['remaining'])?></b></td>
              <td><?=h($r['due_date'])?></td>
              <td><?=h($badge)?></td>
              <td class="no-print">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                  <?php if (($r['status'] ?? '') === 'OPEN'): ?>
                    <a class="menu-card"
                       style="display:inline-flex;gap:.25rem;padding:.15rem .35rem;border-radius:.45rem;"
                       href="member_ar_pay_form.php?id=<?= (int)$r['id'] ?>">
                      <span class="menu-icon">💳</span><span>Bayar</span>
                    </a>
                  <?php endif; ?>

                  <a class="menu-card"
                     style="display:inline-flex;gap:.25rem;padding:.15rem .35rem;border-radius:.45rem;<?= (($r['status'] ?? '') !== 'OPEN') ? 'opacity:.7;' : '' ?>"
                     href="member_ar_letter.php?id=<?= (int)$r['id'] ?>"
                     target="_blank" rel="noopener">
                    <span class="menu-icon">📄</span><span>Surat</span>
                  </a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <p class="no-print" style="margin-top:.75rem;color:#94a3b8;font-size:.75rem;">
    Catatan: Overdue = status OPEN dan due date lebih kecil dari tanggal hari ini.
  </p>
</article>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
