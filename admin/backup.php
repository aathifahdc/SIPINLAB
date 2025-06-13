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

// Direktori backup
$backup_dir = "../backups/";

// Proses backup manual
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'backup') {
        // Jalankan backup
        $result = $database->backupDatabase($backup_dir);
        
        if ($result) {
            $message = 'Backup database berhasil dilakukan';
            
            // Catat log aktivitas
            $conn = $database->getConnection();
            $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                      VALUES (?, 'Melakukan backup database', 'Backup manual')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$user['id']]);
        } else {
            $error = 'Backup database gagal dilakukan';
        }
    }
}

// Ambil data log backup
$conn = $database->getConnection();
$query = "SELECT * FROM backup_log ORDER BY created_at DESC LIMIT 20";
$stmt = $conn->prepare($query);
$stmt->execute();
$backup_logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cek file backup yang ada di direktori
$backup_files = [];
if (is_dir($backup_dir)) {
    $files = scandir($backup_dir);
    foreach ($files as $file) {
        if ($file != '.' && $file != '..' && pathinfo($file, PATHINFO_EXTENSION) == 'sql') {
            $backup_files[] = [
                'name' => $file,
                'size' => filesize($backup_dir . $file),
                'date' => date('Y-m-d H:i:s', filemtime($backup_dir . $file))
            ];
        }
    }
    
    // Urutkan berdasarkan tanggal terbaru
    usort($backup_files, function($a, $b) {
        return strtotime($b['date']) - strtotime($a['date']);
    });
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Backup Database</title>
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
                            <a class="nav-link active" href="backup.php">
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
                    <h1 class="h2">Backup Database</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Backup Manual -->
                <div class="card card-dashboard mb-4">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Backup Manual</h5>
                    </div>
                    <div class="card-body">
                        <p>Lakukan backup database secara manual dengan menekan tombol di bawah ini. File backup akan disimpan di direktori <code><?php echo $backup_dir; ?></code>.</p>
                        <form action="" method="POST">
                            <input type="hidden" name="action" value="backup">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-cloud-arrow-up"></i> Backup Sekarang
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- Backup Otomatis -->
                <div class="card card-dashboard mb-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Backup Otomatis</h5>
                    </div>
                    <div class="card-body">
                        <p>Backup otomatis dijalankan setiap hari pada pukul 00:00 WIB. File backup akan disimpan di direktori <code><?php echo $backup_dir; ?></code>.</p>
                        <p>Untuk mengubah jadwal backup otomatis, silakan hubungi administrator sistem.</p>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Untuk mengaktifkan backup otomatis, tambahkan cron job berikut di server:
                            <pre><code>0 0 * * * php <?php echo $_SERVER['DOCUMENT_ROOT']; ?>/admin/cron-backup.php</code></pre>
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <!-- File Backup -->
                    <div class="col-md-6">
                        <div class="card card-dashboard">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">File Backup</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($backup_files) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Nama File</th>
                                                    <th>Ukuran</th>
                                                    <th>Tanggal</th>
                                                    <th>Aksi</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($backup_files as $file): ?>
                                                    <tr>
                                                        <td><?php echo $file['name']; ?></td>
                                                        <td><?php echo number_format($file['size'] / 1024, 2); ?> KB</td>
                                                        <td><?php echo $file['date']; ?></td>
                                                        <td>
                                                            <a href="<?php echo $backup_dir . $file['name']; ?>" class="btn btn-sm btn-success" download>
                                                                <i class="bi bi-download"></i> Download
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada file backup</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Log Backup -->
                    <div class="col-md-6">
                        <div class="card card-dashboard">
                            <div class="card-header bg-white">
                                <h5 class="mb-0">Log Backup</h5>
                            </div>
                            <div class="card-body">
                                <?php if (count($backup_logs) > 0): ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Tanggal</th>
                                                    <th>Nama File</th>
                                                    <th>Ukuran</th>
                                                    <th>Status</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($backup_logs as $log): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($log['created_at'])); ?></td>
                                                        <td><?php echo $log['nama_file']; ?></td>
                                                        <td><?php echo number_format($log['ukuran_file'] / 1024, 2); ?> KB</td>
                                                        <td>
                                                            <?php if ($log['status'] == 'sukses'): ?>
                                                                <span class="badge bg-success">Sukses</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger">Gagal</span>
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php else: ?>
                                    <p class="text-center text-muted">Tidak ada log backup</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
