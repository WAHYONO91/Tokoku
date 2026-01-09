<?php
// =============================
// sales_edit_pos.php (FINAL)
// =============================
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_login();
require_role(['admin']); // edit transaksi: admin only (ubah kalau perlu)

// =======================
// Ambil setting toko + poin
// =======================
$setting = $pdo->query("
  SELECT 
    store_name,

    -- earning points (threshold)
    points_per_rupiah,
    points_per_rupiah_umum,
    points_per_rupiah_grosir,

    -- redeem (rupiah per 1 point)
    rupiah_per_point_umum,
    rupiah_per_point_grosir,
    redeem_rp_per_point_umum,
    redeem_rp_per_point_grosir
  FROM settings
  WHERE id = 1
")->fetch(PDO::FETCH_ASSOC);

$store = $setting['store_name'] ?? 'TOKO';

// threshold earning: berapa rupiah untuk dapat 1 poin
$global_ppr = (int)($setting['points_per_rupiah'] ?? 0);
$ppr_umum   = (int)($setting['points_per_rupiah_umum'] ?? 0);
$ppr_grosir = (int)($setting['points_per_rupiah_grosir'] ?? 0);

$threshold_umum   = $ppr_umum   > 0 ? $ppr_umum   : $global_ppr;
$threshold_grosir = $ppr_grosir > 0 ? $ppr_grosir : $global_ppr;

// nilai potongan per 1 poin (redeem)
$redeem_umum = (int)($setting['redeem_rp_per_point_umum'] ?? 0);
if ($redeem_umum <= 0) $redeem_umum = (int)($setting['rupiah_per_point_umum'] ?? 100);
if ($redeem_umum <= 0) $redeem_umum = 100;

$redeem_grosir = (int)($setting['redeem_rp_per_point_grosir'] ?? 0);
if ($redeem_grosir <= 0) $redeem_grosir = (int)($setting['rupiah_per_point_grosir'] ?? 25);
if ($redeem_grosir <= 0) $redeem_grosir = 25;

// =======================
// Helper
// =======================
if (!function_exists('rupiah')) {
  function rupiah($n){ return number_format((int)$n, 0, ',', '.'); }
}

function json_out($arr, $http=200){
  http_response_code($http);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($arr);
  exit;
}

function norm_jenis($j){
  $j = strtolower(trim((string)$j));
  return ($j === 'grosir') ? 'grosir' : 'umum';
}

function calc_earned_points($base_for_points, $jenis, $threshold_umum, $threshold_grosir){
  $jenis = norm_jenis($jenis);
  $threshold = ($jenis === 'grosir')
    ? ($threshold_grosir > 0 ? $threshold_grosir : $threshold_umum)
    : ($threshold_umum > 0 ? $threshold_umum : $threshold_grosir);

  if ($threshold <= 0) return 0;
  if ($base_for_points <= 0) return 0;
  return (int) floor($base_for_points / $threshold);
}

function calc_redeem_rp_per_point($jenis, $redeem_umum, $redeem_grosir){
  $jenis = norm_jenis($jenis);
  return ($jenis === 'grosir') ? (int)$redeem_grosir : (int)$redeem_umum;
}

// =======================
// Ambil ID transaksi
// =======================
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) die("ID tidak valid.");

// =======================
// Load header sale
// =======================
$st = $pdo->prepare("SELECT * FROM sales WHERE id=? LIMIT 1");
$st->execute([$id]);
$sale = $st->fetch(PDO::FETCH_ASSOC);
if (!$sale) die("Transaksi tidak ditemukan.");

// izinkan edit semua transaksi termasuk RETURN/VOID kalau kamu mau
// sebelumnya kamu blok status non-OK.
// sekarang: tetap izinkan, tapi minimal transaksi harus ada.
if (empty($sale['status'])) {
  // kalau status null, tetap lanjut.
}

// =======================
// Load detail sale_items + harga master
// =======================
$sti = $pdo->prepare("
  SELECT si.*, i.harga_jual1, i.harga_jual2, i.harga_jual3, i.harga_jual4
  FROM sale_items si
  LEFT JOIN items i ON i.kode = si.item_kode
  WHERE si.sale_id = ?
  ORDER BY si.id ASC
");
$sti->execute([$id]);
$items_old = $sti->fetchAll(PDO::FETCH_ASSOC);

// Map lama by id
$oldById = [];
foreach($items_old as $it){ $oldById[(int)$it['id']] = $it; }

// member list untuk datalist (optional)
$members = [];
try{
  $members = $pdo->query("SELECT kode, nama FROM members ORDER BY created_at DESC LIMIT 300")->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){}

// =======================
// URL cetak (ubah kalau beda)
// =======================
$PRINT_URL_BASE = '/tokoapp/sale_print.php?id=';

// =======================
// API: SAVE (AJAX)
// =======================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payload'])) {
  $payload = json_decode($_POST['payload'], true);
  if (!$payload) json_out(['success'=>false,'error'=>'Payload tidak valid.'], 400);

  // input header
  $member_kode   = trim($payload['member_kode'] ?? '');
  $shift         = (string)($payload['shift'] ?? '');
  $discount      = (int)($payload['discount'] ?? 0);
  $discount_mode = (($payload['discount_mode'] ?? 'rp') === 'pct') ? 'pct' : 'rp';
  $tax           = (int)($payload['tax'] ?? 0);
  $tax_mode      = (($payload['tax_mode'] ?? 'rp') === 'pct') ? 'pct' : 'rp';

  $poin_ditukar  = (int)($payload['poin_ditukar'] ?? 0);
  if ($poin_ditukar < 0) $poin_ditukar = 0;

  $tunai         = (int)($payload['tunai'] ?? 0);
  if ($tunai < 0) $tunai = 0;

  $items_new = $payload['items'] ?? [];
  if (!is_array($items_new) || count($items_new) === 0) {
    json_out(['success'=>false,'error'=>'Item kosong.'], 400);
  }

  // load member baru (pakai members.points)
  $member = null;
  $member_type = null;
  $member_points_now = 0;

  if ($member_kode !== '') {
    $sm = $pdo->prepare("SELECT kode, nama, jenis, points FROM members WHERE kode=? LIMIT 1");
    $sm->execute([$member_kode]);
    $member = $sm->fetch(PDO::FETCH_ASSOC);
    if (!$member) json_out(['success'=>false,'error'=>'Member tidak ditemukan.'], 400);
    $member_type = norm_jenis($member['jenis'] ?? 'umum');
    $member_points_now = (int)($member['points'] ?? 0);
  }

  $rpPerPoint = 0;
  if ($member_type === 'grosir') $rpPerPoint = (int)$redeem_grosir;
  else if ($member_type === 'umum') $rpPerPoint = (int)$redeem_umum;

  // Query harga master
  $qh = $pdo->prepare("SELECT kode, nama, harga_jual1, harga_jual2, harga_jual3, harga_jual4 FROM items WHERE kode=? LIMIT 1");

  // new ids map untuk deteksi delete
  $newIds = [];
  foreach ($items_new as $r) {
    if (!empty($r['id'])) $newIds[(int)$r['id']] = true;
  }

  // kalkulasi + siapkan update/insert/delete
  $subtotal = 0;
  $updates = [];
  $inserts = [];
  $deletes = [];

  foreach ($items_new as $r) {
    $si_id = isset($r['id']) ? (int)$r['id'] : 0;
    $kode  = trim($r['item_kode'] ?? '');
    $qty   = (int)($r['qty'] ?? 0);
    $lvl   = (int)($r['level'] ?? 1);

    if ($qty < 0) $qty = 0;
    if ($lvl < 1) $lvl = 1;
    if ($lvl > 4) $lvl = 4;

    if ($kode === '') continue;

    $qh->execute([$kode]);
    $it = $qh->fetch(PDO::FETCH_ASSOC);
    if (!$it) json_out(['success'=>false,'error'=>"Barang tidak ditemukan: $kode"], 400);

    $nama  = $it['nama'] ?? ($r['nama'] ?? '');
    $harga = (int)($it['harga_jual'.$lvl] ?? 0);

    // fallback harga lama kalau master 0
    if ($harga <= 0 && $si_id > 0 && isset($oldById[$si_id])) {
      $harga = (int)($oldById[$si_id]['harga'] ?? 0);
    }

    if ($si_id > 0) {
      $updates[] = ['id'=>$si_id,'item_kode'=>$kode,'nama'=>$nama,'qty'=>$qty,'level'=>$lvl,'harga'=>$harga];
    } else {
      $inserts[] = ['item_kode'=>$kode,'nama'=>$nama,'qty'=>$qty,'level'=>$lvl,'harga'=>$harga];
    }

    $subtotal += ($qty * $harga);
  }

  foreach ($oldById as $oid=>$old) {
    if (!isset($newIds[$oid])) $deletes[] = (int)$oid;
  }

  // diskon
  $disc_nom = ($discount_mode === 'pct')
    ? (int)floor($subtotal * ($discount/100))
    : min($discount, $subtotal);

  $tax_base = max(0, $subtotal - $disc_nom);

  // pajak
  $tax_nom = ($tax_mode === 'pct')
    ? (int)floor($tax_base * ($tax/100))
    : $tax;

  // point discount (capped)
  $maxAllowable = max(0, $subtotal - $disc_nom + $tax_nom);
  $point_discount = 0;

  if ($member && $rpPerPoint > 0 && $poin_ditukar > 0) {
    $point_discount = $poin_ditukar * $rpPerPoint;
    if ($point_discount > $maxAllowable) {
      $point_discount = $maxAllowable;
      $poin_ditukar = (int)floor($point_discount / $rpPerPoint);
      $point_discount = $poin_ditukar * $rpPerPoint;
    }
  } else {
    $poin_ditukar = 0;
    $point_discount = 0;
  }

  $total = max(0, $subtotal - $disc_nom + $tax_nom - $point_discount);
  $kembalian = max(0, $tunai - $total);

  // =========================
  // POINTS: rollback transaksi lama -> apply transaksi baru
  // Target kolom: members.points (BUKAN poin)
  // =========================
  $old_member_kode   = (string)($sale['member_kode'] ?? '');
  $old_poin_ditukar  = (int)($sale['poin_ditukar'] ?? 0);
  $old_point_discount= (int)($sale['point_discount'] ?? 0);

  // hitung earned points transaksi lama (pakai total lama)
  $old_member_jenis = 'umum';
  if ($old_member_kode !== '') {
    $qom = $pdo->prepare("SELECT jenis FROM members WHERE kode=? LIMIT 1");
    $qom->execute([$old_member_kode]);
    $om = $qom->fetch(PDO::FETCH_ASSOC);
    if ($om) $old_member_jenis = norm_jenis($om['jenis'] ?? 'umum');
  }

  $old_subtotal = (int)($sale['subtotal'] ?? 0);
  $old_discount = (int)($sale['discount'] ?? 0);
  $old_tax      = (int)($sale['tax'] ?? 0);

  $old_base_for_points = max(0, $old_subtotal - $old_discount + $old_tax);
  $old_earned_points = ($old_member_kode !== '')
    ? calc_earned_points($old_base_for_points, $old_member_jenis, $threshold_umum, $threshold_grosir)
    : 0;

  // earned points transaksi baru
  $new_base_for_points = max(0, $subtotal - $disc_nom + $tax_nom);
  $new_earned_points = ($member_kode !== '' && $member_type)
    ? calc_earned_points($new_base_for_points, $member_type, $threshold_umum, $threshold_grosir)
    : 0;

  try {
    $pdo->beginTransaction();

    // ---- 1) Rollback ke member lama (kalau ada):
    // - balikin poin yang dulu ditukar (old_poin_ditukar)
    // - kurangi poin yang dulu didapat (old_earned_points)
    if ($old_member_kode !== '') {
      // points = points + old_poin_ditukar - old_earned_points
      $pdo->prepare("
        UPDATE members
        SET points = GREATEST(0, points + :redeem_back - :earned_back)
        WHERE kode = :kode
        LIMIT 1
      ")->execute([
        ':redeem_back' => $old_poin_ditukar,
        ':earned_back' => $old_earned_points,
        ':kode'        => $old_member_kode
      ]);
    }

    // ---- 2) Apply ke member baru (kalau ada):
    if ($member_kode !== '') {
      // cek member baru + points tersedia
      $cur = $pdo->prepare("SELECT points, jenis FROM members WHERE kode=? LIMIT 1");
      $cur->execute([$member_kode]);
      $mcur = $cur->fetch(PDO::FETCH_ASSOC);
      if (!$mcur) throw new Exception("Member baru tidak ditemukan.");

      $p_available = (int)($mcur['points'] ?? 0);
      $jenis_new = norm_jenis($mcur['jenis'] ?? 'umum');

      // pastikan rpPerPoint sesuai jenis real di DB (biar tidak mismatch)
      $rpPerPoint_real = calc_redeem_rp_per_point($jenis_new, $redeem_umum, $redeem_grosir);

      // kalau jenis berubah, recalc point_discount & total supaya konsisten
      if ($member_type !== $jenis_new) {
        $member_type = $jenis_new;
        $rpPerPoint  = $rpPerPoint_real;

        // recalc point_discount (capped)
        $maxAllowable = max(0, $subtotal - $disc_nom + $tax_nom);
        if ($rpPerPoint > 0 && $poin_ditukar > 0) {
          $point_discount = $poin_ditukar * $rpPerPoint;
          if ($point_discount > $maxAllowable) {
            $point_discount = $maxAllowable;
            $poin_ditukar = (int)floor($point_discount / $rpPerPoint);
            $point_discount = $poin_ditukar * $rpPerPoint;
          }
        } else {
          $poin_ditukar = 0;
          $point_discount = 0;
        }

        $total = max(0, $subtotal - $disc_nom + $tax_nom - $point_discount);
        $kembalian = max(0, $tunai - $total);

        // recalc earned points
        $new_base_for_points = max(0, $subtotal - $disc_nom + $tax_nom);
        $new_earned_points = calc_earned_points($new_base_for_points, $member_type, $threshold_umum, $threshold_grosir);
      }

      if ($poin_ditukar > $p_available) {
        throw new Exception("Poin member tidak cukup. Tersedia: ".$p_available.", diminta: ".$poin_ditukar);
      }

      // apply: points = points - poin_ditukar + new_earned_points
      $pdo->prepare("
        UPDATE members
        SET points = GREATEST(0, points - :redeem - 0) + :earned
        WHERE kode = :kode
        LIMIT 1
      ")->execute([
        ':redeem' => $poin_ditukar,
        ':earned' => $new_earned_points,
        ':kode'   => $member_kode
      ]);
    } else {
      // member kosong -> poin ditukar nol, point_discount nol
      $poin_ditukar = 0;
      $point_discount = 0;
      $new_earned_points = 0;
      $total = max(0, $subtotal - $disc_nom + $tax_nom);
      $kembalian = max(0, $tunai - $total);
    }

    // =========================
    // STOK + SALE_ITEMS
    // =========================
    $upd = $pdo->prepare("
      UPDATE sale_items
      SET item_kode=:kode, nama=:nama, qty=:qty, level=:lvl, harga=:harga
      WHERE id=:id AND sale_id=:sale_id
    ");

    foreach($updates as $u){
      $old = $oldById[(int)$u['id']] ?? null;
      if (!$old) continue;

      $old_kode = (string)$old['item_kode'];
      $old_qty  = (int)$old['qty'];

      $new_kode = (string)$u['item_kode'];
      $new_qty  = (int)$u['qty'];

      if ($new_kode !== $old_kode) {
        if ($old_qty > 0) adjust_stock($pdo, $old_kode, 'toko', +$old_qty);
        if ($new_qty > 0) adjust_stock($pdo, $new_kode, 'toko', -$new_qty);
      } else {
        $delta = $new_qty - $old_qty;
        if ($delta !== 0) adjust_stock($pdo, $new_kode, 'toko', -$delta);
      }

      $upd->execute([
        ':kode'=>$new_kode,
        ':nama'=>$u['nama'],
        ':qty'=>$new_qty,
        ':lvl'=>$u['level'],
        ':harga'=>$u['harga'],
        ':id'=>$u['id'],
        ':sale_id'=>$id
      ]);
    }

    // DELETE rows
    if (!empty($deletes)) {
      $getDel = $pdo->prepare("SELECT * FROM sale_items WHERE id=? AND sale_id=? LIMIT 1");
      $delDo  = $pdo->prepare("DELETE FROM sale_items WHERE id=? AND sale_id=? LIMIT 1");
      foreach($deletes as $rid){
        $getDel->execute([$rid, $id]);
        if ($row = $getDel->fetch(PDO::FETCH_ASSOC)) {
          $kode = (string)$row['item_kode'];
          $qty  = (int)$row['qty'];
          if ($qty > 0) adjust_stock($pdo, $kode, 'toko', +$qty);
          $delDo->execute([$rid, $id]);
        }
      }
    }

    // INSERT rows
    if (!empty($inserts)) {
      $ins = $pdo->prepare("
        INSERT INTO sale_items (sale_id, item_kode, nama, qty, level, harga)
        VALUES (:sale_id, :kode, :nama, :qty, :lvl, :harga)
      ");
      foreach($inserts as $r){
        $ins->execute([
          ':sale_id'=>$id,
          ':kode'=>$r['item_kode'],
          ':nama'=>$r['nama'],
          ':qty'=>$r['qty'],
          ':lvl'=>$r['level'],
          ':harga'=>$r['harga'],
        ]);
        if ((int)$r['qty'] > 0) adjust_stock($pdo, $r['item_kode'], 'toko', -(int)$r['qty']);
      }
    }

    // =========================
    // UPDATE SALES HEADER
    // =========================
    $up = $pdo->prepare("
      UPDATE sales
      SET
        member_kode = :member_kode,
        shift       = :shift,
        subtotal    = :subtotal,
        discount    = :discount,
        discount_mode = :discount_mode,
        tax         = :tax,
        tax_mode    = :tax_mode,
        poin_ditukar= :poin_ditukar,
        point_discount = :point_discount,
        total       = :total,
        tunai       = :tunai,
        kembalian   = :kembalian
      WHERE id = :id
      LIMIT 1
    ");
    $up->execute([
      ':member_kode' => ($member_kode !== '' ? $member_kode : null),
      ':shift' => ($shift !== '' ? $shift : null),
      ':subtotal' => $subtotal,
      ':discount' => $discount,
      ':discount_mode' => $discount_mode,
      ':tax' => $tax,
      ':tax_mode' => $tax_mode,
      ':poin_ditukar' => $poin_ditukar,
      ':point_discount' => $point_discount,
      ':total' => $total,
      ':tunai' => $tunai,
      ':kembalian' => $kembalian,
      ':id' => $id
    ]);

    $pdo->commit();

    json_out([
      'success'=>true,
      'total'=>$total,
      'tunai'=>$tunai,
      'kembalian'=>$kembalian,
      'earned_points'=>$new_earned_points,
      'print_url'=>$PRINT_URL_BASE.$id
    ]);
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    json_out(['success'=>false,'error'=>$e->getMessage()], 400);
  }
}

// =======================
// VIEW: siapkan state awal untuk JS
// =======================
$init = [
  'sale'=>[
    'id'=>(int)$sale['id'],
    'invoice_no'=>$sale['invoice_no'] ?? '',
    'member_kode'=>$sale['member_kode'] ?? '',
    'shift'=>$sale['shift'] ?? '',
    'discount'=>(int)($sale['discount'] ?? 0),
    'discount_mode'=>$sale['discount_mode'] ?? 'rp',
    'tax'=>(int)($sale['tax'] ?? 0),
    'tax_mode'=>$sale['tax_mode'] ?? 'rp',
    'poin_ditukar'=>(int)($sale['poin_ditukar'] ?? 0),
    'tunai'=>(int)($sale['tunai'] ?? 0),
  ],
  'items'=>[]
];

foreach($items_old as $it){
  $lvl = (int)($it['level'] ?? 1); if($lvl<1||$lvl>4) $lvl=1;
  $prices = [
    1 => (int)($it['harga_jual1'] ?? 0),
    2 => (int)($it['harga_jual2'] ?? 0),
    3 => (int)($it['harga_jual3'] ?? 0),
    4 => (int)($it['harga_jual4'] ?? 0),
  ];
  $harga = (int)($it['harga'] ?? 0);
  if ($harga <= 0) $harga = $prices[$lvl] ?? 0;

  $init['items'][] = [
    'id'=>(int)$it['id'],
    'kode'=>$it['item_kode'] ?? '',
    'nama'=>$it['nama'] ?? '',
    'qty'=>(int)($it['qty'] ?? 0),
    'level'=>$lvl,
    'prices'=>[$prices[1],$prices[2],$prices[3],$prices[4]],
    'harga'=>$harga,
  ];
}

?>
<!doctype html>
<html lang="id" data-theme="dark">
<head>
  <meta charset="utf-8">
  <title><?= htmlspecialchars($store) ?> — Edit Transaksi</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    :root{
      color-scheme: dark;
      --topbar-h: 48px;
      --gap: .75rem;
      --card-bg: #0e1726;
      --card-bd: #1f2a3a;
      --page-bg: #0b1220;
      --text: #e2e8f0;
      --muted: #9bb0c9;
      --accent: #7dd3fc;
    }
    *{box-sizing:border-box}
    html,body{height:100%}
    body{
      margin:0;
      background:var(--page-bg);
      color:var(--text);
      font-family:system-ui,Segoe UI,Roboto,Arial,sans-serif;
      line-height:1.45
    }

    .topbar{
      height:var(--topbar-h);
      position:sticky;top:0;z-index:50;
      display:flex;align-items:center;justify-content:space-between;
      padding:0 .8rem;background:#020817;border-bottom:1px solid var(--card-bd);
    }
    .brand{font-weight:700;letter-spacing:.02em;font-size:1rem}
    .sub{font-size:.75rem;opacity:.75}

    .wrap{
      max-width:1440px;
      margin:0 auto;
      padding:var(--gap);
      display:flex;
      flex-direction:column;
      gap:1rem
    }

    .grand{
      position: sticky;
      top: calc(var(--topbar-h) + .5rem);
      z-index: 40;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 1.25rem;
      padding: .9rem 1.1rem;
      border-radius: .75rem;
      background: linear-gradient(180deg, #081224 0%, #0b162a 100%);
      border: 1px solid #22406188;
      box-shadow: 0 6px 16px rgba(0,0,0,.35), inset 0 0 0 1px rgba(125,211,252,.08);
    }
    .grand .lbl{
      font-size: .9rem;
      letter-spacing: .04em;
      color: #8fb8ff;
      opacity: .9;
      text-transform:uppercase;
    }
    .grand .num{
      font-family: ui-monospace, Consolas, Menlo, monospace;
      font-weight: 800;
      font-size: clamp(1.8rem, 2.4vw + 1rem, 3.25rem);
      letter-spacing: .03em;
      color: #7dd3fc;
      text-shadow: 0 0 6px rgba(125,211,252,.8),
                   0 0 14px rgba(56,189,248,.55),
                   0 0 28px rgba(56,189,248,.35);
    }

    .grand-meta{ display:flex; gap:.6rem; flex-wrap:wrap; justify-content:flex-end; }
    .meta-pill{
      padding:.35rem .6rem;
      border-radius:.6rem;
      background:rgba(15,23,42,.65);
      border:1px solid rgba(148,163,184,.25);
      min-width: 140px;
      text-align:right;
    }
    .meta-pill .k{ font-size:.7rem; letter-spacing:.06em; text-transform:uppercase; color:#9ca3af; }
    .meta-pill .v{ font-family:ui-monospace,Consolas,Menlo,monospace; font-weight:800; font-size:1rem; }

    .card{
      background:var(--card-bg);
      border:1px solid var(--card-bd);
      border-radius:.6rem;
      padding:.75rem
    }

    .layout{ display:grid; gap:var(--gap); grid-template-columns:1fr; }

    .table-wrap{ width:100%; overflow-x:auto; border-radius:.5rem; }
    table{ width:100%; border-collapse:collapse; font-size:.92rem; }
    th,td{
      border:1px solid #213047;
      padding:.5rem .55rem;
      vertical-align:middle;
    }
    th{ background:#0f1a2c; font-weight:600; white-space:nowrap; }
    tbody tr:nth-child(odd){ background:#0b1324 }
    .right{text-align:right}
    tr.active-row td{ background:#0b1a34 !important }

    .col-kode{ width: 160px; }
    .col-qty{ width: 110px; }
    .col-harga{ width: 260px; }
    .col-total{ width: 140px; }
    .col-aksi{ width: 180px; }
    .qtyInput{ width: 6.2rem; text-align:right }

    .priceRow{ display:flex; align-items:center; justify-content:space-between; gap:.5rem; }
    .priceText{ font-variant-numeric: tabular-nums; }
    .lvlSel{
      background:#091120;
      border:1px solid #263243;
      color:var(--text);
      border-radius:.35rem;
      padding:.35rem .45rem;
      font-size:.9rem;
    }

    .panel-bottom{ padding: 1rem 1rem; }
    .panel-bottom .kps{
      display:grid;
      grid-template-columns: repeat(3, minmax(260px, 1fr));
      gap:.8rem;
    }
    @media (max-width:1200px){
      .panel-bottom .kps{ grid-template-columns: repeat(2, minmax(260px,1fr)); }
    }
    @media (max-width:640px){
      .panel-bottom .kps{ grid-template-columns: 1fr; }
      .grand{ flex-direction:column; align-items:flex-start; }
      .grand-meta{ width:100%; justify-content:space-between; }
      .meta-pill{ flex:1; min-width:auto; }
    }

    .panel-bottom input:not([type="checkbox"]):not([type="radio"]),
    .panel-bottom select{
      font-size: 1.05rem;
      padding: .7rem .8rem;
      border-radius: .55rem;
      background:#091120;
      border:1px solid #263243;
      color:var(--text);
      width:100%;
    }

    .row{display:flex;gap:.4rem;align-items:center}
    .muted{font-size:.8rem;color:var(--muted)}
    .btn{
      background:#1f2b3e;
      border:1px solid #2c3c55;
      color:var(--text);
      padding:.6rem .8rem;
      border-radius:.5rem;
      cursor:pointer
    }
    .btn:active{transform:translateY(1px)}
    .btn.danger{ background:#3b1f25; border-color:#5b2830; }
    .btn.add{ background:#1f2f28; border-color:#2c4c3f; }
    .btn.warn{ background:#3b2b1a; border-color:#5a4324; }

    /* Overlay kembalian */
    #changeOverlay{
      display:none;
      position:fixed; inset:0; z-index:9999;
      background:rgba(0,0,0,.55);
    }
    #changeBox{
      position:absolute; left:50%; top:50%;
      transform:translate(-50%,-50%);
      background:#0e1726; border:1px solid #223044;
      border-radius:14px;
      padding:18px 22px; min-width:340px;
      text-align:center;
      box-shadow:0 10px 30px rgba(0,0,0,.5);
    }
    #changeTitle{
      font-size:.9rem; color:#9bb0c9;
      letter-spacing:.06em; text-transform:uppercase;
    }
    #changeValue{
      margin-top:6px; font-size:2.2rem; font-weight:900; color:#7dd3fc;
      font-family:ui-monospace,Consolas,Menlo,monospace;
    }
    #changeHint{ margin-top:10px; font-size:.85rem; color:#9bb0c9; }

    @media print{
      .topbar{display:none!important}
      body{background:#fff;color:#000}
      .no-print{display:none!important}
    }
  </style>
</head>
<body>

<div id="changeOverlay">
  <div id="changeBox">
    <div id="changeTitle">Kembalian</div>
    <div id="changeValue">0</div>
    <div id="changeHint">(Enter / Esc untuk tutup)</div>
  </div>
</div>

<div class="topbar">
  <div>
    <div class="brand"><?= htmlspecialchars($store) ?></div>
    <div class="sub">Edit Transaksi — #<?= htmlspecialchars($sale['invoice_no'] ?? $sale['id']) ?></div>
  </div>
  <div class="muted">ID: <?= (int)$sale['id'] ?> • Status: <?= htmlspecialchars($sale['status'] ?? '-') ?></div>
</div>

<div class="wrap">
  <div class="grand">
    <div>
      <div class="lbl">TOTAL (Rp)</div>
      <div id="grandDisplay" class="num">0</div>
    </div>
    <div class="grand-meta">
      <div class="meta-pill">
        <div class="k">Baris</div>
        <div class="v" id="totalRows">0</div>
      </div>
      <div class="meta-pill">
        <div class="k">Total Item</div>
        <div class="v" id="totalItems">0</div>
      </div>
      <div class="meta-pill">
        <div class="k">Tunai</div>
        <div class="v" id="vTunai">0</div>
      </div>
    </div>
  </div>

  <div class="layout">
    <div class="card">
      <header style="margin-bottom:.5rem;font-weight:600">
        Edit item / qty / level, lalu Simpan & Cetak. (F2 cari barang)
      </header>
      <div class="row" style="gap:.6rem;flex-wrap:wrap">
        <button class="btn add" id="btnAddRow" type="button">+ Tambah Baris</button>
        <span class="muted" style="flex:1;min-width:260px">
          Shortcut: F2 Cari Barang • F4 Tunai • F8 Poin • F9 Diskon • F10 Simpan & Cetak
        </span>
      </div>
    </div>

    <div class="card">
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

    <div class="card panel-bottom">
        <div class="kps">
          <label>Member (Kode)
            <div class="memberRow">
              <input list="memberlist" id="member_kode" placeholder="Ketik kode member" autocomplete="off">
              <button type="button" class="btn btn-mini" id="btnMemberSearch" title="Cari Member (Buka Master Member)">Cari</button>
            </div>
          <datalist id="memberlist">
            <?php foreach($members as $m): ?>
              <option value="<?= htmlspecialchars($m['kode']) ?>"><?= htmlspecialchars($m['nama']) ?></option>
            <?php endforeach; ?>
          </datalist>
        </label>

        <label>Nama Member
          <input type="text" id="member_nama" readonly>
        </label>

        <label>Poin Tersedia
          <input type="number" id="member_poin" readonly value="0">
        </label>
      </div>

      <div class="kps" style="margin-top:.8rem">
        <div>
          <div class="muted">Poin Ditukar</div>
          <input type="number" id="poin_ditukar" min="0" value="0" inputmode="numeric">
          <div class="muted">Potongan dari Poin: <span id="poin_potongan_view">0</span></div>
        </div>

        <div>
          <div class="muted">Diskon</div>
          <div class="row">
            <input type="number" id="discount" min="0" value="0" inputmode="numeric">
            <select id="discountMode">
              <option value="rp">Rp</option>
              <option value="pct">%</option>
            </select>
          </div>
        </div>

        <div>
          <div class="muted">PPN</div>
          <div class="row">
            <input type="number" id="tax" min="0" value="0" inputmode="numeric">
            <select id="taxMode">
              <option value="rp">Rp</option>
              <option value="pct">%</option>
            </select>
          </div>
        </div>
      </div>

      <div class="kps" style="margin-top:.8rem">
        <label>Shift
          <select id="shift">
            <option value="">-</option>
            <option value="1">1</option>
            <option value="2">2</option>
          </select>
        </label>

        <div>
          <div class="muted">Tunai</div>
          <input type="text" id="tunai" value="0" inputmode="numeric" autocomplete="off">
        </div>

        <div>
          <div class="muted">Kembalian</div>
          <input type="text" id="kembalian" readonly value="0">
        </div>
      </div>

      <div class="row no-print" style="margin-top:1rem;gap:.8rem;flex-wrap:wrap">
        <button class="btn" id="btnSave" type="button">Simpan & Cetak</button>
        <a class="btn" href="/tokoapp/sales_report.php?from=<?= htmlspecialchars(substr((string)$sale['created_at'],0,10)) ?>&to=<?= htmlspecialchars(substr((string)$sale['created_at'],0,10)) ?>&status=ok">Kembali</a>
        <span class="muted" id="saveStatus" style="flex:1;min-width:260px"></span>
      </div>
    </div>
  </div>
</div>

<script>
  // ===== Init data from PHP
  const INIT = <?= json_encode($init, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  const RP_PER_POINT_UMUM   = <?= (int)$redeem_umum ?>;
  const RP_PER_POINT_GROSIR = <?= (int)$redeem_grosir ?>;

  const cart = [];
  let activeIdx = -1;
  let memberType = null;

  const tbody = document.querySelector('#cartTable tbody');
  const subtotalEl = document.getElementById('subtotal');
  const tdiscountEl = document.getElementById('tdiscount');
  const ttaxEl = document.getElementById('ttax');
  const tpointdiscEl = document.getElementById('tpointdisc');
  const gtotalEl = document.getElementById('gtotal');
  const grandDisplay = document.getElementById('grandDisplay');

  const totalRowsEl = document.getElementById('totalRows');
  const totalItemsEl = document.getElementById('totalItems');
  const vTunaiEl = document.getElementById('vTunai');

  const memberKodeEl = document.getElementById('member_kode');
  const memberNamaEl = document.getElementById('member_nama');
  const memberPoinEl = document.getElementById('member_poin');
  const btnMemberSearch = document.getElementById('btnMemberSearch');

  const poinDitukarEl = document.getElementById('poin_ditukar');
  const poinPotonganView = document.getElementById('poin_potongan_view');

  const discountEl = document.getElementById('discount');
  const discountModeEl = document.getElementById('discountMode');
  const taxEl = document.getElementById('tax');
  const taxModeEl = document.getElementById('taxMode');

  const shiftEl = document.getElementById('shift');

  const tunaiEl = document.getElementById('tunai');
  const kembalianEl = document.getElementById('kembalian');

  const btnSave = document.getElementById('btnSave');
  const btnAddRow = document.getElementById('btnAddRow');
  const saveStatus = document.getElementById('saveStatus');

  const changeOverlay = document.getElementById('changeOverlay');
  const changeValueEl = document.getElementById('changeValue');

  function formatID(n){ return new Intl.NumberFormat('id-ID').format(n); }
  function unformat(s){ return parseInt((s||'0').toString().replace(/[^\d]/g,''))||0; }

  function formatInputAsIDR(inputEl){
    const digits = (inputEl.value || '').replace(/[^\d]/g,'');
    if(!digits){ inputEl.value = ''; return; }
    inputEl.value = formatID(parseInt(digits,10));
  }

  function showKembalianOverlay(kembali){
    changeValueEl.textContent = formatID(Math.max(0, kembali));
    changeOverlay.style.display = 'block';
  }
  function hideKembalianOverlay(){
    changeOverlay.style.display = 'none';
  }

  changeOverlay.addEventListener('click', hideKembalianOverlay);
  window.addEventListener('keydown', (e)=>{
    if (changeOverlay.style.display === 'block' && (e.key === 'Enter' || e.key === 'Escape')) {
      hideKembalianOverlay();
    }
  });

  // tutup overlay saat print window bilang selesai
  window.addEventListener('message', (e)=>{
    if (e && e.data && e.data.type === 'PRINT_DONE'){
      hideKembalianOverlay();
    }
  });

  // ==========================
  // Picker barang (F2)
  // ==========================
  function openItemPicker(){
    const url = '/tokoapp/items.php?pick=1';
    const w = 1200, h = 760;
    const left = Math.max(0, (screen.width  - w) / 2);
    const top  = Math.max(0, (screen.height - h) / 2);
    const win = window.open(url, 'itemPicker', `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`);
    if (win) win.focus();
  }
  window.setItemFromPicker = function(value){
    const v = (value || '').toString().trim();
    if(!v) return;

    if (activeIdx < 0) {
      cart.push({ id:null, kode:'', nama:'', qty:1, level:1, prices:[0,0,0,0] });
      activeIdx = cart.length - 1;
    }
    replaceRowItem(activeIdx, v);
  };

  // ==========================
  // MEMBER picker popup (samakan style pos_display)
  // ==========================
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

  // ==========================
  // MEMBER: load by kode
  // ==========================
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
        // API kamu mungkin balikin "points" atau "poin". kita toleransi dua-duanya.
        const p = (m.points ?? m.poin ?? 0);
        memberPoinEl.value = p || 0;
        memberType = (m.jenis || 'umum');
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

  // ==========================
  // Fetch item by kode untuk row
  // ==========================
  async function fetchItem(kode){
    const res = await fetch('/tokoapp/api/get_item.php?q=' + encodeURIComponent(kode), { cache: 'no-store' });
    return await res.json();
  }

  async function replaceRowItem(idx, kode){
    const code = (kode || '').trim();
    if(!code) return;
    try{
      const it = await fetchItem(code);
      if(!it || !it.kode){ alert('Barang tidak ditemukan: ' + code); return; }

      // toleransi nama key dari API: harga_jual1..4
      const prices=[1,2,3,4].map(i=> parseInt(it['harga_jual'+i] ?? it['harga'+i] ?? 0) || 0);

      cart[idx].kode = it.kode;
      cart[idx].nama = it.nama;
      cart[idx].prices = prices;

      // default level ke 1
      cart[idx].level = 1;

      renderCart();
    }catch(e){
      alert('Gagal mengambil data barang: ' + code);
    }
  }

  // ==========================
  // Hitung potongan poin
  // ==========================
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
      // rapikan poin kalau cap terjadi
      if (perPoint > 0) {
        const p = Math.floor(disc / perPoint);
        poinDitukarEl.value = p;
        disc = p * perPoint;
      }
    }
    poinPotonganView.textContent = formatID(disc);
    return disc;
  }

  function hitungKembalian(grand){
    const tunai = unformat(tunaiEl.value);
    const kembali = Math.max(0, tunai - grand);
    kembalianEl.value = formatID(kembali);
    vTunaiEl.textContent = formatID(tunai);
    return kembali;
  }

  // ==========================
  // Render cart
  // ==========================
    function renderCart(){
    tbody.innerHTML = '';

    let subtotal = 0;
    let totalItems = 0;

    cart.forEach((r, idx) => {
      // pastikan prices selalu array 4 angka
      const h1 = parseInt((r.prices && r.prices[0]) ?? 0, 10) || 0;
      const h2 = parseInt((r.prices && r.prices[1]) ?? 0, 10) || 0;
      const h3 = parseInt((r.prices && r.prices[2]) ?? 0, 10) || 0;
      const h4 = parseInt((r.prices && r.prices[3]) ?? 0, 10) || 0;

      const lvl = parseInt(r.level ?? 1, 10) || 1;
      const level = Math.min(4, Math.max(1, lvl));
      r.level = level;

      const harga = [h1, h2, h3, h4][level - 1] || 0;

      const q = parseInt(r.qty ?? 0, 10) || 0;
      r.qty = q < 0 ? 0 : q;

      const total = r.qty * harga;

      subtotal += total;
      totalItems += r.qty;

      const tr = document.createElement('tr');
      if (idx === activeIdx) tr.classList.add('active-row');

      tr.innerHTML = `
        <td class="col-kode">${r.kode || '-'}</td>
        <td>${r.nama || '-'}</td>
        <td class="right col-qty">
          <input type="number" min="0" value="${r.qty}" class="qtyInput">
        </td>
        <td class="right col-harga">
          <div class="priceRow">
            <span class="priceText">${formatID(harga)}</span>
            <select class="lvlSel" title="Pilih Level Harga">
              <option value="1"${level===1?' selected':''}>H1 • ${formatID(h1)}</option>
              <option value="2"${level===2?' selected':''}>H2 • ${formatID(h2)}</option>
              <option value="3"${level===3?' selected':''}>H3 • ${formatID(h3)}</option>
              <option value="4"${level===4?' selected':''}>H4 • ${formatID(h4)}</option>
            </select>
          </div>
        </td>
        <td class="right col-total">${formatID(total)}</td>
        <td class="col-aksi">
          <div class="row" style="justify-content:flex-end;flex-wrap:wrap">
            <input class="codeInput" placeholder="kode/barcode..." style="width:140px">
            <button class="btn warn btnReplace" type="button">Ganti</button>
            <button class="btn danger btnDel" type="button">Hapus</button>
          </div>
        </td>
      `;

      // klik baris -> aktif (kecuali klik input/select/button)
      tr.addEventListener('click', (ev) => {
        const tag = ev.target.tagName;
        if (tag === 'INPUT' || tag === 'SELECT' || tag === 'BUTTON') return;
        activeIdx = idx;
        renderCart();
      });

      tbody.appendChild(tr);

      // qty input
      const qtyInput = tr.querySelector('.qtyInput');
      qtyInput.addEventListener('input', (e) => {
        const v = parseInt(e.target.value || '0', 10);
        cart[idx].qty = (isNaN(v) || v < 0) ? 0 : v;
        activeIdx = idx;
        renderCart();
      });

      // level select
      const lvlSel = tr.querySelector('.lvlSel');
      lvlSel.addEventListener('change', (e) => {
        const v = parseInt(e.target.value || '1', 10);
        cart[idx].level = Math.min(4, Math.max(1, v));
        activeIdx = idx;
        renderCart();
      });

      // ganti item pakai kode/barcode
      const codeInput = tr.querySelector('.codeInput');
      const btnReplace = tr.querySelector('.btnReplace');
      btnReplace.addEventListener('click', () => {
        const code = (codeInput.value || '').trim();
        if (!code) { alert('Ketik kode/barcode dulu.'); return; }
        replaceRowItem(idx, code);
        codeInput.value = '';
      });

      // hapus baris
      const btnDel = tr.querySelector('.btnDel');
      btnDel.addEventListener('click', () => {
        cart.splice(idx, 1);
        if (activeIdx >= cart.length) activeIdx = cart.length - 1;
        renderCart();
      });
    });

    // ===== footer hitung-hitung =====
    subtotalEl.textContent = formatID(subtotal);

    const dVal = parseInt(discountEl.value || '0', 10) || 0;
    const dMode = discountModeEl.value;
    const disc = (dMode === 'pct')
      ? Math.floor(subtotal * (dVal / 100))
      : Math.min(dVal, subtotal);

    const taxVal = parseInt(taxEl.value || '0', 10) || 0;
    const taxMode = taxModeEl.value;
    const taxBase = Math.max(0, subtotal - disc);
    const taxAmt = (taxMode === 'pct')
      ? Math.floor(taxBase * (taxVal / 100))
      : taxVal;

    const pointDisc = hitungPointDiscount(Math.max(0, subtotal - disc + taxAmt));

    tdiscountEl.textContent = formatID(disc);
    ttaxEl.textContent = formatID(taxAmt);
    tpointdiscEl.textContent = formatID(pointDisc);

    const grand = Math.max(0, subtotal - disc + taxAmt - pointDisc);
    gtotalEl.textContent = formatID(grand);
    grandDisplay.textContent = formatID(grand);

    totalRowsEl.textContent = formatID(cart.length);
    totalItemsEl.textContent = formatID(totalItems);

    hitungKembalian(grand);
  }

  // ==========================
  // Simpan & Cetak
  // ==========================
  async function simpanCetak(){
    if(cart.length === 0){
      alert('Item kosong.');
      return;
    }

    const subtotal = unformat(subtotalEl.textContent);
    const disc = unformat(tdiscountEl.textContent);
    const tax  = unformat(ttaxEl.textContent);
    const pdisc = unformat(tpointdiscEl.textContent);
    const total = Math.max(0, subtotal - disc + tax - pdisc);

    const tunai = unformat(tunaiEl.value);
    if (tunai < total){
      alert('Tunai belum cukup.');
      tunaiEl.focus(); tunaiEl.select && tunaiEl.select();
      return;
    }

    const items = cart.map(r=>{
      const harga = r.prices[(r.level-1)]||0;
      return {
        id: r.id || null,
        item_kode: r.kode,
        nama: r.nama,
        qty: r.qty,
        level: r.level,
        harga: harga
      };
    }).filter(x=>x.item_kode);

    const payload = {
      id: INIT.sale.id,
      member_kode: memberKodeEl.value || '',
      shift: shiftEl.value || '',
      poin_ditukar: parseInt(poinDitukarEl.value||'0',10) || 0,
      discount: parseInt(discountEl.value||'0',10) || 0,
      discount_mode: discountModeEl.value,
      tax: parseInt(taxEl.value||'0',10) || 0,
      tax_mode: taxModeEl.value,
      tunai: tunai,
      items
    };

    saveStatus.textContent = 'Menyimpan...';

    try{
      const form = new FormData();
      form.append('payload', JSON.stringify(payload));

      const res = await fetch(window.location.href, { method:'POST', body: form });
      const data = await res.json();

      if(!data || !data.success){
        throw new Error((data && data.error) ? data.error : 'Gagal menyimpan.');
      }

      saveStatus.textContent = 'Tersimpan. Membuka nota...';
      showKembalianOverlay(data.kembalian || 0);

      if (data.print_url){
        window.open(data.print_url, '_blank', 'noopener');
      } else {
        saveStatus.textContent = 'Tersimpan, tapi print_url kosong (cek $PRINT_URL_BASE).';
      }

    }catch(e){
      saveStatus.textContent = '';
      alert('Gagal menyimpan: ' + e.message);
    }
  }

  // ==========================
  // Events
  // ==========================
  [discountEl, discountModeEl, taxEl, taxModeEl, poinDitukarEl].forEach(el=>{
    el.addEventListener('input', ()=>renderCart());
  });

  tunaiEl.addEventListener('input', ()=>{
    formatInputAsIDR(tunaiEl);
    renderCart();
  });

  btnSave.addEventListener('click', simpanCetak);

  btnAddRow.addEventListener('click', ()=>{
    cart.push({ id:null, kode:'', nama:'', qty:1, level:1, prices:[0,0,0,0] });
    activeIdx = cart.length-1;
    renderCart();
  });

  // shortcut
  window.addEventListener('keydown', (e)=>{
    if(e.key === 'F2'){ e.preventDefault(); openItemPicker(); return; }
    if(e.key === 'F4'){ e.preventDefault(); tunaiEl.focus(); tunaiEl.select&&tunaiEl.select(); return; }
    if(e.key === 'F8'){ e.preventDefault(); poinDitukarEl.focus(); poinDitukarEl.select&&poinDitukarEl.select(); return; }
    if(e.key === 'F9'){ e.preventDefault(); discountEl.focus(); discountEl.select&&discountEl.select(); return; }
    if(e.key === 'F10'){ e.preventDefault(); simpanCetak(); return; }
  });

  // ==========================
  // Bootstrap state from INIT
  // ==========================
  function bootstrap(){
    INIT.items.forEach(it=>{
      cart.push({
        id: it.id,
        kode: it.kode,
        nama: it.nama,
        qty: it.qty,
        level: it.level,
        prices: it.prices || [0,0,0,0]
      });
    });
    activeIdx = cart.length ? 0 : -1;

    memberKodeEl.value = INIT.sale.member_kode || '';
    shiftEl.value = INIT.sale.shift || '';
    discountEl.value = INIT.sale.discount || 0;
    discountModeEl.value = INIT.sale.discount_mode || 'rp';
    taxEl.value = INIT.sale.tax || 0;
    taxModeEl.value = INIT.sale.tax_mode || 'rp';
    poinDitukarEl.value = INIT.sale.poin_ditukar || 0;
    tunaiEl.value = formatID(INIT.sale.tunai || 0);

    if (memberKodeEl.value) {
      loadMemberByKode(memberKodeEl.value).then(()=>renderCart());
    } else {
      renderCart();
    }
  }

  bootstrap();
</script>

</body>
</html>
