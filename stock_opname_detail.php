<?php
require_once __DIR__.'/config.php';
require_access('STOCK_OPNAME');

require_once __DIR__.'/includes/header.php';
require_once __DIR__.'/functions.php';

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT so.*, u.username
    FROM stock_opnames so
    LEFT JOIN users u ON u.id = so.user_id
    WHERE so.id = ?
");
$stmt->execute([$id]);
$opname = $stmt->fetch();

if (!$opname) {
    echo "<article><h3>Data tidak ditemukan.</h3><a href='stock_opname.php' class='button'>Kembali</a></article>";
    require_once __DIR__.'/includes/footer.php';
    exit;
}

$stmt_items = $pdo->prepare("
    SELECT soi.*, i.nama
    FROM stock_opname_items soi
    LEFT JOIN items i ON i.kode = soi.item_kode
    WHERE soi.opname_id = ?
");
$stmt_items->execute([$id]);
$items = $stmt_items->fetchAll();
?>

<article>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
        <h3>Detail Stock Opname #<?= $id ?></h3>
        <div>
            <a href="stock_opname_print.php?id=<?= $id ?>" target="_blank" class="button">Cetak</a>
            <a href="stock_opname.php" class="button secondary">Kembali</a>
        </div>
    </div>

    <div class="grid" style="margin-bottom:1.5rem; background:var(--card-bg); padding:1rem; border-radius:12px; border:1px solid var(--card-bd);">
        <div>
            <strong>Tanggal:</strong><br>
            <?= date('d/m/Y H:i', strtotime($opname['tanggal'])) ?>
        </div>
        <div>
            <strong>Lokasi:</strong><br>
            <?= ucfirst($opname['location']) ?>
        </div>
        <div>
            <strong>Petugas:</strong><br>
            <?= htmlspecialchars($opname['username']) ?>
        </div>
        <div>
            <strong>Catatan:</strong><br>
            <?= htmlspecialchars($opname['note'] ?: '-') ?>
        </div>
    </div>

    <table class="table-small">
        <thead>
            <tr>
                <th style="width:40px;">No</th>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th class="right">Stok Sistem</th>
                <th class="right">Stok Real</th>
                <th class="right">Selisih</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $it): 
                $diff = $it['qty_real'] - $it['qty_system'];
                $diffClass = $diff > 0 ? 'text-success' : ($diff < 0 ? 'text-danger' : '');
            ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($it['item_kode']) ?></td>
                    <td><?= htmlspecialchars($it['nama']) ?></td>
                    <td class="right"><?= number_format($it['qty_system']) ?></td>
                    <td class="right"><?= number_format($it['qty_real']) ?></td>
                    <td class="right <?= $diffClass ?>" style="font-weight:bold;">
                        <?= ($diff > 0 ? '+' : '') . number_format($diff) ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</article>

<style>
.text-success { color: #22c55e; }
.text-danger { color: #ef4444; }
.grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(150px,1fr)); gap:1rem; }
.table-small{ width:100%; border-collapse:collapse; font-size:.85rem; }
.table-small th,.table-small td{ border:1px solid var(--card-bd); padding:.5rem; }
.right{ text-align:right; }
</style>

<?php require_once __DIR__.'/includes/footer.php'; ?>
