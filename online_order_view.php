<?php
require_once __DIR__ . '/config.php';
require_login();
require_once __DIR__ . '/includes/header.php';

require_access('ONLINE_ORDERS');

$id = (int)($_GET['id'] ?? ($_POST['id'] ?? 0));
if ($id <= 0) {
    header('Location: online_orders.php');
    exit;
}

$msg = '';
$err = '';

// Handle Updates
function recalcOnlineOrder($pdo, $order_id) {
    $st = $pdo->prepare("SELECT COALESCE(SUM(total),0) FROM online_order_items WHERE order_id = ?");
    $st->execute([$order_id]);
    $sum = (int)$st->fetchColumn();
    $pdo->prepare("UPDATE online_orders SET subtotal=?, total=? WHERE id=?")->execute([$sum, $sum, $order_id]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_status';

    if ($action === 'update_status') {
        $new_status = $_POST['status'] ?? '';
        $new_payment = $_POST['payment_status'] ?? '';

    if (!empty($new_status) && !empty($new_payment)) {
        try {
            $pdo->beginTransaction();

            $stmt = $pdo->prepare("UPDATE online_orders SET status = ?, payment_status = ? WHERE id = ?");
            $stmt->execute([$new_status, $new_payment, $id]);

            // POS Integration logic
            // Only sync if payment is PAID
            if ($new_payment === 'PAID') {
                // Fetch the order data required for syncing
                $syncOrderQuery = $pdo->prepare("SELECT * FROM online_orders WHERE id = ?");
                $syncOrderQuery->execute([$id]);
                $syncOrder = $syncOrderQuery->fetch(PDO::FETCH_ASSOC);

                // Check if this order is already synced in sales table
                $invoice_no = "WEB-" . str_pad($id, 5, '0', STR_PAD_LEFT);
                $checkSync = $pdo->prepare("SELECT id FROM sales WHERE invoice_no = ? LIMIT 1");
                $checkSync->execute([$invoice_no]);

                if (!$checkSync->fetch()) {
                    // Not synced yet. Proceed to sync.
                    $syncItemsQuery = $pdo->prepare("SELECT * FROM online_order_items WHERE order_id = ?");
                    $syncItemsQuery->execute([$id]);
                    $syncItems = $syncItemsQuery->fetchAll(PDO::FETCH_ASSOC);

                    require_once __DIR__ . '/functions.php';

                    $shift = '1'; 
                    $subtotal = (int)$syncOrder['subtotal'];
                    $total = (int)$syncOrder['total'];
                    $discount = 0;
                    $tax = 0;
                    $tunai = $total; // Online order is assumed to be fully paid via the method chosen
                    $kembalian = 0;
                    $created_by = "Online: " . ($syncOrder['guest_name'] ?: 'Guest');
                    $member_kode = $syncOrder['member_kode'];

                    // 1. Insert into Sales
                    $insSale = $pdo->prepare("
                        INSERT INTO sales
                            (invoice_no, member_kode, shift, subtotal, discount, tax,
                             total, tunai, kembalian, created_by, created_at, status)
                        VALUES
                            (?,?,?,?,?,?,?,?,?,?, NOW(),'OK')
                    ");
                    $insSale->execute([
                        $invoice_no, $member_kode, $shift, $subtotal, $discount, $tax,
                        $total, $tunai, $kembalian, $created_by
                    ]);
                    $new_sale_id = $pdo->lastInsertId();

                    // 2. Insert into Sale items & adjust stock
                    $insItem = $pdo->prepare("
                        INSERT INTO sale_items (sale_id, item_kode, nama, qty, level, harga, total)
                        VALUES (?,?,?,?,?,?,?)
                    ");
                    foreach ($syncItems as $it) {
                        $qty = (int)$it['qty'];
                        // Calculate per unit price
                        $harga = $qty > 0 ? (int)($it['total'] / $qty) : 0;
                        $insItem->execute([
                            $new_sale_id,
                            $it['item_kode'],
                            $it['nama_item'],
                            $qty,
                            1, // Base price level
                            $harga,
                            (int)$it['total']
                        ]);

                        if (!empty($it['item_kode'])) {
                            adjust_stock($pdo, $it['item_kode'], 'toko', -$qty);
                        }
                    }

                    // 3. Update Poin Member
                    if (!empty($member_kode)) {
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
                        $mstmt = $pdo->prepare("SELECT jenis FROM members WHERE kode = ?");
                        $mstmt->execute([$member_kode]);
                        $mrow = $mstmt->fetch(PDO::FETCH_ASSOC);
                        if ($mrow) {
                            $mj = strtolower(trim($mrow['jenis']));
                            if (in_array($mj, ['umum','grosir'], true)) {
                                $member_jenis = $mj;
                            }
                        }

                        $threshold = ($member_jenis === 'grosir')
                            ? ($ppr_grosir ?: $global_ppr)
                            : ($ppr_umum   ?: $global_ppr);

                        $points_award = 0;
                        if ($threshold > 0) {
                            $points_award = (int) floor($total / $threshold);
                        }

                        if ($points_award > 0) {
                            // Hitung ke member
                            $up = $pdo->prepare("
                                UPDATE members
                                SET points = GREATEST(COALESCE(points,0) + ?, 0)
                                WHERE kode = ?
                            ");
                            $up->execute([$points_award, $member_kode]);

                            // Catat history poin (Opsional, tapi jika ingin lebih clean dicatat juga tidak masalah)
                            // Jika ada table point_history, bisa insert kesitu. Saat ini TokoApp belum punya point history kecuali redemptions.
                        }
                    }

                    $msg = 'Status pesanan berhasil diperbarui dan disinkronisasi ke daftar Penjualan (POS)! Poin member ditambahkan.';
                } else {
                    $msg = 'Status pesanan berhasil diperbarui!';
                }
            } else {
                $msg = 'Status pesanan berhasil diperbarui!';
            }

            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $err = 'Error: ' . $e->getMessage();
        }
    } } elseif ($action === 'edit_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $qty = (int)($_POST['qty'] ?? 0);
        
        $st = $pdo->prepare("SELECT harga_satuan FROM online_order_items WHERE id = ? AND order_id = ?");
        $st->execute([$item_id, $id]);
        $harga = (int)$st->fetchColumn();
        
        if ($qty <= 0) {
            $pdo->prepare("DELETE FROM online_order_items WHERE id = ? AND order_id = ?")->execute([$item_id, $id]);
            $msg = "Item berhasil dihapus dari pesanan.";
        } else {
            $tot = $qty * $harga;
            $pdo->prepare("UPDATE online_order_items SET qty = ?, total = ? WHERE id = ? AND order_id = ?")->execute([$qty, $tot, $item_id, $id]);
            $msg = "Quantity item berhasil diupdate.";
        }
        recalcOnlineOrder($pdo, $id);

    } elseif ($action === 'add_item') {
        $kode = trim($_POST['item_kode'] ?? '');
        $qty = (int)($_POST['qty'] ?? 1);
        if ($qty > 0 && !empty($kode)) {
            $check = $pdo->prepare("SELECT nama, harga_jual1 FROM items WHERE kode = ?");
            $check->execute([$kode]);
            $it = $check->fetch(PDO::FETCH_ASSOC);
            if ($it) {
                $chkCart = $pdo->prepare("SELECT id, qty FROM online_order_items WHERE order_id = ? AND item_kode = ?");
                $chkCart->execute([$id, $kode]);
                $exist = $chkCart->fetch(PDO::FETCH_ASSOC);
                
                if ($exist) {
                    $new_qty = $exist['qty'] + $qty;
                    $tot = $new_qty * $it['harga_jual1'];
                    $pdo->prepare("UPDATE online_order_items SET qty=?, total=? WHERE id=?")->execute([$new_qty, $tot, $exist['id']]);
                } else {
                    $tot = $qty * $it['harga_jual1'];
                    $pdo->prepare("INSERT INTO online_order_items (order_id, item_kode, nama_item, qty, harga_satuan, total) VALUES (?,?,?,?,?,?)")
                        ->execute([$id, $kode, $it['nama'], $qty, $it['harga_jual1'], $tot]);
                }
                $msg = "Item berhasil ditambahkan ke pesanan.";
                recalcOnlineOrder($pdo, $id);
            } else {
                $err = "Barang tidak ditemukan. Pastikan kode barang benar.";
            }
        }
    }
}

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM online_orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    echo "<article><mark>Pesanan tidak ditemukan.</mark></article>";
    require_once __DIR__ . '/includes/footer.php';
    exit;
}

// Fetch Items with Images
$stmtItems = $pdo->prepare("
    SELECT oi.*, i.gambar 
    FROM online_order_items oi 
    LEFT JOIN items i ON oi.item_kode = i.kode 
    WHERE oi.order_id = ?
");
$stmtItems->execute([$id]);
$items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

?>

<article>
    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1.5rem;">
        <h3>Detail Pesanan #<?= str_pad($order['id'], 5, '0', STR_PAD_LEFT) ?></h3>
        <a href="online_orders.php" class="secondary">🔙 Kembali</a>
    </div>

    <?php if ($msg): ?>
        <mark style="display:block;margin-bottom:1rem;background:#10b981;color:#fff;">✔️ <?= htmlspecialchars($msg) ?></mark>
    <?php endif; ?>
    <?php if ($err): ?>
        <mark style="display:block;margin-bottom:1rem;background:#dc2626;color:#fff;">⚠️ <?= htmlspecialchars($err) ?></mark>
    <?php endif; ?>

    <div class="grid">
        <!-- DETAIL PELANGGAN -->
        <div>
            <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1.2rem; margin-bottom:1rem;">
                <h5 style="margin-top:0;">Informasi Pengiriman</h5>
                <hr style="border-color:var(--card-bd); margin-bottom:0.75rem;">
                <table style="width:100%; border:none;">
                    <tr style="background:transparent;"><td style="width:30%; padding:0.3rem 0; border:none;" class="muted">Nama</td><td style="padding:0.3rem 0; border:none;"><strong><?= htmlspecialchars($order['guest_name']) ?></strong></td></tr>
                    <tr style="background:transparent;"><td style="padding:0.3rem 0; border:none;" class="muted">Telepon</td><td style="padding:0.3rem 0; border:none;"><?= htmlspecialchars($order['guest_phone']) ?></td></tr>
                    <tr style="background:transparent;"><td style="padding:0.3rem 0; border:none;" class="muted">Alamat</td><td style="padding:0.3rem 0; border:none;"><?= nl2br(htmlspecialchars($order['guest_address'])) ?></td></tr>
                    <tr style="background:transparent;"><td style="padding:0.3rem 0; border:none;" class="muted">Catatan</td><td style="padding:0.3rem 0; border:none;"><?= htmlspecialchars($order['note'] ?: '-') ?></td></tr>
                    <tr style="background:transparent;"><td style="padding:0.3rem 0; border:none;" class="muted">Via</td><td style="padding:0.3rem 0; border:none;"><strong><?= htmlspecialchars($order['payment_method']) ?></strong></td></tr>
                    
                    <?php if (!empty($order['lat_lng'])): ?>
                    <tr style="background:transparent;">
                        <td style="padding:0.5rem 0; border:none;" class="muted">Lokasi Map</td>
                        <td style="padding:0.5rem 0; border:none;">
                            <link rel="stylesheet" href="/tokoapp/assets/vendor/leaflet/leaflet.css" />
                            <script src="/tokoapp/assets/vendor/leaflet/leaflet.js"></script>
                            <div id="view_map" style="height: 180px; border-radius: 6px; border: 1px solid var(--card-bd); margin-bottom: 0.5rem;"></div>
                            <a href="https://www.google.com/maps/search/?api=1&query=<?= urlencode($order['lat_lng']) ?>" target="_blank" role="button" class="secondary outline" style="font-size: 0.75rem; padding: 0.25rem 0.75rem; width: auto;">
                                📍 Buka di Google Maps
                            </a>
                            <script>
                                document.addEventListener('DOMContentLoaded', function() {
                                    var coords = "<?= $order['lat_lng'] ?>".split(',');
                                    var lat = parseFloat(coords[0]);
                                    var lng = parseFloat(coords[1]);
                                    var map = L.map('view_map', {
                                        dragging: false,
                                        scrollWheelZoom: false,
                                        doubleClickZoom: false,
                                        zoomControl: false
                                    }).setView([lat, lng], 15);
                                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                        attribution: '&copy; OSM'
                                    }).addTo(map);
                                    L.marker([lat, lng]).addTo(map);
                                });
                            </script>
                        </td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            
            <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1.2rem; margin-bottom:1rem;">
                <h5 style="margin-top:0;">Item Pesanan</h5>
                <table class="table-small" style="margin-top:0.75rem; margin-bottom:0;">
                    <thead>
                        <tr>
                            <th style="width: 60px;">Gambar</th>
                            <th>Barang</th>
                            <th class="center">Qty / Aksi</th>
                            <th class="right">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $can_edit = ($order['payment_status'] === 'UNPAID' && !in_array($order['status'], ['SENT', 'CANCELLED'])); ?>
                        <?php foreach($items as $i): ?>
                            <tr>
                                <td>
                                    <?php if (!empty($i['gambar']) && file_exists(__DIR__ . '/uploads/items/' . $i['gambar'])): ?>
                                        <img src="uploads/items/<?= htmlspecialchars($i['gambar']) ?>" alt="img" style="max-width: 50px; border-radius: 4px;">
                                    <?php else: ?>
                                        <div style="width:50px; height:40px; background:var(--input-bg, #0f172a); color:var(--text-muted, #94a3b8); display:flex; align-items:center; justify-content:center; font-size:10px; border-radius:4px;">No IMG</div>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($i['nama_item']) ?> <br><small class="muted"><?= $i['item_kode'] ?></small></td>
                                <td class="center">
                                    <?php if($can_edit): ?>
                                    <form method="post" class="inline-edit-form">
                                        <input type="hidden" name="action" value="edit_item">
                                        <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                        <input type="hidden" name="item_id" value="<?= $i['id'] ?>">
                                        
                                        <div class="qty-control group-inputs">
                                            <input type="number" name="qty" value="<?= $i['qty'] ?>" min="0">
                                            <button type="submit" class="secondary outline" title="Update Qty">🔄</button>
                                            <button type="submit" name="qty" value="0" class="danger outline" title="Hapus">🗑️</button>
                                        </div>
                                    </form>
                                    <?php else: ?>
                                        <?= $i['qty'] ?>
                                    <?php endif; ?>
                                </td>
                                <td class="right"><?= rupiah($i['total']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <?php if($can_edit): ?>
                    <tfoot style="border-top: 2px solid var(--card-bd);">
                        <tr>
                            <td colspan="4" style="padding-top: 1rem;">
                                <form method="post" class="inline-add-form">
                                    <input type="hidden" name="action" value="add_item">
                                    <input type="hidden" name="id" value="<?= $order['id'] ?>">
                                    
                                    <div class="group-inputs w-100">
                                        <input type="text" name="item_kode" id="item_kode_input" placeholder="Scan Barcode / F2 Cari Barang..." list="dl_items" required autocomplete="off" style="flex:1;">
                                        <button type="button" class="secondary" onclick="openItemPicker()" title="Cari Barang (F2)">🔍 Cari (F2)</button>
                                    </div>
                                    
                                    <div class="group-inputs" style="margin-left:auto;">
                                        <input type="number" name="qty" value="1" min="1" id="qty_input" style="width:70px; text-align:center;">
                                        <button type="submit">➕ Tambah</button>
                                    </div>
                                </form>
                                <datalist id="dl_items">
                                    <?php
                                    $allItems = $pdo->query("SELECT kode, nama FROM items ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);
                                    foreach($allItems as $itm):
                                    ?>
                                    <option value="<?= htmlspecialchars($itm['kode']) ?>"><?= htmlspecialchars($itm['nama']) ?></option>
                                    <?php endforeach; ?>
                                </datalist>
                            </td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                    <tfoot>
                        <tr>
                            <td colspan="3" class="right"><strong>Grand Total</strong></td>
                            <td class="right"><strong style="color:#10b981;"><?= rupiah($order['total']) ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- UPDATE STATUS FORM -->
        <div>
            <form method="post" style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1.2rem;">
                <h5 style="margin-top:0;">Update Pesanan</h5>
                <hr style="border-color:var(--card-bd); margin-bottom:0.75rem;">
                <input type="hidden" name="id" value="<?= $order['id'] ?>">

                <label>Status Pembayaran
                    <select name="payment_status">
                        <option value="UNPAID" <?= $order['payment_status']==='UNPAID'?'selected':'' ?>>BELUM LUNAS (UNPAID)</option>
                        <option value="PAID" <?= $order['payment_status']==='PAID'?'selected':'' ?>>LUNAS (PAID)</option>
                    </select>
                </label>

                <label>Status Pesanan
                    <select name="status">
                        <option value="PENDING" <?= $order['status']==='PENDING'?'selected':'' ?>>PENDING (Menunggu)</option>
                        <option value="PROCESSED" <?= $order['status']==='PROCESSED'?'selected':'' ?>>PROCESSED (Diproses)</option>
                        <option value="SENT" <?= $order['status']==='SENT'?'selected':'' ?>>SENT (Dikirim/Selesai)</option>
                        <option value="CANCELLED" <?= $order['status']==='CANCELLED'?'selected':'' ?>>CANCELLED (Batal)</option>
                    </select>
                </label>

                <button type="submit" style="width:100%; margin-top:1rem;">💾 Simpan Perubahan</button>
            </form>

            <div style="margin-top:1.5rem; text-align:center;">
                <!-- Shortcut untuk cetak faktur pengiriman menggunakan layout printer struk/faktur -->
                <button type="button" class="secondary" onclick="window.open('online_order_print.php?id=<?= $order['id'] ?>', 'printWindow', 'width=600,height=800')">🖨️ Cetak Faktur Pengiriman</button>
            </div>
        </div>
    </div>
</article>

<script>
function openItemPicker() {
    const w = 1100, h = 720;
    const left = Math.max(0, (screen.width  - w) / 2);
    const top  = Math.max(0, (screen.height - h) / 2);
    const popup = window.open(
        'items.php?pick=1',
        'itemPicker',
        `width=${w},height=${h},left=${left},top=${top},resizable=yes,scrollbars=yes`
    );
    if (popup) popup.focus();
}

// Function called by the popup window when an item is selected
window.setItemFromPicker = function(kode) {
    const k = (kode || '').trim();
    if(!k) return;
    const input = document.getElementById('item_kode_input');
    if(input) {
        input.value = k;
        document.getElementById('qty_input').focus();
    }
};

document.addEventListener('keydown', function(e) {
    // Only capture F2 if we are outside of input fields or if we explicitly want to allow it everywhere
    if (e.key === 'F2') {
        e.preventDefault();
        openItemPicker();
    }
});
</script>

<style>
/* Inline editing form styling overrides */
form { margin-bottom: 0; }
.inline-edit-form, .inline-add-form { margin: 0; display: block; }
.inline-add-form { display: flex; flex-wrap: wrap; gap: 0.5rem; width: 100%; }
.group-inputs { 
    display: inline-flex; 
    align-items: stretch;
}
.group-inputs input, .group-inputs button { 
    margin: 0; 
    border-radius: 0;
    box-shadow: none !important;
}
.group-inputs input:focus { z-index: 2; position: relative; }
.group-inputs > *:first-child { border-top-left-radius: 0.375rem; border-bottom-left-radius: 0.375rem; }
.group-inputs > *:last-child { border-top-right-radius: 0.375rem; border-bottom-right-radius: 0.375rem; }
.group-inputs > * + * { margin-left: -1px; }

.qty-control input { width: 65px; text-align: center; padding: 0.2rem; }
.qty-control button { padding: 0.2rem 0.5rem; font-size: 1rem; }

button.danger.outline { color: #dc2626; border-color: #dc2626; }
button.danger.outline:hover, button.danger.outline:active { background: #fee2e2; border-color: #b91c1c; color: #b91c1c; }

@media print {
    body * { visibility: hidden; }
    article, article * { visibility: visible; }
    article { position: absolute; left: 0; top: 0; margin: 0; padding: 0; box-shadow: none; width: 100%; border: none;}
    form, a, button { display: none !important; }
}
</style>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
