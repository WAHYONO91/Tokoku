<?php
require_once __DIR__.'/config.php';
require_access('STOCK_OPNAME');

$id = (int)($_GET['id'] ?? 0);

$stmt = $pdo->prepare("
    SELECT so.*, u.username, s.store_name, s.store_address, s.store_phone
    FROM stock_opnames so
    LEFT JOIN users u ON u.id = so.user_id
    CROSS JOIN settings s
    WHERE so.id = ? AND s.id = 1
");
$stmt->execute([$id]);
$opname = $stmt->fetch();

if (!$opname) {
    die("Data tidak ditemukan.");
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
<!DOCTYPE html>
<html>
<head>
    <title>Stock Opname #<?= $id ?></title>
    <style>
        body { font-family: 'Courier New', Courier, monospace; font-size: 12px; margin: 20px; color: #000; }
        .header { text-align: center; margin-bottom: 20px; border-bottom: 1px dashed #000; padding-bottom: 10px; }
        .info { margin-bottom: 15px; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { border: 1px solid #000; padding: 5px; text-align: left; }
        .right { text-align: right; }
        .footer { margin-top: 30px; display: flex; justify-content: space-between; }
        .sig { width: 150px; border-top: 1px solid #000; text-align: center; margin-top: 50px; padding-top: 5px; }
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()">Cetak</button>
        <button onclick="window.close()">Tutup</button>
    </div>

    <div class="header">
        <h2 style="margin:0;"><?= htmlspecialchars($opname['store_name']) ?></h2>
        <p style="margin:2px;"><?= htmlspecialchars($opname['store_address']) ?></p>
        <p style="margin:2px;">Telp: <?= htmlspecialchars($opname['store_phone']) ?></p>
    </div>

    <div class="info">
        <table style="border:none;">
            <tr style="border:none;">
                <td style="border:none; width:100px;">No. Opname</td>
                <td style="border:none;">: #<?= $id ?></td>
                <td style="border:none; width:100px;">Lokasi</td>
                <td style="border:none;">: <?= ucfirst($opname['location']) ?></td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;">Tanggal</td>
                <td style="border:none;">: <?= date('d/m/Y H:i', strtotime($opname['tanggal'])) ?></td>
                <td style="border:none;">Petugas</td>
                <td style="border:none;">: <?= htmlspecialchars($opname['username']) ?></td>
            </tr>
            <tr style="border:none;">
                <td style="border:none;">Catatan</td>
                <td style="border:none;" colspan="3">: <?= htmlspecialchars($opname['note'] ?: '-') ?></td>
            </tr>
        </table>
    </div>

    <table>
        <thead>
            <tr>
                <th style="width:30px;">No</th>
                <th>Kode</th>
                <th>Nama Barang</th>
                <th class="right">Sistem</th>
                <th class="right">Real</th>
                <th class="right">Selisih</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($items as $idx => $it): 
                $diff = $it['qty_real'] - $it['qty_system'];
            ?>
                <tr>
                    <td><?= $idx + 1 ?></td>
                    <td><?= htmlspecialchars($it['item_kode']) ?></td>
                    <td><?= htmlspecialchars($it['nama']) ?></td>
                    <td class="right"><?= number_format($it['qty_system']) ?></td>
                    <td class="right"><?= number_format($it['qty_real']) ?></td>
                    <td class="right"><?= ($diff > 0 ? '+' : '') . number_format($diff) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <div class="sig">Petugas</div>
        <div class="sig">Penanggung Jawab</div>
    </div>
</body>
</html>
