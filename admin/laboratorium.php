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

// Proses tambah/edit/hapus laboratorium
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $nama = $_POST['nama'] ?? '';
        $lokasi = $_POST['lokasi'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';
        
        if (empty($nama) || empty($lokasi)) {
            $error = 'Nama dan lokasi laboratorium harus diisi';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Tambah laboratorium baru
                $query = "INSERT INTO laboratorium (nama, lokasi, keterangan) VALUES (?, ?, ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$nama, $lokasi, $keterangan]);
                
                // Catat log aktivitas
                $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                          VALUES (?, 'Menambahkan laboratorium baru', ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$user['id'], "Laboratorium: $nama"]);
                
                $message = 'Laboratorium berhasil ditambahkan';
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'edit') {
        $id_lab = $_POST['id_lab'] ?? '';
        $nama = $_POST['nama'] ?? '';
        $lokasi = $_POST['lokasi'] ?? '';
        $keterangan = $_POST['keterangan'] ?? '';
        
        if (empty($id_lab) || empty($nama) || empty($lokasi)) {
            $error = 'Nama dan lokasi laboratorium harus diisi';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Update laboratorium
                $query = "UPDATE laboratorium SET nama = ?, lokasi = ?, keterangan = ? WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$nama, $lokasi, $keterangan, $id_lab]);
                
                // Catat log aktivitas
                $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                          VALUES (?, 'Mengupdate laboratorium', ?)";
                $stmt = $conn->prepare($query);
                $stmt->execute([$user['id'], "Laboratorium: $nama"]);
                
                $message = 'Laboratorium berhasil diupdate';
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id_lab = $_POST['id_lab'] ?? '';
        
        if (empty($id_lab)) {
            $error = 'ID laboratorium tidak valid';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Cek apakah ada alat di laboratorium ini
                $query = "SELECT COUNT(*) as count FROM alat WHERE id_laboratorium = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$id_lab]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Laboratorium tidak dapat dihapus karena masih memiliki alat';
                } else {
                    // Ambil data laboratorium sebelum dihapus untuk log
                    $query = "SELECT nama FROM laboratorium WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id_lab]);
                    $lab_info = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    // Hapus laboratorium
                    $query = "DELETE FROM laboratorium WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id_lab]);
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Menghapus laboratorium', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Laboratorium: {$lab_info['nama']}"]);
                    
                    $message = 'Laboratorium berhasil dihapus';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Ambil data laboratorium
$conn = $database->getConnection();
$query = "SELECT l.*, 
          (SELECT COUNT(*) FROM alat WHERE id_laboratorium = l.id) as jumlah_alat
          FROM laboratorium l
          ORDER BY l.nama ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$laboratorium_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Kelola Laboratorium</title>
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
                            <a class="nav-link active" href="laboratorium.php">
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
                    <h1 class="h2">Kelola Laboratorium</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addLabModal">
                        <i class="bi bi-plus-lg"></i> Tambah Laboratorium Baru
                    </button>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Daftar Laboratorium -->
                <div class="row">
                    <?php foreach ($laboratorium_list as $lab): ?>
                        <div class="col-md-4 mb-4">
                            <div class="card lab-card h-100">
                                <div class="card-header bg-primary text-white">
                                    <h5 class="mb-0"><?php echo $lab['nama']; ?></h5>
                                </div>
                                <div class="card-body">
                                    <p><strong>Lokasi:</strong> <?php echo $lab['lokasi']; ?></p>
                                    <p><strong>Jumlah Alat:</strong> <?php echo $lab['jumlah_alat']; ?></p>
                                    <?php if (!empty($lab['keterangan'])): ?>
                                        <p><strong>Keterangan:</strong> <?php echo $lab['keterangan']; ?></p>
                                    <?php endif; ?>
                                </div>
                                <div class="card-footer bg-white">
                                    <div class="btn-group w-100">
                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editLabModal<?php echo $lab['id']; ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteLabModal<?php echo $lab['id']; ?>">
                                            <i class="bi bi-trash"></i> Hapus
                                        </button>
                                        <a href="alat.php?lab=<?php echo $lab['id']; ?>" class="btn btn-sm btn-success">
                                            <i class="bi bi-tools"></i> Lihat Alat
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Modal Edit Laboratorium -->
                            <div class="modal fade" id="editLabModal<?php echo $lab['id']; ?>" tabindex="-1" aria-labelledby="editLabModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="editLabModalLabel">Edit Laboratorium</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <form action="" method="POST">
                                            <div class="modal-body">
                                                <div class="mb-3">
                                                    <label for="nama" class="form-label">Nama Laboratorium</label>
                                                    <input type="text" class="form-control" id="nama" name="nama" value="<?php echo $lab['nama']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="lokasi" class="form-label">Lokasi</label>
                                                    <input type="text" class="form-control" id="lokasi" name="lokasi" value="<?php echo $lab['lokasi']; ?>" required>
                                                </div>
                                                <div class="mb-3">
                                                    <label for="keterangan" class="form-label">Keterangan</label>
                                                    <textarea class="form-control" id="keterangan" name="keterangan" rows="3"><?php echo $lab['keterangan']; ?></textarea>
                                                </div>
                                                <input type="hidden" name="id_lab" value="<?php echo $lab['id']; ?>">
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
                            
                            <!-- Modal Hapus Laboratorium -->
                            <div class="modal fade" id="deleteLabModal<?php echo $lab['id']; ?>" tabindex="-1" aria-labelledby="deleteLabModalLabel" aria-hidden="true">
                                <div class="modal-dialog">
                                    <div class="modal-content">
                                        <div class="modal-header">
                                            <h5 class="modal-title" id="deleteLabModalLabel">Konfirmasi Hapus</h5>
                                            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                        </div>
                                        <div class="modal-body">
                                            <p>Apakah Anda yakin ingin menghapus laboratorium ini?</p>
                                            <p><strong>Nama:</strong> <?php echo $lab['nama']; ?></p>
                                            <p><strong>Lokasi:</strong> <?php echo $lab['lokasi']; ?></p>
                                            <?php if ($lab['jumlah_alat'] > 0): ?>
                                                <div class="alert alert-warning">
                                                    <i class="bi bi-exclamation-triangle"></i> Laboratorium ini memiliki <?php echo $lab['jumlah_alat']; ?> alat. Anda harus memindahkan atau menghapus alat-alat tersebut terlebih dahulu.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="modal-footer">
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                            <form action="" method="POST">
                                                <input type="hidden" name="id_lab" value="<?php echo $lab['id']; ?>">
                                                <input type="hidden" name="action" value="delete">
                                                <button type="submit" class="btn btn-danger" <?php echo $lab['jumlah_alat'] > 0 ? 'disabled' : ''; ?>>Hapus</button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah Laboratorium -->
    <div class="modal fade" id="addLabModal" tabindex="-1" aria-labelledby="addLabModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addLabModalLabel">Tambah Laboratorium Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="nama" class="form-label">Nama Laboratorium</label>
                            <input type="text" class="form-control" id="nama" name="nama" required>
                        </div>
                        <div class="mb-3">
                            <label for="lokasi" class="form-label">Lokasi</label>
                            <input type="text" class="form-control" id="lokasi" name="lokasi" required>
                        </div>
                        <div class="mb-3">
                            <label for="keterangan" class="form-label">Keterangan</label>
                            <textarea class="form-control" id="keterangan" name="keterangan" rows="3"></textarea>
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
