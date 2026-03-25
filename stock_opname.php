<?php
require_once __DIR__.'/config.php';
require_access('STOCK_OPNAME');

require_once __DIR__.'/functions.php';

$msg = '';

// ---------------------------------------------------------
// Proses opname (BATCH)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action']) && $_POST['__action'] === 'save_opname') {
    $location = $_POST['location'] ?? 'gudang';
    $note     = $_POST['note']     ?? '';
    $user_id  = $_SESSION['user']['id'] ?? 0;
    
    $itemsPost   = $_POST['items']   ?? [];
    $sysQtysPost = $_POST['sys_qtys'] ?? [];
    $realQtysPost = $_POST['real_qtys'] ?? [];
    
    if (!is_array($itemsPost) || count($itemsPost) === 0) {
        $msg = 'Belum ada barang di daftar opname.';
    } else {
        $pdo->beginTransaction();
        try {
            // Insert header
            $stmt = $pdo->prepare("INSERT INTO stock_opnames (user_id, location, note) VALUES (?, ?, ?)");
            $stmt->execute([$user_id, $location, $note]);
            $opname_id = $pdo->lastInsertId();
            
            $stmt_item = $pdo->prepare("INSERT INTO stock_opname_items (opname_id, item_kode, qty_system, qty_real, difference) VALUES (?, ?, ?, ?, ?)");
            $stmt_update = $pdo->prepare("UPDATE item_stocks SET qty = ? WHERE item_kode = ? AND location = ?");
            
            foreach ($itemsPost as $i => $kode) {
                $sys_qty  = (int)($sysQtysPost[$i]  ?? 0);
                $real_qty = (int)($realQtysPost[$i] ?? 0);
                $diff     = $real_qty - $sys_qty;
                
                // Save detail
                $stmt_item->execute([$opname_id, $kode, $sys_qty, $real_qty, $diff]);
                
                // Update actual stock
                $stmt_update->execute([$real_qty, $kode, $location]);
                
                // Optional: log mutation if needed, but opname is a special case
            }
            
            $pdo->commit();
            log_activity($pdo, 'STOCK_OPNAME', "Stock Opname #$opname_id di $location (" . count($itemsPost) . " item)");
            $msg = 'Stock Opname berhasil disimpan.';
            
            // Redirect to detail or history
            header("Location: stock_opname.php?msg=" . urlencode($msg));
            exit;
        } catch (Throwable $th) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $msg = 'Gagal menyimpan opname: ' . $th->getMessage();
        }
    }
}

require_once __DIR__.'/includes/header.php';

if (isset($_GET['msg'])) {
    $msg = $_GET['msg'];
}

// ---------------------------------------------------------
// Data dropdown item (datalist)
// ---------------------------------------------------------
$items = $pdo->query('SELECT kode, nama FROM items ORDER BY nama')->fetchAll();

// ---------------------------------------------------------
// Filter riwayat
// ---------------------------------------------------------
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');

$sqlHistory = "
    SELECT so.*, u.username
    FROM stock_opnames so
    LEFT JOIN users u ON u.id = so.user_id
    WHERE DATE(so.tanggal) BETWEEN ? AND ?
    ORDER BY so.tanggal DESC
";
$stmt = $pdo->prepare($sqlHistory);
$stmt->execute([$fromDate, $toDate]);
$history = $stmt->fetchAll();
?>

<style>
  :root {
    --bg-page: #0f172a;
    --card-bg: #111827;
    --card-bd: #1f2937;
    --text-main: #e2e8f0;
    --text-muted: #94a3b8;
    --bg-modal: #020617;
    --bg-input: #0f172a;
    --btn-secondary: #111827;
  }
  [data-theme="light"] {
    --bg-page: #f1f5f9;
    --card-bg: #ffffff;
    --card-bd: #cbd5e1;
    --text-main: #0f172a;
    --text-muted: #475569;
    --bg-modal: #ffffff;
    --bg-input: #ffffff;
    --btn-secondary: #f8fafc;
  }

  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:.7rem; }
  .form-card{border:1px solid var(--card-bd); border-radius:12px; padding:1rem; background:var(--card-bg); margin-bottom:1rem; color:var(--text-main);}
  .form-card label { color: var(--text-main); font-weight: 600; }
  .table-small{ width:100%; border-collapse:collapse; font-size:.82rem; color:var(--text-main);}
  .table-small th,.table-small td{ border:1px solid var(--card-bd); padding:.4rem .5rem; }
  .right{text-align:right}
  .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; margin:.6rem 0; }

  #batchWrap{
    max-height: 300px;
    overflow: auto;
    border:1px solid var(--card-bd);
    border-radius:.6rem;
    background:var(--bg-modal);
    margin-top:.5rem;
  }
  #batchTable thead th{
    position: sticky;
    top: 0;
    z-index: 2;
    background:var(--card-bg);
  }
  #batchTable th, #batchTable td { padding: 8px; }

  .diff-plus { color: #22c55e; font-weight: bold; }
  .diff-minus { color: #ef4444; font-weight: bold; }

  /* Modal */
  .modal-hidden{ display:none; }
  .modal-overlay{
    position:fixed; inset:0;
    background:rgba(15,23,42,0.80);
    display:flex; align-items:flex-start; justify-content:center;
    padding-top:80px; z-index:9999;
  }
  .modal-card{
    width:min(960px, 96vw);
    background:var(--bg-modal);
    border-radius:.75rem;
    border:1px solid var(--card-bd);
    box-shadow:0 20px 60px rgba(0,0,0,.75);
    overflow:hidden;
    display:flex; flex-direction:column;
    max-height:80vh;
    color: var(--text-main);
  }
  .modal-header{
    padding:.75rem 1rem;
    border-bottom:1px solid var(--card-bd);
    display:flex; align-items:center; gap:.75rem;
  }
  .modal-header h3{ margin:0; font-size:.95rem; font-weight:600; color:var(--text-main);}
  .modal-header input{
    flex:1; font-size:.95rem;
    padding:.5rem .65rem;
    border-radius:.5rem;
    border:1px solid var(--card-bd);
    background:var(--bg-input);
    color:var(--text-main);
  }
</style>

<article>
  <h3>Stock Opname (Penyesuaian Stok)</h3>

  <?php if($msg): ?>
    <mark style="display:block;margin-bottom:.6rem;"><?= htmlspecialchars($msg) ?></mark>
  <?php endif; ?>

  <form method="post" class="form-card" id="opnameForm">
    <input type="hidden" name="__action" value="save_opname">

    <div class="grid">
      <label>Lokasi Opname
        <select name="location" id="location" required>
          <option value="gudang">Gudang</option>
          <option value="toko">Toko</option>
        </select>
      </label>
      
      <label>Barang (F2)
        <input type="text" id="item_input" list="itemlist" placeholder="Kode/Barcode/Nama" autocomplete="off">
        <datalist id="itemlist">
          <?php foreach($items as $it): ?>
            <option value="<?= htmlspecialchars($it['kode']) ?>"><?= htmlspecialchars($it['nama']) ?></option>
          <?php endforeach; ?>
        </datalist>
      </label>

      <label>Nama Barang
        <input type="text" id="item_name" readonly value="-">
      </label>

      <label>Stok Sistem
        <input type="number" id="qty_system" readonly value="0">
      </label>

      <label>Stok Real
        <input type="number" id="qty_real" value="0">
      </label>

      <label>Selisih
        <input type="number" id="qty_diff" readonly value="0">
      </label>
    </div>

    <div class="toolbar">
      <button type="button" id="btnAddToBatch">Tambah ke Daftar</button>
      <button type="button" id="btnOpenItemSearch" onclick="openModal()">Cari Barang (F2)</button>
    </div>

    <div style="margin-top:1rem;">
      <label>Catatan
        <input type="text" name="note" placeholder="Contoh: Opname Bulanan Maret">
      </label>
    </div>

    <div style="margin-top:1.5rem">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:.5rem;">
            <strong>Daftar Item Opname</strong>
            <button type="button" id="btnClearBatch" class="secondary" style="padding:.2rem .5rem; font-size:.8rem;">Bersihkan</button>
        </div>
        <div id="batchWrap">
            <table class="table-small" id="batchTable">
                <thead>
                    <tr>
                        <th style="width:40px;">No</th>
                        <th>Kode</th>
                        <th>Nama Barang</th>
                        <th class="right">Stok Sistem</th>
                        <th class="right">Stok Real</th>
                        <th class="right">Selisih</th>
                        <th style="width:50px;">Aksi</th>
                    </tr>
                </thead>
                <tbody id="batchBody">
                    <tr><td colspan="7" class="text-center">Belum ada barang di daftar.</td></tr>
                </tbody>
            </table>
        </div>
        <div id="batchHidden"></div>
        
        <div style="margin-top:1rem; text-align:right;">
            <button type="submit" id="btnSubmit" class="primary">Simpan Stock Opname</button>
        </div>
    </div>
  </form>

  <hr>

  <h4>Riwayat Stock Opname</h4>
  <form method="get" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1rem;">
    <label>Dari <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>"></label>
    <label>Sampai <input type="date" name="to" value="<?= htmlspecialchars($toDate) ?>"></label>
    <button type="submit" style="margin-top:1.5rem;">Filter</button>
  </form>

  <table class="table-small">
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Lokasi</th>
        <th>User</th>
        <th>Catatan</th>
        <th style="width:120px;">Aksi</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$history): ?>
        <tr><td colspan="5">Tidak ada riwayat.</td></tr>
      <?php else: foreach ($history as $h): ?>
        <tr>
          <td><?= date('d/m/Y H:i', strtotime($h['tanggal'])) ?></td>
          <td><?= ucfirst($h['location']) ?></td>
          <td><?= htmlspecialchars($h['username']) ?></td>
          <td><?= htmlspecialchars($h['note']) ?></td>
          <td>
            <a href="stock_opname_detail.php?id=<?= $h['id'] ?>" class="button secondary" style="padding:.2rem .4rem; font-size:.7rem;">Detail</a>
            <a href="stock_opname_print.php?id=<?= $h['id'] ?>" target="_blank" class="button" style="padding:.2rem .4rem; font-size:.7rem;">Cetak</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</article>

<!-- === MODAL SEARCH === -->
<div id="itemSearchModal" class="modal-hidden">
  <div class="modal-overlay">
    <div class="modal-card">
      <div class="modal-header">
        <h3>Cari Barang</h3>
        <input type="text" id="modalInput" placeholder="Ketik nama/kode/barcode lalu Enter...">
        <button type="button" onclick="closeModal()">Tutup</button>
      </div>
      <div class="modal-body">
        <table id="itemSearchTable">
          <thead>
            <tr>
              <th>Kode</th>
              <th>Nama</th>
              <th>Barcode</th>
              <th class="right">Harga</th>
            </tr>
          </thead>
          <tbody id="modalBody"></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script>
let batch = [];
const API_BASE = 'api';

async function fetchStock(kode, location) {
    if (!kode) return;
    try {
        const res = await fetch(`${API_BASE}/item_stock.php?q=${encodeURIComponent(kode)}`);
        const data = await res.json();
        if (data.ok && data.stocks) {
            document.getElementById('qty_system').value = data.stocks[location] || 0;
            updateDiff();
        }
    } catch (e) { console.error(e); }
}

function updateDiff() {
    const sys = parseInt(document.getElementById('qty_system').value) || 0;
    const real = parseInt(document.getElementById('qty_real').value) || 0;
    document.getElementById('qty_diff').value = real - sys;
}

document.getElementById('item_input').onchange = (e) => {
    const kode = e.target.value;
    fetchStock(kode, document.getElementById('location').value);

    // Update Nama Barang field
    let nama = '-';
    const options = document.querySelectorAll('#itemlist option');
    for (let opt of options) {
        if (opt.value === kode) {
            nama = opt.textContent;
            break;
        }
    }
    document.getElementById('item_name').value = nama;
};

document.getElementById('location').onchange = () => {
    fetchStock(document.getElementById('item_input').value, document.getElementById('location').value);
};

document.getElementById('qty_real').oninput = updateDiff;

document.getElementById('btnAddToBatch').onclick = () => {
    const kode = document.getElementById('item_input').value;
    if (!kode) return alert('Pilih barang dulu.');
    
    // Find name from datalist
    let nama = '-';
    const options = document.querySelectorAll('#itemlist option');
    for (let opt of options) {
        if (opt.value === kode) {
            nama = opt.textContent;
            break;
        }
    }
    
    const sys = parseInt(document.getElementById('qty_system').value) || 0;
    const real = parseInt(document.getElementById('qty_real').value) || 0;
    const diff = real - sys;
    
    // Check if already in batch
    const exists = batch.findIndex(i => i.kode === kode);
    if (exists !== -1) {
        batch[exists].qty_real = real;
        batch[exists].diff = diff;
    } else {
        batch.push({ kode, nama, qty_system: sys, qty_real: real, diff });
    }
    
    renderBatch();
    document.getElementById('item_input').value = '';
    document.getElementById('item_name').value = '-';
    document.getElementById('qty_system').value = 0;
    document.getElementById('qty_real').value = 0;
    document.getElementById('qty_diff').value = 0;
    document.getElementById('item_input').focus();
};

function renderBatch() {
    const body = document.getElementById('batchBody');
    const hidden = document.getElementById('batchHidden');
    body.innerHTML = '';
    hidden.innerHTML = '';
    
    if (batch.length === 0) {
        body.innerHTML = '<tr><td colspan="7" class="text-center">Belum ada barang di daftar.</td></tr>';
        return;
    }
    
    batch.forEach((item, idx) => {
        const tr = document.createElement('tr');
        const diffClass = item.diff > 0 ? 'diff-plus' : (item.diff < 0 ? 'diff-minus' : '');
        tr.innerHTML = `
            <td>${idx + 1}</td>
            <td>${item.kode}</td>
            <td>${item.nama}</td>
            <td class="right">${item.qty_system}</td>
            <td class="right">${item.qty_real}</td>
            <td class="right ${diffClass}">${item.diff > 0 ? '+' : ''}${item.diff}</td>
            <td><button type="button" class="secondary" style="padding:.1rem .3rem" onclick="removeFromBatch(${idx})">✕</button></td>
        `;
        body.appendChild(tr);
        
        hidden.innerHTML += `<input type="hidden" name="items[]" value="${item.kode}">`;
        hidden.innerHTML += `<input type="hidden" name="sys_qtys[]" value="${item.qty_system}">`;
        hidden.innerHTML += `<input type="hidden" name="real_qtys[]" value="${item.qty_real}">`;
    });
}

function removeFromBatch(idx) {
    batch.splice(idx, 1);
    renderBatch();
}

document.getElementById('btnClearBatch').onclick = () => {
    if (confirm('Bersihkan daftar?')) {
        batch = [];
        renderBatch();
    }
};

// Modal Logic
function openModal() {
    document.getElementById('itemSearchModal').classList.remove('modal-hidden');
    document.getElementById('modalInput').focus();
}
function closeModal() {
    document.getElementById('itemSearchModal').classList.add('modal-hidden');
}
document.getElementById('modalInput').onkeydown = async (e) => {
    if (e.key === 'Enter') {
        const q = e.target.value;
        const res = await fetch(`${API_BASE}/search_items.php?q=${encodeURIComponent(q)}`);
        const data = await res.json();
        const mBody = document.getElementById('modalBody');
        mBody.innerHTML = '';
        data.forEach(it => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${it.kode}</td><td>${it.nama}</td><td>${it.barcode || ''}</td><td class="right">${it.harga_jual1}</td>`;
            tr.onclick = () => {
                document.getElementById('item_input').value = it.kode;
                document.getElementById('item_name').value = it.nama;
                fetchStock(it.kode, document.getElementById('location').value);
                closeModal();
            };
            tr.style.cursor = 'pointer';
            mBody.appendChild(tr);
        });
    } else if (e.key === 'Escape') closeModal();
};

window.onkeydown = (e) => {
  if (e.key === 'F2') openModal();
};
</script>
<?php require_once __DIR__.'/includes/footer.php'; ?>
