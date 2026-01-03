<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);
require_once __DIR__.'/functions.php'; // untuk ensure_stock_rows

// =================== UNDUH CONTOH CSV (HARUS SEBELUM OUTPUT APAPUN) ===================
if (isset($_GET['sample']) && $_GET['sample'] === '1') {
  // Pastikan tidak ada output buffer tersisa
  if (ob_get_level()) { ob_end_clean(); }

  $filename = 'contoh_import_items.csv';
  header('Content-Type: text/csv; charset=utf-8');
  header('Content-Disposition: attachment; filename="'.$filename.'"');
  header('Pragma: no-cache');
  header('Expires: 0');

  $out = fopen('php://output', 'w');

  // Header kolom
  fputcsv($out, ['kode','barcode','nama','unit_code','harga_beli','harga_jual1','harga_jual2','harga_jual3','harga_jual4','min_stock']);

  // Contoh baris
  fputcsv($out, ['BRG-001','8991234567890','Gula Pasir 1kg','PCS',12000,15000,0,0,0,10]);
  fputcsv($out, ['BRG-002','8990987654321','Minyak Goreng 1L','PCS',14000,17500,0,0,0,5]);
  fputcsv($out, ['BRG-003','','Sabun Mandi 80gr','PCS',3000,4000,0,0,0,0]);

  fclose($out);
  exit; // WAJIB: hentikan eksekusi agar tidak ada output lain
}

// =================== SETELAH BLOK SAMPLE, BARU BOLEH INCLUDE HEADER UI ===================
require_once __DIR__.'/includes/header.php';

$info = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
  if ($_FILES['file']['error'] === UPLOAD_ERR_OK) {
    $tmp = $_FILES['file']['tmp_name'];
    $fh  = fopen($tmp, 'r');
    if (!$fh) {
      $info = "Gagal membaca file upload.";
    } else {
      $row = 0; $ok = 0; $fail = 0;

      // Map kolom dinamis
      $map = [
        'kode'         => null,
        'barcode'      => null,
        'nama'         => null,
        'unit_code'    => null, // bisa 'unit' / 'unit_code' / 'satuan'
        'harga_beli'   => null,
        'harga_jual1'  => null,
        'harga_jual2'  => null,
        'harga_jual3'  => null,
        'harga_jual4'  => null,
        'min_stock'    => null,
      ];
      $header = null;

      while (($data = fgetcsv($fh, 0, ",")) !== false) {
        $row++;
        if ($row === 1) {
          $lower = array_map(fn($x)=>strtolower(trim((string)$x)), $data);
          $looksHeader = in_array('kode', $lower, true) || in_array('code', $lower, true) || in_array('barcode', $lower, true);
          if ($looksHeader) {
            $header = $lower;

            $map['kode']        = array_search('kode', $header, true);
            if ($map['kode'] === false) { $map['kode'] = array_search('code', $header, true); }

            $map['barcode']     = array_search('barcode', $header, true);
            $map['nama']        = array_search('nama', $header, true);

            $map['unit_code']   = array_search('unit_code', $header, true);
            if ($map['unit_code'] === false || $map['unit_code'] === null) {
              $map['unit_code'] = array_search('unit', $header, true);
              if ($map['unit_code'] === false || $map['unit_code'] === null) {
                $map['unit_code'] = array_search('satuan', $header, true);
              }
            }

            $map['harga_beli']  = array_search('harga_beli', $header, true);
            $map['harga_jual1'] = array_search('harga_jual1', $header, true);
            $map['harga_jual2'] = array_search('harga_jual2', $header, true);
            $map['harga_jual3'] = array_search('harga_jual3', $header, true);
            $map['harga_jual4'] = array_search('harga_jual4', $header, true);
            $map['min_stock']   = array_search('min_stock', $header, true);
            continue; // lanjut ke baris data berikutnya
          } else {
            // Tanpa header → fallback posisi:
            // 0=kode,1=nama,2=unit,3=harga_beli,4=h1,5=h2,6=h3,7=h4,8=min_stock (opsional),9=barcode (opsional)
            $map['kode']        = 0;
            $map['nama']        = 1;
            $map['unit_code']   = 2;
            $map['harga_beli']  = 3;
            $map['harga_jual1'] = 4;
            $map['harga_jual2'] = 5;
            $map['harga_jual3'] = 6;
            $map['harga_jual4'] = 7;
            $map['min_stock']   = 8;
            $map['barcode']     = 9;
            // langsung proses baris pertama sebagai data
          }
        }

        $get = function($idx) use ($data) {
          if ($idx === false || $idx === null) return null;
          return $data[$idx] ?? null;
        };

        $kode       = trim((string)($get($map['kode']) ?? ''));
        $barcode    = trim((string)($get($map['barcode']) ?? ''));
        $nama       = trim((string)($get($map['nama']) ?? ''));
        $unit_code  = trim((string)($get($map['unit_code']) ?? 'PCS'));
        $harga_beli = (int)($get($map['harga_beli']) ?? 0);
        $h1         = (int)($get($map['harga_jual1']) ?? 0);
        $h2         = (int)($get($map['harga_jual2']) ?? 0);
        $h3         = (int)($get($map['harga_jual3']) ?? 0);
        $h4         = (int)($get($map['harga_jual4']) ?? 0);
        $min_stock  = (int)($get($map['min_stock']) ?? 0);

        if ($kode === '' || $nama === '') { $fail++; continue; }

        try {
          $stmt = $pdo->prepare("
            INSERT INTO items
              (kode, barcode, nama, unit_code, harga_beli, harga_jual1, harga_jual2, harga_jual3, harga_jual4, min_stock, created_at, updated_at)
            VALUES
              (?,?,?,?,?,?,?,?,?,?,NOW(),NOW())
            ON DUPLICATE KEY UPDATE
              barcode     = VALUES(barcode),
              nama        = VALUES(nama),
              unit_code   = VALUES(unit_code),
              harga_beli  = VALUES(harga_beli),
              harga_jual1 = VALUES(harga_jual1),
              harga_jual2 = VALUES(harga_jual2),
              harga_jual3 = VALUES(harga_jual3),
              harga_jual4 = VALUES(harga_jual4),
              min_stock   = VALUES(min_stock),
              updated_at  = NOW()
          ");
          $stmt->execute([$kode,$barcode,$nama,$unit_code,$harga_beli,$h1,$h2,$h3,$h4,$min_stock]);

          ensure_stock_rows($pdo, $kode);
          $ok++;
        } catch (Throwable $e) {
          $fail++;
        }
      }
      fclose($fh);
      $info = "Import selesai. Berhasil: $ok, Gagal: $fail";
    }
  } else {
    $info = "Upload gagal.";
  }
}
?>
<article>
  <h3>Import Data Barang</h3>

  <p>Format CSV yang didukung (disarankan):</p>
  <pre>kode,barcode,nama,unit_code,harga_beli,harga_jual1,harga_jual2,harga_jual3,harga_jual4,min_stock</pre>

  <ul>
    <li><strong>Wajib:</strong> <code>kode</code>, <code>nama</code>, <code>unit_code</code></li>
    <li><strong>Opsional:</strong> <code>barcode</code>, <code>harga_beli</code>, <code>harga_jual1..4</code>, <code>min_stock</code></li>
    <li>Header alternatif untuk <code>unit_code</code>: <code>unit</code>, <code>satuan</code></li>
  </ul>

  <p>
    <a class="secondary" href="?sample=1">⬇️ Unduh Contoh CSV</a>
  </p>

  <?php if($info): ?>
    <mark style="display:block;margin:.6rem 0;"><?= htmlspecialchars($info) ?></mark>
  <?php endif; ?>

  <form method="post" enctype="multipart/form-data">
    <label>Pilih file CSV
      <input type="file" name="file" accept=".csv" required>
    </label>
    <button type="submit">Import</button>
    <a href="items.php" class="secondary">Kembali</a>
  </form>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
