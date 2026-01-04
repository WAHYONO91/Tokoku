<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin','kasir']);
require_once __DIR__.'/includes/header.php';

$suppliers = $pdo->query("SELECT kode, nama FROM suppliers ORDER BY nama")->fetchAll();

// ---------------------------------------------------------
// Ambil harga pembelian terakhir per barang
// ---------------------------------------------------------
$lastBuyMap = [];
try {
  $qLast = $pdo->query("
    SELECT pi.item_kode, pi.harga_beli
    FROM purchase_items pi
    JOIN (
      SELECT item_kode, MAX(id) AS max_id
      FROM purchase_items
      GROUP BY item_kode
    ) t ON t.max_id = pi.id
  ");
  while($r = $qLast->fetch(PDO::FETCH_ASSOC)){
    $lastBuyMap[$r['item_kode']] = (int)$r['harga_beli'];
  }
} catch (Throwable $e) {
  $lastBuyMap = [];
}
?>
<style>
/* === POPUP PENCARIAN BARANG (F2) === */
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
  color:#e5e7eb; font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif;
}
.modal-header{
  padding:.75rem 1rem; border-bottom:1px solid #1f2937;
  display:flex; align-items:center; gap:.75rem;
}
.modal-header h3{margin:0;font-size:.95rem;font-weight:600;}
.modal-header small{font-size:.75rem;color:#9ca3af;}
.modal-header input{
  flex:1;font-size:.95rem;padding:.5rem .65rem;border-radius:.5rem;
  border:1px solid #283548;background:#020617;color:#e5e7eb;
}
.modal-header button{
  padding:.45rem .75rem;border-radius:.45rem;border:1px solid #374151;
  background:#111827;color:#e5e7eb;cursor:pointer;font-size:.85rem;
}
.modal-header button:active{transform:translateY(1px);}
.modal-body{padding:.4rem 1rem 1rem;overflow:auto;}
#itemSearchTable{
  width:100%;border-collapse:collapse;font-size:.88rem;
}
#itemSearchTable th,#itemSearchTable td{
  border:1px solid #1f2937;padding:.35rem .45rem;white-space:nowrap;
}
#itemSearchTable th{
  background:#020617;position:sticky;top:0;z-index:1;
}
#itemSearchTable tbody tr:nth-child(odd){background:#020814;}
#itemSearchTable tbody tr:nth-child(even){background:#020617;}
#itemSearchTable tbody tr:hover{background:#0b162a;cursor:pointer;}
.modal-footer{
  padding:.5rem 1rem .7rem;border-top:1px solid #111827;font-size:.75rem;
  color:#9ca3af;display:flex;justify-content:space-between;gap:.5rem;
}

/* =========================================================
   PENYESUAIAN: TABEL PEMBELIAN SCROLL + FOKUS INPUT TERBARU
   ========================================================= */
.table-scroll{
  max-height:55vh;            /* tinggi tabel. ubah kalau mau */
  overflow-y:auto;
  border:1px solid #1f2937;
  border-radius:.6rem;
}
.table-scroll thead th{
  position:sticky;
  top:0;
  z-index:2;
  background:#0f172a;
}
</style>

<article>
  <h3>Pembelian</h3>

  <form id="purchaseForm" method="post" action="save_purchase.php">
    <div class="grid" style="margin-bottom:1rem;">
      <label>No. Faktur / Invoice Supplier
        <input type="text" name="invoice_no" id="invoice_no" placeholder="boleh dikosongkan, akan dibuat otomatis">
      </label>

      <label>Supplier
        <select name="supplier_kode" id="supplier_kode" required>
          <option value="">-- Pilih Supplier --</option>
          <?php foreach($suppliers as $s): ?>
            <option value="<?=$s['kode']?>"><?=htmlspecialchars($s['nama'])?></option>
          <?php endforeach; ?>
        </select>
      </label>

      <label>Lokasi Stok Masuk
        <select name="location" id="location" required>
          <option value="">-- Pilih Lokasi Stok Masuk --</option>
          <option value="gudang">Gudang</option>
          <option value="toko">Toko</option>
        </select>
      </label>

      <label>Tanggal
        <input type="date" name="purchase_date" id="purchase_date" value="<?=date('Y-m-d')?>">
      </label>
    </div>

    <article style="background:rgba(15,23,42,.3);">
      <header>Scan / Ketik Barcode / Nama Barang lalu Enter (F2 untuk cari barang)</header>
      <input
        id="barcode"
        placeholder="Barcode / Kode / Nama Barang"
        autofocus
        list="itemlist"
      >

      <datalist id="itemlist">
        <?php
        try {
          $items = $pdo->query("
            SELECT kode, nama
            FROM items
            ORDER BY nama ASC
            LIMIT 300
          ")->fetchAll(PDO::FETCH_ASSOC);

          foreach($items as $it){
            echo '<option value="'.htmlspecialchars($it['kode']).'">'.
                    htmlspecialchars($it['nama']).
                 '</option>';
          }
        } catch (Throwable $e) {
          // sesuaikan query di atas kalau struktur beda
        }
        ?>
      </datalist>
    </article>

    <!-- =======================================================
         PENYESUAIAN: bungkus tabel dengan div scroll wrapper
         ======================================================= -->
    <div class="table-scroll" style="margin-top:1rem;">
      <table class="table-small" id="purchaseTable" style="margin:0;">
        <thead>
          <tr>
            <th>Kode</th>
            <th>Nama</th>
            <th>Satuan</th>
            <th class="right">Qty</th>
            <th class="right">Harga Beli</th>
            <th class="right">HJ1</th>
            <th class="right">HJ2</th>
            <th class="right">HJ3</th>
            <th class="right">HJ4</th>
            <th class="right">Total</th>
            <th class="no-print">Aksi</th>
          </tr>
        </thead>
        <tbody></tbody>
        <tfoot>
          <tr>
            <th colspan="9" class="right">Subtotal</th>
            <th class="right" id="subtotal">0</th>
            <th></th>
          </tr>
          <tr>
            <th colspan="9" class="right">Diskon (Rp)</th>
            <th class="right" id="tdiscount">0</th>
            <th></th>
          </tr>
          <tr>
            <th colspan="9" class="right">PPN (Rp)</th>
            <th class="right" id="ttax">0</th>
            <th></th>
          </tr>
          <tr>
            <th colspan="9" class="right">Total</th>
            <th class="right" id="gtotal">0</th>
            <th></th>
          </tr>
        </tfoot>
      </table>
    </div>

    <div class="grid" style="margin-top:1rem;">
      <label>Diskon (%)
        <input type="number" id="discount_pct" min="0" max="100" step="0.01" value="0">
      </label>
      <label>PPN (%)
        <input type="number" id="tax_pct" min="0" max="100" step="0.01" value="0">
      </label>
      <label>Keterangan
        <input type="text" name="note" id="note" placeholder="opsional">
      </label>
    </div>

    <input type="hidden" name="discount" id="discount" value="0">
    <input type="hidden" name="tax" id="tax" value="0">
    <input type="hidden" name="payload" id="payload">

    <button type="submit" class="no-print" style="margin-top:1rem;">Simpan Pembelian</button>
  </form>
</article>

<!-- === POPUP PENCARIAN BARANG (F2) === -->
<div id="itemSearchModal" class="modal-hidden">
  <div class="modal-overlay">
    <div class="modal-card" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div>
          <h3>Pencarian Barang</h3>
          <small>Cari berdasarkan Kode, Barcode, atau Nama. Tekan Enter untuk cari, F2 atau Esc untuk tutup.</small>
        </div>
        <input type="text" id="itemSearchInput" placeholder="Ketik kata kunci, contoh: aqua">
        <button type="button" id="itemSearchBtn">Cari</button>
        <button type="button" id="itemSearchClose">Tutup</button>
      </div>
      <div class="modal-body">
        <table id="itemSearchTable">
          <thead>
            <tr>
              <th style="width:120px;">Kode</th>
              <th style="width:150px;">Barcode</th>
              <th>Nama Barang</th>
              <th style="width:110px;" class="right">Hrg 1</th>
              <th style="width:110px;" class="right">Hrg 2</th>
              <th style="width:110px;" class="right">Hrg 3</th>
              <th style="width:110px;" class="right">Hrg 4</th>
            </tr>
          </thead>
          <tbody id="itemSearchBody">
          </tbody>
        </table>
      </div>
      <div class="modal-footer">
        <span>Double-klik baris untuk memasukkan ke kolom Scan / Barcode, lalu tekan Enter.</span>
        <span>Total data: <span id="itemSearchCount">0</span> barang</span>
      </div>
    </div>
  </div>
</div>

<script>
const lastBuyPriceMap = <?= json_encode($lastBuyMap, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

const rows = [];
const tbody = document.querySelector('#purchaseTable tbody');
const barcodeEl = document.getElementById('barcode');
const subtotalEl = document.getElementById('subtotal');
const tdiscountEl = document.getElementById('tdiscount');
const ttaxEl = document.getElementById('ttax');
const gtotalEl = document.getElementById('gtotal');
const discountPctEl = document.getElementById('discount_pct');
const taxPctEl = document.getElementById('tax_pct');
const discountHidden = document.getElementById('discount');
const taxHidden = document.getElementById('tax');
const tableScrollWrap = document.querySelector('.table-scroll');

function formatID(n){ return new Intl.NumberFormat('id-ID').format(n); }

/* =====================================================
   PENDING QTY (F7) UNTUK ITEM BERIKUTNYA
   ===================================================== */
let pendingQty = 1;

// info kecil di bawah input barcode
const pendingQtyInfo = document.createElement('div');
pendingQtyInfo.id = 'pendingQtyInfo';
pendingQtyInfo.style.marginTop = '.35rem';
pendingQtyInfo.style.fontSize = '.8rem';
pendingQtyInfo.style.color = '#9ca3af';
pendingQtyInfo.textContent = 'Qty input berikutnya: 1 (tekan F7)';
if (barcodeEl) {
  barcodeEl.insertAdjacentElement('afterend', pendingQtyInfo);
}

/* =====================================================
   INFO STOK (Gudang & Toko) SAAT ITEM KETEMU
   ===================================================== */
const stockInfo = document.createElement('div');
stockInfo.id = 'stockInfo';
stockInfo.style.marginTop = '.2rem';
stockInfo.style.fontSize = '.8rem';
stockInfo.style.color = '#9ca3af';
stockInfo.textContent = 'Stok Gudang: - | Stok Toko: -';

if (barcodeEl) {
  // taruh di bawah info qty
  pendingQtyInfo.insertAdjacentElement('afterend', stockInfo);
}

function setStockInfo(gudang, toko){
  const g = Number.isFinite(gudang) ? gudang : 0;
  const t = Number.isFinite(toko) ? toko : 0;
  stockInfo.textContent = `Stok Gudang: ${formatID(g)} | Stok Toko: ${formatID(t)}`;
}

function setPendingQty(v){
  pendingQty = v;
  if (pendingQtyInfo) {
    pendingQtyInfo.textContent = `Qty input berikutnya: ${v} (tekan F7)`;
  }
}

// Hitung ulang subtotal, diskon, PPN, dan total TANPA merender ulang baris (agar navigasi tetap aman)
function recalcFooter(subtotal){
  const discPct = parseFloat(discountPctEl.value || '0');
  const taxPct = parseFloat(taxPctEl.value || '0');

  const discNom = Math.floor(subtotal * (discPct / 100));
  const baseForTax = subtotal - discNom;
  const taxNom = Math.floor(baseForTax * (taxPct / 100));
  const grand = subtotal - discNom + taxNom;

  subtotalEl.textContent = formatID(subtotal);
  tdiscountEl.textContent = formatID(discNom);
  ttaxEl.textContent = formatID(taxNom);
  gtotalEl.textContent = formatID(grand);

  discountHidden.value = discNom;
  taxHidden.value = taxNom;
}

// Dipakai ketika user mengedit qty / harga beli langsung di input (tanpa renderRows)
function recomputeTotalsFromDOM(){
  let subtotal = 0;
  document.querySelectorAll('#purchaseTable tbody tr').forEach((tr, idx) => {
    const qtyInp   = tr.querySelector('.qtyInput');
    const hbeliInp = tr.querySelector('.hbeliInput');
    const qty      = parseInt(qtyInp?.value || '1', 10);
    const hbeli    = parseInt(hbeliInp?.value || '0', 10);
    const lineTotal = (qty > 0 ? qty : 1) * (hbeli >= 0 ? hbeli : 0);
    subtotal += lineTotal;

    const totalCell = tr.querySelector('.lineTotalCell');
    if (totalCell) totalCell.textContent = formatID(lineTotal);
  });
  recalcFooter(subtotal);
}

barcodeEl.addEventListener('keypress', async (e)=>{
  if(e.key === 'Enter'){
    e.preventDefault();
    const code = barcodeEl.value.trim();
    if(!code) return;

    // qty yang akan dipakai untuk input item ini
    const addQty = (pendingQty && pendingQty > 0) ? pendingQty : 1;

    try {
      const res = await fetch('/tokoapp/api/get_item.php?q='+encodeURIComponent(code));
      const item = await res.json();

      if(item && item.kode){

        // === TAMPILKAN STOK SAAT ITEM KETEMU ===
        setStockInfo(
          parseInt(item.stok_gudang || '0', 10),
          parseInt(item.stok_toko || '0', 10)
        );

        const existingIdx = rows.findIndex(r => r.kode === item.kode);
        if (existingIdx >= 0) {
          rows[existingIdx].qty += addQty;
        } else {
          const lastPrev = (typeof lastBuyPriceMap[item.kode] !== 'undefined')
            ? parseInt(lastBuyPriceMap[item.kode], 10)
            : 0;

          const newRow = {
            kode: item.kode,
            nama: item.nama,
            unit: item.unit || item.unit_code || 'pcs',
            qty: addQty,
            harga_beli: parseInt(item.harga_beli || '0', 10),
            harga_beli_last: lastPrev,
            harga_jual1: parseInt(item.harga_jual1 || '0', 10),
            harga_jual2: parseInt(item.harga_jual2 || '0', 10),
            harga_jual3: parseInt(item.harga_jual3 || '0', 10),
            harga_jual4: parseInt(item.harga_jual4 || '0', 10),

            manual_hj1: false,
            manual_hj2: false,
            manual_hj3: false,
            manual_hj4: false
          };

          rows.push(newRow);
        }

        renderRows();

        // setelah dipakai, reset qty item berikutnya kembali 1
        setPendingQty(1);

      } else {
        alert('Barang tidak ditemukan');
        setStockInfo(0, 0);
      }
    } catch(err){
      console.error(err);
      alert('Gagal mengambil data barang');
      setStockInfo(0, 0);
    }

    barcodeEl.value = '';
    focusBarcode(); // <<< PENYESUAIAN: fokus balik ke input
  }
});

function renderRows(){
  tbody.innerHTML = '';
  let subtotal = 0;

  rows.forEach((r,idx)=>{
    const lineTotal = r.qty * r.harga_beli;
    subtotal += lineTotal;

    const tr = document.createElement('tr');
    tr.innerHTML = `
      <td>${r.kode}</td>
      <td>${r.nama}</td>
      <td>${r.unit}</td>
      <td class="right">
        <input type="number" min="1" value="${r.qty}" data-idx="${idx}" class="qtyInput" style="width:4.5rem;">
      </td>
      <td class="right">
        <input type="number" min="0" value="${r.harga_beli}" data-idx="${idx}" class="hbeliInput" style="width:6.5rem;">
        <div class="muted" style="font-size:.75rem;">
          HB sebelumnya: ${ r.harga_beli_last ? formatID(r.harga_beli_last) : '-' }
        </div>
      </td>
      <td class="right">
        <input type="number" min="0" value="${r.harga_jual1}" data-idx="${idx}" class="hjual1Input" style="width:6.5rem;">
      </td>
      <td class="right">
        <input type="number" min="0" value="${r.harga_jual2}" data-idx="${idx}" class="hjual2Input" style="width:6.5rem;">
      </td>
      <td class="right">
        <input type="number" min="0" value="${r.harga_jual3}" data-idx="${idx}" class="hjual3Input" style="width:6.5rem;">
      </td>
      <td class="right">
        <input type="number" min="0" value="${r.harga_jual4}" data-idx="${idx}" class="hjual4Input" style="width:6.5rem;">
      </td>
      <td class="right lineTotalCell">${formatID(lineTotal)}</td>
      <td class="no-print">
        <button type="button" data-idx="${idx}" class="delBtn">Hapus</button>
      </td>
    `;
    tbody.appendChild(tr);
  });

  recalcFooter(subtotal);
  bindRowEvents();

  // =======================================================
  // PENYESUAIAN: auto scroll ke item terbaru + fokus input
  // =======================================================
  if (tableScrollWrap) {
    tableScrollWrap.scrollTop = tableScrollWrap.scrollHeight;
  }
  if (barcodeEl) {
    setTimeout(() => {
      barcodeEl.focus();
      barcodeEl.select && barcodeEl.select();
    }, 0);
  }
}

function bindRowEvents(){
  // Handler qty dan harga beli: update rows + hitung ulang total, TANPA renderRows
  document.querySelectorAll('.qtyInput').forEach(inp=>{
    const handler = (e)=>{
      const idx = parseInt(e.target.dataset.idx);
      let v = parseInt(e.target.value || '1', 10);
      if (!rows[idx]) return;
      if (isNaN(v) || v <= 0) v = 1;
      rows[idx].qty = v;
      e.target.value = v;
      recomputeTotalsFromDOM();
    };
    inp.onchange = handler;
    inp.oninput  = handler;
  });

  document.querySelectorAll('.hbeliInput').forEach(inp=>{
    const handler = (e)=>{
      const idx = parseInt(e.target.dataset.idx);
      let v = parseInt(e.target.value || '0', 10);
      if (!rows[idx]) return;
      if (isNaN(v) || v < 0) v = 0;
      rows[idx].harga_beli = v;
      e.target.value = v;
      recomputeTotalsFromDOM();
    };
    inp.onchange = handler;
    inp.oninput  = handler;
  });

  // Handler harga jual: hanya update rows, tidak perlu hitung total
  function attachHJHandler(selector, fieldName, manualField){
    document.querySelectorAll(selector).forEach(inp=>{
      const handler = (e)=>{
        const idx = parseInt(e.target.dataset.idx);
        if (!rows[idx]) return;

        const raw = e.target.value;
        let v = parseInt(raw, 10);
        if (isNaN(v) || v < 0) v = 0;

        rows[idx][fieldName] = v;
        rows[idx][manualField] = true;
        e.target.value = v;
      };
      inp.onchange = handler;
      inp.oninput  = handler;
    });
  }

  attachHJHandler('.hjual1Input', 'harga_jual1', 'manual_hj1');
  attachHJHandler('.hjual2Input', 'harga_jual2', 'manual_hj2');
  attachHJHandler('.hjual3Input', 'harga_jual3', 'manual_hj3');
  attachHJHandler('.hjual4Input', 'harga_jual4', 'manual_hj4');

  document.querySelectorAll('.delBtn').forEach(btn=>{
    btn.onclick = (e)=>{
      const idx = parseInt(e.target.dataset.idx);
      rows.splice(idx,1);
      renderRows(); // kalau ada hapus baris, kita render ulang
    };
  });

  // Navigasi dengan panah ↑ ↓ ← →
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

        // Atas / Bawah: pindah baris di kolom yang sama
        if (e.key === 'ArrowUp' || e.key === 'ArrowDown') {
          e.preventDefault();
          const dir = (e.key === 'ArrowUp') ? -1 : 1;
          const targetIdx = idx + dir;
          if (targetIdx < 0) return;
          const target = document.querySelector(sel + '[data-idx="'+targetIdx+'"]');
          if (target) {
            target.focus();
            if (typeof target.select === 'function') target.select();
          }
          return;
        }

        // Kiri / Kanan: pindah kolom di baris yang sama
        if (e.key === 'ArrowLeft' || e.key === 'ArrowRight') {
          const dir = (e.key === 'ArrowLeft') ? -1 : 1;
          const newCol = colIndex + dir;
          if (newCol < 0 || newCol >= navSelectors.length) return;

          const targetSel = navSelectors[newCol];
          const target = document.querySelector(targetSel + '[data-idx="'+idx+'"]');
          if (target) {
            e.preventDefault();
            target.focus();
            if (typeof target.select === 'function') target.select();
          }
        }
      });
    });
  });
}

// Sinkron rows dengan DOM sebelum submit (jaga-jaga)
function syncRowsFromDOM(){
  document.querySelectorAll('.qtyInput').forEach(inp=>{
    const idx = parseInt(inp.dataset.idx);
    let v = parseInt(inp.value || '1', 10);
    if (!rows[idx]) return;
    if (isNaN(v) || v <= 0) v = 1;
    rows[idx].qty = v;
  });

  document.querySelectorAll('.hbeliInput').forEach(inp=>{
    const idx = parseInt(inp.dataset.idx);
    let v = parseInt(inp.value || '0', 10);
    if (!rows[idx]) return;
    if (isNaN(v) || v < 0) v = 0;
    rows[idx].harga_beli = v;
  });

  document.querySelectorAll('.hjual1Input').forEach(inp=>{
    const idx = parseInt(inp.dataset.idx);
    let v = parseInt(inp.value || '0', 10);
    if (!rows[idx]) return;
    rows[idx].harga_jual1 = isNaN(v) || v < 0 ? 0 : v;
  });
  document.querySelectorAll('.hjual2Input').forEach(inp=>{
    const idx = parseInt(inp.dataset.idx);
    let v = parseInt(inp.value || '0', 10);
    if (!rows[idx]) return;
    rows[idx].harga_jual2 = isNaN(v) || v < 0 ? 0 : v;
  });
  document.querySelectorAll('.hjual3Input').forEach(inp=>{
    const idx = parseInt(inp.dataset.idx);
    let v = parseInt(inp.value || '0', 10);
    if (!rows[idx]) return;
    rows[idx].harga_jual3 = isNaN(v) || v < 0 ? 0 : v;
  });
  document.querySelectorAll('.hjual4Input').forEach(inp=>{
    const idx = parseInt(inp.dataset.idx);
    let v = parseInt(inp.value || '0', 10);
    if (!rows[idx]) return;
    rows[idx].harga_jual4 = isNaN(v) || v < 0 ? 0 : v;
  });
}

discountPctEl.addEventListener('input', ()=>recomputeTotalsFromDOM());
taxPctEl.addEventListener('input', ()=>recomputeTotalsFromDOM());

document.getElementById('purchaseForm').addEventListener('submit', function(e){
  e.preventDefault();

  syncRowsFromDOM();
  recomputeTotalsFromDOM();

  if(rows.length === 0){
    alert('Detail barang pembelian belum diisi.');
    return;
  }

  // VALIDASI: Supplier & Lokasi wajib dipilih
  const supplierSelect = document.getElementById('supplier_kode');
  const locationSelect = document.getElementById('location');

  if (!supplierSelect.value) {
    alert('Silakan pilih Supplier terlebih dahulu.');
    supplierSelect.focus();
    return;
  }

  if (!locationSelect.value) {
    alert('Silakan pilih Lokasi Stok Masuk terlebih dahulu.');
    locationSelect.focus();
    return;
  }

  const invEl = document.getElementById('invoice_no');
  let invVal = (invEl.value || '').trim();
  if (!invVal) {
    const now = new Date();
    const pad = (n) => n.toString().padStart(2, '0');
    invVal =
      'PB-' +
      now.getFullYear().toString() +
      pad(now.getMonth() + 1) +
      pad(now.getDate()) +
      '-' +
      pad(now.getHours()) +
      pad(now.getMinutes()) +
      pad(now.getSeconds());
    invEl.value = invVal;
  }

  const payload = {
    invoice_no: invVal,
    supplier_kode: supplierSelect.value,
    location: locationSelect.value,
    purchase_date: document.getElementById('purchase_date').value,
    discount: parseInt(document.getElementById('discount').value || '0', 10),
    tax: parseInt(document.getElementById('tax').value || '0', 10),
    note: document.getElementById('note').value || '',
    items: rows.map(r => ({
      kode: r.kode,
      nama: r.nama,
      unit: r.unit,
      qty: r.qty,
      harga_beli: r.harga_beli,
      harga_jual1: r.harga_jual1,
      harga_jual2: r.harga_jual2,
      harga_jual3: r.harga_jual3,
      harga_jual4: r.harga_jual4
    }))
  };
  document.getElementById('payload').value = JSON.stringify(payload);
  this.submit();
});

/* ==============================
   POPUP PENCARIAN BARANG (F2)
   ============================== */
const itemSearchModal  = document.getElementById('itemSearchModal');
const itemSearchInput  = document.getElementById('itemSearchInput');
const itemSearchBtn    = document.getElementById('itemSearchBtn');
const itemSearchClose  = document.getElementById('itemSearchClose');
const itemSearchBody   = document.getElementById('itemSearchBody');
const itemSearchCount  = document.getElementById('itemSearchCount');

let itemSearchOpen = false;

function focusBarcode(){
  if (!barcodeEl) return;
  barcodeEl.focus();
  barcodeEl.select && barcodeEl.select();
}

function openItemSearch() {
  itemSearchOpen = true;
  itemSearchModal.classList.remove('modal-hidden');
  itemSearchInput.value = barcodeEl.value || '';
  setTimeout(() => {
    itemSearchInput.focus();
    itemSearchInput.select && itemSearchInput.select();
  }, 0);

  if (itemSearchInput.value.trim() !== '') {
    doItemSearch(itemSearchInput.value.trim());
  } else {
    renderItemSearchResults([]);
  }
}

function closeItemSearch() {
  itemSearchOpen = false;
  itemSearchModal.classList.add('modal-hidden');
  focusBarcode();
}

function renderItemSearchResults(list) {
  itemSearchBody.innerHTML = '';
  itemSearchCount.textContent = list.length;

  list.forEach((it) => {
    const tr = document.createElement('tr');
    tr.dataset.kode = it.kode || '';
    tr.dataset.barcode = it.barcode || '';
    tr.dataset.nama = it.nama || '';

    tr.innerHTML = `
      <td>${it.kode || ''}</td>
      <td>${it.barcode || ''}</td>
      <td>${it.nama || ''}</td>
      <td class="right">${formatID(parseInt(it.harga_jual1 || 0, 10))}</td>
      <td class="right">${formatID(parseInt(it.harga_jual2 || 0, 10))}</td>
      <td class="right">${formatID(parseInt(it.harga_jual3 || 0, 10))}</td>
      <td class="right">${formatID(parseInt(it.harga_jual4 || 0, 10))}</td>
    `;

    tr.addEventListener('dblclick', () => {
      const valueToUse = tr.dataset.barcode || tr.dataset.kode || '';
      if (valueToUse) {
        barcodeEl.value = valueToUse;
        closeItemSearch();
        focusBarcode();
      }
    });

    itemSearchBody.appendChild(tr);
  });
}

async function doItemSearch(keyword) {
  const q = (keyword || '').trim();
  if (!q) {
    renderItemSearchResults([]);
    return;
  }
  try {
    const res = await fetch('/tokoapp/api/search_items.php?q=' + encodeURIComponent(q));
    const data = await res.json();
    if (Array.isArray(data)) {
      renderItemSearchResults(data);
    } else {
      renderItemSearchResults([]);
    }
  } catch (err) {
    console.error(err);
    alert('Gagal mencari barang.');
    renderItemSearchResults([]);
  }
}

if (itemSearchInput) {
  itemSearchInput.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      doItemSearch(itemSearchInput.value);
    } else if (e.key === 'Escape') {
      e.preventDefault();
      closeItemSearch();
    }
  });
}
if (itemSearchBtn) {
  itemSearchBtn.addEventListener('click', () => {
    doItemSearch(itemSearchInput.value);
  });
}
if (itemSearchClose) {
  itemSearchClose.addEventListener('click', () => {
    closeItemSearch();
  });
}

/* =====================================================
   SHORTCUT KEY GLOBAL:
   - F2: modal cari barang
   - F4: langsung simpan pembelian
   - F7: set qty item berikutnya
   ===================================================== */
const purchaseFormEl = document.getElementById('purchaseForm');

window.addEventListener('keydown', (e) => {
  // jika popup pencarian sedang terbuka
  if (itemSearchOpen) {
    if (e.key === 'F2' || e.key === 'Escape') {
      e.preventDefault();
      closeItemSearch();
    }
    return;
  }

  // F4: langsung simpan pembelian
  if (e.key === 'F4') {
    e.preventDefault();
    if (purchaseFormEl?.requestSubmit) {
      purchaseFormEl.requestSubmit();
    } else {
      // fallback browser lama
      purchaseFormEl?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
    }
    return;
  }

  // F7: set qty untuk item berikutnya sebelum scan/Enter
  if (e.key === 'F7') {
    e.preventDefault();
    const current = pendingQty || 1;
    const input = prompt('Masukkan jumlah (qty) untuk item berikutnya:', String(current));
    if (input === null) {
      focusBarcode();
      return;
    }
    let v = parseInt(input, 10);
    if (isNaN(v) || v <= 0) v = 1;
    setPendingQty(v);
    focusBarcode();
    return;
  }

  // F2: buka modal cari barang
  if (e.key === 'F2') {
    e.preventDefault();
    openItemSearch();
  }
});
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
