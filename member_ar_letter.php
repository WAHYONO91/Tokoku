<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_access('PIUTANG');

/* ================= HELPER ================= */
function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', '.'); }

$today = date('Y-m-d');

/* ================= MODE DETECTION ================= */
$invoiceId = (int)($_GET['id'] ?? 0);
$memberId  = (int)($_GET['member_id'] ?? 0);

if ($invoiceId <= 0 && $memberId <= 0) {
    die('Parameter tidak valid');
}

/* ================= DATA ================= */
$rows = [];
$totalRemaining = 0;
$memberNama = $memberAlamat = $memberTelp = '';

/* === MODE: PER INVOICE === */
if ($invoiceId > 0) {

    $stmt = $pdo->prepare("
        SELECT
          ar.*,
          m.kode AS member_kode,
          m.nama AS member_nama,
          m.alamat AS member_alamat,
          COALESCE(NULLIF(m.telp,''), m.tlp) AS member_telp
        FROM member_ar ar
        JOIN members m ON m.id = ar.member_id
        WHERE ar.id = ?
    ");
    $stmt->execute([$invoiceId]);
    $ar = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$ar) die('Data piutang tidak ditemukan');

    $rows = [$ar];
    $totalRemaining = $ar['remaining'];
    $memberNama    = $ar['member_nama'];
    $memberAlamat  = $ar['member_alamat'];
    $memberTelp    = $ar['member_telp'];

}
/* === MODE: PER MEMBER (AKUMULASI) === */
else {

    $stmt = $pdo->prepare("
        SELECT
          nama, alamat,
          COALESCE(NULLIF(telp,''), tlp) AS telp
        FROM members
        WHERE id = ?
    ");
    $stmt->execute([$memberId]);
    $m = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$m) die('Member tidak ditemukan');

    $stmt = $pdo->prepare("
        SELECT *
        FROM member_ar
        WHERE member_id = ?
          AND status = 'OPEN'
        ORDER BY due_date ASC
    ");
    $stmt->execute([$memberId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) die('Tidak ada piutang aktif');

    $totalRemaining = array_sum(array_column($rows,'remaining'));
    $memberNama    = $m['nama'];
    $memberAlamat  = $m['alamat'];
    $memberTelp    = $m['telp'];
}

/* ================= OVERDUE CHECK ================= */
$isOverdue = false;
foreach ($rows as $r) {
    if (!empty($r['due_date']) && strtotime($r['due_date']) < strtotime($today)) {
        $isOverdue = true;
        break;
    }
}
?>
<!doctype html>
<html lang="id">
<head>
<meta charset="utf-8">
<title>Surat Penagihan Piutang</title>

<style>
/* ===== RESET TOTAL UNTUK CETAK ===== */
@media print {
  header, nav, footer, .topbar, .menu-wrap, .menu-card,
  .no-print, aside, .container, body > *:not(.print-only-surat) {
    display: none !important;
  }
  body {
    background:#fff !important;
    margin:0 !important;
    padding:0 !important;
  }
}

/* ===== SURAT ===== */
.print-only-surat{
  display:block;
  max-width: 800px;
  margin: 0 auto;
  padding: 20px 26px;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 13px;
  color:#000;
}
.surat-title{
  text-align:center;
  font-weight:700;
  letter-spacing:.04em;
  margin:10px 0 14px;
}
.meta{
  display:flex;
  justify-content:space-between;
  font-size:12px;
  margin-bottom:10px;
}
.table{
  width:100%;
  border-collapse:collapse;
  margin-top:8px;
}
.table th, .table td{
  border:1px solid #000;
  padding:6px 8px;
  font-size:12px;
}
.right{text-align:right}
.sig{
  margin-top:30px;
  display:flex;
  justify-content:space-between;
}
.sig .line{
  margin-top:55px;
  border-top:1px solid #000;
  width:220px;
}
</style>
</head>

<body>

<div class="print-only-surat">

  <div class="meta">
    <div>
      <strong>TokoAPP</strong><br>
      Surat Penagihan Piutang
    </div>
    <div style="text-align:right">
      Tanggal: <?=h($today)?><br>
      Status: <?= $isOverdue ? 'OVERDUE' : 'OPEN' ?>
    </div>
  </div>

  <div class="surat-title">PEMBERITAHUAN PENAGIHAN</div>

  <p>
    Kepada Yth.<br>
    <strong><?=h($memberNama)?></strong><br>
    <?=h($memberAlamat ?: '-')?><br>
    Telp: <?=h($memberTelp ?: '-')?>
  </p>

  <p>
    Dengan hormat,<br>
    Bersama ini kami sampaikan rincian piutang yang masih belum dilunasi
    dengan perincian sebagai berikut:
  </p>

  <table class="table">
    <thead>
      <tr>
        <th>Invoice</th>
        <th>Tanggal</th>
        <th>Jatuh Tempo</th>
        <th class="right">Total</th>
        <th class="right">Terbayar</th>
        <th class="right">Sisa</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($rows as $r): ?>
      <tr>
        <td><?=h($r['invoice_no'])?></td>
        <td><?=h(date('Y-m-d',strtotime($r['created_at'])))?></td>
        <td><?=h($r['due_date'] ?? '-')?></td>
        <td class="right"><?=money($r['total'])?></td>
        <td class="right"><?=money($r['paid'])?></td>
        <td class="right"><strong><?=money($r['remaining'])?></strong></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="5" class="right">TOTAL PIUTANG</th>
        <th class="right"><?=money($totalRemaining)?></th>
      </tr>
    </tfoot>
  </table>

  <p style="margin-top:10px">
    Mohon dilakukan pembayaran atas sisa piutang tersebut.
    Apabila pembayaran telah dilakukan, mohon abaikan surat ini.
  </p>

  <div class="sig">
    <div>
      Hormat kami,<br>
      <strong>TokoAPP</strong>
      <div class="line"></div>
    </div>
    <div>
      Diterima oleh,<br>
      <strong><?=h($memberNama)?></strong>
      <div class="line"></div>
    </div>
  </div>

</div>

<script>
  // Auto print jika perlu
  // window.onload = () => window.print();
</script>

</body>
</html>
