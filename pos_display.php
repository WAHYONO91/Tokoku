<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_access('DASHBOARD');

// =======================
// Ambil data pengaturan toko & penukaran poin
// =======================
$setting = $pdo->query("
  SELECT
    store_name,
    theme,
    logo_url,
    rupiah_per_point_umum,
    rupiah_per_point_grosir,
    redeem_rp_per_point_umum,
    redeem_rp_per_point_grosir
  FROM settings
  WHERE id = 1
")->fetch(PDO::FETCH_ASSOC);

$store = $setting['store_name'] ?? 'TOKO';
$app_theme = $setting['theme'] ?? 'dark';
$app_logo = !empty($setting['logo_url']) ? $setting['logo_url'] : '/tokoapp/uploads/logo.jpg';

// 1 poin = berapa Rupiah potongan?
$redeem_umum = (int)($setting['rupiah_per_point_umum'] ?? 0);
if ($redeem_umum <= 0) $redeem_umum = (int)($setting['redeem_rp_per_point_umum'] ?? 100);
if ($redeem_umum <= 0) $redeem_umum = 100;

$redeem_grosir = (int)($setting['rupiah_per_point_grosir'] ?? 0);
if ($redeem_grosir <= 0) $redeem_grosir = (int)($setting['redeem_rp_per_point_grosir'] ?? 25);
if ($redeem_grosir <= 0) $redeem_grosir = 25;
?>
<!doctype html>
<html lang="id" data-theme="<?= htmlspecialchars($app_theme) ?>">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($store) ?> — POS Display</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($app_logo) ?>">

  <style>
    :root{
      color-scheme: <?= $app_theme === 'light' ? 'light' : 'dark' ?>;
      --topbar-h: 48px;
      --gap: .75rem;
      <?php if ($app_theme === 'dark'): ?>
      --card-bg: #0e1726;
      --card-bd: #1f2a3a;
      --page-bg: #0b1220;
      --text: #e2e8f0;
      --muted: #9bb0c9;
      --accent: #7dd3fc;
      --warn: #facc15;
      --danger: #fb7185;
      <?php else: ?>
      --card-bg: #ffffff;
      --card-bd: #cbd5e1;
      --page-bg: #f8fafc;
      --text: #000000;
      --muted: #475569;
      --accent: #0284c7;
      --warn: #eab308;
      --danger: #e11d48;
      <?php endif; ?>
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:var(--page-bg);
      color:var(--text);
      font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;
      line-height:1.35;
    }

    .topbar{
      height:var(--topbar-h);
      position:sticky;top:0;z-index:50;
      display:flex;align-items:center;justify-content:space-between;
      padding:0 .8rem;background: <?= $app_theme === 'dark' ? '#020817' : '#f1f5f9' ?>;border-bottom:1px solid var(--card-bd);
    }
    .brand{font-weight:700;letter-spacing:.02em;font-size:1rem}
    .sub{font-size:.75rem;opacity:.75}
    .clock{
      font-size:.85rem;
      background: <?= $app_theme === 'dark' ? '#0e1726' : '#ffffff' ?>;
      border:1px solid <?= $app_theme === 'dark' ? '#223044' : '#cbd5e1' ?>;
      padding:.2rem .45rem;
      border-radius:.4rem;
      white-space:nowrap
    }

    .wrap{
      max-width:1440px;
      margin:0 auto;
      padding:var(--gap);
      display:flex;
      flex-direction:column;
      gap:.65rem;
    }

    /* =========================
       GRAND DISPLAY (TOTAL/KEMBALIAN)
       ========================= */
    .grand{
      position: sticky;
      top: calc(var(--topbar-h) + .5rem);
      z-index: 40;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.25rem;
      padding: .8rem 1.05rem;
      border-radius: .75rem;
      background: <?= $app_theme === 'dark' ? 'linear-gradient(180deg, #081224 0%, #0b162a 100%)' : 'linear-gradient(180deg, #f0f9ff 0%, #e0f2fe 100%)' ?>;
      border: 1px solid <?= $app_theme === 'dark' ? '#22406188' : '#bae6fd' ?>;
      box-shadow: 0 6px 16px rgba(0,0,0,<?= $app_theme === 'dark' ? '.35' : '.1' ?>), inset 0 0 0 1px rgba(125,211,252,.08);
    }
    .grand .lbl{
      font-size: .86rem;
      letter-spacing: .04em;
      color: <?= $app_theme === 'dark' ? '#8fb8ff' : '#0369a1' ?>;
      opacity: .92;
      text-transform:uppercase;
      font-weight: 600;
    }
    .grand.kembalian .lbl{
      color: var(--warn);
    }
    .grand .num{
      font-family: ui-monospace, Consolas, Menlo, monospace;
      font-weight: 900;
      font-size: clamp(1.7rem, 2.3vw + 1rem, 3.1rem);
      letter-spacing: .03em;
      color: <?= $app_theme === 'dark' ? '#7dd3fc' : '#0369a1' ?>;
      text-shadow: 0 0 6px rgba(125,211,252,<?= $app_theme === 'dark' ? '.8' : '.2' ?>),
                   0 0 14px rgba(56,189,248,<?= $app_theme === 'dark' ? '.55' : '.1' ?>);
    }
    .grand.kembalian .num{
      color: var(--warn);
      text-shadow: 0 0 6px rgba(250,204,21,.75),
                   0 0 16px rgba(250,204,21,.35);
    }

    .grand-meta{
      display:flex;
      gap:.65rem;
      flex-wrap:wrap;
      align-items:stretch;
    }
    .meta-box{
      min-width: 135px;
      padding:.38rem .62rem;
      border-radius:.55rem;
      background: <?= $app_theme === 'dark' ? 'rgba(15,23,42,.8)' : '#f1f5f9' ?>;
      border:1px solid <?= $app_theme === 'dark' ? 'rgba(148,163,184,.45)' : '#cbd5e1' ?>;
    }
    .meta-label{
      font-size:.68rem;
      letter-spacing:.06em;
      text-transform:uppercase;
      color: <?= $app_theme === 'dark' ? '#9ca3af' : '#475569' ?>;
      margin-bottom:.1rem;
      font-weight: 600;
    }
    .meta-value{
      font-family:ui-monospace,Consolas,Menlo,monospace;
      font-size:.98rem;
      font-weight:800;
      color: <?= $app_theme === 'dark' ? '#e5e7eb' : '#000000' ?>;
    }

    .card{
      background:var(--card-bg);
      border:1px solid var(--card-bd);
      border-radius:.6rem;
      padding:.65rem .7rem;
    }

    .layout{
      display:grid;
      gap:var(--gap);
      grid-template-columns:1fr;
    }

    .pos-input{
      width:100%;
      font-size:1.02rem;
      padding:.5rem .6rem;
      border-radius:.5rem;
      border:1px solid var(--card-bd);
      outline:none;
      background: <?= $app_theme === 'dark' ? '#091120' : '#ffffff' ?>;
      color:var(--text);
    }

    /* =========================
       TABLE: muat >=25 baris (max-height di-set via JS)
       ========================= */
    .card-table{ padding:.55rem; }
    .table-wrap{
      width:100%;
      overflow:auto;
      border-radius:.45rem;
      border:1px solid var(--card-bd);
      background: <?= $app_theme === 'dark' ? '#0b1324' : '#ffffff' ?>;
      max-height:none; /* DIKUNCI via JS supaya pas 25 baris */
    }
    table{ width:100%; border-collapse:collapse; font-size:.78rem; }
    th,td{
      border:1px solid var(--card-bd);
      padding:.28rem .38rem;
      vertical-align:middle;
      line-height:1.2;
    }
    th{
      position: sticky;
      top: 0;
      z-index: 2;
      background: <?= $app_theme === 'dark' ? '#0f1a2c' : '#f1f5f9' ?>;
      font-weight:700;
      white-space:nowrap;
    }
    td{ word-break:break-word; }
    tbody tr:nth-child(odd){ background: <?= $app_theme === 'dark' ? '#0b1324' : '#f8fafc' ?> }
    .right{text-align:right}
    tr.active-row td{ background: <?= $app_theme === 'dark' ? '#0b1a34' : '#e0f2fe' ?> !important }

    .col-kode{ width: 140px; }
    .col-qty{ width: 96px; }
    .col-harga{ width: 220px; }
    .col-total{ width: 120px; }
    .col-aksi{ width: 86px; }

    .qtyInput{ width: 5.1rem; text-align:right; font-size:.82rem; padding:.15rem .25rem; }
    .priceRow{ display:flex; align-items:center; justify-content:space-between; gap:.4rem; }
    .priceText{ font-variant-numeric: tabular-nums; }
    .lvlSel{
      background: <?= $app_theme === 'dark' ? '#091120' : '#ffffff' ?>;
      border:1px solid var(--card-bd);
      color:var(--text);
      border-radius:.35rem;
      padding:.2rem .3rem;
      font-size:.82rem;
      min-width: 118px;
    }

    .panel-bottom{ padding: .75rem .75rem; }
    .panel-bottom .kps{
      display:grid;
      grid-template-columns: repeat(3, minmax(240px, 1fr));
      gap:.20rem;
    }
    @media (max-width:1200px){
      .panel-bottom .kps{ grid-template-columns: repeat(2, minmax(240px,1fr)); }
    }
    @media (max-width:640px){
      .panel-bottom .kps{ grid-template-columns: 1fr; }
      .grand{ flex-direction:column; align-items:flex-start; }
      .grand-meta{ width:100%; }
      .meta-box{ flex:1; }
    }

    .panel-bottom input:not([type="checkbox"]):not([type="radio"]),
    .panel-bottom select{
      font-size: 1.0rem;
      padding: .62rem .7rem;
      border-radius: .55rem;
      background: <?= $app_theme === 'dark' ? '#091120' : '#ffffff' ?>;
      border:1px solid var(--card-bd);
      color:var(--text);
    }

    .row{display:flex;gap:.4rem;align-items:center}
    .muted{font-size:.8rem;color:var(--muted)}
    .btn{
      background: <?= $app_theme === 'dark' ? '#1f2b3e' : '#bae6fd' ?>;
      border:1px solid <?= $app_theme === 'dark' ? '#2c3c55' : '#7dd3fc' ?>;
      color: <?= $app_theme === 'dark' ? 'var(--text)' : '#0369a1' ?>;
      padding:.55rem .75rem;
      border-radius:.5rem;
      cursor:pointer;
      font-weight: 500;
    }
    .btn:hover{
        background: <?= $app_theme === 'dark' ? '#2c3c55' : '#7dd3fc' ?>;
        color: <?= $app_theme === 'dark' ? 'var(--text)' : '#0c4a6e' ?>;
    }
    .btn:active{transform:translateY(1px)}
    .btn[disabled]{
      opacity:.55;
      cursor:not-allowed;
    }

    .held-slot{
      font-size:.8rem;
      padding:.25rem .45rem;
      border-radius:.4rem;
      border:1px solid transparent;
      margin-right:.35rem;
    }
    .held-empty{ color:#9ca3af; border-color:transparent; }
    .held-filled{
      color:var(--warn);
      border-color:var(--warn);
      background:rgba(250,204,21,0.07);
    }

    .memberRow{ display:flex; gap:.4rem; align-items:center; }
    .btn-mini{ padding:.55rem .7rem; font-size:.95rem; white-space:nowrap; }

    .table-topinfo{
      display:inline-flex;
      gap:.5rem;
      align-items:center;
      margin: 0 0 .4rem auto;
      font-size:.82rem;
      color: var(--muted);
      text-align:right;
    }
    .table-topinfo strong{
      color: var(--text);
      font-family: ui-monospace,Consolas,Menlo,monospace;
      font-weight: 900;
    }

    tfoot th {
      background: <?= $app_theme === 'dark' ? '#0c1628' : '#e2e8f0' ?>;
      font-weight: 700;
      font-size: 0.86rem;
      position: sticky;
      bottom: 0;
      z-index: 2;
    }
    tfoot th.right { white-space: nowrap; }

    /* =========================
       Overlay Kembalian
       ========================= */
    #changeOverlay{
      display:none;
      position:fixed;
      inset:0;
      z-index:999999;
      background:rgba(0,0,0,.62);
      align-items:center;
      justify-content:center;
      pointer-events:none;
    }
    #changeOverlay.show{ display:flex; }
    #changeOverlay .box{
      background: <?= $app_theme === 'dark' ? '#0e1726' : '#ffffff' ?>;
      border:1px solid <?= $app_theme === 'dark' ? '#223044' : '#cbd5e1' ?>;
      border-radius:14px;
      padding:18px 22px;
      min-width:320px;
      text-align:center;
      box-shadow:0 10px 30px rgba(0,0,0,<?= $app_theme === 'dark' ? '.5' : '.1' ?>);
    }
    #changeOverlay .ttl{
      font-size:.9rem;
      color:#9bb0c9;
      letter-spacing:.06em;
      text-transform:uppercase;
    }
    #changeOverlay .val{
      margin-top:6px;
      font-size:2.2rem;
      font-weight:900;
      color:var(--warn);
      font-family:ui-monospace,Consolas,Menlo,monospace;
      text-shadow: 0 0 10px rgba(250,204,21,.25);
    }
    #changeOverlay .hint{
      margin-top:10px;
      font-size:.85rem;
      color:#9bb0c9;
    }

    @media print{
      .topbar{display:none!important}
      body{background:#fff;color:#000}
      .no-print{display:none!important}
    }
  </style>
</head>
<body>

  <!-- Overlay Kembalian -->
  <div id="changeOverlay" class="no-print" role="dialog" aria-modal="true" aria-label="Kembalian">
    <div class="box">
      <div class="ttl">Kembalian</div>
      <div id="changeValue" class="val">0</div>
      <div class="hint">(Akan hilang otomatis saat scan/input barang berikutnya)</div>
    </div>
  </div>

  <div class="topbar">
    <div>
      <div class="brand"><?= htmlspecialchars($store) ?></div>
      <div class="sub">POS — Tampilan Layar Penuh</div>
    </div>
    <div class="clock"><span id="dateNow"></span> • <span id="clockNow">--:--:--</span></div>
  </div>

  <div class="wrap">
    <div class="grand" id="grandWrap">
      <div>
        <div class="lbl" id="grandLabel">TOTAL (Rp)</div>
        <div id="grandDisplay" class="num">0</div>
      </div>
      <div class="grand-meta">
        <div class="meta-box">
          <div class="meta-label">Total Item</div>
          <div class="meta-value" id="totalItems">0</div>
        </div>
        <div class="meta-box">
          <div class="meta-label">Total Bayar</div>
          <div class="meta-value" id="totalBayar">0</div>
        </div>
      </div>
    </div>

    <div class="card">
      <header style="margin-bottom:.45rem;font-weight:650">
        Scan / ketik barcode / nama barang lalu tekan Enter (F2 untuk cari barang)
      </header>

      <input
        class="pos-input"
        id="barcode"
        placeholder="Barcode / Kode Barang"
        autocomplete="off"
        inputmode="text"
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
                echo '<option value="'.htmlspecialchars($it['kode']).'">'.htmlspecialchars($it['nama']).'</option>';
            }
        } catch (Throwable $e) {}
        ?>
      </datalist>

      <div id="stockInfo" class="muted" style="margin-top:.3rem;font-size:.85rem;">
        Info stok: Gudang 0 • Toko 0 • Total 0
      </div>

      <div id="nextQtyInfo" class="muted" style="margin-top:.12rem;font-size:.85rem;">
        Qty input berikutnya: <strong id="nextQtyValue">1</strong>
      </div>
    </div>

    <div class="layout">

      <div class="card card-table">
        <div class="table-topinfo">
          <span>Baris: <strong id="totalRows">0</strong></span>
          <span>•</span>
          <span>Item: <strong id="totalItemsMini">0</strong></span>
        </div>

        <div class="table-wrap">
          <table id="cartTable">
            <thead>
              <tr>
                <th class="col-kode">Kode</th>
                <th>Nama</th>
                <th class="right col-qty">Qty</th>
                <th class="right col-harga">Harga / Level</th>
                <th class="right col-total">Total</th>
                <th class="col-aksi">Aksi</th>
              </tr>
            </thead>
            <tbody></tbody>
            <tfoot>
              <tr>
                <th colspan="5" class="right">Subtotal</th>
                <th class="right" id="subtotal">0</th>
              </tr>
              <tr>
                <th colspan="5" class="right">Diskon</th>
                <th class="right" id="tdiscount">0</th>
              </tr>
              <tr>
                <th colspan="5" class="right">PPN</th>
                <th class="right" id="ttax">0</th>
              </tr>
              <tr>
                <th colspan="5" class="right">Potongan Poin</th>
                <th class="right" id="tpointdisc">0</th>
              </tr>
              <tr>
                <th colspan="5" class="right"><strong>Total</strong></th>
                <th class="right" id="gtotal"><strong>0</strong></th>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <div class="card panel-bottom" style="display:flex;flex-direction:column;gap:var(--gap)">

        <!-- Member -->
        <div class="kps">
          <label>Member (Kode)
            <div class="memberRow">
              <input list="memberlist" id="member_kode" placeholder="Ketik kode member" autocomplete="off">
              <button type="button" class="btn btn-mini" id="btnMemberSearch" title="Cari Member (Buka Master Member)">Cari</button>
            </div>

            <datalist id="memberlist">
              <?php
              try{
                $mm = $pdo->query("SELECT kode, nama FROM members ORDER BY created_at DESC LIMIT 200")->fetchAll(PDO::FETCH_ASSOC);
                foreach($mm as $m){
                  echo '<option value="'.htmlspecialchars($m['kode']).'">'.htmlspecialchars($m['nama']).'</option>';
                }
              }catch(Throwable $e){}
              ?>
            </datalist>
          </label>

          <label>Nama Member
            <input type="text" id="member_nama" readonly value="">
          </label>

          <label>Poin Tersedia
            <input type="number" id="member_poin" readonly value="0">
          </label>
        </div>

        <!-- Poin, Diskon, PPN -->
        <div class="kps">
          <div>
            <div class="muted">Poin Ditukar</div>
            <div class="row">
              <input type="number" id="poin_ditukar" min="0" value="0" inputmode="numeric">
            </div>
            <div class="muted">Potongan dari Poin: <span id="poin_potongan_view">0</span></div>
          </div>

          <div>
            <div class="muted">Diskon</div>
            <div class="row">
              <input type="number" id="discount" min="0" value="0" inputmode="numeric">
              <select id="discountMode" aria-label="Mode Diskon">
                <option value="rp">Rp</option>
                <option value="pct">%</option>
              </select>
            </div>
          </div>

          <div>
            <div class="muted">PPN</div>
            <div class="row">
              <input type="number" id="tax" min="0" value="0" inputmode="numeric">
              <select id="taxMode" aria-label="Mode Pajak">
                <option value="rp">Rp</option>
                <option value="pct">%</option>
              </select>
            </div>
          </div>
        </div>

        <!-- Metode Pembayaran -->
<div class="kps">
  <div>
    <div class="muted">Metode Pembayaran</div>
    <select id="payMode">
      <option value="cash">Tunai</option>
      <option value="ar">Piutang Member</option>
    </select>
  </div>

  <div>
    <div class="muted">Tunai</div>
    <input type="text" id="tunai" value="0" inputmode="numeric">
  </div>

  <div>
    <div class="muted">Kembalian</div>
    <input type="text" id="kembalian" readonly value="0">
  </div>
</div>
        <!-- Tombol & Parkir -->
        <div class="kps" style="grid-template-columns:1fr">
          <div class="row" style="gap:.65rem;flex-wrap:wrap">
            <button class="btn" id="btnSave" type="button">Simpan &amp; Cetak</button>

            <button class="btn" type="button" data-park="1">Tunda 1</button>
            <button class="btn" type="button" data-park="2">Tunda 2</button>
            <button class="btn" type="button" data-park="3">Tunda 3</button>

            <button class="btn" type="button" data-resume="1">Lanjut 1</button>
            <button class="btn" type="button" data-resume="2">Lanjut 2</button>
            <button class="btn" type="button" data-resume="3">Lanjut 3</button>

            <a class="btn" id="btnReport" href="sales_report.php" target="_blank" rel="noopener">Laporan</a>

            <span class="muted" style="flex:1;min-width:260px">
              F5: Fokus Member • Shift+F5: Cari Member • F6: Barcode • F7: Set Qty Scan Berikutnya •
              F4: Tunai • F8: Poin • F9: Diskon • F10: Simpan • F3: Laporan • F2: Cari Barang • F1: Bayar Cepat
            </span>
          </div>

          <div class="row" id="heldInfoRow" style="margin-top:.25rem;flex-wrap:wrap;">
            <span class="held-slot held-empty" id="heldSlot1">Tunda 1: Kosong</span>
            <span class="held-slot held-empty" id="heldSlot2">Tunda 2: Kosong</span>
            <span class="held-slot held-empty" id="heldSlot3">Tunda 3: Kosong</span>
          </div>

          <div class="row no-print" style="margin-top:.2rem;flex-wrap:wrap;gap:.4rem">
            <button class="btn" type="button" data-clear="1">Hapus Tunda 1</button>
            <button class="btn" type="button" data-clear="2">Hapus Tunda 2</button>
            <button class="btn" type="button" data-clear="3">Hapus Tunda 3</button>
          </div>
        </div>
      </div>

    </div>
  </div>

<script>
  // =========================
  // Jam & tanggal
  // =========================
  (function(){
    const dc=document.getElementById('dateNow'), cc=document.getElementById('clockNow');
    function pad(n){return n<10?'0'+n:n}
    function tick(){
      const now=new Date();
      cc.textContent=pad(now.getHours())+':'+pad(now.getMinutes())+':'+pad(now.getSeconds());
      dc.textContent=now.toLocaleDateString('id-ID',{weekday:'long',year:'numeric',month:'long',day:'numeric'});
    }
    tick(); setInterval(tick,1000);
  })();

  // =========================
  // State & Element refs
  // =========================
  const cart=[], tbody=document.querySelector('#cartTable tbody');
  const subtotalEl=document.getElementById('subtotal');
  const tdiscountEl=document.getElementById('tdiscount');
  const ttaxEl=document.getElementById('ttax');
  const tpointdiscEl=document.getElementById('tpointdisc');
  const gtotalEl=document.getElementById('gtotal');

  const grandWrap=document.getElementById('grandWrap');
  const grandLabel=document.getElementById('grandLabel');
  const grandDisplay=document.getElementById('grandDisplay');

  const totalItemsEl=document.getElementById('totalItems');
  const totalBayarEl=document.getElementById('totalBayar');
  const totalRowsEl = document.getElementById('totalRows');
  const totalItemsMiniEl = document.getElementById('totalItemsMini');

  const barcodeEl=document.getElementById('barcode');
  const stockInfoEl=document.getElementById('stockInfo');

  const discountEl=document.getElementById('discount');
  const discountModeEl=document.getElementById('discountMode');
  const taxEl=document.getElementById('tax');
  const taxModeEl=document.getElementById('taxMode');
  const tunaiEl=document.getElementById('tunai');
  const kembalianEl=document.getElementById('kembalian');
  const payModeEl = document.getElementById('payMode');
  const btnSave=document.getElementById('btnSave');

  const memberKodeEl=document.getElementById('member_kode');
  const memberNamaEl=document.getElementById('member_nama');
  const memberPoinEl=document.getElementById('member_poin');
  const poinDitukarEl=document.getElementById('poin_ditukar');
  const poinPotonganView=document.getElementById('poin_potongan_view');
  const btnMemberSearch=document.getElementById('btnMemberSearch');

  // Qty scan berikutnya (F7)
  let nextScanQty = 1;
  const nextQtyValueEl = document.getElementById('nextQtyValue');

  // Parkir multi slot
  const heldSlots = { 1: null, 2: null, 3: null };
  const heldSlotEls = {
    1: document.getElementById('heldSlot1'),
    2: document.getElementById('heldSlot2'),
    3: document.getElementById('heldSlot3')
  };

  let memberType = null; // 'umum' atau 'grosir'
  let activeIdx = -1;

  const RP_PER_POINT_UMUM   = <?= (int)$redeem_umum ?>;
  const RP_PER_POINT_GROSIR = <?= (int)$redeem_grosir ?>;

  // Quick cash (F1)
  let f1PressCount = 0;
  let f1PressTimer = null;

  // =========================
  // Helpers
  // =========================
  function formatID(n){ return new Intl.NumberFormat('id-ID').format(n); }
  function unformat(s){ return parseInt((s||'0').toString().replace(/[^\d]/g,''))||0; }

  function formatInputAsIDR(inputEl){
    const digits = (inputEl.value || '').replace(/[^\d]/g,'');
    if(!digits){ inputEl.value = ''; return; }
    inputEl.value = formatID(parseInt(digits,10));
  }

  function focusBarcode(){
    if(!barcodeEl) return;
    barcodeEl.focus();
    if (barcodeEl.select) barcodeEl.select();
  }

  function updateNextQtyInfo(){
    if (nextQtyValueEl) nextQtyValueEl.textContent = String(nextScanQty);
  }

  // =========================
  // Kunci tinggi keranjang: tampil 25 baris (presisi)
  // =========================
  function fitCartToRows(visibleRows = 25){
    const wrap = document.querySelector('.table-wrap');
    const table = document.getElementById('cartTable');
    if (!wrap || !table) return;

    const theadRow = table.querySelector('thead tr');
    const tfootRows = table.querySelectorAll('tfoot tr');
    const firstBodyRow = table.querySelector('tbody tr');

    const bodyRowH = firstBodyRow ? firstBodyRow.getBoundingClientRect().height : 28;
    const headH = theadRow ? theadRow.getBoundingClientRect().height : 32;

    let footH = 0;
    tfootRows.forEach(r => footH += r.getBoundingClientRect().height);

    const extra = 6;
    const targetH = Math.ceil(headH + footH + (visibleRows * bodyRowH) + extra);

    wrap.style.maxHeight = targetH + 'px';
  }

  // =========================
  // MODE DISPLAY: TOTAL vs KEMBALIAN
  // =========================
  let displayMode = 'total'; // 'total' | 'kembalian'
  let lastKembalian = 0;

  function setDisplayTotal(){
    displayMode = 'total';
    if (grandWrap) grandWrap.classList.remove('kembalian');
    if (grandLabel) grandLabel.textContent = 'TOTAL (Rp)';
  }

  function setDisplayKembalian(kembali){
    displayMode = 'kembalian';
    lastKembalian = Math.max(0, kembali||0);
    if (grandWrap) grandWrap.classList.add('kembalian');
    if (grandLabel) grandLabel.textContent = 'KEMBALIAN (Rp)';
    if (grandDisplay) grandDisplay.textContent = formatID(lastKembalian);
  }

  // =========================
  // Overlay Kembalian
  // =========================
  const changeOverlay = document.getElementById('changeOverlay');
  const changeValueEl = document.getElementById('changeValue');

  function showKembalianOverlay(kembali){
    if(!changeOverlay || !changeValueEl) return;
    const k = Math.max(0, kembali||0);
    changeValueEl.textContent = formatID(k);
    changeOverlay.classList.add('show');
  }

  function hideKembalianOverlay(){
    if(!changeOverlay) return;
    changeOverlay.classList.remove('show');
  }

  function clearKembalianOnNextInput(){
    if (displayMode === 'kembalian'){
      hideKembalianOverlay();
      setDisplayTotal();
    }
  }

  // =========================
  // PRINT_DONE listener
  // =========================
  let pendingClearAfterPrint = false;

  window.addEventListener('message', (ev) => {
    if (!ev || !ev.data) return;
    if (ev.data.type === 'PRINT_DONE') {
      if (pendingClearAfterPrint) {
        pendingClearAfterPrint = false;
        clearTransaction(true);
      }
      setTimeout(() => focusBarcode(), 0);
    }
  });

  // =========================
  // Item Picker (F2)
  // =========================
  function openItemPicker(){
    const url = '/tokoapp/items.php?pick=1';
    const w = 1200, h = 760;
    const left = Math.max(0, (screen.width  - w) / 2);
    const top  = Math.max(0, (screen.height - h) / 2);

    const win = window.open(
      url,
      'itemPicker',
      `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`
    );
    if (win) win.focus();
  }

  window.setItemFromPicker = function(value){
    const v = (value || '').toString().trim();
    if(!v) return;
    barcodeEl.value = v;
    setTimeout(() => { focusBarcode(); }, 0);
  };

  // =========================
  // Held Slots
  // =========================
  const parkBtns = {
    1: document.querySelector('[data-park="1"]'),
    2: document.querySelector('[data-park="2"]'),
    3: document.querySelector('[data-park="3"]')
  };

  function updateParkButtons(){
    [1,2,3].forEach(slot=>{
      const b = parkBtns[slot];
      if(!b) return;

      const filled = !!heldSlots[slot];
      b.disabled = filled;
      b.title = filled ? ('Tunda ' + slot + ' sudah terisi') : ('Tunda transaksi ke slot ' + slot);
    });
  }

  function updateHeldInfo(){
    [1,2,3].forEach(slot=>{
      const el = heldSlotEls[slot];
      if (!el) return;
      if (heldSlots[slot]){
        el.textContent = 'Tunda ' + slot + ': TERISI';
        el.classList.remove('held-empty');
        el.classList.add('held-filled');
      } else {
        el.textContent = 'Tunda ' + slot + ': Kosong';
        el.classList.remove('held-filled');
        el.classList.add('held-empty');
      }
    });
    updateParkButtons();
  }

  async function loadHeldSlotsFromServer(){
    try{
      const res = await fetch('/tokoapp/api/list_held_transactions.php', { cache: 'no-store' });
      const data = await res.json();
      if (!data || !data.success) return;
      const slots = data.slots || {};
      [1,2,3].forEach(slot => heldSlots[slot] = slots[slot] ? slots[slot] : null);
      updateHeldInfo();
    }catch(e){
      console.error('Error loadHeldSlotsFromServer', e);
    }
  }

  async function ensureSlotEmptyFromServer(slot){
    await loadHeldSlotsFromServer();
    return !heldSlots[slot];
  }

  // =========================
  // Stock info helper
  // =========================
  function updateStockInfo(item){
    if(!stockInfoEl) return;
    const sg = parseInt(item.stok_gudang ?? 0, 10);
    const st = parseInt(item.stok_toko ?? 0, 10);
    const total = sg + st;
    stockInfoEl.textContent =
      'Info stok: Gudang ' + formatID(sg) +
      ' • Toko ' + formatID(st) +
      ' • Total ' + formatID(total);
  }

  // =========================
  // MEMBER: load by kode
  // =========================
  async function loadMemberByKode(kodeRaw) {
    const kode = (kodeRaw || '').trim();

    if (!kode) {
      memberNamaEl.value = '';
      memberPoinEl.value = '0';
      memberType = null;
      hitungPointDiscount();
      renderCart();
      return;
    }

    try {
      const res = await fetch('/tokoapp/api/get_member.php?kode=' + encodeURIComponent(kode), { cache: 'no-store' });
      const m = await res.json();

      if (m && !m.error && m.kode) {
        memberNamaEl.value = m.nama || '';
        memberPoinEl.value = m.poin || 0;
        memberType = m.jenis || 'umum';
      } else {
        memberNamaEl.value = '';
        memberPoinEl.value = '0';
        memberType = null;
        memberKodeEl.value = '';
        alert('Member tidak ditemukan');
        setTimeout(() => { memberKodeEl.focus(); memberKodeEl.select && memberKodeEl.select(); }, 0);
      }
    } catch (e) {
      memberNamaEl.value = '';
      memberPoinEl.value = '0';
      memberType = null;
      memberKodeEl.value = '';
      alert('Gagal mengambil data member');
      setTimeout(() => { memberKodeEl.focus(); memberKodeEl.select && memberKodeEl.select(); }, 0);
    }

    hitungPointDiscount();
    renderCart();
  }

  function triggerMemberSearch() { loadMemberByKode(memberKodeEl.value); }

  memberKodeEl.addEventListener('change', triggerMemberSearch);
  memberKodeEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      triggerMemberSearch();
    }
  });

  window.setMemberFromPicker = function(kode){
    const k = (kode || '').trim();
    if(!k) return;
    memberKodeEl.value = k;
    loadMemberByKode(k);
    setTimeout(() => { focusBarcode(); }, 0);
  };

  function openMemberPicker(){
    const url = '/tokoapp/members.php?pick=1';
    const w = 1100, h = 720;
    const left = Math.max(0, (screen.width  - w) / 2);
    const top  = Math.max(0, (screen.height - h) / 2);

    window.open(
      url,
      'memberPicker',
      `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`
    );
  }

  function openMemberPopupWindow(){
    const w = window.open('members.php?popup=1', 'memberSearch', 'width=1100,height=700');
    if (w) w.focus();
  }
  if (btnMemberSearch) btnMemberSearch.addEventListener('click', openMemberPopupWindow);

  // =========================
// MODE PEMBAYARAN: CASH / PIUTANG
// =========================
payModeEl.addEventListener('change', () => {
  const isAR = payModeEl.value === 'ar';
  tunaiEl.disabled = isAR;
  tunaiEl.value = '0';
  kembalianEl.value = '0';
  setDisplayTotal();
});


  // =========================
  // BARCODE -> ITEM (debounce + queue)
  // =========================
  const SCAN_DEBOUNCE_MS = 80;
  const SCANNER_MAX_GAP_MS = 35;
  const SCANNER_MIN_LEN = 4;

  let barcodeTimer = null;
  let lastKeyTime = 0;
  let fastGapStreak = 0;
  let isLikelyScanner = false;

  const scanQueue = [];
  let processingScan = false;

  async function handleScan(code) {
    try {
      const res = await fetch('/tokoapp/api/get_item.php?q=' + encodeURIComponent(code), { cache: 'no-store' });
      const item = await res.json();

      if (item && item.kode) {
        updateStockInfo(item);
        addToCart(item);
      } else {
        alert('Barang tidak ditemukan: ' + code);
      }
    } catch (err) {
      console.error(err);
      alert('Gagal mengambil data barang: ' + code);
    }
  }

  async function processScanQueue() {
    if (processingScan) return;
    processingScan = true;

    while (scanQueue.length) {
      const code = scanQueue.shift();
      await handleScan(code);
    }

    processingScan = false;
  }

  function enqueueScan(code) {
    if (!code) return;
    scanQueue.push(code);
    processScanQueue();
  }

  function resetScanDetect() {
    clearTimeout(barcodeTimer);
    lastKeyTime = 0;
    fastGapStreak = 0;
    isLikelyScanner = false;
  }

  function submitBarcodeNow() {
    clearTimeout(barcodeTimer);
    const code = (barcodeEl.value || '').trim();
    if (!code) return;

    clearKembalianOnNextInput();

    barcodeEl.value = '';
    focusBarcode();

    enqueueScan(code);
    resetScanDetect();
  }

  barcodeEl.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
      e.preventDefault();
      submitBarcodeNow();
      return;
    }
    if (e.key.length !== 1) return;

    const now = performance.now();
    const gap = lastKeyTime ? (now - lastKeyTime) : 9999;
    lastKeyTime = now;

    if (gap <= SCANNER_MAX_GAP_MS) fastGapStreak++;
    else fastGapStreak = 0;

    const lenAfterKey = (barcodeEl.value || '').length + 1;
    isLikelyScanner = (fastGapStreak >= 2 && lenAfterKey >= SCANNER_MIN_LEN);
  });

  barcodeEl.addEventListener('input', () => {
    clearTimeout(barcodeTimer);
    if (!isLikelyScanner) return;

    barcodeTimer = setTimeout(() => {
      submitBarcodeNow();
    }, SCAN_DEBOUNCE_MS);
  });

  // =========================
  // Cart
  // =========================
  function addToCart(item){
    clearKembalianOnNextInput();

    const defLevel = 1;
    const addQty = (parseInt(nextScanQty || 1, 10) > 0) ? parseInt(nextScanQty, 10) : 1;

    const prices=[1,2,3,4].map(i=> parseInt(item['harga_jual'+i])||0);
    const exist=cart.findIndex(r=>r.kode===item.kode && r.level===defLevel);

    if(exist>=0){
      cart[exist].qty += addQty;
      activeIdx=exist;
    } else {
      cart.push({ kode:item.kode, nama:item.nama, qty:addQty, level:defLevel, prices });
      activeIdx=cart.length-1;
    }

    nextScanQty = 1;
    updateNextQtyInfo();

    // >>> AUTO-SCROLL ke item terbaru + kunci 25 baris
    renderCart({ scrollToActive:true, visibleRows:25 });

    focusBarcode();
  }

  function renderCart(opts = {}){
    // backward compatible: kalau masih ada pemanggilan renderCart(true/false)
    if (typeof opts === 'boolean') opts = { focusLastQty: opts };

    const {
      focusLastQty = false,
      scrollToActive = false,
      visibleRows = 25
    } = opts;

    tbody.innerHTML='';
    let subtotal=0, totalItems=0;

    cart.forEach((r,idx)=>{
      const harga = r.prices[(r.level-1)]||0;
      const total = r.qty*harga;
      subtotal += total; totalItems += r.qty;

      // ====== PERUBAHAN UTAMA:
      // Dropdown level harga sekarang tampilkan nominal harga 1..4
      const h1 = r.prices[0] || 0;
      const h2 = r.prices[1] || 0;
      const h3 = r.prices[2] || 0;
      const h4 = r.prices[3] || 0;

      const tr=document.createElement('tr');
      if(idx===activeIdx) tr.classList.add('active-row');

      tr.innerHTML=`<td class="col-kode">${r.kode}</td>
                    <td>${r.nama}</td>
                    <td class="right col-qty"><input type="number" min="1" value="${r.qty}" class="qtyInput"></td>
                    <td class="right col-harga">
                      <div class="priceRow">
                        <span class="priceText">${formatID(harga)}</span>
                        <select class="lvlSel" title="Pilih Level Harga">
                          <option value="1"${r.level===1?' selected':''}>H1 • ${formatID(h1)}</option>
                          <option value="2"${r.level===2?' selected':''}>H2 • ${formatID(h2)}</option>
                          <option value="3"${r.level===3?' selected':''}>H3 • ${formatID(h3)}</option>
                          <option value="4"${r.level===4?' selected':''}>H4 • ${formatID(h4)}</option>
                        </select>
                      </div>
                    </td>
                    <td class="right col-total">${formatID(total)}</td>
                    <td class="col-aksi"><button class="btn delBtn" style="padding:.25rem .35rem;width:100%;font-size:.78rem">Hapus</button></td>`;

      tr.addEventListener('click', (ev)=>{
        const tag = ev.target.tagName;
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'BUTTON') return;
        activeIdx = idx;
        renderCart();
      });

      tbody.appendChild(tr);

      const qtyInput = tr.querySelector('.qtyInput');
      qtyInput.onchange=(e)=>{
        const v = parseInt(e.target.value||'1',10);
        cart[idx].qty = v>0 ? v:1;
        activeIdx=idx; renderCart();
      };
      qtyInput.onkeydown=(e)=>{
        if(e.key==='Enter'){ e.preventDefault(); focusBarcode(); }
      };

      const delBtn = tr.querySelector('.btn.delBtn');
      delBtn.onclick=()=>{
        cart.splice(idx,1);
        if(activeIdx>=cart.length) activeIdx = cart.length-1;
        renderCart();
        focusBarcode();
      };

      const lvlSel = tr.querySelector('.lvlSel');
      lvlSel.onchange = (ev)=>{
        const n = parseInt(ev.target.value||'1',10);
        if(n>=1 && n<=4){
          cart[idx].level = n;
          activeIdx = idx;

          const rowIndex = idx;
          renderCart();

          const rows = document.querySelectorAll('#cartTable tbody tr');
          const row  = rows[rowIndex];
          const sel  = row ? row.querySelector('.lvlSel') : null;
          if (sel) sel.focus();
        }
      };

      const hargaCell = tr.querySelector('.col-harga');
      if (hargaCell) {
        hargaCell.addEventListener('click', (e)=>{
          if (e.target.tagName !== 'SELECT') {
            const sel = tr.querySelector('.lvlSel');
            if (sel) sel.focus();
          }
        });
      }
    });

    subtotalEl.textContent = formatID(subtotal);

    const dVal = parseInt(discountEl.value||'0',10);
    const dMode = discountModeEl.value;
    const disc = (dMode==='pct') ? Math.floor(subtotal*(dVal/100)) : Math.min(dVal, subtotal);

    const taxVal = parseInt(taxEl.value||'0',10);
    const taxMode = taxModeEl.value;
    const taxBase = Math.max(0, subtotal - disc);
    const taxAmt = (taxMode==='pct') ? Math.floor(taxBase*(taxVal/100)) : taxVal;

    const pointDisc = hitungPointDiscount(Math.max(0, subtotal - disc + taxAmt));

    tdiscountEl.textContent = formatID(disc);
    ttaxEl.textContent = formatID(taxAmt);
    tpointdiscEl.textContent = formatID(pointDisc);

    const grand = Math.max(0, subtotal - disc + taxAmt - pointDisc);
    gtotalEl.textContent = formatID(grand);

    totalItemsEl.textContent = formatID(totalItems);
    if (totalRowsEl) totalRowsEl.textContent = formatID(cart.length);
    if (totalItemsMiniEl) totalItemsMiniEl.textContent = formatID(totalItems);

    totalBayarEl.textContent = formatID(grand);

    hitungKembalian();

    if (displayMode === 'total'){
      if (grandDisplay) grandDisplay.textContent = formatID(grand);
    } else {
      if (grandDisplay) grandDisplay.textContent = formatID(lastKembalian);
    }

    // Kunci tinggi agar tampil 25 baris (setelah row ada)
    fitCartToRows(visibleRows);

    // Auto-scroll ke item terbaru/active row
    if (scrollToActive){
      const wrap = document.querySelector('.table-wrap');
      const rows = wrap ? wrap.querySelectorAll('#cartTable tbody tr') : [];
      const targetIndex = (activeIdx >= 0 ? activeIdx : rows.length - 1);
      const targetRow = rows[targetIndex];

      const ae = document.activeElement;
      const userEditingInTable = ae && wrap && wrap.contains(ae) && (ae.tagName === 'INPUT' || ae.tagName === 'SELECT');

      if (wrap && targetRow && !userEditingInTable){
        targetRow.scrollIntoView({ block:'end', behavior:'smooth' });
      }
    }

    if(focusLastQty){
      const target=(activeIdx>=0?activeIdx:cart.length-1);
      const rows = Array.from(document.querySelectorAll('#cartTable tbody tr'));
      const qty = rows[target]?.querySelector('.qtyInput');
      if(qty){ qty.focus(); qty.select && qty.select(); }
    }
  }

  function hitungPointDiscount(maxAllowable){
    const totalPoin = parseInt(memberPoinEl.value||'0',10);
    let poinUse = parseInt(poinDitukarEl.value||'0',10);
    if(poinUse < 0) poinUse = 0;
    if(poinUse > totalPoin) poinUse = totalPoin;
    poinDitukarEl.value = poinUse;

    let perPoint = 0;
    if(memberType === 'grosir') perPoint = RP_PER_POINT_GROSIR;
    else if(memberType === 'umum') perPoint = RP_PER_POINT_UMUM;
    else perPoint = 0;

    let disc = poinUse * perPoint;
    if(typeof maxAllowable === 'number'){
      disc = Math.min(disc, maxAllowable);
    }
    poinPotonganView.textContent = formatID(disc);
    return disc;
  }

  function hitungKembalian(){
    const subtotal = unformat(subtotalEl.textContent);
    const disc = unformat(tdiscountEl.textContent);
    const tax  = unformat(ttaxEl.textContent);
    const pdisc = unformat(tpointdiscEl.textContent);
    const grand = Math.max(0, subtotal - disc + tax - pdisc);

    const tunai = unformat(tunaiEl.value);
    const kembali = Math.max(0, tunai - grand);

    kembalianEl.value = formatID(kembali);
  }

  // =========================
  // Parkir state helpers
  // =========================
  function getCurrentState(){
    return {
      cart: JSON.parse(JSON.stringify(cart)),
      member_kode: memberKodeEl.value || '',
      member_nama: memberNamaEl.value || '',
      member_poin: memberPoinEl.value || '0',
      memberType: memberType,
      poin_ditukar: poinDitukarEl.value || '0',
      discount: discountEl.value || '0',
      discountMode: discountModeEl.value,
      tax: taxEl.value || '0',
      taxMode: taxModeEl.value,
      tunai: unformat(tunaiEl.value || '0'),
      nextScanQty: nextScanQty
    };
  }

  function applyState(s){
    cart.length = 0;
    (s.cart || []).forEach(r=> cart.push(r));
    memberKodeEl.value = s.member_kode || '';
    memberNamaEl.value = s.member_nama || '';
    memberPoinEl.value = s.member_poin || '0';
    memberType = s.memberType || null;
    poinDitukarEl.value = s.poin_ditukar || '0';
    discountEl.value = s.discount || '0';
    discountModeEl.value = s.discountMode || 'rp';
    taxEl.value = s.tax || '0';
    taxModeEl.value = s.taxMode || 'rp';
    tunaiEl.value = formatID(parseInt(s.tunai || 0,10));

    nextScanQty = parseInt(s.nextScanQty || 1, 10);
    if (!nextScanQty || nextScanQty <= 0) nextScanQty = 1;
    updateNextQtyInfo();
  }

  function clearTransaction(keepKembalianDisplay=false){
    cart.length = 0;
    activeIdx = -1;
    memberKodeEl.value='';
    memberNamaEl.value='';
    memberPoinEl.value='0';
    memberType = null;
    poinDitukarEl.value='0';
    discountEl.value='0';
    discountModeEl.value='rp';
    taxEl.value='0';
    taxModeEl.value='rp';
    tunaiEl.value='0';
    kembalianEl.value='0';

    nextScanQty = 1;
    updateNextQtyInfo();

    renderCart();
    if (!keepKembalianDisplay){
      hideKembalianOverlay();
      setDisplayTotal();
      renderCart();
    }
    setTimeout(() => focusBarcode(), 0);
  }

  async function holdTransaction(slot){
    if(!slot || ![1,2,3].includes(slot)) return;

    if (heldSlots[slot]) {
      alert('Tunda ' + slot + ' sudah TERISI. Klik "Lanjut ' + slot + '" atau "Hapus Tunda ' + slot + '" dulu.');
      return;
    }

    const okEmpty = await ensureSlotEmptyFromServer(slot);
    if (!okEmpty){
      alert('Tunda ' + slot + ' barusan sudah terisi dari perangkat lain. Pilih slot lain.');
      return;
    }

    if(cart.length===0){
      alert('Keranjang kosong, tidak ada transaksi untuk ditunda.');
      return;
    }

    const state = getCurrentState();

    try{
      const res = await fetch('/tokoapp/api/hold_transaction.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ slot: slot, state: state })
      });
      const data = await res.json();
      if (!data || !data.success) {
        alert('Gagal menyimpan transaksi tunda: ' + (data && data.error ? data.error : 'Unknown error'));
        return;
      }

      heldSlots[slot] = state;
      clearTransaction(false);
      updateHeldInfo();
      alert('Transaksi ditunda di Tunda ' + slot);
    }catch(e){
      console.error(e);
      alert('Terjadi kesalahan saat menyimpan transaksi tunda.');
    }
  }

  async function resumeTransaction(slot){
    if(!slot || ![1,2,3].includes(slot)) return;

    try{
      const res = await fetch('/tokoapp/api/get_held_transaction.php?slot=' + encodeURIComponent(slot), { cache: 'no-store' });
      const data = await res.json();
      if (!data || !data.success || !data.state) {
        alert('Tunda ' + slot + ' masih kosong atau gagal diambil.');
        return;
      }

      hideKembalianOverlay();
      setDisplayTotal();

      applyState(data.state);
      renderCart();
      hitungKembalian();
      setTimeout(() => focusBarcode(), 0);

      try{
        await fetch('/tokoapp/api/delete_held_transaction.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ slot: slot })
        });
      }catch(e){
        console.error('Gagal menghapus dari server, tetapi transaksi sudah dimuat.', e);
      }

      heldSlots[slot] = null;
      updateHeldInfo();
    }catch(e){
      console.error(e);
      alert('Terjadi kesalahan saat mengambil transaksi tunda.');
    }
  }

  // =========================
  // SAVE + PRINT
  // =========================
  function simpanCetak(){
    if(cart.length===0){
      alert('Keranjang kosong');
      setTimeout(() => focusBarcode(), 0);
      return;
    }

    const subtotal = unformat(subtotalEl.textContent);
    const disc = unformat(tdiscountEl.textContent);
    const tax  = unformat(ttaxEl.textContent);
    const pdisc = unformat(tpointdiscEl.textContent);
    const total = Math.max(0, subtotal - disc + tax - pdisc);
    const tunai = unformat(tunaiEl.value);

    if (payModeEl.value === 'cash' && tunai < total) {
  alert('Tunai belum cukup.');
  tunaiEl.focus();
  return;
}


    const items = cart.map(r=>{
      const harga = r.prices[(r.level-1)]||0;
      return { kode:r.kode, nama:r.nama, qty:r.qty, level:r.level, harga:harga };
    });

const payload = {
  pay_mode: payModeEl.value, // cash | ar
  member_kode: memberKodeEl.value || null,
  member_nama: memberNamaEl.value || null,

  member_poin: parseInt(memberPoinEl.value||'0',10),
  poin_ditukar: parseInt(poinDitukarEl.value||'0',10),
  point_discount: unformat(tpointdiscEl.textContent),

  shift: 1,
  total: total,
  tunai: (payModeEl.value === 'cash') ? tunai : 0,

  items: items,

  discount: parseInt(discountEl.value||'0',10),
  discount_mode: discountModeEl.value,
  tax: parseInt(taxEl.value||'0',10),
  tax_mode: taxModeEl.value
};


    const kembali = Math.max(0, tunai - total);
    setDisplayKembalian(kembali);
    showKembalianOverlay(kembali);

    pendingClearAfterPrint = true;

    requestAnimationFrame(() => {
      setTimeout(() => {
        const w = window.open('about:blank', 'printWindow', 'width=520,height=740,scrollbars=yes,resizable=yes');
        if (!w) {
          alert('Pop-up diblokir browser. Izinkan pop-up untuk cetak.');
          pendingClearAfterPrint = false;
          return;
        }

        const f = document.createElement('form');
        f.method = 'post';
        f.action = 'save_sale.php';
        f.target = 'printWindow';

        const input = document.createElement('input');
        input.type='hidden';
        input.name='payload';
        input.value=JSON.stringify(payload);
        f.appendChild(input);
        document.body.appendChild(f);

        try { f.submit(); } catch(e){}
        try { f.remove(); } catch(e){}

        try { window.focus(); } catch(e){}
      }, 90);
    });
  }

  // =========================
  // Shortcuts
  // =========================
  window.addEventListener('keydown', (e)=>{
    if (e.key === 'F2') { e.preventDefault(); openItemPicker(); return; }

    if (e.key === 'F1') {
      e.preventDefault();
      if (f1PressTimer) clearTimeout(f1PressTimer);
      f1PressTimer = setTimeout(() => { f1PressCount = 0; f1PressTimer = null; }, 1200);

      f1PressCount++;
      if (f1PressCount > 3) f1PressCount = 1;

      let nominal = 0;
      if (f1PressCount === 1) nominal = 20000;
      else if (f1PressCount === 2) nominal = 50000;
      else nominal = 100000;

      tunaiEl.value = formatID(nominal);
      hitungKembalian();
      tunaiEl.focus();
      tunaiEl.select && tunaiEl.select();
      return;
    }

    if(e.key === 'F5'){
      e.preventDefault();
      if (e.shiftKey) openMemberPicker();
      else { memberKodeEl.focus(); memberKodeEl.select && memberKodeEl.select(); }
      return;
    }

    if(e.key==='F6'){ e.preventDefault(); focusBarcode(); return; }

    if(e.key==='F7'){
      e.preventDefault();
      const current = String(nextScanQty || 1);
      const v = prompt('Set Qty untuk item berikutnya (scan selanjutnya):', current);
      if (v === null) { focusBarcode(); return; }

      let n = parseInt(String(v).replace(/[^\d]/g,''), 10);
      if (!n || n <= 0) n = 1;
      if (n > 1000000) n = 1000000;

      nextScanQty = n;
      updateNextQtyInfo();
      focusBarcode();
      return;
    }

    if(e.key==='F4'){ e.preventDefault(); tunaiEl.focus(); tunaiEl.select&&tunaiEl.select(); return; }
    if(e.key === 'F8'){ e.preventDefault(); poinDitukarEl.focus(); poinDitukarEl.select && poinDitukarEl.select(); return; }
    if(e.key==='F9'){ e.preventDefault(); discountEl.focus(); discountEl.select&&discountEl.select(); return; }
    if(e.key==='F10'){ e.preventDefault(); simpanCetak(); return; }
    if(e.key==='F3'){ e.preventDefault(); window.open('sales_report.php','_blank','noopener'); return; }
  });

  // Flow enter
  discountEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); taxEl.focus(); taxEl.select&&taxEl.select(); }});
  taxEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); poinDitukarEl.focus(); poinDitukarEl.select&&poinDitukarEl.select(); }});
  poinDitukarEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); tunaiEl.focus(); tunaiEl.select&&tunaiEl.select(); }});
  tunaiEl.addEventListener('keydown', (e)=>{ if(e.key==='Enter'){ e.preventDefault(); simpanCetak(); }});

  [discountEl,discountModeEl,taxEl,taxModeEl,poinDitukarEl].forEach(el=> el.addEventListener('input', ()=>renderCart()));

  tunaiEl.addEventListener('input', ()=>{
    formatInputAsIDR(tunaiEl);
    hitungKembalian();
  });

  btnSave.addEventListener('click', ()=>simpanCetak());

  // Parkir & resume buttons
  document.querySelectorAll('[data-park]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const slot = parseInt(btn.getAttribute('data-park'),10);
      holdTransaction(slot);
    });
  });
  document.querySelectorAll('[data-resume]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const slot = parseInt(btn.getAttribute('data-resume'),10);
      resumeTransaction(slot);
    });
  });

  // Hapus tunda
  document.querySelectorAll('[data-clear]').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const slot = parseInt(btn.getAttribute('data-clear'),10);
      if(!slot || ![1,2,3].includes(slot)) return;
      if(!confirm('Hapus transaksi tunda ' + slot + '?')) return;

      try{
        const res = await fetch('/tokoapp/api/delete_held_transaction.php', {
          method: 'POST',
          headers: { 'Content-Type': 'application/json' },
          body: JSON.stringify({ slot: slot })
        });
        const data = await res.json();
        if (!data || !data.success) {
          alert('Gagal menghapus transaksi tunda: ' + (data && data.error ? data.error : 'Unknown error'));
          return;
        }
        heldSlots[slot] = null;
        updateHeldInfo();
        alert('Transaksi tunda ' + slot + ' telah dihapus.');
      }catch(e){
        console.error(e);
        alert('Terjadi kesalahan saat menghapus transaksi tunda.');
      }
    });
  });

  // =========================
  // INIT
  // =========================
  updateNextQtyInfo();
  setDisplayTotal();
  renderCart({ visibleRows:25 });
  updateHeldInfo();
  tunaiEl.value = '0';
  kembalianEl.value = '0';
  loadHeldSlotsFromServer();

  window.addEventListener('resize', () => fitCartToRows(25));

  setTimeout(() => {
    fitCartToRows(25);
    focusBarcode();
  }, 80);
</script>
</body>
</html>
