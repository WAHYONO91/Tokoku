<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_login();
require_role(['admin','kasir']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) die('ID tidak valid');

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
$stmt->execute([$id]);
$ar = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$ar) die('Data piutang tidak ditemukan');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', '.'); }

$today = date('Y-m-d');
$isOverdue = (($ar['status'] ?? '') === 'OPEN' && !empty($ar['due_date']) && strtotime($ar['due_date']) < strtotime(date('Y-m-d')));
?>

<?php require_once __DIR__.'/includes/header.php'; ?>

<style>
  /* Surat: gaya print-friendly */
  .letter-wrap{
    max-width: 820px;
    margin: 0 auto;
    background: #fff;
    color: #111;
    padding: 22px 26px;
    border-radius: 10px;
  }
  .letter-head{
    display:flex;
    justify-content:space-between;
    gap:16px;
    align-items:flex-start;
    margin-bottom:14px;
  }
  .letter-head .brand{
    font-weight:900;
    font-size:18px;
    color:#111;
  }
  .letter-head .meta{
    text-align:right;
    font-size:13px;
    color:#333;
  }
  .letter-title{
    text-align:center;
    font-weight:900;
    font-size:16px;
    letter-spacing:.04em;
    margin: 10px 0 14px;
    text-transform: uppercase;
  }
  .letter-body{
    font-size:14px;
    line-height:1.55;
  }
  .box{
    border:1px solid #e5e7eb;
    padding:12px 14px;
    border-radius:10px;
    margin: 12px 0;
    background:#fafafa;
  }
  .grid{
    display:grid;
    grid-template-columns: 1fr 1fr;
    gap:10px 14px;
    font-size:14px;
  }
  .lbl{ color:#555; font-size:12px; text-transform:uppercase; letter-spacing:.04em; }
  .val{ font-weight:700; }
  .sig{
    margin-top: 28px;
    display:flex;
    justify-content:space-between;
    gap:18px;
  }
  .sig .col{
    width: 48%;
    text-align:center;
  }
  .sig .line{
    margin-top: 60px;
    border-top:1px solid #111;
  }

  @media print{
    .topbar, .no-print, footer, header { display:none !important; }
    body{ background:#fff !important; }
    .letter-wrap{ box-shadow:none !important; border-radius:0 !important; padding:0 !important; }
  }
</style>

<article class="no-print" style="max-width:820px;margin:0 auto 10px;">
  <div style="display:flex;gap:.5rem;flex-wrap:wrap;">
    <a class="menu-card" href="member_ar_list.php" style="text-decoration:none;">
      <span class="menu-icon">↩️</span><span>Kembali</span>
    </a>
    <a class="menu-card" href="#" onclick="window.print();return false;" style="text-decoration:none;">
      <span class="menu-icon">🖨️</span><span>Cetak Surat</span>
    </a>
  </div>
</article>

<div class="letter-wrap">
  <div class="letter-head">
    <div>
      <div class="brand">SURAT PENAGIHAN PIUTANG</div>
      <div style="font-size:13px;color:#333;margin-top:4px;">
        Nomor Dokumen: <b><?=h($ar['invoice_no'] ?? '-')?></b>
      </div>
    </div>
    <div class="meta">
      Tanggal: <b><?=h($today)?></b><br>
      Status: <b><?= ($ar['status']==='PAID' ? 'LUNAS' : ($isOverdue ? 'OVERDUE' : 'OPEN')) ?></b><br>
      Jatuh tempo: <b><?=h($ar['due_date'] ?? '-')?></b>
    </div>
  </div>

  <div class="letter-title">Pemberitahuan Penagihan Hutang / Piutang</div>

  <div class="letter-body">
    Kepada Yth.<br>
    <b><?=h($ar['member_nama'])?></b> (<?=h($ar['member_kode'])?>)<br>
    <?=h($ar['member_alamat'] ?? '-')?><br>
    Telp: <?=h($ar['member_telp'] ?? '-')?><br>

    <div class="box">
      <div class="grid">
        <div>
          <div class="lbl">No. Invoice</div>
          <div class="val"><?=h($ar['invoice_no'] ?? '-')?></div>
        </div>
        <div>
          <div class="lbl">Tanggal Transaksi</div>
          <div class="val"><?=h(isset($ar['created_at']) ? date('Y-m-d', strtotime($ar['created_at'])) : '-')?></div>
        </div>
        <div>
          <div class="lbl">Total Piutang</div>
          <div class="val">Rp <?=money($ar['total'])?></div>
        </div>
        <div>
          <div class="lbl">Terbayar</div>
          <div class="val">Rp <?=money($ar['paid'])?></div>
        </div>
        <div>
          <div class="lbl">Sisa Tagihan</div>
          <div class="val">Rp <?=money($ar['remaining'])?></div>
        </div>
        <div>
          <div class="lbl">Keterangan</div>
          <div class="val">
            <?= $ar['status']==='PAID'
              ? 'Piutang telah lunas.'
              : ($isOverdue ? 'Piutang telah melewati jatuh tempo.' : 'Piutang masih berjalan.')
            ?>
          </div>
        </div>
      </div>
    </div>

    <p>
      Dengan ini kami sampaikan pemberitahuan bahwa masih terdapat sisa piutang sebesar
      <b>Rp <?=money($ar['remaining'])?></b>.
      Mohon dilakukan pembayaran paling lambat pada tanggal <b><?=h($ar['due_date'] ?? '-')?></b>.
    </p>

    <p>
      Apabila pembayaran telah dilakukan, mohon abaikan surat ini. Terima kasih atas kerja samanya.
    </p>

    <div class="sig">
      <div class="col">
        Hormat kami,<br>
        <b>Kasir / Admin</b>
        <div class="line"></div>
      </div>
      <div class="col">
        Diterima oleh,<br>
        <b><?=h($ar['member_nama'])?></b>
        <div class="line"></div>
      </div>
    </div>
  </div>
</div>

<script class="no-print">
  // Auto print kalau mau:
  // setTimeout(()=>window.print(), 250);
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>
