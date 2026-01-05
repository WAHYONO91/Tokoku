<?php
// sale_print.php
// Cetak struk + kirim sinyal balik ke POS agar overlay kembalian bisa ditutup

// DEBUG sementara (hapus kalau sudah stabil)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();

// 1. Ambil ID penjualan
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "ID tidak valid";
    exit;
}

// 2. Ambil data penjualan
$stmt = $pdo->prepare("SELECT * FROM sales WHERE id = ?");
$stmt->execute([$id]);
$sale = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$sale) {
    echo "Penjualan tidak ditemukan";
    exit;
}

// Ambil item
$itemsStmt = $pdo->prepare("SELECT * FROM sale_items WHERE sale_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Ambil settings + layout print
$setting = $pdo->query("
    SELECT 
        store_name,
        store_address,
        store_phone,
        footer_note,
        points_per_rupiah,
        points_per_rupiah_umum,
        points_per_rupiah_grosir,
        rupiah_per_point_umum,
        rupiah_per_point_grosir,
        redeem_rp_per_point_umum,
        redeem_rp_per_point_grosir,
        logo_url,
        -- layout print
        print_paper_width_mm,
        print_paper_height_mm,
        print_margin_mm,
        print_font_size_px,
        print_line_height,
        print_show_tax,
        print_show_discount,
        print_show_point_section,
        print_show_logo,
        print_logo_align,
        print_show_store_name,
        print_show_store_address,
        print_show_store_phone,
        print_show_invoice_line,
        print_show_cashier_line,
        print_show_member_section,
        print_show_point_discount_line,
        print_show_footer,
        print_store_name_font_size_px,
        print_store_name_bold,
        print_store_name_align,
        print_text_margin_lr_mm
    FROM settings
    WHERE id = 1
")->fetch(PDO::FETCH_ASSOC);

// --- info toko dasar ---
$storeName  = $setting['store_name']    ?? 'TOKO';
$storeAddr  = $setting['store_address'] ?? '';
$storePhone = $setting['store_phone']   ?? '';
$footer     = $setting['footer_note']   ?? '';
$logoUrl    = $setting['logo_url']      ?? '';

// --- poin ---
$global_ppr = (int)($setting['points_per_rupiah'] ?? 0);
$ppr_umum   = (int)($setting['points_per_rupiah_umum'] ?? 0);
$ppr_grosir = (int)($setting['points_per_rupiah_grosir'] ?? 0);

$threshold_umum   = $ppr_umum   > 0 ? $ppr_umum   : $global_ppr;
$threshold_grosir = $ppr_grosir > 0 ? $ppr_grosir : $global_ppr;

$redeem_pp_umum = (int)($setting['rupiah_per_point_umum'] ?? 0);
if ($redeem_pp_umum <= 0) $redeem_pp_umum = (int)($setting['redeem_rp_per_point_umum'] ?? 100);
if ($redeem_pp_umum <= 0) $redeem_pp_umum = 100;

$redeem_pp_grosir = (int)($setting['rupiah_per_point_grosir'] ?? 0);
if ($redeem_pp_grosir <= 0) $redeem_pp_grosir = (int)($setting['redeem_rp_per_point_grosir'] ?? 25);
if ($redeem_pp_grosir <= 0) $redeem_pp_grosir = 25;

// --- layout print (pakai default kalau kosong) ---
$paperWidthMm  = (isset($setting['print_paper_width_mm'])  && $setting['print_paper_width_mm']  > 0) ? (float)$setting['print_paper_width_mm']  : 75;
$paperHeightMm = (isset($setting['print_paper_height_mm']) && $setting['print_paper_height_mm'] > 0) ? (float)$setting['print_paper_height_mm'] : 0; // 0 = auto
$paperMarginMm = (isset($setting['print_margin_mm']) && $setting['print_margin_mm'] >= 0) ? (float)$setting['print_margin_mm'] : 2;

$paperFontSize = (isset($setting['print_font_size_px']) && $setting['print_font_size_px'] > 0) ? (int)$setting['print_font_size_px'] : 10;
$paperLineHeight = (isset($setting['print_line_height']) && $setting['print_line_height'] > 0) ? (float)$setting['print_line_height'] : 1.2;

// batas teks kiri/kanan di dalam kertas
$textMarginMm = isset($setting['print_text_margin_lr_mm'])
    ? (float)$setting['print_text_margin_lr_mm']
    : ($paperWidthMm == 75.0 ? 0.5 : 2.0);

// show/hide bagian total & poin
$printShowTax           = !empty($setting['print_show_tax']);
$printShowDiscount      = !empty($setting['print_show_discount']);
$printShowPoints        = !empty($setting['print_show_point_section']);
$printShowPointDiscLine = isset($setting['print_show_point_discount_line']) ? (bool)$setting['print_show_point_discount_line'] : true;
$printShowFooter        = isset($setting['print_show_footer']) ? (bool)$setting['print_show_footer'] : true;

// header & logo
$printShowLogo = isset($setting['print_show_logo']) ? (bool)$setting['print_show_logo'] : true;
$logoAlign     = $setting['print_logo_align'] ?? 'center';
if (!in_array($logoAlign, ['left','center','right'], true)) $logoAlign = 'center';

$showStoreName    = isset($setting['print_show_store_name'])     ? (bool)$setting['print_show_store_name']     : true;
$showStoreAddress = isset($setting['print_show_store_address'])  ? (bool)$setting['print_show_store_address']  : true;
$showStorePhone   = isset($setting['print_show_store_phone'])    ? (bool)$setting['print_show_store_phone']    : true;
$showInvoiceLine  = isset($setting['print_show_invoice_line'])   ? (bool)$setting['print_show_invoice_line']   : true;
$showCashierLine  = isset($setting['print_show_cashier_line'])   ? (bool)$setting['print_show_cashier_line']   : true;
$showMemberLine   = isset($setting['print_show_member_section']) ? (bool)$setting['print_show_member_section'] : true;

// style nama toko
$storeNameFontSize = (isset($setting['print_store_name_font_size_px']) && $setting['print_store_name_font_size_px'] > 0)
    ? (int)$setting['print_store_name_font_size_px']
    : max(12, $paperFontSize + 2);

$storeNameBold = isset($setting['print_store_name_bold']) ? (bool)$setting['print_store_name_bold'] : true;

$storeNameAlign = $setting['print_store_name_align'] ?? 'center';
if (!in_array($storeNameAlign, ['left','center','right'], true)) $storeNameAlign = 'center';

// 4. Info member
$member_kode         = $sale['member_kode'] ?? '';
$member_nama         = '';
$member_jenis        = 'umum';
$member_points_after = 0;   // default biar selalu ada angka
$member_found = false;

if ($member_kode) {
    $mstmt = $pdo->prepare("SELECT * FROM members WHERE kode = ?");
    $mstmt->execute([$member_kode]);
    $mrow = $mstmt->fetch(PDO::FETCH_ASSOC);
    if ($mrow) {
        $member_nama  = $mrow['nama'] ?? '';
        $mj = isset($mrow['jenis']) ? strtolower(trim($mrow['jenis'])) : 'umum';
        $member_jenis = ($mj === 'grosir' || $mj === 'umum') ? $mj : 'umum';
        $member_points_after = isset($mrow['points']) ? (int)$mrow['points'] : 0;

    }
}

// 5. Hitung poin & potongan poin
$subtotal = (int)$sale['subtotal'];
$disc     = (int)$sale['discount'];
$tax      = (int)$sale['tax'];
$total    = (int)$sale['total'];

$base_for_points = max(0, $subtotal - $disc + $tax);
$point_discount  = max(0, $base_for_points - $total);

$threshold = ($member_jenis === 'grosir') ? ($threshold_grosir ?: $threshold_umum) : ($threshold_umum ?: $threshold_grosir);

$earned_points = 0;
if ($member_kode && $threshold > 0 && $base_for_points > 0) {
    $earned_points = (int) floor($base_for_points / $threshold);
}

$points_redeemed = 0;
if ($member_kode && $point_discount > 0) {
    $redeem_pp = ($member_jenis === 'grosir') ? ($redeem_pp_grosir ?: $redeem_pp_umum) : ($redeem_pp_umum ?: $redeem_pp_grosir);
    if ($redeem_pp > 0) {
        $points_redeemed = (int) floor($point_discount / $redeem_pp);
    }
}

// 6. Info lain
$user      = $sale['created_by'] ?? '';
$createdAt = !empty($sale['created_at']) ? date('d/m/Y H:i', strtotime($sale['created_at'])) : date('d/m/Y H:i');

$tunai     = (int)$sale['tunai'];
$kembalian = (int)$sale['kembalian'];

// nilai CSS siap pakai
$cssPaperWidth   = number_format($paperWidthMm, 1, '.', '');
$cssPaperHeight  = $paperHeightMm > 0 ? number_format($paperHeightMm, 1, '.', '') . 'mm' : 'auto';
$cssPaperMargin  = number_format($paperMarginMm, 1, '.', '') . 'mm';
$cssTextMargin   = number_format($textMarginMm, 1, '.', '') . 'mm';
$cssLineHeight   = number_format($paperLineHeight, 2, '.', '');
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Penjualan</title>
  <style>
    @media print {
      @page {
        size: <?= $cssPaperWidth ?>mm <?= $cssPaperHeight ?>;
        margin: <?= $cssPaperMargin ?>;
      }
      body { margin:0; padding:0; background:#fff; }
    }
    body { background:#fff; color:#000; margin:0; padding:0; }
    .struk{
      width:100%;
      max-width:<?= $cssPaperWidth ?>mm;
      margin:0 auto;
      font-family:monospace;
      font-size:<?= (int)$paperFontSize ?>px;
      line-height:<?= $cssLineHeight ?>;
      word-wrap:break-word;
      overflow-wrap:break-word;
      padding-left: <?= $cssTextMargin ?>;
      padding-right: <?= $cssTextMargin ?>;
    }
    .struk table{ width:100%; border-collapse:collapse; }
    .struk td{ padding:2px 0; vertical-align:top; }
    .right{ text-align:right; }
    hr{ border:0; border-top:1px dashed #000; margin:4px 0; }
    .struk, .struk *{ page-break-inside: avoid; }
    @media print{ .no-print{display:none;} }
  </style>

  <script>
  // Cetak sekali, lalu setelah selesai: kirim sinyal ke opener + tutup window
  (function () {
    let done = false;

    function sendDone() {
      if (done) return;
      done = true;

      try {
        if (window.opener) {
          window.opener.postMessage({ type: 'PRINT_DONE' }, '*');
        }
      } catch (e) {}

      setTimeout(function () {
        try { window.close(); } catch (e) {}
      }, 200);
    }

    window.addEventListener('load', function () {
      setTimeout(function () {
        try { window.focus(); } catch (e) {}
        try { window.print(); } catch (e) {}
      }, 50);

      // Fallback: kalau afterprint tidak kejadian (cancel/bug), tetap kirim done
      setTimeout(sendDone, 30000);
    });

    window.addEventListener('afterprint', function () {
      sendDone();
    });
  })();
</script>

</head>

<body>
<div class="struk">

  <?php if ($printShowLogo && $logoUrl && $logoAlign !== 'none'): ?>
    <div style="text-align:<?= htmlspecialchars($logoAlign) ?>;margin-bottom:2px;">
      <img src="<?= htmlspecialchars($logoUrl) ?>" alt="Logo" style="max-height:60px;">
    </div>
  <?php endif; ?>

  <?php if ($showStoreName): ?>
    <div style="
      text-align:<?= htmlspecialchars($storeNameAlign) ?>;
      font-size:<?= (int)$storeNameFontSize ?>px;
      font-weight:<?= $storeNameBold ? 'bold' : 'normal' ?>;
      margin:4px 0;
    ">
      <?= htmlspecialchars($storeName) ?>
    </div>
  <?php endif; ?>

  <?php if ($showStoreAddress && $storeAddr): ?>
    <div style="text-align:center;white-space:pre-line;">
      <?= nl2br(htmlspecialchars($storeAddr)) ?>
    </div>
  <?php endif; ?>

  <?php if ($showStorePhone && $storePhone): ?>
    <div style="text-align:center;">Telp: <?= htmlspecialchars($storePhone) ?></div>
  <?php endif; ?>

  <?php if ($showStoreName || ($showStoreAddress && $storeAddr) || ($showStorePhone && $storePhone)): ?>
    <hr>
  <?php endif; ?>

  <?php if ($showInvoiceLine): ?>
    <div>No: <?= htmlspecialchars($sale['invoice_no'] ?? ('#'.$sale['id'])) ?> | <?= $createdAt ?></div>
  <?php endif; ?>

  <?php if ($showCashierLine): ?>
    <div>Kasir: <?= htmlspecialchars($user) ?></div>
  <?php endif; ?>

  <?php if ($member_kode && $showMemberLine): ?>
    <div>
      Member: <?= htmlspecialchars($member_kode) ?>
      <?php if ($member_nama): ?> - <?= htmlspecialchars($member_nama) ?><?php endif; ?>
      (<?= strtoupper(htmlspecialchars($member_jenis)) ?>)
    </div>
  <?php endif; ?>

  <hr>

  <table>
  <?php foreach ($items as $it): ?>
    <?php $line_total = ((int)$it['qty']) * ((int)$it['harga']); ?>
    <tr>
      <td colspan="3"><?= htmlspecialchars($it['nama']) ?></td>
    </tr>
    <tr>
      <td><?= (int)$it['qty'] ?> x <?= number_format((int)$it['harga'], 0, ',', '.') ?></td>
      <td></td>
      <td class="right"><?= number_format($line_total, 0, ',', '.') ?></td>
    </tr>
  <?php endforeach; ?>
</table>


  <hr>

  <table>
    <tr>
      <td>Subtotal</td>
      <td class="right"><?= number_format($subtotal, 0, ',', '.') ?></td>
    </tr>

    <?php if ($printShowDiscount): ?>
    <tr>
      <td>Diskon</td>
      <td class="right"><?= number_format($disc, 0, ',', '.') ?></td>
    </tr>
    <?php endif; ?>

    <?php if ($printShowTax): ?>
    <tr>
      <td>PPN</td>
      <td class="right"><?= number_format($tax, 0, ',', '.') ?></td>
    </tr>
    <?php endif; ?>

    <?php if ($printShowPointDiscLine): ?>
    <tr>
      <td>Potongan Poin</td>
      <td class="right"><?= number_format($point_discount, 0, ',', '.') ?></td>
    </tr>
    <?php endif; ?>

    <tr>
      <td><strong>Total</strong></td>
      <td class="right"><strong><?= number_format($total, 0, ',', '.') ?></strong></td>
    </tr>
    <tr>
      <td>Tunai</td>
      <td class="right"><?= number_format($tunai, 0, ',', '.') ?></td>
    </tr>
    <tr>
      <td>Kembali</td>
      <td class="right"><?= number_format($kembalian, 0, ',', '.') ?></td>
    </tr>
  </table>

  <?php if ($member_kode && $printShowPoints): ?>
    <hr>
    <div><strong>Info Poin Member</strong></div>
    <div>Poin dari transaksi ini: <?= number_format($earned_points, 0, ',', '.') ?></div>
    <div>Poin yang ditukar: <?= number_format($points_redeemed, 0, ',', '.') ?></div>
    <?php if ($member_points_after !== null): ?>
      <div>Total poin sekarang: <?= number_format($member_points_after, 0, ',', '.') ?></div>
    <?php endif; ?>
  <?php endif; ?>

  <?php if ($printShowFooter && $footer): ?>
    <hr>
    <div style="text-align:center;"><?= htmlspecialchars($footer) ?></div>
  <?php endif; ?>

  <hr>
  <div class="no-print" style="text-align:center;margin-top:6px;">
    <a href="/tokoapp/pos_display.php">Transaksi lagi</a>
  </div>

</div>
</body>
</html>
