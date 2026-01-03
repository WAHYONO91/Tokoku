<?php
// save_sale.php
// Simpan transaksi dari POS + hitung & update poin (earn + redeem)
// Output: halaman print-helper (auto print & close)

require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();

// -------------------------------------
// 1. Ambil payload dari POS
// -------------------------------------
$payload = $_POST['payload'] ?? '';
if (!$payload) {
    http_response_code(400);
    die('Gagal simpan penjualan: payload kosong');
}

$data = json_decode($payload, true);
if (!$data || !is_array($data)) {
    http_response_code(400);
    die('Gagal simpan penjualan: payload tidak valid');
}

// Field dari POS
$member_kode      = $data['member_kode'] ?? null;
$member_nama      = $data['member_nama'] ?? null; // hanya info
$member_poin_awal = (int)($data['member_poin'] ?? 0);

$shift            = $data['shift'] ?? '1';
$tunai            = (int)($data['tunai'] ?? 0);
$items            = $data['items'] ?? [];

$discountInput    = (int)($data['discount'] ?? 0);
$discountMode     = $data['discount_mode'] ?? 'rp'; // 'rp' / 'pct'
$taxInput         = (int)($data['tax'] ?? 0);
$taxMode          = $data['tax_mode'] ?? 'rp';      // 'rp' / 'pct'

$poin_ditukar     = (int)($data['poin_ditukar'] ?? 0);
$point_discount   = (int)($data['point_discount'] ?? 0);

$user             = $_SESSION['user']['username'] ?? 'kasir';
$location         = 'toko';

if (empty($items)) {
    http_response_code(400);
    die('Gagal simpan penjualan: item kosong');
}

// -------------------------------------
// 2. Hitung SUBTOTAL dari items (safety)
// -------------------------------------
$subtotal = 0;
foreach ($items as $it) {
    $qty   = max(1, (int)($it['qty'] ?? 0));
    $harga = max(0, (int)($it['harga'] ?? 0));
    $subtotal += $qty * $harga;
}

// -------------------------------------
// 3. Hitung diskon & pajak (Rp / %)
// -------------------------------------
$discountInput = max(0, $discountInput);
$taxInput      = max(0, $taxInput);

$disc = 0;
if ($discountInput > 0) {
    if ($discountMode === 'pct') {
        $disc = (int) floor($subtotal * ($discountInput / 100));
    } else {
        $disc = min($discountInput, $subtotal);
    }
}

$taxAmt  = 0;
$taxBase = max(0, $subtotal - $disc);
if ($taxInput > 0) {
    if ($taxMode === 'pct') {
        $taxAmt = (int) floor($taxBase * ($taxInput / 100));
    } else {
        $taxAmt = $taxInput;
    }
}

// Total sebelum potongan poin (dasar earning poin)
$total_no_point = $subtotal - $disc + $taxAmt;
if ($total_no_point < 0) $total_no_point = 0;

// -------------------------------------
// 4. Potongan poin & total final
// -------------------------------------
$point_discount = max(0, $point_discount);
if ($point_discount > $total_no_point) {
    $point_discount = $total_no_point;
}

$total = $total_no_point - $point_discount;
if ($total < 0) $total = 0;

// Kembalian
$kembalian = $tunai - $total;
if ($kembalian < 0) $kembalian = 0;

// -------------------------------------
// 5. Ambil aturan earning poin dari settings
// -------------------------------------
$setting = $pdo->query("
    SELECT
        points_per_rupiah,
        points_per_rupiah_umum,
        points_per_rupiah_grosir
    FROM settings
    WHERE id = 1
")->fetch(PDO::FETCH_ASSOC);

$global_ppr = (int)($setting['points_per_rupiah'] ?? 0);
$ppr_umum   = (int)($setting['points_per_rupiah_umum'] ?? 0);
$ppr_grosir = (int)($setting['points_per_rupiah_grosir'] ?? 0);

$threshold_umum   = $ppr_umum   > 0 ? $ppr_umum   : $global_ppr;
$threshold_grosir = $ppr_grosir > 0 ? $ppr_grosir : $global_ppr;

// -------------------------------------
// 6. Ambil jenis member (umum / grosir)
// -------------------------------------
$member_jenis = 'umum';
if ($member_kode) {
    $mstmt = $pdo->prepare("SELECT jenis FROM members WHERE kode = ?");
    $mstmt->execute([$member_kode]);
    $mrow = $mstmt->fetch(PDO::FETCH_ASSOC);
    if ($mrow) {
        $mj = strtolower(trim((string)$mrow['jenis']));
        $member_jenis = ($mj === 'grosir' || $mj === 'umum') ? $mj : 'umum';
    }
}

// -------------------------------------
// 7. Hitung poin earning (dari total_no_point)
// -------------------------------------
$points_award = 0;
if ($member_kode) {
    $threshold = ($member_jenis === 'grosir')
        ? ($threshold_grosir ?: $threshold_umum)
        : ($threshold_umum ?: $threshold_grosir);

    if ($threshold > 0 && $total_no_point > 0) {
        $points_award = (int) floor($total_no_point / $threshold);
    }
}

// -------------------------------------
// 8. Validasi tunai
// -------------------------------------
if ($tunai < $total) {
    http_response_code(400);
    die('Tunai belum cukup / belum diinput.');
}

// -------------------------------------
// 9. Simpan DB (sales, sale_items, stok, update poin)
// -------------------------------------
$invoice_no = 'S' . date('YmdHis');

try {
    $pdo->beginTransaction();

    // ✅ INSERT SALES (Tambahkan poin_ditukar & point_discount)
    $stmt = $pdo->prepare("
        INSERT INTO sales
            (invoice_no, member_kode, shift, subtotal, discount, tax, total, tunai, kembalian,
             created_by, created_at, status, discount_mode, tax_mode, poin_ditukar, point_discount)
        VALUES
            (?,?,?,?,?,?,?,?,?, ?, NOW(),'OK', ?, ?, ?, ?)
    ");
    $stmt->execute([
        $invoice_no,
        $member_kode,
        $shift,
        $subtotal,
        $disc,
        $taxAmt,
        $total,
        $tunai,
        $kembalian,
        $user,
        $discountMode,
        $taxMode,
        $poin_ditukar,
        $point_discount
    ]);
    $sale_id = (int)$pdo->lastInsertId();

    // Insert ITEMS + update stok
    $stmtItem = $pdo->prepare("
        INSERT INTO sale_items (sale_id, item_kode, nama, qty, harga, total)
        VALUES (?,?,?,?,?,?)
    ");

    foreach ($items as $it) {
        $kode  = (string)($it['kode'] ?? '');
        $nama  = (string)($it['nama'] ?? '');
        $qty   = max(1, (int)($it['qty'] ?? 0));
        $harga = max(0, (int)($it['harga'] ?? 0));
        $line_total = $qty * $harga;

        if ($kode === '') continue;

        $stmtItem->execute([$sale_id, $kode, $nama, $qty, $harga, $line_total]);

        // stok lokasi "toko"
        adjust_stock($pdo, $kode, $location, -$qty);
    }

    // Update poin member (earn + redeem)
    if ($member_kode) {
        $delta_points = $points_award - $poin_ditukar;

        $up = $pdo->prepare("
            UPDATE members
            SET points = GREATEST(COALESCE(points,0) + ?, 0)
            WHERE kode = ?
        ");
        $up->execute([$delta_points, $member_kode]);
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    die('Gagal simpan penjualan: '.$e->getMessage());
}

// -------------------------------------
// 10. PRINT HELPER: buka sale_print.php, print, close
// -------------------------------------
$printUrl = "sale_print.php?id=".$sale_id;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Struk...</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <style>
    body{font-family:system-ui,Arial,sans-serif;padding:16px}
    .muted{color:#666;font-size:14px}
  </style>
</head>
<body>
  <div><strong>Menyiapkan struk…</strong></div>
  <div class="muted">Jika struk tidak otomatis tercetak, klik tombol di bawah.</div>
  <p>
    <button id="btn" type="button">Buka Struk</button>
  </p>

<script>
  const url = <?= json_encode($printUrl) ?>;

  function go(){
    // pindah ke halaman struk (di window printWindow)
    location.href = url;
  }

  document.getElementById('btn').addEventListener('click', go);

  // auto
  setTimeout(go, 80);
</script>
</body>
</html>
