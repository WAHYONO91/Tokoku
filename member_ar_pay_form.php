
<?php
require_once __DIR__.'/config.php';
require_once __DIR__.'/functions.php';

require_login();
require_role(['admin','kasir']);

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    die('ID piutang tidak valid.');
}

$stmt = $pdo->prepare("
    SELECT ar.*, m.kode AS member_kode, m.nama AS member_nama
    FROM member_ar ar
    JOIN members m ON m.id = ar.member_id
    WHERE ar.id = ?
");
$stmt->execute([$id]);
$ar = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$ar) {
    die('Data piutang tidak ditemukan.');
}
?>

<?php require_once __DIR__.'/includes/header.php'; ?>

<article style="max-width:520px;margin:auto;">
  <h3>Bayar Piutang Member</h3>

  <div class="menu-card" style="margin-bottom:.8rem;cursor:default;">
    <div><b>Member</b></div>
    <div><?=htmlspecialchars($ar['member_kode'])?> — <?=htmlspecialchars($ar['member_nama'])?></div>
    <div style="margin-top:.35rem;font-size:.9rem;color:#94a3b8;">
      Invoice: <b><?=htmlspecialchars($ar['invoice_no'])?></b>
    </div>
  </div>

  <form method="post" action="member_ar_pay_process.php" class="card">
    <input type="hidden" name="id" value="<?= (int)$ar['id'] ?>">

    <label>
      Sisa Piutang
      <input type="text" value="<?= number_format($ar['remaining'], 0, ',', '.') ?>" readonly>
    </label>

    <label>
      Nominal Pembayaran
      <input
        type="number"
        name="amount"
        min="1"
        max="<?= (float)$ar['remaining'] ?>"
        step="1"
        required
        placeholder="Masukkan nominal bayar"
      >
    </label>

    <div style="display:flex;gap:.5rem;margin-top:.75rem;">
      <button type="submit" class="primary">💳 Bayar</button>
      <a href="member_ar_list.php" class="secondary" style="text-decoration:none;">Batal</a>
    </div>
  </form>

  <p style="margin-top:.75rem;font-size:.75rem;color:#94a3b8;">
    Catatan: Pembayaran tidak boleh melebihi sisa piutang.
  </p>
</article>

<?php require_once __DIR__.'/includes/footer.php'; ?>

