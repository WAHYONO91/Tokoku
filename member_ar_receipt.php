<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_access('PIUTANG');

$id  = (int)($_GET['id'] ?? 0);
$amt = (float)($_GET['amt'] ?? 0);

if ($id <= 0) {
    die('ID tidak valid');
}

$stmt = $pdo->prepare("
  SELECT
    ar.*,
    m.kode AS member_kode,
    m.nama AS member_nama
  FROM member_ar ar
  JOIN members m ON m.id = ar.member_id
  WHERE ar.id = ?
");
$stmt->execute([$id]);
$ar = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ar) {
    die('Data piutang tidak ditemukan');
}

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function money($n){ return number_format((float)$n, 0, ',', '.'); }

$paid_now = $amt > 0 ? $amt : 0;
$paid_total = (float)$ar['paid'];
$remaining = (float)$ar['remaining'];
$status = (string)$ar['status'];

$invoice = $ar['invoice_no'] ?? '-';
?>
<?php require_once __DIR__.'/includes/header.php'; ?>

<article style="max-width:520px;margin:auto;">
  <h3>Bukti Pembayaran Piutang</h3>

  <div class="menu-card" style="cursor:default;margin-bottom:.7rem;">
    <div style="font-weight:700;">Member</div>
    <div><?=h($ar['member_kode'])?> — <?=h($ar['member_nama'])?></div>
    <div style="margin-top:.35rem;font-size:.9rem;color:#94a3b8;">
      Invoice: <b><?=h($invoice)?></b>
    </div>
  </div>

  <div class="card" style="padding:.75rem;">
    <div style="display:flex;justify-content:space-between;gap:.75rem;flex-wrap:wrap;">
      <div>
        <div style="color:#94a3b8;font-size:.8rem;">Bayar Sekarang</div>
        <div style="font-family:ui-monospace,Consolas,Menlo,monospace;font-weight:900;font-size:1.25rem;">
          Rp <?=money($paid_now)?>
        </div>
      </div>

      <div>
        <div style="color:#94a3b8;font-size:.8rem;">Status</div>
        <div style="font-weight:800;">
          <?= $status === 'PAID' ? '✅ LUNAS' : '⏳ OPEN' ?>
        </div>
      </div>
    </div>

    <hr style="border:0;border-top:1px solid #1f2a3a;margin:.75rem 0;">

    <div style="display:grid;grid-template-columns:1fr 1fr;gap:.6rem;">
      <div>
        <div style="color:#94a3b8;font-size:.8rem;">Total Piutang</div>
        <div><b>Rp <?=money($ar['total'])?></b></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.8rem;">Total Terbayar</div>
        <div><b>Rp <?=money($paid_total)?></b></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.8rem;">Sisa</div>
        <div><b>Rp <?=money($remaining)?></b></div>
      </div>
      <div>
        <div style="color:#94a3b8;font-size:.8rem;">Jatuh Tempo</div>
        <div><?=h($ar['due_date'] ?? '-')?></div>
      </div>
    </div>
  </div>

  <div class="no-print" style="display:flex;gap:.5rem;margin-top:.8rem;flex-wrap:wrap;">
    <a class="menu-card" href="member_ar_list.php" style="text-decoration:none;">
      <span class="menu-icon">↩️</span><span>Kembali</span>
    </a>
    <a class="menu-card" href="#" onclick="window.print();return false;" style="text-decoration:none;">
      <span class="menu-icon">🖨️</span><span>Print</span>
    </a>
  </div>
</article>

<?php require_once __DIR__.'/includes/footer.php'; ?>

