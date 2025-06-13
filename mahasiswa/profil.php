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

// Proses update profil
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Debug: Tampilkan data POST
    // echo "<pre>"; print_r($_POST); echo "</pre>";
    
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $email = $_POST['email'] ?? '';
    $no_telp = $_POST['no_telp'] ?? '';
    $password = $_POST['password'] ?? '';
    $password_baru = $_POST['password_baru'] ?? '';
    $konfirmasi_password = $_POST['konfirmasi_password'] ?? '';
    
    if (empty($nama_lengkap)) {
        $error = 'Nama lengkap harus diisi';
    } else {
        try {
            $conn = $database->getConnection();
            
            // Jika password diisi, berarti user ingin mengubah password
            if (!empty($password)) {
                // Verifikasi password lama
                $query = "SELECT password FROM pengguna WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$user['id']]);
                $user_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if (!password_verify($password, $user_data['password'])) {
                    $error = 'Password lama tidak sesuai';
                } elseif (empty($password_baru) || empty($konfirmasi_password)) {
                    $error = 'Password baru dan konfirmasi password harus diisi';
                } elseif ($password_baru != $konfirmasi_password) {
                    $error = 'Password baru dan konfirmasi password tidak sama';
                } else {
                    // Update data dengan password baru
                    $hashed_password = password_hash($password_baru, PASSWORD_DEFAULT);
                    $query = "UPDATE pengguna SET nama_lengkap = ?, email = ?, no_telp = ?, password = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$nama_lengkap, $email, $no_telp, $hashed_password, $user['id']]);
                    
                    $success = 'Profil berhasil diperbarui dengan password baru';
                    
                    // Update session data
                    $_SESSION['nama_lengkap'] = $nama_lengkap;
                    
                    // Refresh user data
                    $user = $auth->getUser();
                }
            } else {
                // Update data tanpa mengubah password
                $query = "UPDATE pengguna SET nama_lengkap = ?, email = ?, no_telp = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$nama_lengkap, $email, $no_telp, $user['id']]);
                
                $success = 'Profil berhasil diperbarui';
                
                // Update session data
                $_SESSION['nama_lengkap'] = $nama_lengkap;
                
                // Refresh user data
                $user = $auth->getUser();
            }
            
            // Log aktivitas
            $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) VALUES (?, 'Update Profil', 'Profil berhasil diperbarui')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user['id']]);
            
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
            // Debug: Tampilkan error lengkap
            // echo "<pre>"; print_r($e); echo "</pre>";
        }
    }
}

// Ambil data statistik peminjaman
try {
    $conn = $database->getConnection();
    $query = "SELECT COUNT(*) as total_peminjaman FROM peminjaman WHERE id_peminjam = ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user['id']]);
    $total_peminjaman = $stmt->fetch(PDO::FETCH_ASSOC)['total_peminjaman'];

    $query = "SELECT COUNT(*) as total_aktif FROM peminjaman WHERE id_peminjam = ? AND status IN ('menunggu', 'dipinjam', 'terlambat')";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user['id']]);
    $total_aktif = $stmt->fetch(PDO::FETCH_ASSOC)['total_aktif'];

    $query = "SELECT COUNT(*) as total_selesai FROM peminjaman WHERE id_peminjam = ? AND status = 'dikembalikan'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user['id']]);
    $total_selesai = $stmt->fetch(PDO::FETCH_ASSOC)['total_selesai'];

    $query = "SELECT COUNT(*) as total_dibatalkan FROM peminjaman WHERE id_peminjam = ? AND status = 'dibatalkan'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$user['id']]);
    $total_dibatalkan = $stmt->fetch(PDO::FETCH_ASSOC)['total_dibatalkan'];
} catch (PDOException $e) {
    // Jika gagal mengambil statistik, set nilai default
    $total_peminjaman = 0;
    $total_aktif = 0;
    $total_selesai = 0;
    $total_dibatalkan = 0;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Profil</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #212529;
            color: #fff;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
        }
        .sidebar .nav-link:hover {
            color: #fff;
        }
        .sidebar .nav-link.active {
            color: #fff;
            background-color: rgba(255, 255, 255, 0.1);
        }
        .main-content {
            padding: 20px;
        }
        .card-dashboard {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .profile-header {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background-color: #6c757d;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            margin-right: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="position-sticky pt-3">
                    <div class="text-center mb-4">
                        <h4>SIPINLAB</h4>
                        <p class="text-muted small">Sistem Peminjaman Alat Lab</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">
                                <i class="bi bi-house-door me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pinjam.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Pinjam Alat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="riwayat.php">
                                <i class="bi bi-clock-history me-2"></i> Riwayat Peminjaman
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="lapor-kerusakan.php">
                                <i class="bi bi-exclamation-triangle me-2"></i> Lapor Kerusakan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="profil.php">
                                <i class="bi bi-person me-2"></i> Profil
                            </a>
                        </li>
                        <li class="nav-item mt-5">
                            <a class="nav-link text-danger" href="../logout.php">
                                <i class="bi bi-box-arrow-left me-2"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>
            
            <!-- Main content -->
            <div class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">Profil</h1>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Profile Header -->
                <div class="profile-header d-flex align-items-center">
                    <div class="profile-avatar">
                        <?php echo strtoupper(substr($user['nama_lengkap'] ?? 'U', 0, 1)); ?>
                    </div>
                    <div>
                        <h3><?php echo $user['nama_lengkap'] ?? 'Nama Tidak Tersedia'; ?></h3>
                        <p class="mb-0">NPM: <?php echo $user['npm'] ?? 'NPM Tidak Tersedia'; ?></p>
                        <p class="mb-0">Email: <?php echo $user['email'] ?? 'Email Tidak Tersedia'; ?></p>
                        <p class="mb-0">No. Telepon: <?php echo $user['no_telp'] ?? 'No. Telepon Tidak Tersedia'; ?></p>
                    </div>
                </div>
                
                <!-- Statistik Peminjaman -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Peminjaman</h5>
                                <p class="card-text display-4"><?php echo $total_peminjaman; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">Peminjaman Aktif</h5>
                                <p class="card-text display-4"><?php echo $total_aktif; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Selesai</h5>
                                <p class="card-text display-4"><?php echo $total_selesai; ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-dashboard bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Dibatalkan</h5>
                                <p class="card-text display-4"><?php echo $total_dibatalkan; ?></p>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Form Edit Profil -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Edit Profil</h5>
                    </div>
                    <div class="card-body">
                        <form action="profil.php" method="POST">
                            <div class="mb-3">
                                <label for="npm" class="form-label">NPM</label>
                                <input type="text" class="form-control" id="npm" value="<?php echo $user['npm'] ?? ''; ?>" disabled>
                                <div class="form-text">NPM tidak dapat diubah</div>
                            </div>
                            <div class="mb-3">
                                <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                                <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo $user['nama_lengkap'] ?? ''; ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="email" class="form-label">Email</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $user['email'] ?? ''; ?>">
                            </div>
                            <div class="mb-3">
                                <label for="no_telp" class="form-label">Nomor Telepon</label>
                                <input type="text" class="form-control" id="no_telp" name="no_telp" value="<?php echo $user['no_telp'] ?? ''; ?>">
                            </div>
                            <hr>
                            <h5>Ubah Password</h5>
                            <div class="mb-3">
                                <label for="password" class="form-label">Password Lama</label>
                                <input type="password" class="form-control" id="password" name="password">
                                <div class="form-text">Kosongkan jika tidak ingin mengubah password</div>
                            </div>
                            <div class="mb-3">
                                <label for="password_baru" class="form-label">Password Baru</label>
                                <input type="password" class="form-control" id="password_baru" name="password_baru">
                            </div>
                            <div class="mb-3">
                                <label for="konfirmasi_password" class="form-label">Konfirmasi Password Baru</label>
                                <input type="password" class="form-control" id="konfirmasi_password" name="konfirmasi_password">
                            </div>
                            <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
