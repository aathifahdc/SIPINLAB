DELIMITER $$
CREATE DEFINER=root@localhost FUNCTION cek_ketersediaan_alat(p_id_alat INT) RETURNS int(11)
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE jumlah INT;
    
    SELECT jumlah_tersedia INTO jumlah
    FROM alat
    WHERE id = p_id_alat;

    RETURN jumlah;
END$$
DELIMITER ;

DELIMITER $$
CREATE DEFINER=root@localhost FUNCTION cek_status_peminjam(npm_peminjam VARCHAR(20)) RETURNS varchar(20) CHARSET utf8mb4 COLLATE utf8mb4_general_ci
    READS SQL DATA
    DETERMINISTIC
BEGIN
    DECLARE status_peminjam VARCHAR(20);
    DECLARE peminjam_id INT;
    DECLARE memiliki_peminjaman_aktif BOOLEAN;
    
    -- Ambil ID peminjam berdasarkan NPM
    SELECT id INTO peminjam_id
    FROM pengguna
    WHERE npm = npm_peminjam;
    
    -- Cek apakah memiliki peminjaman aktif
    SELECT COUNT(*) > 0 INTO memiliki_peminjaman_aktif
    FROM peminjaman
    WHERE id_peminjam = peminjam_id
    AND status IN ('menunggu', 'dipinjam', 'terlambat');
    
    IF memiliki_peminjaman_aktif THEN
        SET status_peminjam = 'memiliki_peminjaman';
    ELSE
        SET status_peminjam = 'bebas';
    END IF;
    
    RETURN status_peminjam;
END$$
DELIMITER ;