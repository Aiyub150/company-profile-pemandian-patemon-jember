-- Database untuk Aplikasi Web Pemandian Patemon
-- Sesuai dengan konfigurasi di dist/app/config.php: database = 'pemandian'
-- Level RBAC: 1 = Super Admin, 2 = Admin, 3 = Staf Kasir, 0 = Pengunjung
-- Akun bawaan (semua password: "password123"):
--   superadmin@pemandian.com  →  Kepala Pemandian   (Level 1 – Super Admin)
--   admin@pemandian.com       →  Admin Pemandian    (Level 2 – Admin)
--   staff@pemandian.com       →  Staff Pemandian    (Level 3 – Staf Kasir)

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+08:00";

CREATE DATABASE IF NOT EXISTS `pemandian`
  DEFAULT CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE `pemandian`;

DROP TABLE IF EXISTS `detail_transaksi`;
DROP TABLE IF EXISTS `transaksi`;
DROP TABLE IF EXISTS `ulasan`;
DROP TABLE IF EXISTS `tiket`;
DROP TABLE IF EXISTS `users`;
DROP TABLE IF EXISTS `gallery`;

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=pengunjung, 1=superadmin, 2=admin, 3=staf',
  `avatar` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uniq_users_username` (`username`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tiket` (
  `id_tiket` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tiket` varchar(50) NOT NULL,
  `harga` int(11) NOT NULL DEFAULT 0,
  `ikon` varchar(50) DEFAULT 'fa-ticket',
  PRIMARY KEY (`id_tiket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `transaksi` (
  `id_transaksi` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) NOT NULL,
  `nama_pemesan` varchar(100) DEFAULT NULL,
  `tgl_pemesanan` date NOT NULL,
  `total_harga` int(11) NOT NULL DEFAULT 0,
  `metode_pembayaran` varchar(50) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status` enum('done','notyet') NOT NULL DEFAULT 'notyet',
  `deleted_at` datetime DEFAULT NULL,
  PRIMARY KEY (`id_transaksi`),
  KEY `idx_transaksi_user` (`id_user`),
  KEY `idx_transaksi_tgl` (`tgl_pemesanan`),
  KEY `idx_transaksi_deleted` (`deleted_at`),
  CONSTRAINT `fk_transaksi_user`
    FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `detail_transaksi` (
  `id_detail` int(11) NOT NULL AUTO_INCREMENT,
  `id_transaksi` int(11) NOT NULL,
  `jenis_tiket` varchar(50) NOT NULL,
  `quantity` int(11) NOT NULL DEFAULT 0,
  `sub_total` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id_detail`),
  KEY `idx_detail_transaksi` (`id_transaksi`),
  CONSTRAINT `fk_detail_transaksi`
    FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `ulasan` (
  `id_ulasan` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `ulasan` text NOT NULL,
  `tgl_ulasan` date NOT NULL,
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=belum dibaca, 1=sudah dibaca',
  PRIMARY KEY (`id_ulasan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `gallery` (
  `id_gallery` int(11) NOT NULL AUTO_INCREMENT,
  `judul` varchar(100) NOT NULL,
  `deskripsi_card` varchar(200) DEFAULT NULL COMMENT 'Teks pendek di card preview',
  `deskripsi_popup` text DEFAULT NULL COMMENT 'Teks panjang di popup modal',
  `gambar_card` varchar(255) NOT NULL COMMENT 'Gambar tampilan card/preview',
  `gambar_popup` varchar(255) NOT NULL COMMENT 'Gambar tampilan popup/lightbox',
  `urutan` int(11) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id_gallery`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel Filter Kata Kasar / Toxic Words
CREATE TABLE IF NOT EXISTS `toxic_words` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `word` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_word` (`word`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel Audit Trail / Activity Log
CREATE TABLE IF NOT EXISTS `activity_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `id_user` int(11) DEFAULT NULL,
  `username` varchar(100) DEFAULT NULL,
  `action` varchar(50) NOT NULL,
  `module` varchar(50) NOT NULL,
  `description` text NOT NULL,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_act_module` (`module`),
  KEY `idx_act_created` (`created_at`),
  KEY `idx_act_user` (`id_user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel Monitor Trafik & Respon Server (Server Log)
CREATE TABLE IF NOT EXISTS `server_logs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `method` varchar(10) NOT NULL,
  `path` varchar(255) NOT NULL,
  `status_code` int(11) NOT NULL DEFAULT 200,
  `ip_address` varchar(45) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `response_time_ms` float DEFAULT NULL,
  `id_user` int(11) DEFAULT NULL,
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_srv_created` (`created_at`),
  KEY `idx_srv_status` (`status_code`),
  KEY `idx_srv_method` (`method`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Tabel Rate Limiting & Proteksi Brute Force (SEC-05)
CREATE TABLE IF NOT EXISTS `login_attempts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(45) NOT NULL,
  `username` varchar(100) NOT NULL,
  `attempt_time` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_login_ip` (`ip_address`),
  KEY `idx_login_user` (`username`),
  KEY `idx_login_time` (`attempt_time`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- =============================================================
-- Seed Data Akun Bawaan (Bcrypt Hash)
-- =============================================================
INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `email`, `no_telepon`, `level`) VALUES
(1, 'Kepala Pemandian',  'superadmin', '$2y$10$bmbuM94ur1hVxmIy/UyYCu.kceCANajqqzDoaOznyQHeuE9BjRh8a', 'superadmin@pemandian.com', '0812345678',  1),
(2, 'Admin Pemandian',   'admin',      '$2y$10$bmbuM94ur1hVxmIy/UyYCu.kceCANajqqzDoaOznyQHeuE9BjRh8a', 'admin@pemandian.com',      '0887654321',  2),
(3, 'Staff Pemandian',   'staff',      '$2y$10$bmbuM94ur1hVxmIy/UyYCu.kceCANajqqzDoaOznyQHeuE9BjRh8a', 'staff@pemandian.com',      '0891827364',  3);

-- Seed Data Tiket
INSERT INTO `tiket` (`id_tiket`, `nama_tiket`, `harga`, `ikon`) VALUES
(1, 'Dewasa',    10000, 'fa-person'),
(2, 'Anak-Anak',  5000, 'fa-child-reaching'),
(3, 'Lansia',     7000, 'fa-person-cane');

-- Seed Data Transaksi
INSERT INTO `transaksi` (`id_transaksi`, `id_user`, `tgl_pemesanan`, `total_harga`, `metode_pembayaran`, `bukti_pembayaran`, `status`) VALUES
(1, 3, '2026-08-28', 25000, 'Qris',          'logo2.png', 'done'),
(2, 3, '2026-08-29', 20000, 'Bayar Di Loket', NULL,       'notyet'),
(3, 2, '2026-08-30', 30000, 'Bayar Di Loket', NULL,       'done'),
(4, 2, '2026-08-31', 15000, 'Transfer Bank',  NULL,       'done');

-- Seed Data Detail Transaksi
INSERT INTO `detail_transaksi` (`id_detail`, `id_transaksi`, `jenis_tiket`, `quantity`, `sub_total`) VALUES
(1, 1, 'Dewasa',    2, 20000),
(2, 1, 'Anak-Anak', 1,  5000),
(3, 2, 'Dewasa',    2, 20000),
(4, 3, 'Dewasa',    3, 30000),
(5, 4, 'Anak-Anak', 3, 15000);

-- Seed Data Ulasan
INSERT INTO `ulasan` (`id_ulasan`, `username`, `email`, `no_telepon`, `ulasan`, `tgl_ulasan`, `is_read`) VALUES
(1, 'Pengunjung Setia', 'visitor@example.com', '081298765432', 'Air pemandian sangat jernih dan segar, tempatnya bersih!', '2026-08-31', 0);

-- Seed Data Gallery
INSERT INTO `gallery` (`id_gallery`, `judul`, `deskripsi_card`, `deskripsi_popup`, `gambar_card`, `gambar_popup`, `urutan`) VALUES
(1, 'Wahana Kolam & Waterpark',        'Fasilitas Rekreasi Keluarga Modern & Asri',           'Pemandian Patemon menyediakan kolam renang bertingkat serta wahana seluncuran air yang aman dan menyenangkan untuk anak-anak maupun dewasa. Air kolam di Pemandian Patemon dialirkan langsung secara alami dari sumber mata air tanpa kaporit.', 'gambar5.png', 'gambar9.png',  1),
(2, 'Kunjungan Mantan Bupati Jember',  'Peninjauan Pemandian Patemon (Periode 2021–2025)',    'Mantan Bupati Jember, Ir. H. Hendy Siswanto, ST. IPU. (periode 2021–2025), melakukan peninjauan langsung ke Pemandian Patemon untuk mengecek kelayakan kolam, kebersihan sumber mata air, serta fasilitas penunjang wisata.', 'gambar7.png', 'gambar8.png',  2),
(3, 'Mata Air Alami Argopuro',         'Air Dingin Jernih Tanpa Bahan Kaporit',              'Keistimewaan utama Pemandian Patemon adalah limpahan mata air alami dari lereng Pegunungan Argopuro yang mengalir jernih, dingin, dan murni tanpa zat kimia kaporit.', 'gambar4.png', 'gambar4.png',  3);

-- Seed Data Toxic Words
INSERT IGNORE INTO `toxic_words` (`word`) VALUES
('anjing'), ('babi'), ('monyet'), ('bangsat'), ('bajingan'), ('kontol'), ('memek'), ('jembut'),
('pantek'), ('asu'), ('perek'), ('lonte'), ('kampret'), ('tai'), ('tolol'), ('goblok'), ('idiot'),
('bego'), ('fuck'), ('shit'), ('bitch'), ('asshole'), ('bastard'), ('cunt'), ('dick'), ('pussy'),
('whore'), ('slut'), ('ngentot'), ('itil'), ('pepek'), ('kimak'), ('peler'), ('tetek');

COMMIT;

