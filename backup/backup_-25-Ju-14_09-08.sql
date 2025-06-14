-- MySQL dump 10.13  Distrib 8.0.30, for Win64 (x86_64)
--
-- Host: localhost    Database: sipinlab
-- ------------------------------------------------------
-- Server version	8.0.30

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `alat`
--

DROP TABLE IF EXISTS `alat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `alat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `kode` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `deskripsi` text COLLATE utf8mb4_general_ci,
  `jumlah_total` int NOT NULL DEFAULT '1',
  `jumlah_tersedia` int NOT NULL DEFAULT '1',
  `kondisi` enum('baik','rusak_ringan','rusak_berat') COLLATE utf8mb4_general_ci DEFAULT 'baik',
  `id_kategori` int DEFAULT NULL,
  `id_laboratorium` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `kode` (`kode`),
  KEY `id_kategori` (`id_kategori`),
  KEY `id_laboratorium` (`id_laboratorium`),
  CONSTRAINT `alat_ibfk_1` FOREIGN KEY (`id_kategori`) REFERENCES `kategori_alat` (`id`),
  CONSTRAINT `alat_ibfk_2` FOREIGN KEY (`id_laboratorium`) REFERENCES `laboratorium` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `alat`
--

LOCK TABLES `alat` WRITE;
/*!40000 ALTER TABLE `alat` DISABLE KEYS */;
INSERT INTO `alat` VALUES (1,'NET001','Router Cisco','Router Cisco untuk praktikum jaringan',10,15,'baik',1,1,'2025-06-13 16:03:27','2025-06-13 17:58:06'),(2,'IOT001','Arduino Uno','Arduino untuk praktikum IoT',30,25,'rusak_berat',2,2,'2025-06-13 16:03:27','2025-06-13 17:48:23'),(3,'COMP001','Laptop Dell','Laptop untuk praktikum pemrograman',15,15,'baik',3,3,'2025-06-13 16:03:27','2025-06-13 16:03:27');
/*!40000 ALTER TABLE `alat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `backup_log`
--

DROP TABLE IF EXISTS `backup_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `backup_log` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama_file` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `ukuran_file` int NOT NULL,
  `lokasi_penyimpanan` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `status` enum('sukses','gagal') COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `backup_log`
--

LOCK TABLES `backup_log` WRITE;
/*!40000 ALTER TABLE `backup_log` DISABLE KEYS */;
/*!40000 ALTER TABLE `backup_log` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `detail_peminjaman`
--

DROP TABLE IF EXISTS `detail_peminjaman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `detail_peminjaman` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_peminjaman` int NOT NULL,
  `id_alat` int NOT NULL,
  `jumlah` int NOT NULL DEFAULT '1',
  `status_kembali` enum('belum','sudah','rusak') COLLATE utf8mb4_general_ci DEFAULT 'belum',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `detail_peminjaman_ibfk_1` (`id_peminjaman`),
  KEY `detail_peminjaman_ibfk_2` (`id_alat`),
  CONSTRAINT `detail_peminjaman_ibfk_1` FOREIGN KEY (`id_peminjaman`) REFERENCES `peminjaman` (`id`) ON DELETE CASCADE,
  CONSTRAINT `detail_peminjaman_ibfk_2` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `detail_peminjaman`
--

LOCK TABLES `detail_peminjaman` WRITE;
/*!40000 ALTER TABLE `detail_peminjaman` DISABLE KEYS */;
INSERT INTO `detail_peminjaman` VALUES (1,1,2,10,'sudah','2025-06-13 17:23:17','2025-06-13 17:48:23'),(2,2,1,5,'belum','2025-06-13 17:53:37','2025-06-13 17:53:37');
/*!40000 ALTER TABLE `detail_peminjaman` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `before_insert_detail_peminjaman` BEFORE INSERT ON `detail_peminjaman` FOR EACH ROW BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `kategori_alat`
--

DROP TABLE IF EXISTS `kategori_alat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `kategori_alat` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `kategori_alat`
--

LOCK TABLES `kategori_alat` WRITE;
/*!40000 ALTER TABLE `kategori_alat` DISABLE KEYS */;
INSERT INTO `kategori_alat` VALUES (1,'Networking','Peralatan jaringan komputer','2025-06-13 16:03:27','2025-06-13 16:03:27'),(2,'Sensor','Peralatan sensor untuk IoT','2025-06-13 16:03:27','2025-06-13 16:03:27'),(3,'Komputer','Perangkat komputer dan aksesoris','2025-06-13 16:03:27','2025-06-13 16:03:27');
/*!40000 ALTER TABLE `kategori_alat` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laboratorium`
--

DROP TABLE IF EXISTS `laboratorium`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laboratorium` (
  `id` int NOT NULL AUTO_INCREMENT,
  `nama` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `lokasi` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `keterangan` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laboratorium`
--

LOCK TABLES `laboratorium` WRITE;
/*!40000 ALTER TABLE `laboratorium` DISABLE KEYS */;
INSERT INTO `laboratorium` VALUES (1,'Lab Jaringan','Gedung A Lantai 2','Laboratorium untuk praktikum jaringan komputer','2025-06-13 16:03:27','2025-06-13 16:03:27'),(2,'Lab IoT','Gedung B Lantai 1','Laboratorium untuk praktikum Internet of Things','2025-06-13 16:03:27','2025-06-13 16:03:27'),(3,'Lab Pemrograman','Gedung A Lantai 3','Laboratorium untuk praktikum pemrograman','2025-06-13 16:03:27','2025-06-13 16:03:27');
/*!40000 ALTER TABLE `laboratorium` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `laporan_kerusakan`
--

DROP TABLE IF EXISTS `laporan_kerusakan`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `laporan_kerusakan` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_alat` int NOT NULL,
  `id_pelapor` int NOT NULL,
  `deskripsi_kerusakan` text COLLATE utf8mb4_general_ci NOT NULL,
  `tingkat_kerusakan` enum('ringan','sedang','berat') COLLATE utf8mb4_general_ci DEFAULT 'ringan',
  `tanggal_laporan` datetime NOT NULL,
  `status` enum('menunggu','diproses','selesai') COLLATE utf8mb4_general_ci DEFAULT 'menunggu',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `id_pelapor` (`id_pelapor`),
  KEY `fk_laporan_alat` (`id_alat`),
  CONSTRAINT `fk_laporan_alat` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`) ON UPDATE CASCADE,
  CONSTRAINT `laporan_kerusakan_ibfk_1` FOREIGN KEY (`id_alat`) REFERENCES `alat` (`id`),
  CONSTRAINT `laporan_kerusakan_ibfk_2` FOREIGN KEY (`id_pelapor`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `laporan_kerusakan`
--

LOCK TABLES `laporan_kerusakan` WRITE;
/*!40000 ALTER TABLE `laporan_kerusakan` DISABLE KEYS */;
INSERT INTO `laporan_kerusakan` VALUES (1,2,2,'Pecah, gabisa dipake, ancur lah pokoknya','berat','2025-06-13 19:35:57','selesai','2025-06-13 17:35:57','2025-06-13 17:37:40'),(2,2,2,'Pecah, gabisa dipake, ancur lah pokoknya','berat','2025-06-13 19:37:49','menunggu','2025-06-13 17:37:49','2025-06-13 17:37:49');
/*!40000 ALTER TABLE `laporan_kerusakan` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `log_aktivitas`
--

DROP TABLE IF EXISTS `log_aktivitas`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `log_aktivitas` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_pengguna` int DEFAULT NULL,
  `aktivitas` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `detail` text COLLATE utf8mb4_general_ci,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_log_aktivitas_user` (`id_pengguna`),
  CONSTRAINT `log_aktivitas_ibfk_1` FOREIGN KEY (`id_pengguna`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `log_aktivitas`
--

LOCK TABLES `log_aktivitas` WRITE;
/*!40000 ALTER TABLE `log_aktivitas` DISABLE KEYS */;
INSERT INTO `log_aktivitas` VALUES (1,2,'Login','Login berhasil','2025-06-13 16:38:24'),(2,2,'Logout','Logout berhasil','2025-06-13 16:50:49'),(3,5,'Sistem','Reset password admin','2025-06-13 17:11:27'),(4,5,'Sistem','Reset password admin','2025-06-13 17:13:07'),(5,5,'Login','Login berhasil','2025-06-13 17:13:17'),(6,5,'Mengupdate alat','Alat: Arduino Uno (IOT001)','2025-06-13 17:14:02'),(7,5,'Logout','Logout berhasil','2025-06-13 17:14:38'),(8,2,'Login','Login berhasil','2025-06-13 17:14:47'),(9,2,'Mengajukan peminjaman','Peminjaman ID: 1','2025-06-13 17:23:17'),(10,5,'Login','Login berhasil','2025-06-13 17:29:00'),(11,5,'Menyetujui peminjaman','Peminjaman ID: 1','2025-06-13 17:29:08'),(12,2,'Melaporkan kerusakan alat','Alat ID: 2, Tingkat: berat','2025-06-13 17:35:57'),(13,5,'Mengupdate alat','Alat: Arduino Uno (IOT001)','2025-06-13 17:36:51'),(14,5,'Mengupdate status laporan kerusakan','Laporan ID: 1, Status: selesai','2025-06-13 17:37:40'),(15,2,'Melaporkan kerusakan alat','Alat ID: 2, Tingkat: berat','2025-06-13 17:37:49'),(16,5,'Mengembalikan alat','Peminjaman ID: 1','2025-06-13 17:48:23'),(17,2,'Update Profil','Profil berhasil diperbarui','2025-06-13 17:53:06'),(18,2,'Mengajukan peminjaman','Peminjaman ID: 2','2025-06-13 17:53:37'),(19,NULL,'Membatalkan peminjaman','Peminjaman ID: 2','2025-06-13 17:58:06'),(20,2,'Pembatalan Peminjaman','Membatalkan peminjaman ID: 2 - Alat: Router Cisco (5 unit)','2025-06-13 17:58:06'),(22,6,'Login','Login berhasil','2025-06-13 19:55:37'),(23,6,'Logout','Logout berhasil','2025-06-13 20:01:23'),(24,5,'Login','Login berhasil','2025-06-13 20:01:40'),(25,5,'Login','Login berhasil','2025-06-13 20:13:27'),(26,5,'Login','Login berhasil','2025-06-14 01:54:59');
/*!40000 ALTER TABLE `log_aktivitas` ENABLE KEYS */;
UNLOCK TABLES;

--
-- Table structure for table `peminjaman`
--

DROP TABLE IF EXISTS `peminjaman`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `peminjaman` (
  `id` int NOT NULL AUTO_INCREMENT,
  `id_peminjam` int NOT NULL,
  `tanggal_pinjam` datetime NOT NULL,
  `tanggal_kembali` datetime DEFAULT NULL,
  `status` enum('menunggu','dipinjam','dikembalikan','terlambat','dibatalkan') COLLATE utf8mb4_general_ci DEFAULT 'menunggu',
  `keterangan` text COLLATE utf8mb4_general_ci,
  `disetujui_oleh` int DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `disetujui_oleh` (`disetujui_oleh`),
  KEY `idx_peminjaman_status` (`status`),
  KEY `idx_peminjaman_user` (`id_peminjam`),
  CONSTRAINT `peminjaman_ibfk_1` FOREIGN KEY (`id_peminjam`) REFERENCES `pengguna` (`id`),
  CONSTRAINT `peminjaman_ibfk_2` FOREIGN KEY (`disetujui_oleh`) REFERENCES `pengguna` (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `peminjaman`
--

LOCK TABLES `peminjaman` WRITE;
/*!40000 ALTER TABLE `peminjaman` DISABLE KEYS */;
INSERT INTO `peminjaman` VALUES (1,2,'2025-06-13 19:15:00','2025-06-14 00:48:23','dikembalikan','Praktikum IOT',5,'2025-06-13 17:23:17','2025-06-13 17:48:23'),(2,2,'2025-06-13 19:53:00',NULL,'dibatalkan','',NULL,'2025-06-13 17:53:37','2025-06-13 17:58:06');
/*!40000 ALTER TABLE `peminjaman` ENABLE KEYS */;
UNLOCK TABLES;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_0900_ai_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = 'NO_AUTO_VALUE_ON_ZERO' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`root`@`localhost`*/ /*!50003 TRIGGER `after_update_peminjaman` AFTER UPDATE ON `peminjaman` FOR EACH ROW BEGIN
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
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `pengguna`
--

DROP TABLE IF EXISTS `pengguna`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `pengguna` (
  `id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(50) COLLATE utf8mb4_general_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_general_ci NOT NULL,
  `nama_lengkap` varchar(100) COLLATE utf8mb4_general_ci NOT NULL,
  `npm` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `role` enum('admin','mahasiswa') COLLATE utf8mb4_general_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `email` varchar(100) COLLATE utf8mb4_general_ci DEFAULT NULL,
  `no_telp` varchar(20) COLLATE utf8mb4_general_ci DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `username` (`username`),
  UNIQUE KEY `npm` (`npm`),
  KEY `idx_username` (`username`),
  KEY `idx_npm` (`npm`),
  KEY `idx_role` (`role`)
) ENGINE=InnoDB AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping data for table `pengguna`
--

LOCK TABLES `pengguna` WRITE;
/*!40000 ALTER TABLE `pengguna` DISABLE KEYS */;
INSERT INTO `pengguna` VALUES (2,'aathifahdc','$2y$10$uKcvKvkBLyXBT0mf1LnbqOtwelrANv168x3qhS/tM0jQf4vJ5ijaW','Aathifah Dihyan Calysta','2317051020','mahasiswa','2025-06-13 16:38:05','2025-06-13 17:53:06','aathifah800@gmail.com','081234567'),(5,'admin','$2y$10$HFGjrgHAAuCm6QReQ8vEvO68A6Vd12Frqcdi105FDJs4tgL4.WlAq','Administrator',NULL,'admin','2025-06-13 17:07:51','2025-06-13 17:13:07',NULL,NULL),(6,'Yifii_','$2y$10$axU/qKlIk3cRqhZV36sk/uXwY9SMWb8kwFnnTZ3Y0J65HtGFF39hm','Yifiaa','2317051051','mahasiswa','2025-06-13 19:55:27','2025-06-13 19:55:27',NULL,NULL);
/*!40000 ALTER TABLE `pengguna` ENABLE KEYS */;
UNLOCK TABLES;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-06-14  9:08:31
