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
$tanggal_awal = isset($_GET['tanggal_awal']) ? $_GET['tanggal_awal'] : date('Y-m-d', strtotime('-7 days'));
$tanggal_akhir = isset($_GET['tanggal_akhir']) ? $_GET['tanggal_akhir'] : date('Y-m-d');
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query untuk mengambil data log aktivitas
$conn = $database->getConnection();
$query = "SELECT la.*, u.nama_lengkap
          FROM log_aktivitas la
          LEFT JOIN pengguna u ON la.id_pengguna = u.id
          WHERE la.created_at BETWEEN ? AND ?";

// Tambahkan filter pencarian jika ada
if (!empty($search)) {
    $query .= " AND (la.aktivitas LIKE ? OR la.detail LIKE ? OR u.nama_lengkap LIKE ?)";
}

$query .= " ORDER BY la.created_at DESC";

$stmt = $conn->prepare($query);

// Bind parameter
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59', $search_param, $search_param, $search_param]);
} else {
    $stmt->execute([$tanggal_awal . ' 00:00:00', $tanggal_akhir . ' 23:59:59']);
}

$log_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Log Aktivitas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
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
                    <h1 class="h2">Log Aktivitas</h1>
                    <button type="button" class="btn btn-primary" onclick="window.print()">
                        <i class="bi bi-printer"></i> Cetak Log
                    </button>
                </div>
                
                <!-- Filter dan Pencarian -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-3">
                                <label for="tanggal_awal" class="form-label">Tanggal Awal</label>
                                <input type="date" class="form-control" id="tanggal_awal" name="tanggal_awal" value="<?php echo $tanggal_awal; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="tanggal_akhir" class="form-label">Tanggal Akhir</label>
                                <input type="date" class="form-control" id="tanggal_akhir" name="tanggal_akhir" value="<?php echo $tanggal_akhir; ?>">
                            </div>
                            <div class="col-md-4">
                                <label for="search" class="form-label">Cari</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Aktivitas, detail, atau pengguna" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Log Aktivitas -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Log Aktivitas</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($log_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Waktu</th>
                                            <th>Pengguna</th>
                                            <th>Aktivitas</th>
                                            <th>Detail</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($log_list as $log): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i:s', strtotime($log['created_at'])); ?></td>
                                                <td><?php echo $log['nama_lengkap'] ?? 'System'; ?></td>
                                                <td><?php echo $log['aktivitas']; ?></td>
                                                <td><?php echo $log['detail']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada data log aktivitas</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
