<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();

// Ambil setting id=1
$stmt = $pdo->prepare("SELECT * FROM settings WHERE id = 1 LIMIT 1");
$stmt->execute();
$setting = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$setting) {
    die("Settings not found");
}

// Default jika kosong
$defaults = [
    'print_paper_width_mm'     => 75,
    'print_paper_height_mm'    => 0,   // 0 = auto
    'print_margin_mm'          => 2,
    'print_font_size_px'       => 10,
    'print_line_height'        => 1.2,
    'print_show_tax'           => 1,
    'print_show_discount'      => 1,
    'print_show_point_section' => 1,
];

foreach ($defaults as $k => $v) {
    if (!isset($setting[$k]) || $setting[$k] === null || $setting[$k] === '') {
        $setting[$k] = $v;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $paper_width  = (float)($_POST['print_paper_width_mm'] ?? 75);
    $paper_height = (float)($_POST['print_paper_height_mm'] ?? 0);
    $margin       = (float)($_POST['print_margin_mm'] ?? 2);
    $font_size    = (int)($_POST['print_font_size_px'] ?? 10);
    $line_height  = (float)($_POST['print_line_height'] ?? 1.2);

    $show_tax      = isset($_POST['print_show_tax']) ? 1 : 0;
    $show_discount = isset($_POST['print_show_discount']) ? 1 : 0;
    $show_points   = isset($_POST['print_show_point_section']) ? 1 : 0;

    $update = $pdo->prepare("
        UPDATE settings SET
            print_paper_width_mm      = :w,
            print_paper_height_mm     = :h,
            print_margin_mm           = :m,
            print_font_size_px        = :fs,
            print_line_height         = :lh,
            print_show_tax            = :st,
            print_show_discount       = :sd,
            print_show_point_section  = :sp
        WHERE id = 1
    ");

    $update->execute([
        ':w'  => $paper_width,
        ':h'  => $paper_height,
        ':m'  => $margin,
        ':fs' => $font_size,
        ':lh' => $line_height,
        ':st' => $show_tax,
        ':sd' => $show_discount,
        ':sp' => $show_points,
    ]);

    header("Location: layout_struk_settings.php?saved=1");
    exit;
}
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Pengaturan Layout Struk / Faktur</title>
  <style>
    body{
      font-family: Arial, sans-serif;
      font-size: 14px;
      padding: 20px;
      background:#f5f5f5;
    }
    .card{
      background:#fff;
      padding:15px;
      border-radius:6px;
      max-width:480px;
      margin:0 auto;
      box-shadow:0 1px 4px rgba(0,0,0,.1);
    }
    .card h2{
      margin-top:0;
      margin-bottom:10px;
    }
    .form-group{
      margin-bottom:10px;
    }
    label{
      display:block;
      font-weight:bold;
      margin-bottom:4px;
    }
    input[type="number"]{
      width:100%;
      padding:6px;
      box-sizing:border-box;
    }
    .checkbox-group{
      margin-bottom:6px;
    }
    button{
      padding:8px 14px;
      cursor:pointer;
    }
    .alert{
      margin-bottom:10px;
      padding:8px;
      background:#e0ffe0;
      border:1px solid #8cc38c;
      border-radius:4px;
    }
  </style>
</head>
<body>
<div class="card">
  <h2>Pengaturan Layout Cetak Struk/Faktur</h2>

  <?php if (!empty($_GET['saved'])): ?>
    <div class="alert">Pengaturan berhasil disimpan.</div>
  <?php endif; ?>

  <form method="post">
    <div class="form-group">
      <label>Lebar kertas (mm)</label>
      <input type="number" step="0.1" name="print_paper_width_mm"
             value="<?= htmlspecialchars($setting['print_paper_width_mm']) ?>">
      <small>Contoh: 58, 75, 80</small>
    </div>

    <div class="form-group">
      <label>Tinggi kertas (mm, 0 = auto)</label>
      <input type="number" step="0.1" name="print_paper_height_mm"
             value="<?= htmlspecialchars($setting['print_paper_height_mm']) ?>">
      <small>Biarkan 0 kalau ingin auto (mengikuti panjang isi)</small>
    </div>

    <div class="form-group">
      <label>Margin (mm)</label>
      <input type="number" step="0.1" name="print_margin_mm"
             value="<?= htmlspecialchars($setting['print_margin_mm']) ?>">
    </div>

    <div class="form-group">
      <label>Ukuran font (px)</label>
      <input type="number" name="print_font_size_px"
             value="<?= htmlspecialchars($setting['print_font_size_px']) ?>">
    </div>

    <div class="form-group">
      <label>Line-height (jarak baris)</label>
      <input type="number" step="0.1" name="print_line_height"
             value="<?= htmlspecialchars($setting['print_line_height']) ?>">
    </div>

    <div class="form-group">
      <strong>Bagian yang ditampilkan</strong>
      <div class="checkbox-group">
        <label>
          <input type="checkbox" name="print_show_discount" <?= $setting['print_show_discount'] ? 'checked' : '' ?>>
          Tampilkan baris Diskon
        </label>
      </div>
      <div class="checkbox-group">
        <label>
          <input type="checkbox" name="print_show_tax" <?= $setting['print_show_tax'] ? 'checked' : '' ?>>
          Tampilkan baris PPN
        </label>
      </div>
      <div class="checkbox-group">
        <label>
          <input type="checkbox" name="print_show_point_section" <?= $setting['print_show_point_section'] ? 'checked' : '' ?>>
          Tampilkan Info Poin Member
        </label>
      </div>
    </div>

    <button type="submit">Simpan</button>
  </form>
</div>
</body>
</html>
