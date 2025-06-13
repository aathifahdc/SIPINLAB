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

// Filter tanggal
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-01');
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$jenis_laporan = isset($_GET['jenis_laporan']) ? $_GET['jenis_laporan'] : 'peminjaman';

// Ambil data untuk laporan
$conn = $database->getConnection();

if ($jenis_laporan == 'peminjaman') {
    // Laporan peminjaman
    $query = "SELECT p.*, u.nama_lengkap as nama_peminjam, u.npm, a.nama as nama_alat, a.kode as kode_alat, 
              l.nama as nama_lab, dp.jumlah, dp.status_kembali, admin.nama_lengkap as nama_admin
              FROM peminjaman p
              JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
              JOIN alat a ON dp.id_alat = a.id
              JOIN laboratorium l ON a.id_laboratorium = l.id
              JOIN pengguna u ON p.id_peminjam = u.id
              LEFT JOIN pengguna admin ON p.disetujui_oleh = admin.id
              WHERE p.tanggal_pinjam BETWEEN ? AND ?
              ORDER BY p.tanggal_pinjam DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $data_laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistik peminjaman
    $query = "SELECT COUNT(*) as total_peminjaman,
              SUM(CASE WHEN p.status = 'menunggu' THEN 1 ELSE 0 END) as total_menunggu,
              SUM(CASE WHEN p.status = 'dipinjam' THEN 1 ELSE 0 END) as total_dipinjam,
              SUM(CASE WHEN p.status = 'dikembalikan' THEN 1 ELSE 0 END) as total_dikembalikan,
              SUM(CASE WHEN p.status = 'terlambat' THEN 1 ELSE 0 END) as total_terlambat,
              SUM(CASE WHEN p.status = 'dibatalkan' THEN 1 ELSE 0 END) as total_dibatalkan
              FROM peminjaman p
              WHERE p.tanggal_pinjam BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $statistik = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($jenis_laporan == 'alat') {
    // Laporan alat
    $query = "SELECT a.*, k.nama as kategori, l.nama as laboratorium,
              (SELECT COUNT(*) FROM detail_peminjaman dp JOIN peminjaman p ON dp.id_peminjaman = p.id WHERE dp.id_alat = a.id AND p.tanggal_pinjam BETWEEN ? AND ?) as total_dipinjam
              FROM alat a
              JOIN kategori_alat k ON a.id_kategori = k.id
              JOIN laboratorium l ON a.id_laboratorium = l.id
              ORDER BY total_dipinjam DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $data_laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistik alat
    $query = "SELECT COUNT(*) as total_alat,
              SUM(jumlah_total) as total_stok,
              SUM(jumlah_tersedia) as total_tersedia,
              SUM(CASE WHEN kondisi = 'baik' THEN 1 ELSE 0 END) as total_baik,
              SUM(CASE WHEN kondisi = 'rusak_ringan' THEN 1 ELSE 0 END) as total_rusak_ringan,
              SUM(CASE WHEN kondisi = 'rusak_berat' THEN 1 ELSE 0 END) as total_rusak_berat
              FROM alat";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $statistik = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($jenis_laporan == 'mahasiswa') {
    // Laporan mahasiswa
    $query = "SELECT u.*, 
              (SELECT COUNT(*) FROM peminjaman WHERE id_peminjam = u.id AND tanggal_pinjam BETWEEN ? AND ?) as total_peminjaman,
              (SELECT COUNT(*) FROM peminjaman WHERE id_peminjam = u.id AND status = 'terlambat' AND tanggal_pinjam BETWEEN ? AND ?) as total_terlambat
              FROM pengguna u
              WHERE u.role = 'mahasiswa'
              ORDER BY total_peminjaman DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59', $tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $data_laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistik mahasiswa
    $query = "SELECT COUNT(*) as total_mahasiswa,
              (SELECT COUNT(*) FROM peminjaman p JOIN pengguna u ON p.id_peminjam = u.id WHERE u.role = 'mahasiswa' AND p.tanggal_pinjam BETWEEN ? AND ?) as total_peminjaman,
              (SELECT COUNT(DISTINCT id_peminjam) FROM peminjaman WHERE tanggal_pinjam BETWEEN ? AND ?) as total_peminjam_aktif
              FROM pengguna
              WHERE role = 'mahasiswa'";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59', $tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $statistik = $stmt->fetch(PDO::FETCH_ASSOC);
} elseif ($jenis_laporan == 'kerusakan') {
    // Laporan kerusakan
    $query = "SELECT lk.*, a.nama as nama_alat, a.kode as kode_alat, u.nama_lengkap as nama_pelapor, l.nama as nama_lab
              FROM laporan_kerusakan lk
              JOIN alat a ON lk.id_alat = a.id
              JOIN pengguna u ON lk.id_pelapor = u.id
              JOIN laboratorium l ON a.id_laboratorium = l.id
              WHERE lk.tanggal_laporan BETWEEN ? AND ?
              ORDER BY lk.tanggal_laporan DESC";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $data_laporan = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Statistik kerusakan
    $query = "SELECT COUNT(*) as total_laporan,
              SUM(CASE WHEN status = 'menunggu' THEN 1 ELSE 0 END) as total_menunggu,
              SUM(CASE WHEN status = 'diproses' THEN 1 ELSE 0 END) as total_diproses,
              SUM(CASE WHEN status = 'selesai' THEN 1 ELSE 0 END) as total_selesai
              FROM laporan_kerusakan
              WHERE tanggal_laporan BETWEEN ? AND ?";
    $stmt = $conn->prepare($query);
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
    $statistik = $stmt->fetch(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Laporan</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        .sidebar {
            min-height: 100vh;
            background-color: #343a40;
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
        .stats-card {
            transition: transform 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
        }
        @media print {
            .sidebar, .no-print {
                display: none !important;
            }
            .main-content {
                margin-left: 0 !important;
                padding: 0 !important;
            }
            .card {
                box-shadow: none !important;
                border: 1px solid #ddd !important;
            }
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
                            <a class="nav-link" href="dashboard.php">
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
                            <a class="nav-link active" href="laporan.php">
                                <i class="bi bi-file-earmark-text me-2"></i> Laporan
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="backup.php">
                                <i class="bi bi-cloud-arrow-up me-2"></i> Backup
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
                    <h1 class="h2">Laporan</h1>
                    <button type="button" class="btn btn-primary no-print" onclick="window.print()">
                        <i class="bi bi-printer"></i> Cetak Laporan
                    </button>
                </div>
                
                <!-- Filter Laporan -->
                <div class="card mb-4 no-print">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="jenis_laporan" class="form-label">Jenis Laporan</label>
                                <select name="jenis_laporan" id="jenis_laporan" class="form-select">
                                    <option value="peminjaman" <?php echo $jenis_laporan == 'peminjaman' ? 'selected' : ''; ?>>Peminjaman</option>
                                    <option value="alat" <?php echo $jenis_laporan == 'alat' ? 'selected' : ''; ?>>Alat</option>
                                    <option value="mahasiswa" <?php echo $jenis_laporan == 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
                                    <option value="kerusakan" <?php echo $jenis_laporan == 'kerusakan' ? 'selected' : ''; ?>>Kerusakan</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?php echo $tanggal_awal; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo $tanggal_akhir; ?>">
                            </div>
                            <div class="col-md-3 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Tampilkan Laporan</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Header Laporan -->
                <div class="text-center mb-4">
                    <h3>LAPORAN <?php echo strtoupper($jenis_laporan); ?> SIPINLAB</h3>
                    <p>Periode: <?php echo date('d/m/Y', strtotime($tanggal_awal)); ?> - <?php echo date('d/m/Y', strtotime($tanggal_akhir)); ?></p>
                </div>
                
                <!-- Statistik -->
                <div class="row mb-4">
                    <?php if ($jenis_laporan == 'peminjaman'): ?>
                        <div class="col-md-2">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total</h5>
                                    <h3><?php echo $statistik['total_peminjaman']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card stats-card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h5>Menunggu</h5>
                                    <h3><?php echo $statistik['total_menunggu']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Dipinjam</h5>
                                    <h3><?php echo $statistik['total_dipinjam']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Dikembalikan</h5>
                                    <h3><?php echo $statistik['total_dikembalikan']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card stats-card bg-danger text-white">
                                <div class="card-body text-center">
                                    <h5>Terlambat</h5>
                                    <h3><?php echo $statistik['total_terlambat']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <div class="card stats-card bg-secondary text-white">
                                <div class="card-body text-center">
                                    <h5>Dibatalkan</h5>
                                    <h3><?php echo $statistik['total_dibatalkan']; ?></h3>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($jenis_laporan == 'alat'): ?>
                        <div class="col-md-4">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Alat</h5>
                                    <h3><?php echo $statistik['total_alat']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Total Stok</h5>
                                    <h3><?php echo $statistik['total_stok']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Tersedia</h5>
                                    <h3><?php echo $statistik['total_tersedia']; ?></h3>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($jenis_laporan == 'mahasiswa'): ?>
                        <div class="col-md-4">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Mahasiswa</h5>
                                    <h3><?php echo $statistik['total_mahasiswa']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Total Peminjaman</h5>
                                    <h3><?php echo $statistik['total_peminjaman']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Peminjam Aktif</h5>
                                    <h3><?php echo $statistik['total_peminjam_aktif']; ?></h3>
                                </div>
                            </div>
                        </div>
                    <?php elseif ($jenis_laporan == 'kerusakan'): ?>
                        <div class="col-md-3">
                            <div class="card stats-card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5>Total Laporan</h5>
                                    <h3><?php echo $statistik['total_laporan']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-warning text-dark">
                                <div class="card-body text-center">
                                    <h5>Menunggu</h5>
                                    <h3><?php echo $statistik['total_menunggu']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5>Diproses</h5>
                                    <h3><?php echo $statistik['total_diproses']; ?></h3>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card stats-card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5>Selesai</h5>
                                    <h3><?php echo $statistik['total_selesai']; ?></h3>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <!-- Data Laporan -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Data Laporan <?php echo ucfirst($jenis_laporan); ?></h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($data_laporan) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <?php if ($jenis_laporan == 'peminjaman'): ?>
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal Pinjam</th>
                                                <th>Tanggal Kembali</th>
                                                <th>Peminjam</th>
                                                <th>NPM</th>
                                                <th>Alat</th>
                                                <th>Lab</th>
                                                <th>Jumlah</th>
                                                <th>Status</th>
                                                <th>Disetujui Oleh</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($data_laporan as $data): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($data['tanggal_pinjam'])); ?></td>
                                                    <td><?php echo $data['tanggal_kembali'] ? date('d/m/Y H:i', strtotime($data['tanggal_kembali'])) : '-'; ?></td>
                                                    <td><?php echo $data['nama_peminjam']; ?></td>
                                                    <td><?php echo $data['npm']; ?></td>
                                                    <td><?php echo $data['kode_alat'] . ' - ' . $data['nama_alat']; ?></td>
                                                    <td><?php echo $data['nama_lab']; ?></td>
                                                    <td><?php echo $data['jumlah']; ?></td>
                                                    <td>
                                                        <?php if ($data['status'] == 'menunggu'): ?>
                                                            <span class="badge bg-warning text-dark">Menunggu</span>
                                                        <?php elseif ($data['status'] == 'dipinjam'): ?>
                                                            <span class="badge bg-primary">Dipinjam</span>
                                                        <?php elseif ($data['status'] == 'dikembalikan'): ?>
                                                            <span class="badge bg-success">Dikembalikan</span>
                                                        <?php elseif ($data['status'] == 'terlambat'): ?>
                                                            <span class="badge bg-danger">Terlambat</span>
                                                        <?php elseif ($data['status'] == 'dibatalkan'): ?>
                                                            <span class="badge bg-secondary">Dibatalkan</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $data['nama_admin'] ?? '-'; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    <?php elseif ($jenis_laporan == 'alat'): ?>
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Kode</th>
                                                <th>Nama Alat</th>
                                                <th>Kategori</th>
                                                <th>Laboratorium</th>
                                                <th>Stok Total</th>
                                                <th>Tersedia</th>
                                                <th>Kondisi</th>
                                                <th>Total Dipinjam</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($data_laporan as $data): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo $data['kode']; ?></td>
                                                    <td><?php echo $data['nama']; ?></td>
                                                    <td><?php echo $data['kategori']; ?></td>
                                                    <td><?php echo $data['laboratorium']; ?></td>
                                                    <td><?php echo $data['jumlah_total']; ?></td>
                                                    <td><?php echo $data['jumlah_tersedia']; ?></td>
                                                    <td>
                                                        <?php if ($data['kondisi'] == 'baik'): ?>
                                                            <span class="badge bg-success">Baik</span>
                                                        <?php elseif ($data['kondisi'] == 'rusak_ringan'): ?>
                                                            <span class="badge bg-warning text-dark">Rusak Ringan</span>
                                                        <?php elseif ($data['kondisi'] == 'rusak_berat'): ?>
                                                            <span class="badge bg-danger">Rusak Berat</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td><?php echo $data['total_dipinjam']; ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    <?php elseif ($jenis_laporan == 'mahasiswa'): ?>
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>NPM</th>
                                                <th>Nama Mahasiswa</th>
                                                <th>Username</th>
                                                <th>Total Peminjaman</th>
                                                <th>Total Terlambat</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($data_laporan as $data): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo $data['npm']; ?></td>
                                                    <td><?php echo $data['nama_lengkap']; ?></td>
                                                    <td><?php echo $data['username']; ?></td>
                                                    <td><?php echo $data['total_peminjaman']; ?></td>
                                                    <td>
                                                        <?php if ($data['total_terlambat'] > 0): ?>
                                                            <span class="badge bg-danger"><?php echo $data['total_terlambat']; ?></span>
                                                        <?php else: ?>
                                                            <span class="badge bg-success">0</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    <?php elseif ($jenis_laporan == 'kerusakan'): ?>
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Tanggal Laporan</th>
                                                <th>Alat</th>
                                                <th>Laboratorium</th>
                                                <th>Pelapor</th>
                                                <th>Deskripsi Kerusakan</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php $no = 1; foreach ($data_laporan as $data): ?>
                                                <tr>
                                                    <td><?php echo $no++; ?></td>
                                                    <td><?php echo date('d/m/Y H:i', strtotime($data['tanggal_laporan'])); ?></td>
                                                    <td><?php echo $data['kode_alat'] . ' - ' . $data['nama_alat']; ?></td>
                                                    <td><?php echo $data['nama_lab']; ?></td>
                                                    <td><?php echo $data['nama_pelapor']; ?></td>
                                                    <td><?php echo $data['deskripsi_kerusakan']; ?></td>
                                                    <td>
                                                        <?php if ($data['status'] == 'menunggu'): ?>
                                                            <span class="badge bg-warning text-dark">Menunggu</span>
                                                        <?php elseif ($data['status'] == 'diproses'): ?>
                                                            <span class="badge bg-primary">Diproses</span>
                                                        <?php elseif ($data['status'] == 'selesai'): ?>
                                                            <span class="badge bg-success">Selesai</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    <?php endif; ?>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada data untuk periode yang dipilih</p>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Footer Laporan -->
                <div class="mt-4 text-end">
                    <p>Dicetak pada: <?php echo date('d/m/Y H:i:s'); ?></p>
                    <p>Oleh: <?php echo $user['nama_lengkap']; ?></p>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
