<?php
// Script untuk backup otomatis yang dijalankan oleh cron job

require_once '../config/database.php';

// Inisialisasi database
$database = new Database();

// Direktori backup
$backup_dir = "../backups/";

// Jalankan backup
$result = $database->backupDatabase($backup_dir);

// Log hasil backup
$conn = $database->getConnection();
$query = "INSERT INTO log_aktivitas (aktivitas, detail) 
          VALUES ('Backup otomatis', ?)";
$stmt = $conn->prepare($query);
$stmt->execute([$result ? 'Backup berhasil' : 'Backup gagal']);

// Output hasil untuk log cron
echo date('Y-m-d H:i:s') . " - Backup " . ($result ? "berhasil" : "gagal") . "\n";
?>
