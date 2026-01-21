<?php
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/includes/header.php';

/* ======================
   Helper: cek tabel & kolom (pakai cache)
   ====================== */
function table_exists(PDO $pdo, string $table): bool {
  static $cache = [];
  if (array_key_exists($table, $cache)) return $cache[$table];

  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.tables
    WHERE table_schema = DATABASE() AND table_name = :t
    LIMIT 1
  ");
  $st->execute([':t' => $table]);
  $cache[$table] = (bool)$st->fetchColumn();
  return $cache[$table];
}

function column_exists(PDO $pdo, string $table, string $col): bool {
  static $cache = [];
  $key = $table . '.' . $col;
  if (array_key_exists($key, $cache)) return $cache[$key];

  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
    LIMIT 1
  ");
  $st->execute([':t' => $table, ':c' => $col]);
  $cache[$key] = (bool)$st->fetchColumn();
  return $cache[$key];
}

/* ======================
   Identitas toko
   ====================== */
$setting = $pdo->query("
  SELECT store_name, store_address, store_phone, logo_url
  FROM settings
  WHERE id=1
")->fetch(PDO::FETCH_ASSOC);

$store_name    = $setting['store_name']    ?? 'TOKO';
$store_address = $setting['store_address'] ?? '';
$store_phone   = $setting['store_phone']   ?? '';
$logo_url      = $setting['logo_url']      ?? '';

/* ======================
   Ringkasan penjualan/pembelian
   ====================== */
$today      = date('Y-m-d');
$this_month = date('Y-m-01');

/* Penjualan hari ini & bulan ini */
$sum_today = $pdo->prepare("
  SELECT COALESCE(SUM(total),0) AS total
  FROM sales
  WHERE (status IS NULL OR status='OK')
    AND DATE(created_at) = ?
");
$sum_today->execute([$today]);
$total_today = (int)($sum_today->fetch()['total'] ?? 0);

$sum_month = $pdo->prepare("
  SELECT COALESCE(SUM(total),0) AS total
  FROM sales
  WHERE (status IS NULL OR status='OK')
    AND DATE(created_at) BETWEEN ? AND ?
");
$sum_month->execute([$this_month, $today]);
$total_month = (int)($sum_month->fetch()['total'] ?? 0);

$trx_today = $pdo->prepare("
  SELECT COUNT(*) AS c
  FROM sales
  WHERE (status IS NULL OR status='OK')
    AND DATE(created_at)=?
");
$trx_today->execute([$today]);
$count_today = (int)($trx_today->fetch()['c'] ?? 0);

/* Pembelian (aman jika kolom total tidak ada) */
$pb_today = 0;
$pb_month = 0;
if (table_exists($pdo, 'purchases')) {
  $pb_sql_base = "
    COALESCE(SUM(
      COALESCE(total, subtotal - COALESCE(discount,0) + COALESCE(tax,0))
    ),0) AS total
  ";

  // Hari ini
  $pbt = $pdo->prepare("
    SELECT {$pb_sql_base}
    FROM purchases
    WHERE DATE(COALESCE(purchase_date, created_at)) = ?
  ");
  $pbt->execute([$today]);
  $pb_today = (int)($pbt->fetch()['total'] ?? 0);

  // Bulan ini
  $pbm = $pdo->prepare("
    SELECT {$pb_sql_base}
    FROM purchases
    WHERE DATE(COALESCE(purchase_date, created_at)) BETWEEN ? AND ?
  ");
  $pbm->execute([$this_month, $today]);
  $pb_month = (int)($pbm->fetch()['total'] ?? 0);
}

/* ======================
   Data grafik 7 hari (1 query, GROUP BY date)
   ====================== */
$sales_labels = [];
$sales_values = [];

$start7 = date('Y-m-d', strtotime('-6 days'));
$end7   = $today;

$st_sales = $pdo->prepare("
  SELECT DATE(created_at) AS d, COALESCE(SUM(total),0) AS total
  FROM sales
  WHERE (status IS NULL OR status='OK')
    AND DATE(created_at) BETWEEN :from AND :to
  GROUP BY DATE(created_at)
");
$st_sales->execute([':from' => $start7, ':to' => $end7]);
$sales_raw = $st_sales->fetchAll(PDO::FETCH_KEY_PAIR); // [ 'YYYY-mm-dd' => total ]

for ($i=6; $i>=0; $i--) {
  $d = date('Y-m-d', strtotime("-{$i} day"));
  $label = date('d/m', strtotime($d));
  $sales_labels[] = $label;
  $sales_values[] = isset($sales_raw[$d]) ? (int)$sales_raw[$d] : 0;
}

/* Grafik pembelian 7 hari (kalau ada purchases) */
$purchase_labels = [];
$purchase_values = [];

if (table_exists($pdo, 'purchases')) {
  $st_pur = $pdo->prepare("
    SELECT DATE(COALESCE(purchase_date, created_at)) AS d,
           COALESCE(SUM(
             COALESCE(total, subtotal - COALESCE(discount,0) + COALESCE(tax,0))
           ),0) AS total
    FROM purchases
    WHERE DATE(COALESCE(purchase_date, created_at)) BETWEEN :from AND :to
    GROUP BY DATE(COALESCE(purchase_date, created_at))
  ");
  $st_pur->execute([':from' => $start7, ':to' => $end7]);
  $pur_raw = $st_pur->fetchAll(PDO::FETCH_KEY_PAIR);

  for ($i=6; $i>=0; $i--) {
    $d = date('Y-m-d', strtotime("-{$i} day"));
    $label = date('d/m', strtotime($d));
    $purchase_labels[] = $label;
    $purchase_values[] = isset($pur_raw[$d]) ? (int)$pur_raw[$d] : 0;
  }
}

/* ======================
   KAS HARI INI (samakan dengan cashier_cash.php)
   ====================== */
$cash_in_today      = 0;
$cash_out_today     = 0;
$cash_balance_today = 0;

$has_cash_ledger =
  table_exists($pdo,'cash_ledger') &&
  column_exists($pdo,'cash_ledger','direction') &&
  column_exists($pdo,'cash_ledger','type') &&
  column_exists($pdo,'cash_ledger','amount') &&
  (column_exists($pdo,'cash_ledger','tanggal') || column_exists($pdo,'cash_ledger','created_at'));

if ($has_cash_ledger) {
  $useTanggal = column_exists($pdo,'cash_ledger','tanggal');
  $dateExpr   = $useTanggal ? 'tanggal' : 'DATE(created_at)';

  // OPENING (IN)
  $q_open = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM cash_ledger
    WHERE {$dateExpr} = ?
      AND UPPER(direction)='IN'
      AND type='OPENING'
  ");
  $q_open->execute([$today]);
  $opening = (int)$q_open->fetchColumn();

  // MANUAL_IN (IN)
  $q_min = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM cash_ledger
    WHERE {$dateExpr} = ?
      AND UPPER(direction)='IN'
      AND type='MANUAL_IN'
  ");
  $q_min->execute([$today]);
  $manual_in = (int)$q_min->fetchColumn();

  // MANUAL_OUT (OUT)
  $q_mout = $pdo->prepare("
    SELECT COALESCE(SUM(amount),0)
    FROM cash_ledger
    WHERE {$dateExpr} = ?
      AND UPPER(direction)='OUT'
      AND type='MANUAL_OUT'
  ");
  $q_mout->execute([$today]);
  $manual_out = (int)$q_mout->fetchColumn();

  // SALES OK (masuk)
  $q_sales_in = $pdo->prepare("
    SELECT COALESCE(SUM(s.total),0)
    FROM sales s
    WHERE DATE(s.created_at)=?
      AND (s.status IS NULL OR s.status='OK')
  ");
  $q_sales_in->execute([$today]);
  $in_sales = (int)$q_sales_in->fetchColumn();

  // SALES retur/batal (keluar)
  $q_sales_out = $pdo->prepare("
    SELECT COALESCE(SUM(s.total),0)
    FROM sales s
    WHERE DATE(s.created_at)=?
      AND s.status IN ('RETURN','BATAL','CANCEL')
  ");
  $q_sales_out->execute([$today]);
  $out_sales = (int)$q_sales_out->fetchColumn();

  $cash_in_today      = $opening + $manual_in + $in_sales;
  $cash_out_today     = $manual_out + $out_sales;
  $cash_balance_today = $cash_in_today - $cash_out_today;
}

/* ======================
   STOK LIMIT (<= min_stock), top 20 + nama supplier
   ====================== */
$low_stocks = [];
if (table_exists($pdo,'items')) {

  $canJoinSupplier =
    table_exists($pdo, 'suppliers') &&
    column_exists($pdo, 'items', 'supplier_kode') &&
    column_exists($pdo, 'suppliers', 'kode') &&
    column_exists($pdo, 'suppliers', 'nama');

  if ($canJoinSupplier) {
    $items_stmt = $pdo->query("
      SELECT
        i.kode,
        i.nama,
        COALESCE(NULLIF(s.nama,''), NULLIF(i.unit,''), 'Supplier Umum') AS supplier_name,
        COALESCE(i.min_stock,0)   AS min_stock,
        COALESCE(i.harga_beli,0)  AS hb,
        COALESCE(i.harga_jual1,0) AS h1
      FROM items i
      LEFT JOIN suppliers s ON s.kode = i.supplier_kode
      WHERE COALESCE(i.min_stock,0) > 0
      ORDER BY i.min_stock ASC
      LIMIT 200
    ");
  } else {
    $items_stmt = $pdo->query("
      SELECT
        kode,
        nama,
        COALESCE(NULLIF(unit,''), 'Supplier Umum') AS supplier_name,
        COALESCE(min_stock,0)   AS min_stock,
        COALESCE(harga_beli,0)  AS hb,
        COALESCE(harga_jual1,0) AS h1
      FROM items
      WHERE COALESCE(min_stock,0) > 0
      ORDER BY min_stock ASC
      LIMIT 200
    ");
  }

  $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);

  foreach ($items as $it) {
    $g = get_stock($pdo, $it['kode'], 'gudang');
    $t = get_stock($pdo, $it['kode'], 'toko');
    $qty = (int)$g + (int)$t;
    $min = (int)$it['min_stock'];

    if ($qty <= $min) {
      $low_stocks[] = [
        'kode'     => $it['kode'],
        'nama'     => $it['nama'],
        'supplier' => $it['supplier_name'] ?? 'Supplier Umum',
        'qty'      => $qty,
        'min'      => $min,
        'hb'       => (int)$it['hb'],
        'h1'       => (int)$it['h1'],
      ];
    }
  }

  usort($low_stocks, fn($a,$b) => $a['qty'] <=> $b['qty']);
  $low_stocks = array_slice($low_stocks, 0, 20);
}

?>
<article>
  <h3>Dashboard</h3>

  <!-- IDENTITAS TOKO -->
  <div style="display:flex; gap:1rem; align-items:center; margin-bottom:1.2rem;">
    <?php if(!empty($logo_url)): ?>
      <img src="<?= htmlspecialchars($logo_url) ?>" alt="Logo" style="max-height:60px;">
    <?php endif; ?>
    <div>
      <div style="font-size:1.2rem; font-weight:600;"><?= htmlspecialchars($store_name) ?></div>
      <?php if($store_address): ?>
        <div style="font-size:.9rem;"><?= htmlspecialchars($store_address) ?></div>
      <?php endif; ?>
      <?php if($store_phone): ?>
        <div style="font-size:.9rem;">Telp: <?= htmlspecialchars($store_phone) ?></div>
      <?php endif; ?>
    </div>
  </div>

  <!-- CARD RINGKASAN -->
  <div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(200px, 1fr)); margin-bottom:1.2rem;">
    <article style="margin:0;">
      <header>Penjualan Hari Ini</header>
      <strong style="font-size:1.35rem;"><?= 'Rp '.number_format($total_today,0,',','.') ?></strong>
      <p style="margin-bottom:0;">Tanggal: <?= date('d-m-Y') ?></p>
    </article>
    <article style="margin:0;">
      <header>Penjualan Bulan Ini</header>
      <strong style="font-size:1.35rem;"><?= 'Rp '.number_format($total_month,0,',','.') ?></strong>
      <p style="margin-bottom:0;">Periode: <?= date('m-Y') ?></p>
    </article>
    <article style="margin:0;">
      <header>Transaksi Hari Ini</header>
      <strong style="font-size:1.35rem;"><?= number_format($count_today,0,',','.') ?></strong>
      <p style="margin-bottom:0;">Invoice masuk</p>
    </article>
    <article style="margin:0;">
      <header>Pembelian Hari Ini</header>
      <strong style="font-size:1.35rem;"><?= 'Rp '.number_format($pb_today,0,',','.') ?></strong>
      <p style="margin-bottom:0;opacity:.85">Bulan ini: <?= 'Rp '.number_format($pb_month,0,',','.') ?></p>
    </article>
  </div>
  <?php
/* ======================
   RINGKASAN PIUTANG MEMBER (member_ar)
   ====================== */
$ar_total_open = 0;
$ar_total_paid = 0;
$ar_total_rem  = 0;
$ar_cnt_open   = 0;
$ar_cnt_over   = 0;

if (table_exists($pdo, 'member_ar')) {
  $st_ar = $pdo->query("
    SELECT
      COALESCE(SUM(CASE WHEN status='OPEN' THEN total ELSE 0 END),0) AS total_open,
      COALESCE(SUM(paid),0) AS total_paid,
      COALESCE(SUM(CASE WHEN status='OPEN' THEN remaining ELSE 0 END),0) AS total_remaining,
      COALESCE(SUM(CASE WHEN status='OPEN' THEN 1 ELSE 0 END),0) AS cnt_open,
      COALESCE(SUM(CASE WHEN status='OPEN' AND due_date < CURDATE() THEN 1 ELSE 0 END),0) AS cnt_overdue
    FROM member_ar
  ");
  $ar_sum = $st_ar->fetch(PDO::FETCH_ASSOC) ?: [];
  $ar_total_open = (int)($ar_sum['total_open'] ?? 0);
  $ar_total_paid = (int)($ar_sum['total_paid'] ?? 0);
  $ar_total_rem  = (int)($ar_sum['total_remaining'] ?? 0);
  $ar_cnt_open   = (int)($ar_sum['cnt_open'] ?? 0);
  $ar_cnt_over   = (int)($ar_sum['cnt_overdue'] ?? 0);
}
?>

<article style="margin:0 0 1.2rem 0;">
  <header style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;">
    <div style="font-weight:650;">Ringkasan Piutang Member</div>
    <a class="menu-card" href="member_ar_list.php" style="text-decoration:none;">
      <span class="menu-icon">🧾</span><span>Lihat Semua Piutang</span>
    </a>
  </header>

  <?php if (!table_exists($pdo, 'member_ar')): ?>
    <p style="opacity:.85;margin:.5rem 0">
      Tabel <code>member_ar</code> belum tersedia.
    </p>
  <?php else: ?>
    <div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit, minmax(220px, 1fr)); margin-top:.8rem;">
      <article style="margin:0;">
        <header>Total Piutang OPEN</header>
        <strong style="font-size:1.35rem;"><?= 'Rp '.number_format($ar_total_open,0,',','.') ?></strong>
        <p style="margin-bottom:0;opacity:.85">Jumlah open: <?= number_format($ar_cnt_open,0,',','.') ?></p>
      </article>

      <article style="margin:0;">
        <header>Sisa Tagihan</header>
        <strong style="font-size:1.35rem;"><?= 'Rp '.number_format($ar_total_rem,0,',','.') ?></strong>
        <p style="margin-bottom:0;opacity:.85">Yang belum tertagih</p>
      </article>

      <article style="margin:0;">
        <header>Overdue</header>
        <strong style="font-size:1.35rem;"><?= number_format($ar_cnt_over,0,',','.') ?></strong>
        <p style="margin-bottom:0;opacity:.85">Lewat jatuh tempo</p>
      </article>

      <article style="margin:0;">
        <header>Total Terbayar</header>
        <strong style="font-size:1.35rem;"><?= 'Rp '.number_format($ar_total_paid,0,',','.') ?></strong>
        <p style="margin-bottom:0;opacity:.85">Akumulasi pembayaran</p>
      </article>
    </div>
  <?php endif; ?>
</article>

<?php
$ar_latest = [];
if (table_exists($pdo, 'member_ar') && table_exists($pdo, 'members')) {
  $st = $pdo->query("
    SELECT
      ar.id, ar.invoice_no, ar.remaining, ar.due_date, ar.status, ar.created_at,
      m.kode AS member_kode, m.nama AS member_nama,
      CASE WHEN ar.status='OPEN' AND ar.due_date < CURDATE() THEN 1 ELSE 0 END AS is_overdue
    FROM member_ar ar
    JOIN members m ON m.id = ar.member_id
    ORDER BY ar.created_at DESC
    LIMIT 10
  ");
  $ar_latest = $st->fetchAll(PDO::FETCH_ASSOC);
}
?>

<article style="margin:0 0 1.2rem 0;">
  <header style="display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;">
    <div style="font-weight:650;">Piutang Terbaru (Top 10)</div>
    <div class="muted" style="font-size:.85rem;">Klik “Bayar” untuk cicilan</div>
  </header>

  <?php if (empty($ar_latest)): ?>
    <p style="opacity:.85;margin:.5rem 0">Belum ada data piutang.</p>
  <?php else: ?>
    <div style="overflow:auto;margin-top:.6rem;">
      <table class="table-small" style="min-width:900px">
        <thead>
          <tr>
            <th>Tanggal</th>
            <th>Invoice</th>
            <th>Member</th>
            <th class="right">Sisa</th>
            <th>Jatuh Tempo</th>
            <th>Status</th>
            <th class="no-print">Aksi</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach($ar_latest as $r): 
          $isOver = (int)$r['is_overdue'] === 1;
          $badge = ($r['status']==='PAID') ? '✅ LUNAS' : ($isOver ? '⚠️ OVERDUE' : '⏳ OPEN');
        ?>
          <tr>
            <td><?= htmlspecialchars(date('Y-m-d', strtotime($r['created_at']))) ?></td>
            <td><?= htmlspecialchars($r['invoice_no']) ?></td>
            <td><?= htmlspecialchars($r['member_kode']) ?> — <?= htmlspecialchars($r['member_nama']) ?></td>
            <td class="right"><b><?= number_format((float)$r['remaining'],0,',','.') ?></b></td>
            <td><?= htmlspecialchars($r['due_date']) ?></td>
            <td><?= htmlspecialchars($badge) ?></td>
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
                   href="member_ar_letter.php?id=<?= (int)$r['id'] ?>" target="_blank" rel="noopener">
                  <span class="menu-icon">📄</span><span>Surat</span>
                </a>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</article>


  <!-- GRID GRAFIK -->
  <div style="display:grid; gap:1rem; grid-template-columns:repeat(auto-fit,minmax(320px,1fr)); align-items:start">
    <article style="margin:0;">
      <header>Penjualan 7 Hari Terakhir</header>
      <canvas id="salesChart" height="140"></canvas>
    </article>

    <article style="margin:0;">
      <header>Pembelian 7 Hari Terakhir</header>
      <canvas id="purchaseChart" height="140"></canvas>
    </article>

    <!-- Kas per hari dari cash_ledger -->
    <article style="margin:0; grid-column:1/-1;">
      <header>Kas Hari Ini (Per Hari)</header>
      <?php if($has_cash_ledger): ?>
        <div style="display:grid;gap:.8rem;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));align-items:stretch">
          <article style="margin:0;">
            <header>Kas Masuk</header>
            <strong style="font-size:1.35rem;">Rp <?= number_format($cash_in_today,0,',','.') ?></strong>
            <p style="margin-bottom:0;opacity:.85">Tanggal: <?= date('d-m-Y') ?></p>
          </article>
          <article style="margin:0;">
            <header>Kas Keluar</header>
            <strong style="font-size:1.35rem;">Rp <?= number_format($cash_out_today,0,',','.') ?></strong>
            <p style="margin-bottom:0;opacity:.85">Tanggal: <?= date('d-m-Y') ?></p>
          </article>
          <article style="margin:0;">
            <header>Saldo Kas (Hari Ini)</header>
            <strong style="font-size:1.35rem;">
              <?= ($cash_balance_today>=0?'Rp ':'- Rp ') . number_format(abs($cash_balance_today),0,',','.') ?>
            </strong>
            <p style="margin-bottom:0;opacity:.85"><?= $cash_balance_today>=0 ? 'Positif' : 'Defisit' ?></p>
          </article>
        </div>
      <?php else: ?>
        <p style="opacity:.85;margin:.5rem 0">
          Tabel <code>cash_ledger</code> belum tersedia atau kolom wajib belum lengkap.
          Minimal: <code>direction</code>, <code>amount</code>, dan <code>tanggal</code> (DATE)
          atau <code>created_at</code> (DATETIME).
        </p>
      <?php endif; ?>
    </article>
  </div>

  <!-- STOK LIMIT -->
  <article style="margin-top:1.2rem;">
    <header>Stok Di Bawah / Setara Batas Minimum (Top 20)</header>
    <div style="overflow:auto">
      <table class="table-small" style="min-width:1000px">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Supplier</th>
            <th class="right">Qty Total</th>
            <th class="right">Min</th>
            <th class="right">Harga Beli</th>
            <th class="right">Harga Jual H1</th>
          </tr>
        </thead>
        <tbody>
          <?php if(empty($low_stocks)): ?>
            <tr><td colspan="7">Semua stok aman di atas batas minimum.</td></tr>
          <?php else: foreach($low_stocks as $ls): ?>
            <tr>
              <td><?= htmlspecialchars($ls['kode']) ?></td>
              <td><?= htmlspecialchars($ls['nama']) ?></td>
              <td><?= htmlspecialchars(($ls['supplier'] ?? '') !== '' ? $ls['supplier'] : '-') ?></td>
              <td class="right"><?= number_format($ls['qty'],0,',','.') ?></td>
              <td class="right"><?= number_format($ls['min'],0,',','.') ?></td>
              <td class="right"><?= number_format($ls['hb'],0,',','.') ?></td>
              <td class="right"><?= number_format($ls['h1'],0,',','.') ?></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </article>
</article>

<!-- Chart.js (LOCAL) -->
<script src="/tokoapp/assets/vendor/chart.umd.min.js"></script>
<script>
if (!window.Chart) {
  console.warn('Chart.js tidak tersedia. Pastikan file ada di /tokoapp/assets/vendor/chart.umd.min.js');
}
</script>

<script>
const salesLabels    = <?= json_encode($sales_labels) ?>;
const salesValues    = <?= json_encode($sales_values) ?>;
const purchaseLabels = <?= json_encode($purchase_labels) ?>;
const purchaseValues = <?= json_encode($purchase_values) ?>;

if (window.Chart) {
  // Chart Penjualan
  (() => {
    const el = document.getElementById('salesChart');
    if (!el) return;

    new Chart(el.getContext('2d'), {
      type: 'bar',
      data: {
        labels: salesLabels,
        datasets: [{
          label: 'Penjualan (Rp)',
          data: salesValues,
          borderWidth: 1
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (v) => new Intl.NumberFormat('id-ID').format(v)
            }
          }
        }
      }
    });
  })();

  // Chart Pembelian
  (() => {
    const el = document.getElementById('purchaseChart');
    if (!el) return;

    new Chart(el.getContext('2d'), {
      type: 'bar',
      data: {
        labels: purchaseLabels,
        datasets: [{
          label: 'Pembelian (Rp)',
          data: purchaseValues,
          borderWidth: 1
        }]
      },
      options: {
        plugins: { legend: { display: false } },
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              callback: (v) => new Intl.NumberFormat('id-ID').format(v)
            }
          }
        }
      }
    });
  })();
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
