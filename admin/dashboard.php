<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Inisialisasi database dan auth
$database = new Database();
$auth = new Auth($database);

// Cek apakah user sudah login dan role-nya admin
if (!$auth->isLoggedIn() || !$auth->isAdmin()) {
    header("Location: ../index.php");
    exit;
}

// Ambil data user
$user = $auth->getUser();

// Ambil data statistik
$conn = $database->getConnection();

// Total alat
$query = "SELECT COUNT(*) as total FROM alat";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_alat = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total peminjaman aktif
$query = "SELECT COUNT(*) as total FROM peminjaman WHERE status IN ('menunggu', 'dipinjam')";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_peminjaman_aktif = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total peminjaman menunggu persetujuan
$query = "SELECT COUNT(*) as total FROM peminjaman WHERE status = 'menunggu'";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_menunggu = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Total laporan kerusakan
$query = "SELECT COUNT(*) as total FROM laporan_kerusakan WHERE status != 'selesai'";
$stmt = $conn->prepare($query);
$stmt->execute();
$total_laporan = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

// Peminjaman terbaru
$query = "SELECT p.*, u.nama_lengkap as nama_peminjam, u.npm, a.nama as nama_alat, a.kode as kode_alat, l.nama as nama_lab, dp.jumlah
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          JOIN pengguna u ON p.id_peminjam = u.id
          ORDER BY p.created_at DESC
          LIMIT 5";
$stmt = $conn->prepare($query);
$stmt->execute();
$peminjaman_terbaru = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Alat dengan stok menipis (kurang dari 3)
$query = "SELECT a.*, k.nama as kategori, l.nama as laboratorium
          FROM alat a
          JOIN kategori_alat k ON a.id_kategori = k.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE a.jumlah_tersedia < 3
          ORDER BY a.jumlah_tersedia ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$alat_menipis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Log aktivitas terbaru
$query = "SELECT la.*, u.nama_lengkap
          FROM log_aktivitas la
          LEFT JOIN pengguna u ON la.id_pengguna = u.id
          ORDER BY la.created_at DESC
          LIMIT 10";
$stmt = $conn->prepare($query);
$stmt->execute();
$log_aktivitas = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Dashboard Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
    :root {
        --black-primary: #121212;       /* Hitam utama */
        --black-secondary: #1E1E1E;    /* Hitam sekunder */
        --black-light: #2D2D2D;        /* Hitam lebih terang */
        --gold-primary: #FFD700;       /* Kuning emas */
        --gold-secondary: #FFC107;     /* Kuning lebih gelap */
        --gold-light: #FFF9C4;         /* Kuning sangat muda */
        --text-white: #FFFFFF;         /* Teks putih */
        --text-muted: rgba(255, 255, 255, 0.7); /* Teks muted */
    }

    .sidebar {
        min-height: 100vh;
        background-color: var(--black-primary);
        color: var(--text-white);
        border-right: 1px solid rgba(255, 215, 0, 0.1);
    }

    .sidebar .nav-link {
        color: var(--text-muted);
        padding: 12px 20px;
        margin: 4px 0;
        border-radius: 4px;
        transition: all 0.3s ease;
    }

    .sidebar .nav-link:hover {
        color: var(--gold-primary);
        background-color: var(--black-light);
    }

    .sidebar .nav-link.active {
        color: var(--black-primary);
        background-color: var(--gold-primary);
        font-weight: 600;
        box-shadow: 0 2px 10px rgba(255, 215, 0, 0.3);
    }

    .sidebar .nav-link i {
        margin-right: 10px;
        color: var(--gold-secondary);
    }

    .main-content {
        padding: 25px;
        background-color: #F8F9FA;
        min-height: 100vh;
    }

    .card-dashboard {
        margin-bottom: 25px;
        border-radius: 8px;
        border: none;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        transition: all 0.3s ease;
        border-top: 3px solid var(--gold-primary);
    }

    .card-dashboard:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 20px rgba(0, 0, 0, 0.12);
    }

    .stats-card {
        transition: all 0.3s ease;
        background-color: white;
    }

    .stats-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 6px 18px rgba(0, 0, 0, 0.1);
    }

    .stats-card .card-title {
        color: var(--black-primary);
        font-weight: 600;
    }

    .stats-card .card-value {
        color: var(--gold-secondary);
        font-size: 1.8rem;
        font-weight: 700;
    }

    /* Tambahan elemen UI */
    .navbar {
        background-color: var(--black-primary) !important;
        border-bottom: 1px solid rgba(255, 215, 0, 0.15);
    }

    .navbar-brand {
        color: var(--gold-primary) !important;
        font-weight: 700;
    }

    .btn-gold {
        background-color: var(--gold-primary);
        color: var(--black-primary);
        font-weight: 600;
        border: none;
    }

    .btn-gold:hover {
        background-color: var(--gold-secondary);
        color: var(--black-primary);
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
                        <p class="text-muted small">Admin Panel</p>
                    </div>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="dashboard.php">
                                <i class="bi bi-speedometer2 me-2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="peminjaman.php">
                                <i class="bi bi-box-arrow-right me-2"></i> Peminjaman
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pengembalian.php">
                                <i class="bi bi-box-arrow-in-left me-2"></i> Pengembalian
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="alat.php">
                                <i class="bi bi-tools me-2"></i> Kelola Alat
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="laboratorium.php">
                                <i class="bi bi-building me-2"></i> Laboratorium
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="pengguna.php">
                                <i class="bi bi-people me-2"></i> Pengguna
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="laporan.php">
                                <i class="bi bi-file-earmark-text me-2"></i> Laporan
                            </a>
                        </li>
                        <li class="nav-item">
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
                    <h1 class="h2">Dashboard Admin</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <span class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-person me-1"></i> <?php echo $user['nama_lengkap']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="row">
                    <div class="col-md-3">
                        <div class="card card-dashboard stats-card bg-primary text-white">
                            <div class="card-body">
                                <h5 class="card-title">Total Alat</h5>
                                <p class="card-text display-4"><?php echo $total_alat; ?></p>
                                <a href="alat.php" class="text-white">Kelola alat <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-dashboard stats-card bg-success text-white">
                            <div class="card-body">
                                <h5 class="card-title">Peminjaman Aktif</h5>
                                <p class="card-text display-4"><?php echo $total_peminjaman_aktif; ?></p>
                                <a href="peminjaman.php" class="text-white">Lihat detail <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-dashboard stats-card bg-warning text-dark">
                            <div class="card-body">
                                <h5 class="card-title">Menunggu Persetujuan</h5>
                                <p class="card-text display-4"><?php echo $total_menunggu; ?></p>
                                <a href="peminjaman.php?status=menunggu" class="text-dark">Proses sekarang <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card card-dashboard stats-card bg-danger text-white">
                            <div class="card-body">
                                <h5 class="card-title">Laporan Kerusakan</h5>
                                <p class="card-text display-4"><?php echo $total_laporan; ?></p>
                                <a href="laporan_kerusakan.php" class="text-white">Lihat laporan <i class="bi bi-arrow-right"></i></a>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Peminjaman Terbaru -->
                <div class="card card-dashboard mt-4">
                    <div class="card-header bg-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0">Peminjaman Terbaru</h5>
                        <a href="peminjaman.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                    </div>
                    <div class="card-body">
                        <?php if (count($peminjaman_terbaru) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Peminjam</th>
                                            <th>NPM</th>
                                            <th>Alat</th>
                                            <th>Lab</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_terbaru as $pinjam): ?>
                                            <tr>
                                                <td><?php echo $pinjam['nama_peminjam']; ?></td>
                                                <td><?php echo $pinjam['npm']; ?></td>
                                                <td><?php echo $pinjam['kode_alat'] . ' - ' . $pinjam['nama_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_lab']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pinjam['tanggal_pinjam'])); ?></td>
                                                <td>
                                                    <?php if ($pinjam['status'] == 'menunggu'): ?>
                                                        <span class="badge bg-warning text-dark">Menunggu</span>
                                                    <?php elseif ($pinjam['status'] == 'dipinjam'): ?>
                                                        <span class="badge bg-primary">Dipinjam</span>
                                                    <?php elseif ($pinjam['status'] == 'dikembalikan'): ?>
                                                        <span class="badge bg-success">Dikembalikan</span>
                                                    <?php elseif ($pinjam['status'] == 'terlambat'): ?>
                                                        <span class="badge bg-danger">Terlambat</span>
                                                    <?php elseif ($pinjam['status'] == 'dibatalkan'): ?>
                                                        <span class="badge bg-secondary">Dibatalkan</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="detail_peminjaman.php?id=<?php echo $pinjam['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada peminjaman terbaru</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <div class="row mt-4">
                    <!-- Alat dengan Stok Menipis -->
                    <div class="col-md-6">
                        <div class="card card-dashboard h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Alat dengan Stok Menipis</h5>
                                <a href="alat.php?filter=menipis" class="btn btn-sm btn-primary">Lihat Semua</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($alat_menipis) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Kode</th>
                                                    <th>Nama Alat</th>
                                                    <th>Lab</th>
                                                    <th>Tersedia</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($alat_menipis as $alat): ?>
                                                    <tr>
                                                        <td><?php echo $alat['kode']; ?></td>
                                                        <td><?php echo $alat['nama']; ?></td>
                                                        <td><?php echo $alat['laboratorium']; ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $alat['jumlah_tersedia'] == 0 ? 'danger' : 'warning'; ?>">
                                                                <?php echo $alat['jumlah_tersedia']; ?>/<?php echo $alat['jumlah_total']; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada alat dengan stok menipis</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Log Aktivitas -->
                    <div class="col-md-6">
                        <div class="card card-dashboard h-100">
                            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0">Log Aktivitas Terbaru</h5>
                                <a href="log_aktivitas.php" class="btn btn-sm btn-primary">Lihat Semua</a>
                            </div>
                            <div class="card-body">
                                <?php if (count($log_aktivitas) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Waktu</th>
                                                    <th>Pengguna</th>
                                                    <th>Aktivitas</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($log_aktivitas as $log): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                                        <td><?php echo $log['nama_lengkap'] ?? 'System'; ?></td>
                                                        <td><?php echo $log['aktivitas']; ?></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada log aktivitas</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</body>
</html>
