DELIMITER $$
CREATE DEFINER=root@localhost PROCEDURE kembalikan_alat(
    IN p_id_peminjaman INT,
    IN p_id_pengguna INT,
    IN p_kondisi ENUM('baik', 'rusak_ringan', 'rusak_berat')
)
BEGIN
    DECLARE p_id_alat INT;
    DECLARE p_jumlah INT;
    DECLARE p_status_peminjaman VARCHAR(20);
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Terjadi kesalahan saat memproses pengembalian';
    END;
    
    -- Cek status peminjaman
    SELECT status INTO p_status_peminjaman FROM peminjaman WHERE id = p_id_peminjaman;
    
    IF p_status_peminjaman != 'dipinjam' THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Peminjaman tidak dalam status dipinjam';
    END IF;
    
    START TRANSACTION;
    
    -- Update status peminjaman
    UPDATE peminjaman 
    SET status = 'dikembalikan', tanggal_kembali = NOW()
    WHERE id = p_id_peminjaman;
    
    -- Ambil data alat yang dipinjam
    SELECT id_alat, jumlah INTO p_id_alat, p_jumlah 
    FROM detail_peminjaman 
    WHERE id_peminjaman = p_id_peminjaman;
    
    -- Update status detail peminjaman
    UPDATE detail_peminjaman 
    SET status_kembali = 'sudah' 
    WHERE id_peminjaman = p_id_peminjaman;
    
    -- Update jumlah alat tersedia
    UPDATE alat 
    SET jumlah_tersedia = jumlah_tersedia + p_jumlah,
        kondisi = CASE 
            WHEN p_kondisi != 'baik' THEN p_kondisi
            ELSE kondisi
        END
    WHERE id = p_id_alat;
    
    -- Catat log aktivitas
    INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail)
    VALUES (p_id_pengguna, 'Mengembalikan alat', CONCAT('Peminjaman ID: ', p_id_peminjaman));
    
    COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=root@localhost PROCEDURE lapor_kerusakan(
    IN p_id_alat INT,
    IN p_id_pelapor INT,
    IN p_deskripsi_kerusakan TEXT
)
BEGIN
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Terjadi kesalahan saat melaporkan kerusakan';
    END;
    
    START TRANSACTION;
    
    -- Tambahkan laporan kerusakan
    INSERT INTO laporan_kerusakan (id_alat, id_pelapor, deskripsi_kerusakan, tanggal_laporan)
    VALUES (p_id_alat, p_id_pelapor, p_deskripsi_kerusakan, NOW());
    
    -- Catat log aktivitas
    INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail)
    VALUES (p_id_pelapor, 'Melaporkan kerusakan alat', CONCAT('Alat ID: ', p_id_alat));
    
    COMMIT;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=root@localhost PROCEDURE pinjam_alat(
    IN p_id_peminjam INT,
    IN p_tanggal_pinjam DATETIME,
    IN p_keterangan TEXT,
    IN p_id_alat INT,
    IN p_jumlah INT
)
BEGIN
    DECLARE p_id_peminjaman INT;
    DECLARE alat_tersedia INT;
    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Terjadi kesalahan saat memproses peminjaman';
    END;
    
    -- Cek ketersediaan alat
    SELECT jumlah_tersedia INTO alat_tersedia FROM alat WHERE id = p_id_alat;
    
    IF alat_tersedia < p_jumlah THEN
        SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Jumlah alat tidak mencukupi';
    END IF;
    
    START TRANSACTION;
    
    -- Buat peminjaman baru
    INSERT INTO peminjaman (id_peminjam, tanggal_pinjam, status, keterangan)
    VALUES (p_id_peminjam, p_tanggal_pinjam, 'menunggu', p_keterangan);
    
    SET p_id_peminjaman = LAST_INSERT_ID();
    
    -- Tambahkan detail peminjaman
    INSERT INTO detail_peminjaman (id_peminjaman, id_alat, jumlah)
    VALUES (p_id_peminjaman, p_id_alat, p_jumlah);
    
    -- Catat log aktivitas
    INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail)
    VALUES (p_id_peminjam, 'Mengajukan peminjaman', CONCAT('Peminjaman ID: ', p_id_peminjaman));
    
    COMMIT;
END$$
DELIMITER ;