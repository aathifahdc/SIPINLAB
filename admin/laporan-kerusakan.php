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

// Filter status
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Proses update status laporan kerusakan
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_laporan = $_POST['id_laporan'] ?? 0;
    $status_baru = $_POST['status_baru'] ?? '';
    
    if ($id_laporan && $status_baru) {
        try {
            $conn = $database->getConnection();
            
            // Update status laporan
            $query = "UPDATE laporan_kerusakan SET status = ? WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$status_baru, $id_laporan]);
            
            // Catat log aktivitas
            $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                      VALUES (?, 'Mengupdate status laporan kerusakan', ?)";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user['id'], "Laporan ID: $id_laporan, Status: $status_baru"]);
            
            $message = 'Status laporan kerusakan berhasil diupdate';
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Query untuk mengambil data laporan kerusakan
$conn = $database->getConnection();
$query = "SELECT lk.*, a.nama as nama_alat, a.kode as kode_alat, u.nama_lengkap as nama_pelapor, l.nama as nama_lab
          FROM laporan_kerusakan lk
          JOIN alat a ON lk.id_alat = a.id
          JOIN pengguna u ON lk.id_pelapor = u.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE 1=1";

// Tambahkan filter status jika ada
if (!empty($status)) {
    $query .= " AND lk.status = :status";
}

// Tambahkan filter pencarian jika ada
if (!empty($search)) {
    $query .= " AND (a.nama LIKE :search OR a.kode LIKE :search OR u.nama_lengkap LIKE :search)";
}

$query .= " ORDER BY lk.tanggal_laporan DESC";

$stmt = $conn->prepare($query);

// Bind parameter jika ada filter
if (!empty($status)) {
    $stmt->bindParam(':status', $status);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$laporan_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Laporan Kerusakan</title>
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
                            <a class="nav-link" href="laporan.php">
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
                    <h1 class="h2">Laporan Kerusakan</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Filter dan Pencarian -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="status" class="form-label">Filter Status</label>
                                <select name="status" id="status" class="form-select">
                                    <option value="">Semua Status</option>
                                    <option value="menunggu" <?php echo $status == 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                    <option value="diproses" <?php echo $status == 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                    <option value="selesai" <?php echo $status == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Cari</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Nama alat, kode, atau pelapor" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Laporan Kerusakan -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Laporan Kerusakan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($laporan_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Tanggal Laporan</th>
                                            <th>Alat</th>
                                            <th>Laboratorium</th>
                                            <th>Pelapor</th>
                                            <th>Deskripsi Kerusakan</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($laporan_list as $laporan): ?>
                                            <tr>
                                                <td><?php echo $laporan['id']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($laporan['tanggal_laporan'])); ?></td>
                                                <td><?php echo $laporan['kode_alat'] . ' - ' . $laporan['nama_alat']; ?></td>
                                                <td><?php echo $laporan['nama_lab']; ?></td>
                                                <td><?php echo $laporan['nama_pelapor']; ?></td>
                                                <td><?php echo $laporan['deskripsi_kerusakan']; ?></td>
                                                <td>
                                                    <?php if ($laporan['status'] == 'menunggu'): ?>
                                                        <span class="badge bg-warning text-dark">Menunggu</span>
                                                    <?php elseif ($laporan['status'] == 'diproses'): ?>
                                                        <span class="badge bg-primary">Diproses</span>
                                                    <?php elseif ($laporan['status'] == 'selesai'): ?>
                                                        <span class="badge bg-success">Selesai</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#updateStatusModal<?php echo $laporan['id']; ?>">
                                                        <i class="bi bi-pencil"></i> Update Status
                                                    </button>
                                                    
                                                    <!-- Modal Update Status -->
                                                    <div class="modal fade" id="updateStatusModal<?php echo $laporan['id']; ?>" tabindex="-1" aria-labelledby="updateStatusModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="updateStatusModalLabel">Update Status Laporan Kerusakan</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form action="" method="POST">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="status_baru" class="form-label">Status Baru</label>
                                                                            <select class="form-select" id="status_baru" name="status_baru" required>
                                                                                <option value="menunggu" <?php echo $laporan['status'] == 'menunggu' ? 'selected' : ''; ?>>Menunggu</option>
                                                                                <option value="diproses" <?php echo $laporan['status'] == 'diproses' ? 'selected' : ''; ?>>Diproses</option>
                                                                                <option value="selesai" <?php echo $laporan['status'] == 'selesai' ? 'selected' : ''; ?>>Selesai</option>
                                                                            </select>
                                                                        </div>
                                                                        <input type="hidden" name="id_laporan" value="<?php echo $laporan['id']; ?>">
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada data laporan kerusakan</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
