-- SQL dump for project "Pemandian"
-- Database: pemandian

CREATE DATABASE IF NOT EXISTS `pemandian` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `pemandian`;

-- Table: users
DROP TABLE IF EXISTS `users`;
CREATE TABLE `users` (
  `id_user` INT NOT NULL AUTO_INCREMENT,
  `nama` VARCHAR(150) DEFAULT NULL,
  `username` VARCHAR(100) NOT NULL,
  `password` VARCHAR(255) NOT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `no_telepon` VARCHAR(30) DEFAULT NULL,
  `level` ENUM('admin','staf','user') NOT NULL DEFAULT 'user',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_user`),
  UNIQUE KEY `uq_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: tiket
DROP TABLE IF EXISTS `tiket`;
CREATE TABLE `tiket` (
  `id_tiket` INT NOT NULL AUTO_INCREMENT,
  `nama_tiket` VARCHAR(150) NOT NULL,
  `harga` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_tiket`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: transaksi
DROP TABLE IF EXISTS `transaksi`;
CREATE TABLE `transaksi` (
  `id_transaksi` INT NOT NULL AUTO_INCREMENT,
  `id_user` INT NOT NULL,
  `tgl_pemesanan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `total_harga` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  `metode_pembayaran` VARCHAR(100) DEFAULT NULL,
  `bukti_pembayaran` VARCHAR(255) DEFAULT NULL,
  `status` VARCHAR(50) DEFAULT 'Pending',
  PRIMARY KEY (`id_transaksi`),
  KEY `idx_transaksi_user` (`id_user`),
  CONSTRAINT `fk_transaksi_user` FOREIGN KEY (`id_user`) REFERENCES `users` (`id_user`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: detail_transaksi
DROP TABLE IF EXISTS `detail_transaksi`;
CREATE TABLE `detail_transaksi` (
  `id_detail` INT NOT NULL AUTO_INCREMENT,
  `id_transaksi` INT NOT NULL,
  `jenis_tiket` VARCHAR(100) NOT NULL,
  `quantity` INT NOT NULL DEFAULT 1,
  `sub_total` DECIMAL(12,2) NOT NULL DEFAULT 0.00,
  PRIMARY KEY (`id_detail`),
  KEY `idx_detail_transaksi` (`id_transaksi`),
  CONSTRAINT `fk_detail_transaksi` FOREIGN KEY (`id_transaksi`) REFERENCES `transaksi` (`id_transaksi`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Table: ulasan
DROP TABLE IF EXISTS `ulasan`;
CREATE TABLE `ulasan` (
  `id_ulasan` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(150) DEFAULT NULL,
  `email` VARCHAR(150) DEFAULT NULL,
  `no_telepon` VARCHAR(30) DEFAULT NULL,
  `ulasan` TEXT,
  `tgl_ulasan` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id_ulasan`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sample data: users
INSERT INTO `users` (`nama`,`username`,`password`,`email`,`no_telepon`,`level`) VALUES
('Administrator','admin', '$2y$10$XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX','admin@example.com','081234567890','admin'),
('Staf Operator','staf1', '$2y$10$YYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYYY','staf@example.com','081298765432','staf'),
('Pengunjung','user1', '$2y$10$ZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZZ','user1@example.com','081300011122','user');

-- Note: Passwords above are placeholders (bcrypt) -- replace with real hashed passwords as needed.

-- Sample data: tiket
INSERT INTO `tiket` (`nama_tiket`,`harga`) VALUES
('Tiket Dewasa', 50000.00),
('Tiket Anak-Anak', 30000.00),
('Tiket Keluarga (2D+1A)', 130000.00);

-- Sample data: transaksi + detail_transaksi
INSERT INTO `transaksi` (`id_user`,`tgl_pemesanan`,`total_harga`,`metode_pembayaran`,`bukti_pembayaran`,`status`) VALUES
(3, '2024-01-15 10:30:00', 130000.00, 'Transfer', 'bukti1.jpg', 'Lunas'),
(3, '2024-02-20 14:45:00', 100000.00, 'Bayar di Loket', NULL, 'Pending');

INSERT INTO `detail_transaksi` (`id_transaksi`,`jenis_tiket`,`quantity`,`sub_total`) VALUES
(1, 'Dewasa', 2, 100000.00),
(1, 'Anak-Anak', 1, 30000.00),
(2, 'Dewasa', 2, 100000.00);

-- Sample data: ulasan
INSERT INTO `ulasan` (`username`,`email`,`no_telepon`,`ulasan`,`tgl_ulasan`) VALUES
('user1','user1@example.com','081300011122','Tempatnya bersih dan pelayanan ramah.','2024-01-16 09:00:00'),
('anonymous','anon@example.com',NULL,'Anak-anak sangat senang bermain di kolam!','2024-02-21 11:15:00');

-- End of dump
