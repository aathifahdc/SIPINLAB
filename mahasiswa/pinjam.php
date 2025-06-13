<?php
require_once '../config/database.php';
require_once '../config/auth.php';

// Inisialisasi database dan auth
$database = new Database();
$auth = new Auth($database);

// Cek login dan role mahasiswa
if (!$auth->isLoggedIn() || !$auth->isMahasiswa()) {
    header("Location: ../index.php");
    exit;
}

// Ambil data user
$user = $auth->getUser();

// Ambil data laboratorium
$conn = $database->getConnection();
$query = "SELECT * FROM laboratorium ORDER BY nama ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$laboratorium = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter lab dan pencarian
$id_lab = $_GET['lab'] ?? '';
$search = $_GET['search'] ?? '';

// Ambil data alat tersedia
$query = "SELECT a.*, k.nama AS kategori, l.nama AS laboratorium
          FROM alat a
          JOIN kategori_alat k ON a.id_kategori = k.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE a.jumlah_tersedia > 0";

if (!empty($id_lab)) {
    $query .= " AND a.id_laboratorium = :id_lab";
}
if (!empty($search)) {
    $query .= " AND (a.nama LIKE :search OR a.kode LIKE :search OR k.nama LIKE :search)";
}
$query .= " ORDER BY a.nama ASC";

$stmt = $conn->prepare($query);
if (!empty($id_lab)) {
    $stmt->bindParam(':id_lab', $id_lab);
}
if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}
$stmt->execute();
$alat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Proses peminjaman
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_alat = isset($_POST['id_alat']) ? (int)$_POST['id_alat'] : 0;
    $jumlah = isset($_POST['jumlah']) ? (int)$_POST['jumlah'] : 1;
    $tanggal_pinjam = $_POST['tanggal_pinjam'] ?? date('Y-m-d H:i:s');
    $keterangan = $_POST['keterangan'] ?? '';

    if ($id_alat === 0 || empty($tanggal_pinjam)) {
        $error = 'Data peminjaman tidak lengkap.';
    } else {
        try {
            // Jalankan stored procedure
            $database->executeStoredProcedure('pinjam_alat', [
                $user['id'],
                $tanggal_pinjam,
                $keterangan,
                $id_alat,
                $jumlah
            ]);
            $success = 'Permintaan peminjaman berhasil diajukan.';
        } catch (PDOException $e) {
            $error = $e->getMessage(); // Tangkap error dari trigger atau procedure
        }
    }
}
?>


<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Pinjam Alat</title>
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
        .card-alat {
            margin-bottom: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            transition: transform 0.3s;
        }
        .card-alat:hover {
            transform: translateY(-5px);
        }
        .card-alat .card-header {
            background-color: #f8f9fa;
            font-weight: bold;
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
                            <a class="nav-link active" href="pinjam.php">
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
                    <h1 class="h2">Pinjam Alat</h1>
                </div>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                
                <!-- Filter dan Pencarian -->
                <div class="card mb-4">
                    <div class="card-body">
                        <form action="" method="GET" class="row g-3">
                            <div class="col-md-4">
                                <label for="lab" class="form-label">Filter Laboratorium</label>
                                <select name="lab" id="lab" class="form-select">
                                    <option value="">Semua Laboratorium</option>
                                    <?php foreach ($laboratorium as $lab): ?>
                                        <option value="<?php echo $lab['id']; ?>" <?php echo $id_lab == $lab['id'] ? 'selected' : ''; ?>>
                                            <?php echo $lab['nama']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Cari Alat</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Nama atau kode alat" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Alat -->
<div class="row">
    <?php if (empty($alat_list)): ?>
        <div class="col-12">
            <div class="alert alert-warning text-center">Tidak ada alat yang tersedia.</div>
        </div>
    <?php else: ?>
        <?php foreach ($alat_list as $alat): ?>
            <div class="col-md-6 col-lg-4">
                <div class="card card-alat">
                    <div class="card-header">
                        <?php echo htmlspecialchars($alat['nama']); ?>
                    </div>
                    <div class="card-body">
                        <p><strong>Kode:</strong> <?php echo htmlspecialchars($alat['kode']); ?></p>
                        <p><strong>Kategori:</strong> <?php echo htmlspecialchars($alat['kategori']); ?></p>
                        <p><strong>Laboratorium:</strong> <?php echo htmlspecialchars($alat['laboratorium']); ?></p>
                        <p><strong>Jumlah Tersedia:</strong> <?php echo $alat['jumlah_tersedia']; ?></p>

                        <!-- Form Peminjaman -->
                        <form action="" method="POST">
                            <input type="hidden" name="id_alat" value="<?php echo $alat['id']; ?>">
                            <div class="mb-2">
                                <label for="jumlah_<?php echo $alat['id']; ?>" class="form-label">Jumlah</label>
                                <input type="number" class="form-control" id="jumlah_<?php echo $alat['id']; ?>" name="jumlah" value="1" min="1" max="<?php echo $alat['jumlah_tersedia']; ?>" required>
                            </div>
                            <div class="mb-2">
                                <label for="tanggal_pinjam_<?php echo $alat['id']; ?>" class="form-label">Tanggal Pinjam</label>
                                <input type="datetime-local" class="form-control" id="tanggal_pinjam_<?php echo $alat['id']; ?>" name="tanggal_pinjam" value="<?php echo date('Y-m-d\TH:i'); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="keterangan_<?php echo $alat['id']; ?>" class="form-label">Keterangan</label>
                                <textarea class="form-control" id="keterangan_<?php echo $alat['id']; ?>" name="keterangan" rows="2" placeholder="Contoh: Untuk praktikum Fisika Dasar"></textarea>
                            </div>
                            <button type="submit" class="btn btn-success w-100">
                                <i class="bi bi-cart-plus"></i> Ajukan Peminjaman
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>
