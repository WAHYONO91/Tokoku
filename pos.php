<?php
// ===== BOOTSTRAP WAJIB =====
require_once __DIR__.'/config.php';
require_login();
require_once __DIR__.'/includes/header.php';

// ===== DEBUG (matikan di produksi) =====
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ===== SETTINGS =====
$setting = [];
try {
  $qset = $pdo->query("SELECT * FROM settings WHERE id=1");
  if ($qset) $setting = $qset->fetch(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $setting = [];
}

// Legacy koef (poin per rupiah)
$legacy_coef       = (float)($setting['points_per_rupiah'] ?? 0);
$coef_umum         = (float)($setting['points_per_rupiah_umum']   ?? ($legacy_coef ?: 0));
$coef_grosir       = (float)($setting['points_per_rupiah_grosir'] ?? ($legacy_coef ?: 0));

// Kolom baru (rupiah per 1 poin)
$rp_point_umum_db   = (int)($setting['rupiah_per_point_umum']   ?? 0);
$rp_point_grosir_db = (int)($setting['rupiah_per_point_grosir'] ?? 0);

// Fallback aman → dipakai JS
$RPP_UMUM   = $rp_point_umum_db   ?: (($coef_umum   > 0) ? (int)round(1/$coef_umum)   : 0);
$RPP_GROSIR = $rp_point_grosir_db ?: (($coef_grosir > 0) ? (int)round(1/$coef_grosir) : 0);
?>
<style>
/* ===== UI Kartu Total ===== */
.totals-banner{display:flex;gap:.8rem;margin:.6rem 0 3.2rem;flex-wrap:wrap}
.totals-box{flex:1 1 180px;border:1px solid #1f2937;border-radius:12px;padding:.6rem .8rem;background:#0f172a;}
.totals-label{font-size:.8rem;color:#94a3b8;margin-bottom:.25rem}
.totals-number{font-size:1.4rem;font-weight:700;letter-spacing:.5px}
@media (max-width:640px){.totals-number{font-size:1.2rem}}
.badge-jenis{background:#6b7280;color:#fff;padding:.15rem .45rem;border-radius:.4rem;font-size:.78rem}

/* ===== Banner Grand Total ===== */
#grandDisplayWrap{position:sticky; top:0; z-index:10; background:#0f172a; color:#0ff; padding:.5rem .7rem; border:1px solid #1f2937; border-radius:.5rem; margin:.5rem 0;}
#grandDisplay{font-family:ui-monospace,Consolas,Menlo,monospace; font-size:2rem; letter-spacing:.03em;}

/* ===== Footer Panduan Tombol ===== */
.hotkey-footer{
  position: fixed; left: 0; right: 0; bottom: 0; z-index: 20;
  background: #0f172a; color: #e5e7eb; border-top: 1px solid #1f2937;
  padding: .5rem .75rem; font-size: .88rem;
}
.hotkey-footer .inner{
  max-width: 1200px; margin: 0 auto; display: flex; gap: .6rem .9rem;
  flex-wrap: wrap; align-items: center; justify-content: center;
}
.hotkey-footer .title{font-weight:600;margin-right:.25rem;white-space:nowrap;opacity:.9}
.hotkey-footer .chip{
  display:inline-flex;align-items:center;gap:.35rem;padding:.25rem .5rem;
  border:1px solid #1f2937;border-radius:8px;background:#0b1222;white-space:nowrap;
}
.hotkey-footer kbd{
  background:#111827;border:1px solid #374151;border-radius:6px;
  padding:.05rem .35rem; font-family:ui-monospace,Consolas,Menlo,monospace;
  font-size:.8rem; color:#f9fafb;
}
.hotkey-footer.hidden{display:none}
@media print{ .hotkey-footer{ display:none !important; } }

/* ===== Spasi bawah agar konten tidak ketutup footer ===== */
.page-bottom-spacer{height:56px}

/* ===== Toggle Rp/% kecil ===== */
.toggle-unit{
  display:inline-flex;gap:.25rem;margin-left:.35rem;vertical-align:middle
}
.toggle-unit button{
  border:1px solid #1f2937;background:#0b1222;color:#e5e7eb;padding:.1rem .4rem;border-radius:6px;font-size:.78rem;cursor:pointer
}
.toggle-unit button.active{background:#1f2937}
.help-mini{font-size:.78rem;opacity:.8;margin-top:.2rem;display:block}
</style>

<div id="grandDisplayWrap" class="no-print">
  <div style="font-size:.8rem; opacity:.8;">TOTAL (Rp)</div>
  <div id="grandDisplay">0</div>
</div>

<article>
  <h3>Penjualan (POS)</h3>
  <form id="posForm" method="post" action="save_sale.php" onsubmit="return handleSubmit(event)">
    <div class="grid">
      <label>Member (opsional)
        <input list="memberlist" name="member_kode" id="member_kode" placeholder="Ketik kode member" autocomplete="off">
        <datalist id="memberlist">
          <?php
          try {
            $mm = $pdo->query("SELECT kode, nama FROM members ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
            foreach($mm as $m){
              echo '<option value="'.htmlspecialchars($m['kode']).'">'.htmlspecialchars($m['nama']).'</option>';
            }
          } catch (Throwable $e) {}
          ?>
        </datalist>
        <small id="memberJenisInfo" style="display:block;margin-top:.25rem;opacity:.85;">Jenis: <span class="badge-jenis" id="memberJenisBadge">-</span></small>
      </label>
      <label>Shift
        <select name="shift" id="shift">
          <option value="1">1</option>
          <option value="2">2</option>
        </select>
      </label>
      <label>Level Harga
        <select id="levelHarga" name="level_harga_default">
          <option value="1">Harga 1</option>
          <option value="2">Harga 2</option>
          <option value="3">Harga 3</option>
          <option value="4">Harga 4</option>
        </select>
      </label>
    </div>

    <article>
      <header>Scan / ketik barcode lalu tekan Enter</header>
      <input class="pos-input" id="barcode" placeholder="Barcode / Kode Barang" autocomplete="off" autofocus>
    </article>

    <table class="table-small" id="cartTable">
      <thead>
        <tr>
          <th>Kode</th>
          <th>Nama</th>
          <th class="right">Qty</th>
          <th class="right">Harga</th>
          <th class="right">Total</th>
          <th class="no-print">Aksi</th>
        </tr>
      </thead>
      <tbody></tbody>
      <tfoot>
        <tr><th colspan="4" class="right">Subtotal</th><th class="right" id="subtotal">0</th><th></th></tr>
        <tr><th colspan="4" class="right">Diskon</th><th class="right" id="tdiscount">0</th><th></th></tr>
        <tr><th colspan="4" class="right">PPN/Pajak</th><th class="right" id="ttax">0</th><th></th></tr>
        <tr><th colspan="4" class="right">Total</th><th class="right" id="gtotal">0</th><th></th></tr>
      </tfoot>
    </table>

    <section class="totals-banner">
      <div class="totals-box">
        <div class="totals-label">Total Item</div>
        <div id="totalItems" class="totals-number">0</div>
      </div>
      <div class="totals-box">
        <div class="totals-label">Total Bayar</div>
        <div id="totalBayar" class="totals-number">0</div>
      </div>
      <div class="totals-box">
        <div class="totals-label">Poin Didapat</div>
        <div id="poinDidapat" class="totals-number">0</div>
      </div>
    </section>

    <div class="grid">
      <label>Diskon 
        <span class="toggle-unit">
          <button type="button" id="discRpBtn"  class="active">Rp</button>
          <button type="button" id="discPctBtn">%</button>
        </span>
        <input type="number" name="discount" id="discount" min="0" value="0" step="1">
        <small class="help-mini" id="discHelp">≈ 0 (0%)</small>
      </label>
      <label>PPN/Pajak 
        <span class="toggle-unit">
          <button type="button" id="taxRpBtn"  class="active">Rp</button>
          <button type="button" id="taxPctBtn">%</button>
        </span>
        <input type="number" name="tax" id="tax" min="0" value="0" step="1">
        <small class="help-mini" id="taxHelp">≈ 0 (0%)</small>
      </label>
      <label>Tunai
        <input type="number" name="tunai" id="tunai" min="0" value="0">
      </label>
      <label>Kembalian
        <input type="number" id="kembalian" value="0" readonly>
      </label>
    </div>

    <input type="hidden" name="payload" id="payload">
    <button type="submit" class="no-print" id="btnSubmit">Simpan & Cetak</button>
  </form>

  <!-- spacer agar tabel tidak ketutup footer -->
  <div class="page-bottom-spacer"></div>
</article>

<!-- Footer Panduan Tombol -->
<div class="hotkey-footer no-print" id="hotkeyPanel">
  <div class="inner">
    <span class="title">Panduan Tombol:</span>
    <span class="chip"><kbd>Ctrl</kbd>+<kbd>B</kbd> Fokus Barcode</span>
    <span class="chip"><kbd>F6</kbd> Kosongkan & Fokus Barcode</span>
    <span class="chip"><kbd>F2</kbd> Fokus Qty terakhir</span>
    <span class="chip"><kbd>F7</kbd>/<kbd>Shift</kbd>+<kbd>F7</kbd> Level Harga ±</span>
    <span class="chip"><kbd>F8</kbd> Fokus Tunai</span>
    <span class="chip"><kbd>F9</kbd> Fokus Diskon</span>
    <span class="chip"><kbd>F10</kbd> Simpan & Cetak</span>
    <span class="chip"><kbd>Enter</kbd> Diskon→PPN→Tunai→Simpan</span>
    <span class="chip"><kbd>?</kbd> Tampil/Sembunyi</span>
  </div>
</div>

<script>
// ==== Konstanta threshold poin (rupiah per 1 poin) ====
const RUPIAH_PER_POINT_UMUM   = <?= (int)$RPP_UMUM ?>;
const RUPIAH_PER_POINT_GROSIR = <?= (int)$RPP_GROSIR ?>;

// ==== State ====
const cart = [];
let currentMember = { kode: null, jenis: null };

// Mode input: 'rp' atau 'pct'
let discMode = 'rp';
let taxMode  = 'rp';

// ==== Elemen ====
const tbody = document.querySelector('#cartTable tbody');
const subtotalEl = document.getElementById('subtotal');
const grandDisplay=document.getElementById('grandDisplay');
const totalItemsEl = document.getElementById('totalItems');
const totalBayarEl = document.getElementById('totalBayar');
const discountEl=document.getElementById('discount');
const taxEl=document.getElementById('tax');
const tdiscountEl=document.getElementById('tdiscount');
const ttaxEl=document.getElementById('ttax');
const gtotalEl=document.getElementById('gtotal');
const barcodeEl = document.getElementById('barcode');
const tunaiEl = document.getElementById('tunai');
const kembalianEl = document.getElementById('kembalian');
const levelHargaSel = document.getElementById('levelHarga');
const form = document.getElementById('posForm');
const btnSubmit = document.getElementById('btnSubmit');
const memberKodeEl = document.getElementById('member_kode');
const memberJenisBadge = document.getElementById('memberJenisBadge');
const poinDidapatEl = document.getElementById('poinDidapat');
const hotkeyPanel = document.getElementById('hotkeyPanel');

// Toggle Rp/% buttons
const discRpBtn  = document.getElementById('discRpBtn');
const discPctBtn = document.getElementById('discPctBtn');
const taxRpBtn   = document.getElementById('taxRpBtn');
const taxPctBtn  = document.getElementById('taxPctBtn');
const discHelp   = document.getElementById('discHelp');
const taxHelp    = document.getElementById('taxHelp');

// ==== Util ====
function formatRupiah(n){ return new Intl.NumberFormat('id-ID').format(n); }
function setGrand(n){ grandDisplay.textContent = formatRupiah(n); }
function focusBarcode(select=true){
  if(!barcodeEl) return;
  setTimeout(()=>{ barcodeEl.focus(); if(select) barcodeEl.select(); }, 0);
}
function getQueryParam(name){
  const u=new URL(window.location.href); return u.searchParams.get(name);
}

// Konversi antar unit (tidak ubah state, hanya fungsi bantu)
function pctToRp(base, pct){ return Math.floor((base>0?base:0) * ((pct>0?pct:0)/100)); }
function rpToPct(base, rp){  if(base<=0) return 0; return ( (rp>0?rp:0) / base ) * 100; }

// Hitung nilai diskon & pajak (rupiah) dari input dan mode
function calcDiscountTaxAmounts(){
  const subtotal = cart.reduce((a,b)=>a+b.qty*b.harga,0);

  let discInput = parseFloat(discountEl.value || '0');
  let taxInput  = parseFloat(taxEl.value || '0');

  let discAmt = (discMode==='pct') ? pctToRp(subtotal, discInput) : Math.floor(discInput);
  if (discAmt > subtotal) discAmt = subtotal;

  const taxBase = subtotal - discAmt;
  let taxAmt = (taxMode==='pct') ? pctToRp(taxBase, taxInput) : Math.floor(taxInput);
  if (taxAmt < 0) taxAmt = 0;

  return { subtotal, discAmt, taxAmt, taxBase, discInput, taxInput };
}

// Update teks bantuan (konversi balik)
function updateHelps(){
  const { subtotal, discAmt, taxAmt, taxBase, discInput, taxInput } = calcDiscountTaxAmounts();
  // Diskon
  const discPctView = (discMode==='pct') ? discInput : rpToPct(subtotal, discAmt);
  discHelp.textContent = `≈ ${formatRupiah(discAmt)} (${(discPctView||0).toFixed(2)}%)`;
  // PPN
  const taxPctView  = (taxMode==='pct') ? taxInput : rpToPct(taxBase, taxAmt);
  taxHelp.textContent  = `≈ ${formatRupiah(taxAmt)} (${(taxPctView||0).toFixed(2)}%)`;
}

// ==== Reset tender fields ====
function resetTender(){
  if(discountEl){ discountEl.value = 0; }
  if(taxEl){ taxEl.value = 0; }
  if(tunaiEl){ tunaiEl.value = 0; }
  if(kembalianEl){ kembalianEl.value = 0; }
  renderCart(); // update total, konversi, dsb
  hitungKembalian();
}

// ==== Init ====
document.addEventListener('DOMContentLoaded', ()=>{
  focusBarcode(true);
  const shouldReset = sessionStorage.getItem('posResetOnReturn') === '1';
  const resetParam  = getQueryParam('reset') === '1';
  if (shouldReset || resetParam) {
    resetTender();
    sessionStorage.removeItem('posResetOnReturn');
  }
});
window.addEventListener('pageshow', ()=>{ resetTender(); });

// ==== Hotkeys Global ====
document.addEventListener('keydown', (e)=>{
  if(e.ctrlKey && (e.key==='b'||e.key==='B')){ e.preventDefault(); focusBarcode(true); return; }
  if(e.key === 'F6'){ e.preventDefault(); barcodeEl.value=''; focusBarcode(true); return; }
  if(e.key === 'F2'){
    e.preventDefault();
    const allQty = document.querySelectorAll('.qtyInput');
    if(allQty.length>0){ const last=allQty[allQty.length-1]; last.focus(); last.select(); }
    return;
  }
  if(e.key === 'F7'){
    e.preventDefault();
    let v = parseInt(levelHargaSel.value||'1') || 1;
    if(e.shiftKey){ v = (v-2+4)%4 + 1; } else { v = (v%4)+1; }
    levelHargaSel.value = String(v);
    renderCart();
    return;
  }
  if(e.key === 'F8'){ e.preventDefault(); tunaiEl.focus(); tunaiEl.select && tunaiEl.select(); return; }
  if(e.key === 'F9'){ e.preventDefault(); discountEl.focus(); discountEl.select && discountEl.select(); return; }
  if(e.key === 'F10'){ e.preventDefault(); handleSubmit(); return; }
  if(e.key === '?' || (e.shiftKey && e.key === '/')){
    const tag = (e.target && e.target.tagName) ? e.target.tagName.toLowerCase() : '';
    if(tag!=='input' && tag!=='textarea'){
      e.preventDefault();
      hotkeyPanel.classList.toggle('hidden');
    }
  }
});

// ==== Member jenis & API ====
async function fetchMemberJenis(kode){
  if(!kode){ currentMember={kode:null,jenis:null}; updateMemberBadge(); renderCart(); return; }
  try{
    const res = await fetch('/tokoapp/api/get_member.php?kode='+encodeURIComponent(kode));
    if(!res.ok) throw new Error('HTTP '+res.status);
    const m = await res.json();
    let jenis = (m && m.jenis) ? String(m.jenis).toLowerCase().trim() : null;
    if(jenis!=='umum' && jenis!=='grosir') jenis = 'umum';
    currentMember = { kode: m?.kode || kode, jenis };
  }catch(e){
    currentMember = { kode: kode, jenis: 'umum' };
  }
  updateMemberBadge();
  renderCart();
}
function updateMemberBadge(){
  let txt='-'; let bg='#6b7280';
  if(currentMember.jenis==='grosir'){ txt='Grosir'; bg='#1d4ed8'; }
  else if(currentMember.jenis==='umum'){ txt='Umum'; bg='#6b7280'; }
  memberJenisBadge.textContent = txt;
  memberJenisBadge.style.background = bg;
}
function thresholdUntukJenis(jenis){
  return (jenis==='grosir') ? (RUPIAH_PER_POINT_GROSIR||0) : (RUPIAH_PER_POINT_UMUM||0);
}

// ==== Barcode → tambah item ====
barcodeEl.addEventListener('keydown', async (e)=>{
  if(e.key === 'Enter'){
    e.preventDefault();
    const code = barcodeEl.value.trim();
    if(!code) return;
    const res = await fetch('/tokoapp/api/get_item.php?q='+encodeURIComponent(code));
    const item = await res.json();
    if(item && item.kode){
      addToCart(item, true);
    } else {
      alert('Barang tidak ditemukan');
      focusBarcode(true);
    }
    barcodeEl.value='';
  }
});
memberKodeEl.addEventListener('change', ()=> fetchMemberJenis(memberKodeEl.value.trim()));
memberKodeEl.addEventListener('blur',   ()=> fetchMemberJenis(memberKodeEl.value.trim()));

// ==== Toggle Rp/% ====
function activateDisc(mode){
  discMode = mode;
  discRpBtn.classList.toggle('active', mode==='rp');
  discPctBtn.classList.toggle('active', mode==='pct');
  renderCart();
}
function activateTax(mode){
  taxMode = mode;
  taxRpBtn.classList.toggle('active', mode==='rp');
  taxPctBtn.classList.toggle('active', mode==='pct');
  renderCart();
}
discRpBtn.onclick  = ()=> activateDisc('rp');
discPctBtn.onclick = ()=> activateDisc('pct');
taxRpBtn.onclick   = ()=> activateTax('rp');
taxPctBtn.onclick  = ()=> activateTax('pct');

function addToCart(item, focusQty=false){
  const level = parseInt(levelHargaSel.value||'1');
  const harga = parseInt(item['harga_jual'+level]) || 0;
  const exist = cart.find(r=>r.kode===item.kode && r.level===level);
  if(exist){ exist.qty+=1; } else { cart.push({kode:item.kode, nama:item.nama, qty:1, level, harga}); }
  renderCart(focusQty);
}

function renderCart(focusLastQty=false){
  tbody.innerHTML=''; let subtotal=0; let totalItems=0;
  cart.forEach((r,idx)=>{
    const total=r.qty*r.harga; subtotal+=total; totalItems+=r.qty;
    const tr=document.createElement('tr');
    tr.innerHTML=`
      <td>${r.kode}</td>
      <td>${r.nama}</td>
      <td class="right"><input type="number" min="1" value="${r.qty}" style="width:4.3rem" data-idx="${idx}" class="qtyInput"></td>
      <td class="right">${formatRupiah(r.harga)}</td>
      <td class="right">${formatRupiah(total)}</td>
      <td class="no-print"><button data-idx="${idx}" class="outline contrast delBtn">Hapus</button></td>`;
    tbody.appendChild(tr);
  });

  // Hitung diskon/pajak rupiah dari mode
  const { discAmt, taxAmt, subtotal: sub } = calcDiscountTaxAmounts();

  subtotalEl.textContent = formatRupiah(sub);
  tdiscountEl.textContent = formatRupiah(discAmt);
  ttaxEl.textContent = formatRupiah(taxAmt);

  const grand=sub - discAmt + taxAmt;
  gtotalEl.textContent = formatRupiah(grand);
  totalItemsEl && (totalItemsEl.textContent = formatRupiah(totalItems));
  totalBayarEl && (totalBayarEl.textContent = formatRupiah(grand));
  setGrand(Math.max(0,grand));
  updatePoinDisplay(grand);
  hitungKembalian();
  updateHelps(); // tampilkan konversi balik

  bindRowEvents();
  if (focusLastQty) {
    const allQty=document.querySelectorAll('.qtyInput');
    if(allQty.length>0){ const last=allQty[allQty.length-1]; last.focus(); last.select(); }
  }
}

function updatePoinDisplay(grandTotal=null){
  if(grandTotal===null){
    const { subtotal:sub, discAmt, taxAmt } = calcDiscountTaxAmounts();
    grandTotal = sub - discAmt + taxAmt;
  }
  const jenis=currentMember.jenis||'umum';
  const T=thresholdUntukJenis(jenis);
  let poin=0; if(currentMember.kode && T>0){ poin=Math.floor(grandTotal/T); }
  poinDidapatEl.textContent=formatRupiah(poin);
  return poin;
}

function bindRowEvents(){
  document.querySelectorAll('.qtyInput').forEach(inp=>{
    inp.onchange=(e)=>{
      const idx=parseInt(e.target.dataset.idx);
      const v=parseInt(e.target.value||'1');
      cart[idx].qty = v>0?v:1;
      renderCart();
      focusBarcode(false);
    };
    inp.onkeydown=(e)=>{
      if(e.key==='Enter'){ e.preventDefault(); inp.dispatchEvent(new Event('change')); focusBarcode(true); }
      else if(e.key==='Escape'){ e.preventDefault(); focusBarcode(true); }
    };
  });
  document.querySelectorAll('.delBtn').forEach(btn=>{
    btn.onclick=(e)=>{
      const idx=parseInt(e.target.dataset.idx);
      cart.splice(idx,1);
      renderCart();
      focusBarcode(false);
    }
  });
}

function hitungKembalian(){
  const { subtotal:sub, discAmt, taxAmt } = calcDiscountTaxAmounts();
  let total=sub - discAmt + taxAmt;
  let tunai=parseInt(tunaiEl.value||'0');
  let kembalian=tunai-total;
  kembalianEl.value = kembalian>=0? kembalian:0;
}

// Recalc
tunaiEl.addEventListener('input', ()=>{hitungKembalian();});
discountEl.addEventListener('input', ()=>{renderCart();});
taxEl.addEventListener('input', ()=>{renderCart();});

// Rantai Enter: Diskon → PPN → Tunai → Submit
discountEl.addEventListener('keydown',(e)=>{
  if(e.key==='Enter'){ e.preventDefault(); taxEl.focus(); taxEl.select && taxEl.select(); }
});
taxEl.addEventListener('keydown',(e)=>{
  if(e.key==='Enter'){ e.preventDefault(); tunaiEl.focus(); tunaiEl.select && tunaiEl.select(); }
});
tunaiEl.addEventListener('keydown',(e)=>{
  if(e.key==='Enter'){ e.preventDefault(); handleSubmit(); }
});

// Submit + validasi + set flag reset
function handleSubmit(e){
  if(e) e.preventDefault();
  if(cart.length===0){ alert('Keranjang kosong'); focusBarcode(true); return false; }

  const { subtotal:sub, discAmt, taxAmt } = calcDiscountTaxAmounts();
  const grandTotal=sub - discAmt + taxAmt;

  let tunai=parseInt(tunaiEl.value||'0');
  if(!Number.isFinite(tunai) || tunai<=0){
    alert('Pembayaran tunai belum diinput.');
    tunaiEl.focus(); tunaiEl.select && tunaiEl.select();
    return false;
  }
  if(tunai < grandTotal){
    alert('Pembayaran tunai kurang dari total. Mohon lengkapi pembayaran.');
    tunaiEl.focus(); tunaiEl.select && tunaiEl.select();
    return false;
  }

  const jenis=currentMember.jenis||'umum';
  const T=thresholdUntukJenis(jenis);
  const points_award=(currentMember.kode && T>0)? Math.floor(grandTotal/T):0;

  const payload={
    member_kode: document.getElementById('member_kode').value || null,
    member_jenis: currentMember.kode? jenis : null,
    shift: document.getElementById('shift').value,
    tunai,
    items: cart,
    // Kirim dalam Rupiah agar backend konsisten
    discount: discAmt,
    tax: taxAmt,
    total: grandTotal,
    points_award,
    points_rule:{
      rupiah_per_point_umum:   RUPIAH_PER_POINT_UMUM,
      rupiah_per_point_grosir: RUPIAH_PER_POINT_GROSIR,
      threshold_dipakai: T
    },
    // Informasi tampilan (opsional)
    ui_modes: { discount: discMode, tax: taxMode }
  };
  document.getElementById('payload').value = JSON.stringify(payload);

  try { sessionStorage.setItem('posResetOnReturn', '1'); } catch(_) {}
  form.submit();
  return true;
}
</script>
<?php include __DIR__.'/includes/footer.php'; ?>
