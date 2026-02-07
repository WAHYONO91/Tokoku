<?php
require_once __DIR__.'/config.php';
require_access('REDEEM');
require_once __DIR__.'/includes/header.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to   = $_GET['to']   ?? date('Y-m-d');

// Ambil jenis member (jika kolom tidak ada, nanti kita fallback di PHP)
$stmt = $pdo->prepare("
    SELECT r.id,
           r.member_kode,
           m.nama AS member_nama,
           m.jenis AS member_jenis,
           r.qty,
           r.description,
           r.redeemed_at,
           r.created_by
    FROM member_point_redemptions r
    LEFT JOIN members m ON m.kode = r.member_kode
    WHERE DATE(r.redeemed_at) BETWEEN ? AND ?
    ORDER BY r.redeemed_at DESC, r.id DESC
");
$stmt->execute([$from, $to]);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

// helper ambil nilai fleksibel
function get_val($arr, $keys, $default='') {
  foreach ((array)$keys as $k) {
    if (array_key_exists($k, $arr) && $arr[$k] !== null) return $arr[$k];
  }
  return $default;
}

$total_poin = 0;
foreach ($rows as $row) {
  $total_poin += (int)$row['qty'];
}
?>
<article>
  <h3>Riwayat Tukar Poin</h3>

  <form method="get" class="no-print" style="display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:.8rem;">
    <label>Dari
      <input type="date" name="from" value="<?=htmlspecialchars($from)?>">
    </label>
    <label>Sampai
      <input type="date" name="to" value="<?=htmlspecialchars($to)?>">
    </label>
    <button type="submit">Tampilkan</button>
    <button type="button" onclick="window.print()">Print</button>
  </form>

  <table class="table-small">
    <thead>
      <tr>
        <th>Tanggal</th>
        <th>Member</th>
        <th>Jenis</th>
        <th class="right">Poin Ditukar</th>
        <th>Keterangan Penukaran</th>
        <th>Petugas</th>
      </tr>
    </thead>
    <tbody>
      <?php if(!$rows): ?>
        <tr><td colspan="6">Belum ada penukaran.</td></tr>
      <?php else: ?>
        <?php foreach($rows as $r): ?>
          <?php
            // tentukan jenis dengan fallback 'umum' bila kosong/kolom belum ada
            $jenis = strtolower(trim(get_val($r, ['member_jenis'], 'umum')));
            if (!in_array($jenis, ['umum','grosir'], true)) {
              $jenis = 'umum';
            }
            $badgeBg = ($jenis === 'grosir') ? '#1d4ed8' : '#6b7280';
            $badgeTx = '#fff';
          ?>
          <tr>
            <td><?=date('d-m-Y H:i', strtotime($r['redeemed_at']))?></td>
            <td>
              <?=htmlspecialchars($r['member_kode'] ?? '')?>
              <?php if(!empty($r['member_nama'])): ?>
                - <?=htmlspecialchars($r['member_nama'])?>
              <?php endif; ?>
            </td>
            <td>
              <span class="badge-jenis" style="background:<?= $badgeBg ?>;color:<?= $badgeTx ?>;padding:.2rem .5rem;border-radius:.4rem;font-size:.85rem;">
                <?= htmlspecialchars(ucfirst($jenis)) ?>
              </span>
            </td>
            <td class="right"><?=number_format((int)$r['qty'],0,',','.')?></td>
            <td><?=nl2br(htmlspecialchars($r['description'] ?? ''))?></td>
            <td><?=htmlspecialchars($r['created_by'] ?? '')?></td>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="3" class="right">TOTAL POIN DITUKAR</th>
        <th class="right"><?=number_format($total_poin,0,',','.')?></th>
        <th colspan="2"></th>
      </tr>
    </tfoot>
  </table>
</article>
<?php include __DIR__.'/includes/footer.php'; ?>
