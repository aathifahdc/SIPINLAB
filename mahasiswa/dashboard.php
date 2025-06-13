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

// Ambil data peminjaman aktif
$conn = $database->getConnection();
$query = "SELECT p.*, a.nama as nama_alat, a.kode as kode_alat, l.nama as nama_lab, dp.jumlah
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE p.id_peminjam = ? AND p.status IN ('menunggu', 'dipinjam', 'terlambat')
          ORDER BY p.tanggal_pinjam DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user['id']]);
$peminjaman_aktif = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data peminjaman selesai
$query = "SELECT p.*, a.nama as nama_alat, a.kode as kode_alat, l.nama as nama_lab, dp.jumlah
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE p.id_peminjam = ? AND p.status IN ('dikembalikan', 'dibatalkan')
          ORDER BY p.tanggal_pinjam DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute([$user['id']]);
$peminjaman_selesai = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil data alat tersedia
$query = "SELECT a.*, k.nama as kategori, l.nama as laboratorium
          FROM alat a
          JOIN kategori_alat k ON a.id_kategori = k.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE a.jumlah_tersedia > 0
          ORDER BY a.nama ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$alat_tersedia = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Dashboard Mahasiswa</title>
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
                            <a class="nav-link active" href="dashboard.php">
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
                            <a class="nav-link" href="profil.php">
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
                    <h1 class="h2">Dashboard</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-person me-1"></i> <?php echo $user['nama_lengkap']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Info Cards -->
                <div class="row">
                    <div class="col-md-4">
                        <div class="card card-dashboard bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Peminjaman Aktif</h5>
                                <p class="card-text display-4"><?php echo count($peminjaman_aktif); ?></p>
                                <a href="riwayat.php" class="text-white">Lihat detail <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-dashboard bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Alat Tersedia</h5>
                                <p class="card-text display-4"><?php echo count($alat_tersedia); ?></p>
                                <a href="pinjam.php" class="text-white">Pinjam alat <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card card-dashboard bg-info text-white">
                            <div class="card-body">
                                <h5 class="card-title">NPM</h5>
                                <p class="card-text display-6"><?php echo $user['npm']; ?></p>
                                <a href="profil.php" class="text-white">Lihat profil <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Peminjaman Aktif -->
                <div class="card card-dashboard mt-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Peminjaman Aktif</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($peminjaman_aktif) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Alat</th>
                                            <th>Nama Alat</th>
                                            <th>Laboratorium</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_aktif as $pinjam): ?>
                                            <tr>
                                                <td><?php echo $pinjam['kode_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_lab']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pinjam['tanggal_pinjam'])); ?></td>
                                                <td><?php echo $pinjam['jumlah']; ?></td>
                                                <td>
                                                    <?php if ($pinjam['status'] == 'menunggu'): ?>
                                                        <span class="badge bg-warning">Menunggu</span>
                                                    <?php elseif ($pinjam['status'] == 'dipinjam'): ?>
                                                        <span class="badge bg-primary">Dipinjam</span>
                                                    <?php elseif ($pinjam['status'] == 'terlambat'): ?>
                                                        <span class="badge bg-danger">Terlambat</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="detail-peminjaman.php?id=<?php echo $pinjam['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada peminjaman aktif</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Riwayat Peminjaman -->
                <div class="card card-dashboard mt-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Riwayat Peminjaman Terakhir</h5>
                        <a href="riwayat.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($peminjaman_selesai) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Alat</th>
                                            <th>Nama Alat</th>
                                            <th>Laboratorium</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Tanggal Kembali</th>
                                            <th>Status</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_selesai as $pinjam): ?>
                                            <tr>
                                                <td><?php echo $pinjam['kode_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_lab']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pinjam['tanggal_pinjam'])); ?></td>
                                                <td>
                                                    <?php echo $pinjam['tanggal_kembali'] ? date('d/m/Y H:i', strtotime($pinjam['tanggal_kembali'])) : '-'; ?>
                                                </td>
                                                <td>
                                                    <?php if ($pinjam['status'] == 'dikembalikan'): ?>
                                                        <span class="badge bg-success">Dikembalikan</span>
                                                    <?php elseif ($pinjam['status'] == 'dibatalkan'): ?>
                                                        <span class="badge bg-danger">Dibatalkan</span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada riwayat peminjaman</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
