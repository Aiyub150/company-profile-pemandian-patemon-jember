-- Database untuk Aplikasi Web Pemandian
-- Sesuai dengan konfigurasi di dist/app/config.php: database = 'pemandian'
-- Login contoh:
--   admin  / admin123
--   staff  / staff123
--   aiyub  / user123

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
  `password` varchar(100) NOT NULL,
  `email` varchar(100) NOT NULL,
  `no_telepon` varchar(20) DEFAULT NULL,
  `level` tinyint(1) NOT NULL DEFAULT 0 COMMENT '0=pengguna, 1=admin, 2=staff',
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uniq_users_username` (`username`),
  UNIQUE KEY `uniq_users_email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `tiket` (
  `id_tiket` int(11) NOT NULL AUTO_INCREMENT,
  `nama_tiket` varchar(50) NOT NULL,
  `harga` int(11) NOT NULL DEFAULT 0,
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

INSERT INTO `users` (`id_user`, `nama`, `username`, `password`, `email`, `no_telepon`, `level`) VALUES
(1, 'Administrator Pemandian', 'admin', 'admin123', 'admin@pemandian.local', '081234567001', 1),
(2, 'Staff Loket', 'staff', 'staff123', 'staff@pemandian.local', '081234567002', 2),
(3, 'Aiyub', 'aiyub', 'user123', 'aiyub@example.com', '081234567003', 0),
(4, 'Amanda Putri', 'amanda', 'user123', 'amanda@example.com', '081234567004', 0),
(5, 'Alvi Ramadhan', 'alvi', 'user123', 'alvi@example.com', '081234567005', 0);

INSERT INTO `tiket` (`id_tiket`, `nama_tiket`, `harga`) VALUES
(1, 'Dewasa', 10000),
(2, 'Anak-Anak', 5000);

INSERT INTO `transaksi` (`id_transaksi`, `id_user`, `tgl_pemesanan`, `total_harga`, `metode_pembayaran`, `bukti_pembayaran`, `status`) VALUES
(1, 3, '2026-08-28', 25000, 'Qris', 'logo2.png', 'done'),
(2, 4, '2026-08-29', 20000, 'Bayar Di Loket', 'Bayar Di Loket', 'notyet'),
(3, 5, '2026-08-30', 30000, 'Loket 1', 'bayar di loket', 'done'),
(4, 2, '2026-08-31', 15000, 'Loket 2', 'bayar di loket', 'done');

INSERT INTO `detail_transaksi` (`id_detail`, `id_transaksi`, `jenis_tiket`, `quantity`, `sub_total`) VALUES
(1, 1, 'Dewasa', 2, 20000),
(2, 1, 'Anak-Anak', 1, 5000),
(3, 2, 'Dewasa', 1, 10000),
(4, 2, 'Anak-Anak', 2, 10000),
(5, 3, 'Dewasa', 3, 30000),
(6, 3, 'Anak-Anak', 0, 0),
(7, 4, 'Dewasa', 1, 10000),
(8, 4, 'Anak-Anak', 1, 5000);

INSERT INTO `ulasan` (`id_ulasan`, `username`, `email`, `no_telepon`, `ulasan`, `tgl_ulasan`) VALUES
(1, 'Aiyub', 'aiyub@example.com', '081234567003', 'Tempatnya nyaman dan cocok untuk liburan keluarga.', '2026-08-28'),
(2, 'Amanda Putri', 'amanda@example.com', '081234567004', 'Air kolam bersih, pelayanan loket juga cepat.', '2026-08-29'),
(3, 'Alvi Ramadhan', 'alvi@example.com', '081234567005', 'Harga tiket terjangkau. Semoga fasilitas bilas bisa ditambah.', '2026-08-30');

COMMIT;
