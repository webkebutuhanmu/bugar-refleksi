-- phpMyAdmin SQL Dump
-- version 5.2.0
-- https://www.phpmyadmin.net/
--
-- Host: localhost:3306
-- Generation Time: Feb 08, 2026 at 05:09 PM
-- Server version: 8.0.30
-- PHP Version: 8.1.10

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `bugar_refleksi`
--

-- --------------------------------------------------------

--
-- Table structure for table `beds`
--

CREATE TABLE `beds` (
  `id` int NOT NULL,
  `branch_id` int NOT NULL,
  `nomor_bed` varchar(10) NOT NULL,
  `tipe` enum('Laki-laki','Perempuan','Atas','Bawah','Regular') DEFAULT 'Regular',
  `status` enum('kosong','terisi') DEFAULT 'kosong'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `beds`
--

INSERT INTO `beds` (`id`, `branch_id`, `nomor_bed`, `tipe`, `status`) VALUES
(1, 1, 'L1', 'Laki-laki', 'kosong'),
(2, 1, 'L2', 'Laki-laki', 'kosong'),
(3, 1, 'L3', 'Laki-laki', 'kosong'),
(4, 1, 'P1', 'Perempuan', 'kosong'),
(5, 1, 'P2', 'Perempuan', 'kosong'),
(6, 1, 'P3', 'Perempuan', 'kosong'),
(7, 2, 'A1', 'Atas', 'kosong'),
(8, 2, 'A2', 'Atas', 'kosong'),
(9, 2, 'A3', 'Atas', 'kosong'),
(10, 2, 'A4', 'Atas', 'kosong'),
(11, 2, 'B1', 'Bawah', 'kosong'),
(12, 2, 'B2', 'Bawah', 'kosong'),
(13, 2, 'B3', 'Bawah', 'kosong'),
(14, 2, 'B4', 'Bawah', 'kosong');

-- --------------------------------------------------------

--
-- Table structure for table `branches`
--

CREATE TABLE `branches` (
  `id` int NOT NULL,
  `nama_cabang` varchar(100) NOT NULL,
  `alamat` text,
  `latitude` decimal(10,8) DEFAULT NULL,
  `longitude` decimal(11,8) DEFAULT NULL,
  `pin` varchar(6) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `branches`
--

INSERT INTO `branches` (`id`, `nama_cabang`, `alamat`, `latitude`, `longitude`, `pin`) VALUES
(1, 'Cabang Pusat', 'Jl. Merdeka No. 123, Jakarta Pusat', '-6.20000000', '106.81666600', '123456'),
(2, 'Cabang Selatan', 'Jl. Gatot Subroto No. 45, Jakarta Selatan', '-6.22501400', '106.80937200', '654321');

-- --------------------------------------------------------

--
-- Table structure for table `kasir_attendance`
--

CREATE TABLE `kasir_attendance` (
  `id` int NOT NULL,
  `session_id` varchar(50) DEFAULT NULL,
  `kasir_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `tanggal` date NOT NULL,
  `waktu_masuk` datetime NOT NULL,
  `waktu_keluar` datetime DEFAULT NULL,
  `status` enum('aktif','selesai') DEFAULT 'aktif',
  `omset_shift` decimal(10,2) DEFAULT '0.00',
  `total_transaksi_shift` int DEFAULT '0'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `kasir_attendance`
--

INSERT INTO `kasir_attendance` (`id`, `session_id`, `kasir_id`, `branch_id`, `tanggal`, `waktu_masuk`, `waktu_keluar`, `status`, `omset_shift`, `total_transaksi_shift`) VALUES
(1, 'shift_69889efe6c5504.58329321', 3, 1, '2026-02-08', '2026-02-08 14:34:38', '2026-02-08 21:37:30', 'selesai', '50000.00', 1),
(2, 'shift_6988a07045a223.48167730', 3, 1, '2026-02-08', '2026-02-08 14:40:48', '2026-02-08 21:59:24', 'selesai', '50000.00', 1),
(3, 'shift_6988a4d191c582.94398129', 3, 1, '2026-02-08', '2026-02-08 14:59:29', '2026-02-08 21:59:48', 'selesai', '50000.00', 1),
(4, 'shift_6988a8f882b081.74829160', 6, 1, '2026-02-08', '2026-02-08 15:17:12', '2026-02-08 23:34:23', 'selesai', '120000.00', 1),
(5, 'shift_6988bb16cb0941.28502969', 6, 2, '2026-02-08', '2026-02-08 16:34:30', '2026-02-08 23:36:38', 'selesai', '50000.00', 1),
(6, 'shift_6988bdeeb12bc3.11381044', 3, 1, '2026-02-08', '2026-02-08 16:46:38', '2026-02-08 23:52:30', 'selesai', '50000.00', 1),
(7, 'shift_6988bf65797614.45204269', 6, 2, '2026-02-08', '2026-02-08 16:52:53', NULL, 'aktif', '0.00', 0),
(8, 'shift_6988c222c90298.89869134', 3, 1, '2026-02-08', '2026-02-08 17:04:34', '2026-02-09 00:04:52', 'selesai', '0.00', 0),
(9, 'shift_6988c23a8d69a6.26973724', 3, 2, '2026-02-08', '2026-02-08 17:04:58', '2026-02-09 00:05:28', 'selesai', '0.00', 0);

-- --------------------------------------------------------

--
-- Table structure for table `packages`
--

CREATE TABLE `packages` (
  `id` int NOT NULL,
  `nama_paket` varchar(100) NOT NULL,
  `durasi_menit` int NOT NULL DEFAULT '60',
  `harga` decimal(10,2) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `packages`
--

INSERT INTO `packages` (`id`, `nama_paket`, `durasi_menit`, `harga`) VALUES
(1, 'Refleksi Kaki', 45, '50000.00'),
(2, 'Full Body', 90, '120000.00');

-- --------------------------------------------------------

--
-- Table structure for table `shift_logs`
--

CREATE TABLE `shift_logs` (
  `id` int NOT NULL,
  `attendance_id` int NOT NULL,
  `kasir_id` int NOT NULL,
  `branch_id` int NOT NULL,
  `waktu_buka` datetime NOT NULL,
  `waktu_tutup` datetime DEFAULT NULL,
  `omset_shift` decimal(10,2) DEFAULT '0.00',
  `total_transaksi` int DEFAULT '0',
  `catatan_buka` text,
  `catatan_tutup` text
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `shift_logs`
--

INSERT INTO `shift_logs` (`id`, `attendance_id`, `kasir_id`, `branch_id`, `waktu_buka`, `waktu_tutup`, `omset_shift`, `total_transaksi`, `catatan_buka`, `catatan_tutup`) VALUES
(1, 1, 3, 1, '2026-02-08 14:34:38', '2026-02-08 21:37:30', '50000.00', 1, NULL, ''),
(2, 2, 3, 1, '2026-02-08 14:40:48', '2026-02-08 21:59:24', '50000.00', 1, NULL, ''),
(3, 3, 3, 1, '2026-02-08 14:59:29', '2026-02-08 21:59:48', '50000.00', 1, NULL, ''),
(4, 4, 6, 1, '2026-02-08 15:17:12', '2026-02-08 23:34:23', '120000.00', 1, NULL, ''),
(5, 5, 6, 2, '2026-02-08 16:34:30', '2026-02-08 23:36:38', '50000.00', 1, NULL, ''),
(6, 6, 3, 1, '2026-02-08 16:46:38', '2026-02-08 23:52:30', '50000.00', 1, NULL, ''),
(7, 7, 6, 2, '2026-02-08 16:52:53', NULL, '0.00', 0, NULL, NULL),
(8, 8, 3, 1, '2026-02-08 17:04:34', '2026-02-09 00:04:52', '0.00', 0, NULL, ''),
(9, 9, 3, 2, '2026-02-08 17:04:58', '2026-02-09 00:05:28', '0.00', 0, NULL, '');

-- --------------------------------------------------------

--
-- Table structure for table `transactions`
--

CREATE TABLE `transactions` (
  `id` int NOT NULL,
  `branch_id` int DEFAULT NULL,
  `bed_id` int DEFAULT NULL,
  `kasir_id` int DEFAULT NULL,
  `terapis_id` int DEFAULT NULL,
  `package_id` int DEFAULT NULL,
  `nama_pelanggan` varchar(100) DEFAULT NULL,
  `no_hp_pelanggan` varchar(20) DEFAULT NULL,
  `total_bayar` decimal(10,2) DEFAULT NULL,
  `tanggal_transaksi` date DEFAULT NULL,
  `waktu_selesai` datetime DEFAULT NULL,
  `status` enum('proses','selesai','batal') DEFAULT 'proses',
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `transactions`
--

INSERT INTO `transactions` (`id`, `branch_id`, `bed_id`, `kasir_id`, `terapis_id`, `package_id`, `nama_pelanggan`, `no_hp_pelanggan`, `total_bayar`, `tanggal_transaksi`, `waktu_selesai`, `status`, `created_at`) VALUES
(1, 2, NULL, 3, 4, 2, 'Yusuf', '081264619290', '120000.00', '2026-02-08', '2026-02-08 01:13:22', 'selesai', '2026-02-07 18:13:22'),
(2, 1, NULL, 3, 5, 1, 'Yunita', '089898928290', '50000.00', '2026-02-08', '2026-02-08 01:15:21', 'selesai', '2026-02-07 18:15:21'),
(3, 1, NULL, 3, 5, 1, 'Meisuri', '0819283920', '50000.00', '2026-02-08', '2026-02-08 22:20:16', 'selesai', '2026-02-08 14:35:16'),
(4, 1, 1, 6, 4, 2, 'reza', '08938329299', '120000.00', '2026-02-08', '2026-02-08 23:47:26', 'selesai', '2026-02-08 16:33:02'),
(5, 2, 7, 6, 5, 1, 'serly', '090101010', '50000.00', '2026-02-08', '2026-02-08 23:47:22', 'selesai', '2026-02-08 16:35:50');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) DEFAULT NULL,
  `role` enum('owner','admin','kasir','terapis') NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_0900_ai_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `nama_lengkap`, `role`) VALUES
(1, 'owner', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Bos Besar', 'owner'),
(2, 'admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Mba Keuangan', 'admin'),
(3, 'kasir1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Siti Kasir', 'kasir'),
(4, 'terapis1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Budi Pijat', 'terapis'),
(5, 'terapis2', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Ani Refleksi', 'terapis'),
(6, 'nelvi', '$2y$10$NZ9kh.K3oXdwLQxmDhb0peGvCm..X.tN9tK8YVjkI6GRHQMwAi5TO', 'nelvi', 'kasir');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `beds`
--
ALTER TABLE `beds`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `branches`
--
ALTER TABLE `branches`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `kasir_attendance`
--
ALTER TABLE `kasir_attendance`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kasir_id` (`kasir_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `packages`
--
ALTER TABLE `packages`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `shift_logs`
--
ALTER TABLE `shift_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `attendance_id` (`attendance_id`),
  ADD KEY `kasir_id` (`kasir_id`),
  ADD KEY `branch_id` (`branch_id`);

--
-- Indexes for table `transactions`
--
ALTER TABLE `transactions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `branch_id` (`branch_id`),
  ADD KEY `kasir_id` (`kasir_id`),
  ADD KEY `terapis_id` (`terapis_id`),
  ADD KEY `package_id` (`package_id`),
  ADD KEY `bed_id` (`bed_id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `beds`
--
ALTER TABLE `beds`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=15;

--
-- AUTO_INCREMENT for table `branches`
--
ALTER TABLE `branches`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `kasir_attendance`
--
ALTER TABLE `kasir_attendance`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `packages`
--
ALTER TABLE `packages`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `shift_logs`
--
ALTER TABLE `shift_logs`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=10;

--
-- AUTO_INCREMENT for table `transactions`
--
ALTER TABLE `transactions`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `beds`
--
ALTER TABLE `beds`
  ADD CONSTRAINT `beds_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `kasir_attendance`
--
ALTER TABLE `kasir_attendance`
  ADD CONSTRAINT `kasir_attendance_ibfk_1` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `kasir_attendance_ibfk_2` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `shift_logs`
--
ALTER TABLE `shift_logs`
  ADD CONSTRAINT `shift_logs_ibfk_1` FOREIGN KEY (`attendance_id`) REFERENCES `kasir_attendance` (`id`),
  ADD CONSTRAINT `shift_logs_ibfk_2` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `shift_logs_ibfk_3` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`);

--
-- Constraints for table `transactions`
--
ALTER TABLE `transactions`
  ADD CONSTRAINT `transactions_ibfk_1` FOREIGN KEY (`branch_id`) REFERENCES `branches` (`id`),
  ADD CONSTRAINT `transactions_ibfk_2` FOREIGN KEY (`kasir_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_ibfk_3` FOREIGN KEY (`terapis_id`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `transactions_ibfk_4` FOREIGN KEY (`package_id`) REFERENCES `packages` (`id`),
  ADD CONSTRAINT `transactions_ibfk_5` FOREIGN KEY (`bed_id`) REFERENCES `beds` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
