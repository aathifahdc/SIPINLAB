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

// Filter berdasarkan status
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query untuk mengambil data peminjaman
$conn = $database->getConnection();
$query = "SELECT p.*, a.nama as nama_alat, a.kode as kode_alat, l.nama as nama_lab, dp.jumlah
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE p.id_peminjam = ?";

// Tambahkan filter status jika ada
if (!empty($status_filter)) {
    $query .= " AND p.status = :status";
}

// Tambahkan filter pencarian jika ada
if (!empty($search)) {
    $query .= " AND (a.nama LIKE :search OR a.kode LIKE :search OR l.nama LIKE :search)";
}

$query .= " ORDER BY p.tanggal_pinjam DESC";

$stmt = $conn->prepare($query);
$stmt->bindParam(1, $user['id']);

// Bind parameter jika ada filter
if (!empty($status_filter)) {
    $stmt->bindParam(':status', $status_filter);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$peminjaman_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Riwayat Peminjaman</title>
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
                            <a class="nav-link active" href="riwayat.php">
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
                    <h1 class="h2">Riwayat Peminjaman</h1>
                </div>
                
                <!-- Filter dan Pencarian -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Filter Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="menunggu" <?php echo $status_filter == 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="dipinjam" <?php echo $status_filter == 'dipinjam' ? 'selected' : ''; ?>>Dipinjam</option>
                                    <option value="dikembalikan" <?php echo $status_filter == 'dikembalikan' ? 'selected' : ''; ?>>Dikembalikan</option>
                                    <option value="dibatalkan" <?php echo $status_filter == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                    <option value="terlambat" <?php echo $status_filter == 'terlambat' ? 'selected' : ''; ?>>Terlambat</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Cari</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Nama atau kode alat" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Riwayat Peminjaman -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Peminjaman</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($peminjaman_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode Alat</th>
                                            <th>Nama Alat</th>
                                            <th>Laboratorium</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Tanggal Kembali</th>
                                            <th>Jumlah</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_list as $pinjam): ?>
                                            <tr>
                                                <td><?php echo $pinjam['kode_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_lab']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pinjam['tanggal_pinjam'])); ?></td>
                                                <td>
                                                    <?php echo $pinjam['tanggal_kembali'] ? date('d/m/Y H:i', strtotime($pinjam['tanggal_kembali'])) : '-'; ?>
                                                </td>
                                                <td><?php echo $pinjam['jumlah']; ?></td>
                                                <td>
                                                    <?php if ($pinjam['status'] == 'menunggu'): ?>
                                                        <span class="badge bg-warning">Menunggu</span>
                                                    <?php elseif ($pinjam['status'] == 'dipinjam'): ?>
                                                        <span class="badge bg-primary">Dipinjam</span>
                                                    <?php elseif ($pinjam['status'] == 'dikembalikan'): ?>
                                                        <span class="badge bg-success">Dikembalikan</span>
                                                    <?php elseif ($pinjam['status'] == 'dibatalkan'): ?>
                                                        <span class="badge bg-danger">Dibatalkan</span>
                                                    <?php elseif ($pinjam['status'] == 'terlambat'): ?>
                                                        <span class="badge bg-danger">Terlambat</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <a href="detail-peminjaman.php?id=<?php echo $pinjam['id']; ?>" class="btn btn-sm btn-info">
                                                        <i class="bi bi-eye"></i> Detail
                                                    </a>
                                                    <?php if ($pinjam['status'] == 'menunggu'): ?>
                                                        <a href="batalkan-peminjaman.php?id=<?php echo $pinjam['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Apakah Anda yakin ingin membatalkan peminjaman ini?')">
                                                            <i class="bi bi-x-circle"></i> Batalkan
                                                        </a>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada data peminjaman</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
