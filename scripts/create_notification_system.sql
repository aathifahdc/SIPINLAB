-- Sistem notifikasi untuk pengingat pengembalian
CREATE TABLE IF NOT EXISTS notifikasi (
    id_notifikasi INT PRIMARY KEY AUTO_INCREMENT,
    npm VARCHAR(20),
    judul VARCHAR(200) NOT NULL,
    pesan TEXT NOT NULL,
    tipe ENUM('info', 'warning', 'danger') DEFAULT 'info',
    status ENUM('unread', 'read') DEFAULT 'unread',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (npm) REFERENCES pengguna(npm)
);

-- Event scheduler untuk pengingat otomatis
DELIMITER //

CREATE EVENT IF NOT EXISTS reminder_pengembalian
ON SCHEDULE EVERY 1 DAY
STARTS CURRENT_TIMESTAMP
DO
BEGIN
    -- Pengingat H-1 sebelum jatuh tempo
    INSERT INTO notifikasi (npm, judul, pesan, tipe)
    SELECT 
        p.npm,
        'Pengingat Pengembalian Alat',
        CONCAT('Alat ', a.nama_alat, ' harus dikembalikan besok (', DATE_FORMAT(p.tanggal_kembali_rencana, '%d-%m-%Y %H:%i'), ')'),
        'warning'
    FROM peminjaman p
    JOIN alat a ON p.id_alat = a.id_alat
    WHERE p.status = 'dipinjam'
    AND DATE(p.tanggal_kembali_rencana) = DATE_ADD(CURDATE(), INTERVAL 1 DAY);
    
    -- Notifikasi keterlambatan
    INSERT INTO notifikasi (npm, judul, pesan, tipe)
    SELECT 
        p.npm,
        'Alat Terlambat Dikembalikan',
        CONCAT('Alat ', a.nama_alat, ' sudah terlambat ', DATEDIFF(NOW(), p.tanggal_kembali_rencana), ' hari. Segera kembalikan!'),
        'danger'
    FROM peminjaman p
    JOIN alat a ON p.id_alat = a.id_alat
    WHERE p.status = 'dipinjam'
    AND p.tanggal_kembali_rencana < NOW();
END //

DELIMITER ;

-- Aktifkan event scheduler
SET GLOBAL event_scheduler = ON;
