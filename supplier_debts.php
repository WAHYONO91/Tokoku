<?php
require_once __DIR__.'/config.php';
require_login();
require_role(['admin']);
require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/functions.php';

$msg = $_GET['msg'] ?? '';
$err = $_GET['err'] ?? '';

// Ambil daftar hutang per supplier (summary)
$debtsSummary = $pdo->query("
    SELECT 
        s.kode, 
        s.nama, 
        SUM(p.sisa) as total_hutang,
        COUNT(p.id) as invoice_count
    FROM suppliers s
    JOIN purchases p ON p.supplier_kode = s.kode
    WHERE p.status_lunas = 0
    GROUP BY s.kode, s.nama
    HAVING total_hutang > 0
    ORDER BY total_hutang DESC
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil detail invoice yang belum lunas (jika supplier dipilih)
$selectedSup = $_GET['sup'] ?? '';
$invoices = [];
if ($selectedSup) {
    $st = $pdo->prepare("
        SELECT * FROM purchases 
        WHERE supplier_kode = ? AND status_lunas = 0 
        ORDER BY purchase_date ASC
    ");
    $st->execute([$selectedSup]);
    $invoices = $st->fetchAll(PDO::FETCH_ASSOC);

    // Ambil riwayat pembayaran
    $stPay = $pdo->prepare("
        SELECT * FROM supplier_payments 
        WHERE supplier_kode = ? 
        ORDER BY tanggal DESC
    ");
    $stPay->execute([$selectedSup]);
    $payments = $stPay->fetchAll(PDO::FETCH_ASSOC);

    // Ambil info supplier
    $stSup = $pdo->prepare("SELECT * FROM suppliers WHERE kode = ?");
    $stSup->execute([$selectedSup]);
    $supplierInfo = $stSup->fetch(PDO::FETCH_ASSOC);
}

$mode = $_GET['mode'] ?? '';
?>

<style>
.table-actions{white-space:nowrap;display:flex;gap:.4rem;align-items:center}
.btn-mini{padding:0.25rem 0.6rem; font-size:0.75rem; margin-bottom:0; line-height:1;}
</style>

<article>
  <header class="no-print">
    <h3>Daftar Hutang Supplier (Tagihan)</h3>
    <p>Pantau dan kelola pembayaran hutang kepada supplier.</p>
  </header>

  <?php if($msg): ?>
    <mark style="background:#16a34a; color:white; display:block; margin-bottom:1rem;"><?=htmlspecialchars($msg)?></mark>
  <?php endif; ?>
  <?php if($err): ?>
    <mark style="background:#ef4444; color:white; display:block; margin-bottom:1rem;"><?=htmlspecialchars($err)?></mark>
  <?php endif; ?>

  <div class="grid no-print">
    <!-- Kolom Kiri: Summary Supplier -->
    <section>
      <h6>Ringkasan Per Supplier</h6>
      <table class="table-small">
        <thead>
          <tr>
            <th>Supplier</th>
            <th class="right">Total Hutang</th>
            <th class="right">Invoice</th>
            <th>Aksi</th>
          </tr>
        </thead>
        <tbody>
          <?php if(!$debtsSummary): ?>
            <tr><td colspan="4">Tidak ada hutang aktif.</td></tr>
          <?php else: foreach($debtsSummary as $ds): ?>
            <tr <?= $selectedSup === $ds['kode'] ? 'style="background:rgba(59,130,246,0.1); border-left:4px solid #3b82f6;"' : '' ?>>
              <td><?=htmlspecialchars($ds['nama'])?></td>
              <td class="right"><?=number_format($ds['total_hutang'],0,',','.')?></td>
              <td class="right"><?=htmlspecialchars($ds['invoice_count'])?></td>
              <td class="table-actions">
                <a href="?sup=<?=urlencode($ds['kode'])?>" role="button" class="btn-mini">💸 Bayar</a>
                <a href="?sup=<?=urlencode($ds['kode'])?>&mode=rekap" role="button" class="secondary btn-mini">📊 Rekap</a>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </section>

    <!-- Kolom Kanan: Detail Invoice atau Form Bayar -->
    <section>
      <?php if(!$selectedSup): ?>
        <div style="text-align:center; padding:2rem; border:1px dashed #374151; border-radius:0.5rem; color:#94a3b8;">
          Pilih supplier di samping untuk melihat rincian tagihan.
        </div>
      <?php else: ?>
        <h6>Detail Tagihan: <?=htmlspecialchars($selectedSup)?></h6>
        <table class="table-small">
          <thead>
            <tr>
              <th>Tgl</th>
              <th>No. Faktur</th>
              <th class="right">Total</th>
              <th class="right">Sisa</th>
              <th>Pilih</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach($invoices as $inv): ?>
              <tr>
                <td><?=date('d/m/y', strtotime($inv['purchase_date']))?></td>
                <td><?=htmlspecialchars($inv['invoice_no'])?></td>
                <td class="right"><?=number_format($inv['total'],0,',','.')?></td>
                <td class="right" style="color:#ef4444;"><?=number_format($inv['sisa'],0,',','.')?></td>
                <td>
                    <button type="button" class="secondary" 
                            onclick="setPayment('<?=$inv['id']?>', '<?=$inv['invoice_no']?>', <?=$inv['sisa']?>)"
                            style="padding:.1rem .4rem; font-size:.7rem;">Pilih</button>
                </td>
              </tr>
            <?php endforeach; ?>
          </tbody>
        </table>

        <!-- Form Bayar -->
        <article id="payArea" style="display:none; margin-top:1rem; border:1px solid #3b82f6;">
            <header style="padding:.5rem; font-size:.85rem;">Bayar Tagihan: <span id="payInvNo"></span></header>
            <form action="supplier_pay_process.php" method="post" style="padding:1rem;">
                <input type="hidden" name="purchase_id" id="payInvId">
                <input type="hidden" name="supplier_kode" value="<?=htmlspecialchars($selectedSup)?>">
                
                <div class="grid">
                    <label>Jumlah Bayar
                        <input type="number" name="jumlah" id="payAmount" required min="1">
                    </label>
                    <label>Metode
                        <select name="metode">
                            <option value="Tunai">Tunai</option>
                            <option value="Transfer">Transfer</option>
                        </select>
                    </label>
                </div>
                <label>Keterangan
                    <input type="text" name="keterangan" placeholder="Catatan pembayaran...">
                </label>
                <div style="display:flex; gap:0.5rem;">
                    <button type="submit" style="flex:1;">Simpan Pembayaran</button>
                    <button type="button" class="secondary" onclick="hidePayment()">Batal</button>
                </div>
            </form>
        </article>
      <?php endif; ?>
    </section>
  </div>

  <!-- VIEW REKAP (KHUSUS CETAK/VIEW RINGKASAN) -->
  <?php if ($selectedSup && $mode === 'rekap'): ?>
    <div id="rekapArea" class="rekap-container">
        <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:1.5rem;">
            <div>
                <h4 style="margin:0;">REKAP HUTANG SUPPLIER</h4>
                <p style="margin:0; font-size:.9rem; color:#94a3b8;"><?= htmlspecialchars($store_name) ?></p>
            </div>
            <div class="no-print" style="display:flex; gap:0.5rem;">
                <button onclick="window.print()" style="background:#059669; border:none; margin-bottom:0;">🖨️ Cetak Rekap</button>
                <a href="?sup=<?=urlencode($selectedSup)?>" role="button" class="secondary" style="margin-bottom:0;">Tutup</a>
            </div>
        </div>

        <div class="grid" style="margin-bottom:1rem;">
            <div>
                <strong>Supplier:</strong><br>
                <?= htmlspecialchars($supplierInfo['nama'] ?? $selectedSup) ?><br>
                <small><?= htmlspecialchars($supplierInfo['alamat'] ?? '') ?></small><br>
                <small><?= htmlspecialchars($supplierInfo['tlp'] ?? '') ?></small>
            </div>
            <div style="text-align:right;">
                <strong>Tanggal Cetak:</strong><br>
                <?= date('d F Y H:i') ?>
            </div>
        </div>

        <h6>1. Daftar Faktur Belum Lunas</h6>
        <table class="table-small">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>No. Faktur</th>
                    <th class="right">Total Faktur</th>
                    <th class="right">Telah Dibayar</th>
                    <th class="right">Sisa Hutang</th>
                </tr>
            </thead>
            <tbody>
                <?php $totalSisa = 0; foreach($invoices as $inv): $totalSisa += $inv['sisa'];?>
                <tr>
                    <td><?= date('d/m/Y', strtotime($inv['purchase_date'])) ?></td>
                    <td><?= htmlspecialchars($inv['invoice_no']) ?></td>
                    <td class="right"><?= number_format($inv['total'], 0, ',', '.') ?></td>
                    <td class="right"><?= number_format($inv['bayar'], 0, ',', '.') ?></td>
                    <td class="right" style="font-weight:bold;"><?= number_format($inv['sisa'], 0, ',', '.') ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr>
                    <th colspan="4" class="right">TOTAL HUTANG TERKINI</th>
                    <th class="right" style="background:rgba(239,68,68,0.1); color:#ef4444; font-size:1.1rem;">
                        Rp <?= number_format($totalSisa, 0, ',', '.') ?>
                    </th>
                </tr>
            </tfoot>
        </table>

        <h6 style="margin-top:1.5rem;">2. Riwayat Pembayaran Terakhir</h6>
        <table class="table-small">
            <thead>
                <tr>
                    <th>Tanggal</th>
                    <th>Jumlah</th>
                    <th>Metode</th>
                    <th>Keterangan</th>
                </tr>
            </thead>
            <tbody>
                <?php if(!$payments): ?>
                    <tr><td colspan="4">Belum ada catatan pembayaran.</td></tr>
                <?php else: foreach($payments as $p): ?>
                <tr>
                    <td><?= date('d/m/Y H:i', strtotime($p['tanggal'])) ?></td>
                    <td><?= number_format($p['jumlah'], 0, ',', '.') ?></td>
                    <td><?= htmlspecialchars($p['metode']) ?></td>
                    <td><?= htmlspecialchars($p['keterangan']) ?></td>
                </tr>
                <?php endforeach; endif; ?>
            </tbody>
        </table>

        <div style="margin-top:2rem; display:flex; justify-content:space-between;">
            <div style="text-align:center; width:200px; border-top:1px solid #4b5563; padding-top:.5rem; font-size:.8rem;">
                Administrasi
            </div>
            <div style="text-align:center; width:200px; border-top:1px solid #4b5563; padding-top:.5rem; font-size:.8rem;">
                Supplier
            </div>
        </div>
    </div>

    <style>
    .rekap-container {
        background:#111827; 
        border:1px solid #1f2937; 
        border-radius:0.75rem; 
        padding:2rem;
        margin-top:1rem;
    }
    @media print {
        body * { visibility: hidden; }
        .rekap-container, .rekap-container * { visibility: visible; }
        .rekap-container { 
            position: absolute; left: 0; top: 0; width: 100%; 
            background: white !important; color: black !important; 
            border: none; padding: 0; margin: 0;
        }
        .no-print { display: none !important; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ccc !important; padding: 8px; color: black !important; }
        .rekap-container h4, .rekap-container h6 { color: black !important; }
        .rekap-container small, .rekap-container p { color: #444 !important; }
    }
    </style>
  <?php endif; ?>
</article>

<script>
function setPayment(id, no, sisa) {
    document.getElementById('payArea').style.display = 'block';
    document.getElementById('payInvNo').textContent = no;
    document.getElementById('payInvId').value = id;
    document.getElementById('payAmount').value = sisa;
    document.getElementById('payAmount').max = sisa;
    document.getElementById('payArea').scrollIntoView({behavior: 'smooth'});
}
function hidePayment() {
    document.getElementById('payArea').style.display = 'none';
}
</script>

<?php require_once __DIR__.'/includes/footer.php'; ?>
