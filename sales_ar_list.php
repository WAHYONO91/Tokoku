<?php
// /tokoapp/sales_ar_list.php — Daftar Piutang Sales + Pelunasan Cepat

require_once __DIR__.'/config.php';
require_access('PIUTANG');
require_once __DIR__.'/includes/header.php';

// ====== Filter ======
$today   = date('Y-m-d');
$from    = $_GET['from']    ?? $today;           // filter due_date dari
$to      = $_GET['to']      ?? $today;           // filter due_date sampai
$status  = $_GET['status']  ?? 'OPEN';           // OPEN|PARTIAL|PAID|ALL
$supp    = trim($_GET['supplier'] ?? '');        // kode supplier (opsional)
$q       = trim($_GET['q'] ?? '');               // cari invoice / supplier_nama

$statuses = ['OPEN','PARTIAL','PAID','ALL'];
if (!in_array($status, $statuses, true)) $status = 'OPEN';

// Ambil master supplier untuk dropdown
$suppliers = $pdo->query("SELECT kode, nama FROM suppliers ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

// ====== Build SQL ======
$sql = "
  SELECT
    ar.id,
    ar.supplier_kode,
    s.nama AS supplier_nama,
    ar.purchase_id,
    p.invoice_no,
    ar.amount,            -- total piutang awal
    ar.due_date,
    ar.status,
    ar.created_at,
    COALESCE(SUM(pay.amount),0) AS paid_amount,
    (ar.amount - COALESCE(SUM(pay.amount),0)) AS remain_amount
  FROM sales_ar ar
  JOIN purchases p ON p.id = ar.purchase_id
  LEFT JOIN suppliers s ON s.kode = ar.supplier_kode
  LEFT JOIN ar_payments pay ON pay.ar_id = ar.id
  WHERE DATE(ar.due_date) BETWEEN :from AND :to
";
$params = [':from'=>$from, ':to'=>$to];

// Status filter
if ($status !== 'ALL') {
  $sql .= " AND ar.status = :status ";
  $params[':status'] = $status;
}

// Supplier filter
if ($supp !== '') {
  $sql .= " AND ar.supplier_kode = :supp ";
  $params[':supp'] = $supp;
}

// Keyword filter
if ($q !== '') {
  $sql .= " AND (p.invoice_no LIKE :q OR s.nama LIKE :q) ";
  $params[':q'] = '%'.$q.'%';
}

$sql .= "
  GROUP BY ar.id, ar.supplier_kode, s.nama, ar.purchase_id, p.invoice_no, ar.amount, ar.due_date, ar.status, ar.created_at
  ORDER BY ar.due_date ASC, ar.created_at DESC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ringkasan
$total_piutang = 0;
$total_remain  = 0;
foreach($rows as $r){
  $total_piutang += (int)$r['amount'];
  $total_remain  += max(0, (int)$r['remain_amount']);
}

// Helper
if (!function_exists('rupiah')) {
  function rupiah($n){ return number_format((int)$n, 0, ',', '.'); }
}
?>
<style>
  :root{
    --bd:#223047; --card:#0e1726; --muted:#9bb0c9; --ok:#10b981; --warn:#f59e0b; --err:#ef4444;
  }
  article{padding:.5rem .2rem}
  h3{margin:.2rem 0 .6rem;font-size:1.05rem}
  .toolbar{display:flex;flex-wrap:wrap;gap:.6rem;margin:.4rem 0 .9rem;align-items:end}
  .toolbar label{display:flex;flex-direction:column;font-size:.82rem;color:var(--muted)}
  .toolbar input,.toolbar select{
    background:#091120;border:1px solid var(--bd);color:#e2e8f0;border-radius:.45rem;padding:.46rem .55rem;min-width:12ch
  }
  .toolbar button,.toolbar a.secondary{
    padding:.5rem .7rem;border-radius:.5rem;border:1px solid #2c3c55;background:#1f2b3e;color:#e2e8f0;text-decoration:none;cursor:pointer
  }
  .chips{display:flex;gap:.4rem;flex-wrap:wrap;margin:.2rem 0 .8rem}
  .chip{background:#0c1424;border:1px solid var(--bd);border-radius:999px;color:#cfe4ff;padding:.22rem .6rem;font-size:.82rem}
  .chip b{opacity:.8}

  .table-wrap{overflow:auto;border:1px solid var(--bd);border-radius:.6rem}
  table{width:100%;border-collapse:collapse;font-size:.92rem;min-width:1040px}
  th,td{padding:.55rem .65rem;border-bottom:1px solid var(--bd)}
  thead th{position:sticky;top:0;background:#0f1a2c;z-index:1}
  tbody tr:nth-child(odd){background:#0b1324}
  tbody tr:hover{background:#0b1a34}
  .right{text-align:right}
  .status{font-size:.78rem;padding:.1rem .45rem;border-radius:.45rem;border:1px solid #2b3952;background:#0c1424;display:inline-block}
  .status.OPEN{color:#fde68a;border-color:#5a471c;background:#1b1608}
  .status.PARTIAL{color:#fde68a;border-color:#5a471c;background:#1b1608}
  .status.PAID{color:#a7f3d0;border-color:#194a3b;background:#0c1e1a}
  .form-inline{display:flex;gap:.35rem;align-items:center;flex-wrap:wrap}
  .form-inline input,.form-inline select{
    background:#091120;border:1px solid var(--bd);color:#e2e8f0;border-radius:.35rem;padding:.35rem .45rem
  }
  .btn{display:inline-flex;align-items:center;gap:.35rem;background:#1f2b3e;border:1px solid #2c3c55;color:#e2e8f0;padding:.35rem .55rem;border-radius:.45rem;text-decoration:none}
  .btn:hover{background:#24344c}
  .btn-ok{background:#133226;border-color:#245c45}
  .btn-ok:hover{background:#184232}
  @media print{ .no-print{display:none!important} body{background:#fff;color:#000} }
</style>

<article>
  <h3>Daftar Piutang Sales</h3>

  <form method="get" class="no-print toolbar">
    <label>Due Date Dari
      <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
    </label>
    <label>Sampai
      <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
    </label>
    <label>Status
      <select name="status">
        <option value="OPEN"    <?= $status==='OPEN'?'selected':''    ?>>OPEN</option>
        <option value="PARTIAL" <?= $status==='PARTIAL'?'selected':'' ?>>PARTIAL</option>
        <option value="PAID"    <?= $status==='PAID'?'selected':''    ?>>PAID</option>
        <option value="ALL"     <?= $status==='ALL'?'selected':''     ?>>SEMUA</option>
      </select>
    </label>
    <label>Supplier
      <select name="supplier">
        <option value="">— Semua —</option>
        <?php foreach($suppliers as $s): ?>
          <option value="<?= htmlspecialchars($s['kode']) ?>" <?= $supp===$s['kode']?'selected':'' ?>>
            <?= htmlspecialchars($s['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Cari (Invoice / Supplier)
      <input type="text" name="q" value="<?=htmlspecialchars($q)?>" placeholder="INV/PO/… atau nama supplier">
    </label>
    <button type="submit">Terapkan</button>
    <a class="secondary" href="?from=<?=htmlspecialchars($today)?>&to=<?=htmlspecialchars($today)?>&status=OPEN">Reset Hari Ini</a>
    <button type="button" onclick="window.print()">Print</button>
  </form>

  <div class="chips">
    <span class="chip"><b>Total Piutang</b>: Rp <?= rupiah($total_piutang) ?></span>
    <span class="chip"><b>Sisa (Remain)</b>: Rp <?= rupiah($total_remain) ?></span>
    <span class="chip"><b>Baris</b>: <?= count($rows) ?></span>
  </div>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Due Date</th>
          <th>Supplier</th>
          <th>Invoice</th>
          <th class="right">Total (Piutang)</th>
          <th class="right">Terbayar</th>
          <th class="right">Sisa</th>
          <th>Status</th>
          <th class="no-print">Pelunasan</th>
        </tr>
      </thead>
      <tbody>
        <?php if(!$rows): ?>
          <tr><td colspan="8">Tidak ada data piutang.</td></tr>
        <?php else: foreach($rows as $r):
          $remain = max(0, (int)$r['remain_amount']);
          $paid   = (int)$r['paid_amount'];
          $total  = (int)$r['amount'];
          $disabled = ($r['status']==='PAID' || $remain===0) ? 'disabled' : '';
        ?>
          <tr>
            <td><?= htmlspecialchars($r['due_date']) ?></td>
            <td><?= htmlspecialchars($r['supplier_nama'] ?? $r['supplier_kode']) ?></td>
            <td><?= htmlspecialchars($r['invoice_no']) ?></td>
            <td class="right"><?= rupiah($total) ?></td>
            <td class="right"><?= rupiah($paid) ?></td>
            <td class="right"><strong><?= rupiah($remain) ?></strong></td>
            <td><span class="status <?= htmlspecialchars($r['status']) ?>"><?= htmlspecialchars($r['status']) ?></span></td>
            <td class="no-print">
              <form class="form-inline" action="ar_pay.php" method="post" onsubmit="return confirm('Catat pembayaran piutang ini?');">
                <input type="hidden" name="ar_id" value="<?= (int)$r['id'] ?>">
                <input type="date" name="pay_date" value="<?= date('Y-m-d') ?>" required <?= $disabled ?>>
                <select name="method" <?= $disabled ?>>
                  <option value="cash">Kas</option>
                  <option value="bank">Bank</option>
                  <option value="sales_offset">Offset Penjualan</option>
                </select>
                <input type="number" name="amount" min="1" max="<?= (int)$remain ?>" value="<?= (int)$remain ?>" <?= $disabled ?>>
                <input type="text" name="note" placeholder="catatan" style="min-width:10rem" <?= $disabled ?>>
                <button class="btn btn-ok" type="submit" <?= $disabled ?>>Bayar</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
      <tfoot>
        <tr>
          <th colspan="3" class="right">TOTAL</th>
          <th class="right"><?= rupiah($total_piutang) ?></th>
          <th></th>
          <th class="right"><?= rupiah($total_remain) ?></th>
          <th colspan="2"></th>
        </tr>
      </tfoot>
    </table>
  </div>
</article>

<?php include __DIR__.'/includes/footer.php'; ?>
