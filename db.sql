-- Create database (optional): CREATE DATABASE tokoapp CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
-- USE tokoapp;

-- Users (admin, kasir). Shift 1/2 recorded per sale, not per user.
CREATE TABLE IF NOT EXISTS users (
  id INT AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(50) UNIQUE NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  role ENUM('admin','kasir') NOT NULL DEFAULT 'kasir',
  permissions TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Settings (e.g., point rate: berapa Rupiah per 1 poin)
CREATE TABLE IF NOT EXISTS settings (
  id INT PRIMARY KEY,
  store_name VARCHAR(100),
  store_address TEXT,
  store_phone VARCHAR(20),
  theme VARCHAR(20) DEFAULT 'dark',
  points_per_rupiah DECIMAL(18,8) NOT NULL DEFAULT 0.0001
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO settings (id, points_per_rupiah) VALUES (1, 0.0001) ON DUPLICATE KEY UPDATE points_per_rupiah=values(points_per_rupiah);

-- Master barang
CREATE TABLE IF NOT EXISTS items (
  kode VARCHAR(50) PRIMARY KEY,
  barcode VARCHAR(50) UNIQUE,
  nama VARCHAR(255) NOT NULL,
  harga_beli BIGINT NOT NULL DEFAULT 0,
  harga_jual1 BIGINT NOT NULL DEFAULT 0,
  harga_jual2 BIGINT NOT NULL DEFAULT 0,
  harga_jual3 BIGINT NOT NULL DEFAULT 0,
  harga_jual4 BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Master member (pelanggan)
CREATE TABLE IF NOT EXISTS members (
  kode VARCHAR(50) PRIMARY KEY,
  nama VARCHAR(255) NOT NULL,
  alamat TEXT,
  tlp VARCHAR(50),
  poin BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Master supplier
CREATE TABLE IF NOT EXISTS suppliers (
  id INT AUTO_INCREMENT PRIMARY KEY,
  kode VARCHAR(50) UNIQUE,
  nama VARCHAR(255) NOT NULL,
  alamat TEXT,
  tlp VARCHAR(50),
  hutang_awal BIGINT DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Penjualan (header)
CREATE TABLE IF NOT EXISTS sales (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  member_kode VARCHAR(50) NULL,
  subtotal BIGINT NOT NULL DEFAULT 0,
  total BIGINT NOT NULL DEFAULT 0,
  tunai BIGINT NOT NULL DEFAULT 0,
  kembalian BIGINT NOT NULL DEFAULT 0,
  poin_didapat BIGINT NOT NULL DEFAULT 0,
  shift ENUM('1','2') NOT NULL DEFAULT '1',
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (member_kode) REFERENCES members(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Detail penjualan
CREATE TABLE IF NOT EXISTS sale_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  sale_id BIGINT NOT NULL,
  item_kode VARCHAR(50) NOT NULL,
  nama_item VARCHAR(255) NOT NULL,
  qty INT NOT NULL,
  level_harga TINYINT NOT NULL,
  harga_satuan BIGINT NOT NULL,
  total BIGINT NOT NULL,
  FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
  FOREIGN KEY (item_kode) REFERENCES items(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin user: username=admin, password=admin123 (PLEASE change later)
INSERT INTO users (username, password_hash, role) VALUES
('admin', '$2y$10$FJrjv2n3H6m8Nq8tq2VNVuW3z8a8z6lR1g5aYf10N8sE6YcBf0bWy', 'admin')
ON DUPLICATE KEY UPDATE username=username;
-- The hash corresponds to bcrypt('admin123')
-- ========== INVENTORY & LOCATIONS ==========
CREATE TABLE IF NOT EXISTS item_stocks (
  item_kode VARCHAR(50) NOT NULL,
  location ENUM('gudang','toko') NOT NULL,
  qty INT NOT NULL DEFAULT 0,
  PRIMARY KEY (item_kode, location),
  FOREIGN KEY (item_kode) REFERENCES items(kode) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Initialize stocks for existing items (0 if missing). Run after items exist.
INSERT INTO item_stocks (item_kode, location, qty)
  SELECT i.kode, 'gudang', 0 FROM items i
  ON DUPLICATE KEY UPDATE qty = qty;
INSERT INTO item_stocks (item_kode, location, qty)
  SELECT i.kode, 'toko', 0 FROM items i
  ON DUPLICATE KEY UPDATE qty = qty;

-- ========== PURCHASES (TRANSAKSI PEMBELIAN) ==========
CREATE TABLE IF NOT EXISTS purchases (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  supplier_id INT NULL,
  supplier_kode VARCHAR(50),
  location ENUM('gudang','toko') NOT NULL DEFAULT 'gudang',
  purchase_date DATE,
  invoice_no VARCHAR(50),
  subtotal BIGINT NOT NULL DEFAULT 0,
  discount BIGINT NOT NULL DEFAULT 0,
  tax BIGINT NOT NULL DEFAULT 0,
  total BIGINT NOT NULL DEFAULT 0,
  bayar BIGINT DEFAULT 0,
  sisa BIGINT DEFAULT 0,
  status_lunas TINYINT(1) DEFAULT 1,
  note VARCHAR(255),
  created_by VARCHAR(50),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (supplier_id) REFERENCES suppliers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS purchase_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT NOT NULL,
  item_kode VARCHAR(50) NOT NULL,
  nama_item VARCHAR(255) NOT NULL,
  qty INT NOT NULL,
  harga_beli BIGINT NOT NULL,
  total BIGINT NOT NULL DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE,
  FOREIGN KEY (item_kode) REFERENCES items(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== STOCK TRANSFER (MUTASI) ==========
CREATE TABLE IF NOT EXISTS stock_transfers (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  user_id INT NOT NULL,
  from_location ENUM('gudang','toko') NOT NULL,
  to_location ENUM('gudang','toko') NOT NULL,
  note VARCHAR(255),
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS stock_transfer_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  transfer_id BIGINT NOT NULL,
  item_kode VARCHAR(50) NOT NULL,
  qty INT NOT NULL,
  FOREIGN KEY (transfer_id) REFERENCES stock_transfers(id) ON DELETE CASCADE,
  FOREIGN KEY (item_kode) REFERENCES items(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
-- ========== MINIMUM STOCK ==========
ALTER TABLE items ADD COLUMN IF NOT EXISTS min_stock INT NOT NULL DEFAULT 0;

-- ========== DISCOUNT & TAX ON SALES ==========
ALTER TABLE sales
  ADD COLUMN IF NOT EXISTS discount BIGINT NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS tax BIGINT NOT NULL DEFAULT 0;

-- ========== SALES RETURN (RETUR PENJUALAN) ==========
CREATE TABLE IF NOT EXISTS sales_returns (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  sale_id BIGINT NOT NULL,
  user_id INT NOT NULL,
  member_kode VARCHAR(50) NULL,
  total_refund BIGINT NOT NULL DEFAULT 0,
  FOREIGN KEY (sale_id) REFERENCES sales(id),
  FOREIGN KEY (user_id) REFERENCES users(id),
  FOREIGN KEY (member_kode) REFERENCES members(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS sales_return_items (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  return_id BIGINT NOT NULL,
  item_kode VARCHAR(50) NOT NULL,
  qty INT NOT NULL,
  harga_satuan BIGINT NOT NULL,
  total BIGINT NOT NULL,
  FOREIGN KEY (return_id) REFERENCES sales_returns(id) ON DELETE CASCADE,
  FOREIGN KEY (item_kode) REFERENCES items(kode)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


CREATE TABLE IF NOT EXISTS held_transactions (
  id INT AUTO_INCREMENT PRIMARY KEY,
  slot TINYINT NOT NULL,
  user_id INT NULL,
  state_json LONGTEXT NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_slot (slot),
  KEY idx_user_slot (user_id, slot)
);

-- ========== AUDIT LOGS ==========
CREATE TABLE IF NOT EXISTS audit_logs (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  username VARCHAR(50) NOT NULL,
  action VARCHAR(50) NOT NULL,
  description TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== UPDATED STOCK MUTATIONS LOG ==========
CREATE TABLE IF NOT EXISTS stock_mutations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  item_kode VARCHAR(64) NOT NULL,
  from_loc  VARCHAR(32) NOT NULL,
  to_loc    VARCHAR(32) NOT NULL,
  qty       INT NOT NULL DEFAULT 0,
  created_by VARCHAR(64) DEFAULT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX (item_kode),
  INDEX (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== MODULES ==========
CREATE TABLE IF NOT EXISTS modules (
    id INT AUTO_INCREMENT PRIMARY KEY,
    module_code VARCHAR(50) UNIQUE,
    module_name VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1
);

-- ========== SUPPLIER PAYMENTS ==========
CREATE TABLE IF NOT EXISTS supplier_payments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tanggal DATETIME DEFAULT CURRENT_TIMESTAMP,
    supplier_kode VARCHAR(50) NOT NULL,
    purchase_id BIGINT DEFAULT NULL,
    jumlah BIGINT NOT NULL DEFAULT 0,
    metode VARCHAR(50) DEFAULT 'Tunai',
    keterangan TEXT,
    created_by VARCHAR(50),
    INDEX (purchase_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== CASH LEDGER ==========
CREATE TABLE IF NOT EXISTS cash_ledger (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  tanggal DATE NOT NULL,
  shift TINYINT NULL,
  user_id INT NULL,
  direction ENUM('IN','OUT') NOT NULL,
  type VARCHAR(50) NOT NULL,
  amount BIGINT NOT NULL DEFAULT 0,
  note VARCHAR(255) NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== SALES AR (PIUTANG SALES) ==========
CREATE TABLE IF NOT EXISTS sales_ar (
  id INT AUTO_INCREMENT PRIMARY KEY,
  purchase_id BIGINT NOT NULL,
  supplier_kode VARCHAR(50),
  amount BIGINT NOT NULL DEFAULT 0,
  due_date DATE,
  status ENUM('OPEN','PARTIAL','PAID') DEFAULT 'OPEN',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (purchase_id) REFERENCES purchases(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ========== AR PAYMENTS (CICILAN PIUTANG) ==========
CREATE TABLE IF NOT EXISTS ar_payments (
  id INT AUTO_INCREMENT PRIMARY KEY,
  ar_id INT NOT NULL,
  pay_date DATE NOT NULL,
  method VARCHAR(50),
  amount BIGINT NOT NULL,
  note VARCHAR(255),
  user_id INT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (ar_id) REFERENCES sales_ar(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
