<?php
require_once __DIR__ . '/config.php';

// Enable session for cart if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$cart = $_SESSION['cart'] ?? [];
if (empty($cart)) {
    header('Location: shop.php');
    exit;
}

$cart_items = [];
$total = 0;

$in = str_repeat('?,', count($cart) - 1) . '?';
$stmt = $pdo->prepare("SELECT kode, nama, harga_jual1 FROM items WHERE kode IN ($in)");
$stmt->execute(array_keys($cart));
$items_db = $stmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($items_db as $item) {
    $kode = $item['kode'];
    $qty = $cart[$kode];
    $subtotal = $qty * $item['harga_jual1'];
    $total += $subtotal;
    
    $item['qty'] = $qty;
    $item['subtotal'] = $subtotal;
    $cart_items[] = $item;
}

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $err = 'Invalid Security Token. Silakan kembali ke keranjang dan coba lagi.';
    } else {
        $guest_name = trim($_POST['guest_name'] ?? '');
        $guest_phone = trim($_POST['guest_phone'] ?? '');
        $guest_address = trim($_POST['guest_address'] ?? '');
        $payment_method = trim($_POST['payment_method'] ?? '');
        $note = trim($_POST['note'] ?? '');

        if ($guest_name === '' || $guest_phone === '' || $guest_address === '') {
            $err = 'Mohon lengkapi data pengiriman Anda.';
        } elseif (!in_array($payment_method, ['COD', 'QRIS'])) {
            $err = 'Pilih metode pembayaran yang valid.';
        } else {
            try {
                $pdo->beginTransaction();
            
            $member_kode = $_SESSION['member']['kode'] ?? null;
            
            // Generate simple order ID/Invoice
            $status = 'PENDING';
            $payment_status = 'UNPAID';
            
            $guest_lat_lng = trim($_POST['lat_lng'] ?? '');
            
            $stmt = $pdo->prepare("INSERT INTO online_orders (member_kode, guest_name, guest_phone, guest_address, subtotal, total, payment_method, payment_status, status, note, lat_lng, tanggal) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())");
            $stmt->execute([
                $member_kode,
                $guest_name,
                $guest_phone,
                $guest_address,
                $total,
                $total,
                $payment_method,
                $payment_status,
                $status,
                $note,
                $guest_lat_lng
            ]);
            $order_id = $pdo->lastInsertId();
            
            $stmtItem = $pdo->prepare("INSERT INTO online_order_items (order_id, item_kode, nama_item, qty, harga_satuan, total) VALUES (?, ?, ?, ?, ?, ?)");
            foreach ($cart_items as $ci) {
                $stmtItem->execute([
                    $order_id,
                    $ci['kode'],
                    $ci['nama'],
                    $ci['qty'],
                    $ci['harga_jual1'],
                    $ci['subtotal']
                ]);
            }
            
                $pdo->commit();
                
                // Clear cart
                unset($_SESSION['cart']);
                
                header("Location: order_success.php?id=$order_id");
                exit;
                
            } catch (Exception $e) {
                $pdo->rollBack();
                $err = "Gagal memproses pesanan: " . $e->getMessage();
            }
        }
    }
}

$page_title = "Checkout - " . ($store_name ?? 'TokoAPP');
require_once __DIR__ . '/includes/shop_header.php';

$def_name = $_POST['guest_name'] ?? '';
$def_phone = $_POST['guest_phone'] ?? '';
$def_address = $_POST['guest_address'] ?? '';

if (isset($_SESSION['member']) && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $pdo->prepare("SELECT nama, telp, alamat FROM members WHERE kode = ?");
    $stmt->execute([$_SESSION['member']['kode']]);
    $mdata = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($mdata) {
        $def_name = $mdata['nama'];
        $def_phone = $mdata['telp'];
        $def_address = $mdata['alamat'];
    }
}
?>

<article>
    <h2>🚀 Checkout</h2>
    <p>Silakan lengkapi data pengiriman Anda untuk menyelesaikan pesanan.</p>

    <?php if ($err): ?>
        <mark style="display:block;margin-bottom:1rem;background:#dc2626;color:#fff;">⚠️ <?= htmlspecialchars($err) ?></mark>
    <?php endif; ?>

    <style>
        @media (max-width: 768px) {
            .grid {
                grid-template-columns: 1fr;
                gap: 1.5rem;
            }
            article {
                padding: 1rem 0.5rem;
            }
        }
    </style>
    <div class="grid">
        <div>
            <h4>Ringkasan Pesanan</h4>
            <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1rem; margin-bottom:1.5rem;">
                <ul style="list-style:none; padding:0; margin:0;">
                    <?php foreach ($cart_items as $ci): ?>
                        <li style="display:flex; justify-content:space-between; margin-bottom:0.5rem; border-bottom:1px dashed var(--card-bd, #1f2937); padding-bottom:0.5rem;">
                            <span><?= htmlspecialchars($ci['nama']) ?> <small class="muted">x<?= $ci['qty'] ?></small></span>
                            <span><?= rupiah($ci['subtotal']) ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div style="display:flex; justify-content:space-between; margin-top:1rem; font-weight:700; font-size:1.1rem;">
                    <span>TOTAL</span>
                    <span style="color:#10b981;"><?= rupiah($total) ?></span>
                </div>
            </div>
            <a href="cart.php" class="secondary" style="font-size:0.9rem;">🔙 Kembali ke Keranjang</a>
        </div>

        <div>
            <form method="post" autocomplete="off" style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1.25rem;">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="lat_lng" id="lat_lng" value="<?= htmlspecialchars($_POST['lat_lng'] ?? '') ?>">
                
                <h4>Data Pengiriman</h4>
                
                <label>Nama Lengkap / Penerima
                    <input type="text" name="guest_name" required value="<?= htmlspecialchars($def_name) ?>">
                </label>
                
                <label>Nomor WhatsApp / HP
                    <input type="text" name="guest_phone" required value="<?= htmlspecialchars($def_phone) ?>">
                </label>
                
                <label>Alamat Lengkap Pengiriman
                    <textarea name="guest_address" rows="3" required placeholder="Lengkapi dengan RT/RW, Kelurahan, Kecamatan, Patokan, dll"><?= htmlspecialchars($def_address) ?></textarea>
                </label>

                <!-- MAP PICKER -->
                <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
                <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
                
                <label>Tandai Lokasi di Map (Opsional)
                    <div id="map" style="height: 300px; border-radius: 8px; margin-bottom: 0.5rem; border: 1px solid var(--card-bd);"></div>
                    <small class="muted">Klik pada peta untuk menentukan titik lokasi pengiriman Anda.</small>
                </label>

                <script>
                    document.addEventListener('DOMContentLoaded', function() {
                        var initialLat = -7.375765; // Default (Gumelar, Banyumas)
                        var initialLng = 108.981065;
                        
                        var savedLatLng = document.getElementById('lat_lng').value;
                        if (savedLatLng) {
                            var parts = savedLatLng.split(',');
                            initialLat = parseFloat(parts[0]);
                            initialLng = parseFloat(parts[1]);
                        }

                        var map = L.map('map').setView([initialLat, initialLng], 13);
                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            attribution: '&copy; OpenStreetMap'
                        }).addTo(map);

                        var marker;
                        if (savedLatLng) {
                            marker = L.marker([initialLat, initialLng]).addTo(map);
                        }

                        map.on('click', function(e) {
                            var lat = e.latlng.lat.toFixed(6);
                            var lng = e.latlng.lng.toFixed(6);
                            document.getElementById('lat_lng').value = lat + ',' + lng;
                            
                            if (marker) {
                                marker.setLatLng(e.latlng);
                            } else {
                                marker = L.marker(e.latlng).addTo(map);
                            }
                        });
                        
                        // Try to get user location
                        if (!savedLatLng && navigator.geolocation) {
                            navigator.geolocation.getCurrentPosition(function(position) {
                                var userLat = position.coords.latitude;
                                var userLng = position.coords.longitude;
                                map.setView([userLat, userLng], 15);
                            });
                        }
                    });
                </script>

                <label>Catatan Order (Opsional)
                    <input type="text" name="note" placeholder="Misal: Warna pesanan, waktu pengiriman" value="<?= htmlspecialchars($_POST['note'] ?? '') ?>">
                </label>

                <h4>Metode Pembayaran</h4>
                <div class="grid">
                    <label>
                        <input type="radio" name="payment_method" value="COD" required <?= (($_POST['payment_method'] ?? 'COD') === 'COD') ? 'checked' : '' ?>>
                        🚚 COD (Bayar di Tempat)
                    </label>
                    <label>
                        <input type="radio" name="payment_method" value="QRIS" required <?= (($_POST['payment_method'] ?? '') === 'QRIS') ? 'checked' : '' ?>>
                        📱 QRIS (Transfer)
                        <div style="font-size:0.8rem; margin-top:0.3rem;" class="muted">Scan QR Code setelah sukses checkout</div>
                    </label>
                </div>

                <hr style="margin: 1.5rem 0; border-color:var(--card-bd, #1f2937);">
                <button type="submit" style="width:100%; font-size:1.1rem; padding:0.75rem;">Buat Pesanan Sekarang ✅</button>
            </form>
        </div>
    </div>
</article>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>
