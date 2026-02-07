<?php
// /tokoapp/cash_out.php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_access('CASH_OUT');

$user_id  = $_SESSION['user']['id'] ?? null;
$today    = date('Y-m-d');
$from     = $_GET['from'] ?? $today;
$to       = $_GET['to']   ?? $today;
$shift    = isset($_GET['shift']) && $_GET['shift'] !== '' ? (int)$_GET['shift'] : null;

// ====== PRG: proses POST sebelum output ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tanggal = $_POST['tanggal'] ?? $today;
  $shift_p = isset($_POST['shift']) && $_POST['shift'] !== '' ? (int)$_POST['shift'] : null;
  $note    = trim($_POST['note'] ?? '');
  $amount  = max(0, (int)($_POST['amount'] ?? 0));

  if ($amount <= 0) {
    $_SESSION['flash'] = 'Nominal harus lebih dari 0.';
    header("Location: cash_out.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
  }

  // Hanya MANUAL_OUT di sini
  $stmt = $pdo->prepare("
    INSERT INTO cash_ledger (tanggal, shift, user_id, direction, type, amount, note)
    VALUES (:tanggal, :shift, :user_id, 'OUT', 'MANUAL_OUT', :amount, :note)
  ");
  $stmt->execute([
    ':tanggal' => $tanggal,
    ':shift'   => $shift_p,
    ':user_id' => $user_id,
    ':amount'  => $amount,
    ':note'    => $note ?: 'Pengeluaran kas',
  ]);

  $_SESSION['flash'] = 'Pengeluaran kas tersimpan.';
  header("Location: cash_out.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
}

// ====== Ambil data setelah semua kemungkinan redirect ======
require_once __DIR__.'/includes/header.php';

function idr($n){ return number_format((int)$n, 0, ',', '.'); }

// Ledger OUT (MANUAL_OUT)
$sql = "
  SELECT *
  FROM cash_ledger
  WHERE tanggal BETWEEN :from AND :to
    AND direction = 'OUT'
";
$params = [':from'=>$from, ':to'=>$to];
if ($shift !== null) {
  $sql .= " AND (shift = :shift OR shift IS NULL) ";
  $params[':shift'] = $shift;
}
$sql .= " ORDER BY created_at ASC, id ASC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total OUT manual
$total_out_manual = 0;
foreach ($rows as $r) $total_out_manual += (int)$r['amount'];

// Tambahkan OUT dari retur/batal (auto)
$sql_sales_out = "
  SELECT COALESCE(SUM(s.total),0) AS total_out
  FROM sales s
  WHERE DATE(s.created_at) BETWEEN :from AND :to
    AND (s.status IN ('RETURN','BATAL','CANCEL'))
";
$params_out = [':from'=>$from, ':to'=>$to];
if ($shift !== null) { $sql_sales_out .= " AND s.shift = :shift "; $params_out[':shift'] = $shift; }
$stmt_out = $pdo->prepare($sql_sales_out);
$stmt_out->execute($params_out);
$out_sales = (int)($stmt_out->fetchColumn() ?: 0);

$grand_out = $total_out_manual + $out_sales;
?>
<article>
  <h3>Pengeluaran Kas</h3>
  <form method="get" class="no-print" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.8rem;">
    <label>Dari <input type="date" name="from" value="<?=htmlspecialchars($from)?>"></label>
    <label>Sampai <input type="date" name="to" value="<?=htmlspecialchars($to)?>"></label>
    <label>Shift
      <select name="shift">
        <option value="">Semua</option>
        <option value="1" <?= $shift===1?'selected':'' ?>>1</option>
        <option value="2" <?= $shift===2?'selected':'' ?>>2</option>
      </select>
    </label>
    <button type="submit">Terapkan</button>
    <a class="secondary" href="?from=<?=date('Y-m-d')?>&to=<?=date('Y-m-d')?>">Hari Ini</a>
    <button type="button" onclick="window.print()">Print</button>
  </form>

  <?php if(!empty($_SESSION['flash'])): ?>
    <div class="card" style="margin:.6rem 0"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <!-- Form Pengeluaran -->
  <div class="card" style="display:grid;grid-template-columns:repeat(1,minmax(320px,1fr));gap:.8rem;margin-bottom:1rem;">
    <form method="post" style="display:flex;flex-direction:column;gap:.5rem">
      <div><div class="muted">Tanggal</div><input type="date" name="tanggal" value="<?=htmlspecialchars($today)?>"></div>
      <div><div class="muted">Shift</div>
        <select name="shift"><option value="">-</option><option value="1">1</option><option value="2">2</option></select>
      </div>
      <div><div class="muted">Pengeluaran (Rp)</div><input type="number" name="amount" min="0" required></div>
      <div><div class="muted">Keterangan</div><input type="text" name="note" placeholder="mis. beli plastik, parkir, konsumsi, dsb."></div>
      <button class="btn" type="submit">Simpan Pengeluaran</button>
    </form>
  </div>

  <!-- Tabel pengeluaran -->
  <div class="table-wrap">
    <table class="table-small" style="min-width:860px">
      <thead>
        <tr>
          <th>Tanggal/Jam</th><th>Shift</th><th>Jenis</th><th>Keterangan</th><th class="right">Keluar (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="5">Belum ada pengeluaran manual pada periode ini.</td></tr>
        <?php else: foreach($rows as $r): ?>
          <tr>
            <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($r['created_at']))) ?></td>
            <td><?= htmlspecialchars($r['shift'] ?? '-') ?></td>
            <td><?= htmlspecialchars($r['type']) ?></td>
            <td><?= htmlspecialchars($r['note'] ?? '-') ?></td>
            <td class="right"><?= idr($r['amount']) ?></td>
          </tr>
        <?php endforeach; endif; ?>
        <tr>
          <td colspan="4"><em>Retur/Batal (AUTO)</em></td>
          <td class="right"><strong><?= idr($out_sales) ?></strong></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="right">TOTAL PENGELUARAN</th>
          <th class="right"><strong><?= idr($grand_out) ?></strong></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="no-print" style="margin-top:.6rem;">
    <button onclick="window.print()">Print</button>
  </p>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
