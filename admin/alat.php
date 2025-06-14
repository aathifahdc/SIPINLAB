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

// Ambil data laboratorium dan kategori untuk form tambah/edit
$conn = $database->getConnection();
$query = "SELECT * FROM laboratorium ORDER BY nama ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$laboratorium = $stmt->fetchAll(PDO::FETCH_ASSOC);

$query = "SELECT * FROM kategori_alat ORDER BY nama ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$kategori = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Filter berdasarkan laboratorium dan kategori
$id_lab = isset($_GET['lab']) ? $_GET['lab'] : '';
$id_kategori = isset($_GET['kategori']) ? $_GET['kategori'] : '';
$filter = isset($_GET['filter']) ? $_GET['filter'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Proses tambah alat
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $kode = $_POST['kode'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $jumlah_total = $_POST['jumlah_total'] ?? 1;
        $kondisi = $_POST['kondisi'] ?? 'baik';
        $id_kategori_post = $_POST['id_kategori'] ?? '';
        $id_laboratorium_post = $_POST['id_laboratorium'] ?? '';
        
        if (empty($kode) || empty($nama) || empty($id_kategori_post) || empty($id_laboratorium_post)) {
            $error = 'Semua field harus diisi';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Cek apakah kode alat sudah ada
                $query = "SELECT COUNT(*) as count FROM alat WHERE kode = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$kode]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Kode alat sudah digunakan';
                } else {
                    // Tambah alat baru
                    $query = "INSERT INTO alat (kode, nama, deskripsi, jumlah_total, jumlah_tersedia, kondisi, id_kategori, id_laboratorium) 
                              VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$kode, $nama, $deskripsi, $jumlah_total, $jumlah_total, $kondisi, $id_kategori_post, $id_laboratorium_post]);
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Menambahkan alat baru', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Alat: $nama ($kode)"]);
                    
                    $message = 'Alat berhasil ditambahkan';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'edit') {
        $id_alat = $_POST['id_alat'] ?? '';
        $kode = $_POST['kode'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $deskripsi = $_POST['deskripsi'] ?? '';
        $jumlah_total = $_POST['jumlah_total'] ?? 1;
        $jumlah_tersedia = $_POST['jumlah_tersedia'] ?? 1;
        $kondisi = $_POST['kondisi'] ?? 'baik';
        $id_kategori_post = $_POST['id_kategori'] ?? '';
        $id_laboratorium_post = $_POST['id_laboratorium'] ?? '';
        
        if (empty($id_alat) || empty($kode) || empty($nama) || empty($id_kategori_post) || empty($id_laboratorium_post)) {
            $error = 'Semua field harus diisi';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Cek apakah kode alat sudah ada (kecuali untuk alat yang sedang diedit)
                $query = "SELECT COUNT(*) as count FROM alat WHERE kode = ? AND id != ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$kode, $id_alat]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Kode alat sudah digunakan';
                } else {
                    // Update alat
                    $query = "UPDATE alat SET kode = ?, nama = ?, deskripsi = ?, jumlah_total = ?, jumlah_tersedia = ?, 
                              kondisi = ?, id_kategori = ?, id_laboratorium = ? WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$kode, $nama, $deskripsi, $jumlah_total, $jumlah_tersedia, $kondisi, $id_kategori_post, $id_laboratorium_post, $id_alat]);
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Mengupdate alat', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Alat: $nama ($kode)"]);
                    
                    $message = 'Alat berhasil diupdate';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id_alat = $_POST['id_alat'] ?? '';
        
        if (empty($id_alat)) {
            $error = 'ID alat tidak valid';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Cek apakah alat sedang dipinjam
                $query = "SELECT COUNT(*) as count FROM detail_peminjaman dp
                          JOIN peminjaman p ON dp.id_peminjaman = p.id
                          WHERE dp.id_alat = ? AND p.status IN ('menunggu', 'dipinjam')";
                $stmt = $conn->prepare($query);
                $stmt->execute([$id_alat]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Alat tidak dapat dihapus karena sedang dipinjam atau dalam proses peminjaman';
                } else {
                    // Ambil data alat sebelum dihapus untuk log
                    $query = "SELECT kode, nama FROM alat WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id_alat]);
                    $alat_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Hapus alat
                    $query = "DELETE FROM alat WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id_alat]);
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Menghapus alat', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Alat: {$alat_info['nama']} ({$alat_info['kode']})"]);
                    
                    $message = 'Alat berhasil dihapus';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Query untuk mengambil data alat
$query = "SELECT a.*, k.nama as kategori, l.nama as laboratorium
          FROM alat a
          JOIN kategori_alat k ON a.id_kategori = k.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          WHERE 1=1";

// Tambahkan filter laboratorium jika ada
if (!empty($id_lab)) {
    $query .= " AND a.id_laboratorium = :id_lab";
}

// Tambahkan filter kategori jika ada
if (!empty($id_kategori)) {
    $query .= " AND a.id_kategori = :id_kategori";
}

// Tambahkan filter stok menipis jika ada
if ($filter == 'menipis') {
    $query .= " AND a.jumlah_tersedia < 3";
}

// Tambahkan filter pencarian jika ada
if (!empty($search)) {
    $query .= " AND (a.nama LIKE :search OR a.kode LIKE :search OR k.nama LIKE :search)";
}

$query .= " ORDER BY a.nama ASC";

$stmt = $conn->prepare($query);

// Bind parameter jika ada filter
if (!empty($id_lab)) {
    $stmt->bindParam(':id_lab', $id_lab);
}

if (!empty($id_kategori)) {
    $stmt->bindParam(':id_kategori', $id_kategori);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$alat_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Kelola Alat</title>
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
                            <a class="nav-link active" href="alat.php">
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
                    <h1 class="h2">Kelola Alat</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAlatModal">
                        <i class="bi bi-plus-lg"></i> Tambah Alat Baru
                    </button>
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
                            <div class="col-md-3">
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
                            <div class="col-md-3">
                                <label for="kategori" class="form-label">Filter Kategori</label>
                                <select name="kategori" id="kategori" class="form-select">
                                    <option value="">Semua Kategori</option>
                                    <?php foreach ($kategori as $kat): ?>
                                        <option value="<?php echo $kat['id']; ?>" <?php echo $id_kategori == $kat['id'] ? 'selected' : ''; ?>>
                                            <?php echo $kat['nama']; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="filter" class="form-label">Filter Khusus</label>
                                <select name="filter" id="filter" class="form-select">
                                    <option value="">Tidak Ada</option>
                                    <option value="menipis" <?php echo $filter == 'menipis' ? 'selected' : ''; ?>>Stok Menipis</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="search" class="form-label">Cari</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="search" name="search" placeholder="Nama atau kode alat" value="<?php echo $search; ?>">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="bi bi-search"></i>
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Alat -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Alat</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($alat_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Kode</th>
                                            <th>Nama Alat</th>
                                            <th>Kategori</th>
                                            <th>Laboratorium</th>
                                            <th>Stok</th>
                                            <th>Kondisi</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($alat_list as $alat): ?>
                                            <tr>
                                                <td><?php echo $alat['kode']; ?></td>
                                                <td><?php echo $alat['nama']; ?></td>
                                                <td><?php echo $alat['kategori']; ?></td>
                                                <td><?php echo $alat['laboratorium']; ?></td>
                                                <td>
                                                    <span class="badge bg-<?php echo $alat['jumlah_tersedia'] == 0 ? 'danger' : ($alat['jumlah_tersedia'] < 3 ? 'warning' : 'success'); ?>">
                                                        <?php echo $alat['jumlah_tersedia']; ?>/<?php echo $alat['jumlah_total']; ?>
                                                    </span>
                                                </td>
                                                <td>
                                                    <?php if ($alat['kondisi'] == 'baik'): ?>
                                                        <span class="badge bg-success">Baik</span>
                                                    <?php elseif ($alat['kondisi'] == 'rusak_ringan'): ?>
                                                        <span class="badge bg-warning text-dark">Rusak Ringan</span>
                                                    <?php elseif ($alat['kondisi'] == 'rusak_berat'): ?>
                                                        <span class="badge bg-danger">Rusak Berat</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editAlatModal<?php echo $alat['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </button>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteAlatModal<?php echo $alat['id']; ?>">
                                                            <i class="bi bi-trash"></i> Hapus
                                                        </button>
                                                    </div>
                                                    
                                                    <!-- Modal Edit Alat -->
                                                    <div class="modal fade" id="editAlatModal<?php echo $alat['id']; ?>" tabindex="-1" aria-labelledby="editAlatModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="editAlatModalLabel">Edit Alat</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form action="" method="POST">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="kode" class="form-label">Kode Alat</label>
                                                                            <input type="text" class="form-control" id="kode" name="kode" value="<?php echo $alat['kode']; ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="nama" class="form-label">Nama Alat</label>
                                                                            <input type="text" class="form-control" id="nama" name="nama" value="<?php echo $alat['nama']; ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="deskripsi" class="form-label">Deskripsi</label>
                                                                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"><?php echo $alat['deskripsi']; ?></textarea>
                                                                        </div>
                                                                        <div class="row mb-3">
                                                                            <div class="col-md-6">
                                                                                <label for="jumlah_total" class="form-label">Jumlah Total</label>
                                                                                <input type="number" class="form-control" id="jumlah_total" name="jumlah_total" value="<?php echo $alat['jumlah_total']; ?>" min="1" required>
                                                                            </div>
                                                                            <div class="col-md-6">
                                                                                <label for="jumlah_tersedia" class="form-label">Jumlah Tersedia</label>
                                                                                <input type="number" class="form-control" id="jumlah_tersedia" name="jumlah_tersedia" value="<?php echo $alat['jumlah_tersedia']; ?>" min="0" required>
                                                                            </div>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="kondisi" class="form-label">Kondisi</label>
                                                                            <select class="form-select" id="kondisi" name="kondisi" required>
                                                                                <option value="baik" <?php echo $alat['kondisi'] == 'baik' ? 'selected' : ''; ?>>Baik</option>
                                                                                <option value="rusak_ringan" <?php echo $alat['kondisi'] == 'rusak_ringan' ? 'selected' : ''; ?>>Rusak Ringan</option>
                                                                                <option value="rusak_berat" <?php echo $alat['kondisi'] == 'rusak_berat' ? 'selected' : ''; ?>>Rusak Berat</option>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="id_kategori" class="form-label">Kategori</label>
                                                                            <select class="form-select" id="id_kategori" name="id_kategori" required>
                                                                                <?php foreach ($kategori as $kat): ?>
                                                                                    <option value="<?php echo $kat['id']; ?>" <?php echo $alat['id_kategori'] == $kat['id'] ? 'selected' : ''; ?>>
                                                                                        <?php echo $kat['nama']; ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="id_laboratorium" class="form-label">Laboratorium</label>
                                                                            <select class="form-select" id="id_laboratorium" name="id_laboratorium" required>
                                                                                <?php foreach ($laboratorium as $lab): ?>
                                                                                    <option value="<?php echo $lab['id']; ?>" <?php echo $alat['id_laboratorium'] == $lab['id'] ? 'selected' : ''; ?>>
                                                                                        <?php echo $lab['nama']; ?>
                                                                                    </option>
                                                                                <?php endforeach; ?>
                                                                            </select>
                                                                        </div>
                                                                        <input type="hidden" name="id_alat" value="<?php echo $alat['id']; ?>">
                                                                        <input type="hidden" name="action" value="edit">
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                                                                    </div>
                                                                </form>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    
                                                    <!-- Modal Hapus Alat -->
                                                    <div class="modal fade" id="deleteAlatModal<?php echo $alat['id']; ?>" tabindex="-1" aria-labelledby="deleteAlatModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="deleteAlatModalLabel">Konfirmasi Hapus</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Apakah Anda yakin ingin menghapus alat ini?</p>
                                                                    <p><strong>Kode:</strong> <?php echo $alat['kode']; ?></p>
                                                                    <p><strong>Nama:</strong> <?php echo $alat['nama']; ?></p>
                                                                    <p><strong>Laboratorium:</strong> <?php echo $alat['laboratorium']; ?></p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                    <form action="" method="POST">
                                                                        <input type="hidden" name="id_alat" value="<?php echo $alat['id']; ?>">
                                                                        <input type="hidden" name="action" value="delete">
                                                                        <button type="submit" class="btn btn-danger">Hapus</button>
                                                                    </form>
                                                                </div>
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
                            <p class="text-center text-muted">Tidak ada data alat</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah Alat -->
    <div class="modal fade" id="addAlatModal" tabindex="-1" aria-labelledby="addAlatModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAlatModalLabel">Tambah Alat Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="kode" class="form-label">Kode Alat</label>
                            <input type="text" class="form-control" id="kode" name="kode" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Alat</label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="deskripsi" class="form-label">Deskripsi</label>
                            <textarea class="form-control" id="deskripsi" name="deskripsi" rows="3"></textarea>
                        </div>
                        <div class="mb-3">
                            <label for="jumlah_total" class="form-label">Jumlah</label>
                            <input type="number" class="form-control" id="jumlah_total" name="jumlah_total" value="1" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label for="kondisi" class="form-label">Kondisi</label>
                            <select class="form-select" id="kondisi" name="kondisi" required>
                                <option value="baik" selected>Baik</option>
                                <option value="rusak_ringan">Rusak Ringan</option>
                                <option value="rusak_berat">Rusak Berat</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="id_kategori" class="form-label">Kategori</label>
                            <select class="form-select" id="id_kategori" name="id_kategori" required>
                                <option value="">Pilih Kategori</option>
                                <?php foreach ($kategori as $kat): ?>
                                    <option value="<?php echo $kat['id']; ?>"><?php echo $kat['nama']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="id_laboratorium" class="form-label">Laboratorium</label>
                            <select class="form-select" id="id_laboratorium" name="id_laboratorium" required>
                                <option value="">Pilih Laboratorium</option>
                                <?php foreach ($laboratorium as $lab): ?>
                                    <option value="<?php echo $lab['id']; ?>"><?php echo $lab['nama']; ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <input type="hidden" name="action" value="add">
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                        <button type="submit" class="btn btn-primary">Simpan</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
