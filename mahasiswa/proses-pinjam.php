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

// Cek apakah form sudah disubmit
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: pinjam.php");
    exit;
}

// Ambil data dari form
$id_alat = $_POST['id_alat'] ?? '';
$jumlah = $_POST['jumlah'] ?? '';
$keterangan = $_POST['keterangan'] ?? '';

// Validasi input
if (empty($id_alat) || empty($jumlah) || $jumlah <= 0) {
    header("Location: pinjam.php?error=Semua field harus diisi dengan benar");
    exit;
}

// Cek ketersediaan alat menggunakan fungsi
$conn = $database->getConnection();
$query = "SELECT cek_ketersediaan_alat(?, ?) as tersedia";
$stmt = $conn->prepare($query);
$stmt->execute([$id_alat, $jumlah]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['tersedia'] != 1) {
    header("Location: pinjam.php?error=Stok alat tidak mencukupi");
    exit;
}

// Cek status peminjam menggunakan fungsi
$query = "SELECT cek_status_peminjam(?) as status";
$stmt = $conn->prepare($query);
$stmt->execute([$user['id']]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);

if ($result['status'] != 1) {
    header("Location: pinjam.php?error=Anda memiliki peminjaman yang belum dikembalikan atau terlambat");
    exit;
}

// Jalankan stored procedure pinjam_alat
$result = $database->executeStoredProcedure('pinjam_alat', [
    $user['id'],
    $id_alat,
    $jumlah,
    $keterangan
]);

if (isset($result['error'])) {
    header("Location: pinjam.php?error=" . urlencode($result['error']));
    exit;
}

// Redirect ke halaman riwayat dengan pesan sukses
header("Location: riwayat.php?success=Permintaan peminjaman berhasil dibuat");
exit;
?>
