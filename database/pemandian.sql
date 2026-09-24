-- Database untuk Aplikasi Web Pemandian Patemon
-- Sesuai dengan konfigurasi di dist/app/config.php: database = 'pemandian'
-- Akun demo bawaan (password sudah dienkripsi BCRYPT demi keamanan):
--   admin  / admin123   (Level 1 - Administrator)
--   staff  / staff123   (Level 2 - Staff Kasir/Loket)
--   aiyub  / user123    (Level 0 - Pengguna/Pengunjung)
--   amanda / user123    (Level 0 - Pengguna/Pengunjung)
--   alvi   / user123    (Level 0 - Pengguna/Pengunjung)

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

CREATE TABLE `users` (
  `id_user` int(11) NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) DEFAULT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=pengguna, 1=admin, 2=staff',
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
  `tgl_pemesanan` date NOT NULL,
  `total_harga` int(11) NOT NULL DEFAULT 0,
  `metode_pembayaran` varchar(50) NOT NULL,
  `bukti_pembayaran` varchar(255) DEFAULT NULL,
  `status` enum('done','notyet') NOT NULL DEFAULT 'notyet',
  PRIMARY KEY (`id_transaksi`),
  KEY `idx_transaksi_user` (`id_user`),
  KEY `idx_transaksi_tgl` (`tgl_pemesanan`),
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
  PRIMARY KEY (`id_ulasan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Seed Data Akun Bawaan (Hashed Passwords)
INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `email`, `no_telepon`, `level`) VALUES
(1, 'Administrator Pemandian', 'admin', '$2y$10$Ry3tAysvVxQ1Ejiv5NcM8OWUMoNlc6ZBfblXGoIks4HwFKaxirFTS', 'admin@pemandian.local', '081234567001', 1),
(2, 'Staff Loket', 'staff', '$2y$10$1TfoEwR4N8/nf0nD62eAaudVVW/OC/NsL7ruaKObukC3rscSxDzRy', 'staff@pemandian.local', '081234567002', 2),
(3, 'Aiyub', 'aiyub', '$2y$10$eFmS1zwaaGhxCbfC5gh2hus2JZESVYLogIOvE4nFy/MDSI1EWJMr6', 'aiyub@example.com', '081234567003', 0),
(4, 'Amanda Putri', 'amanda', '$2y$10$zlGkpxlqCJyD/pA4MPutseIAxC2AQhFBVlBZ7MSSi.a74DH4WE1IC', 'amanda@example.com', '081234567004', 0),
(5, 'Alvi Ramadhan', 'alvi', '$2y$10$G.tALcgdjx6tVwuw281WvervdeMiP1XuVtOemFEPSrEdHee4WdZv6', 'alvi@example.com', '081234567005', 0);

-- Seed Data Tiket
INSERT INTO `tiket` (`id_tiket`, `nama_tiket`, `harga`, `ikon`) VALUES
(1, 'Dewasa', 10000, 'fa-person'),
(2, 'Anak-Anak', 5000, 'fa-child-reaching'),
(3, 'Lansia', 7000, 'fa-person-cane');

-- Seed Data Transaksi
INSERT INTO `transaksi` (`id_transaksi`, `id_user`, `tgl_pemesanan`, `total_harga`, `metode_pembayaran`, `bukti_pembayaran`, `status`) VALUES
(1, 3, '2026-08-28', 25000, 'Qris', 'logo2.png', 'done'),
(2, 4, '2026-08-29', 20000, 'Bayar Di Loket', NULL, 'notyet'),
(3, 5, '2026-08-30', 30000, 'Loket 1', NULL, 'done'),
(4, 2, '2026-08-31', 15000, 'Loket 2', NULL, 'done');

-- Seed Data Detail Transaksi
INSERT INTO `detail_transaksi` (`id_detail`, `id_transaksi`, `jenis_tiket`, `quantity`, `sub_total`) VALUES
(1, 1, 'Dewasa', 2, 20000),
(2, 1, 'Anak-Anak', 1, 5000),
(3, 2, 'Dewasa', 2, 20000),
(4, 3, 'Dewasa', 3, 30000),
(5, 4, 'Anak-Anak', 3, 15000);

-- Seed Data Ulasan
INSERT INTO `ulasan` (`id_ulasan`, `username`, `email`, `no_telepon`, `ulasan`, `tgl_ulasan`) VALUES
(1, 'Pengunjung Setia', 'visitor@example.com', '081298765432', 'Air pemandian sangat jernih dan segar, tempatnya bersih!', '2026-08-31');

COMMIT;
