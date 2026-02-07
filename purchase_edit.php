<?php
require_once __DIR__.'/config.php';
require_access('PURCHASE');

/**
 * Helper to check if a column exists in a table.
 */
function table_has_col(PDO $pdo, string $table, string $col): bool {
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
    die("ID tidak valid.");
}

// 1. Fetch Purchase Header
$stmt = $pdo->prepare("SELECT * FROM purchases WHERE id = ?");
$stmt->execute([$id]);
$purchase = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$purchase) {
    die("Pembelian tidak ditemukan.");
}

// 2. Fetch Purchase Items
$itStmt = $pdo->prepare("
    SELECT pi.*, i.harga_jual1, i.harga_jual2, i.harga_jual3, i.harga_jual4, i.barcode
    FROM purchase_items pi
    LEFT JOIN items i ON i.kode = pi.item_kode
    WHERE pi.purchase_id = ?
    ORDER BY pi.id
");
$itStmt->execute([$id]);
$currentDetails = $itStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Handle POST Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload'])) {
    $data = json_decode($_POST['payload'], true);
    if (!$data) {
        $msg_err = "Data payload tidak valid.";
    } else {
        try {
            $pdo->beginTransaction();

            // A. REVERT OLD STOCK
            $oldLocation = $purchase['location'];
            foreach ($currentDetails as $oldItem) {
                adjust_stock($pdo, $oldItem['item_kode'], $oldLocation, -$oldItem['qty']);
            }

            // B. DELETE OLD DETAILS
            $pdo->prepare("DELETE FROM purchase_items WHERE purchase_id = ?")->execute([$id]);

            // C. UPDATE HEADER
            $newLocation = ($data['location'] ?? 'gudang') === 'toko' ? 'toko' : 'gudang';
            $subtotal = 0;
            foreach ($data['items'] as $it) {
                $subtotal += ($it['qty'] * $it['harga_beli']);
            }
            $discount = (int)($data['discount'] ?? 0);
            $tax      = (int)($data['tax'] ?? 0);
            $total    = max(0, $subtotal - $discount + $tax);

            $updHead = $pdo->prepare("
                UPDATE purchases 
                SET invoice_no = ?, supplier_kode = ?, location = ?, 
                    purchase_date = ?, subtotal = ?, discount = ?, tax = ?, total = ?, note = ?
                WHERE id = ?
            ");
            $updHead->execute([
                $data['invoice_no'],
                $data['supplier_kode'],
                $newLocation,
                $data['purchase_date'],
                $subtotal,
                $discount,
                $tax,
                $total,
                $data['note'] ?? '',
                $id
            ]);

            // D. INSERT NEW DETAILS & APPLY STOCK & UPDATE MASTER PRICES
            $has_pi_total = table_has_col($pdo, 'purchase_items', 'total');
            $has_hj1 = table_has_col($pdo, 'items', 'harga_jual1');
            $has_hj2 = table_has_col($pdo, 'items', 'harga_jual2');
            $has_hj3 = table_has_col($pdo, 'items', 'harga_jual3');
            $has_hj4 = table_has_col($pdo, 'items', 'harga_jual4');
            $has_hb  = table_has_col($pdo, 'items', 'harga_beli');

            foreach ($data['items'] as $it) {
                $kode = $it['kode'];
                $qty  = (int)$it['qty'];
                $hb   = (int)$it['harga_beli'];
                $lineTotal = $qty * $hb;

                // Insert detail
                if ($has_pi_total) {
                    $ins = $pdo->prepare("INSERT INTO purchase_items (purchase_id, item_kode, nama, unit, qty, harga_beli, total, created_at) VALUES (?,?,?,?,?,?,?,NOW())");
                    $ins->execute([$id, $kode, $it['nama'], $it['unit'] ?? 'pcs', $qty, $hb, $lineTotal]);
                } else {
                    $ins = $pdo->prepare("INSERT INTO purchase_items (purchase_id, item_kode, nama, unit, qty, harga_beli, created_at) VALUES (?,?,?,?,?,?,NOW())");
                    $ins->execute([$id, $kode, $it['nama'], $it['unit'] ?? 'pcs', $qty, $hb]);
                }

                // Adjust Stock
                adjust_stock($pdo, $kode, $newLocation, $qty);

                // Update Master Items
                $sets = [];
                $params = [':kode' => $kode];
                if ($has_hb) { $sets[] = "harga_beli = :hb"; $params[':hb'] = $hb; }
                if ($has_hj1 && isset($it['harga_jual1'])) { $sets[] = "harga_jual1 = :h1"; $params[':h1'] = $it['harga_jual1']; }
                if ($has_hj2 && isset($it['harga_jual2'])) { $sets[] = "harga_jual2 = :h2"; $params[':h2'] = $it['harga_jual2']; }
                if ($has_hj3 && isset($it['harga_jual3'])) { $sets[] = "harga_jual3 = :h3"; $params[':h3'] = $it['harga_jual3']; }
                if ($has_hj4 && isset($it['harga_jual4'])) { $sets[] = "harga_jual4 = :h4"; $params[':h4'] = $it['harga_jual4']; }

                if (!empty($sets)) {
                    $sqlU = "UPDATE items SET ".implode(', ', $sets)." WHERE kode = :kode";
                    $pdo->prepare($sqlU)->execute($params);
                }
            }

            log_activity($pdo, 'EDIT_PURCHASE', "Mengedit pembelian ID: $id (Invoice: {$data['invoice_no']})");
            $pdo->commit();
            header("Location: purchases_report.php?msg=Pembelian berhasil diperbarui");
            exit;
        } catch (Exception $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg_err = "Gagal memperbarui: " . $e->getMessage();
        }
    }
}

$suppliers = $pdo->query("SELECT kode, nama FROM suppliers ORDER BY nama")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__.'/includes/header.php';

// Prepare rows for JS
$jsRows = [];
foreach ($currentDetails as $d) {
    $jsRows[] = [
        'kode' => $d['item_kode'],
        'nama' => $d['nama'],
        'unit' => $d['unit'] ?? 'pcs',
        'qty'  => (int)$d['qty'],
        'harga_beli' => (int)$d['harga_beli'],
        'harga_jual1' => (int)($d['harga_jual1'] ?? 0),
        'harga_jual2' => (int)($d['harga_jual2'] ?? 0),
        'harga_jual3' => (int)($d['harga_jual3'] ?? 0),
        'harga_jual4' => (int)($d['harga_jual4'] ?? 0),
    ];
}
?>

<style>
/* Same styles as purchases.php for consistency */
.modal-hidden{display:none;}
.modal-overlay{
  position:fixed; inset:0; background:rgba(15,23,42,0.85);
  display:flex; align-items:flex-start; justify-content:center;
  padding-top:80px; z-index:120;
}
.modal-card{
  width:min(960px, 96vw); background:#020617; border-radius:.75rem;
  border:1px solid #1e293b; box-shadow:0 20px 60px rgba(0,0,0,.75);
  overflow:hidden; display:flex; flex-direction:column; max-height:80vh;
  color:#e5e7eb;
}
.modal-header{
  padding:.75rem 1rem; border-bottom:1px solid #1f2937;
  display:flex; align-items:center; gap:.75rem;
}
.modal-header input{
  flex:1;font-size:.95rem;padding:.5rem .65rem;border-radius:.5rem;
  border:1px solid #283548;background:#020617;color:#e5e7eb;
}
.modal-body{padding:.4rem 1rem 1rem;overflow:auto;}
#itemSearchTable{width:100%;border-collapse:collapse;font-size:.88rem;}
#itemSearchTable th,#itemSearchTable td{border:1px solid #1f2937;padding:.35rem .45rem;}
.table-scroll{max-height:50vh; overflow-y:auto; border:1px solid #1f2937; border-radius:.6rem;}
.table-scroll thead th{position:sticky; top:0; z-index:2; background:#0f172a;}
.right{text-align:right}
</style>

<article>
  <header style="display:flex; justify-content:space-between; align-items:center;">
    <h3>Ubah Pembelian</h3>
    <a href="purchases_report.php" class="secondary" style="font-size:.8rem;">← Kembali</a>
  </header>

  <?php if (isset($msg_err)): ?>
    <mark style="background:#fee2e2; color:#b91c1c; display:block; margin-bottom:1rem;"><?= htmlspecialchars($msg_err) ?></mark>
  <?php endif; ?>

  <form id="purchaseForm" method="post">
    <input type="hidden" name="id" value="<?= (int)$purchase['id'] ?>">
    <input type="hidden" name="payload" id="payload">

    <div class="grid" style="margin-bottom:1rem;">
      <label>No. Faktur
        <input type="text" name="invoice_no" id="invoice_no" value="<?= htmlspecialchars($purchase['invoice_no'] ?? '') ?>" required>
      </label>
      <label>Supplier
        <select name="supplier_kode" id="supplier_kode" required>
          <?php foreach($suppliers as $s): ?>
            <option value="<?= $s['kode'] ?>" <?= $s['kode'] === $purchase['supplier_kode'] ? 'selected' : '' ?>><?= htmlspecialchars($s['nama']) ?></option>
          <?php endforeach; ?>
        </select>
      </label>
      <label>Lokasi
        <select name="location" id="location" required>
          <option value="gudang" <?= $purchase['location'] === 'gudang' ? 'selected' : '' ?>>Gudang</option>
          <option value="toko" <?= $purchase['location'] === 'toko' ? 'selected' : '' ?>>Toko</option>
        </select>
      </label>
      <label>Tanggal
        <input type="date" name="purchase_date" id="purchase_date" value="<?= $purchase['purchase_date'] ?: date('Y-m-d') ?>">
      </label>
    </div>

    <article style="background:rgba(15,23,42,.2); padding:.8rem;">
      <label>Scan / Barcode / Nama Barang (F2 untuk cari)
        <input id="barcode" placeholder="Ketik lalu Enter" autocomplete="off">
      </label>
    </article>

    <div class="table-scroll">
      <table class="table-small" id="purchaseTable">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th class="right" style="width:80px">Qty</th>
            <th class="right" style="width:120px">Hrg Beli</th>
            <th class="right" style="width:100px">HJ1</th>
            <th class="right" style="width:100px">HJ2</th>
            <th class="right" style="width:100px">HJ3</th>
            <th class="right" style="width:100px">HJ4</th>
            <th class="right" style="width:130px">Total</th>
            <th style="width:60px">Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <th colspan="8" class="right">Subtotal</th>
            <th class="right" id="subtotal">0</th>
            <th></th>
          </tr>
          <tr>
            <th colspan="8" class="right">Diskon (Nominal)</th>
            <th class="right"><input type="number" id="discount_val" value="<?= (int)$purchase['discount'] ?>" style="width:100%; text-align:right; margin:0; padding:.2rem;"></th>
            <th></th>
          </tr>
          <tr>
            <th colspan="8" class="right">PPN (Nominal)</th>
            <th class="right"><input type="number" id="tax_val" value="<?= (int)$purchase['tax'] ?>" style="width:100%; text-align:right; margin:0; padding:.2rem;"></th>
            <th></th>
          </tr>
          <tr>
            <th colspan="8" class="right">Grand Total</th>
            <th class="right" id="gtotal">0</th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <label style="margin-top:1rem;">Keterangan
        <input type="text" id="note" value="<?= htmlspecialchars($purchase['note'] ?? '') ?>">
    </label>

    <button type="submit" style="margin-top:1rem;">Simpan Perubahan</button>
  </form>
</article>

<!-- Modal Search -->
<div id="itemSearchModal" class="modal-hidden">
  <div class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Cari Barang</h3>
        <input type="text" id="itemSearchInput" placeholder="Ketik kata kunci...">
        <button type="button" id="itemSearchClose">Tutup</button>
      </div>
      <div class="modal-body">
        <table id="itemSearchTable">
          <thead><tr><th>Kode</th><th>Nama</th><th class="right">HJ1</th></tr></thead>
          <tbody id="itemSearchBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
const rows = <?= json_encode($jsRows) ?>;
const tbody = document.querySelector('#purchaseTable tbody');
const barcodeEl = document.getElementById('barcode');
const discardValEl = document.getElementById('discount_val');
const taxValEl = document.getElementById('tax_val');

function formatID(n){ return new Intl.NumberFormat('id-ID').format(n); }

function recalc(){
    let sub = 0;
    rows.forEach(r => sub += (r.qty * r.harga_beli));
    const disc = parseInt(discardValEl.value || 0);
    const tax = parseInt(taxValEl.value || 0);
    const grand = sub - disc + tax;

    document.getElementById('subtotal').textContent = formatID(sub);
    document.getElementById('gtotal').textContent = formatID(grand);
}

function render(){
    tbody.innerHTML = '';
    rows.forEach((r, idx) => {
        const tr = document.createElement('tr');
        tr.innerHTML = `
            <td>${r.kode}</td>
            <td>${r.nama}</td>
            <td><input type="number" value="${r.qty}" class="row-qty" data-idx="${idx}" style="width:70px; text-align:right; margin:0;"></td>
            <td><input type="number" value="${r.harga_beli}" class="row-hb" data-idx="${idx}" style="text-align:right; margin:0;"></td>
            <td><input type="number" value="${r.harga_jual1}" class="row-hj1" data-idx="${idx}" style="text-align:right; margin:0;"></td>
            <td><input type="number" value="${r.harga_jual2}" class="row-hj2" data-idx="${idx}" style="text-align:right; margin:0;"></td>
            <td><input type="number" value="${r.harga_jual3}" class="row-hj3" data-idx="${idx}" style="text-align:right; margin:0;"></td>
            <td><input type="number" value="${r.harga_jual4}" class="row-hj4" data-idx="${idx}" style="text-align:right; margin:0;"></td>
            <td class="right">${formatID(r.qty * r.harga_beli)}</td>
            <td><button type="button" class="del-btn" data-idx="${idx}">✕</button></td>
        `;
        tbody.appendChild(tr);
    });
    recalc();

    // Bind events
    tbody.querySelectorAll('input').forEach(inp => {
        inp.oninput = (e) => {
            const idx = e.target.dataset.idx;
            const val = parseInt(e.target.value || 0);
            if(e.target.classList.contains('row-qty')) rows[idx].qty = val;
            if(e.target.classList.contains('row-hb')) rows[idx].harga_beli = val;
            if(e.target.classList.contains('row-hj1')) rows[idx].harga_jual1 = val;
            if(e.target.classList.contains('row-hj2')) rows[idx].harga_jual2 = val;
            if(e.target.classList.contains('row-hj3')) rows[idx].harga_jual3 = val;
            if(e.target.classList.contains('row-hj4')) rows[idx].harga_jual4 = val;
            recalc();
            // update total cell in this row locally
            const tr = e.target.closest('tr');
            tr.querySelector('td:nth-last-child(2)').textContent = formatID(rows[idx].qty * rows[idx].harga_beli);
        };
    });
    tbody.querySelectorAll('.del-btn').forEach(btn => {
        btn.onclick = (e) => {
            rows.splice(e.target.dataset.idx, 1);
            render();
        };
    });
}

barcodeEl.onkeypress = async (e) => {
    if(e.key === 'Enter'){
        e.preventDefault();
        const q = barcodeEl.value.trim();
        if(!q) return;
        const res = await fetch('api/get_item.php?q=' + encodeURIComponent(q));
        const item = await res.json();
        if(item && item.kode){
            const exist = rows.find(r => r.kode === item.kode);
            if(exist) {
                exist.qty++;
            } else {
                rows.push({
                    kode: item.kode,
                    nama: item.nama,
                    unit: item.unit || 'pcs',
                    qty: 1,
                    harga_beli: parseInt(item.harga_beli || 0),
                    harga_jual1: parseInt(item.harga_jual1 || 0),
                    harga_jual2: parseInt(item.harga_jual2 || 0),
                    harga_jual3: parseInt(item.harga_jual3 || 0),
                    harga_jual4: parseInt(item.harga_jual4 || 0),
                });
            }
            render();
            barcodeEl.value = '';
        } else {
            alert('Item tidak ditemukan');
        }
    }
};

discardValEl.oninput = recalc;
taxValEl.oninput = recalc;

document.getElementById('purchaseForm').onsubmit = function(e){
    const payload = {
        invoice_no: document.getElementById('invoice_no').value,
        supplier_kode: document.getElementById('supplier_kode').value,
        location: document.getElementById('location').value,
        purchase_date: document.getElementById('purchase_date').value,
        discount: parseInt(discardValEl.value || 0),
        tax: parseInt(taxValEl.value || 0),
        note: document.getElementById('note').value,
        items: rows
    };
    document.getElementById('payload').value = JSON.stringify(payload);
};

// Search handling (Simplified)
const modal = document.getElementById('itemSearchModal');
const searchInput = document.getElementById('itemSearchInput');
const searchBody = document.getElementById('itemSearchBody');

window.onkeydown = (e) => {
    if(e.key === 'F2') {
        e.preventDefault();
        modal.classList.remove('modal-hidden');
        searchInput.focus();
    }
    if(e.key === 'Escape') modal.classList.add('modal-hidden');
};
document.getElementById('itemSearchClose').onclick = () => modal.classList.add('modal-hidden');

searchInput.onkeypress = async (e) => {
    if(e.key === 'Enter'){
        const res = await fetch('api/search_items.php?q=' + encodeURIComponent(searchInput.value));
        const data = await res.json();
        searchBody.innerHTML = '';
        data.forEach(it => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${it.kode}</td><td>${it.nama}</td><td class="right">${formatID(it.harga_jual1)}</td>`;
            tr.onclick = () => {
                barcodeEl.value = it.kode;
                modal.classList.add('modal-hidden');
                barcodeEl.focus();
                // trigger enter
                barcodeEl.dispatchEvent(new KeyboardEvent('keypress', {'key':'Enter'}));
            };
            searchBody.appendChild(tr);
        });
    }
};

render();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
