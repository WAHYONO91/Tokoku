<?php
require_once __DIR__ . '/config.php';
require_login();

// Extra safety check (role must be admin or kasir)
$role = $_SESSION['user']['role'] ?? '';
if ($role !== 'admin' && $role !== 'kasir') {
    http_response_code(403);
    die("Akses ditolak. Hanya Admin dan Kasir yang dapat mengakses halaman ini.");
}

$msg = '';
$err = '';

// ===== AUTO MIGRATION FOR MULTIPLE ADS & POPUPS =====
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shop_ads (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            description TEXT NULL,
            link VARCHAR(255) NULL,
            image VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
    
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS shop_popups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL DEFAULT '',
            content TEXT NULL,
            link VARCHAR(255) NULL,
            image VARCHAR(255) NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");

    // Alter table safely to add promotional item support
    try {
        $pdo->exec("ALTER TABLE shop_popups ADD COLUMN item_kode VARCHAR(50) NULL");
    } catch (PDOException $e) {}
    try {
        $pdo->exec("ALTER TABLE shop_popups ADD COLUMN promo_price BIGINT NULL DEFAULT 0");
    } catch (PDOException $e) {}
} catch (PDOException $e) {
    $err = 'Gagal melakukan migrasi database: ' . $e->getMessage();
}

// ===== HANDLE ACTIONS (POST) =====
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    // 1. ADD BANNER AD
    if ($action === 'add_ad') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $sort_order = (int)($_POST['sort_order'] ?? 0);
        $image = '';

        if (isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image_file']['tmp_name'];
            $name = $_FILES['image_file']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

            if (in_array($ext, $allowed, true)) {
                $uploadDir = __DIR__ . '/uploads/shop';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $newName = 'ad_' . date('Ymd_His') . '_' . rand(100,999) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmp, $dest)) {
                    $image = 'uploads/shop/' . $newName;
                } else {
                    $err = 'Gagal mengunggah berkas gambar iklan.';
                }
            } else {
                $err = 'Format gambar tidak valid. Gunakan PNG, JPG, JPEG, GIF, atau WEBP.';
            }
        }

        if (empty($err)) {
            try {
                $stmt = $pdo->prepare("INSERT INTO shop_ads (title, description, link, image, is_active, sort_order) VALUES (?, ?, ?, ?, 1, ?)");
                $stmt->execute([$title, $description, $link, $image, $sort_order]);
                log_activity($pdo, 'ADD_SHOP_AD', 'Menambahkan banner iklan baru: ' . $title);
                $msg = 'Iklan banner baru berhasil ditambahkan!';
            } catch (PDOException $e) {
                $err = 'Gagal menyimpan ke database: ' . $e->getMessage();
            }
        }
    }

    // 2. TOGGLE AD STATUS
    elseif ($action === 'toggle_ad') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        try {
            $stmt = $pdo->prepare("UPDATE shop_ads SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $msg = 'Status iklan berhasil diperbarui!';
        } catch (PDOException $e) {
            $err = 'Gagal memperbarui status: ' . $e->getMessage();
        }
    }

    // 3. DELETE AD (WITH PHYSICAL FILE REMOVAL)
    elseif ($action === 'delete_ad') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            // Find image filename first
            $stmt = $pdo->prepare("SELECT image FROM shop_ads WHERE id = ?");
            $stmt->execute([$id]);
            $img = $stmt->fetchColumn();

            if (!empty($img) && file_exists(__DIR__ . '/' . $img)) {
                @unlink(__DIR__ . '/' . $img);
            }

            $stmtDel = $pdo->prepare("DELETE FROM shop_ads WHERE id = ?");
            $stmtDel->execute([$id]);
            log_activity($pdo, 'DELETE_SHOP_AD', 'Menghapus banner iklan ID: ' . $id);
            $msg = 'Iklan banner berhasil dihapus!';
        } catch (PDOException $e) {
            $err = 'Gagal menghapus iklan: ' . $e->getMessage();
        }
    }

    // 4. ADD POPUP PROMO
    elseif ($action === 'add_popup') {
        $title = trim($_POST['title'] ?? '');
        $content = trim($_POST['content'] ?? '');
        $link = trim($_POST['link'] ?? '');
        $item_kode = trim($_POST['item_kode'] ?? '');
        $promo_price = (int)($_POST['promo_price'] ?? 0);
        $image = '';

        if (empty($item_kode)) {
            $err = 'Wajib memilih barang yang akan dipromosikan.';
        }

        if (empty($err) && isset($_FILES['image_file']) && $_FILES['image_file']['error'] === UPLOAD_ERR_OK) {
            $tmp = $_FILES['image_file']['tmp_name'];
            $name = $_FILES['image_file']['name'];
            $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $allowed = ['png', 'jpg', 'jpeg', 'gif', 'webp'];

            if (in_array($ext, $allowed, true)) {
                $uploadDir = __DIR__ . '/uploads/shop';
                if (!is_dir($uploadDir)) {
                    @mkdir($uploadDir, 0777, true);
                }
                $newName = 'popup_' . date('Ymd_His') . '_' . rand(100,999) . '.' . $ext;
                $dest = $uploadDir . '/' . $newName;
                if (move_uploaded_file($tmp, $dest)) {
                    $image = 'uploads/shop/' . $newName;
                } else {
                    $err = 'Gagal mengunggah berkas gambar popup promo.';
                }
            } else {
                $err = 'Format gambar tidak didukung.';
            }
        }

        if (empty($err)) {
            try {
                // Automatically set other popups to inactive if this one is active, ensuring only one popup is active at a time
                $stmt = $pdo->prepare("INSERT INTO shop_popups (title, content, link, image, is_active, item_kode, promo_price) VALUES (?, ?, ?, ?, 1, ?, ?)");
                $stmt->execute([$title, $content, $link, $image, $item_kode, $promo_price]);
                $newId = $pdo->lastInsertId();

                // Deactivate other popups
                $pdo->prepare("UPDATE shop_popups SET is_active = 0 WHERE id != ?")->execute([$newId]);

                log_activity($pdo, 'ADD_SHOP_POPUP', 'Menambahkan popup promo baru: ' . $title);
                $msg = 'Popup promo berhasil dibuat dan diaktifkan!';
            } catch (PDOException $e) {
                $err = 'Gagal menyimpan ke database: ' . $e->getMessage();
            }
        }
    }

    // 5. TOGGLE POPUP STATUS
    elseif ($action === 'toggle_popup') {
        $id = (int)($_POST['id'] ?? 0);
        $status = (int)($_POST['status'] ?? 0);
        try {
            if ($status === 1) {
                // If activating this one, deactivate all others
                $pdo->exec("UPDATE shop_popups SET is_active = 0");
            }
            $stmt = $pdo->prepare("UPDATE shop_popups SET is_active = ? WHERE id = ?");
            $stmt->execute([$status, $id]);
            $msg = 'Status popup promo berhasil diperbarui!';
        } catch (PDOException $e) {
            $err = 'Gagal memperbarui status popup: ' . $e->getMessage();
        }
    }

    // 6. DELETE POPUP (WITH PHYSICAL FILE REMOVAL)
    elseif ($action === 'delete_popup') {
        $id = (int)($_POST['id'] ?? 0);
        try {
            // Find image filename first
            $stmt = $pdo->prepare("SELECT image FROM shop_popups WHERE id = ?");
            $stmt->execute([$id]);
            $img = $stmt->fetchColumn();

            if (!empty($img) && file_exists(__DIR__ . '/' . $img)) {
                @unlink(__DIR__ . '/' . $img);
            }

            $stmtDel = $pdo->prepare("DELETE FROM shop_popups WHERE id = ?");
            $stmtDel->execute([$id]);
            log_activity($pdo, 'DELETE_SHOP_POPUP', 'Menghapus popup promo ID: ' . $id);
            $msg = 'Popup promo berhasil dihapus!';
        } catch (PDOException $e) {
            $err = 'Gagal menghapus popup promo: ' . $e->getMessage();
        }
    }
}

// ===== GET DATA FOR VIEW =====
$ads = $pdo->query("SELECT * FROM shop_ads ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$popups = $pdo->query("
    SELECT p.*, i.nama AS item_nama, i.harga_jual1 AS harga_asli 
    FROM shop_popups p 
    LEFT JOIN items i ON p.item_kode = i.kode 
    ORDER BY p.id DESC
")->fetchAll(PDO::FETCH_ASSOC);
$items_list = $pdo->query("SELECT kode, nama, harga_jual1 FROM items ORDER BY nama ASC")->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/header.php';
?>

<style>
/* Premium Theme Integration for shop_settings.php based on Pico CSS v2 */
:root {
    --shop-bg-card: var(--pico-card-background-color, #111827);
    --shop-border: var(--pico-card-border-color, var(--pico-border-color, rgba(148, 163, 184, 0.12)));
    --shop-text-main: var(--pico-color, #e2e8f0);
    --shop-text-muted: var(--pico-muted-color, #94a3b8);
    --shop-primary: var(--pico-primary, #f97316);
    --shop-bg-input: var(--pico-form-element-background-color, var(--pico-background-color, #0f172a));
}

.tab-container {
    background: var(--shop-bg-card) !important;
    border: 1px solid var(--shop-border) !important;
    border-radius: 16px;
    padding: 2rem;
    box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.05);
}
.tab-buttons {
    display: flex;
    border-bottom: 2px solid var(--shop-border);
    margin-bottom: 2rem;
    gap: 1.5rem;
}
.tab-btn {
    padding: 0.8rem 1rem;
    background: transparent !important;
    border: none !important;
    color: var(--shop-text-muted) !important;
    font-weight: 700;
    cursor: pointer;
    border-bottom: 3px solid transparent !important;
    border-radius: 0 !important;
    margin-bottom: -2px;
    font-size: 1rem;
    transition: all 0.3s ease;
}
.tab-btn.active {
    color: var(--shop-primary) !important;
    border-bottom-color: var(--shop-primary) !important;
}
.admin-tab-content {
    display: none;
}
.admin-tab-content.active {
    display: block;
}

/* Beautiful custom card layout */
.cards-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 1.5rem;
    margin-bottom: 2.5rem;
}
.promo-card {
    background: var(--shop-bg-input) !important;
    border: 1px solid var(--shop-border) !important;
    border-radius: 12px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05);
    transition: transform 0.3s ease, box-shadow 0.3s ease;
    position: relative;
}
.promo-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1);
}
.card-img-preview {
    height: 160px;
    background-size: cover;
    background-position: center;
    background-color: #cbd5e1;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #fff;
    font-weight: bold;
    font-size: 0.9rem;
    text-shadow: 0 1px 3px rgba(0,0,0,0.5);
    position: relative;
}
.card-badge {
    position: absolute;
    top: 10px;
    left: 10px;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #fff;
    z-index: 5;
}
.card-badge.active { background: #10b981; }
.card-badge.inactive { background: #ef4444; }

.card-badge-right {
    position: absolute;
    top: 10px;
    right: 10px;
    padding: 0.25rem 0.6rem;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 800;
    text-transform: uppercase;
    color: #fff;
    background: #4f46e5;
    z-index: 5;
}

.card-details {
    padding: 1.25rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}
.card-title {
    font-size: 1.05rem;
    font-weight: 800;
    margin-bottom: 0.5rem;
    color: var(--shop-text-main) !important;
}
.card-desc {
    font-size: 0.85rem;
    color: var(--shop-text-muted) !important;
    line-height: 1.4;
    margin-bottom: 1rem;
    flex-grow: 1;
}
.card-actions {
    display: flex;
    gap: 0.5rem;
    border-top: 1px solid var(--shop-border);
    padding-top: 0.75rem;
    margin-top: auto;
}
.card-btn {
    flex-grow: 1;
    font-size: 0.78rem !important;
    font-weight: 700 !important;
    padding: 0.4rem 0.8rem !important;
    border-radius: 6px !important;
    text-align: center;
    margin: 0;
}

/* Card Button overrides to counter heavy global rules in Light theme */
button.card-btn.secondary.outline {
    background-color: transparent !important;
    color: var(--shop-primary) !important;
    border: 1px solid var(--shop-primary) !important;
}
button.card-btn.secondary.outline:hover {
    background-color: var(--shop-primary) !important;
    color: #ffffff !important;
}
button.card-btn.danger.outline {
    background-color: transparent !important;
    color: #ef4444 !important;
    border: 1px solid #ef4444 !important;
}
button.card-btn.danger.outline:hover {
    background-color: #ef4444 !important;
    color: #ffffff !important;
}

/* Form Styles */
.form-section {
    background: var(--shop-bg-card) !important;
    border: 1px solid var(--shop-border) !important;
    border-radius: 12px;
    padding: 1.5rem;
    margin-top: 2rem;
}
.form-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1.5rem;
}
@media (max-width: 768px) {
    .form-grid {
        grid-template-columns: 1fr;
    }
}
label {
    font-weight: 700;
    font-size: 0.88rem;
    margin-bottom: 0.4rem;
    color: var(--shop-text-main) !important;
}
input[type="text"], textarea, input[type="file"] {
    border-radius: 8px !important;
    background: var(--shop-bg-input) !important;
    border: 1px solid var(--shop-border) !important;
    color: var(--shop-text-main) !important;
    margin-bottom: 1rem;
    padding: 0.6rem 1rem !important;
}
button[type="submit"] {
    font-weight: 800;
    border-radius: 8px !important;
    padding: 0.7rem 1.8rem !important;
    font-size: 0.95rem !important;
}
</style>

<article>
    <header style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:1rem; margin-bottom: 2rem;">
        <hgroup style="margin:0;">
            <h3 style="margin:0; font-weight:800; display:flex; align-items:center; gap:0.5rem;">🛍️⚙️ Pengaturan Shop</h3>
            <p style="margin:0; font-size:0.88rem; opacity:0.8;">Kelola banyak iklan banner carousel dan popup promo dengan sistem upload yang fleksibel.</p>
        </hgroup>
        <a href="shop.php" target="_blank" role="button" class="secondary" style="font-size: 0.8rem; padding: 0.4rem 1.2rem; border-radius: 8px; font-weight:700;">
            👁️ Lihat Halaman Shop
        </a>
    </header>

    <?php if ($msg): ?>
        <mark style="display:block; margin-bottom:1.5rem; background:#10b981; color:#fff; padding:0.75rem; border-radius:10px; text-align:center; font-weight:700; box-shadow: 0 4px 12px rgba(16,185,129,0.15);">
            ✔️ <?= htmlspecialchars($msg) ?>
        </mark>
    <?php endif; ?>

    <?php if ($err): ?>
        <mark style="display:block; margin-bottom:1.5rem; background:#ef4444; color:#fff; padding:0.75rem; border-radius:10px; text-align:center; font-weight:700; box-shadow: 0 4px 12px rgba(239,68,68,0.15);">
            ❌ <?= htmlspecialchars($err) ?>
        </mark>
    <?php endif; ?>

    <div class="tab-container">
        <!-- Tab Buttons -->
        <div class="tab-buttons">
            <button class="tab-btn active" id="tabBtnAd" onclick="switchTab('ad')">📢 Kelola Banner Iklan</button>
            <button class="tab-btn" id="tabBtnPopup" onclick="switchTab('popup')">✨ Kelola Popup Promo</button>
        </div>

        <!-- ==================== TAB 1: BANNER IKLAN ==================== -->
        <div class="admin-tab-content active" id="tabContentAd">
            <h4 style="margin-top:0; font-weight:800; margin-bottom:1.25rem;">Daftar Banner Iklan Aktif</h4>
            
            <?php if (empty($ads)): ?>
                <div style="text-align:center; padding:3rem; background:var(--input-bg, #f8fafc); border-radius:12px; border:2px dashed var(--card-bd, #cbd5e1); color:var(--text-muted); margin-bottom:2rem;">
                    <span style="font-size:3rem;">📢</span>
                    <p style="font-weight:700; margin-top:1rem; margin-bottom:0.25rem;">Belum Ada Banner Iklan</p>
                    <p style="font-size:0.85rem; margin-bottom:0;">Silakan tambahkan iklan baru menggunakan form di bawah.</p>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($ads as $ad): ?>
                        <div class="promo-card">
                            <span class="card-badge <?= $ad['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $ad['is_active'] ? 'Aktif' : 'Non-Aktif' ?>
                            </span>
                            <span class="card-badge-right">
                                Urutan: <?= (int)$ad['sort_order'] ?>
                            </span>
                            
                            <?php if (!empty($ad['image']) && file_exists(__DIR__ . '/' . $ad['image'])): ?>
                                <div class="card-img-preview" style="background-image: url('<?= htmlspecialchars($ad['image']) ?>');"></div>
                            <?php else: ?>
                                <div class="card-img-preview" style="background: linear-gradient(135deg, #4f46e5, #7c3aed);">No Image</div>
                            <?php endif; ?>

                            <div class="card-details">
                                <div class="card-title"><?= htmlspecialchars($ad['title'] ?: 'Tanpa Judul') ?></div>
                                <div class="card-desc"><?= htmlspecialchars($ad['description'] ?: 'Tidak ada deskripsi.') ?></div>
                                <?php if (!empty($ad['link'])): ?>
                                    <div style="font-size:0.75rem; color:var(--brand-color); word-break:break-all; font-weight:600; margin-bottom:1rem;">
                                        🔗 <?= htmlspecialchars($ad['link']) ?>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <!-- Toggle Status Button -->
                                    <form method="post" style="margin:0; flex-grow:1;">
                                        <input type="hidden" name="action" value="toggle_ad">
                                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $ad['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="card-btn secondary outline">
                                            <?= $ad['is_active'] ? '🔴 Nonaktifkan' : '🟢 Aktifkan' ?>
                                        </button>
                                    </form>

                                    <!-- Delete Button -->
                                    <form method="post" style="margin:0; flex-grow:1;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus iklan banner ini secara permanen?');">
                                        <input type="hidden" name="action" value="delete_ad">
                                        <input type="hidden" name="id" value="<?= $ad['id'] ?>">
                                        <button type="submit" class="card-btn danger outline">🗑️ Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Add New Banner Form -->
            <div class="form-section">
                <h5 style="margin-top:0; font-weight:800; border-bottom:1px solid var(--card-bd); padding-bottom:0.5rem; margin-bottom:1rem; color:var(--brand-color);">➕ Tambah Banner Iklan Baru</h5>
                
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_ad">
                    
                    <div class="form-grid">
                        <div>
                            <label for="ad_title">Judul Banner</label>
                            <input type="text" name="title" id="ad_title" placeholder="Contoh: Diskon Gadget Akhir Pekan!" required>

                            <label for="ad_desc">Deskripsi Promo Singkat</label>
                            <textarea name="description" id="ad_desc" rows="3" placeholder="Tulis deskripsi promo yang menggugah selera belanja..."></textarea>
                        </div>
                        <div>
                            <label for="ad_link">Link Tujuan Tombol</label>
                            <input type="text" name="link" id="ad_link" placeholder="Contoh: shop.php?kategori=Elektronik">

                            <label for="ad_sort_order">Urutan Tampil</label>
                            <input type="number" name="sort_order" id="ad_sort_order" value="0" min="0" placeholder="0 (Makin kecil makin awal)">

                            <label for="ad_image">Upload Gambar Banner (Landscape rekomendasi 16:9)</label>
                            <input type="file" name="image_file" id="ad_image" accept="image/*" onchange="previewFile(this, 'ad_preview_box')" required>
                            
                            <div id="ad_preview_box" style="margin-top:0.5rem; display:none;">
                                <small style="display:block; font-weight:600; margin-bottom:0.25rem;">Preview Gambar:</small>
                                <img id="ad_preview_img" src="#" alt="Preview" style="max-height:100px; border-radius:8px; border:1px solid var(--card-bd);">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" style="background:var(--shop-primary)!important; border:none!important; color:white!important; width:100%; margin-top:1rem;">🚀 Upload & Aktifkan Banner</button>
                </form>
            </div>
        </div>

        <!-- ==================== TAB 2: POPUP PROMO ==================== -->
        <div class="admin-tab-content" id="tabContentPopup">
            <h4 style="margin-top:0; font-weight:800; margin-bottom:1.25rem;">Daftar Popup Promo</h4>
            <small style="display:block; margin-bottom:1.5rem; color:var(--text-muted); background:var(--input-bg); padding:0.6rem 1rem; border-radius:8px; border-left:4px solid var(--brand-color);">
                ℹ️ Sistem secara otomatis hanya akan mengaktifkan **maksimal satu popup promo** dalam satu waktu untuk kenyamanan berbelanja pelanggan. Mengaktifkan satu popup akan menonaktifkan popup lainnya.
            </small>

            <?php if (empty($popups)): ?>
                <div style="text-align:center; padding:3rem; background:var(--input-bg, #f8fafc); border-radius:12px; border:2px dashed var(--card-bd, #cbd5e1); color:var(--text-muted); margin-bottom:2rem;">
                    <span style="font-size:3rem;">✨</span>
                    <p style="font-weight:700; margin-top:1rem; margin-bottom:0.25rem;">Belum Ada Popup Promo</p>
                    <p style="font-size:0.85rem; margin-bottom:0;">Silakan buat popup promosi baru menggunakan form di bawah.</p>
                </div>
            <?php else: ?>
                <div class="cards-grid">
                    <?php foreach ($popups as $pop): ?>
                        <div class="promo-card">
                            <span class="card-badge <?= $pop['is_active'] ? 'active' : 'inactive' ?>">
                                <?= $pop['is_active'] ? 'Aktif' : 'Non-Aktif' ?>
                            </span>

                            <?php if (!empty($pop['image']) && file_exists(__DIR__ . '/' . $pop['image'])): ?>
                                <div class="card-img-preview" style="background-image: url('<?= htmlspecialchars($pop['image']) ?>');"></div>
                            <?php else: ?>
                                <div class="card-img-preview" style="background: linear-gradient(135deg, #f59e0b, #d97706);">No Image</div>
                            <?php endif; ?>

                            <div class="card-details">
                                <div class="card-title"><?= htmlspecialchars($pop['title']) ?></div>
                                <div class="card-desc"><?= nl2br(htmlspecialchars($pop['content'])) ?></div>

                                <?php if (!empty($pop['item_kode'])): ?>
                                    <div style="font-size:0.8rem; background:rgba(0,0,0,0.15); padding:0.6rem 0.8rem; border-radius:8px; margin-bottom:1rem; border-left:3px solid var(--shop-primary);">
                                        🎯 <strong>Barang Promo:</strong> <?= htmlspecialchars($pop['item_nama'] ?: 'Barang Tidak Ditemukan') ?><br>
                                        🏷️ <strong>Harga Asli:</strong> <?= rupiah($pop['harga_asli'] ?: 0) ?><br>
                                        🔥 <strong>Harga Promo:</strong> <span style="color:#ef4444; font-weight:bold;"><?= rupiah($pop['promo_price']) ?></span>
                                    </div>
                                <?php endif; ?>

                                <div class="card-actions">
                                    <!-- Toggle Status Button -->
                                    <form method="post" style="margin:0; flex-grow:1;">
                                        <input type="hidden" name="action" value="toggle_popup">
                                        <input type="hidden" name="id" value="<?= $pop['id'] ?>">
                                        <input type="hidden" name="status" value="<?= $pop['is_active'] ? 0 : 1 ?>">
                                        <button type="submit" class="card-btn secondary outline">
                                            <?= $pop['is_active'] ? '🔴 Nonaktifkan' : '🟢 Aktifkan' ?>
                                        </button>
                                    </form>

                                    <!-- Delete Button -->
                                    <form method="post" style="margin:0; flex-grow:1;" onsubmit="return confirm('Apakah Anda yakin ingin menghapus popup promo ini secara permanen?');">
                                        <input type="hidden" name="action" value="delete_popup">
                                        <input type="hidden" name="id" value="<?= $pop['id'] ?>">
                                        <button type="submit" class="card-btn danger outline">🗑️ Hapus</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Add New Popup Form -->
            <div class="form-section">
                <h5 style="margin-top:0; font-weight:800; border-bottom:1px solid var(--shop-border); padding-bottom:0.5rem; margin-bottom:1rem; color:var(--shop-primary);">➕ Tambah Popup Promo Baru</h5>
                
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_popup">
                    
                    <div class="form-grid">
                        <div>
                            <label for="popup_title">Judul Popup Promo</label>
                            <input type="text" name="title" id="popup_title" placeholder="Contoh: Flash Sale! Hemat 40% hari ini 🔥" required>

                            <label for="popup_content">Pesan Promosi</label>
                            <textarea name="content" id="popup_content" rows="3" placeholder="Tuliskan detail promo, syarat & ketentuan singkat..."></textarea>

                            <label for="popup_link">Link Tambahan (Opsional)</label>
                            <input type="text" name="link" id="popup_link" placeholder="Contoh: shop.php?kategori=Elektronik">
                        </div>
                        <div>
                            <label for="popup_item_kode">🎯 Barang yang Dipromosikan <span style="color:#ef4444;">*</span></label>
                            <select name="item_kode" id="popup_item_kode" required onchange="updatePromoHarga(this)">
                                <option value="">-- Pilih Barang --</option>
                                <?php foreach ($items_list as $it): ?>
                                <option value="<?= htmlspecialchars($it['kode']) ?>" 
                                        data-harga="<?= $it['harga_jual1'] ?>"
                                        data-nama="<?= htmlspecialchars($it['nama']) ?>">
                                    <?= htmlspecialchars($it['nama']) ?> (<?= rupiah($it['harga_jual1']) ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <small style="display:block; margin-top:-0.7rem; margin-bottom:1rem; color:var(--shop-text-muted);">Barang ini akan otomatis masuk keranjang saat promo diklaim.</small>

                            <label for="popup_promo_price">🔥 Harga Promo (Rp) <span style="color:#ef4444;">*</span></label>
                            <input type="number" name="promo_price" id="popup_promo_price" min="0" step="100" placeholder="Masukkan harga diskon..." required>
                            <div id="promo_price_hint" style="display:none; font-size:0.8rem; margin-top:-0.5rem; margin-bottom:1rem; padding:0.4rem 0.6rem; border-radius:6px; background:rgba(239,68,68,0.1); border:1px solid rgba(239,68,68,0.3);">
                                <span style="color:var(--shop-text-muted);">Harga asli:</span> <span id="harga_asli_display" style="font-weight:700;"></span>
                                <span style="color:var(--shop-text-muted); margin-left:0.5rem;">→ Hemat:</span> <span id="hemat_display" style="color:#10b981; font-weight:700;"></span>
                            </div>

                            <label for="popup_image">Visual Banner Popup (Opsional)</label>
                            <input type="file" name="image_file" id="popup_image" accept="image/*" onchange="previewFile(this, 'popup_preview_box')">
                            
                            <div id="popup_preview_box" style="margin-top:0.5rem; display:none;">
                                <small style="display:block; font-weight:600; margin-bottom:0.25rem;">Preview Gambar:</small>
                                <img id="popup_preview_img" src="#" alt="Preview" style="max-height:100px; border-radius:8px; border:1px solid var(--shop-border);">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" style="background:var(--shop-primary)!important; border:none!important; color:white!important; width:100%; margin-top:1rem;">🚀 Buat & Aktifkan Popup Promo</button>
                </form>
            </div>
        </div>
    </div>
</article>

<script>
function switchTab(tabName) {
    const tabBtnAd = document.getElementById("tabBtnAd");
    const tabBtnPopup = document.getElementById("tabBtnPopup");
    const tabContentAd = document.getElementById("tabContentAd");
    const tabContentPopup = document.getElementById("tabContentPopup");

    if (tabName === 'ad') {
        tabBtnAd.classList.add("active");
        tabBtnPopup.classList.remove("active");
        tabContentAd.classList.add("active");
        tabContentPopup.classList.remove("active");
        window.location.hash = 'ad';
    } else {
        tabBtnAd.classList.remove("active");
        tabBtnPopup.classList.add("active");
        tabContentAd.classList.remove("active");
        tabContentPopup.classList.add("active");
        window.location.hash = 'popup';
    }
}

// ===== PROMO PRICE SAVINGS CALCULATOR =====
let selectedItemHarga = 0;

function formatRupiah(num) {
    return 'Rp ' + Number(num).toLocaleString('id-ID');
}

function updatePromoHarga(sel) {
    const opt = sel.options[sel.selectedIndex];
    selectedItemHarga = parseInt(opt.getAttribute('data-harga') || '0');
    updateHint();
}

function updateHint() {
    const priceInput = document.getElementById('popup_promo_price');
    const hint = document.getElementById('promo_price_hint');
    const hargaAsliEl = document.getElementById('harga_asli_display');
    const hematEl = document.getElementById('hemat_display');

    if (selectedItemHarga > 0) {
        const promoPrice = parseInt(priceInput.value || '0');
        const hemat = selectedItemHarga - promoPrice;
        hargaAsliEl.textContent = formatRupiah(selectedItemHarga);
        hematEl.textContent = hemat > 0 ? formatRupiah(hemat) : '-';
        hint.style.display = 'block';
    } else {
        hint.style.display = 'none';
    }
}

document.addEventListener('DOMContentLoaded', function () {
    const hash = window.location.hash;
    if (hash === '#popup') {
        switchTab('popup');
    } else if (hash === '#ad') {
        switchTab('ad');
    }

    const priceInput = document.getElementById('popup_promo_price');
    if (priceInput) {
        priceInput.addEventListener('input', updateHint);
    }
});

// Image FileReader client-side preview helper
function previewFile(input, boxId) {
    const box = document.getElementById(boxId);
    const img = box.querySelector('img');
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            box.style.display = 'block';
        }
        reader.readAsDataURL(input.files[0]);
    } else {
        box.style.display = 'none';
    }
}
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
