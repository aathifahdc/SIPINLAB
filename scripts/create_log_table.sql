-- Tabel untuk logging aktivitas sistem
CREATE TABLE IF NOT EXISTS log_aktivitas (
    id_log INT PRIMARY KEY AUTO_INCREMENT,
    npm VARCHAR(20),
    aktivitas VARCHAR(100) NOT NULL,
    detail TEXT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (npm) REFERENCES pengguna(npm)
);

-- Index untuk performa
CREATE INDEX idx_log_npm ON log_aktivitas(npm);
CREATE INDEX idx_log_timestamp ON log_aktivitas(timestamp);
CREATE INDEX idx_log_aktivitas ON log_aktivitas(aktivitas);
