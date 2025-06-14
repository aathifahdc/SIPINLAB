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

// Proses persetujuan atau penolakan peminjaman
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_peminjaman = $_POST['id_peminjaman'] ?? 0;
    $action = $_POST['action'] ?? '';
    
    if ($id_peminjaman && $action) {
        $conn = $database->getConnection();
        
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
        }
    }
}

// Query untuk mengambil data peminjaman
$conn = $database->getConnection();
$query = "SELECT p.*, u.nama_lengkap as nama_peminjam, u.npm, a.nama as nama_alat, a.kode as kode_alat, 
          l.nama as nama_lab, dp.jumlah, dp.status_kembali
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          JOIN pengguna u ON p.id_peminjam = u.id
          WHERE 1=1";

// Tambahkan filter status jika ada
if (!empty($status)) {
    $query .= " AND p.status = :status";
}

// Tambahkan filter pencarian jika ada
if (!empty($search)) {
    $query .= " AND (u.nama_lengkap LIKE :search OR u.npm LIKE :search OR a.nama LIKE :search OR a.kode LIKE :search)";
}

$query .= " ORDER BY p.created_at DESC";

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
$peminjaman_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Kelola Peminjaman</title>
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
                    <h1 class="h2">Kelola Peminjaman</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
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
                                    <option value="dipinjam" <?php echo $status == 'dipinjam' ? 'selected' : ''; ?>>Dipinjam</option>
                                    <option value="dikembalikan" <?php echo $status == 'dikembalikan' ? 'selected' : ''; ?>>Dikembalikan</option>
                                    <option value="terlambat" <?php echo $status == 'terlambat' ? 'selected' : ''; ?>>Terlambat</option>
                                    <option value="dibatalkan" <?php echo $status == 'dibatalkan' ? 'selected' : ''; ?>>Dibatalkan</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Cari</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Nama peminjam, NPM, atau alat" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Peminjaman -->
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
                                            <th>ID</th>
                                            <th>Peminjam</th>
                                            <th>NPM</th>
                                            <th>Alat</th>
                                            <th>Lab</th>
                                            <th>Jumlah</th>
                                            <th>Tanggal Pinjam</th>
                                            <th>Status</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_list as $pinjam): ?>
                                            <tr>
                                                <td><?php echo $pinjam['id']; ?></td>
                                                <td><?php echo $pinjam['nama_peminjam']; ?></td>
                                                <td><?php echo $pinjam['npm']; ?></td>
                                                <td><?php echo $pinjam['kode_alat'] . ' - ' . $pinjam['nama_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_lab']; ?></td>
                                                <td><?php echo $pinjam['jumlah']; ?></td>
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
                                                    <div class="btn-group">
                                                        <a href="detail_peminjaman.php?id=<?php echo $pinjam['id']; ?>" class="btn btn-sm btn-info">
                                                            <i class="bi bi-eye"></i> Detail
                                                        </a>
                                                        
                                                        <?php if ($pinjam['status'] == 'menunggu'): ?>
                                                            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#approveModal<?php echo $pinjam['id']; ?>">
                                                                <i class="bi bi-check-lg"></i> Setujui
                                                            </button>
                                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#rejectModal<?php echo $pinjam['id']; ?>">
                                                                <i class="bi bi-x-lg"></i> Tolak
                                                            </button>
                                                            
                                                            <!-- Modal Approve -->
                                                            <div class="modal fade" id="approveModal<?php echo $pinjam['id']; ?>" tabindex="-1" aria-labelledby="approveModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="approveModalLabel">Konfirmasi Persetujuan</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Apakah Anda yakin ingin menyetujui peminjaman ini?</p>
                                                                            <p><strong>Peminjam:</strong> <?php echo $pinjam['nama_peminjam']; ?></p>
                                                                            <p><strong>Alat:</strong> <?php echo $pinjam['nama_alat']; ?></p>
                                                                            <p><strong>Jumlah:</strong> <?php echo $pinjam['jumlah']; ?></p>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                            <form action="" method="POST">
                                                                                <input type="hidden" name="id_peminjaman" value="<?php echo $pinjam['id']; ?>">
                                                                                <input type="hidden" name="action" value="approve">
                                                                                <button type="submit" class="btn btn-success">Setujui</button>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                            
                                                            <!-- Modal Reject -->
                                                            <div class="modal fade" id="rejectModal<?php echo $pinjam['id']; ?>" tabindex="-1" aria-labelledby="rejectModalLabel" aria-hidden="true">
                                                                <div class="modal-dialog">
                                                                    <div class="modal-content">
                                                                        <div class="modal-header">
                                                                            <h5 class="modal-title" id="rejectModalLabel">Konfirmasi Penolakan</h5>
                                                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                        </div>
                                                                        <div class="modal-body">
                                                                            <p>Apakah Anda yakin ingin menolak peminjaman ini?</p>
                                                                            <p><strong>Peminjam:</strong> <?php echo $pinjam['nama_peminjam']; ?></p>
                                                                            <p><strong>Alat:</strong> <?php echo $pinjam['nama_alat']; ?></p>
                                                                            <p><strong>Jumlah:</strong> <?php echo $pinjam['jumlah']; ?></p>
                                                                        </div>
                                                                        <div class="modal-footer">
                                                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                            <form action="" method="POST">
                                                                                <input type="hidden" name="id_peminjaman" value="<?php echo $pinjam['id']; ?>">
                                                                                <input type="hidden" name="action" value="reject">
                                                                                <button type="submit" class="btn btn-danger">Tolak</button>
                                                                            </form>
                                                                        </div>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        <?php endif; ?>
                                                    </div>
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
