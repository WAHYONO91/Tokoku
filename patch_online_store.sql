-- ==============================================================================
-- UPDATE DATABASE UNTUK FITUR TOKO ONLINE & MEMBER
-- Tanggal: 7 Maret 2026
-- ==============================================================================

-- 1. Tambahan Kolom Kategori & Gambar di tabel items
ALTER TABLE items 
    ADD COLUMN IF NOT EXISTS kategori VARCHAR(100) NULL AFTER barcode,
    ADD COLUMN IF NOT EXISTS gambar VARCHAR(255) NULL AFTER kategori;

-- 2. Tambahan Password Hash & Poin di tabel members
ALTER TABLE members 
    ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255) NULL AFTER email,
    ADD COLUMN IF NOT EXISTS poin INT NOT NULL DEFAULT 0 AFTER password_hash;

-- 3. Tabel Carts (Jika tidak pakai session, tapi saat ini sistem pakai session)
-- Tapi kalau perlu bisa disiapkan saja.

-- 4. Tabel Pesanan Online (online_orders)
CREATE TABLE IF NOT EXISTS online_orders (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    member_kode VARCHAR(50) NULL,
    guest_name VARCHAR(100) NOT NULL,
    guest_phone VARCHAR(50) NOT NULL,
    guest_address TEXT NOT NULL,
    subtotal BIGINT NOT NULL DEFAULT 0,
    total BIGINT NOT NULL DEFAULT 0,
    payment_method VARCHAR(50) NOT NULL,
    payment_status ENUM('UNPAID', 'PAID') NOT NULL DEFAULT 'UNPAID',
    status ENUM('PENDING', 'PROCESSED', 'SENT', 'CANCELLED') NOT NULL DEFAULT 'PENDING',
    note TEXT NULL,
    lat_lng VARCHAR(100) NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 5. Tabel Item Pesanan Online (online_order_items)
CREATE TABLE IF NOT EXISTS online_order_items (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT NOT NULL,
    item_kode VARCHAR(50) NOT NULL,
    nama_item VARCHAR(255) NOT NULL,
    qty INT NOT NULL,
    harga_satuan BIGINT NOT NULL,
    total BIGINT NOT NULL,
    FOREIGN KEY (order_id) REFERENCES online_orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- 6. Tambahan QRIS URL di pengaturan toko
ALTER TABLE settings 
    ADD COLUMN IF NOT EXISTS qris_url VARCHAR(255) NULL AFTER logo_url;

-- 7. Tambahan Modul Pesanan Online ke akses hak cipta (kalau sistem butuh reset)
INSERT IGNORE INTO modules (module_code, module_name, is_active) 
VALUES ('ONLINE_ORDERS', 'Pesanan Online', 1);
-- 8. Tambahan Kolom lat_lng di online_orders
ALTER TABLE online_orders
    ADD COLUMN IF NOT EXISTS lat_lng VARCHAR(100) NULL AFTER note;
