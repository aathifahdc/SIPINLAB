<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Inisialisasi database dan auth
$database = new Database();
$auth = new Auth($database);

// Cek apakah user sudah login dan role-nya mahasiswa
if (!$auth->isLoggedIn() || !$auth->isMahasiswa()) {
    header("Location: ../index.php");
    exit;
}

// Ambil data user
$user = $auth->getUser();

// Cek apakah ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: riwayat.php?error=invalid_id");
    exit;
}

$id_peminjaman = (int)$_GET['id'];

try {
    $conn = $database->getConnection();
    
    // Cek apakah peminjaman milik user yang login dan statusnya masih menunggu
    $query = "SELECT p.*, dp.id_alat, dp.jumlah, a.nama as nama_alat
              FROM peminjaman p
              JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
              JOIN alat a ON dp.id_alat = a.id
              WHERE p.id = ? AND p.id_peminjam = ? AND p.status = 'menunggu'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$id_peminjaman, $user['id']]);
    $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika data tidak ditemukan atau bukan milik user yang login atau status bukan menunggu
    if (!$peminjaman) {
        header("Location: riwayat.php?error=cannot_cancel");
        exit;
    }

    // Mulai transaction
    $conn->beginTransaction();

    // 1. Update status peminjaman menjadi dibatalkan
    $query = "UPDATE peminjaman SET 
              status = 'dibatalkan', 
              updated_at = NOW() 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $result1 = $stmt->execute([$id_peminjaman]);

    // 2. Kembalikan stok alat
    $query = "UPDATE alat SET 
              jumlah_tersedia = jumlah_tersedia + ? 
              WHERE id = ?";
    $stmt = $conn->prepare($query);
    $result2 = $stmt->execute([$peminjaman['jumlah'], $peminjaman['id_alat']]);

    // 3. Catat log aktivitas
    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail, created_at) 
              VALUES (?, ?, ?, NOW())";
    $stmt = $conn->prepare($query);
    $detail_log = "Membatalkan peminjaman ID: {$id_peminjaman} - Alat: {$peminjaman['nama_alat']} ({$peminjaman['jumlah']} unit)";
    $result3 = $stmt->execute([$user['id'], 'Pembatalan Peminjaman', $detail_log]);

    // Cek apakah semua query berhasil
    if ($result1 && $result2 && $result3) {
        // Commit transaction
        $conn->commit();
        
        // Redirect ke halaman riwayat dengan pesan sukses
        header("Location: riwayat.php?success=cancel_success");
        exit;
    } else {
        // Rollback jika ada yang gagal
        $conn->rollback();
        header("Location: detail-peminjaman.php?id={$id_peminjaman}&error=cancel_failed");
        exit;
    }

} catch (PDOException $e) {
    // Rollback transaction jika terjadi error
    if ($conn->inTransaction()) {
        $conn->rollback();
    }
    
    // Log error untuk debugging
    error_log("Error in batalkan-peminjaman.php: " . $e->getMessage());
    
    // Redirect dengan pesan error
    header("Location: detail-peminjaman.php?id={$id_peminjaman}&error=database_error");
    exit;
} catch (Exception $e) {
    // Handle error lainnya
    error_log("General error in batalkan-peminjaman.php: " . $e->getMessage());
    header("Location: riwayat.php?error=system_error");
    exit;
}
?>
