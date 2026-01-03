<?php
require_once __DIR__ . '/config.php';
require_login(); // hanya user yang login boleh mengakses

// Ambil data barang untuk dicetak barcode-nya
$sql = "SELECT kode, barcode FROM items";
$stmt = $pdo->prepare($sql);
$stmt->execute();
$items = $stmt->fetchAll();

// Pastikan ada barang yang ditemukan
if (!$items) {
  die('Tidak ada barang untuk dicetak.');
}

?>
<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Cetak Barcode</title>
  <style>
    @page {
      size: 210mm 330mm; /* Ukuran F4 */
      margin: 10mm;
    }
    body {
      font-family: Arial, sans-serif;
      font-size: 10pt;
    }
    h3 {
      text-align: center;
      margin-bottom: 8mm;
    }
    .labels {
      display: grid;
      grid-template-columns: repeat(5, 1fr); /* 5 kolom */
      gap: 4mm;
    }
    .label {
      border: 1px dashed #ccc;
      padding: 2mm;
      text-align: center;
    }
    .label svg {
      width: 100%; /* Menyesuaikan lebar */
      height: 30mm; /* Ukuran tinggi barcode lebih kecil */
    }
    .label div {
      margin-top: 2mm;
      font-size: 9pt;
    }
  </style>
</head>
<body>
  <h3>Cetak Semua Barcode</h3>

  <div class="labels">
    <?php foreach ($items as $item): ?>
      <div class="label">
        <svg class="barcode"></svg>
        <div><?= htmlspecialchars($item['kode']) ?></div>
      </div>
    <?php endforeach; ?>
  </div>

  <!-- Sisipkan JsBarcode untuk membuat barcode -->
  <script src="/tokoapp/assets/vendor/JsBarcode.all.min.js"></script>
  <script>
    window.onload = function() {
      var barcodes = <?php echo json_encode(array_column($items, 'barcode')); ?>;
      var svgs = document.querySelectorAll("svg.barcode");

      svgs.forEach(function(el, index) {
        // Generate barcode untuk setiap elemen SVG
        JsBarcode(el, barcodes[index], {
          format: "CODE128",
          displayValue: true,
          fontSize: 8, // Mengatur ukuran font menjadi lebih kecil
          height: 30,  // Mengatur tinggi barcode lebih kecil
          margin: 10,  // Menambahkan margin untuk jarak antar barcode
          textMargin: 5, // Menambahkan jarak antara barcode dan teks
        });
      });

      // Otomatis cetak halaman setelah barcode di-generate
      window.print();
    };
  </script>
</body>
</html>
