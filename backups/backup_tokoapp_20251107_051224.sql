-- ------------------------------------------------------
--  Backup Database : tokoapp
--  Tanggal         : 2025-11-07 05:12:24
--  Host            : localhost
--  PHP             : 8.0.8
-- ------------------------------------------------------

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
SET AUTOCOMMIT = 0;
START TRANSACTION;
SET time_zone = "+00:00";

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Struktur tabel `item_stocks`
--

DROP TABLE IF EXISTS `item_stocks`;
CREATE TABLE `item_stocks` (
  `item_kode` varchar(64) NOT NULL,
  `location` varchar(32) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`item_kode`,`location`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `item_stocks` (total baris: 16)
--
INSERT INTO `item_stocks` (`item_kode`,`location`,`qty`) VALUES 
('002','gudang',40),
('002','toko',6),
(111,'gudang',100),
(111,'toko',40),
(11111,'gudang',0),
(11111,'toko',0),
(123,'gudang',0),
(123,'toko',-37),
(1234,'gudang',0),
(1234,'toko',-22),
(123456789,'gudang',0),
(123456789,'toko',0),
(5555,'gudang',303),
(5555,'toko',94),
(987,'gudang',0),
(987,'toko',-1);

--
-- Struktur tabel `items`
--

DROP TABLE IF EXISTS `items`;
CREATE TABLE `items` (
  `kode` varchar(64) NOT NULL,
  `barcode` varchar(64) DEFAULT NULL,
  `nama` varchar(160) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `unit_code` varchar(16) NOT NULL DEFAULT 'pcs',
  `harga_beli` bigint(20) NOT NULL DEFAULT 0,
  `harga_jual1` bigint(20) NOT NULL DEFAULT 0,
  `harga_jual2` bigint(20) NOT NULL DEFAULT 0,
  `harga_jual3` bigint(20) NOT NULL DEFAULT 0,
  `harga_jual4` bigint(20) NOT NULL DEFAULT 0,
  `min_stock` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`kode`),
  UNIQUE KEY `barcode` (`barcode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `items` (total baris: 12)
--
INSERT INTO `items` (`kode`,`barcode`,`nama`,`unit`,`unit_code`,`harga_beli`,`harga_jual1`,`harga_jual2`,`harga_jual3`,`harga_jual4`,`min_stock`,`created_at`,`updated_at`) VALUES 
('002','RS00001','akua',NULL,'pcs',1500,2500,2250,2000,1750,10,'2025-11-02 17:43:35','2025-11-02 17:43:35'),
(111,11121,'BIMOLI','pcs','pcs',8000,9500,9250,9000,8500,1,'2025-11-02 17:44:31','2025-11-02 17:44:31'),
(11111,123456789,1111,'pcs','pcs',1,1,1,1,1,1,'2025-11-03 19:57:53','2025-11-07 04:33:20'),
(123,12345,'BIMOLI 100 ML','pcs','pcs',1500,2500,2250,2000,1750,10,'2025-11-03 19:34:57',NULL),
(1234,123456,'nutisari','pcs','pcs',1000,2000,1750,1500,1250,10,'2025-11-03 19:36:47',NULL),
(12345,NULL,'BIMOLI 100 ML','pcs','pcs',1500,2500,2250,2000,1750,10,'2025-11-03 19:32:21',NULL),
(123456,654321,'kola kola','pcs','pcs',3,7,6,4,3,9,'2025-11-03 19:51:55',NULL),
(123456789,987456,'akua','pcs','pcs',2000,2000,2000,2000,2000,10,'2025-11-03 19:59:35','2025-11-07 04:37:20'),
(4321,98765,'mentos','pcs','pcs',1000,2000,1500,1250,1100,10,'2025-11-03 19:54:34',NULL),
(444,234,'ddd','kg','pcs',2,6,5,4,3,10,'2025-11-03 19:48:19',NULL),
(5555,5555,'akua',NULL,'dus',23000,25000,24500,24000,23499,4,'2025-11-07 02:11:32','2025-11-07 04:33:03'),
(987,1452,'fit','dus','pcs',20000,24000,23000,22000,21000,10,'2025-11-03 19:43:07',NULL);

--
-- Struktur tabel `locations`
--

DROP TABLE IF EXISTS `locations`;
CREATE TABLE `locations` (
  `code` varchar(32) NOT NULL,
  `name` varchar(64) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `locations` (total baris: 2)
--
INSERT INTO `locations` (`code`,`name`) VALUES 
('gudang','Gudang'),
('toko','Toko');

--
-- Struktur tabel `member_point_redemptions`
--

DROP TABLE IF EXISTS `member_point_redemptions`;
CREATE TABLE `member_point_redemptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `member_kode` varchar(50) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `description` text DEFAULT NULL,
  `redeemed_at` datetime NOT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `member_kode` (`member_kode`),
  KEY `redeemed_at` (`redeemed_at`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `member_point_redemptions` (total baris: 1)
--
INSERT INTO `member_point_redemptions` (`id`,`member_kode`,`qty`,`description`,`redeemed_at`,`created_by`,`created_at`) VALUES 
(1,123,10,'panci','2025-11-02 13:52:00','wahyono','2025-11-02 19:52:17');

--
-- Struktur tabel `members`
--

DROP TABLE IF EXISTS `members`;
CREATE TABLE `members` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode` varchar(64) NOT NULL,
  `nama` varchar(160) NOT NULL,
  `jenis` enum('umum','grosir') NOT NULL DEFAULT 'umum',
  `alamat` varchar(255) NOT NULL DEFAULT '',
  `tlp` varchar(64) NOT NULL DEFAULT '',
  `poin` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `telp` varchar(50) DEFAULT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `members` (total baris: 2)
--
INSERT INTO `members` (`id`,`kode`,`nama`,`jenis`,`alamat`,`tlp`,`poin`,`created_at`,`telp`,`points`) VALUES 
(2,'0001','Fathir','umum','Gumelar','',0,'2025-11-02 20:38:21','085875099445',47),
(3,'002','amir','grosir','jl jalan','',0,'2025-11-07 02:30:43','058575099445',10);

--
-- Struktur tabel `purchase_items`
--

DROP TABLE IF EXISTS `purchase_items`;
CREATE TABLE `purchase_items` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) NOT NULL,
  `item_kode` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `unit` varchar(20) DEFAULT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `harga_beli` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=10 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `purchase_items` (total baris: 6)
--
INSERT INTO `purchase_items` (`id`,`purchase_id`,`item_kode`,`nama`,`unit`,`qty`,`harga_beli`) VALUES 
(4,13,'002','akua','pcs',10,2000),
(5,14,'002','akua','pcs',500,2000),
(6,15,5555,'akua','pcs',4,23000),
(7,16,5555,'akua','pcs',3,0),
(8,17,5555,'akua','pcs',500,22000),
(9,18,111,'BIMOLI','pcs',100,20000);

--
-- Struktur tabel `purchases`
--

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) DEFAULT NULL,
  `supplier_kode` varchar(64) DEFAULT NULL,
  `location` varchar(32) NOT NULL DEFAULT 'gudang',
  `subtotal` bigint(20) NOT NULL DEFAULT 0,
  `discount` bigint(20) NOT NULL DEFAULT 0,
  `tax` bigint(20) NOT NULL DEFAULT 0,
  `total` bigint(20) NOT NULL DEFAULT 0,
  `created_by` varchar(64) NOT NULL,
  `status` enum('OK','VOID') NOT NULL DEFAULT 'OK',
  `void_reason` varchar(255) DEFAULT NULL,
  `void_by` varchar(64) DEFAULT NULL,
  `void_at` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=19 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `purchases` (total baris: 7)
--
INSERT INTO `purchases` (`id`,`invoice_no`,`supplier_kode`,`location`,`subtotal`,`discount`,`tax`,`total`,`created_by`,`status`,`void_reason`,`void_by`,`void_at`,`created_at`) VALUES 
(2,NULL,NULL,'gudang',150000,0,0,150000,'admin','OK',NULL,NULL,NULL,'2025-11-02 17:46:38'),
(13,'nav1111','002','gudang',20000,0,0,20000,'wahyono','OK',NULL,NULL,NULL,'2025-11-02 19:33:11'),
(14,'00002','002','toko',1000000,0,0,1000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-02 20:31:08'),
(15,'0001','002','toko',92000,9200,9108,91908,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 02:13:06'),
(16,33333,'002','gudang',0,0,0,0,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 03:18:14'),
(17,111111,'002','gudang',11000000,0,0,11000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 04:21:44'),
(18,321456,'002','gudang',2000000,0,0,2000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 04:22:46');

--
-- Struktur tabel `purchases_audit`
--

DROP TABLE IF EXISTS `purchases_audit`;
CREATE TABLE `purchases_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` int(11) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `info` text DEFAULT NULL,
  `actor` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur tabel `sale_items`
--

DROP TABLE IF EXISTS `sale_items`;
CREATE TABLE `sale_items` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `sale_id` int(10) unsigned NOT NULL,
  `item_kode` varchar(50) NOT NULL,
  `nama` varchar(200) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `harga` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_item_kode` (`item_kode`)
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `sale_items` (total baris: 36)
--
INSERT INTO `sale_items` (`id`,`sale_id`,`item_kode`,`nama`,`qty`,`harga`,`total`) VALUES 
(1,1,'002','akua',1,2500,2500),
(2,2,'002','akua',100,1750,175000),
(3,3,'002','akua',10,2000,20000),
(4,4,'002','akua',10,1750,17500),
(5,5,'002','akua',100,2000,200000),
(6,6,'002','akua',2,2000,4000),
(7,7,111,'BIMOLI',2,9250,18500),
(8,7,'002','akua',3,2250,6750),
(9,8,'002','akua',20,2250,45000),
(10,9,123,'BIMOLI 100 ML',20,2500,50000),
(11,10,1234,'nutisari',10,2000,20000),
(12,11,1234,'nutisari',10,2000,20000),
(13,12,5555,'akua',4,23500,94000),
(14,12,111,'BIMOLI',1,8500,8500),
(15,12,1234,'nutisari',2,1250,2500),
(16,12,987,'fit',1,21000,21000),
(17,13,5555,'akua',3,25000,75000),
(18,14,5555,'akua',3,25000,75000),
(19,15,5555,'akua',2,25000,50000),
(20,16,5555,'akua',1,24000,24000),
(21,17,5555,'akua',1,25000,25000),
(22,17,123,'BIMOLI 100 ML',12,2500,30000),
(23,18,5555,'akua',2,25000,50000),
(24,18,123,'BIMOLI 100 ML',2,2500,5000),
(29,20,5555,'akua',1,24000,24000),
(30,20,123,'BIMOLI 100 ML',1,2000,2000),
(31,21,5555,'akua',1,24000,24000),
(32,22,5555,'akua',20,25000,500000),
(33,23,123,'BIMOLI 100 ML',2,2500,5000),
(34,24,5555,'akua',2,24000,48000),
(35,25,5555,'akua',2,25000,50000),
(36,26,5555,'akua',2,23500,47000),
(37,27,5555,'akua',50,23500,1175000),
(38,28,5555,'akua',10,25000,250000),
(39,29,5555,'akua',5,24000,120000),
(40,30,5555,'akua',1,25000,25000);

--
-- Struktur tabel `sales`
--

DROP TABLE IF EXISTS `sales`;
CREATE TABLE `sales` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) DEFAULT NULL,
  `member_kode` varchar(50) DEFAULT NULL,
  `shift` varchar(10) DEFAULT NULL,
  `subtotal` int(11) NOT NULL DEFAULT 0,
  `discount` int(11) NOT NULL DEFAULT 0,
  `tax` int(11) NOT NULL DEFAULT 0,
  `total` int(11) NOT NULL DEFAULT 0,
  `tunai` int(11) NOT NULL DEFAULT 0,
  `kembalian` int(11) NOT NULL DEFAULT 0,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime DEFAULT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'OK',
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_invoice_no` (`invoice_no`),
  KEY `idx_member` (`member_kode`)
) ENGINE=InnoDB AUTO_INCREMENT=31 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `sales` (total baris: 29)
--
INSERT INTO `sales` (`id`,`invoice_no`,`member_kode`,`shift`,`subtotal`,`discount`,`tax`,`total`,`tunai`,`kembalian`,`created_by`,`created_at`,`status`) VALUES 
(1,'PEL/20251102134355',NULL,1,2500,0,0,2500,2500,0,'wahyono','2025-11-02 19:43:55','RETURNED'),
(2,'PEL/20251102135149',123,2,175000,0,0,175000,200000,25000,'wahyono','2025-11-02 19:51:49','OK'),
(3,'PEL/20251102135650',123,2,20000,0,0,20000,50000,30000,'wahyono','2025-11-02 19:56:50','OK'),
(4,'PEL/20251102143912','0001',2,17500,0,0,17500,20000,2500,'wahyono','2025-11-02 20:39:12','OK'),
(5,'S20251102144257','0001',2,200000,0,0,200000,250000,50000,'wahyono','2025-11-02 20:42:57','OK'),
(6,'S20251102150411','0001',2,4000,0,0,4000,5000,1000,'ali','2025-11-02 21:04:11','OK'),
(7,'S20251102151012','0001',2,25250,0,0,25250,30000,4750,'ali','2025-11-02 21:10:12','OK'),
(8,'S20251102152637',123,2,45000,0,0,45000,50000,5000,'wahyono','2025-11-02 21:26:37','OK'),
(9,'S20251106042536','0001',1,50000,0,0,50000,0,0,'wahyono','2025-11-06 10:25:36','OK'),
(10,'S20251106042640',123,1,20000,0,0,20000,0,0,'wahyono','2025-11-06 10:26:40','OK'),
(11,'S20251106042725',123,1,20000,5000,0,15000,0,0,'wahyono','2025-11-06 10:27:25','OK'),
(12,'S20251106201505','0001',2,126000,0,0,126000,150000,24000,'wahyono','2025-11-07 02:15:05','OK'),
(13,'S20251106205322','002',1,75000,0,0,75000,0,0,'wahyono','2025-11-07 02:53:22','OK'),
(14,'PEL20251106205832','002',1,75000,0,0,75000,100000,25000,'wahyono','2025-11-07 02:58:32','OK'),
(15,'PEL20251106205931','0001',2,50000,0,0,50000,50000,0,'wahyono','2025-11-07 02:59:31','OK'),
(16,'PEL20251106212443',NULL,1,24000,0,0,24000,25000,1000,'wahyono','2025-11-07 03:24:43','OK'),
(17,'PEL20251106212717',NULL,1,55000,0,0,55000,100000,45000,'wahyono','2025-11-07 03:27:17','OK'),
(18,'PEL20251106212829','002',2,55000,0,0,55000,100000,45000,'wahyono','2025-11-07 03:28:29','OK'),
(20,'S20251106213620','002',2,26000,0,0,26000,50000,24000,'wahyono','2025-11-07 03:36:20','OK'),
(21,'S20251106213939','0001',2,24000,0,0,24000,50000,26000,'wahyono','2025-11-07 03:39:39','OK'),
(22,'S20251106214451','002',1,500000,10000,11000,501000,550000,49000,'wahyono','2025-11-07 03:44:51','OK'),
(23,'S20251106214859',NULL,1,5000,10,0,4990,10000,5010,'wahyono','2025-11-07 03:48:59','OK'),
(24,'S20251106214931','0001',2,48000,0,0,48000,50000,2000,'wahyono','2025-11-07 03:49:31','OK'),
(25,'S20251106215958',NULL,1,50000,5000,4950,49950,50000,50,'wahyono','2025-11-07 03:59:58','OK'),
(26,'S20251106220126','0001',1,47000,4700,4653,46953,50000,3047,'wahyono','2025-11-07 04:01:26','OK'),
(27,'S20251106220349','002',2,1175000,5000,128700,1298700,1300000,1300,'wahyono','2025-11-07 04:03:49','OK'),
(28,'S20251106220538','0001',1,250000,0,0,250000,300000,50000,'wahyono','2025-11-07 04:05:38','OK'),
(29,'S20251106220846','002',2,120000,0,0,120000,120000,0,'wahyono','2025-11-07 04:08:46','OK'),
(30,'S20251106221014','0001',1,25000,0,0,25000,50000,25000,'wahyono','2025-11-07 04:10:14','OK');

--
-- Struktur tabel `sales_audit`
--

DROP TABLE IF EXISTS `sales_audit`;
CREATE TABLE `sales_audit` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sale_id` int(11) DEFAULT NULL,
  `action` varchar(32) NOT NULL,
  `info` text DEFAULT NULL,
  `actor` varchar(64) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Struktur tabel `settings`
--

DROP TABLE IF EXISTS `settings`;
CREATE TABLE `settings` (
  `id` int(11) NOT NULL,
  `store_name` varchar(120) NOT NULL DEFAULT 'TOKO',
  `store_address` varchar(255) NOT NULL DEFAULT '',
  `store_phone` varchar(50) NOT NULL DEFAULT '',
  `footer_note` varchar(255) NOT NULL DEFAULT 'Terima kasih telah berbelanja.',
  `logo_url` varchar(255) NOT NULL DEFAULT '',
  `invoice_prefix` varchar(32) NOT NULL DEFAULT 'INV/',
  `invoice_counter_date` date DEFAULT NULL,
  `invoice_counter` int(11) NOT NULL DEFAULT 0,
  `points_per_rupiah` int(11) NOT NULL DEFAULT 0,
  `points_per_rupiah_umum` int(11) NOT NULL DEFAULT 0,
  `points_per_rupiah_grosir` int(11) NOT NULL DEFAULT 0,
  `rupiah_per_point_umum` int(11) NOT NULL DEFAULT 0,
  `rupiah_per_point_grosir` int(11) NOT NULL DEFAULT 0,
  `qr_provider_url` varchar(255) NOT NULL DEFAULT 'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `settings` (total baris: 1)
--
INSERT INTO `settings` (`id`,`store_name`,`store_address`,`store_phone`,`footer_note`,`logo_url`,`invoice_prefix`,`invoice_counter_date`,`invoice_counter`,`points_per_rupiah`,`points_per_rupiah_umum`,`points_per_rupiah_grosir`,`rupiah_per_point_umum`,`rupiah_per_point_grosir`,`qr_provider_url`) VALUES 
(1,'PELANGI MART','Cinurug Rt 06 Rw 06, Gumelar, Kec. Gumelar, Kabupaten Banyumas, Jawa Tengah 53165','085875099445','Terima kasih telah berbelanja.','/tokoapp/uploads/logo_20251102_125050.jpg','PEL/','2025-11-02',10,0,0,0,10000,25000,'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=');

--
-- Struktur tabel `suppliers`
--

DROP TABLE IF EXISTS `suppliers`;
CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `kode` varchar(64) NOT NULL,
  `nama` varchar(160) NOT NULL,
  `alamat` varchar(255) NOT NULL DEFAULT '',
  `tlp` varchar(64) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `suppliers` (total baris: 1)
--
INSERT INTO `suppliers` (`id`,`kode`,`nama`,`alamat`,`tlp`,`created_at`,`updated_at`) VALUES 
(1,'002','UNILEVER','Jl. Gatot Subroto No.95, Wanasari, Sidanegara, Kec. Cilacap Tengah, Kabupaten Cilacap, Jawa Tengah 53212','085875099445','2025-11-02 17:45:00',NULL);

--
-- Struktur tabel `units`
--

DROP TABLE IF EXISTS `units`;
CREATE TABLE `units` (
  `code` varchar(16) NOT NULL,
  `name` varchar(32) NOT NULL,
  PRIMARY KEY (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `units` (total baris: 6)
--
INSERT INTO `units` (`code`,`name`) VALUES 
('dus','Dus'),
('gr','Gram'),
('kg','Kilogram'),
('liter','Liter'),
('ml','Mililiter'),
('pcs','Pieces');

--
-- Struktur tabel `users`
--

DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(50) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `role` enum('admin','kasir') NOT NULL DEFAULT 'kasir',
  `is_active` tinyint(4) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `users` (total baris: 3)
--
INSERT INTO `users` (`id`,`username`,`password_hash`,`role`,`is_active`,`created_at`) VALUES 
(1,'admin','$2y$10$TwGS5GnYAfU3RVdB4o9eFunG9Y2zLLqUdPoxiz.EAY0MsFA0NZrhq','admin',0,'2025-11-02 17:30:04'),
(2,'wahyono','$2y$10$ytfeT8Hj3ypDRh4x1zc5TeuZC2xLn6K1rCqf7Mucob75IEpEUcfbe','admin',1,'2025-11-02 18:36:05'),
(3,'ali','$2y$10$c7dfyFjHsXDaEyLYJANtNOscM7.WkYCdy9IBNiuMYxdNNZUVwsfXi','kasir',1,'2025-11-02 18:42:07');


COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
