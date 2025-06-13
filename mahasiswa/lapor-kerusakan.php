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

// Ambil data peminjaman aktif untuk dropdown
$conn = $database->getConnection();
$query = "SELECT p.id, dp.id_alat, a.nama as nama_alat, a.kode as kode_alat, l.nama as nama_lab
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE p.id_peminjam = ? AND p.status = 'dipinjam'
          ORDER BY p.tanggal_pinjam DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user['id']]);
$peminjaman_aktif = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses laporan kerusakan
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_alat = $_POST['id_alat'] ?? '';
    $deskripsi = $_POST['deskripsi'] ?? '';
    $tingkat_kerusakan = $_POST['tingkat_kerusakan'] ?? '';
    
    if (empty($id_alat) || empty($deskripsi) || empty($tingkat_kerusakan)) {
        $error = 'Semua field harus diisi';
    } else {
        try {
            $conn = $database->getConnection();
            
            // Tanggal laporan saat ini
            $tanggal_laporan = date('Y-m-d H:i:s');
            
            // Insert laporan kerusakan
            $query = "INSERT INTO laporan_kerusakan (id_alat, id_pelapor, deskripsi_kerusakan, tingkat_kerusakan, tanggal_laporan, status) 
                      VALUES (?, ?, ?, ?, ?, 'menunggu')";
            $stmt = $conn->prepare($query);
            $result = $stmt->execute([$id_alat, $user['id'], $deskripsi, $tingkat_kerusakan, $tanggal_laporan]);
            
            if ($result) {
                // Update kondisi alat berdasarkan tingkat kerusakan
                $kondisi = 'baik';
                if ($tingkat_kerusakan == 'ringan') {
                    $kondisi = 'rusak_ringan';
                } elseif ($tingkat_kerusakan == 'sedang' || $tingkat_kerusakan == 'berat') {
                    $kondisi = 'rusak_berat';
                }
                
                $query = "UPDATE alat SET kondisi = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$kondisi, $id_alat]);
                
                // Catat log aktivitas
                $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                          VALUES (?, 'Melaporkan kerusakan alat', ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$user['id'], "Alat ID: $id_alat, Tingkat: $tingkat_kerusakan"]);
                
                $success = 'Laporan kerusakan berhasil dikirim';
            } else {
                $error = 'Gagal mengirim laporan kerusakan';
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan: ' . $e->getMessage();
        }
    }
}

// Ambil data laporan kerusakan yang sudah dibuat
$query = "SELECT lk.*, a.nama as nama_alat, a.kode as kode_alat, l.nama as nama_lab
          FROM laporan_kerusakan lk
          JOIN alat a ON lk.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE lk.id_pelapor = ?
          ORDER BY lk.tanggal_laporan DESC";
$stmt = $conn->prepare($query);
$stmt->execute([$user['id']]);
$laporan_kerusakan = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Lapor Kerusakan</title>
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
                            <a class="nav-link" href="riwayat.php">
                                <i class="bi bi-clock-history me-2"></i> Riwayat Peminjaman
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link active" href="lapor-kerusakan.php">
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
                    <h1 class="h2">Lapor Kerusakan Alat</h1>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Form Lapor Kerusakan -->
                <div class="card card-dashboard mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Form Laporan Kerusakan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($peminjaman_aktif) > 0): ?>
                            <form action="" method="POST">
                                <div class="mb-3">
                                    <label for="id_alat" class="form-label">Pilih Alat yang Dipinjam</label>
                                    <select name="id_alat" id="id_alat" class="form-select" required>
                                        <option value="">-- Pilih Alat --</option>
                                        <?php foreach ($peminjaman_aktif as $pinjam): ?>
                                            <option value="<?php echo $pinjam['id_alat']; ?>">
                                                <?php echo $pinjam['kode_alat'] . ' - ' . $pinjam['nama_alat'] . ' (' . $pinjam['nama_lab'] . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="tingkat_kerusakan" class="form-label">Tingkat Kerusakan</label>
                                    <select name="tingkat_kerusakan" id="tingkat_kerusakan" class="form-select" required>
                                        <option value="">-- Pilih Tingkat Kerusakan --</option>
                                        <option value="ringan">Ringan</option>
                                        <option value="sedang">Sedang</option>
                                        <option value="berat">Berat</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label for="deskripsi" class="form-label">Deskripsi Kerusakan</label>
                                    <textarea name="deskripsi" id="deskripsi" class="form-control" rows="4" required placeholder="Jelaskan detail kerusakan alat..."></textarea>
                                </div>
                                <button type="submit" class="btn btn-primary">Kirim Laporan</button>
                            </form>
                        <?php else: ?>
                            <div class="alert alert-info">
                                Anda tidak memiliki peminjaman aktif. Silakan pinjam alat terlebih dahulu untuk dapat melaporkan kerusakan.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Riwayat Laporan Kerusakan -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Riwayat Laporan Kerusakan</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($laporan_kerusakan) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tanggal Laporan</th>
                                            <th>Kode Alat</th>
                                            <th>Nama Alat</th>
                                            <th>Laboratorium</th>
                                            <th>Tingkat Kerusakan</th>
                                            <th>Status</th>
                                            <th>Deskripsi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($laporan_kerusakan as $laporan): ?>
                                            <tr>
                                                <td><?php echo date('d/m/Y H:i', strtotime($laporan['tanggal_laporan'])); ?></td>
                                                <td><?php echo $laporan['kode_alat']; ?></td>
                                                <td><?php echo $laporan['nama_alat']; ?></td>
                                                <td><?php echo $laporan['nama_lab']; ?></td>
                                                <td>
                                                    <?php if ($laporan['tingkat_kerusakan'] == 'ringan'): ?>
                                                        <span class="badge bg-success">Ringan</span>
                                                    <?php elseif ($laporan['tingkat_kerusakan'] == 'sedang'): ?>
                                                        <span class="badge bg-warning">Sedang</span>
                                                    <?php elseif ($laporan['tingkat_kerusakan'] == 'berat'): ?>
                                                        <span class="badge bg-danger">Berat</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($laporan['status'] == 'menunggu'): ?>
                                                        <span class="badge bg-warning">Menunggu</span>
                                                    <?php elseif ($laporan['status'] == 'diproses'): ?>
                                                        <span class="badge bg-primary">Diproses</span>
                                                    <?php elseif ($laporan['status'] == 'selesai'): ?>
                                                        <span class="badge bg-success">Selesai</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $laporan['deskripsi_kerusakan']; ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada riwayat laporan kerusakan</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
