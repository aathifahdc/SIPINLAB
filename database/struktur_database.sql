-- Tabel Laboratorium
CREATE TABLE laboratorium (
    id_lab INT PRIMARY KEY AUTO_INCREMENT,
    nama_lab VARCHAR(100) NOT NULL,
    lokasi VARCHAR(200),
    penanggung_jawab VARCHAR(100),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Kategori Alat
CREATE TABLE kategori_alat (
    id_kategori INT PRIMARY KEY AUTO_INCREMENT,
    nama_kategori VARCHAR(50) NOT NULL,
    deskripsi TEXT
);

-- Tabel Alat
CREATE TABLE alat (
    id_alat INT PRIMARY KEY AUTO_INCREMENT,
    nama_alat VARCHAR(100) NOT NULL,
    id_kategori INT,
    id_lab INT,
    kondisi ENUM('baik', 'rusak', 'maintenance') DEFAULT 'baik',
    status ENUM('tersedia', 'dipinjam', 'maintenance') DEFAULT 'tersedia',
    spesifikasi TEXT,
    tanggal_beli DATE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (id_kategori) REFERENCES kategori_alat(id_kategori),
    FOREIGN KEY (id_lab) REFERENCES laboratorium(id_lab)
);

-- Tabel Pengguna
CREATE TABLE pengguna (
    npm VARCHAR(20) PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE,
    password VARCHAR(255),
    role ENUM('mahasiswa', 'dosen', 'admin') DEFAULT 'mahasiswa',
    fakultas VARCHAR(100),
    jurusan VARCHAR(100),
    status ENUM('aktif', 'nonaktif') DEFAULT 'aktif',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Tabel Peminjaman
CREATE TABLE peminjaman (
    id_peminjaman INT PRIMARY KEY AUTO_INCREMENT,
    npm VARCHAR(20),
    id_alat INT,
    tanggal_pinjam DATETIME DEFAULT CURRENT_TIMESTAMP,
    tanggal_kembali_rencana DATETIME,
    tanggal_kembali_aktual DATETIME NULL,
    keperluan TEXT,
    status ENUM('dipinjam', 'dikembalikan', 'terlambat') DEFAULT 'dipinjam',
    denda DECIMAL(10,2) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (npm) REFERENCES pengguna(npm),
    FOREIGN KEY (id_alat) REFERENCES alat(id_alat)
);

-- Tabel Laporan Kerusakan
CREATE TABLE laporan_kerusakan (
    id_laporan INT PRIMARY KEY AUTO_INCREMENT,
    id_alat INT,
    npm VARCHAR(20),
    id_peminjaman INT,
    deskripsi_kerusakan TEXT NOT NULL,
    tingkat_kerusakan ENUM('ringan', 'sedang', 'berat') DEFAULT 'ringan',
    status_laporan ENUM('pending', 'proses', 'selesai') DEFAULT 'pending',
    tanggal_lapor DATETIME DEFAULT CURRENT_TIMESTAMP,
    tanggal_selesai DATETIME NULL,
    biaya_perbaikan DECIMAL(10,2) DEFAULT 0,
    FOREIGN KEY (id_alat) REFERENCES alat(id_alat),
    FOREIGN KEY (npm) REFERENCES pengguna(npm),
    FOREIGN KEY (id_peminjaman) REFERENCES peminjaman(id_peminjaman)
);

-- Insert data sample
INSERT INTO laboratorium (nama_lab, lokasi, penanggung_jawab) VALUES
('Lab Jaringan', 'Gedung A Lt. 2', 'Dr. Ahmad Fauzi'),
('Lab IoT', 'Gedung B Lt. 3', 'Dr. Siti Nurhaliza'),
('Lab Multimedia', 'Gedung C Lt. 1', 'Dr. Budi Santoso');

INSERT INTO kategori_alat (nama_kategori, deskripsi) VALUES
('Networking', 'Peralatan jaringan komputer'),
('IoT Devices', 'Perangkat Internet of Things'),
('Multimedia', 'Peralatan audio visual');

INSERT INTO alat (nama_alat, id_kategori, id_lab, spesifikasi) VALUES
('Router Cisco 2901', 1, 1, 'Cisco 2901 Integrated Services Router'),
('Arduino Uno R3', 2, 2, 'Microcontroller board based on ATmega328P'),
('Raspberry Pi 4', 2, 2, '4GB RAM, Quad-core ARM Cortex-A72'),
('Projector Epson', 3, 3, 'Full HD 1920x1080, 3000 lumens');

INSERT INTO pengguna (npm, nama, email, password, role, fakultas, jurusan) VALUES
('2021001', 'John Doe', 'john@student.ac.id', MD5('password123'), 'mahasiswa', 'Teknik', 'Informatika'),
('2021002', 'Jane Smith', 'jane@student.ac.id', MD5('password123'), 'mahasiswa', 'Teknik', 'Elektro'),
('ADMIN001', 'Admin Lab', 'admin@lab.ac.id', MD5('admin123'), 'admin', 'Teknik', 'Informatika');

-- FUNCTIONS
DELIMITER //

-- Function: Cek ketersediaan alat
CREATE FUNCTION cek_ketersediaan_alat(p_id_alat INT) 
RETURNS VARCHAR(20)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_status VARCHAR(20);
    DECLARE v_kondisi VARCHAR(20);
    
    SELECT status, kondisi INTO v_status, v_kondisi
    FROM alat 
    WHERE id_alat = p_id_alat;
    
    IF v_kondisi = 'rusak' THEN
        RETURN 'rusak';
    ELSEIF v_status = 'dipinjam' THEN
        RETURN 'dipinjam';
    ELSEIF v_status = 'maintenance' THEN
        RETURN 'maintenance';
    ELSE
        RETURN 'tersedia';
    END IF;
END //

-- Function: Cek status peminjam
CREATE FUNCTION cek_status_peminjam(p_npm VARCHAR(20))
RETURNS VARCHAR(50)
READS SQL DATA
DETERMINISTIC
BEGIN
    DECLARE v_count INT;
    DECLARE v_status_user VARCHAR(20);
    
    -- Cek status user
    SELECT status INTO v_status_user
    FROM pengguna 
    WHERE npm = p_npm;
    
    IF v_status_user = 'nonaktif' THEN
        RETURN 'user_nonaktif';
    END IF;
    
    -- Cek apakah ada peminjaman yang belum dikembalikan
    SELECT COUNT(*) INTO v_count
    FROM peminjaman 
    WHERE npm = p_npm AND status = 'dipinjam';
    
    IF v_count > 0 THEN
        RETURN 'ada_peminjaman_aktif';
    END IF;
    
    -- Cek apakah ada keterlambatan
    SELECT COUNT(*) INTO v_count
    FROM peminjaman 
    WHERE npm = p_npm AND status = 'terlambat';
    
    IF v_count > 0 THEN
        RETURN 'ada_keterlambatan';
    END IF;
    
    RETURN 'boleh_pinjam';
END //

DELIMITER ;

-- STORED PROCEDURES
DELIMITER //

-- Procedure: Pinjam Alat
CREATE PROCEDURE pinjam_alat(
    IN p_npm VARCHAR(20),
    IN p_id_alat INT,
    IN p_tanggal_kembali_rencana DATETIME,
    IN p_keperluan TEXT,
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE v_ketersediaan VARCHAR(20);
    DECLARE v_status_peminjam VARCHAR(50);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaksi gagal';
    END;
    
    START TRANSACTION;
    
    -- Cek ketersediaan alat
    SET v_ketersediaan = cek_ketersediaan_alat(p_id_alat);
    
    IF v_ketersediaan != 'tersedia' THEN
        SET p_result = CONCAT('ERROR: Alat ', v_ketersediaan);
        ROLLBACK;
    ELSE
        -- Cek status peminjam
        SET v_status_peminjam = cek_status_peminjam(p_npm);
        
        IF v_status_peminjam != 'boleh_pinjam' THEN
            SET p_result = CONCAT('ERROR: ', v_status_peminjam);
            ROLLBACK;
        ELSE
            -- Insert peminjaman
            INSERT INTO peminjaman (npm, id_alat, tanggal_kembali_rencana, keperluan)
            VALUES (p_npm, p_id_alat, p_tanggal_kembali_rencana, p_keperluan);
            
            -- Update status alat
            UPDATE alat SET status = 'dipinjam' WHERE id_alat = p_id_alat;
            
            SET p_result = 'SUCCESS: Peminjaman berhasil';
            COMMIT;
        END IF;
    END IF;
END //

-- Procedure: Kembalikan Alat
CREATE PROCEDURE kembalikan_alat(
    IN p_id_peminjaman INT,
    IN p_kondisi_alat VARCHAR(20),
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE v_id_alat INT;
    DECLARE v_npm VARCHAR(20);
    DECLARE v_tanggal_rencana DATETIME;
    DECLARE v_denda DECIMAL(10,2) DEFAULT 0;
    DECLARE v_hari_terlambat INT DEFAULT 0;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaksi gagal';
    END;
    
    START TRANSACTION;
    
    -- Get data peminjaman
    SELECT id_alat, npm, tanggal_kembali_rencana 
    INTO v_id_alat, v_npm, v_tanggal_rencana
    FROM peminjaman 
    WHERE id_peminjaman = p_id_peminjaman AND status = 'dipinjam';
    
    IF v_id_alat IS NULL THEN
        SET p_result = 'ERROR: Peminjaman tidak ditemukan atau sudah dikembalikan';
        ROLLBACK;
    ELSE
        -- Hitung denda jika terlambat
        SET v_hari_terlambat = DATEDIFF(NOW(), v_tanggal_rencana);
        IF v_hari_terlambat > 0 THEN
            SET v_denda = v_hari_terlambat * 5000; -- Rp 5000 per hari
        END IF;
        
        -- Update peminjaman
        UPDATE peminjaman 
        SET tanggal_kembali_aktual = NOW(),
            status = IF(v_hari_terlambat > 0, 'terlambat', 'dikembalikan'),
            denda = v_denda
        WHERE id_peminjaman = p_id_peminjaman;
        
        -- Update status dan kondisi alat
        UPDATE alat 
        SET status = 'tersedia', 
            kondisi = p_kondisi_alat 
        WHERE id_alat = v_id_alat;
        
        SET p_result = CONCAT('SUCCESS: Alat dikembalikan. Denda: Rp ', v_denda);
        COMMIT;
    END IF;
END //

-- Procedure: Lapor Kerusakan
CREATE PROCEDURE lapor_kerusakan(
    IN p_id_alat INT,
    IN p_npm VARCHAR(20),
    IN p_id_peminjaman INT,
    IN p_deskripsi TEXT,
    IN p_tingkat_kerusakan VARCHAR(20),
    OUT p_result VARCHAR(100)
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SET p_result = 'ERROR: Transaksi gagal';
    END;
    
    START TRANSACTION;
    
    -- Insert laporan kerusakan
    INSERT INTO laporan_kerusakan (id_alat, npm, id_peminjaman, deskripsi_kerusakan, tingkat_kerusakan)
    VALUES (p_id_alat, p_npm, p_id_peminjaman, p_deskripsi, p_tingkat_kerusakan);
    
    -- Update kondisi alat
    UPDATE alat SET kondisi = 'rusak', status = 'maintenance' WHERE id_alat = p_id_alat;
    
    SET p_result = 'SUCCESS: Laporan kerusakan berhasil dibuat';
    COMMIT;
END //

DELIMITER ;

-- TRIGGERS
DELIMITER //

-- Trigger: Cek sebelum peminjaman
CREATE TRIGGER before_insert_peminjaman
BEFORE INSERT ON peminjaman
FOR EACH ROW
BEGIN
    DECLARE v_ketersediaan VARCHAR(20);
    DECLARE v_status_peminjam VARCHAR(50);
    
    SET v_ketersediaan = cek_ketersediaan_alat(NEW.id_alat);
    SET v_status_peminjam = cek_status_peminjam(NEW.npm);
    
    IF v_ketersediaan != 'tersedia' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Alat tidak tersedia untuk dipinjam';
    END IF;
    
    IF v_status_peminjam != 'boleh_pinjam' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Peminjam tidak memenuhi syarat';
    END IF;
END //

-- Trigger: Update status keterlambatan
CREATE TRIGGER update_status_terlambat
BEFORE UPDATE ON peminjaman
FOR EACH ROW
BEGIN
    IF NEW.tanggal_kembali_aktual IS NOT NULL AND OLD.tanggal_kembali_aktual IS NULL THEN
        IF NEW.tanggal_kembali_aktual > NEW.tanggal_kembali_rencana THEN
            SET NEW.status = 'terlambat';
        ELSE
            SET NEW.status = 'dikembalikan';
        END IF;
    END IF;
END //

DELIMITER ;
