-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: localhost
-- Generation Time: Jun 13, 2025 at 07:59 PM
-- Server version: 10.4.28-MariaDB
-- PHP Version: 8.2.4

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `SIPINLAB`
--

-- --------------------------------------------------------

--
-- Table structure for table `alat`
--

CREATE TABLE `alat` (
  `id` int(11) NOT NULL,
  `kode` varchar(50) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `deskripsi` text DEFAULT NULL,
  `jumlah_total` int(11) NOT NULL DEFAULT 1,
  `jumlah_tersedia` int(11) NOT NULL DEFAULT 1,
  `kondisi` enum('baik','rusak_ringan','rusak_berat') DEFAULT 'baik',
  `id_kategori` int(11) DEFAULT NULL,
  `id_laboratorium` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `alat`
--

INSERT INTO `alat` (`id`, `kode`, `nama`, `deskripsi`, `jumlah_total`, `jumlah_tersedia`, `kondisi`, `id_kategori`, `id_laboratorium`, `created_at`, `updated_at`) VALUES
(1, 'NET001', 'Router Cisco', 'Router Cisco untuk praktikum jaringan', 10, 15, 'baik', 1, 1, '2025-06-13 16:03:27', '2025-06-13 17:58:06'),
(2, 'IOT001', 'Arduino Uno', 'Arduino untuk praktikum IoT', 30, 25, 'rusak_berat', 2, 2, '2025-06-13 16:03:27', '2025-06-13 17:48:23'),
(3, 'COMP001', 'Laptop Dell', 'Laptop untuk praktikum pemrograman', 15, 15, 'baik', 3, 3, '2025-06-13 16:03:27', '2025-06-13 16:03:27');

-- --------------------------------------------------------

--
-- Table structure for table `backup_log`
--

CREATE TABLE `backup_log` (
  `id` int(11) NOT NULL,
  `nama_file` varchar(255) NOT NULL,
  `ukuran_file` int(11) NOT NULL,
  `lokasi_penyimpanan` varchar(255) NOT NULL,
  `status` enum('sukses','gagal') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `detail_peminjaman`
--

CREATE TABLE `detail_peminjaman` (
  `id` int(11) NOT NULL,
  `id_peminjaman` int(11) NOT NULL,
  `id_alat` int(11) NOT NULL,
  `jumlah` int(11) NOT NULL DEFAULT 1,
  `status_kembali` enum('belum','sudah','rusak') DEFAULT 'belum',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `detail_peminjaman`
--

INSERT INTO `detail_peminjaman` (`id`, `id_peminjaman`, `id_alat`, `jumlah`, `status_kembali`, `created_at`, `updated_at`) VALUES
(1, 1, 2, 10, 'sudah', '2025-06-13 17:23:17', '2025-06-13 17:48:23'),
(2, 2, 1, 5, 'belum', '2025-06-13 17:53:37', '2025-06-13 17:53:37');

--
-- Triggers `detail_peminjaman`
--
DELIMITER $$
CREATE TRIGGER `before_insert_detail_peminjaman` BEFORE INSERT ON `detail_peminjaman` FOR EACH ROW BEGIN
    DECLARE alat_tersedia INT;
    DECLARE peminjam_id INT;
    DECLARE peminjam_status VARCHAR(20);
    
    -- Ambil jumlah alat tersedia
    SELECT jumlah_tersedia INTO alat_tersedia 
    FROM alat 
    WHERE id = NEW.id_alat;
    
    -- Ambil ID peminjam
    SELECT id_peminjam INTO peminjam_id 
    FROM peminjaman 
    WHERE id = NEW.id_peminjaman;
    
    -- Cek status peminjam (apakah memiliki peminjaman yang belum dikembalikan)
    SELECT COUNT(*) > 0 INTO peminjam_status 
    FROM peminjaman p
    JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
    WHERE p.id_peminjam = peminjam_id 
    AND p.status = 'dipinjam' 
    AND dp.status_kembali = 'belum';
    
    -- Cek ketersediaan alat
    IF alat_tersedia < NEW.jumlah THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Jumlah alat tidak mencukupi untuk dipinjam';
    END IF;
    
    -- Cek status peminjam
    IF peminjam_status = 1 THEN
        SIGNAL SQLSTATE '45000' 
        SET MESSAGE_TEXT = 'Peminjam masih memiliki alat yang belum dikembalikan';
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `kategori_alat`
--

CREATE TABLE `kategori_alat` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `kategori_alat`
--

INSERT INTO `kategori_alat` (`id`, `nama`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Networking', 'Peralatan jaringan komputer', '2025-06-13 16:03:27', '2025-06-13 16:03:27'),
(2, 'Sensor', 'Peralatan sensor untuk IoT', '2025-06-13 16:03:27', '2025-06-13 16:03:27'),
(3, 'Komputer', 'Perangkat komputer dan aksesoris', '2025-06-13 16:03:27', '2025-06-13 16:03:27');

-- --------------------------------------------------------

--
-- Table structure for table `laboratorium`
--

CREATE TABLE `laboratorium` (
  `id` int(11) NOT NULL,
  `nama` varchar(100) NOT NULL,
  `lokasi` varchar(100) NOT NULL,
  `keterangan` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laboratorium`
--

INSERT INTO `laboratorium` (`id`, `nama`, `lokasi`, `keterangan`, `created_at`, `updated_at`) VALUES
(1, 'Lab Jaringan', 'Gedung A Lantai 2', 'Laboratorium untuk praktikum jaringan komputer', '2025-06-13 16:03:27', '2025-06-13 16:03:27'),
(2, 'Lab IoT', 'Gedung B Lantai 1', 'Laboratorium untuk praktikum Internet of Things', '2025-06-13 16:03:27', '2025-06-13 16:03:27'),
(3, 'Lab Pemrograman', 'Gedung A Lantai 3', 'Laboratorium untuk praktikum pemrograman', '2025-06-13 16:03:27', '2025-06-13 16:03:27');

-- --------------------------------------------------------

--
-- Table structure for table `laporan_kerusakan`
--

CREATE TABLE `laporan_kerusakan` (
  `id` int(11) NOT NULL,
  `id_alat` int(11) NOT NULL,
  `id_pelapor` int(11) NOT NULL,
  `deskripsi_kerusakan` text NOT NULL,
  `tingkat_kerusakan` enum('ringan','sedang','berat') DEFAULT 'ringan',
  `tanggal_laporan` datetime NOT NULL,
  `status` enum('menunggu','diproses','selesai') DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `laporan_kerusakan`
--

INSERT INTO `laporan_kerusakan` (`id`, `id_alat`, `id_pelapor`, `deskripsi_kerusakan`, `tingkat_kerusakan`, `tanggal_laporan`, `status`, `created_at`, `updated_at`) VALUES
(1, 2, 2, 'Pecah, gabisa dipake, ancur lah pokoknya', 'berat', '2025-06-13 19:35:57', 'selesai', '2025-06-13 17:35:57', '2025-06-13 17:37:40'),
(2, 2, 2, 'Pecah, gabisa dipake, ancur lah pokoknya', 'berat', '2025-06-13 19:37:49', 'menunggu', '2025-06-13 17:37:49', '2025-06-13 17:37:49');

-- --------------------------------------------------------

--
-- Table structure for table `log_aktivitas`
--

CREATE TABLE `log_aktivitas` (
  `id` int(11) NOT NULL,
  `id_pengguna` int(11) DEFAULT NULL,
  `aktivitas` varchar(255) NOT NULL,
  `detail` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `log_aktivitas`
--

INSERT INTO `log_aktivitas` (`id`, `id_pengguna`, `aktivitas`, `detail`, `created_at`) VALUES
(1, 2, 'Login', 'Login berhasil', '2025-06-13 16:38:24'),
(2, 2, 'Logout', 'Logout berhasil', '2025-06-13 16:50:49'),
(3, 5, 'Sistem', 'Reset password admin', '2025-06-13 17:11:27'),
(4, 5, 'Sistem', 'Reset password admin', '2025-06-13 17:13:07'),
(5, 5, 'Login', 'Login berhasil', '2025-06-13 17:13:17'),
(6, 5, 'Mengupdate alat', 'Alat: Arduino Uno (IOT001)', '2025-06-13 17:14:02'),
(7, 5, 'Logout', 'Logout berhasil', '2025-06-13 17:14:38'),
(8, 2, 'Login', 'Login berhasil', '2025-06-13 17:14:47'),
(9, 2, 'Mengajukan peminjaman', 'Peminjaman ID: 1', '2025-06-13 17:23:17'),
(10, 5, 'Login', 'Login berhasil', '2025-06-13 17:29:00'),
(11, 5, 'Menyetujui peminjaman', 'Peminjaman ID: 1', '2025-06-13 17:29:08'),
(12, 2, 'Melaporkan kerusakan alat', 'Alat ID: 2, Tingkat: berat', '2025-06-13 17:35:57'),
(13, 5, 'Mengupdate alat', 'Alat: Arduino Uno (IOT001)', '2025-06-13 17:36:51'),
(14, 5, 'Mengupdate status laporan kerusakan', 'Laporan ID: 1, Status: selesai', '2025-06-13 17:37:40'),
(15, 2, 'Melaporkan kerusakan alat', 'Alat ID: 2, Tingkat: berat', '2025-06-13 17:37:49'),
(16, 5, 'Mengembalikan alat', 'Peminjaman ID: 1', '2025-06-13 17:48:23'),
(17, 2, 'Update Profil', 'Profil berhasil diperbarui', '2025-06-13 17:53:06'),
(18, 2, 'Mengajukan peminjaman', 'Peminjaman ID: 2', '2025-06-13 17:53:37'),
(19, NULL, 'Membatalkan peminjaman', 'Peminjaman ID: 2', '2025-06-13 17:58:06'),
(20, 2, 'Pembatalan Peminjaman', 'Membatalkan peminjaman ID: 2 - Alat: Router Cisco (5 unit)', '2025-06-13 17:58:06');

-- --------------------------------------------------------

--
-- Table structure for table `peminjaman`
--

CREATE TABLE `peminjaman` (
  `id` int(11) NOT NULL,
  `id_peminjam` int(11) NOT NULL,
  `tanggal_pinjam` datetime NOT NULL,
  `tanggal_kembali` datetime DEFAULT NULL,
  `status` enum('menunggu','dipinjam','dikembalikan','terlambat','dibatalkan') DEFAULT 'menunggu',
  `keterangan` text DEFAULT NULL,
  `disetujui_oleh` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `peminjaman`
--

INSERT INTO `peminjaman` (`id`, `id_peminjam`, `tanggal_pinjam`, `tanggal_kembali`, `status`, `keterangan`, `disetujui_oleh`, `created_at`, `updated_at`) VALUES
(1, 2, '2025-06-13 19:15:00', '2025-06-14 00:48:23', 'dikembalikan', 'Praktikum IOT', 5, '2025-06-13 17:23:17', '2025-06-13 17:48:23'),
(2, 2, '2025-06-13 19:53:00', NULL, 'dibatalkan', '', NULL, '2025-06-13 17:53:37', '2025-06-13 17:58:06');

--
-- Triggers `peminjaman`
--
DELIMITER $$
CREATE TRIGGER `after_update_peminjaman` AFTER UPDATE ON `peminjaman` FOR EACH ROW BEGIN
    DECLARE alat_id INT;
    DECLARE jumlah_dipinjam INT;
    
    -- Jika status berubah menjadi dipinjam
    IF NEW.status = 'dipinjam' AND OLD.status = 'menunggu' THEN
        -- Ambil data alat yang dipinjam
        SELECT id_alat, jumlah INTO alat_id, jumlah_dipinjam
        FROM detail_peminjaman
        WHERE id_peminjaman = NEW.id;
        
        -- Kurangi jumlah alat tersedia
        UPDATE alat
        SET jumlah_tersedia = jumlah_tersedia - jumlah_dipinjam
        WHERE id = alat_id;
    END IF;
    
    -- Jika status berubah menjadi dibatalkan
    IF NEW.status = 'dibatalkan' AND OLD.status = 'menunggu' THEN
        -- Catat log aktivitas
        INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail)
        VALUES (NEW.disetujui_oleh, 'Membatalkan peminjaman', CONCAT('Peminjaman ID: ', NEW.id));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `pengguna`
--

CREATE TABLE `pengguna` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `nama_lengkap` varchar(100) NOT NULL,
  `npm` varchar(20) DEFAULT NULL,
  `role` enum('admin','mahasiswa') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `email` varchar(100) DEFAULT NULL,
  `no_telp` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `pengguna`
--

INSERT INTO `pengguna` (`id`, `username`, `password`, `nama_lengkap`, `npm`, `role`, `created_at`, `updated_at`, `email`, `no_telp`) VALUES
(2, 'aathifahdc', '$2y$10$uKcvKvkBLyXBT0mf1LnbqOtwelrANv168x3qhS/tM0jQf4vJ5ijaW', 'Aathifah Dihyan Calysta', '2317051020', 'mahasiswa', '2025-06-13 16:38:05', '2025-06-13 17:53:06', 'aathifah800@gmail.com', '081234567'),
(5, 'admin', '$2y$10$HFGjrgHAAuCm6QReQ8vEvO68A6Vd12Frqcdi105FDJs4tgL4.WlAq', 'Administrator', NULL, 'admin', '2025-06-13 17:07:51', '2025-06-13 17:13:07', NULL, NULL);

--
-- Indexes for dumped tables
--

--
-- Indexes for table `alat`
--
ALTER TABLE `alat`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `kode` (`kode`),
  ADD KEY `id_kategori` (`id_kategori`),
  ADD KEY `id_laboratorium` (`id_laboratorium`);

--
-- Indexes for table `backup_log`
--
ALTER TABLE `backup_log`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `detail_peminjaman_ibfk_1` (`id_peminjaman`),
  ADD KEY `detail_peminjaman_ibfk_2` (`id_alat`);

--
-- Indexes for table `kategori_alat`
--
ALTER TABLE `kategori_alat`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `laboratorium`
--
ALTER TABLE `laboratorium`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `laporan_kerusakan`
--
ALTER TABLE `laporan_kerusakan`
  ADD PRIMARY KEY (`id`),
  ADD KEY `id_pelapor` (`id_pelapor`),
  ADD KEY `fk_laporan_alat` (`id_alat`);

--
-- Indexes for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_log_aktivitas_user` (`id_pengguna`);

--
-- Indexes for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD PRIMARY KEY (`id`),
  ADD KEY `disetujui_oleh` (`disetujui_oleh`),
  ADD KEY `idx_peminjaman_status` (`status`),
  ADD KEY `idx_peminjaman_user` (`id_peminjam`);

--
-- Indexes for table `pengguna`
--
ALTER TABLE `pengguna`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `npm` (`npm`),
  ADD KEY `idx_username` (`username`),
  ADD KEY `idx_npm` (`npm`),
  ADD KEY `idx_role` (`role`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `alat`
--
ALTER TABLE `alat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `backup_log`
--
ALTER TABLE `backup_log`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `kategori_alat`
--
ALTER TABLE `kategori_alat`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `laboratorium`
--
ALTER TABLE `laboratorium`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `laporan_kerusakan`
--
ALTER TABLE `laporan_kerusakan`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=21;

--
-- AUTO_INCREMENT for table `peminjaman`
--
ALTER TABLE `peminjaman`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=3;

--
-- AUTO_INCREMENT for table `pengguna`
--
ALTER TABLE `pengguna`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `alat`
--
ALTER TABLE `alat`
  ADD CONSTRAINT `alat_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_alat` (`id`),
  ADD CONSTRAINT `alat_ibfk_2` FOREIGN KEY (`id_laboratorium`) REFERENCES `laboratorium` (`id`);

--
-- Constraints for table `detail_peminjaman`
--
ALTER TABLE `detail_peminjaman`
  ADD CONSTRAINT `detail_peminjaman_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `detail_peminjaman_ibfk_2` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `laporan_kerusakan`
--
ALTER TABLE `laporan_kerusakan`
  ADD CONSTRAINT `fk_laporan_alat` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`) ON UPDATE CASCADE,
  ADD CONSTRAINT `laporan_kerusakan_ibfk_1` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`),
  ADD CONSTRAINT `laporan_kerusakan_ibfk_2` FOREIGN KEY (`id_pelapor`) REFERENCES `pengguna` (`id`);

--
-- Constraints for table `log_aktivitas`
--
ALTER TABLE `log_aktivitas`
  ADD CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id`);

--
-- Constraints for table `peminjaman`
--
ALTER TABLE `peminjaman`
  ADD CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_peminjam`) REFERENCES `pengguna` (`id`),
  ADD CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`disetujui_oleh`) REFERENCES `pengguna` (`id`);
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
