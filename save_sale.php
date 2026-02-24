<?php
// save_sale.php
// Simpan transaksi POS (Tunai / Piutang)
// + update poin
// + cetak struk

require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';
require_login();

/* =====================================================
 * 1. Ambil payload
 * ===================================================== */
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

/* =====================================================
 * 2. Ambil field dari POS
 * ===================================================== */
$pay_mode         = $data['pay_mode'] ?? 'cash'; // cash | ar
$member_kode      = $data['member_kode'] ?? null;
$member_nama      = $data['member_nama'] ?? null;

$shift            = $data['shift'] ?? '1';
$tunai            = (int)($data['tunai'] ?? 0);
$items            = $data['items'] ?? [];

$discountInput    = (int)($data['discount'] ?? 0);
$discountMode     = $data['discount_mode'] ?? 'rp';
$taxInput         = (int)($data['tax'] ?? 0);
$taxMode          = $data['tax_mode'] ?? 'rp';

$poin_ditukar     = (int)($data['poin_ditukar'] ?? 0);
$point_discount   = (int)($data['point_discount'] ?? 0);

$user             = $_SESSION['user']['username'] ?? 'kasir';
$location         = 'toko';

if (empty($items)) {
    http_response_code(400);
    die('Gagal simpan penjualan: item kosong');
}

/* =====================================================
 * 3. Hitung subtotal (safety)
 * ===================================================== */
$subtotal = 0;
foreach ($items as $it) {
    $qty   = max(1, (int)($it['qty'] ?? 0));
    $harga = max(0, (int)($it['harga'] ?? 0));
    $subtotal += $qty * $harga;
}

/* =====================================================
 * 4. Hitung diskon & pajak
 * ===================================================== */
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

$total_no_point = max(0, $subtotal - $disc + $taxAmt);

/* =====================================================
 * 5. Potongan poin & total akhir
 * ===================================================== */
$point_discount = max(0, $point_discount);
if ($point_discount > $total_no_point) {
    $point_discount = $total_no_point;
}

$total = max(0, $total_no_point - $point_discount);

/* =====================================================
 * 6. Validasi pembayaran (INI FIX UTAMA)
 * ===================================================== */
if ($pay_mode === 'cash') {
    if ($tunai < $total) {
        http_response_code(400);
        die('Tunai belum cukup / belum diinput.');
    }
    $kembalian = $tunai - $total;
} elseif ($pay_mode === 'ar') {
    if (!$member_kode) {
        http_response_code(400);
        die('Transaksi piutang wajib memilih member.');
    }
    // piutang: tidak ada tunai & kembalian
    $tunai = 0;
    $kembalian = 0;
} else {
    http_response_code(400);
    die('Metode pembayaran tidak valid.');
}

/* =====================================================
 * 7. Hitung poin earning
 * ===================================================== */
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

$member_jenis = 'umum';
if ($member_kode) {
    $mstmt = $pdo->prepare("SELECT jenis FROM members WHERE kode = ?");
    $mstmt->execute([$member_kode]);
    $mrow = $mstmt->fetch(PDO::FETCH_ASSOC);
    if ($mrow) {
        $mj = strtolower(trim($mrow['jenis']));
        if (in_array($mj, ['umum','grosir'], true)) {
            $member_jenis = $mj;
        }
    }
}

$threshold = ($member_jenis === 'grosir')
    ? ($ppr_grosir ?: $global_ppr)
    : ($ppr_umum   ?: $global_ppr);

$points_award = 0;
if ($member_kode && $threshold > 0) {
    $points_award = (int) floor($total_no_point / $threshold);
}

/* =====================================================
 * 8. Simpan DB (TRANSACTION)
 * ===================================================== */
$invoice_no = 'S'.date('YmdHis');

try {
    $pdo->beginTransaction();

    // SALES
    $stmt = $pdo->prepare("
        INSERT INTO sales
            (invoice_no, member_kode, shift, subtotal, discount, tax,
             total, tunai, kembalian, created_by, created_at, status,
             discount_mode, tax_mode, poin_ditukar, point_discount)
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

    // SALE ITEMS + STOK
    $stmtItem = $pdo->prepare("
        INSERT INTO sale_items (sale_id, item_kode, nama, qty, level, harga, total)
        VALUES (?,?,?,?,?,?,?)
    ");

    foreach ($items as $it) {
        $kode  = (string)($it['kode'] ?? '');
        if ($kode === '') continue;

        $nama  = (string)($it['nama'] ?? '');
        $qty   = max(1, (int)($it['qty'] ?? 0));
        $lvl   = max(1, min(4, (int)($it['level'] ?? 1)));
        $harga = max(0, (int)($it['harga'] ?? 0));
        $line_total = $qty * $harga;

        $stmtItem->execute([$sale_id, $kode, $nama, $qty, $lvl, $harga, $line_total]);
        adjust_stock($pdo, $kode, $location, -$qty);
    }

    // UPDATE POIN MEMBER
    if ($member_kode) {
        $delta = $points_award - $poin_ditukar;
        $up = $pdo->prepare("
            UPDATE members
            SET points = GREATEST(COALESCE(points,0) + ?, 0)
            WHERE kode = ?
        ");
        $up->execute([$delta, $member_kode]);

        // LOG PENUKARAN POIN (Jika ada poin yang ditukar)
        if ($poin_ditukar > 0) {
            $insRedeem = $pdo->prepare("
                INSERT INTO member_point_redemptions (member_kode, qty, description, redeemed_at, created_by)
                VALUES (?, ?, ?, NOW(), ?)
            ");
            $insRedeem->execute([
                $member_kode,
                $poin_ditukar,
                "Penukaran poin POS (Invoice: $invoice_no)",
                $user
            ]);
        }
    }

    // INSERT PIUTANG
    if ($pay_mode === 'ar') {
        $m = $pdo->prepare("SELECT id FROM members WHERE kode = ?");
        $m->execute([$member_kode]);
        $member = $m->fetch(PDO::FETCH_ASSOC);
        if (!$member) {
            throw new Exception('Member tidak ditemukan');
        }

        $ar = $pdo->prepare("
            INSERT INTO member_ar
            (member_id, invoice_no, total, paid, remaining, due_date, status)
            VALUES (?, ?, ?, 0, ?, DATE_ADD(CURDATE(), INTERVAL 30 DAY), 'OPEN')
        ");
        $ar->execute([
            $member['id'],
            $invoice_no,
            $total,
            $total
        ]);
    }

    $pdo->commit();

} catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    die('Gagal simpan penjualan: '.$e->getMessage());
}

/* =====================================================
 * 9. PRINT HELPER
 * ===================================================== */
$printUrl = "sale_print.php?id=".$sale_id;
?>
<!doctype html>
<html lang="id">
<head>
  <meta charset="utf-8">
  <title>Cetak Struk...</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
</head>
<body>
  <div><strong>Menyiapkan struk…</strong></div>
  <script>
    location.href = <?= json_encode($printUrl) ?>;
  </script>
</body>
</html>
