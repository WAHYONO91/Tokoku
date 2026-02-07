<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);

// Ambil data pengaturan toko
$setting = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
$msg = '';
$logo_error = '';

// --- MIGRASI OTOMATIS: tambah kolom theme jika belum ada ---
try {
  $pdo->exec("ALTER TABLE settings ADD COLUMN IF NOT EXISTS theme VARCHAR(20) DEFAULT 'dark'");
} catch (PDOException $e) {}

// Refresh data setelah migrasi
if (empty($setting)) {
    $setting = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
}

// helper ambil POST atau default lama
function old_or($key, $default) {
  return array_key_exists($key, $_POST) ? $_POST[$key] : $default;
}

// helper aman int
function to_int($v, $def=0){ return (int) (is_numeric($v) ? $v : $def); }

// -------------------------
// DEFAULT POIN
// -------------------------
$points_per_rupiah_umum   = (float)($setting['points_per_rupiah_umum'] ?? 0.01);
$points_per_rupiah_grosir = (float)($setting['points_per_rupiah_grosir'] ?? 0.05);

$rupiah_per_point_umum   = to_int($setting['rupiah_per_point_umum'] ?? 100);
$rupiah_per_point_grosir = to_int($setting['rupiah_per_point_grosir'] ?? 25);

// -------------------------
// DEFAULT LAYOUT STRUK
// -------------------------
// Roll fisik 75 mm, tapi area cetak real biasanya sekitar 70–72 mm.
// Supaya teks tidak terpotong, pakai 70 mm sebagai lebar CSS default.
$print_paper_width_mm  = isset($setting['print_paper_width_mm']) && $setting['print_paper_width_mm'] > 0
    ? (float)$setting['print_paper_width_mm']
    : 70.0;

$print_paper_height_mm = isset($setting['print_paper_height_mm']) ? (float)$setting['print_paper_height_mm'] : 0.0;

// Margin luar (browser → tepi kertas)
$print_margin_mm       = isset($setting['print_margin_mm']) ? (float)$setting['print_margin_mm'] : 0.0;

// Batas teks kiri/kanan dari tepi area cetak
$print_text_margin_lr_mm = isset($setting['print_text_margin_lr_mm'])
    ? (float)$setting['print_text_margin_lr_mm']
    : 1.5; // 1.5–2 mm cukup aman

$print_font_size_px    = isset($setting['print_font_size_px']) && $setting['print_font_size_px'] > 0
    ? (int)$setting['print_font_size_px']
    : 10;
$print_line_height     = isset($setting['print_line_height']) && $setting['print_line_height'] > 0
    ? (float)$setting['print_line_height']
    : 1.2;

$print_show_tax           = isset($setting['print_show_tax'])           ? (int)$setting['print_show_tax']           : 1;
$print_show_discount      = isset($setting['print_show_discount'])      ? (int)$setting['print_show_discount']      : 1;
$print_show_point_section = isset($setting['print_show_point_section']) ? (int)$setting['print_show_point_section'] : 1;

// -------------------------
// DEFAULT SHOW/HIDE HEADER & FOOTER
// -------------------------
$print_show_logo  = isset($setting['print_show_logo']) ? (int)$setting['print_show_logo'] : 1;

$allowedLogoAlign  = ['left','center','right','none'];
$allowedTitleAlign = ['left','center','right'];

$print_logo_align_raw = $setting['print_logo_align'] ?? 'center';
$print_logo_align = in_array($print_logo_align_raw, $allowedLogoAlign, true) ? $print_logo_align_raw : 'center';

$print_show_store_name          = isset($setting['print_show_store_name'])          ? (int)$setting['print_show_store_name']          : 1;
$print_show_store_address       = isset($setting['print_show_store_address'])       ? (int)$setting['print_show_store_address']       : 1;
$print_show_store_phone         = isset($setting['print_show_store_phone'])         ? (int)$setting['print_show_store_phone']         : 1;
$print_show_invoice_line        = isset($setting['print_show_invoice_line'])        ? (int)$setting['print_show_invoice_line']        : 1;
$print_show_cashier_line        = isset($setting['print_show_cashier_line'])        ? (int)$setting['print_show_cashier_line']        : 1;
$print_show_member_section      = isset($setting['print_show_member_section'])      ? (int)$setting['print_show_member_section']      : 1;
$print_show_point_discount_line = isset($setting['print_show_point_discount_line']) ? (int)$setting['print_show_point_discount_line'] : 1;
$print_show_footer              = isset($setting['print_show_footer'])              ? (int)$setting['print_show_footer']              : 1;

// -------------------------
// DEFAULT STYLE NAMA TOKO (PELANGI MART)
// -------------------------
$store_name_font_size = isset($setting['print_store_name_font_size_px']) && $setting['print_store_name_font_size_px'] > 0
    ? (int)$setting['print_store_name_font_size_px']
    : 14; // default: sedikit lebih besar

$store_name_bold = isset($setting['print_store_name_bold'])
    ? (int)$setting['print_store_name_bold']
    : 1;

$store_name_align_raw = $setting['print_store_name_align'] ?? 'center';
$store_name_align = in_array($store_name_align_raw, $allowedTitleAlign, true) ? $store_name_align_raw : 'center';

// Simpan pengaturan baru
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Nilai dasar
  $store_name      = old_or('store_name', $setting['store_name'] ?? 'TOKO');
  $store_address   = old_or('store_address', $setting['store_address'] ?? '');
  $store_phone     = old_or('store_phone', $setting['store_phone'] ?? '');
  $footer_note     = old_or('footer_note', $setting['footer_note'] ?? '');
  $invoice_prefix  = old_or('invoice_prefix', $setting['invoice_prefix'] ?? 'INV/');
  $qr_provider_url = old_or('qr_provider_url', $setting['qr_provider_url'] ?? 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=');
  $theme           = old_or('theme', $setting['theme'] ?? 'dark');

  // Poin
  $points_per_rupiah_umum   = (float)old_or('points_per_rupiah_umum',   $points_per_rupiah_umum);
  $points_per_rupiah_grosir = (float)old_or('points_per_rupiah_grosir', $points_per_rupiah_grosir);

  $rupiah_per_point_umum   = to_int(old_or('rupiah_per_point_umum',   $rupiah_per_point_umum),   $rupiah_per_point_umum);
  $rupiah_per_point_grosir = to_int(old_or('rupiah_per_point_grosir', $rupiah_per_point_grosir), $rupiah_per_point_grosir);

  // Layout dasar
  $print_paper_width_mm  = (float)old_or('print_paper_width_mm',  $print_paper_width_mm);
  $print_paper_height_mm = (float)old_or('print_paper_height_mm', $print_paper_height_mm);
  $print_margin_mm       = (float)old_or('print_margin_mm',       $print_margin_mm);

  // Normalisasi lebar:
  // - kalau >80 mm, paksa 80
  // - kalau di antara 70–80 mm (misal 75), paksa 70 mm agar tidak melebihi area cetak roll 75 mm.
  if ($print_paper_width_mm > 80) {
      $print_paper_width_mm = 80.0;
  }
  if ($print_paper_width_mm >= 70 && $print_paper_width_mm <= 80) {
      $print_paper_width_mm = 70.0;
  }

  // Batas teks kiri/kanan
  $print_text_margin_lr_mm = (float)old_or('print_text_margin_lr_mm', $print_text_margin_lr_mm);
  if ($print_text_margin_lr_mm < 1.0) {
      $print_text_margin_lr_mm = 1.0;
  }

  $print_font_size_px    = to_int(old_or('print_font_size_px',    $print_font_size_px), $print_font_size_px);
  $print_line_height     = (float)old_or('print_line_height',     $print_line_height);

  $print_show_tax           = isset($_POST['print_show_tax'])           ? 1 : 0;
  $print_show_discount      = isset($_POST['print_show_discount'])      ? 1 : 0;
  $print_show_point_section = isset($_POST['print_show_point_section']) ? 1 : 0;

  // Header/footer & logo flags
  $print_show_logo  = isset($_POST['print_show_logo']) ? 1 : 0;

  $print_logo_align_post = $_POST['print_logo_align'] ?? $print_logo_align;
  $print_logo_align = in_array($print_logo_align_post, $allowedLogoAlign, true) ? $print_logo_align_post : $print_logo_align;

  $print_show_store_name          = isset($_POST['print_show_store_name'])          ? 1 : 0;
  $print_show_store_address       = isset($_POST['print_show_store_address'])       ? 1 : 0;
  $print_show_store_phone         = isset($_POST['print_show_store_phone'])         ? 1 : 0;
  $print_show_invoice_line        = isset($_POST['print_show_invoice_line'])        ? 1 : 0;
  $print_show_cashier_line        = isset($_POST['print_show_cashier_line'])        ? 1 : 0;
  $print_show_member_section      = isset($_POST['print_show_member_section'])      ? 1 : 0;
  $print_show_point_discount_line = isset($_POST['print_show_point_discount_line']) ? 1 : 0;
  $print_show_footer              = isset($_POST['print_show_footer'])              ? 1 : 0;

  // Style Nama Toko
  $store_name_font_size = to_int(old_or('print_store_name_font_size_px', $store_name_font_size), $store_name_font_size);
  $store_name_bold      = isset($_POST['print_store_name_bold']) ? 1 : 0;

  $store_name_align_post = $_POST['print_store_name_align'] ?? $store_name_align;
  $store_name_align = in_array($store_name_align_post, $allowedTitleAlign, true)
      ? $store_name_align_post
      : $store_name_align;

  // Logo upload
  $logo_url = $setting['logo_url'] ?? '';
  if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] === UPLOAD_ERR_OK) {
    $tmp  = $_FILES['logo_file']['tmp_name'];
    $name = $_FILES['logo_file']['name'];
    $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    $allowed = ['png','jpg','jpeg','gif','webp'];
    if (!in_array($ext, $allowed, true)) {
      $logo_error = 'Format file tidak didukung. Pakai png/jpg/jpeg/gif/webp.';
    } else {
      $uploadDir = __DIR__.'/uploads';
      if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
      $newName = 'logo_'.date('Ymd_His').'.'.$ext;
      $dest = $uploadDir . '/' . $newName;
      if (move_uploaded_file($tmp, $dest)) {
        $logo_url = '/tokoapp/uploads/'.$newName;
      } else {
        $logo_error = 'Gagal upload logo.';
      }
    }
  }

  // SIMPAN
  try {
    $stmt = $pdo->prepare("
      UPDATE settings
         SET store_name                = ?,
             store_address             = ?,
             store_phone               = ?,
             footer_note               = ?,
             logo_url                  = ?,
             invoice_prefix            = ?,
             points_per_rupiah_umum    = ?,
             points_per_rupiah_grosir  = ?,
             rupiah_per_point_umum     = ?,
             rupiah_per_point_grosir   = ?,
             qr_provider_url           = ?,
             print_paper_width_mm      = ?,
             print_paper_height_mm     = ?,
             print_margin_mm           = ?,
             print_text_margin_lr_mm   = ?,
             print_font_size_px        = ?,
             print_line_height         = ?,
             print_show_tax            = ?,
             print_show_discount       = ?,
             print_show_point_section  = ?,
             print_show_logo           = ?,
             print_logo_align          = ?,
             print_show_store_name           = ?,
             print_show_store_address       = ?,
             print_show_store_phone         = ?,
             print_show_invoice_line        = ?,
             print_show_cashier_line        = ?,
             print_show_member_section      = ?,
             print_show_point_discount_line = ?,
             print_show_footer              = ?,
             print_store_name_font_size_px  = ?,
             print_store_name_bold          = ?,
             print_store_name_align         = ?,
             theme                          = ?
       WHERE id=1
    ");
    $stmt->execute([
      $store_name,
      $store_address,
      $store_phone,
      $footer_note,
      $logo_url,
      $invoice_prefix,
      $points_per_rupiah_umum,
      $points_per_rupiah_grosir,
      $rupiah_per_point_umum,
      $rupiah_per_point_grosir,
      $qr_provider_url,
      $print_paper_width_mm,
      $print_paper_height_mm,
      $print_margin_mm,
      $print_text_margin_lr_mm,
      $print_font_size_px,
      $print_line_height,
      $print_show_tax,
      $print_show_discount,
      $print_show_point_section,
      $print_show_logo,
      $print_logo_align,
      $print_show_store_name,
      $print_show_store_address,
      $print_show_store_phone,
      $print_show_invoice_line,
      $print_show_cashier_line,
      $print_show_member_section,
      $print_show_point_discount_line,
      $print_show_footer,
      $store_name_font_size,
      $store_name_bold,
      $store_name_align,
      $theme
    ]);
    log_activity($pdo, 'UPDATE_SETTINGS', "Mengubah pengaturan toko (Nama: $store_name)");
    $msg = 'Pengaturan disimpan.';
  } catch (PDOException $e1) {
    $msg = 'Gagal menyimpan pengaturan: ' . $e1->getMessage();
  }

  // Refresh data pengaturan (kalau mau dipakai next load)
  $setting = $pdo->query("SELECT * FROM settings WHERE id=1")->fetch(PDO::FETCH_ASSOC) ?: [];
}

require_once __DIR__.'/includes/header.php';
?>

<article>
  <h3>Pengaturan Toko</h3>

  <?php if ($msg): ?>
    <mark><?= htmlspecialchars($msg) ?></mark>
  <?php endif; ?>

  <?php if ($logo_error): ?>
    <mark style="background:#fee2e2;color:#b91c1c"><?= htmlspecialchars($logo_error) ?></mark>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data" class="settings-form">
    <label>
      Nama Toko
      <input type="text" name="store_name" value="<?= htmlspecialchars($setting['store_name'] ?? '') ?>">
    </label>

    <label>
      Alamat
      <textarea name="store_address" rows="2"><?= htmlspecialchars($setting['store_address'] ?? '') ?></textarea>
    </label>

    <label>
      Telepon
      <input type="text" name="store_phone" value="<?= htmlspecialchars($setting['store_phone'] ?? '') ?>">
    </label>

    <label>
      Footer Struk
      <input type="text" name="footer_note" value="<?= htmlspecialchars($setting['footer_note'] ?? '') ?>">
    </label>

    <label>
      Logo (upload file lokal)
      <input type="file" name="logo_file" accept=".png,.jpg,.jpeg,.gif,.webp">
      <small>Biarkan kosong jika tidak mengganti logo.</small>
    </label>

    <label>
      Tema Aplikasi
      <select name="theme">
        <option value="dark" <?= ($setting['theme'] ?? 'dark') === 'dark' ? 'selected' : '' ?>>Dark (Gelap)</option>
        <option value="light" <?= ($setting['theme'] ?? 'dark') === 'light' ? 'selected' : '' ?>>Light (Terang)</option>
      </select>
    </label>

    <?php if (!empty($setting['logo_url'])): ?>
      <div style="margin-bottom:1rem;">
        <strong>Logo sekarang:</strong><br>
        <img src="<?= htmlspecialchars($setting['logo_url']) ?>" alt="Logo" style="max-height:80px;">
      </div>
    <?php endif; ?>

    <label>
      Prefix Invoice
      <input type="text" name="invoice_prefix" value="<?= htmlspecialchars($setting['invoice_prefix'] ?? 'INV/') ?>">
    </label>

    <label>
      QR Provider URL
      <input type="text" name="qr_provider_url" value="<?= htmlspecialchars($setting['qr_provider_url'] ?? 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=') ?>">
      <small>Dipakai kalau kamu generate QR di struk (opsional).</small>
    </label>

    <!-- Pengaturan Penerimaan Poin -->
    <fieldset style="border:1px solid #e5e7eb; padding:.8rem; border-radius:.5rem;">
      <legend><strong>Penerimaan Poin</strong></legend>
      <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <label>
          Member Umum (konfigurasi)
          <input type="number" name="points_per_rupiah_umum"
                 value="<?= htmlspecialchars((string)$points_per_rupiah_umum) ?>" step="0.01">
          <small>Sesuaikan dengan logika perhitungan poin di struk.</small>
        </label>
        <label>
          Member Grosir (konfigurasi)
          <input type="number" name="points_per_rupiah_grosir"
                 value="<?= htmlspecialchars((string)$points_per_rupiah_grosir) ?>" step="0.01">
        </label>
      </div>
    </fieldset>

    <!-- Pengaturan Penukaran Poin -->
    <fieldset style="border:1px solid #e5e7eb; padding:.8rem; border-radius:.5rem;">
      <legend><strong>Penukaran Poin</strong></legend>
      <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <label>
          Nilai 1 Poin (Member Umum, Rp)
          <input type="number" name="rupiah_per_point_umum"
                 value="<?= htmlspecialchars((string)$rupiah_per_point_umum) ?>" min="0" step="1">
          <small>Contoh: 100 → 1 poin = Rp100 potongan.</small>
        </label>
        <label>
          Nilai 1 Poin (Member Grosir, Rp)
          <input type="number" name="rupiah_per_point_grosir"
                 value="<?= htmlspecialchars((string)$rupiah_per_point_grosir) ?>" min="0" step="1">
          <small>Contoh: 25 → 1 poin = Rp25 potongan.</small>
        </label>
      </div>
    </fieldset>

    <!-- Pengaturan Layout Cetak Struk/Faktur -->
    <fieldset style="border:1px solid #e5e7eb; padding:.8rem; border-radius:.5rem;">
      <legend><strong>Layout Dasar Cetak Struk / Faktur</strong></legend>

      <div class="grid" style="display:grid;grid-template-columns:1fr 1fr;gap:.75rem;">
        <label>
          Lebar Kertas (mm)
          <input type="number" step="0.1" name="print_paper_width_mm"
                 value="<?= htmlspecialchars((string)$print_paper_width_mm) ?>">
          <small>Contoh: 58, 70, 75, 80 (roll 75 mm gunakan 70 mm sebagai area cetak).</small>
        </label>

        <label>
          Tinggi Kertas (mm, 0 = auto)
          <input type="number" step="0.1" name="print_paper_height_mm"
                 value="<?= htmlspecialchars((string)$print_paper_height_mm) ?>">
          <small>Biarkan 0 agar panjang mengikuti isi.</small>
        </label>

        <label>
          Margin (mm)
          <input type="number" step="0.1" name="print_margin_mm"
                 value="<?= htmlspecialchars((string)$print_margin_mm) ?>">
        </label>

        <label>
          Batas Teks Kiri &amp; Kanan (mm)
          <input type="number" step="0.1" name="print_text_margin_lr_mm"
                 value="<?= htmlspecialchars((string)$print_text_margin_lr_mm) ?>">
          <small>Biasanya 1.5–2 mm untuk kertas 75 mm.</small>
        </label>

        <label>
          Ukuran Font (px)
          <input type="number" name="print_font_size_px"
                 value="<?= htmlspecialchars((string)$print_font_size_px) ?>" min="6" step="1">
        </label>

        <label>
          Line-height (jarak baris)
          <input type="number" step="0.1" name="print_line_height"
                 value="<?= htmlspecialchars((string)$print_line_height) ?>">
          <small>Misal: 1.1, 1.2, 1.4.</small>
        </label>
      </div>

      <div style="margin-top:.5rem;">
        <strong>Bagian yang ditampilkan di bagian total</strong><br>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_discount" <?= $print_show_discount ? 'checked' : '' ?>>
          Tampilkan baris Diskon
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_tax" <?= $print_show_tax ? 'checked' : '' ?>>
          Tampilkan baris PPN
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_point_section" <?= $print_show_point_section ? 'checked' : '' ?>>
          Tampilkan blok Info Poin Member (di bawah)
        </label>
      </div>
    </fieldset>

    <!-- Pengaturan Tampilan Header & Footer -->
    <fieldset style="border:1px solid #e5e7eb; padding:.8rem; border-radius:.5rem;">
      <legend><strong>Header (Logo & Info Toko) dan Footer</strong></legend>

      <div style="margin-bottom:.5rem;">
        <label style="display:block;margin-bottom:.3rem;">
          <input type="checkbox" name="print_show_logo" <?= $print_show_logo ? 'checked' : '' ?>>
          Tampilkan Logo di Struk
        </label>

        <label>
          Posisi Logo
          <select name="print_logo_align">
            <option value="left"   <?= $print_logo_align === 'left'   ? 'selected' : '' ?>>Kiri</option>
            <option value="center" <?= $print_logo_align === 'center' ? 'selected' : '' ?>>Tengah</option>
            <option value="right"  <?= $print_logo_align === 'right'  ? 'selected' : '' ?>>Kanan</option>
            <option value="none"   <?= $print_logo_align === 'none'   ? 'selected' : '' ?>>Jangan ditampilkan</option>
          </select>
        </label>
      </div>

      <div style="margin-top:.5rem;">
        <strong>Style Nama Toko (mis. PELANGI MART)</strong><br>
        <label style="display:block;margin-top:.25rem;">
          Ukuran Font Nama Toko (px)
          <input type="number" name="print_store_name_font_size_px"
                 value="<?= htmlspecialchars((string)$store_name_font_size) ?>" min="6" step="1">
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_store_name_bold" <?= $store_name_bold ? 'checked' : '' ?>>
          Tampil tebal (bold)
        </label>
        <label style="display:block;margin-top:.25rem;">
          Perataan Nama Toko
          <select name="print_store_name_align">
            <option value="left"   <?= $store_name_align === 'left'   ? 'selected' : '' ?>>Kiri</option>
            <option value="center" <?= $store_name_align === 'center' ? 'selected' : '' ?>>Tengah</option>
            <option value="right"  <?= $store_name_align === 'right'  ? 'selected' : '' ?>>Kanan</option>
          </select>
        </label>
      </div>

      <div style="margin-top:.5rem;">
        <strong>Info Header</strong><br>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_store_name" <?= $print_show_store_name ? 'checked' : '' ?>>
          Tampilkan Nama Toko
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_store_address" <?= $print_show_store_address ? 'checked' : '' ?>>
          Tampilkan Alamat
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_store_phone" <?= $print_show_store_phone ? 'checked' : '' ?>>
          Tampilkan Telepon
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_invoice_line" <?= $print_show_invoice_line ? 'checked' : '' ?>>
          Tampilkan baris No Invoice & Tanggal
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_cashier_line" <?= $print_show_cashier_line ? 'checked' : '' ?>>
          Tampilkan baris Kasir
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_member_section" <?= $print_show_member_section ? 'checked' : '' ?>>
          Tampilkan baris Member (jika ada)
        </label>
      </div>

      <div style="margin-top:.5rem;">
        <strong>Info Poin & Footer</strong><br>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_point_discount_line" <?= $print_show_point_discount_line ? 'checked' : '' ?>>
          Tampilkan baris "Potongan Poin" di tabel total
        </label>
        <label style="display:block;margin-top:.25rem;">
          <input type="checkbox" name="print_show_footer" <?= $print_show_footer ? 'checked' : '' ?>>
          Tampilkan Footer Struk
        </label>
      </div>
    </fieldset>

    <button type="submit">Simpan</button>
  </form>
</article>

<style>
.settings-form {
  display:flex;
  flex-direction:column;
  gap:.9rem;
  max-width:640px;
}
.settings-form label {
  display:flex;
  flex-direction:column;
  gap:.35rem;
}
.settings-form fieldset legend {
  padding:0 .35rem;
}
</style>

<?php include __DIR__.'/includes/footer.php'; ?>
