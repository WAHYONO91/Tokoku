<?php
// /tokoapp/cash_in.php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_access('CASH_IN');

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
  $action  = $_POST['action'] ?? ''; // 'opening' atau 'manual_in'

  if ($amount <= 0) {
    $_SESSION['flash'] = 'Nominal harus lebih dari 0.';
    header("Location: cash_in.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":''));
    exit;
  }

  if (!in_array($action, ['opening','manual_in'], true)) {
    $_SESSION['flash'] = 'Aksi tidak valid.';
    header("Location: cash_in.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":''));
    exit;
  }

  $type      = ($action === 'opening') ? 'OPENING' : 'MANUAL_IN';
  $direction = 'IN';

  $stmt = $pdo->prepare("
    INSERT INTO cash_ledger (tanggal, shift, user_id, direction, type, amount, note)
    VALUES (:tanggal, :shift, :user_id, :direction, :type, :amount, :note)
  ");
  $stmt->execute([
    ':tanggal'   => $tanggal,
    ':shift'     => $shift_p,
    ':user_id'   => $user_id,
    ':direction' => $direction,
    ':type'      => $type,
    ':amount'    => $amount,
    ':note'      => $note ?: ($type==='OPENING'?'Modal kasir':'Penerimaan lain'),
  ]);

  $_SESSION['flash'] = 'Penerimaan kas tersimpan.';
  header("Location: cash_in.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":''));
  exit;
}

// ====== Ambil data setelah semua kemungkinan redirect ======
require_once __DIR__.'/includes/header.php';

function idr($n){ return number_format((int)$n, 0, ',', '.'); }

// Ledger IN (OPENING, MANUAL_IN)
$sql = "
  SELECT *
  FROM cash_ledger
  WHERE tanggal BETWEEN :from AND :to
    AND direction = 'IN'
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

// Total IN dari ledger manual
$total_in_manual = 0;
foreach ($rows as $r) $total_in_manual += (int)$r['amount'];

// Tambahkan IN dari penjualan (auto)
$sql_sales_in = "
  SELECT COALESCE(SUM(s.total),0) AS total_in
  FROM sales s
  WHERE DATE(s.created_at) BETWEEN :from AND :to
    AND (s.status IS NULL OR s.status = 'OK')
";
$params_in = [':from'=>$from, ':to'=>$to];
if ($shift !== null) { $sql_sales_in .= " AND s.shift = :shift "; $params_in[':shift'] = $shift; }
$stmt_in = $pdo->prepare($sql_sales_in);
$stmt_in->execute($params_in);
$in_sales = (int)($stmt_in->fetchColumn() ?: 0);

$grand_in = $total_in_manual + $in_sales;
?>
<article>
  <h3>Penerimaan Kas</h3>
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

  <!-- Form Opening & Penerimaan -->
  <div class="card" style="display:grid;grid-template-columns:repeat(2,minmax(260px,1fr));gap:.8rem;margin-bottom:1rem;">
    <form method="post" style="display:flex;flex-direction:column;gap:.5rem">
      <input type="hidden" name="action" value="opening">
      <div><div class="muted">Tanggal</div><input type="date" name="tanggal" value="<?=htmlspecialchars($today)?>"></div>
      <div><div class="muted">Shift</div>
        <select name="shift"><option value="">-</option><option value="1">1</option><option value="2">2</option></select>
      </div>
      <div><div class="muted">Modal/Opening (Rp)</div><input type="number" name="amount" min="0" required></div>
      <div><div class="muted">Keterangan</div><input type="text" name="note" placeholder="mis. modal awal laci"></div>
      <button class="btn" type="submit">Simpan Opening</button>
    </form>

    <form method="post" style="display:flex;flex-direction:column;gap:.5rem">
      <input type="hidden" name="action" value="manual_in">
      <div><div class="muted">Tanggal</div><input type="date" name="tanggal" value="<?=htmlspecialchars($today)?>"></div>
      <div><div class="muted">Shift</div>
        <select name="shift"><option value="">-</option><option value="1">1</option><option value="2">2</option></select>
      </div>
      <div><div class="muted">Penerimaan Lain (Rp)</div><input type="number" name="amount" min="0" required></div>
      <div><div class="muted">Keterangan</div><input type="text" name="note" placeholder="mis. titipan, topup kas"></div>
      <button class="btn" type="submit">Simpan Penerimaan</button>
    </form>
  </div>

  <!-- Tabel penerimaan -->
  <div class="table-wrap">
    <table class="table-small" style="min-width:860px">
      <thead>
        <tr>
          <th>Tanggal/Jam</th><th>Shift</th><th>Jenis</th><th>Keterangan</th><th class="right">Masuk (Rp)</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="5">Belum ada penerimaan manual pada periode ini.</td></tr>
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
          <td colspan="4"><em>Penjualan (AUTO)</em></td>
          <td class="right"><strong><?= idr($in_sales) ?></strong></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="right">TOTAL PENERIMAAN</th>
          <th class="right"><strong><?= idr($grand_in) ?></strong></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="no-print" style="margin-top:.6rem;">
    <button onclick="window.print()">Print</button>
  </p>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
