<?php
// /tokoapp/cashier_cash.php (Versi UI Rapi)
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();
require_role(['admin','kasir']); // akses halaman

$user_id   = $_SESSION['user']['id']   ?? null;
$user_role = $_SESSION['user']['role'] ?? null;
$is_admin  = ($user_role === 'admin');

function idr($n){ return number_format((int)$n, 0, ',', '.'); }
function allow_type($t){ return in_array($t, ['OPENING','MANUAL_IN','MANUAL_OUT'], true); }

// ====== FILTER (GET) ======
$today = date('Y-m-d');
$from  = $_GET['from'] ?? $today;
$to    = $_GET['to']   ?? $today;
$shift = isset($_GET['shift']) && $_GET['shift'] !== '' ? (int)$_GET['shift'] : null;

// ====== CSRF ======
if (empty($_SESSION['csrf'])) {
  $_SESSION['csrf'] = bin2hex(random_bytes(16));
}
$CSRF = $_SESSION['csrf'];

// ====== PRG: Tangani POST sebelum output ======
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  if (empty($_POST['csrf']) || $_POST['csrf'] !== $_SESSION['csrf']) {
    $_SESSION['flash'] = 'Sesi tidak valid. Muat ulang halaman.';
    header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
  }

  $action  = $_POST['action'] ?? '';
  $tanggal = $_POST['tanggal'] ?? $today;
  $shift_p = isset($_POST['shift']) && $_POST['shift'] !== '' ? (int)$_POST['shift'] : null;
  $note    = trim($_POST['note'] ?? '');
  $amount  = max(0, (int)($_POST['amount'] ?? 0));
  $id      = isset($_POST['id']) ? (int)$_POST['id'] : null;

  // Tambah
  if (in_array($action, ['opening','manual_in','manual_out'], true)) {
    if ($amount <= 0) {
      $_SESSION['flash'] = 'Nominal harus lebih dari 0.';
      header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
    }
    $map = [
      'opening'    => ['direction'=>'IN' , 'type'=>'OPENING'   , 'def_note'=>'Modal kasir'],
      'manual_in'  => ['direction'=>'IN' , 'type'=>'MANUAL_IN' , 'def_note'=>'Penerimaan lain'],
      'manual_out' => ['direction'=>'OUT', 'type'=>'MANUAL_OUT', 'def_note'=>'Pengeluaran kas'],
    ];
    $direction = $map[$action]['direction'];
    $type      = $map[$action]['type'];
    $def_note  = $map[$action]['def_note'];

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
      ':note'      => $note ?: $def_note,
    ]);
    $_SESSION['flash'] = 'Transaksi kas tersimpan.';
    header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
  }

  // Update
  if ($action === 'update' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM cash_ledger WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row){ $_SESSION['flash']='Data tidak ditemukan.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }
    if (!allow_type($row['type'])){ $_SESSION['flash']='Data ini tidak dapat diedit.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }
    if (!$is_admin && (int)$row['user_id'] !== (int)$user_id){ $_SESSION['flash']='Anda tidak berhak mengedit entri ini.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }
    if ($amount <= 0){ $_SESSION['flash']='Nominal harus lebih dari 0.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }

    $stmt = $pdo->prepare("
      UPDATE cash_ledger
         SET tanggal=:tanggal, shift=:shift, amount=:amount, note=:note
       WHERE id=:id
    ");
    $stmt->execute([
      ':tanggal'=>$tanggal, ':shift'=>$shift_p, ':amount'=>$amount, ':note'=>$note, ':id'=>$id
    ]);
    $_SESSION['flash'] = 'Transaksi kas diperbarui.';
    header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
  }

  // Delete
  if ($action === 'delete' && $id) {
    $stmt = $pdo->prepare("SELECT * FROM cash_ledger WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row){ $_SESSION['flash']='Data tidak ditemukan.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }
    if (!allow_type($row['type'])){ $_SESSION['flash']='Data ini tidak dapat dihapus.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }
    if (!$is_admin && (int)$row['user_id'] !== (int)$user_id){ $_SESSION['flash']='Anda tidak berhak menghapus entri ini.'; header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit; }

    $pdo->prepare("DELETE FROM cash_ledger WHERE id=:id")->execute([':id'=>$id]);
    $_SESSION['flash'] = 'Transaksi kas dihapus.';
    header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
  }

  $_SESSION['flash'] = 'Aksi tidak dikenal.';
  header("Location: cashier_cash.php?from={$from}&to={$to}".($shift!==null?"&shift={$shift}":'')); exit;
}

// ====== Setelah PRG: Query data ======
require_once __DIR__.'/includes/header.php';

// Ledger manual
$sql_led = "
  SELECT *
  FROM cash_ledger
  WHERE tanggal BETWEEN :from AND :to
";
$params_led = [':from'=>$from, ':to'=>$to];
if ($shift !== null) { $sql_led .= " AND (shift = :shift OR shift IS NULL) "; $params_led[':shift'] = $shift; }
$sql_led .= " ORDER BY created_at ASC, id ASC";
$stmt_led = $pdo->prepare($sql_led); $stmt_led->execute($params_led);
$ledger_rows = $stmt_led->fetchAll(PDO::FETCH_ASSOC);

// Sales in
$sql_in = "
  SELECT COALESCE(SUM(s.total),0) AS total_in
  FROM sales s
  WHERE DATE(s.created_at) BETWEEN :from AND :to
    AND (s.status IS NULL OR s.status = 'OK')
";
$params_in = [':from'=>$from, ':to'=>$to];
if ($shift !== null) { $sql_in .= " AND s.shift = :shift "; $params_in[':shift'] = $shift; }
$stmt_in = $pdo->prepare($sql_in); $stmt_in->execute($params_in);
$in_sales = (int)($stmt_in->fetchColumn() ?: 0);

// Sales out
$sql_out = "
  SELECT COALESCE(SUM(s.total),0) AS total_out
  FROM sales s
  WHERE DATE(s.created_at) BETWEEN :from AND :to
    AND (s.status IN ('RETURN','BATAL','CANCEL'))
";
$params_out = [':from'=>$from, ':to'=>$to];
if ($shift !== null) { $sql_out .= " AND s.shift = :shift "; $params_out[':shift'] = $shift; }
$stmt_out = $pdo->prepare($sql_out); $stmt_out->execute($params_out);
$out_sales = (int)($stmt_out->fetchColumn() ?: 0);

// Ringkasan
$opening=0; $manual_in=0; $manual_out=0;
foreach ($ledger_rows as $lr) {
  if ($lr['type']==='OPENING'   && $lr['direction']==='IN')  $opening    += (int)$lr['amount'];
  if ($lr['type']==='MANUAL_IN' && $lr['direction']==='IN')  $manual_in  += (int)$lr['amount'];
  if ($lr['type']==='MANUAL_OUT'&& $lr['direction']==='OUT') $manual_out += (int)$lr['amount'];
}
$kas_masuk  = $opening + $manual_in + $in_sales;
$kas_keluar = $manual_out + $out_sales;
$saldo      = $kas_masuk - $kas_keluar;

// Edit state
$edit_id  = isset($_GET['edit']) ? (int)$_GET['edit'] : null;
$edit_row = null;
if ($edit_id) {
  $s = $pdo->prepare("SELECT * FROM cash_ledger WHERE id=:id");
  $s->execute([':id'=>$edit_id]);
  $tmp = $s->fetch(PDO::FETCH_ASSOC);
  if ($tmp && allow_type($tmp['type']) && ($is_admin || (int)$tmp['user_id']===(int)$user_id)) {
    $edit_row = $tmp;
  }
}
?>
<style>
  :root{
    --gap:.75rem;
    --card-bg:#0e1726;
    --card-bd:#1f2a3a;
    --page-bg:#0b1220;
    --text:#e2e8f0;
    --muted:#9bb0c9;
    --accent:#7dd3fc;
    --accent-2:#34d399;
    --danger:#f87171;
    --warn:#fbbf24;
  }
  .toolbar{
    display:flex;flex-wrap:wrap;gap:.5rem;margin:.6rem 0 .8rem;
    align-items:end
  }
  .toolbar label{display:flex;flex-direction:column;font-size:.8rem;color:var(--muted)}
  .toolbar input,.toolbar select{background:#091120;border:1px solid #263243;color:var(--text);border-radius:.45rem;padding:.45rem .55rem}
  .toolbar .btn{background:#1f2b3e;border:1px solid #2c3c55;color:var(--text);padding:.5rem .75rem;border-radius:.5rem;cursor:pointer}
  .toolbar a.secondary{padding:.46rem .6rem;border:1px solid #2b3952;border-radius:.45rem;color:#cbd5e1;text-decoration:none}

  .pill-cards{display:grid;grid-template-columns:repeat(5,minmax(160px,1fr));gap:.6rem;margin-bottom:.8rem}
  .pill{background:var(--card-bg);border:1px solid var(--card-bd);border-radius:.75rem;padding:.6rem .7rem}
  .pill .lbl{font-size:.78rem;color:var(--muted)}
  .pill .val{font-weight:800;font-size:1.05rem}
  .pill.accent .val{color:#93c5fd}
  .pill.out .val{color:#fca5a5}

  .saldo-sticky{
    position:sticky;top:.5rem;z-index:1;
    background:linear-gradient(180deg,#0b1220,#0e1726);
    border:1px solid var(--card-bd);border-radius:.7rem;
    padding:.55rem .75rem;margin-bottom:.8rem;display:flex;justify-content:space-between;align-items:center
  }
  .saldo-sticky .val{font-family:ui-monospace,Consolas,Menlo,monospace;font-size:1.5rem;font-weight:900;color:var(--accent)}

  .grid-forms{
    display:grid;grid-template-columns:repeat(3,minmax(260px,1fr));gap:.8rem;margin-bottom:1rem
  }
  .card{background:var(--card-bg);border:1px solid var(--card-bd);border-radius:.7rem;padding:.75rem}
  .muted{font-size:.8rem;color:var(--muted)}

  .card form .row{display:flex;flex-direction:column;gap:.35rem}
  .card input,.card select{background:#091120;border:1px solid #263243;color:var(--text);border-radius:.45rem;padding:.5rem .55rem;width:100%}
  .btn{background:#1f2b3e;border:1px solid #2c3c55;color:var(--text);padding:.55rem .75rem;border-radius:.5rem;cursor:pointer}
  .btn:active{transform:translateY(1px)}
  .btn.danger{background:#3b1f25;border-color:#5b2830}
  .btn.success{background:#1f2f28;border-color:#2c4c3f}
  .btn.small{padding:.3rem .5rem;font-size:.78rem;border-radius:.4rem}

  .table-wrap{overflow:auto;border-radius:.6rem;border:1px solid var(--card-bd)}
  table{width:100%;border-collapse:collapse;font-size:.9rem;min-width:980px}
  th,td{border-bottom:1px solid #223047;padding:.55rem .6rem}
  thead th{position:sticky;top:0;background:#0f1a2c;z-index:1}
  tbody tr:nth-child(odd){background:#0b1324}
  tbody tr:hover{background:#0b1a34}
  .right{text-align:right}
  .no-print{ }

  .tag{display:inline-block;font-size:.72rem;padding:.06rem .35rem;border:1px solid #2b3952;border-radius:.35rem;background:#0c1424;color:#a3c6ff}
  .flash{background:#0f1a2c;border:1px solid #223047;border-radius:.6rem;padding:.5rem .65rem;margin:.6rem 0}
  @media (max-width:1120px){ .pill-cards{grid-template-columns:repeat(3,1fr)} .grid-forms{grid-template-columns:1fr} }
  @media (max-width:680px){ .pill-cards{grid-template-columns:repeat(2,1fr)} }
</style>

<article>
  <h3 style="margin:.4rem 0 .2rem">Buku Kas Kasir</h3>

  <!-- Toolbar filter -->
  <form method="get" class="toolbar no-print">
    <label>Dari
      <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
    </label>
    <label>Sampai
      <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
    </label>
    <label>Shift
      <select name="shift">
        <option value="">Semua</option>
        <option value="1" <?= $shift===1?'selected':'' ?>>1</option>
        <option value="2" <?= $shift===2?'selected':'' ?>>2</option>
      </select>
    </label>
    <button class="btn" type="submit">Terapkan</button>
    <a class="secondary" href="?from=<?=$today?>&to=<?=$today?>">Hari Ini</a>
    <button class="btn" type="button" onclick="window.print()">Print</button>
  </form>

  <?php if(!empty($_SESSION['flash'])): ?>
    <div class="flash"><?= htmlspecialchars($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
  <?php endif; ?>

  <!-- Pill summary -->
  <div class="pill-cards">
    <div class="pill"><div class="lbl">Modal (Opening)</div><div class="val"><?=idr($opening)?></div></div>
    <div class="pill"><div class="lbl">Penerimaan Lain</div><div class="val"><?=idr($manual_in)?></div></div>
    <div class="pill accent"><div class="lbl">Penjualan (Auto)</div><div class="val"><?=idr($in_sales)?></div></div>
    <div class="pill out"><div class="lbl">Pengeluaran</div><div class="val"><?=idr($manual_out)?></div></div>
    <div class="pill out"><div class="lbl">Retur/Batal (Auto)</div><div class="val"><?=idr($out_sales)?></div></div>
  </div>

  <!-- Saldo -->
  <div class="saldo-sticky">
    <div style="font-weight:700">Saldo Kas</div>
    <div class="val"><?=idr($saldo)?></div>
  </div>

  <!-- Forms -->
  <div class="grid-forms">
    <!-- Opening -->
    <div class="card">
      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="action" value="opening">
        <div class="muted">Tanggal</div>
        <input type="date" name="tanggal" value="<?=htmlspecialchars($today)?>">
        <div class="muted">Shift</div>
        <select name="shift"><option value="">-</option><option value="1">1</option><option value="2">2</option></select>
        <div class="muted">Modal Kasir (Rp)</div>
        <input type="number" name="amount" min="0" required>
        <div class="muted">Keterangan</div>
        <input type="text" name="note" placeholder="mis. modal awal laci">
        <button class="btn success" type="submit">Simpan Opening</button>
      </form>
    </div>

    <!-- Penerimaan lain -->
    <div class="card">
      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="action" value="manual_in">
        <div class="muted">Tanggal</div>
        <input type="date" name="tanggal" value="<?=htmlspecialchars($today)?>">
        <div class="muted">Shift</div>
        <select name="shift"><option value="">-</option><option value="1">1</option><option value="2">2</option></select>
        <div class="muted">Penerimaan Lain (Rp)</div>
        <input type="number" name="amount" min="0" required>
        <div class="muted">Keterangan</div>
        <input type="text" name="note" placeholder="mis. titipan / topup kas">
        <button class="btn" type="submit">Simpan Penerimaan</button>
      </form>
    </div>

    <!-- Edit (conditional) -->
    <div class="card" style="<?= $edit_row?'':'display:none' ?>">
      <form method="post" class="row">
        <input type="hidden" name="csrf" value="<?=$CSRF?>">
        <input type="hidden" name="action" value="update">
        <input type="hidden" name="id" value="<?= $edit_row['id'] ?? '' ?>">
        <div class="muted">Edit #<?= $edit_row['id'] ?? '' ?> <span class="tag"><?= htmlspecialchars($edit_row['type'] ?? '') ?></span></div>
        <div class="muted">Tanggal</div>
        <input type="date" name="tanggal" value="<?= htmlspecialchars($edit_row['tanggal'] ?? $today) ?>">
        <div class="muted">Shift</div>
        <select name="shift">
          <option value="" <?= (($edit_row['shift'] ?? '')===''?'selected':'')?>>-</option>
          <option value="1" <?= (($edit_row['shift'] ?? null)==1?'selected':'')?>>1</option>
          <option value="2" <?= (($edit_row['shift'] ?? null)==2?'selected':'')?>>2</option>
        </select>
        <div class="muted">Nominal (Rp)</div>
        <input type="number" name="amount" min="0" value="<?= (int)($edit_row['amount'] ?? 0) ?>">
        <div class="muted">Keterangan</div>
        <input type="text" name="note" value="<?= htmlspecialchars($edit_row['note'] ?? '') ?>">
        <div style="display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.35rem">
          <button class="btn" type="submit">Simpan Perubahan</button>
          <a class="btn small" href="cashier_cash.php?from=<?=$from?>&to=<?=$to?><?= $shift!==null?"&shift={$shift}":'' ?>">Batal</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Tabel -->
  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tanggal/Jam</th>
          <th>Shift</th>
          <th>Jenis</th>
          <th>Keterangan</th>
          <th class="right">Masuk (Rp)</th>
          <th class="right">Keluar (Rp)</th>
          <th class="right">Saldo</th>
          <th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
        <?php
        $running = 0;
        if ($ledger_rows) {
          foreach ($ledger_rows as $lr) {
            $in  = $lr['direction']==='IN'  ? (int)$lr['amount'] : 0;
            $out = $lr['direction']==='OUT' ? (int)$lr['amount'] : 0;
            $running += ($in - $out);
            $can_act = allow_type($lr['type']) && ($is_admin || (int)$lr['user_id']===(int)$user_id);
            ?>
            <tr>
              <td><?= htmlspecialchars(date('d-m-Y H:i', strtotime($lr['created_at']))) ?></td>
              <td><?= htmlspecialchars($lr['shift'] ?? '-') ?></td>
              <td><span class="tag"><?= htmlspecialchars($lr['type']) ?></span></td>
              <td><?= htmlspecialchars($lr['note'] ?? '-') ?></td>
              <td class="right"><?= idr($in) ?></td>
              <td class="right"><?= idr($out) ?></td>
              <td class="right"><?= idr($running) ?></td>
              <td class="no-print" style="white-space:nowrap">
                <?php if ($can_act): ?>
                  <a class="btn small" href="cashier_cash.php?from=<?=htmlspecialchars($from)?>&to=<?=htmlspecialchars($to)?><?= $shift!==null?('&shift='.$shift):'' ?>&edit=<?= (int)$lr['id'] ?>">Edit</a>
                  <form method="post" onsubmit="return confirm('Hapus entri kas ini?');" style="display:inline">
                    <input type="hidden" name="csrf" value="<?=$CSRF?>">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= (int)$lr['id'] ?>">
                    <button class="btn small danger" type="submit">Hapus</button>
                  </form>
                <?php else: ?>
                  <span class="muted">-</span>
                <?php endif; ?>
              </td>
            </tr>
            <?php
          }
        } else {
          echo '<tr><td colspan="8">Belum ada transaksi kas manual pada periode ini.</td></tr>';
        }
        ?>
        <tr>
          <td colspan="3"><em>Penjualan (AUTO)</em></td>
          <td><em>Akumulasi dari tabel penjualan</em></td>
          <td class="right"><strong><?= idr($in_sales) ?></strong></td>
          <td class="right">0</td>
          <td class="right"><?= idr($running + $in_sales) ?></td>
          <td></td>
        </tr>
        <tr>
          <td colspan="3"><em>Retur/Batal (AUTO)</em></td>
          <td><em>Akumulasi dari tabel penjualan</em></td>
          <td class="right">0</td>
          <td class="right"><strong><?= idr($out_sales) ?></strong></td>
          <td class="right"><?= idr($running + $in_sales - $out_sales) ?></td>
          <td></td>
        </tr>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="right">Total Masuk</th>
          <th class="right"><?= idr($kas_masuk) ?></th>
          <th class="right"><?= idr($kas_keluar) ?></th>
          <th class="right"><strong><?= idr($saldo) ?></strong></th>
          <th></th>
        </tr>
      </tfoot>
    </table>
  </div>

  <p class="no-print" style="margin-top:.6rem;">
    <button class="btn" onclick="window.print()">Print</button>
  </p>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
