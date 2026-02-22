<?php
// /tokoapp/sales_report.php — Laporan Penjualan (OK/RETUR/BATAL/SEMUA) + Aksi Edit (Admin saja)

require_once __DIR__.'/config.php';
require_access('REPORT_SALES');
require_once __DIR__.'/includes/header.php';

// Helper rupiah (hindari redeclare)
if (!function_exists('rupiah')) {
  function rupiah($n){ return number_format((int)$n, 0, ',', '.'); }
}

$is_admin = (($_SESSION['user']['role'] ?? '') === 'admin');

// Filter
$today  = date('Y-m-d');
$from   = $_GET['from']   ?? $today;
$to     = $_GET['to']     ?? $today;
$q      = trim($_GET['q'] ?? '');
$status = strtolower(trim($_GET['status'] ?? 'ok')); // ok|retur|batal|all
$valid_status = ['ok','retur','batal','all'];
if (!in_array($status, $valid_status, true)) $status = 'ok';

// =====================================
// Query utama: JOIN member agar tampil nama
// =====================================
// Asumsi tabel: members(kode,nama). Ganti kalau beda.
$sql = "
  SELECT
    s.*,
    m.nama AS member_nama
  FROM sales s
  LEFT JOIN members m ON m.kode = s.member_kode
  WHERE DATE(s.created_at) BETWEEN :from AND :to
";
$params = [
  ':from' => $from,
  ':to'   => $to,
];

// Filter status
if ($status === 'ok') {
  $sql .= " AND (s.status IS NULL OR s.status='OK')";
} elseif ($status === 'retur') {
  $sql .= " AND s.status='RETURN'";
} elseif ($status === 'batal') {
  $sql .= " AND (s.status='BATAL' OR s.status='CANCEL')";
} // 'all' = tanpa filter tambahan

// Filter pencarian invoice / member (kode / nama)
if ($q !== '') {
  $sql .= " AND (s.invoice_no LIKE :q1 OR s.member_kode LIKE :q2 OR m.nama LIKE :q3)";
  $params[':q1'] = '%'.$q.'%';
  $params[':q2'] = '%'.$q.'%';
  $params[':q3'] = '%'.$q.'%';
}

$sql .= " ORDER BY s.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Total / count
$total_sum  = 0;
foreach ($rows as $r) {
  $total_sum += (int)($r['total'] ?? 0);
}
$count_rows = count($rows);

// Ringkas badge per status
function count_by_status($pdo, $from, $to, $where) {
  // NOTE: ikut join members biar WHERE m.nama (kalau dipakai) tidak error di masa depan
  $q = "
    SELECT COUNT(*)
    FROM sales s
    LEFT JOIN members m ON m.kode = s.member_kode
    WHERE DATE(s.created_at) BETWEEN :from AND :to
    ".$where."
  ";
  $st = $pdo->prepare($q);
  $st->execute([':from'=>$from, ':to'=>$to]);
  return (int)$st->fetchColumn();
}

$cnt_ok    = count_by_status($pdo, $from, $to, "AND (s.status IS NULL OR s.status='OK')");
$cnt_retur = count_by_status($pdo, $from, $to, "AND s.status='RETURN'");
$cnt_batal = count_by_status($pdo, $from, $to, "AND (s.status='BATAL' OR s.status='CANCEL')");
$cnt_all   = $cnt_ok + $cnt_retur + $cnt_batal;

// Helper UI
function pill_link($label, $key, $active, $from, $to, $q, $count, $clsExtra=''){
  $qs = http_build_query([
    'from'   => $from,
    'to'     => $to,
    'status' => $key,
    'q'      => $q
  ]);
  $cls = 'pill '.($active ? 'active ' : '').$clsExtra;
  $badge = $count !== null ? '<span class="badge">'.(int)$count.'</span>' : '';
  return '<a class="'.$cls.'" href="?'.$qs.'"><span class="dot"></span>'.$label.$badge.'</a>';
}
?>
<style>
  :root{
    --bg:#0b1220; --card:#0e1726; --bd:#1f2a3a; --txt:#e2e8f0; --muted:#9bb0c9;
    --accent:#7dd3fc; --ok:#10b981; --retur:#f59e0b; --batal:#ef4444;
    --input-bg:#091120; --input-bd:#263243;
  }
  [data-theme="light"] {
    --bg:#f1f5f9; --card:#ffffff; --bd:#cbd5e1; --txt:#0f172a; --muted:#475569;
    --accent:#0284c7; --input-bg:#ffffff; --input-bd:#94a3b8;
  }
  article{padding:.5rem .2rem}
  h3{margin:.2rem 0 .6rem;font-size:1.05rem}

  .toolbar{
    display:flex;flex-wrap:wrap;gap:.6rem;margin:.4rem 0 .9rem;align-items:end
  }
  .toolbar label{display:flex;flex-direction:column;font-size:.85rem;color:var(--txt);font-weight:600}
  .toolbar input,.toolbar select{
    background:var(--input-bg);border:1px solid var(--input-bd);color:var(--txt);
    border-radius:.45rem;padding:.46rem .55rem;min-width:12ch
  }
  .toolbar button,.toolbar a.secondary{
    padding:.5rem .7rem;border-radius:.5rem;border:1px solid #2c3c55;background:#1f2b3e;color:var(--txt);
    text-decoration:none;cursor:pointer
  }

  .tabs{display:flex;flex-wrap:wrap;gap:.5rem;margin:-.2rem 0 .8rem}
  .pill{
    display:inline-flex;align-items:center;gap:.45rem;
    background:var(--card);border:1px solid var(--bd);border-radius:999px;
    padding:.35rem .7rem;text-decoration:none;color:var(--txt);font-size:.86rem
  }
  .pill .dot{width:.5rem;height:.5rem;border-radius:999px;background:#64748b;display:inline-block}
  .pill.active{background:var(--accent);border-color:var(--accent);color:#fff}
  [data-theme="dark"] .pill.active{background:#12223b;border-color:#375986;}
  .pill.ok   .dot{background:var(--ok)}
  .pill.retur .dot{background:var(--retur)}
  .pill.batal .dot{background:var(--batal)}
  .pill .badge{
    background:var(--bg);border:1px solid var(--bd);border-radius:999px;
    padding:.02rem .4rem;font-size:.72rem;color:var(--txt)
  }

  .info{opacity:.85;margin:.25rem 0 .7rem}

  .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:.6rem}
  table{width:100%;border-collapse:collapse;font-size:.92rem;min-width:980px}
  th,td{padding:.6rem .7rem;border-bottom:1px solid var(--bd)}
  thead th{position:sticky;top:0;background:var(--bg);z-index:1}
  tbody tr:nth-child(odd){background:rgba(0,0,0,0.02)}
  [data-theme="dark"] tbody tr:nth-child(odd){background:#0b1324}
  tbody tr:hover{background:rgba(0,0,0,0.05)}
  [data-theme="dark"] tbody tr:hover{background:#0b1a34}
  .right{text-align:right}

  .status{
    font-size:.78rem;display:inline-flex;align-items:center;gap:.35rem;
    padding:.1rem .45rem;border-radius:.45rem;border:1px solid var(--bd);background:var(--card)
  }
  .status.ok{color:#059669;background:#dbfde6;border-color:#a7f3d0}
  .status.retur{color:#92400e;background:#fef3c7;border-color:#fde68a}
  .status.batal{color:#991b1b;background:#fee2e2;border-color:#fecaca}
  [data-theme="dark"] .status.ok{color:#a7f3d0;border-color:#194a3b;background:#0c1e1a}
  [data-theme="dark"] .status.retur{color:#fde68a;border-color:#5a471c;background:#1b1608}
  [data-theme="dark"] .status.batal{color:#fecaca;border-color:#5c1f28;background:#1f0c12}

  .btn-inline{
    display:inline-flex;align-items:center;gap:.35rem;
    background:#1f2b3e;border:1px solid #2c3c55;color:var(--txt);
    padding:.35rem .55rem;border-radius:.45rem;text-decoration:none
  }
  .btn-inline:hover{background:#24344c}
  .btn-danger{background:#3b1f25;border-color:#5b2830}
  .btn-danger:hover{background:#4a2430}
  .btn-warning{background:#3a321b;border-color:#5a4d27}
  .btn-warning:hover{background:#4a4224}

  @media print{
    .no-print{display:none!important}
    body{background:#fff;color:#000}
    thead th{background:#eee;border-color:#bbb}
    .status{border-color:#888;background:#fff;color:#000}
  }
</style>

<article>
  <h3>Laporan Penjualan</h3>

  <!-- Toolbar -->
  <form method="get" class="no-print toolbar">
    <label>Dari
      <input type="date" name="from" value="<?= htmlspecialchars($from) ?>">
    </label>
    <label>Sampai
      <input type="date" name="to" value="<?= htmlspecialchars($to) ?>">
    </label>
    <label>Status
      <select name="status">
        <option value="ok"    <?= $status==='ok'?'selected':''    ?>>OK</option>
        <option value="retur" <?= $status==='retur'?'selected':'' ?>>RETUR</option>
        <option value="batal" <?= $status==='batal'?'selected':'' ?>>BATAL</option>
        <option value="all"   <?= $status==='all'?'selected':''   ?>>SEMUA</option>
      </select>
    </label>
    <label>Cari Invoice / Member
      <input type="text" name="q" placeholder="mis. S202511070930 / nama member" value="<?= htmlspecialchars($q) ?>">
    </label>
    <button type="submit">Tampilkan</button>
    <a class="secondary" href="?from=<?= htmlspecialchars($today) ?>&to=<?= htmlspecialchars($today) ?>&status=ok">Reset Hari Ini</a>
    <button type="button" onclick="window.print()">Print</button>
  </form>

  <!-- Tabs -->
  <div class="tabs no-print">
    <?= pill_link('OK', 'ok', $status==='ok', $from, $to, $q, $cnt_ok, 'ok'); ?>
    <?= pill_link('RETUR', 'retur', $status==='retur', $from, $to, $q, $cnt_retur, 'retur'); ?>
    <?= pill_link('BATAL', 'batal', $status==='batal', $from, $to, $q, $cnt_batal, 'batal'); ?>
    <?= pill_link('SEMUA', 'all', $status==='all', $from, $to, $q, $cnt_all); ?>
  </div>

  <p class="info">
    Periode: <strong><?= htmlspecialchars(date('d-m-Y', strtotime($from))) ?></strong>
    s.d. <strong><?= htmlspecialchars(date('d-m-Y', strtotime($to))) ?></strong>
    — Status: <strong><?= strtoupper($status) ?></strong>
    — Hasil: <strong><?= (int)$count_rows ?></strong> transaksi
  </p>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Tanggal</th>
          <th>Invoice</th>
          <th>Nama Member</th>
          <th>Shift</th>
          <th class="right">Total</th>
          <th>Status</th>
          <th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="7">Tidak ada penjualan.</td></tr>
      <?php else: ?>
        <?php foreach($rows as $r):
          $st = $r['status'] ?? 'OK';
          $is_ok_row = ($st === 'OK' || $st === null);
          $label = ($st==='CANCEL') ? 'BATAL' : ($st ?: 'OK');
          $st_class = 'ok';
          if ($label === 'BATAL') $st_class = 'batal';
          if ($label === 'RETURN' || $label === 'RETUR') $st_class = 'retur';

          $can_edit = $is_admin && $is_ok_row;

          // tampilkan nama kalau ada, fallback ke kode, fallback '-'
          $member_name = trim((string)($r['member_nama'] ?? ''));
          if ($member_name === '') $member_name = trim((string)($r['member_kode'] ?? ''));
          if ($member_name === '') $member_name = '-';
        ?>
        <tr>
          <td><?= date('d-m-Y H:i', strtotime($r['created_at'])) ?></td>
          <td><?= htmlspecialchars($r['invoice_no']) ?></td>
          <td><?= htmlspecialchars($member_name) ?></td>
          <td><?= htmlspecialchars($r['shift'] ?? '-') ?></td>
          <td class="right"><?= rupiah((int)($r['total'] ?? 0)) ?></td>
          <td><span class="status <?= $st_class ?>"><?= htmlspecialchars($label==='RETURN'?'RETUR':$label) ?></span></td>
          <td class="no-print" style="white-space:nowrap;display:flex;gap:.45rem;">
            <a class="btn-inline" href="/tokoapp/sale_print.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">Cetak</a>

            <?php if($can_edit): ?>
              <a class="btn-inline btn-warning" href="/tokoapp/sale_edit.php?id=<?= (int)$r['id'] ?>">Edit</a>
            <?php endif; ?>

            <?php if($is_admin && $is_ok_row): ?>
              <a class="btn-inline" href="/tokoapp/sale_return.php?id=<?= (int)$r['id'] ?>"
                 onclick="return confirm('Retur penuh transaksi ini? stok akan dikembalikan.');">Retur</a>
              <a class="btn-inline btn-danger" href="/tokoapp/sale_cancel.php?id=<?= (int)$r['id'] ?>"
                 onclick="return confirm('Batalkan transaksi ini? stok akan dikembalikan.');">Batal</a>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="4" class="right">TOTAL</th>
          <th class="right"><?= rupiah($total_sum) ?></th>
          <th colspan="2"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</article>

<?php include __DIR__.'/includes/footer.php'; ?>
