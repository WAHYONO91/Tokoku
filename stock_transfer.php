<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin','kasir']);

require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/functions.php';

// ---------------------------------------------------------
// Pastikan tabel log mutasi ada
// ---------------------------------------------------------
$pdo->exec("
  CREATE TABLE IF NOT EXISTS stock_mutations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_kode VARCHAR(64) NOT NULL,
    from_loc  VARCHAR(32) NOT NULL,
    to_loc    VARCHAR(32) NOT NULL,
    qty       INT NOT NULL DEFAULT 0,
    created_by VARCHAR(64) DEFAULT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX (item_kode),
    INDEX (created_at)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
");

$msg = '';

// ---------------------------------------------------------
// Proses mutasi (BATCH)
// ---------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['__action']) && $_POST['__action'] === 'mutate') {

  $from = $_POST['from'] ?? 'gudang';
  $to   = $_POST['to']   ?? 'toko';
  $user = $_SESSION['user']['username'] ?? 'system';

  $itemsPost = $_POST['items'] ?? [];
  $qtysPost  = $_POST['qtys']  ?? [];

  if ($from === $to) {
    $msg = 'Lokasi asal dan tujuan tidak boleh sama.';
  } elseif (!is_array($itemsPost) || !is_array($qtysPost) || count($itemsPost) === 0) {
    $msg = 'Belum ada barang di daftar mutasi.';
  } else {

    // Normalisasi + validasi list (gabung jika kode sama)
    $batch = [];
    $errors = [];

    for ($i=0; $i<count($itemsPost); $i++) {
      $rawItem = trim((string)($itemsPost[$i] ?? ''));
      $qty     = max(0, (int)($qtysPost[$i] ?? 0));

      if ($rawItem === '' || $qty <= 0) continue;

      // Validasi item ada (kode/barcode)
      $st = $pdo->prepare("SELECT kode FROM items WHERE kode = ? OR barcode = ? LIMIT 1");
      $st->execute([$rawItem, $rawItem]);
      $kode = (string)$st->fetchColumn();

      if ($kode === '') {
        $errors[] = "Barang tidak ditemukan: ".htmlspecialchars($rawItem);
        continue;
      }

      if (!isset($batch[$kode])) $batch[$kode] = 0;
      $batch[$kode] += $qty;
    }

    if (!empty($errors)) {
      $msg = "Gagal: ".implode(' | ', $errors);
    } elseif (empty($batch)) {
      $msg = 'Daftar mutasi kosong / qty tidak valid.';
    } else {

      // Cek stok semua dulu (biar gak setengah jalan)
      $insufficient = [];
      foreach ($batch as $kode => $qty) {
        $stok = (int)get_stock($pdo, $kode, $from);
        if ($stok < $qty) {
          $insufficient[] = "$kode (butuh $qty, sisa $stok di $from)";
        }
      }

      if ($insufficient) {
        $msg = "Stok tidak cukup: ".implode(', ', $insufficient);
      } else {
        $pdo->beginTransaction();
        try {
          $ins = $pdo->prepare("INSERT INTO stock_mutations (item_kode, from_loc, to_loc, qty, created_by, created_at)
                                VALUES (?,?,?,?,?,NOW())");

          foreach ($batch as $kode => $qty) {
            adjust_stock($pdo, $kode, $from, -$qty);
            adjust_stock($pdo, $kode, $to,   +$qty);
            $ins->execute([$kode, $from, $to, $qty, $user]);
          }

          $pdo->commit();
          $msg = 'Mutasi batch berhasil: '.count($batch).' barang.';
        } catch (Throwable $th) {
          if ($pdo->inTransaction()) $pdo->rollBack();
          $msg = 'Gagal mutasi batch: '.$th->getMessage();
        }
      }
    }
  }
}

// ---------------------------------------------------------
// Data dropdown item (datalist)
// ---------------------------------------------------------
$items = $pdo->query('SELECT kode,nama FROM items ORDER BY nama')->fetchAll();

// ---------------------------------------------------------
// Filter riwayat
// ---------------------------------------------------------
$fromDate = $_GET['from'] ?? date('Y-m-01');
$toDate   = $_GET['to']   ?? date('Y-m-d');
$q        = trim($_GET['q'] ?? '');

$params = [$fromDate, $toDate];
$where  = "WHERE DATE(m.created_at) BETWEEN ? AND ?";

if ($q !== '') {
  $where .= " AND (m.item_kode LIKE ? OR i.nama LIKE ?)";
  $like = '%'.$q.'%';
  $params[] = $like;
  $params[] = $like;
}

$sqlList = "
  SELECT m.*, i.nama AS item_nama
  FROM stock_mutations m
  LEFT JOIN items i ON i.kode = m.item_kode
  $where
  ORDER BY m.created_at DESC, m.id DESC
  LIMIT 500
";
$listStmt = $pdo->prepare($sqlList);
$listStmt->execute($params);
$mutasi = $listStmt->fetchAll();
?>

<style>
  .grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,1fr)); gap:.7rem; }
  .form-card{border:1px solid #1f2937; border-radius:12px; padding:1rem; background:#0f172a; margin-bottom:1rem;}
  .table-small{ width:100%; border-collapse:collapse; font-size:.82rem;}
  .table-small th,.table-small td{ border:1px solid #1f2937; padding:.4rem .5rem; }
  .right{text-align:right}
  .toolbar{ display:flex; gap:.5rem; flex-wrap:wrap; margin:.6rem 0; }

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
    background:#020617;
    border-radius:.75rem;
    border:1px solid #1e293b;
    box-shadow:0 20px 60px rgba(0,0,0,.75);
    overflow:hidden;
    display:flex; flex-direction:column;
    max-height:80vh;
  }
  .modal-header{
    padding:.75rem 1rem;
    border-bottom:1px solid #1f2937;
    display:flex; align-items:center; gap:.75rem;
  }
  .modal-header h3{ margin:0; font-size:.95rem; font-weight:600; }
  .modal-header small{ font-size:.75rem; color:#9bb0c9; }
  .modal-header input{
    flex:1; font-size:.95rem;
    padding:.5rem .65rem;
    border-radius:.5rem;
    border:1px solid #283548;
    background:#020617;
    color:#e2e8f0;
  }
  .modal-header button{
    padding:.45rem .75rem;
    border-radius:.45rem;
    border:1px solid #374151;
    background:#111827;
    color:#e2e8f0;
    cursor:pointer;
    font-size:.85rem;
  }
  .modal-body{ padding:.4rem 1rem 1rem; overflow:auto; }
  .modal-footer{
    padding:.5rem 1rem .7rem;
    border-top:1px solid #111827;
    font-size:.75rem;
    color:#9bb0c9;
    display:flex;
    justify-content:space-between;
    gap:.5rem;
  }

  #itemSearchTable{
    width:100%;
    border-collapse:collapse;
    font-size:.88rem;
  }
  #itemSearchTable th,#itemSearchTable td{
    border:1px solid #1f2937;
    padding:.35rem .45rem;
    white-space:nowrap;
  }
  #itemSearchTable th{
    background:#020617;
    position:sticky;
    top:0;
    z-index:1;
  }
  #itemSearchTable tbody tr:nth-child(odd){ background:#020814; }
  #itemSearchTable tbody tr:hover{ background:#0b162a; cursor:pointer; }
</style>

<article>
  <h3>Mutasi Gudang ⇄ Toko (Batch)</h3>

  <?php if($msg): ?>
    <mark style="display:block;margin-bottom:.6rem;"><?= htmlspecialchars($msg) ?></mark>
  <?php endif; ?>

  <form method="post" class="form-card" id="mutasiForm">
    <input type="hidden" name="__action" value="mutate">

    <div class="grid">
      <label>Barang (F2 untuk popup)
        <input
          type="text"
          name="item_kode"
          id="item_kode"
          list="itemlist"
          placeholder="Ketik kode/barcode/nama (atau tekan F2)"
          autocomplete="off"
        >
        <datalist id="itemlist">
          <?php foreach($items as $it){ ?>
            <option value="<?= htmlspecialchars($it['kode']) ?>">
              <?= htmlspecialchars($it['nama'].' ('.$it['kode'].')') ?>
            </option>
          <?php } ?>
        </datalist>
      </label>

      <label>Dari
        <select name="from" id="from_loc">
          <option value="gudang">Gudang</option>
          <option value="toko">Toko</option>
        </select>
      </label>
      <label>Ke
        <select name="to" id="to_loc">
          <option value="toko">Toko</option>
          <option value="gudang">Gudang</option>
        </select>
      </label>
      <label>Qty
        <input type="number" id="qty_input" min="1" value="1">
      </label>
    </div>

    <div class="toolbar">
      <button type="button" id="btnAddToBatch">Tambah ke daftar</button>
      <button type="submit" id="btnSubmitBatch">Proses Mutasi (Batch)</button>
      <button type="button" id="btnOpenItemSearch">Cari Barang (F2)</button>
    </div>

    <div style="color:#9bb0c9;font-size:.82rem;margin-top:.5rem">
      Tips: Double klik barang di popup untuk mengisi field Barang, lalu klik "Tambah ke daftar".
    </div>

    <!-- TABEL BATCH -->
    <div style="margin-top:.9rem">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:.5rem;flex-wrap:wrap;">
        <strong>Daftar Barang yang Akan Dimutasi</strong>
        <button type="button" id="btnClearBatch" style="padding:.35rem .6rem;border-radius:.45rem;border:1px solid #374151;background:#111827;color:#e2e8f0;cursor:pointer;">
          Hapus Semua
        </button>
      </div>

      <table class="table-small" style="margin-top:.5rem" id="batchTable">
        <thead>
          <tr>
            <th style="width:40px;">No</th>
            <th style="width:160px;">Kode</th>
            <th>Nama</th>
            <th class="right" style="width:120px;">Qty</th>
            <th style="width:90px;">Aksi</th>
          </tr>
        </thead>
        <tbody id="batchBody">
          <tr><td colspan="5">Belum ada barang di daftar.</td></tr>
        </tbody>
      </table>

      <!-- HIDDEN INPUTS UNTUK POST -->
      <div id="batchHidden"></div>
    </div>

  </form>

  <hr style="border:0;border-top:1px solid #1f2937;margin:1rem 0;">

  <form method="get" class="no-print" style="display:flex;gap:.5rem;flex-wrap:wrap;margin:.6rem 0 .8rem;">
    <label>Dari
      <input type="date" name="from" value="<?= htmlspecialchars($fromDate) ?>">
    </label>
    <label>Sampai
      <input type="date" name="to" value="<?= htmlspecialchars($toDate) ?>">
    </label>
    <label>Cari (Kode/Nama)
      <input type="text" name="q" placeholder="misal: GUL / Gula" value="<?= htmlspecialchars($q) ?>">
    </label>
    <button type="submit">Tampilkan</button>
    <button type="button" onclick="window.print()">Print</button>
  </form>

  <table class="table-small">
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Kode</th>
        <th>Nama Barang</th>
        <th>Dari</th>
        <th>Ke</th>
        <th class="right">Qty</th>
        <th>Petugas</th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$mutasi): ?>
        <tr><td colspan="7">Belum ada data mutasi pada rentang ini.</td></tr>
      <?php else: foreach ($mutasi as $m): ?>
        <tr>
          <td><?= date('d-m-Y H:i', strtotime($m['created_at'])) ?></td>
          <td><?= htmlspecialchars($m['item_kode']) ?></td>
          <td><?= htmlspecialchars($m['item_nama'] ?? '-') ?></td>
          <td><?= htmlspecialchars(ucfirst($m['from_loc'])) ?></td>
          <td><?= htmlspecialchars(ucfirst($m['to_loc'])) ?></td>
          <td class="right"><?= number_format((int)$m['qty'], 0, ',', '.') ?></td>
          <td><?= htmlspecialchars($m['created_by'] ?? '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</article>

<!-- === POPUP PENCARIAN BARANG (F2) === -->
<div id="itemSearchModal" class="modal-hidden">
  <div class="modal-overlay">
    <div class="modal-card" role="dialog" aria-modal="true">
      <div class="modal-header">
        <div>
          <h3>Pencarian Barang</h3>
          <small>Cari kode/barcode/nama. Enter untuk cari. F2 / Esc untuk tutup.</small>
        </div>
        <input type="text" id="itemSearchInput" placeholder="Ketik kata kunci, contoh: gula">
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
          <tbody id="itemSearchBody"></tbody>
        </table>
      </div>
      <div class="modal-footer">
        <span>Double-klik baris untuk memilih barang.</span>
        <span>Total data: <span id="itemSearchCount">0</span> barang</span>
      </div>
    </div>
  </div>
</div>

<script>
(function(){
  // =========================================
  // KONFIG: SESUAIKAN BASE PATH APP LU
  // =========================================
  const API_BASE = '/tokoapp/api'; // <-- ganti kalau folder beda

  // =========================
  // ELEMEN FORM
  // =========================
  const form       = document.getElementById('mutasiForm');
  const itemKodeEl = document.getElementById('item_kode');
  const qtyEl      = document.getElementById('qty_input');
  const fromEl     = document.getElementById('from_loc');
  const toEl       = document.getElementById('to_loc');
  const btnSubmit  = document.getElementById('btnSubmitBatch');

  // =========================
  // ELEMEN MODAL PENCARIAN
  // =========================
  const btnOpen    = document.getElementById('btnOpenItemSearch');
  const modalWrap  = document.getElementById('itemSearchModal');
  const modalInput = document.getElementById('itemSearchInput');
  const modalBtn   = document.getElementById('itemSearchBtn');
  const modalClose = document.getElementById('itemSearchClose');
  const modalBody  = document.getElementById('itemSearchBody');
  const modalCount = document.getElementById('itemSearchCount');

  // =========================
  // ELEMEN BATCH
  // =========================
  const btnAddToBatch = document.getElementById('btnAddToBatch');
  const btnClearBatch = document.getElementById('btnClearBatch');
  const batchBody     = document.getElementById('batchBody');
  const batchHidden   = document.getElementById('batchHidden');

  if (!form || !itemKodeEl) {
    console.error('mutasiForm / item_kode tidak ditemukan');
    return;
  }

  function formatID(n){ return new Intl.NumberFormat('id-ID').format(n||0); }

  // =========================
  // INFO STOK (PREVIEW)
  // =========================
  const stockBox = document.createElement('div');
  stockBox.id = 'stockInfo';
  stockBox.style.marginTop = '.35rem';
  stockBox.style.fontSize = '.82rem';
  stockBox.style.color = '#9ca3af';
  stockBox.style.lineHeight = '1.35';
  stockBox.innerHTML = `
    <div>Stok Gudang: - | Stok Toko: -</div>
    <div>Stok Asal: - | Stok Tujuan: - | Sisa Setelah Mutasi: -</div>
    <div id="stockWarn" style="margin-top:.25rem;color:#fca5a5;display:none;">
      Qty melebihi stok lokasi asal.
    </div>
  `;
  itemKodeEl.insertAdjacentElement('afterend', stockBox);
  const warnEl = stockBox.querySelector('#stockWarn');

  let lastStock = { gudang: null, toko: null };

  function getFromStock(){
    const from = (fromEl && fromEl.value) ? fromEl.value : 'gudang';
    if (from === 'gudang') return lastStock.gudang;
    if (from === 'toko')   return lastStock.toko;
    return null;
  }
  function getToStock(){
    const to = (toEl && toEl.value) ? toEl.value : 'toko';
    if (to === 'gudang') return lastStock.gudang;
    if (to === 'toko')   return lastStock.toko;
    return null;
  }

  function updatePreview(){
    const gudang = lastStock.gudang;
    const toko   = lastStock.toko;

    const fromStock = getFromStock();
    const toStock   = getToStock();

    const qty = Math.max(0, parseInt(qtyEl?.value || '0', 10) || 0);

    const line1 = `Stok Gudang: ${gudang === null ? '-' : formatID(gudang)} | Stok Toko: ${toko === null ? '-' : formatID(toko)}`;
    let line2 = `Stok Asal: ${fromStock === null ? '-' : formatID(fromStock)} | Stok Tujuan: ${toStock === null ? '-' : formatID(toStock)} | Sisa Setelah Mutasi: -`;

    if (fromStock !== null) {
      const sisa = fromStock - qty;
      line2 = `Stok Asal: ${formatID(fromStock)} | Stok Tujuan: ${toStock === null ? '-' : formatID(toStock)} | Sisa Setelah Mutasi: ${formatID(sisa)}`;
    }

    stockBox.children[0].textContent = line1;
    stockBox.children[1].textContent = line2;

    const invalid = (fromStock !== null) && (qty > fromStock) && qty > 0;
    if (invalid) {
      warnEl.style.display = 'block';
    } else {
      warnEl.style.display = 'none';
    }
  }

  // =========================
  // FETCH STOK (AUTO SAAT INPUT)
  // =========================
  let timer = null;
  let lastQuery = '';
  let lastReqId = 0;

  async function fetchStock(q){
    q = (q || '').trim();
    if (!q) {
      lastStock = { gudang: null, toko: null };
      updatePreview();
      return;
    }

    if (q === lastQuery) return;
    lastQuery = q;

    const reqId = ++lastReqId;

    try{
      const res = await fetch(API_BASE + '/item_stock.php?q=' + encodeURIComponent(q));
      const data = await res.json();

      if (reqId !== lastReqId) return;

      if (data && data.ok){
        lastStock.gudang = parseInt(data?.stocks?.gudang, 10) || 0;
        lastStock.toko   = parseInt(data?.stocks?.toko, 10) || 0;
      } else {
        lastStock = { gudang: null, toko: null };
      }

      updatePreview();
    }catch(e){
      console.error(e);
      lastStock = { gudang: null, toko: null };
      updatePreview();
    }
  }

  itemKodeEl.addEventListener('input', ()=>{
    clearTimeout(timer);
    timer = setTimeout(()=>fetchStock(itemKodeEl.value), 350);
  });
  itemKodeEl.addEventListener('change', ()=>fetchStock(itemKodeEl.value));
  itemKodeEl.addEventListener('blur',  ()=>fetchStock(itemKodeEl.value));

  fromEl && fromEl.addEventListener('change', updatePreview);
  toEl && toEl.addEventListener('change', updatePreview);
  qtyEl && qtyEl.addEventListener('input', updatePreview);
  qtyEl && qtyEl.addEventListener('change', updatePreview);

  // =========================
  // MODAL SEARCH
  // =========================
  let modalOpen = false;

  function openModal(){
    modalOpen = true;
    modalWrap.classList.remove('modal-hidden');
    modalInput.value = (itemKodeEl.value || '').trim();
    setTimeout(()=>{ modalInput.focus(); modalInput.select && modalInput.select(); }, 0);

    if (modalInput.value.trim() !== '') doSearch(modalInput.value.trim());
    else renderSearch([]);
  }

  function closeModal(){
    modalOpen = false;
    modalWrap.classList.add('modal-hidden');
    itemKodeEl.focus();
    itemKodeEl.select && itemKodeEl.select();
  }

  function renderSearch(list){
    modalBody.innerHTML = '';
    modalCount.textContent = (list || []).length;

    (list || []).forEach(it=>{
      const tr = document.createElement('tr');
      tr.dataset.kode = it.kode || '';
      tr.dataset.barcode = it.barcode || '';
      tr.innerHTML = `
        <td>${it.kode || ''}</td>
        <td>${it.barcode || ''}</td>
        <td>${it.nama || ''}</td>
        <td class="right">${formatID(parseInt(it.harga_jual1||0,10))}</td>
        <td class="right">${formatID(parseInt(it.harga_jual2||0,10))}</td>
        <td class="right">${formatID(parseInt(it.harga_jual3||0,10))}</td>
        <td class="right">${formatID(parseInt(it.harga_jual4||0,10))}</td>
      `;

      tr.addEventListener('dblclick', ()=>{
        const val = tr.dataset.kode || tr.dataset.barcode || '';
        if (!val) return;

        lastQuery = '';
        itemKodeEl.value = val;
        fetchStock(val);
        closeModal();
      });

      modalBody.appendChild(tr);
    });
  }

  async function doSearch(keyword){
    const q = (keyword || '').trim();
    if (!q){ renderSearch([]); return; }
    try{
      const res = await fetch(API_BASE + '/search_items.php?q=' + encodeURIComponent(q));
      const data = await res.json();
      renderSearch(Array.isArray(data) ? data : []);
    }catch(e){
      console.error(e);
      alert('Gagal mencari barang.');
      renderSearch([]);
    }
  }

  btnOpen && btnOpen.addEventListener('click', openModal);
  modalBtn && modalBtn.addEventListener('click', ()=>doSearch(modalInput.value));
  modalClose && modalClose.addEventListener('click', closeModal);

  modalInput && modalInput.addEventListener('keydown', (e)=>{
    if (e.key === 'Enter'){ e.preventDefault(); doSearch(modalInput.value); }
    if (e.key === 'Escape'){ e.preventDefault(); closeModal(); }
  });

  window.addEventListener('keydown', (e)=>{
    if (!modalOpen && e.key === 'F2'){ e.preventDefault(); openModal(); }
    else if (modalOpen && (e.key === 'F2' || e.key === 'Escape')){ e.preventDefault(); closeModal(); }
  });

  // =========================
  // BATCH LIST
  // =========================
  let batchMap = {}; // {kode: {kode,nama,qty}}

  function getItemNameByKode(kode){
    const opts = document.querySelectorAll('#itemlist option');
    for (const o of opts) {
      if ((o.value || '').trim() === kode) {
        const t = (o.textContent || '').trim();
        const idx = t.lastIndexOf('(');
        if (idx > 0) return t.slice(0, idx).trim();
        return t || '-';
      }
    }
    return '-';
  }

  function renderBatch(){
    const keys = Object.keys(batchMap);
    batchBody.innerHTML = '';
    batchHidden.innerHTML = '';

    if (keys.length === 0) {
      batchBody.innerHTML = '<tr><td colspan="5">Belum ada barang di daftar.</td></tr>';
      return;
    }

    let hiddenHtml = '';

    keys.forEach((kode, idx)=>{
      const it = batchMap[kode];
      const tr = document.createElement('tr');
      tr.innerHTML = `
        <td>${idx+1}</td>
        <td>${kode}</td>
        <td>${it.nama || '-'}</td>
        <td class="right">
          <input type="number" min="1" value="${it.qty}" data-kode="${kode}" class="batchQty"
                 style="width:95px;padding:.25rem .35rem;border:1px solid #283548;border-radius:.35rem;background:#020617;color:#e2e8f0;text-align:right;">
        </td>
        <td>
          <button type="button" data-del="${kode}"
            style="padding:.25rem .45rem;border-radius:.35rem;border:1px solid #374151;background:#111827;color:#e2e8f0;cursor:pointer;">
            Hapus
          </button>
        </td>
      `;
      batchBody.appendChild(tr);

      hiddenHtml += `<input type="hidden" name="items[]" value="${kode}">`;
      hiddenHtml += `<input type="hidden" name="qtys[]" value="${it.qty}">`;
    });

    batchHidden.innerHTML = hiddenHtml;

    batchBody.querySelectorAll('.batchQty').forEach(inp=>{
      inp.addEventListener('input', ()=>{
        const kode = inp.dataset.kode;
        const v = Math.max(1, parseInt(inp.value || '1', 10) || 1);
        inp.value = v;
        batchMap[kode].qty = v;
        renderBatch(); // refresh hidden qtys
      });
    });

    batchBody.querySelectorAll('button[data-del]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        const kode = btn.getAttribute('data-del');
        delete batchMap[kode];
        renderBatch();
      });
    });
  }

  async function normalizeToKode(raw){
    raw = (raw || '').trim();
    if (!raw) return '';
    try{
      const res = await fetch(API_BASE + '/search_items.php?q=' + encodeURIComponent(raw));
      const data = await res.json();
      if (Array.isArray(data) && data.length > 0) {
        return (data[0].kode || '').trim();
      }
    }catch(e){}
    return raw; // fallback: backend yang validasi
  }

  btnAddToBatch && btnAddToBatch.addEventListener('click', async ()=>{
    const raw = (itemKodeEl.value || '').trim();
    const qty = Math.max(1, parseInt(qtyEl?.value || '1', 10) || 1);

    if (!raw) { alert('Isi barang dulu.'); itemKodeEl.focus(); return; }

    const kode = await normalizeToKode(raw);
    if (!kode) { alert('Barang tidak ditemukan.'); return; }

    if (!batchMap[kode]) {
      batchMap[kode] = { kode, nama: getItemNameByKode(kode), qty: qty };
    } else {
      batchMap[kode].qty += qty;
    }

    renderBatch();

    itemKodeEl.value = '';
    qtyEl.value = 1;
    lastQuery = '';
    lastStock = { gudang:null, toko:null };
    updatePreview();
    itemKodeEl.focus();
  });

  btnClearBatch && btnClearBatch.addEventListener('click', ()=>{
    if (!confirm('Hapus semua daftar batch?')) return;
    batchMap = {};
    renderBatch();
  });

  form.addEventListener('submit', (e)=>{
    const keys = Object.keys(batchMap);
    if (keys.length === 0) {
      e.preventDefault();
      alert('Daftar mutasi masih kosong.');
      return;
    }
    renderBatch();
    if (btnSubmit) btnSubmit.disabled = true; // cegah double submit
  });

  // init
  renderBatch();
  updatePreview();

})();
</script>

<?php include __DIR__.'/includes/footer.php'; ?>
