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
    echo "Akses ditolak.";
    exit;
}

/* ================= HELPER ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', '.'); }
function is_ymd($s){ return (bool)preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$s); }

/* ================= UPDATE DUE DATE (MAX 7 HARI) ================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_due_date') {
    $id  = (int)($_POST['id'] ?? 0);
    $new = $_POST['due_date'] ?? '';

    if ($id > 0 && $new !== '') {
        $q = $pdo->prepare("SELECT created_at FROM member_ar WHERE id = :id");
        $q->execute([':id' => $id]);
        if ($c = $q->fetch(PDO::FETCH_ASSOC)) {
            $created = date('Y-m-d', strtotime($c['created_at']));
            $min = strtotime($created);
            $max = strtotime($created . ' +7 days');
            $ts  = strtotime($new);
            if ($ts < $min) $ts = $min;
            if ($ts > $max) $ts = $max;
            $final = date('Y-m-d', $ts);

            $u = $pdo->prepare("
                UPDATE member_ar
                SET due_date = :d
                WHERE id = :id AND status = 'OPEN'
            ");
            $u->execute([':d' => $final, ':id' => $id]);
        }
    }
    header('Location: member_ar_list.php');
    exit;
}

/* ================= FILTER ================= */
$q        = trim($_GET['q'] ?? '');
$status   = strtoupper(trim($_GET['status'] ?? '')); // hanya untuk tabel utama
$overdue  = (int)($_GET['overdue'] ?? 0);
$date_from = trim($_GET['date_from'] ?? '');
$date_to   = trim($_GET['date_to'] ?? '');

$where  = [];
$params = [];

/* Search */
if ($q !== '') {
    // FIX: placeholder tidak boleh diulang di PDO MySQL
    $where[] = "(ar.invoice_no LIKE :q1 OR m.kode LIKE :q2 OR m.nama LIKE :q3)";
    $params[':q1'] = "%$q%";
    $params[':q2'] = "%$q%";
    $params[':q3'] = "%$q%";
}

/* Status (tabel utama saja) */
if (in_array($status, ['OPEN','PAID'], true)) {
    $where[] = "ar.status = :st";
    $params[':st'] = $status;
}

/* Overdue (OPEN saja) */
if ($overdue === 1) {
    $where[] = "ar.status='OPEN' AND ar.due_date < CURDATE()";
}

/* Filter tanggal transaksi (created_at) */
if ($date_from !== '' && is_ymd($date_from)) {
    $where[] = "DATE(ar.created_at) >= :df";
    $params[':df'] = $date_from;
}
if ($date_to !== '' && is_ymd($date_to)) {
    $where[] = "DATE(ar.created_at) <= :dt";
    $params[':dt'] = $date_to;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

/* ================= FILTER DASAR UNTUK DAFTAR OPEN/PAID (selalu tampil) ================= */
$baseWhere = [];
$baseParams = [];

/* Search */
if ($q !== '') {
    $baseWhere[] = "(ar.invoice_no LIKE :bq1 OR m.kode LIKE :bq2 OR m.nama LIKE :bq3)";
    $baseParams[':bq1'] = "%$q%";
    $baseParams[':bq2'] = "%$q%";
    $baseParams[':bq3'] = "%$q%";
}

/* Filter tanggal transaksi (created_at) - versi base */
if ($date_from !== '' && is_ymd($date_from)) {
    $baseWhere[] = "DATE(ar.created_at) >= :bdf";
    $baseParams[':bdf'] = $date_from;
}
if ($date_to !== '' && is_ymd($date_to)) {
    $baseWhere[] = "DATE(ar.created_at) <= :bdt";
    $baseParams[':bdt'] = $date_to;
}

/* Open list */
$openWhere = $baseWhere;
$openParams = $baseParams;
$openWhere[] = "ar.status='OPEN'";
if ($overdue === 1) $openWhere[] = "ar.due_date < CURDATE()";
$openWhereSql = 'WHERE ' . implode(' AND ', $openWhere);

/* Paid list */
$paidWhere = $baseWhere;
$paidParams = $baseParams;
$paidWhere[] = "ar.status='PAID'";
$paidWhereSql = 'WHERE ' . implode(' AND ', $paidWhere);

/* ================= SNAPSHOT HARI INI ================= */
/* 1) Piutang aktif (outstanding) per tanggal berjalan */
$snap = $pdo->query("
SELECT
  COALESCE(SUM(remaining),0) AS piutang_aktif,
  SUM(CASE WHEN status='OPEN' THEN 1 ELSE 0 END) open_cnt,
  SUM(CASE WHEN status='OPEN' AND due_date < CURDATE() THEN 1 ELSE 0 END) overdue_cnt
FROM member_ar
WHERE status='OPEN'
  AND DATE(created_at) <= CURDATE()
")->fetch(PDO::FETCH_ASSOC);

/* 2) Pembayaran piutang yang masuk hari ini */
$paidToday = (float)$pdo->query("
SELECT COALESCE(SUM(amount),0)
FROM member_ar_payments
WHERE DATE(paid_at) = CURDATE()
")->fetchColumn();

/* 3) Piutang transaksi hari ini (invoice OPEN yang dibuat hari ini) */
$todayAR = $pdo->query("
SELECT
  COALESCE(SUM(remaining),0) AS piutang_transaksi_hari_ini,
  COUNT(*) AS cnt_invoice_hari_ini
FROM member_ar
WHERE DATE(created_at) = CURDATE()
  AND status = 'OPEN'
")->fetch(PDO::FETCH_ASSOC);

/* 4) (Opsional) total nilai invoice yang dibuat hari ini (semua status) */
$todayInvoiceTotal = (float)$pdo->query("
SELECT COALESCE(SUM(total),0)
FROM member_ar
WHERE DATE(created_at) = CURDATE()
")->fetchColumn();

/* ================= REKAP PIUTANG PER MEMBER (ikut filter search + tgl transaksi) ================= */
$recapWhere = [];
$recapParams = [];

if ($q !== '') {
    $recapWhere[] = "(ar.invoice_no LIKE :rq1 OR m.kode LIKE :rq2 OR m.nama LIKE :rq3)";
    $recapParams[':rq1'] = "%$q%";
    $recapParams[':rq2'] = "%$q%";
    $recapParams[':rq3'] = "%$q%";
}
if ($date_from !== '' && is_ymd($date_from)) {
    $recapWhere[] = "DATE(ar.created_at) >= :rdf";
    $recapParams[':rdf'] = $date_from;
}
if ($date_to !== '' && is_ymd($date_to)) {
    $recapWhere[] = "DATE(ar.created_at) <= :rdt";
    $recapParams[':rdt'] = $date_to;
}

$recapWhereSql = $recapWhere ? ('WHERE ' . implode(' AND ', $recapWhere)) : '';

$stmtRecap = $pdo->prepare("
SELECT
  m.kode,
  m.nama,
  SUM(CASE WHEN ar.status='OPEN' THEN ar.remaining ELSE 0 END) piutang,
  SUM(ar.paid) terbayar,
  COUNT(CASE WHEN ar.status='OPEN' THEN 1 END) open_cnt,
  SUM(CASE WHEN ar.status='OPEN' AND ar.due_date < CURDATE() THEN 1 ELSE 0 END) overdue_cnt
FROM members m
JOIN member_ar ar ON ar.member_id = m.id
$recapWhereSql
GROUP BY m.id, m.kode, m.nama
HAVING piutang > 0
ORDER BY piutang DESC
");
$stmtRecap->execute($recapParams);
$recapMember = $stmtRecap->fetchAll(PDO::FETCH_ASSOC);

/* ================= AGING 0–7 HARI ================= */
$aging = $pdo->query("
SELECT
  SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 0 AND 1 THEN remaining ELSE 0 END) d01,
  SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 2 AND 3 THEN remaining ELSE 0 END) d23,
  SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 4 AND 5 THEN remaining ELSE 0 END) d45,
  SUM(CASE WHEN DATEDIFF(CURDATE(), due_date) BETWEEN 6 AND 7 THEN remaining ELSE 0 END) d67
FROM member_ar
WHERE status='OPEN'
")->fetch(PDO::FETCH_ASSOC);

/* ================= LIST INVOICE (TABEL UTAMA, IKUT FILTER STATUS) ================= */
$sql = "
SELECT
  ar.*,
  m.kode AS mk,
  m.nama AS mn,
  CASE
    WHEN ar.status='OPEN' AND ar.due_date < CURDATE() THEN 1
    ELSE 0
  END AS is_overdue
FROM member_ar ar
JOIN members m ON m.id = ar.member_id
$whereSql
ORDER BY ar.created_at DESC
";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* ================= DAFTAR TAGIHAN (OPEN) PER INVOICE ================= */
$sqlOpen = "
SELECT
  ar.*,
  m.kode AS mk,
  m.nama AS mn,
  CASE WHEN ar.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
FROM member_ar ar
JOIN members m ON m.id = ar.member_id
$openWhereSql
ORDER BY ar.due_date ASC, ar.created_at DESC
";
$stmtOpen = $pdo->prepare($sqlOpen);
$stmtOpen->execute($openParams);
$rowsOpen = $stmtOpen->fetchAll(PDO::FETCH_ASSOC);

/* ================= DAFTAR PIUTANG LUNAS (PAID) PER INVOICE ================= */
$sqlPaid = "
SELECT
  ar.*,
  m.kode AS mk,
  m.nama AS mn
FROM member_ar ar
JOIN members m ON m.id = ar.member_id
$paidWhereSql
ORDER BY ar.created_at DESC
";
$stmtPaid = $pdo->prepare($sqlPaid);
$stmtPaid->execute($paidParams);
$rowsPaid = $stmtPaid->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<style>
.aging-table td { font-weight:600; text-align:right }
.aging-0-1 { background:#dcfce7; color:#166534 }
.aging-2-3 { background:#fef9c3; color:#854d0e }
.aging-4-5 { background:#ffedd5; color:#9a3412 }
.aging-6-7 { background:#fee2e2; color:#991b1b; font-weight:700 }
@media print {
  .aging-0-1,.aging-2-3,.aging-4-5,.aging-6-7 { background:transparent; color:#000; }
}
</style>

<article>
  <h3>Piutang Member</h3>

  <!-- FILTER -->
  <form method="get" class="no-print" style="display:flex;gap:.4rem;flex-wrap:wrap;align-items:end;margin-bottom:.6rem;">
    <label style="margin:0;min-width:220px;">
      Cari (Invoice / Kode / Nama)
      <input type="text" name="q" value="<?=h($q)?>" placeholder="mis. INV-001 / MB001 / Budi">
    </label>

    <label style="margin:0;min-width:180px;">
      Status (Tabel Utama)
      <select name="status">
        <option value="" <?= $status===''?'selected':''; ?>>Semua</option>
        <option value="OPEN" <?= $status==='OPEN'?'selected':''; ?>>Belum Lunas</option>
        <option value="PAID" <?= $status==='PAID'?'selected':''; ?>>Lunas</option>
      </select>
    </label>

    <label style="margin:0;min-width:170px;">
      Tgl Transaksi Dari
      <input type="date" name="date_from" value="<?=h($date_from)?>">
    </label>

    <label style="margin:0;min-width:170px;">
      Tgl Transaksi Sampai
      <input type="date" name="date_to" value="<?=h($date_to)?>">
    </label>

    <label style="margin:0;">
      <input type="checkbox" name="overdue" value="1" <?= $overdue===1?'checked':''; ?>>
      Jatuh tempo saja (OPEN)
    </label>

    <button type="submit" class="primary">Terapkan</button>
    <a href="member_ar_list.php" class="secondary" style="text-decoration:none;">Reset</a>
  </form>

  <!-- DASHBOARD SNAPSHOT -->
  <div class="no-print" style="display:flex;gap:.6rem;flex-wrap:wrap;margin-bottom:1rem;">
    <div class="menu-card">📌 Piutang Aktif (Outstanding) <b><?=money($snap['piutang_aktif'])?></b></div>
    <div class="menu-card">🧾 Piutang Transaksi Hari Ini <b><?=money($todayAR['piutang_transaksi_hari_ini'])?></b></div>
    <div class="menu-card">🧮 Invoice Hari Ini <b><?= (int)$todayAR['cnt_invoice_hari_ini'] ?></b></div>
    <div class="menu-card">🛒 Total Invoice Hari Ini <b><?=money($todayInvoiceTotal)?></b></div>
    <div class="menu-card">💰 Dibayar Hari Ini <b><?=money($paidToday)?></b></div>
    <div class="menu-card">⏳ Open <b><?= (int)$snap['open_cnt'] ?></b></div>
    <div class="menu-card">⚠️ Overdue <b><?= (int)$snap['overdue_cnt'] ?></b></div>
    <a href="#" onclick="window.print();return false;" class="menu-card">🖨️ Print</a>
  </div>

  <!-- REKAP PER MEMBER -->
  <h4>Rekap Piutang per Member</h4>
  <div style="overflow:auto;">
    <table class="table-small">
      <thead>
        <tr>
          <th>Member</th>
          <th class="right">Piutang</th>
          <th class="right">Terbayar</th>
          <th>Open</th>
          <th>Overdue</th>
          <th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$recapMember): ?>
          <tr><td colspan="6">Tidak ada piutang aktif.</td></tr>
        <?php else: ?>
          <?php foreach ($recapMember as $m): ?>
            <tr>
              <td><?=h($m['kode'])?> — <?=h($m['nama'])?></td>
              <td class="right"><b><?=money($m['piutang'])?></b></td>
              <td class="right"><?=money($m['terbayar'])?></td>
              <td><?= (int)$m['open_cnt'] ?></td>
              <td><?= (int)$m['overdue_cnt'] ?></td>
              <td class="no-print">
                <?php
                  $qs = [
                    'q' => $m['kode'],
                    'date_from' => ($date_from !== '' ? $date_from : null),
                    'date_to'   => ($date_to !== '' ? $date_to : null),
                  ];
                  $qs = array_filter($qs, fn($v)=>$v!==null && $v!=='');
                ?>
                <a class="menu-card" style="padding:.15rem .4rem;" href="member_ar_list.php?<?=http_build_query($qs)?>">
                  🔍 Detail
                </a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- AGING -->
  <h4 style="margin-top:1rem;">Aging Piutang (0–7 Hari)</h4>
  <div style="overflow:auto;">
    <table class="table-small aging-table">
      <tr><th>0–1</th><th>2–3</th><th>4–5</th><th>6–7</th></tr>
      <tr>
        <td class="aging-0-1"><?=money($aging['d01'])?></td>
        <td class="aging-2-3"><?=money($aging['d23'])?></td>
        <td class="aging-4-5"><?=money($aging['d45'])?></td>
        <td class="aging-6-7"><?=money($aging['d67'])?></td>
      </tr>
    </table>
  </div>

  <p class="no-print" style="margin-top:.4rem;font-size:.75rem;color:#94a3b8;max-width:720px;">
    <b>Keterangan:</b> Aging piutang menunjukkan sisa piutang berdasarkan jumlah hari keterlambatan setelah jatuh tempo.
    Semakin besar rentang hari, semakin tinggi prioritas penagihan.
  </p>

  <!-- TAGIHAN (OPEN) -->
  <h4 style="margin-top:1rem;">Daftar Tagihan per Invoice (OPEN)</h4>
  <div style="overflow:auto;">
    <table class="table-small">
      <thead>
        <tr>
          <th>Tanggal</th><th>Invoice</th><th>Member</th>
          <th class="right">Total</th><th class="right">Terbayar</th><th class="right">Sisa</th>
          <th>Jatuh Tempo</th><th>Status</th><th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rowsOpen): ?>
          <tr><td colspan="9">Tidak ada tagihan OPEN.</td></tr>
        <?php else: ?>
          <?php foreach ($rowsOpen as $r): ?>
            <tr>
              <td><?=h(date('Y-m-d', strtotime($r['created_at'])))?></td>
              <td><?=h($r['invoice_no'])?></td>
              <td><?=h($r['mk'])?> — <?=h($r['mn'])?></td>
              <td class="right"><?=money($r['total'])?></td>
              <td class="right"><?=money($r['paid'])?></td>
              <td class="right"><b><?=money($r['remaining'])?></b></td>
              <td>
                <?php
                  $cd = date('Y-m-d', strtotime($r['created_at']));
                  $mx = date('Y-m-d', strtotime($cd.' +7 days'));
                ?>
                <form method="post" class="no-print" style="display:flex;gap:.25rem;">
                  <input type="hidden" name="action" value="update_due_date">
                  <input type="hidden" name="id" value="<?= (int)$r['id'] ?>">
                  <input type="date" name="due_date" value="<?=h($r['due_date'])?>" min="<?=$cd?>" max="<?=$mx?>">
                  <button class="secondary">✔</button>
                </form>
              </td>
              <td><?= ((int)$r['is_overdue']===1) ? 'OVERDUE' : 'OPEN' ?></td>
              <td class="no-print">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                  <a class="menu-card" href="member_ar_pay_form.php?id=<?= (int)$r['id'] ?>">💳 Bayar</a>
                  <a class="menu-card" target="_blank" rel="noopener" href="member_ar_letter.php?id=<?= (int)$r['id'] ?>">📄 Surat</a>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- LUNAS (PAID) -->
  <h4 style="margin-top:1rem;">Daftar Piutang Lunas per Invoice (PAID)</h4>
  <div style="overflow:auto;">
    <table class="table-small">
      <thead>
        <tr>
          <th>Tanggal</th><th>Invoice</th><th>Member</th>
          <th class="right">Total</th><th class="right">Terbayar</th><th class="right">Sisa</th>
          <th>Jatuh Tempo</th><th>Status</th><th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rowsPaid): ?>
          <tr><td colspan="9">Tidak ada invoice PAID.</td></tr>
        <?php else: ?>
          <?php foreach ($rowsPaid as $r): ?>
            <tr>
              <td><?=h(date('Y-m-d', strtotime($r['created_at'])))?></td>
              <td><?=h($r['invoice_no'])?></td>
              <td><?=h($r['mk'])?> — <?=h($r['mn'])?></td>
              <td class="right"><?=money($r['total'])?></td>
              <td class="right"><?=money($r['paid'])?></td>
              <td class="right"><b><?=money($r['remaining'])?></b></td>
              <td><?=h($r['due_date'])?></td>
              <td>LUNAS</td>
              <td class="no-print">
                <a class="menu-card" target="_blank" rel="noopener" href="member_ar_letter.php?id=<?= (int)$r['id'] ?>">📄 Surat</a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <!-- TABEL UTAMA (FITUR LAMA) -->
  <h4 style="margin-top:1rem;">Daftar Invoice Piutang (Tabel Utama)</h4>
  <div style="overflow:auto;">
    <table class="table-small">
      <thead>
        <tr>
          <th>Tanggal</th><th>Invoice</th><th>Member</th>
          <th class="right">Total</th><th class="right">Terbayar</th><th class="right">Sisa</th>
          <th>Jatuh Tempo</th><th>Status</th><th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php if (!$rows): ?>
          <tr><td colspan="9">Tidak ada data.</td></tr>
        <?php else: ?>
          <?php foreach ($rows as $r): ?>
            <?php
              $isOver = ((int)$r['is_overdue'] === 1);
              $badge = $r['status'] === 'PAID' ? 'LUNAS' : ($isOver ? 'OVERDUE' : 'OPEN');
            ?>
            <tr>
              <td><?=h(date('Y-m-d', strtotime($r['created_at'])))?></td>
              <td><?=h($r['invoice_no'])?></td>
              <td><?=h($r['mk'])?> — <?=h($r['mn'])?></td>
              <td class="right"><?=money($r['total'])?></td>
              <td class="right"><?=money($r['paid'])?></td>
              <td class="right"><b><?=money($r['remaining'])?></b></td>
              <td><?=h($r['due_date'])?></td>
              <td><?=h($badge)?></td>
              <td class="no-print">
                <div style="display:flex;gap:.35rem;flex-wrap:wrap;">
                  <?php if (($r['status'] ?? '') === 'OPEN'): ?>
                    <a class="menu-card" href="member_ar_pay_form.php?id=<?= (int)$r['id'] ?>">💳 Bayar</a>
                  <?php endif; ?>
                  <a class="menu-card" target="_blank" rel="noopener" href="member_ar_letter.php?id=<?= (int)$r['id'] ?>">📄 Surat</a>
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
