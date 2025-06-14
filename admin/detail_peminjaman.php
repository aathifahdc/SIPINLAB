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

// Ambil ID peminjaman dari parameter URL
$id_peminjaman = isset($_GET['id']) ? $_GET['id'] : 0;

if (!$id_peminjaman) {
    header("Location: peminjaman.php");
    exit;
}

// Ambil data peminjaman
$conn = $database->getConnection();
$query = "SELECT p.*, u.nama_lengkap as nama_peminjam, u.npm, a.nama as nama_alat, a.kode as kode_alat, 
          l.nama as nama_lab, dp.jumlah, dp.status_kembali, admin.nama_lengkap as nama_admin
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          JOIN pengguna u ON p.id_peminjam = u.id
          LEFT JOIN pengguna admin ON p.disetujui_oleh = admin.id
          WHERE p.id = ?";
$stmt = $conn->prepare($query);
$stmt->execute([$id_peminjaman]);
$peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$peminjaman) {
    header("Location: peminjaman.php");
    exit;
}

// Proses persetujuan atau penolakan peminjaman
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'approve') {
        // Update status peminjaman menjadi dipinjam
        $query = "UPDATE peminjaman SET status = 'dipinjam', disetujui_oleh = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user['id'], $id_peminjaman]);
        
        // Catat log aktivitas
        $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) VALUES (?, 'Menyetujui peminjaman', ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user['id'], "Peminjaman ID: $id_peminjaman"]);
        
        $message = 'Peminjaman berhasil disetujui';
        
        // Refresh data peminjaman
        $stmt = $conn->prepare($query);
        $stmt->execute([$id_peminjaman]);
        $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);
    } elseif ($action == 'reject') {
        // Update status peminjaman menjadi dibatalkan
        $query = "UPDATE peminjaman SET status = 'dibatalkan', disetujui_oleh = ? WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user['id'], $id_peminjaman]);
        
        // Catat log aktivitas
        $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) VALUES (?, 'Menolak peminjaman', ?)";
        $stmt = $conn->prepare($query);
        $stmt->execute([$user['id'], "Peminjaman ID: $id_peminjaman"]);
        
        $message = 'Peminjaman berhasil ditolak';
        
        // Refresh data peminjaman
        $stmt = $conn->prepare($query);
        $stmt->execute([$id_peminjaman]);
        $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Detail Peminjaman</title>
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
        .detail-label {
            font-weight: bold;
            color: #6c757d;
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
                            <a class="nav-link active" href="peminjaman.php">
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
                    <h1 class="h2">Detail Peminjaman</h1>
                    <div>
                        <a href="peminjaman.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Kembali
                        </a>
                        <button type="button" class="btn btn-primary" onclick="window.print()">
                            <i class="bi bi-printer"></i> Cetak
                        </button>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <!-- Detail Peminjaman -->
                <div class="card card-dashboard">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Informasi Peminjaman #<?php echo $peminjaman['id']; ?></h5>
                    </div>
                    <div class="card-body">
                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h6 class="text-primary">Informasi Peminjaman</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="detail-label">ID Peminjaman</td>
                                        <td>: <?php echo $peminjaman['id']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Tanggal Pinjam</td>
                                        <td>: <?php echo date('d/m/Y H:i', strtotime($peminjaman['tanggal_pinjam'])); ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Tanggal Kembali</td>
                                        <td>: <?php echo $peminjaman['tanggal_kembali'] ? date('d/m/Y H:i', strtotime($peminjaman['tanggal_kembali'])) : '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Status</td>
                                        <td>: 
                                            <?php if ($peminjaman['status'] == 'menunggu'): ?>
                                                <span class="badge bg-warning text-dark">Menunggu</span>
                                            <?php elseif ($peminjaman['status'] == 'dipinjam'): ?>
                                                <span class="badge bg-primary">Dipinjam</span>
                                            <?php elseif ($peminjaman['status'] == 'dikembalikan'): ?>
                                                <span class="badge bg-success">Dikembalikan</span>
                                            <?php elseif ($peminjaman['status'] == 'terlambat'): ?>
                                                <span class="badge bg-danger">Terlambat</span>
                                            <?php elseif ($peminjaman['status'] == 'dibatalkan'): ?>
                                                <span class="badge bg-secondary">Dibatalkan</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Disetujui Oleh</td>
                                        <td>: <?php echo $peminjaman['nama_admin'] ?? '-'; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Keterangan</td>
                                        <td>: <?php echo $peminjaman['keterangan'] ?? '-'; ?></td>
                                    </tr>
                                </table>
                            </div>
                            <div class="col-md-6">
                                <h6 class="text-primary">Informasi Peminjam</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="detail-label">Nama Peminjam</td>
                                        <td>: <?php echo $peminjaman['nama_peminjam']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">NPM</td>
                                        <td>: <?php echo $peminjaman['npm']; ?></td>
                                    </tr>
                                </table>
                                
                                <h6 class="text-primary mt-4">Informasi Alat</h6>
                                <table class="table table-borderless">
                                    <tr>
                                        <td class="detail-label">Kode Alat</td>
                                        <td>: <?php echo $peminjaman['kode_alat']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Nama Alat</td>
                                        <td>: <?php echo $peminjaman['nama_alat']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Laboratorium</td>
                                        <td>: <?php echo $peminjaman['nama_lab']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Jumlah</td>
                                        <td>: <?php echo $peminjaman['jumlah']; ?></td>
                                    </tr>
                                    <tr>
                                        <td class="detail-label">Status Kembali</td>
                                        <td>: 
                                            <?php if ($peminjaman['status_kembali'] == 'belum'): ?>
                                                <span class="badge bg-warning text-dark">Belum</span>
                                            <?php elseif ($peminjaman['status_kembali'] == 'sudah'): ?>
                                                <span class="badge bg-success">Sudah</span>
                                            <?php elseif ($peminjaman['status_kembali'] == 'rusak'): ?>
                                                <span class="badge bg-danger">Rusak</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                        
                        <?php if ($peminjaman['status'] == 'menunggu'): ?>
                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#rejectModal">
                                    <i class="bi bi-x-lg"></i> Tolak Peminjaman
                                </button>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#approveModal">
                                    <i class="bi bi-check-lg"></i> Setujui Peminjaman
                                </button>
                            </div>
                            
                            <!-- Modal Approve -->
                            <div class="modal fade" id="approveModal" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="approveModalLabel">Konfirmasi Persetujuan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Apakah Anda yakin ingin menyetujui peminjaman ini?</p>
                                            <p><strong>Peminjam:</strong> <?php echo $peminjaman['nama_peminjam']; ?></p>
                                            <p><strong>Alat:</strong> <?php echo $peminjaman['nama_alat']; ?></p>
                                            <p><strong>Jumlah:</strong> <?php echo $peminjaman['jumlah']; ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <form action="" method="POST">
                                                <input type="hidden" name="action" value="approve">
                                                <button type="submit" class="btn btn-success">Setujui</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modal Reject -->
                            <div class="modal fade" id="rejectModal" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="rejectModalLabel">Konfirmasi Penolakan</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Apakah Anda yakin ingin menolak peminjaman ini?</p>
                                            <p><strong>Peminjam:</strong> <?php echo $peminjaman['nama_peminjam']; ?></p>
                                            <p><strong>Alat:</strong> <?php echo $peminjaman['nama_alat']; ?></p>
                                            <p><strong>Jumlah:</strong> <?php echo $peminjaman['jumlah']; ?></p>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <form action="" method="POST">
                                                <input type="hidden" name="action" value="reject">
                                                <button type="submit" class="btn btn-danger">Tolak</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php elseif ($peminjaman['status'] == 'dipinjam'): ?>
                            <div class="d-flex justify-content-end mt-3">
                                <a href="pengembalian.php" class="btn btn-primary">
                                    <i class="bi bi-box-arrow-in-left"></i> Proses Pengembalian
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
