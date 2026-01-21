-- ------------------------------------------------------
--  Backup Database : tokoapp
--  Tanggal         : 2025-11-16 05:36:13
--  Host            : singbanter.my.id
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
-- Struktur tabel `cash_ledger`
--

DROP TABLE IF EXISTS `cash_ledger`;
CREATE TABLE `cash_ledger` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  `tanggal` date NOT NULL,
  `shift` tinyint(4) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `direction` enum('IN','OUT') NOT NULL,
  `type` enum('OPENING','MANUAL_IN','MANUAL_OUT','ADJUST') NOT NULL,
  `amount` int(11) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `cash_ledger` (total baris: 5)
--
INSERT INTO `cash_ledger` (`id`,`created_at`,`tanggal`,`shift`,`user_id`,`direction`,`type`,`amount`,`note`) VALUES 
(1,'2025-11-08 08:18:28','2025-11-08',1,2,'IN','OPENING',500000,'modal pagi'),
(2,'2025-11-08 08:20:31','2025-11-08',1,2,'IN','MANUAL_IN',200000,'tabahan'),
(3,'2025-11-08 08:57:15','2025-11-08',1,2,'OUT','MANUAL_OUT',2000,'bayar sales'),
(4,'2025-11-08 10:53:09','2025-11-08',2,2,'OUT','MANUAL_OUT',500000,'Pengeluaran kas'),
(5,'2025-11-14 09:08:32','2025-11-14',1,2,'IN','OPENING',500000,'Modal pagi');

--
-- Struktur tabel `cashier_cash`
--

DROP TABLE IF EXISTS `cashier_cash`;
CREATE TABLE `cashier_cash` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` enum('IN','OUT') NOT NULL,
  `amount` bigint(20) NOT NULL DEFAULT 0,
  `note` varchar(255) DEFAULT NULL,
  `ref_type` varchar(50) DEFAULT NULL,
  `ref_id` int(11) DEFAULT NULL,
  `created_by` varchar(50) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`created_at`),
  KEY `idx_type` (`type`),
  KEY `idx_ref` (`ref_type`,`ref_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
-- Dumping data untuk tabel `item_stocks` (total baris: 24)
--
INSERT INTO `item_stocks` (`item_kode`,`location`,`qty`) VALUES 
('00124','gudang',0),
('00124','toko',0),
('002','gudang',30),
('002','toko',116),
(111,'gudang',101),
(111,'toko',26),
(11111,'gudang',115),
(11111,'toko',-5),
(123,'gudang',7),
(123,'toko',-38),
(1234,'gudang',4),
(1234,'toko',2),
(123456,'gudang',2),
(123456,'toko',-2),
(123456789,'gudang',2),
(123456789,'toko',-1),
(5555,'gudang',205),
(5555,'toko',127),
(987,'gudang',90),
(987,'toko',-2),
('bariot','gudang',10),
('bariot','toko',0),
('brang','gudang',0),
('brang','toko',0);

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
-- Dumping data untuk tabel `items` (total baris: 11)
--
INSERT INTO `items` (`kode`,`barcode`,`nama`,`unit`,`unit_code`,`harga_beli`,`harga_jual1`,`harga_jual2`,`harga_jual3`,`harga_jual4`,`min_stock`,`created_at`,`updated_at`) VALUES 
('002','RS00001','akua',NULL,'pcs',1500,2500,2250,2000,1750,10,'2025-11-03 00:43:35','2025-11-03 00:43:35'),
(111,11121,'BIMOLI','pcs','pcs',8000,9500,9250,9000,8500,1,'2025-11-03 00:44:31','2025-11-03 00:44:31'),
(11111,123456789,'le mineral','pcs','gal',18500,21000,20500,20000,19500,1,'2025-11-04 02:57:53','2025-11-15 07:11:57'),
(123,12345,'BIMOLI 100 ML','pcs','pcs',5000,6250,6000,5750,5500,10,'2025-11-04 02:34:57',NULL),
(1234,123456,'nutisari','pcs','pcs',5000,6250,6000,5750,5500,10,'2025-11-04 02:36:47',NULL),
(123456,654321,'kola kola','pcs','pcs',5000,6250,6000,5750,5500,9,'2025-11-04 02:51:55',NULL),
(123456789,987456,'Aku mau beli roti celup sama bando ini ya tan','pcs','pcs',10000,12500,12000,11500,11000,0,'2025-11-04 02:59:35','2025-11-15 07:23:45'),
(4321,98765,'mentos','pcs','pcs',1000,2000,1500,1250,1100,10,'2025-11-04 02:54:34',NULL),
(5555,5555,'akua',NULL,'dus',26000,32500,31200,29900,28600,4,'2025-11-07 09:11:32','2025-11-07 11:33:03'),
(987,1452,'fit','dus','pcs',20000,24000,23000,22000,21000,10,'2025-11-04 02:43:07',NULL),
('bariot',654789,'nanas',NULL,'pcs',2000,2500,2400,2300,2200,0,'2025-11-14 19:00:07','2025-11-14 19:00:07');

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
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `member_point_redemptions` (total baris: 3)
--
INSERT INTO `member_point_redemptions` (`id`,`member_kode`,`qty`,`description`,`redeemed_at`,`created_by`,`created_at`) VALUES 
(1,123,10,'panci','2025-11-02 13:52:00','wahyono','2025-11-02 19:52:17'),
(2,'0023',20,'gula 2 kg','2025-11-08 11:37:00','wahyono','2025-11-08 11:37:59'),
(3,1234,1,'aa','2025-11-13 20:44:00','wahyono','2025-11-13 20:44:48');

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
) ENGINE=InnoDB AUTO_INCREMENT=6 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `members` (total baris: 4)
--
INSERT INTO `members` (`id`,`kode`,`nama`,`jenis`,`alamat`,`tlp`,`poin`,`created_at`,`telp`,`points`) VALUES 
(2,'0001','Fathir','umum','Gumelar','',0,'2025-11-03 03:38:21','085875099445',220),
(3,'002','amir','grosir','jl jalan','',0,'2025-11-07 09:30:43','058575099445',915),
(4,'0023','WAHYONO','umum','jlnnnnn','',0,'2025-11-07 12:23:26','058575099445',64),
(5,1234,'santi','umum','Majingklak, Gumelar, Kec. Gumelar, Kabupaten Banyumas, Jawa Tengah 53165','',0,'2025-11-13 19:53:21','085875099445',6);

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
) ENGINE=InnoDB AUTO_INCREMENT=28 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `purchase_items` (total baris: 24)
--
INSERT INTO `purchase_items` (`id`,`purchase_id`,`item_kode`,`nama`,`unit`,`qty`,`harga_beli`) VALUES 
(4,13,'002','akua','pcs',10,2000),
(5,14,'002','akua','pcs',500,2000),
(6,15,5555,'akua','pcs',4,23000),
(7,16,5555,'akua','pcs',3,0),
(8,17,5555,'akua','pcs',500,22000),
(9,18,111,'BIMOLI','pcs',100,20000),
(10,19,111,'BIMOLI','pcs',1,2000),
(11,20,5555,'akua','pcs',1,20000),
(12,21,123,'BIMOLI 100 ML','pcs',50,20000),
(13,22,987,'fit','pcs',100,20000),
(14,23,11111,1111,'pcs',10,10000),
(15,28,11111,1111,'pcs',10,11000),
(16,29,1234,'nutisari','pcs',10,1500),
(17,30,1234,'nutisari','pcs',10,1500),
(18,31,1234,'nutisari','pcs',10,1500),
(19,32,123,'BIMOLI 100 ML','pcs',1,5000),
(20,33,5555,'akua','dus',1,26000),
(21,34,11111,1111,'pcs',200,12500),
(22,35,1234,'nutisari','pcs',4,5000),
(23,36,123456,'kola kola','pcs',1,5000),
(24,37,123456,'kola kola','pcs',1,5000),
(25,38,'bariot','nanas','pcs',10,2000),
(26,39,123456789,'Aku mau beli roti celup sama bando ini ya tan','pcs',1,10000),
(27,40,123456789,'Aku mau beli roti celup sama bando ini ya tan','pcs',1,10000);

--
-- Struktur tabel `purchases`
--

DROP TABLE IF EXISTS `purchases`;
CREATE TABLE `purchases` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `invoice_no` varchar(50) DEFAULT NULL,
  `supplier_kode` varchar(64) DEFAULT NULL,
  `location` varchar(32) NOT NULL DEFAULT 'gudang',
  `purchase_date` date DEFAULT NULL,
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
) ENGINE=InnoDB AUTO_INCREMENT=41 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `purchases` (total baris: 25)
--
INSERT INTO `purchases` (`id`,`invoice_no`,`supplier_kode`,`location`,`purchase_date`,`subtotal`,`discount`,`tax`,`total`,`created_by`,`status`,`void_reason`,`void_by`,`void_at`,`created_at`) VALUES 
(2,NULL,NULL,'gudang',NULL,150000,0,0,150000,'admin','OK',NULL,NULL,NULL,'2025-11-03 00:46:38'),
(13,'nav1111','002','gudang',NULL,20000,0,0,20000,'wahyono','OK',NULL,NULL,NULL,'2025-11-03 02:33:11'),
(14,'00002','002','toko',NULL,1000000,0,0,1000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-03 03:31:08'),
(15,'0001','002','toko',NULL,92000,9200,9108,91908,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 09:13:06'),
(16,33333,'002','gudang',NULL,0,0,0,0,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 10:18:14'),
(17,111111,'002','gudang',NULL,11000000,0,0,11000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 11:21:44'),
(18,321456,'002','gudang',NULL,2000000,0,0,2000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-07 11:22:46'),
(19,'00025','0001','gudang',NULL,2000,0,0,2000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 15:55:25'),
(20,'00024','002','gudang',NULL,20000,0,0,20000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 15:56:17'),
(21,'00002','002','gudang',NULL,1000000,0,0,1000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 16:57:33'),
(22,3698,'0001','gudang',NULL,2000000,0,0,2000000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 16:59:58'),
(23,32584,'0001','gudang',NULL,100000,0,0,100000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 17:04:38'),
(28,3564,'0001','gudang','2025-11-08',110000,0,0,110000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 17:15:20'),
(29,3216548,'0001','gudang','2025-11-08',15000,0,0,15000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 17:50:36'),
(30,326589,'0001','gudang','2025-11-08',15000,0,0,15000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 17:51:11'),
(31,32653,'0001','toko','2025-11-08',15000,0,0,15000,'wahyono','OK',NULL,NULL,NULL,'2025-11-08 17:52:10'),
(32,123123,'0001','gudang','2025-11-13',5000,0,0,5000,'wahyono','OK',NULL,NULL,NULL,'2025-11-13 21:40:02'),
(33,321564,'0001','gudang','2025-11-13',26000,0,0,26000,'wahyono','OK',NULL,NULL,NULL,'2025-11-13 21:40:55'),
(34,987456,'0001','gudang','2025-11-13',2500000,0,0,2500000,'wahyono','OK',NULL,NULL,NULL,'2025-11-13 21:42:32'),
(35,3564,'002','gudang','2025-11-14',20000,0,0,20000,'wahyono','OK',NULL,NULL,NULL,'2025-11-14 07:12:15'),
(36,'0001','002','gudang','2025-11-14',5000,0,0,5000,'wahyono','OK',NULL,NULL,NULL,'2025-11-14 07:12:46'),
(37,'0001','002','gudang','2025-11-14',5000,0,0,5000,'wahyono','OK',NULL,NULL,NULL,'2025-11-14 17:10:14'),
(38,321654,'0001','gudang','2025-11-14',20000,0,0,20000,'wahyono','OK',NULL,NULL,NULL,'2025-11-14 19:01:44'),
(39,3564,'0001','gudang','2025-11-15',10000,0,0,10000,'wahyono','OK',NULL,NULL,NULL,'2025-11-15 07:24:25'),
(40,3564,'0001','gudang','2025-11-15',10000,0,0,10000,'wahyono','OK',NULL,NULL,NULL,'2025-11-15 19:32:25');

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
  `level` int(1) DEFAULT 1,
  PRIMARY KEY (`id`),
  KEY `idx_sale_id` (`sale_id`),
  KEY `idx_item_kode` (`item_kode`)
) ENGINE=InnoDB AUTO_INCREMENT=148 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `sale_items` (total baris: 140)
--
INSERT INTO `sale_items` (`id`,`sale_id`,`item_kode`,`nama`,`qty`,`harga`,`total`,`level`) VALUES 
(1,1,'002','akua',1,2500,2500,1),
(2,2,11111,1111,100,13750,175000,1),
(3,3,'002','akua',10,2500,20000,1),
(4,4,'002','akua',10,1750,17500,1),
(5,5,'002','akua',100,2000,200000,1),
(6,6,'002','akua',2,2000,4000,1),
(7,7,111,'BIMOLI',2,9250,18500,1),
(8,7,'002','akua',3,2250,6750,1),
(9,8,'002','akua',20,2250,45000,1),
(10,9,123,'BIMOLI 100 ML',20,2500,50000,1),
(11,10,1234,'nutisari',10,2000,20000,1),
(12,11,1234,'nutisari',10,2000,20000,1),
(13,12,5555,'akua',4,23500,94000,1),
(14,12,111,'BIMOLI',1,8500,8500,1),
(15,12,1234,'nutisari',2,1250,2500,1),
(16,12,987,'fit',1,21000,21000,1),
(17,13,5555,'akua',3,25000,75000,1),
(18,14,5555,'akua',3,25000,75000,1),
(19,15,5555,'akua',2,25000,50000,1),
(20,16,5555,'akua',1,24000,24000,1),
(21,17,5555,'akua',1,25000,25000,1),
(22,17,123,'BIMOLI 100 ML',12,2500,30000,1),
(23,18,5555,'akua',2,25000,50000,1),
(24,18,123,'BIMOLI 100 ML',2,2500,5000,1),
(29,20,5555,'akua',1,24000,24000,1),
(30,20,123,'BIMOLI 100 ML',1,2000,2000,1),
(31,21,5555,'akua',1,24000,24000,1),
(32,22,5555,'akua',20,25000,500000,1),
(33,23,123,'BIMOLI 100 ML',2,2500,5000,1),
(34,24,5555,'akua',2,24000,48000,1),
(35,25,5555,'akua',2,25000,50000,1),
(36,26,5555,'akua',2,23500,47000,1),
(37,27,5555,'akua',50,23500,1175000,1),
(38,28,5555,'akua',10,25000,250000,1),
(39,29,5555,'akua',5,24000,120000,1),
(40,30,5555,'akua',1,25000,25000,1),
(41,31,5555,'akua',10,25000,250000,1),
(42,32,5555,'akua',1,24500,24500,1),
(43,32,123,'BIMOLI 100 ML',1,2250,2250,1),
(44,32,1234,'nutisari',2,1750,3500,1),
(45,33,5555,'akua',1,25000,25000,1),
(46,34,5555,'akua',1,25000,25000,1),
(47,35,5555,'akua',1,25000,25000,1),
(48,35,123,'BIMOLI 100 ML',1,2500,2500,1),
(49,35,987,'fit',1,24000,24000,1),
(50,36,5555,'akua',1,25000,25000,1),
(51,37,5555,'akua',1,24000,24000,1),
(52,37,123,'BIMOLI 100 ML',2,2250,4500,1),
(53,37,987,'fit',1,22000,22000,1),
(54,38,5555,'akua',2,25000,50000,1),
(58,39,5555,'akua',2,25000,50000,1),
(59,39,123,'BIMOLI 100 ML',2,2500,5000,1),
(60,39,111,'BIMOLI',1,9500,9500,1),
(61,39,987,'fit',1,24000,24000,1),
(62,40,5555,'akua',2,25000,50000,1),
(63,40,111,'BIMOLI',2,9500,19000,1),
(64,40,123,'BIMOLI 100 ML',1,2500,2500,1),
(65,40,987,'fit',1,24000,24000,1),
(66,40,1234,'nutisari',1,2000,2000,1),
(67,41,5555,'akua',5,24000,120000,1),
(68,42,987,'fit',1,23000,23000,1),
(69,43,5555,'akua',1,25000,25000,1),
(70,44,5555,'akua',1,25000,25000,1),
(71,45,11111,1111,3,13750,41250,1),
(72,46,11111,1111,3,13750,41250,1),
(73,47,11111,1111,1,13750,13750,1),
(74,48,5555,'akua',2,25000,50000,1),
(75,49,5555,'akua',1,25000,25000,1),
(76,50,5555,'akua',3,25000,75000,1),
(77,51,5555,'akua',2,24500,49000,1),
(78,52,5555,'akua',1,25000,25000,1),
(79,52,123,'BIMOLI 100 ML',2,2500,5000,1),
(80,53,5555,'akua',1,25000,25000,1),
(81,53,123,'BIMOLI 100 ML',1,2500,2500,1),
(82,54,123,'BIMOLI 100 ML',20,2500,50000,1),
(83,55,123,'BIMOLI 100 ML',2,2500,5000,1),
(84,55,5555,'akua',1,25000,25000,1),
(85,55,987,'fit',2,24000,48000,1),
(86,55,111,'BIMOLI',1,9500,9500,1),
(87,56,5555,'akua',1,25000,25000,1),
(88,57,123,'BIMOLI 100 ML',1,2500,2500,1),
(89,57,5555,'akua',1,25000,25000,1),
(90,57,123456,'kola kola',1,7,7,1),
(91,57,111,'BIMOLI',1,9500,9500,1),
(92,58,5555,'akua',1,25000,25000,1),
(93,59,5555,'akua',1,24500,24500,1),
(94,59,123,'BIMOLI 100 ML',1,2250,2250,1),
(95,59,111,'BIMOLI',1,9250,9250,1),
(96,59,987,'fit',1,22000,22000,1),
(97,60,5555,'akua',2,25000,50000,1),
(98,60,123,'BIMOLI 100 ML',1,2500,2500,1),
(99,60,111,'BIMOLI',1,9500,9500,1),
(100,60,987,'fit',1,24000,24000,1),
(101,61,5555,'akua',1,24500,24500,1),
(102,61,123,'BIMOLI 100 ML',1,2250,2250,1),
(103,61,111,'BIMOLI',1,9250,9250,1),
(104,62,123,'BIMOLI 100 ML',1,2500,2500,1),
(105,62,111,'BIMOLI',1,9500,9500,1),
(106,62,5555,'akua',1,25000,25000,1),
(107,62,987,'fit',1,24000,24000,1),
(108,63,5555,'akua',1,25000,25000,1),
(109,64,5555,'akua',1,25000,25000,1),
(110,64,123,'BIMOLI 100 ML',1,2500,2500,1),
(111,64,111,'BIMOLI',1,9500,9500,1),
(112,65,123,'BIMOLI 100 ML',1,6250,6250,1),
(113,65,5555,'akua',1,32500,32500,1),
(114,65,111,'BIMOLI',1,9500,9500,1),
(115,65,987,'fit',1,24000,24000,1),
(116,66,123,'BIMOLI 100 ML',2,6250,12500,1),
(117,66,1234,'nutisari',1,6250,6250,1),
(118,66,5555,'akua',2,32500,65000,1),
(119,67,5555,'akua',1,32500,32500,1),
(120,68,5555,'akua',2,32500,65000,1),
(121,69,5555,'akua',1,32500,32500,1),
(122,70,5555,'akua',1,32500,32500,1),
(123,71,5555,'akua',1,32500,32500,1),
(124,72,5555,'akua',1,32500,32500,1),
(125,73,5555,'akua',3,32500,97500,1),
(126,74,123,'BIMOLI 100 ML',3,6250,18750,1),
(127,74,111,'BIMOLI',1,9500,9500,1),
(128,74,5555,'akua',1,32500,32500,1),
(129,74,987,'fit',1,24000,24000,1),
(130,74,123456,'kola kola',1,6250,6250,1),
(131,74,11111,1111,1,15625,15625,1),
(132,74,1234,'nutisari',1,6250,6250,1),
(133,75,5555,'akua',1,32500,32500,1),
(134,76,5555,'akua',1,32500,32500,1),
(135,77,11111,'le mineral',1,21000,21000,1),
(136,77,5555,'akua',1,32500,32500,1),
(137,77,123,'BIMOLI 100 ML',1,6250,6250,1),
(138,77,1234,'nutisari',1,6250,6250,1),
(139,77,111,'BIMOLI',1,9500,9500,1),
(140,78,123,'BIMOLI 100 ML',1,6250,6250,1),
(141,78,5555,'akua',1,32500,32500,1),
(142,78,11111,'le mineral',1,21000,21000,1),
(143,78,111,'BIMOLI',1,9500,9500,1),
(144,79,123456789,'Aku mau beli roti celup sama bando ini ya tan',1,12500,12500,1),
(145,79,123,'BIMOLI 100 ML',1,6250,6250,1),
(146,79,5555,'akua',1,32500,32500,1),
(147,79,111,'BIMOLI',1,9500,9500,1);

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
  `discount_mode` enum('rp','pct') DEFAULT 'rp',
  `tax_mode` enum('rp','pct') DEFAULT 'rp',
  PRIMARY KEY (`id`),
  KEY `idx_created_at` (`created_at`),
  KEY `idx_invoice_no` (`invoice_no`),
  KEY `idx_member` (`member_kode`)
) ENGINE=InnoDB AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `sales` (total baris: 78)
--
INSERT INTO `sales` (`id`,`invoice_no`,`member_kode`,`shift`,`subtotal`,`discount`,`tax`,`total`,`tunai`,`kembalian`,`created_by`,`created_at`,`status`,`discount_mode`,`tax_mode`) VALUES 
(1,'PEL/20251102134355',NULL,1,2500,0,0,2500,2500,0,'wahyono','2025-11-02 19:43:55','RETURNED','rp','rp'),
(2,'PEL/20251102135149',123,2,175000,0,0,1375000,200000,25000,'wahyono','2025-11-02 19:51:49','OK','rp','rp'),
(3,'PEL/20251102135650',123,2,20000,0,0,25000,50000,30000,'wahyono','2025-11-02 19:56:50','OK','rp','rp'),
(4,'PEL/20251102143912','0001',2,17500,0,0,17500,20000,2500,'wahyono','2025-11-02 20:39:12','OK','rp','rp'),
(5,'S20251102144257','0001',2,200000,0,0,200000,250000,50000,'wahyono','2025-11-02 20:42:57','OK','rp','rp'),
(6,'S20251102150411','0001',2,4000,0,0,4000,5000,1000,'ali','2025-11-02 21:04:11','OK','rp','rp'),
(7,'S20251102151012','0001',2,25250,0,0,25250,30000,4750,'ali','2025-11-02 21:10:12','OK','rp','rp'),
(8,'S20251102152637',123,2,45000,0,0,45000,50000,5000,'wahyono','2025-11-02 21:26:37','OK','rp','rp'),
(9,'S20251106042536','0001',1,50000,0,0,50000,0,0,'wahyono','2025-11-06 10:25:36','OK','rp','rp'),
(10,'S20251106042640',123,1,20000,0,0,20000,0,0,'wahyono','2025-11-06 10:26:40','OK','rp','rp'),
(11,'S20251106042725',123,1,20000,5000,0,15000,0,0,'wahyono','2025-11-06 10:27:25','OK','rp','rp'),
(12,'S20251106201505','0001',2,126000,0,0,126000,150000,24000,'wahyono','2025-11-07 02:15:05','OK','rp','rp'),
(13,'S20251106205322','002',1,75000,0,0,75000,0,0,'wahyono','2025-11-07 02:53:22','OK','rp','rp'),
(14,'PEL20251106205832','002',1,75000,0,0,75000,100000,25000,'wahyono','2025-11-07 02:58:32','OK','rp','rp'),
(15,'PEL20251106205931','0001',2,50000,0,0,50000,50000,0,'wahyono','2025-11-07 02:59:31','OK','rp','rp'),
(16,'PEL20251106212443',NULL,1,24000,0,0,24000,25000,1000,'wahyono','2025-11-07 03:24:43','OK','rp','rp'),
(17,'PEL20251106212717',NULL,1,55000,0,0,55000,100000,45000,'wahyono','2025-11-07 03:27:17','OK','rp','rp'),
(18,'PEL20251106212829','002',2,55000,0,0,55000,100000,45000,'wahyono','2025-11-07 03:28:29','OK','rp','rp'),
(20,'S20251106213620','002',2,26000,0,0,26000,50000,24000,'wahyono','2025-11-07 03:36:20','OK','rp','rp'),
(21,'S20251106213939','0001',2,24000,0,0,24000,50000,26000,'wahyono','2025-11-07 03:39:39','OK','rp','rp'),
(22,'S20251106214451','002',1,500000,10000,11000,501000,550000,49000,'wahyono','2025-11-07 03:44:51','OK','rp','rp'),
(23,'S20251106214859',NULL,1,5000,10,0,4990,10000,5010,'wahyono','2025-11-07 03:48:59','OK','rp','rp'),
(24,'S20251106214931','0001',2,48000,0,0,48000,50000,2000,'wahyono','2025-11-07 03:49:31','OK','rp','rp'),
(25,'S20251106215958',NULL,1,50000,5000,4950,49950,50000,50,'wahyono','2025-11-07 03:59:58','OK','rp','rp'),
(26,'S20251106220126','0001',1,47000,4700,4653,46953,50000,3047,'wahyono','2025-11-07 04:01:26','OK','rp','rp'),
(27,'S20251106220349','002',2,1175000,5000,128700,1298700,1300000,1300,'wahyono','2025-11-07 04:03:49','OK','rp','rp'),
(28,'S20251106220538','0001',1,250000,0,0,250000,300000,50000,'wahyono','2025-11-07 04:05:38','OK','rp','rp'),
(29,'S20251106220846','002',2,120000,0,0,120000,120000,0,'wahyono','2025-11-07 04:08:46','OK','rp','rp'),
(30,'S20251106221014','0001',1,25000,0,0,25000,50000,25000,'wahyono','2025-11-07 04:10:14','OK','rp','rp'),
(31,'S20251107052415','0023',1,250000,0,0,250000,300000,50000,'wahyono','2025-11-07 05:24:15','OK','rp','rp'),
(32,'S20251107053910','0023',2,30250,0,0,30250,50000,19750,'wahyono','2025-11-07 05:39:10','OK','rp','rp'),
(33,'S20251107054106',NULL,1,25000,0,0,25000,100000,75000,'wahyono','2025-11-07 05:41:06','OK','rp','rp'),
(34,'S20251107054513',NULL,1,25000,0,0,25000,50000,25000,'wahyono','2025-11-07 05:45:13','OK','rp','rp'),
(35,'S20251107054707',NULL,1,51500,0,0,51500,60000,8500,'wahyono','2025-11-07 05:47:07','OK','rp','rp'),
(36,'S20251107064037','0023',1,25000,0,0,25000,25000,0,'wahyono','2025-11-07 06:40:37','OK','rp','rp'),
(37,'S20251108075438',NULL,1,50500,10000,11,40511,50000,9489,'wahyono','2025-11-08 07:54:38','OK','rp','rp'),
(38,'S20251108075537','0023',1,86000,5000,11,45011,100000,18989,'wahyono','2025-11-08 07:55:37','OK','rp','rp'),
(39,'S20251108080246','0001',1,88500,0,0,88500,100000,11500,'ali','2025-11-08 08:02:46','CANCEL','rp','rp'),
(40,'S20251108083436',NULL,1,97500,10,11,97501,100000,2499,'wahyono','2025-11-08 08:34:36','OK','rp','rp'),
(41,'S20251108083547','0023',1,120000,0,0,120000,150000,30000,'wahyono','2025-11-08 08:35:47','OK','rp','rp'),
(42,'S20251108100303','0023',1,23000,0,0,23000,50000,27000,'wahyono','2025-11-08 10:03:03','OK','rp','rp'),
(43,'S20251110195806','0023',1,25000,0,0,25000,50000,25000,'wahyono','2025-11-10 19:58:06','OK','rp','rp'),
(44,'S20251110195939','0001',1,25000,0,0,25000,50000,25000,'wahyono','2025-11-10 19:59:39','OK','rp','rp'),
(45,'S20251110200040','0023',1,41250,0,0,41250,50000,8750,'wahyono','2025-11-10 20:00:40','OK','rp','rp'),
(46,'S20251110200821','0023',1,41250,0,0,41250,50000,8750,'wahyono','2025-11-10 20:08:21','OK','rp','rp'),
(47,'S20251110202828','0023',1,13750,0,0,13750,20000,6250,'wahyono','2025-11-10 20:28:28','OK','rp','rp'),
(48,'S20251110210406','002',1,50000,0,0,50000,50000,0,'wahyono','2025-11-10 21:04:06','OK','rp','rp'),
(49,'S20251113191035','0023',1,25000,0,0,25000,50000,25000,'wahyono','2025-11-13 19:10:35','OK','rp','rp'),
(50,'S20251113191432','0001',1,75000,0,0,75000,100000,25000,'wahyono','2025-11-13 19:14:32','OK','rp','rp'),
(51,'S20251113194706','0023',1,49000,0,0,47000,50000,3000,'wahyono','2025-11-13 19:47:06','OK','rp','rp'),
(52,'S20251113195512',1234,1,30000,0,0,30000,50000,20000,'wahyono','2025-11-13 19:55:12','OK','rp','rp'),
(53,'S20251113195559',1234,1,27500,0,0,27400,30000,2600,'wahyono','2025-11-13 19:55:59','OK','rp','rp'),
(54,'S20251113200428','0023',1,50000,5000,4950,47950,50000,2050,'wahyono','2025-11-13 20:04:28','OK','pct','pct'),
(55,'S20251113200823',1234,1,87500,0,0,87500,100000,12500,'wahyono','2025-11-13 20:08:23','OK','rp','rp'),
(56,'S20251113200852',NULL,1,25000,0,0,25000,25000,0,'wahyono','2025-11-13 20:08:52','OK','rp','rp'),
(57,'S20251113203416',1234,1,37007,0,0,37007,50000,12993,'wahyono','2025-11-13 20:34:16','OK','rp','rp'),
(58,'S20251113203515',1234,1,25000,0,0,24800,25000,200,'wahyono','2025-11-13 20:35:15','OK','rp','rp'),
(59,'S20251113205138',1234,1,58000,0,0,57500,60000,2500,'wahyono','2025-11-13 20:51:38','OK','rp','rp'),
(60,'S20251113205401',NULL,1,86000,0,0,86000,100000,14000,'wahyono','2025-11-13 20:54:01','OK','rp','rp'),
(61,'S20251113205744',1234,1,36000,0,0,35500,50000,14500,'wahyono','2025-11-13 20:57:44','OK','rp','rp'),
(62,'S20251113210501',1234,1,61000,0,0,61000,100000,39000,'wahyono','2025-11-13 21:05:01','OK','rp','rp'),
(63,'S20251113211015','0023',1,25000,0,0,15000,50000,35000,'wahyono','2025-11-13 21:10:15','OK','rp','rp'),
(64,'S20251113212355','0023',1,37000,0,0,37000,50000,13000,'wahyono','2025-11-13 21:23:55','OK','rp','rp'),
(65,'S20251114071037','0001',1,72250,0,0,67250,70000,2750,'wahyono','2025-11-14 07:10:37','OK','rp','rp'),
(66,'S20251114091207','0023',1,83750,0,0,0,0,0,'wahyono','2025-11-14 09:12:07','OK','rp','rp'),
(67,'S20251114114409','0001',1,32500,0,0,32500,50000,17500,'wahyono','2025-11-14 11:44:09','OK','rp','rp'),
(68,'S20251114114508','0001',1,65000,0,0,60000,70000,10000,'wahyono','2025-11-14 11:45:08','OK','rp','rp'),
(69,'S20251114180004','002',1,32500,0,0,32500,50000,17500,'wahyono','2025-11-14 18:00:04','OK','rp','rp'),
(70,'S20251114180041','002',1,32500,0,0,22500,22500,0,'wahyono','2025-11-14 18:00:41','OK','rp','rp'),
(71,'S20251114180502','002',1,32500,0,0,32000,40000,8000,'wahyono','2025-11-14 18:05:02','OK','rp','rp'),
(72,'S20251114180552','0023',1,32500,0,0,27500,30000,2500,'wahyono','2025-11-14 18:05:52','OK','rp','rp'),
(73,'S20251114180812','002',1,97500,9750,9652,51952,60000,8048,'wahyono','2025-11-14 18:08:12','OK','pct','pct'),
(74,'S20251114181009','0023',1,112875,0,0,110875,120000,9125,'wahyono','2025-11-14 18:10:09','OK','rp','rp'),
(75,'S20251114181159','0023',1,32500,0,0,32500,35000,2500,'wahyono','2025-11-14 18:11:59','OK','rp','rp'),
(76,'S20251114181217','002',1,32500,0,0,32500,35000,2500,'wahyono','2025-11-14 18:12:17','OK','rp','rp'),
(77,'S20251115071815','0023',1,75500,0,0,73500,100000,26500,'wahyono','2025-11-15 07:18:15','OK','rp','rp'),
(78,'S20251115072221','002',1,69250,0,0,59250,60000,750,'wahyono','2025-11-15 07:22:21','OK','rp','rp'),
(79,'S20251115072500','0023',1,60750,0,0,59750,60000,250,'wahyono','2025-11-15 07:25:00','OK','rp','rp');

--
-- Struktur tabel `sales_ar`
--

DROP TABLE IF EXISTS `sales_ar`;
CREATE TABLE `sales_ar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `supplier_kode` varchar(64) NOT NULL,
  `purchase_id` int(11) NOT NULL,
  `amount` bigint(20) NOT NULL DEFAULT 0,
  `due_date` date DEFAULT NULL,
  `status` enum('OPEN','PARTIAL','PAID','CANCEL') NOT NULL DEFAULT 'OPEN',
  `note` varchar(255) DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` datetime NOT NULL,
  `updated_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_ar_purchase` (`purchase_id`),
  CONSTRAINT `fk_ar_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `purchases` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
  `redeem_rp_per_point_umum` int(11) DEFAULT 100,
  `redeem_rp_per_point_grosir` int(11) DEFAULT 25,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `settings` (total baris: 1)
--
INSERT INTO `settings` (`id`,`store_name`,`store_address`,`store_phone`,`footer_note`,`logo_url`,`invoice_prefix`,`invoice_counter_date`,`invoice_counter`,`points_per_rupiah`,`points_per_rupiah_umum`,`points_per_rupiah_grosir`,`rupiah_per_point_umum`,`rupiah_per_point_grosir`,`qr_provider_url`,`redeem_rp_per_point_umum`,`redeem_rp_per_point_grosir`) VALUES 
(1,'PELANGI MART','Cinurug Rt 06 Rw 06, Gumelar, Kec. Gumelar, Kabupaten Banyumas, Jawa Tengah 53165','085875099445','Terima kasih telah berbelanja.','/tokoapp/uploads/logo_20251102_125050.jpg','PEL/','2025-11-02',10,0,20000,15000,100,100,'https://api.qrserver.com/v1/create-qr-code/?size=140x140&data=',100,25);

--
-- Struktur tabel `stock_mutations`
--

DROP TABLE IF EXISTS `stock_mutations`;
CREATE TABLE `stock_mutations` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `item_kode` varchar(64) NOT NULL,
  `from_loc` varchar(32) NOT NULL,
  `to_loc` varchar(32) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  `created_by` varchar(64) DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `item_kode` (`item_kode`),
  KEY `created_at` (`created_at`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `stock_mutations` (total baris: 8)
--
INSERT INTO `stock_mutations` (`id`,`item_kode`,`from_loc`,`to_loc`,`qty`,`created_by`,`created_at`) VALUES 
(1,'002','gudang','toko',10,'wahyono','2025-11-07 05:17:14'),
(2,5555,'gudang','toko',100,'wahyono','2025-11-07 05:20:59'),
(3,123,'gudang','toko',25,'wahyono','2025-11-08 09:58:16'),
(4,123,'gudang','toko',19,'wahyono','2025-11-08 09:59:06'),
(5,987,'gudang','toko',10,'wahyono','2025-11-08 10:00:25'),
(6,1234,'gudang','toko',20,'wahyono','2025-11-08 10:51:34'),
(7,11111,'gudang','toko',100,'wahyono','2025-11-13 21:43:24'),
(8,11111,'gudang','toko',5,'wahyono','2025-11-15 07:12:28');

--
-- Struktur tabel `stocks`
--

DROP TABLE IF EXISTS `stocks`;
CREATE TABLE `stocks` (
  `location` varchar(20) NOT NULL,
  `item_kode` varchar(64) NOT NULL,
  `qty` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`location`,`item_kode`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

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
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4;

--
-- Dumping data untuk tabel `suppliers` (total baris: 2)
--
INSERT INTO `suppliers` (`id`,`kode`,`nama`,`alamat`,`tlp`,`created_at`,`updated_at`) VALUES 
(1,'002','UNILEVER','Jl. Gatot Subroto No.95, Wanasari, Sidanegara, Kec. Cilacap Tengah, Kabupaten Cilacap, Jawa Tengah 53212','085875099445','2025-11-03 00:45:00',NULL),
(2,'0001','MJN','jalan jana','0857854221','2025-11-07 12:22:52','2025-11-07 05:22:52');

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
-- Dumping data untuk tabel `units` (total baris: 34)
--
INSERT INTO `units` (`code`,`name`) VALUES 
('bag','Bag'),
('botol','Botol'),
('box','Box'),
('bulan','Bulan'),
('can','Kaleng'),
('cc','Cubic Centimeter'),
('cm','Centimeter'),
('dus','Dus'),
('gal','Gallon'),
('gr','Gram'),
('ha','Hektar'),
('hari','Hari'),
('jam','Jam'),
('jar','Jar'),
('kg','Kilogram'),
('km','Kilometer'),
('kw','Kuintal'),
('liter','Liter'),
('m','Meter'),
('m2','Meter Persegi'),
('m3','Cubic Meter'),
('mg','Miligram'),
('ml','Mililiter'),
('mm','Milimeter'),
('ons','Ons'),
('pack','Pack'),
('pair','Pasang'),
('pcs','Pieces'),
('sachet','Sachet'),
('set','Set'),
('ton','Ton'),
('tray','Tray'),
('tube','Tube'),
('unit','Unit');

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
(1,'admin','$2y$10$otp3U65Ilo0DHJ/xFyrLOeXRR8A4hgKQro.jNStzDNbZJweoFMJ3K','admin',0,'2025-11-03 00:30:04'),
(2,'wahyono','$2y$10$ytfeT8Hj3ypDRh4x1zc5TeuZC2xLn6K1rCqf7Mucob75IEpEUcfbe','admin',1,'2025-11-03 01:36:05'),
(3,'ali','$2y$10$c7dfyFjHsXDaEyLYJANtNOscM7.WkYCdy9IBNiuMYxdNNZUVwsfXi','kasir',1,'2025-11-03 01:42:07');


COMMIT;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
