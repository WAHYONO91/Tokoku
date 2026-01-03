<?php
// members.php
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/header.php';

/**
 * MODE POPUP:
 * buka dari pos_display.php pakai:
 *   members.php?popup=1
 */
$isPopup = (isset($_GET['popup']) && $_GET['popup'] == '1');

/**
 * PENCARIAN: berdasarkan kode / nama / alamat
 * GET: ?q=...
 */
$q = trim($_GET['q'] ?? '');

// TOTAL seluruh member (tanpa filter)
$totalMembers = (int)$pdo->query("SELECT COUNT(*) FROM members")->fetchColumn();

if ($q !== '') {
  $like = "%{$q}%";

  $stmt = $pdo->prepare("
    SELECT *
    FROM members
    WHERE (kode LIKE :q1 OR nama LIKE :q2 OR alamat LIKE :q3)
    ORDER BY created_at DESC
  ");

  $stmt->execute([
    ':q1' => $like,
    ':q2' => $like,
    ':q3' => $like,
  ]);

  $members = $stmt->fetchAll();
  $shownCount = is_array($members) ? count($members) : 0;
} else {
  $members = $pdo->query("SELECT * FROM members ORDER BY created_at DESC")->fetchAll();
  $shownCount = is_array($members) ? count($members) : 0;
}
?>

<style>
  .member-toolbar{
    display:flex;
    align-items:center;
    gap:.6rem;
    flex-wrap:wrap;
    margin:.5rem 0 .5rem;
  }

  .member-box{
    border:1px solid #d1d5db;
    border-radius:10px;
    background: transparent;
    padding:.55rem .7rem;
  }

  .member-add{
    text-decoration:none;
    display:inline-flex;
    align-items:center;
    white-space:nowrap;
  }

  .member-search{
    display:flex;
    align-items:center;
    gap:.45rem;
  }
  .member-search input{
    min-width:280px;
    margin:0;
  }
  .member-search button{ margin:0; }

  .member-search .btn-reset{
    text-decoration:none;
    border:1px solid #d1d5db;
    border-radius:8px;
    padding:.35rem .55rem;
    display:inline-flex;
    align-items:center;
    white-space:nowrap;
    background: transparent;
  }

  .member-hint{
    opacity:.85;
    padding:.35rem .2rem;
    white-space:nowrap;
  }

  /* Keterangan di atas tabel - kecil */
  .table-meta{
    font-size: .85rem;
    opacity: .85;
    margin: 0 0 .45rem 0;
    display:flex;
    gap:.6rem;
    flex-wrap:wrap;
    align-items:center;
  }

  /* MODE POPUP: bikin baris table keliatan clickable */
  .popup-note{
    margin:.45rem 0 .65rem;
    padding:.55rem .75rem;
    border:1px dashed #94a3b8;
    border-radius:10px;
    opacity:.9;
    font-size:.9rem;
  }
  .popup-mode table tbody tr{ cursor:pointer; }
  .popup-mode table tbody tr:hover{ background: rgba(2, 132, 199, .10); }
</style>

<article class="<?= $isPopup ? 'popup-mode' : '' ?>">
  <h3>Master Member</h3>

  <?php if ($isPopup): ?>
    <div class="popup-note">
      Mode pilih member untuk POS: <b>double-click</b> pada baris member untuk mengirim <b>kode</b> ke POS dan menutup popup.
    </div>
  <?php endif; ?>

  <!-- TOOLBAR -->
  <div class="no-print member-toolbar">
    <?php if (!$isPopup): ?>
      <a href="member_add.php" class="contrast member-box member-add">+ Tambah Member</a>
    <?php endif; ?>

    <form method="get" class="member-box member-search">
      <?php if ($isPopup): ?>
        <input type="hidden" name="popup" value="1">
      <?php endif; ?>

      <input
        type="text"
        name="q"
        value="<?= htmlspecialchars($q) ?>"
        placeholder="Cari: kode / nama / alamat"
        autocomplete="off"
      />
      <button type="submit">Cari</button>

      <?php if ($q !== ''): ?>
        <a href="members.php<?= $isPopup ? '?popup=1' : '' ?>" class="btn-reset">Reset</a>
      <?php endif; ?>
    </form>

    <?php if ($q !== ''): ?>
      <div class="member-hint">
        Hasil pencarian: <b><?= htmlspecialchars($q) ?></b>
      </div>
    <?php endif; ?>
  </div>
  <!-- /TOOLBAR -->

  <!-- KETERANGAN DI ATAS TABEL -->
  <div class="table-meta">
    <span>Ditampilkan: <b><?= number_format((int)$shownCount, 0, ',', '.') ?></b></span>
    <span>Total Member: <b><?= number_format((int)$totalMembers, 0, ',', '.') ?></b></span>
  </div>

  <table class="table-small">
    <thead>
      <tr>
        <th>Kode</th>
        <th>Nama</th>
        <th>Jenis</th>
        <th>Alamat</th>
        <th>Telp</th>
        <th class="right">Poin</th>
        <?php if (!$isPopup): ?>
          <th class="no-print">Aksi</th>
        <?php endif; ?>
      </tr>
    </thead>
    <tbody>
      <?php if(!$members): ?>
        <tr>
          <td colspan="<?= $isPopup ? 6 : 7 ?>">
            <?= ($q !== '') ? 'Data tidak ditemukan.' : 'Belum ada data member.' ?>
          </td>
        </tr>
      <?php else: ?>
        <?php foreach($members as $m): ?>
          <?php
            $kode   = $m['kode']   ?? '';
            $nama   = $m['nama']   ?? '';
            $alamat = $m['alamat'] ?? '';

            $telp   = $m['telp'] ?? ($m['tlp'] ?? ($m['phone'] ?? ''));
            $points = $m['points'] ?? ($m['poin'] ?? ($m['point'] ?? 0));

            $jenisRaw = $m['jenis'] ?? 'umum';
            $jenis    = strtolower(trim($jenisRaw));
            if(!in_array($jenis, ['umum','grosir'], true)) $jenis = 'umum';

            $badgeBg = ($jenis === 'grosir') ? '#1d4ed8' : '#6b7280';
            $badgeTx = '#fff';
          ?>
          <tr data-kode="<?= htmlspecialchars($kode, ENT_QUOTES) ?>">
            <td><?= htmlspecialchars($kode) ?></td>
            <td><?= htmlspecialchars($nama) ?></td>
            <td>
              <span style="background:<?= $badgeBg ?>;color:<?= $badgeTx ?>;padding:.2rem .5rem;border-radius:.4rem;font-size:.85rem;">
                <?= htmlspecialchars(ucfirst($jenis)) ?>
              </span>
            </td>
            <td><?= htmlspecialchars($alamat) ?></td>
            <td><?= htmlspecialchars($telp) ?></td>
            <td class="right"><?= number_format((int)$points, 0, ',', '.') ?></td>

            <?php if (!$isPopup): ?>
              <td class="no-print" style="white-space:nowrap; display:flex; gap:.35rem;">
                <a href="member_edit.php?kode=<?= urlencode($kode) ?>">Edit</a>
                <a href="member_redeem.php?kode=<?= urlencode($kode) ?>" class="contrast">Tukar Poin</a>
                <a href="member_delete.php?kode=<?= urlencode($kode) ?>" onclick="return confirm('Hapus member ini?')">Hapus</a>
              </td>
            <?php endif; ?>
          </tr>
        <?php endforeach; ?>
      <?php endif; ?>
    </tbody>
  </table>
</article>

<script>
(function () {
  const params = new URLSearchParams(window.location.search);
  const isPopup = (params.get('popup') === '1');
  if (!isPopup) return;

  // dblclick pilih member -> kirim ke opener (pos_display.php)
  const rows = document.querySelectorAll('table tbody tr[data-kode]');
  rows.forEach((tr) => {
    tr.title = 'Double-click untuk pilih member';
    tr.addEventListener('dblclick', () => {
      const kode = tr.dataset.kode || '';
      if (!kode) return;

      if (!window.opener || window.opener.closed) {
        alert('Jendela POS tidak ditemukan / sudah tertutup.');
        return;
      }

      try {
        // isi input member_kode di POS
        const input = window.opener.document.getElementById('member_kode');
        if (input) input.value = kode;

        // trigger load member di POS (paling aman)
        if (typeof window.opener.loadMemberByKode === 'function') {
          window.opener.loadMemberByKode(kode);
        } else if (typeof window.opener.triggerMemberSearch === 'function') {
          window.opener.triggerMemberSearch();
        }

        window.close();
      } catch (err) {
        alert('Gagal mengirim member ke POS: ' + (err && err.message ? err.message : err));
      }
    });
  });
})();
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>
