<?php
require_once __DIR__ . '/config.php';

// Enable session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['member'])) {
    header('Location: member_login.php');
    exit;
}

$member_kode = $_SESSION['member']['kode'];

// Fetch member data
$stmt = $pdo->prepare("SELECT * FROM members WHERE kode = ?");
$stmt->execute([$member_kode]);
$member = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$member) {
    unset($_SESSION['member']);
    header('Location: member_login.php');
    exit;
}

$msg = '';
$err = '';

// Generate CSRF Token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'change_password') {
    $csrf_token = $_POST['csrf_token'] ?? '';
    
    if (!hash_equals($_SESSION['csrf_token'], $csrf_token)) {
        $err = 'Invalid Security Token. Silakan refresh halaman dan coba lagi.';
    } else {
        $old = $_POST['old_password'] ?? '';
        $new = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        if (password_verify($old, $member['password_hash'] ?? '')) {
            if ($new === $confirm && strlen($new) >= 4) {
                $hash = password_hash($new, PASSWORD_DEFAULT);
                $up = $pdo->prepare("UPDATE members SET password_hash = ? WHERE kode = ?");
                if ($up->execute([$hash, $member['kode']])) {
                    $member['password_hash'] = $hash; // update local
                    $msg = 'Password berhasil diperbarui!';
                } else {
                    $err = 'Terjadi kesalahan sistem, gagal menyimpan password.';
                }
            } else {
                $err = 'Password baru tidak sama dengan konfirmasi, atau terlalu pendek (minimal 4 karakter).';
            }
        } else {
            $err = 'Password lama yang Anda masukkan salah.';
        }
    }
}

// Fetch member's online orders
$ostmt = $pdo->prepare("SELECT * FROM online_orders WHERE member_kode = ? ORDER BY tanggal DESC LIMIT 20");
$ostmt->execute([$member_kode]);
$online_orders = $ostmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch member's offline sales (POS)
$sstmt = $pdo->prepare("SELECT * FROM sales WHERE member_kode = ? ORDER BY created_at DESC LIMIT 20");
$sstmt->execute([$member_kode]);
$pos_sales = $sstmt->fetchAll(PDO::FETCH_ASSOC);

function statusColor($s) {
    return match(strtoupper($s)) {
        'PENDING'   => '#eab308',
        'PROCESSED' => '#3b82f6',
        'SENT'      => '#10b981',
        'CANCELLED' => '#dc2626',
        default     => '#64748b'
    };
}
function payColor($p) {
    return match(strtoupper($p)) {
        'PAID'   => '#10b981',
        'UNPAID' => '#dc2626',
        default  => '#64748b'
    };
}

$page_title = "Profil Member - " . ($store_name ?? 'TokoAPP');
require_once __DIR__ . '/includes/shop_header.php';
?>

<article>
    <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 2rem;">
        <div>
            <h2 style="margin-bottom:0.2rem;">👤 Profil <?= htmlspecialchars($member['nama']) ?></h2>
            <div class="muted">Kode Member: <strong><?= htmlspecialchars($member['kode']) ?></strong></div>
        </div>
        <div style="display:flex; gap:1rem; align-items:center;">
            <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); padding: 1rem 1.5rem; border-radius: 8px; text-align:center;">
                <div style="font-size: 0.9rem;" class="muted">Total Poin</div>
                <div style="font-size: 1.8rem; font-weight: bold; color: #10b981;">
                    <?= number_format((int)$member['poin'], 0, ',', '.') ?>
                </div>
            </div>
            
            <a href="member_logout.php" role="button" class="secondary outline" onclick="return confirm('Yakin ingin keluar?')" style="padding: 0.5rem 1rem;">
                🚪 Logout
            </a>
        </div>
    </div>

    <?php if ($msg): ?>
        <mark style="display:block;margin-bottom:1rem;background:#10b981;color:#fff;">✔️ <?= htmlspecialchars($msg) ?></mark>
    <?php endif; ?>
    <?php if ($err): ?>
        <mark style="display:block;margin-bottom:1rem;background:#dc2626;color:#fff;">⚠️ <?= htmlspecialchars($err) ?></mark>
    <?php endif; ?>

    <details style="margin-bottom: 2rem;">
        <summary>⚙️ Pengaturan Akun (Ganti Password)</summary>
        <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1.25rem; margin-top: 1rem;">
            <form method="post" autocomplete="off">
                <input type="hidden" name="action" value="change_password">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <label>Password Lama
                    <input type="password" name="old_password" required>
                </label>
                <div class="grid">
                    <label>Password Baru
                        <input type="password" name="new_password" required minlength="4">
                    </label>
                    <label>Ulangi Password Baru
                        <input type="password" name="confirm_password" required minlength="4">
                    </label>
                </div>
                <button type="submit" style="width: auto; margin-top: 0.5rem;">Update Password</button>
            </form>
        </div>
    </details>

    <div class="grid">
        <!-- ONLINE ORDERS -->
        <div>
            <h4 style="margin-bottom:1rem;">🛍️ Riwayat Belanja Online</h4>
            <?php if (empty($online_orders)): ?>
                <p class="muted">Belum ada transaksi online.</p>
            <?php else: ?>
                <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1rem;">
                    <ul style="list-style:none; padding:0; margin:0;">
                        <?php foreach ($online_orders as $oo): ?>
                            <li style="border-bottom:1px dashed var(--card-bd, #1f2937); padding-bottom:1rem; margin-bottom:1rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                    <strong>#<?= str_pad($oo['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                                    <span style="font-size:0.8rem; background:<?= statusColor($oo['status']) ?>; color:#fff; padding:0.15rem 0.5rem; border-radius:4px;">
                                        <?= htmlspecialchars($oo['status']) ?>
                                    </span>
                                </div>
                                <div style="font-size:0.9rem; margin-bottom:0.2rem;" class="muted">
                                    📅 <?= date('d-m-Y H:i', strtotime($oo['tanggal'])) ?> | 🚚 <?= htmlspecialchars($oo['payment_method']) ?>
                                </div>
                                <div style="font-size:0.9rem; margin-bottom:0.5rem;" class="muted">
                                    💳 Pembayaran: <span style="font-weight:bold; color:<?= payColor($oo['payment_status']) ?>"><?= htmlspecialchars($oo['payment_status']) ?></span>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:#10b981;"><?= rupiah($oo['total']) ?></strong>
                                    <a href="order_success.php?id=<?= $oo['id'] ?>" class="secondary outline" style="padding:0.2rem 0.5rem; font-size:0.8rem;">Lihat Struk ↗</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- OFFLINE ORDERS (POS) -->
        <div>
            <h4 style="margin-bottom:1rem;">🏬 Riwayat Belanja Toko (Kasir)</h4>
            <?php if (empty($pos_sales)): ?>
                <p class="muted">Belum ada transaksi langsung di kasir toko.</p>
            <?php else: ?>
                <div style="background:var(--card-bg, #111827); border:1px solid var(--card-bd, #1f2937); border-radius:8px; padding:1rem;">
                    <ul style="list-style:none; padding:0; margin:0;">
                        <?php foreach ($pos_sales as $so): ?>
                            <li style="border-bottom:1px dashed var(--card-bd, #1f2937); padding-bottom:1rem; margin-bottom:1rem;">
                                <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:0.5rem;">
                                    <strong>TRX-<?= str_pad($so['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                                    <span style="font-size:0.8rem; background:#10b981; color:#fff; padding:0.15rem 0.5rem; border-radius:4px;">SELESAI</span>
                                </div>
                                <div style="font-size:0.9rem; margin-bottom:0.2rem;" class="muted">
                                    📅 <?= date('d-m-Y H:i', strtotime($so['created_at'])) ?>
                                </div>
                                <div style="font-size:0.9rem; margin-bottom:0.5rem;" class="muted">
                                    Kasir: <?= htmlspecialchars($so['created_by']) ?>
                                </div>
                                <div style="display:flex; justify-content:space-between; align-items:center;">
                                    <strong style="color:#10b981;"><?= rupiah($so['total']) ?></strong>
                                    <!-- In a real scenario we could make a public receipt viewer. For now, we can link to a stripped down print page or just show the summary -->
                                    <a href="sale_print.php?id=<?= $so['id'] ?>" target="_blank" class="secondary outline" style="padding:0.2rem 0.5rem; font-size:0.8rem;">Lihat Struk ↗</a>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>
        </div>
    </div>
</article>

<?php require_once __DIR__ . '/includes/shop_footer.php'; ?>
