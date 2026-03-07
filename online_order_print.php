<?php
// online_order_print.php
// Cetak struk/faktur pengiriman pesanan online

// DEBUG sementara (hapus kalau sudah stabil)
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();

// 1. Ambil ID pesanan
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo "ID tidak valid";
    exit;
}

// 2. Ambil data pesanan
$stmt = $pdo->prepare("SELECT * FROM online_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "Pesanan tidak ditemukan";
    exit;
}

// Ambil item
$itemsStmt = $pdo->prepare("SELECT * FROM online_order_items WHERE order_id = ?");
$itemsStmt->execute([$id]);
$items = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Ambil settings + layout print
$setting = $pdo->query("
    SELECT 
        store_name,
        store_address,
        store_phone,
        footer_note,
        logo_url,
        print_paper_width_mm,
        print_paper_height_mm,
        print_margin_mm,
        print_font_size_px,
        print_line_height,
        print_show_logo,
        print_logo_align,
        print_show_store_name,
        print_show_store_address,
        print_show_store_phone,
        print_show_invoice_line,
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

// show/hide bagian total
$printShowFooter = isset($setting['print_show_footer']) ? (bool)$setting['print_show_footer'] : true;

// header & logo
$printShowLogo = isset($setting['print_show_logo']) ? (bool)$setting['print_show_logo'] : true;
$logoAlign     = $setting['print_logo_align'] ?? 'center';
if (!in_array($logoAlign, ['left','center','right'], true)) $logoAlign = 'center';

$showStoreName    = isset($setting['print_show_store_name'])     ? (bool)$setting['print_show_store_name']     : true;
$showStoreAddress = isset($setting['print_show_store_address'])  ? (bool)$setting['print_show_store_address']  : true;
$showStorePhone   = isset($setting['print_show_store_phone'])    ? (bool)$setting['print_show_store_phone']    : true;
$showInvoiceLine  = isset($setting['print_show_invoice_line'])   ? (bool)$setting['print_show_invoice_line']   : true;

// style nama toko
$storeNameFontSize = (isset($setting['print_store_name_font_size_px']) && $setting['print_store_name_font_size_px'] > 0)
    ? (int)$setting['print_store_name_font_size_px']
    : max(12, $paperFontSize + 2);

$storeNameBold = isset($setting['print_store_name_bold']) ? (bool)$setting['print_store_name_bold'] : true;

$storeNameAlign = $setting['print_store_name_align'] ?? 'center';
if (!in_array($storeNameAlign, ['left','center','right'], true)) $storeNameAlign = 'center';

// 4. Perhitungan akhir
$subtotal = (int)$order['subtotal'];
$total    = (int)$order['total'];
$tanggalOrder = !empty($order['tanggal']) ? date('d/m/Y H:i', strtotime($order['tanggal'])) : date('d/m/Y H:i');

$invoice_str = "WEB-" . str_pad($order['id'], 5, '0', STR_PAD_LEFT);

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
  <title>Cetak Pesanan #<?= htmlspecialchars($order['id']) ?></title>
  <style>
    @media print {
      @page {
        size: <?= $cssPaperWidth ?>mm <?= $cssPaperHeight ?>;
        margin: <?= $cssPaperMargin ?>;
      }
      body { margin:0; padding:0; background:#fff; }
    }
    body { background:#fff; color:#000; margin:0; padding:0; }
    .struk {
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
    .struk table { width:100%; border-collapse:collapse; }
    .struk td { padding:2px 0; vertical-align:top; }
    .right { text-align:right; }
    hr { border:0; border-top:1px dashed #000; margin:4px 0; }
    .struk, .struk * { page-break-inside: avoid; }
    @media print { .no-print { display:none; } }
  </style>

  <script>
  (function () {
    window.addEventListener('load', function () {
      setTimeout(function () {
        try { window.focus(); } catch (e) {}
        try { window.print(); } catch (e) {}
      }, 50);
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

  <div style="text-align:center; font-weight:bold; font-size: <?= $paperFontSize + 1 ?>px; margin: 4px 0;">
    FAKTUR PENGIRIMAN
  </div>

  <?php if ($showInvoiceLine): ?>
    <div>No: <?= htmlspecialchars($invoice_str) ?> | <?= $tanggalOrder ?></div>
  <?php endif; ?>
  
  <hr>
  <div><strong>Pengiriman Kepada:</strong></div>
  <div><?= htmlspecialchars($order['guest_name']) ?></div>
  <div>Tlp: <?= htmlspecialchars($order['guest_phone']) ?></div>
  <div style="white-space:pre-line; margin-top:2px;"><?= nl2br(htmlspecialchars($order['guest_address'])) ?></div>
  <?php if (!empty($order['note'])): ?>
    <div style="margin-top:2px;">Note: <em><?= htmlspecialchars($order['note']) ?></em></div>
  <?php endif; ?>

  <hr>

  <table>
  <?php foreach ($items as $it): ?>
    <tr>
      <td colspan="3"><?= htmlspecialchars($it['nama_item']) ?></td>
    </tr>
    <tr>
      <td><?= (int)$it['qty'] ?> x <?= number_format((int)$it['total'] / max(1, $it['qty']), 0, ',', '.') ?></td>
      <td></td>
      <td class="right"><?= number_format((int)$it['total'], 0, ',', '.') ?></td>
    </tr>
  <?php endforeach; ?>
  </table>

  <hr>

  <table>
    <tr>
      <td>Subtotal</td>
      <td class="right"><?= number_format($subtotal, 0, ',', '.') ?></td>
    </tr>
    <tr>
      <td>Metode Pembayaran</td>
      <td class="right"><strong><?= htmlspecialchars($order['payment_method']) ?></strong></td>
    </tr>
    <tr>
      <td>Status Bayar</td>
      <td class="right"><?= htmlspecialchars($order['payment_status']) ?></td>
    </tr>
    <tr>
      <td><strong style="font-size:<?= $paperFontSize + 1 ?>px;">Total Order</strong></td>
      <td class="right"><strong style="font-size:<?= $paperFontSize + 1 ?>px;"><?= number_format($total, 0, ',', '.') ?></strong></td>
    </tr>
  </table>

  <?php if ($printShowFooter && $footer): ?>
    <hr>
    <div style="text-align:center;"><?= htmlspecialchars($footer) ?></div>
  <?php endif; ?>

  <hr>
  <div class="no-print" style="text-align:center;margin-top:6px;">
    <a href="javascript:window.close();" style="text-decoration:none; padding:4px 8px; border:1px solid #ccc; border-radius:4px; color:#000;">Tutup</a>
  </div>

</div>
</body>
</html>
