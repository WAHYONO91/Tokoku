<?php 
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();
require_role(['admin']);
require_once __DIR__.'/includes/header.php';

function column_exists(PDO $pdo, string $table, string $col): bool {
  $st = $pdo->prepare("
    SELECT 1
    FROM information_schema.columns
    WHERE table_schema = DATABASE() AND table_name = :t AND column_name = :c
    LIMIT 1
  ");
  $st->execute([':t'=>$table, ':c'=>$col]);
  return (bool)$st->fetchColumn();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_GET['id'] ?? 0);
if ($id <= 0) {
  echo "<article><mark>ID tidak valid.</mark></article>";
  include __DIR__.'/includes/footer.php';
  exit;
}

$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id=?");
$stmt->execute([$id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$purchase) {
  echo "<article><mark>Pembelian tidak ditemukan.</mark></article>";
  include __DIR__.'/includes/footer.php';
  exit;
}

$old_location = $purchase['location'] ?? 'gudang';

$has_pi_total = column_exists($pdo, 'purchase_items', 'total');

$has_items_hb = column_exists($pdo, 'items', 'harga_beli');
$has_hj1      = column_exists($pdo, 'items', 'harga_jual1');
$has_hj2      = column_exists($pdo, 'items', 'harga_jual2');
$has_hj3      = column_exists($pdo, 'items', 'harga_jual3');
$has_hj4      = column_exists($pdo, 'items', 'harga_jual4');

$suppliers = $pdo->query("SELECT kode, nama FROM suppliers ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

$itStmt = $pdo->prepare("
  SELECT 
    pi.id,
    pi.item_kode,
    pi.nama,
    pi.unit,
    pi.qty,
    COALESCE(pi.harga_beli,0) AS harga_beli,
    COALESCE(i.harga_jual1,0) AS harga_jual1,
    COALESCE(i.harga_jual2,0) AS harga_jual2,
    COALESCE(i.harga_jual3,0) AS harga_jual3,
    COALESCE(i.harga_jual4,0) AS harga_jual4
  FROM purchase_items pi
  LEFT JOIN items i ON i.kode = pi.item_kode
  WHERE pi.purchase_id = ?
  ORDER BY pi.id
");
$itStmt->execute([$id]);
$details = $itStmt->fetchAll(PDO::FETCH_ASSOC);

$date_value = !empty($purchase['purchase_date'])
  ? $purchase['purchase_date']
  : substr((string)$purchase['created_at'], 0, 10);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $invoice_no    = trim($_POST['invoice_no'] ?? '');
  $supplier_kode = trim($_POST['supplier_kode'] ?? '');
  $location      = ($_POST['location'] ?? 'gudang') === 'toko' ? 'toko' : 'gudang';
  $date_input    = $_POST['purchase_date'] ?: $date_value;

  $postedItems = $_POST['items'] ?? [];

  try {
    $pdo->beginTransaction();

    $upd = $pdo->prepare("
      UPDATE purchases
      SET invoice_no = ?, supplier_kode = ?, location = ?, purchase_date = ?
      WHERE id = ?
    ");
    $upd->execute([$invoice_no, $supplier_kode, $location, $date_input, $id]);

    $getDetail = $pdo->prepare("
      SELECT id, item_kode, qty
      FROM purchase_items
      WHERE id = ? AND purchase_id = ?
    ");

    if ($has_pi_total) {
      $updDetail = $pdo->prepare("
        UPDATE purchase_items
        SET qty = ?, harga_beli = ?, total = ?
        WHERE id = ?
      ");
    } else {
      $updDetail = $pdo->prepare("
        UPDATE purchase_items
        SET qty = ?, harga_beli = ?
        WHERE id = ?
      ");
    }

    foreach ($postedItems as $detailId => $row) {
      $detailId = (int)$detailId;

      $new_qty = isset($row['qty']) ? (int)$row['qty'] : 0;
      if ($new_qty < 0) $new_qty = 0;

      $new_hb  = isset($row['harga_beli']) ? (int)$row['harga_beli'] : 0;
      if ($new_hb < 0) $new_hb = 0;

      // Harga jual: kosong/0 dianggap 0, dan AKAN menyet 0 ke DB
      $new_h1 = isset($row['harga_jual1']) ? (int)$row['harga_jual1'] : 0;
      if ($new_h1 < 0) $new_h1 = 0;

      $new_h2 = isset($row['harga_jual2']) ? (int)$row['harga_jual2'] : 0;
      if ($new_h2 < 0) $new_h2 = 0;

      $new_h3 = isset($row['harga_jual3']) ? (int)$row['harga_jual3'] : 0;
      if ($new_h3 < 0) $new_h3 = 0;

      $new_h4 = isset($row['harga_jual4']) ? (int)$row['harga_jual4'] : 0;
      if ($new_h4 < 0) $new_h4 = 0;

      $getDetail->execute([$detailId, $id]);
      $d = $getDetail->fetch(PDO::FETCH_ASSOC);
      if (!$d) continue;

      $old_qty = (int)$d['qty'];
      $kode    = $d['item_kode'];

      $lineTotal = $new_qty * $new_hb;

      if ($location === $old_location) {
        $delta = $new_qty - $old_qty;
        if ($delta !== 0) {
          adjust_stock($pdo, $kode, $location, $delta);
        }
      } else {
        if ($old_qty !== 0) {
          adjust_stock($pdo, $kode, $old_location, -$old_qty);
        }
        if ($new_qty !== 0) {
          adjust_stock($pdo, $kode, $location, $new_qty);
        }
      }

      if ($has_pi_total) {
        $updDetail->execute([$new_qty, $new_hb, $lineTotal, $detailId]);
      } else {
        $updDetail->execute([$new_qty, $new_hb, $detailId]);
      }

      // Update master items – termasuk set 0 untuk harga_jual2–4 jika diinput 0
      $sets   = [];
      $params = [':kode' => $kode];

      if ($has_items_hb) {
        $sets[]        = "harga_beli = :hb";
        $params[':hb'] = $new_hb;
      }
      if ($has_hj1) {
        $sets[]         = "harga_jual1 = :h1";
        $params[':h1']  = $new_h1;
      }
      if ($has_hj2) {
        $sets[]         = "harga_jual2 = :h2";
        $params[':h2']  = $new_h2;
      }
      if ($has_hj3) {
        $sets[]         = "harga_jual3 = :h3";
        $params[':h3']  = $new_h3;
      }
      if ($has_hj4) {
        $sets[]         = "harga_jual4 = :h4";
        $params[':h4']  = $new_h4;
      }

      if (!empty($sets)) {
        $sqlU = "UPDATE items SET ".implode(', ', $sets)." WHERE kode = :kode";
        $pdo->prepare($sqlU)->execute($params);
      }
    }

    if ($has_pi_total) {
      $sumSql = "
        SELECT SUM(COALESCE(total, qty * COALESCE(harga_beli,0))) AS subtotal_calc
        FROM purchase_items
        WHERE purchase_id = ?
      ";
    } else {
      $sumSql = "
        SELECT SUM(qty * COALESCE(harga_beli,0)) AS subtotal_calc
        FROM purchase_items
        WHERE purchase_id = ?
      ";
    }
    $sumSt = $pdo->prepare($sumSql);
    $sumSt->execute([$id]);
    $subtotal_calc = (int)($sumSt->fetchColumn() ?: 0);

    $discount = (int)($purchase['discount'] ?? 0);
    $tax      = (int)($purchase['tax'] ?? 0);
    $total    = max(0, $subtotal_calc - $discount + $tax);

    $updHeadTotals = $pdo->prepare("
      UPDATE purchases
      SET subtotal = ?, total = ?
      WHERE id = ?
    ");
    $updHeadTotals->execute([$subtotal_calc, $total, $id]);

    $pdo->commit();

    echo "<article><mark>Pembelian, harga, dan stok berhasil diperbarui.</mark> <p><a href='purchases_report.php'>Kembali ke laporan</a></p></article>";
    include __DIR__.'/includes/footer.php';
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    echo "<article><mark>Gagal memperbarui: ".htmlspecialchars($e->getMessage())."</mark></article>";
    include __DIR__.'/includes/footer.php';
    exit;
  }
}

$date_value = !empty($purchase['purchase_date'])
  ? $purchase['purchase_date']
  : substr((string)$purchase['created_at'], 0, 10);
?>
<style>
  .grid-form{display:grid;grid-template-columns:repeat(auto-fit,minmax(240px,1fr));gap:.6rem}
  .table-wrap{overflow:auto;border:1px solid #223047;border-radius:.6rem;margin-top:.8rem}
  table{width:100%;border-collapse:collapse;min-width:1100px}
  th,td{border-bottom:1px solid #223047;padding:.5rem .6rem;vertical-align:middle}
  thead th{background:#0f1a2c;position:sticky;top:0;z-index:1}
  tbody tr:nth-child(odd){background:#0b1324}
  .right{text-align:right}
  .muted{opacity:.8;font-size:.85rem}
  .actions{display:flex;gap:.5rem;flex-wrap:wrap;margin-top:.6rem}
  @media print{ .no-print{display:none!important} }
</style>

<article>
  <h3>Ubah Pembelian</h3>

  <form method="post" class="no-print grid-form">
    <input type="hidden" name="id" value="<?= (int)$purchase['id'] ?>">

    <label>No. Faktur
      <input type="text" name="invoice_no" value="<?= htmlspecialchars($purchase['invoice_no'] ?? '') ?>">
    </label>

    <label>Supplier
      <select name="supplier_kode">
        <option value="">-- pilih --</option>
        <?php foreach($suppliers as $s): ?>
          <option value="<?= htmlspecialchars($s['kode']) ?>"
            <?= ($s['kode'] == ($purchase['supplier_kode'] ?? '')) ? 'selected' : '' ?>>
            <?= htmlspecialchars($s['nama']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </label>

    <label>Lokasi
      <select name="location">
        <option value="gudang" <?= (($purchase['location'] ?? '') === 'gudang') ? 'selected' : '' ?>>Gudang</option>
        <option value="toko"   <?= (($purchase['location'] ?? '') === 'toko')   ? 'selected' : '' ?>>Toko</option>
      </select>
    </label>

    <label>Tanggal Pembelian
      <input type="date" name="purchase_date" value="<?= htmlspecialchars($date_value) ?>">
    </label>

    <div class="table-wrap" style="grid-column:1/-1">
      <table>
        <thead>
          <tr>
            <th style="width:110px">Kode</th>
            <th>Nama</th>
            <th style="width:90px" class="right">Qty</th>
            <th style="width:120px" class="right">Harga Beli</th>
            <th style="width:120px" class="right">HJ1</th>
            <th style="width:120px" class="right">HJ2</th>
            <th style="width:120px" class="right">HJ3</th>
            <th style="width:120px" class="right">HJ4</th>
            <th style="width:150px" class="right">Total (preview)</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$details): ?>
            <tr><td colspan="9">Detail pembelian kosong.</td></tr>
          <?php else: ?>
            <?php $rowIndex = 0; ?>
            <?php foreach($details as $d):
              $qty = (int)$d['qty'];
              $hb  = (int)$d['harga_beli'];
              $hj1 = (int)$d['harga_jual1'];
              $hj2 = (int)$d['harga_jual2'];
              $hj3 = (int)$d['harga_jual3'];
              $hj4 = (int)$d['harga_jual4'];
              $tot = $qty * $hb;
            ?>
            <tr>
              <td><?= htmlspecialchars($d['item_kode']) ?></td>
              <td><?= htmlspecialchars($d['nama']) ?></td>
              <td class="right">
                <input type="number"
                       name="items[<?= (int)$d['id'] ?>][qty]"
                       value="<?= $qty ?>"
                       min="0" step="1"
                       class="qtyInput"
                       data-idx="<?= $rowIndex ?>"
                       style="width:4.5rem;text-align:right">
              </td>
              <td class="right">
                <input type="number"
                       name="items[<?= (int)$d['id'] ?>][harga_beli]"
                       value="<?= $hb ?>"
                       min="0" step="1"
                       class="hbeliInput"
                       data-idx="<?= $rowIndex ?>"
                       style="width:7rem;text-align:right">
              </td>
              <td class="right">
                <input type="number"
                       name="items[<?= (int)$d['id'] ?>][harga_jual1]"
                       value="<?= $hj1 ?>"
                       min="0" step="1"
                       class="hjual1Input"
                       data-idx="<?= $rowIndex ?>"
                       style="width:7rem;text-align:right">
              </td>
              <td class="right">
                <input type="number"
                       name="items[<?= (int)$d['id'] ?>][harga_jual2]"
                       value="<?= $hj2 ?>"
                       min="0" step="1"
                       class="hjual2Input"
                       data-idx="<?= $rowIndex ?>"
                       style="width:7rem;text-align:right">
              </td>
              <td class="right">
                <input type="number"
                       name="items[<?= (int)$d['id'] ?>][harga_jual3]"
                       value="<?= $hj3 ?>"
                       min="0" step="1"
                       class="hjual3Input"
                       data-idx="<?= $rowIndex ?>"
                       style="width:7rem;text-align:right">
              </td>
              <td class="right">
                <input type="number"
                       name="items[<?= (int)$d['id'] ?>][harga_jual4]"
                       value="<?= $hj4 ?>"
                       min="0" step="1"
                       class="hjual4Input"
                       data-idx="<?= $rowIndex ?>"
                       style="width:7rem;text-align:right">
              </td>
              <td class="right"><?= number_format($tot,0,',','.') ?></td>
            </tr>
            <?php $rowIndex++; ?>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <div class="actions">
      <button type="submit">Simpan</button>
      <a href="purchases_report.php" class="secondary">Batal</a>
    </div>
  </form>
</article>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const navSelectors = [
    '.qtyInput',
    '.hbeliInput',
    '.hjual1Input',
    '.hjual2Input',
    '.hjual3Input',
    '.hjual4Input'
  ];

  navSelectors.forEach((sel, colIndex) => {
    document.querySelectorAll(sel).forEach(inp => {
      inp.addEventListener('keydown', (e) => {
        const idx = parseInt(e.target.dataset.idx);
        if (Number.isNaN(idx)) return;

        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
          e.preventDefault();
          const dir = (e.key === 'ArrowUp') ? -1 : 1;
          const targetIdx = idx + dir;
          if (targetIdx < 0) return;
          const target = document.querySelector(sel + '[data-idx="'+targetIdx+'"]');
          if (target) {
            target.focus();
            if (target.select) target.select();
          }
          return;
        }

        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
          const dir = (e.key === 'ArrowLeft') ? -1 : 1;
          const newCol = colIndex + dir;
          if (newCol < 0 || newCol >= navSelectors.length) return;

          const targetSel = navSelectors[newCol];
          const target = document.querySelector(targetSel + '[data-idx="'+idx+'"]');
          if (target) {
            e.preventDefault();
            target.focus();
            if (target.select) target.select();
          }
        }
      });
    });
  });
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
