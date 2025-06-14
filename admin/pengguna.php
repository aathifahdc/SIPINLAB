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

// Proses tambah/edit/hapus pengguna
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action == 'add') {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nama_lengkap = $_POST['nama_lengkap'] ?? '';
        $npm = $_POST['npm'] ?? '';
        $role = $_POST['role'] ?? 'mahasiswa';
        
        if (empty($username) || empty($password) || empty($nama_lengkap)) {
            $error = 'Username, password, dan nama lengkap harus diisi';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Cek apakah username sudah ada
                $query = "SELECT COUNT(*) as count FROM pengguna WHERE username = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$username]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Username sudah digunakan';
                } else {
                    // Hash password
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Tambah pengguna baru
                    $query = "INSERT INTO pengguna (username, password, nama_lengkap, npm, role) VALUES (?, ?, ?, ?, ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$username, $hashed_password, $nama_lengkap, $npm, $role]);
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Menambahkan pengguna baru', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Pengguna: $username ($role)"]);
                    
                    $message = 'Pengguna berhasil ditambahkan';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'edit') {
        $id_pengguna = $_POST['id_pengguna'] ?? '';
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nama_lengkap = $_POST['nama_lengkap'] ?? '';
        $npm = $_POST['npm'] ?? '';
        $role = $_POST['role'] ?? 'mahasiswa';
        
        if (empty($id_pengguna) || empty($username) || empty($nama_lengkap)) {
            $error = 'Username dan nama lengkap harus diisi';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Cek apakah username sudah ada (kecuali untuk pengguna yang sedang diedit)
                $query = "SELECT COUNT(*) as count FROM pengguna WHERE username = ? AND id != ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$username, $id_pengguna]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Username sudah digunakan';
                } else {
                    // Update pengguna
                    if (!empty($password)) {
                        // Hash password baru
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        
                        $query = "UPDATE pengguna SET username = ?, password = ?, nama_lengkap = ?, npm = ?, role = ? WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$username, $hashed_password, $nama_lengkap, $npm, $role, $id_pengguna]);
                    } else {
                        // Tidak mengubah password
                        $query = "UPDATE pengguna SET username = ?, nama_lengkap = ?, npm = ?, role = ? WHERE id = ?";
                        $stmt = $conn->prepare($query);
                        $stmt->execute([$username, $nama_lengkap, $npm, $role, $id_pengguna]);
                    }
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Mengupdate pengguna', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Pengguna: $username"]);
                    
                    $message = 'Pengguna berhasil diupdate';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    } elseif ($action == 'delete') {
        $id_pengguna = $_POST['id_pengguna'] ?? '';
        
        if (empty($id_pengguna)) {
            $error = 'ID pengguna tidak valid';
        } elseif ($id_pengguna == $user['id']) {
            $error = 'Anda tidak dapat menghapus akun Anda sendiri';
        } else {
            try {
                $conn = $database->getConnection();
                
                // Ambil data pengguna sebelum dihapus untuk log
                $query = "SELECT username, role FROM pengguna WHERE id = ?";
                $stmt = $conn->prepare($query);
                $stmt->execute([$id_pengguna]);
                $pengguna_info = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Cek apakah pengguna memiliki peminjaman aktif
                $query = "SELECT COUNT(*) as count FROM peminjaman WHERE id_peminjam = ? AND status IN ('menunggu', 'dipinjam')";
                $stmt = $conn->prepare($query);
                $stmt->execute([$id_pengguna]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result['count'] > 0) {
                    $error = 'Pengguna tidak dapat dihapus karena memiliki peminjaman aktif';
                } else {
                    // Hapus pengguna
                    $query = "DELETE FROM pengguna WHERE id = ?";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$id_pengguna]);
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Menghapus pengguna', ?)";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id'], "Pengguna: {$pengguna_info['username']} ({$pengguna_info['role']})"]);
                    
                    $message = 'Pengguna berhasil dihapus';
                }
            } catch (PDOException $e) {
                $error = 'Terjadi kesalahan: ' . $e->getMessage();
            }
        }
    }
}

// Filter berdasarkan role
$role_filter = isset($_GET['role']) ? $_GET['role'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

// Query untuk mengambil data pengguna
$conn = $database->getConnection();
$query = "SELECT p.*, 
          (SELECT COUNT(*) FROM peminjaman WHERE id_peminjam = p.id AND status IN ('menunggu', 'dipinjam')) as peminjaman_aktif
          FROM pengguna p
          WHERE 1=1";

// Tambahkan filter role jika ada
if (!empty($role_filter)) {
    $query .= " AND p.role = :role";
}

// Tambahkan filter pencarian jika ada
if (!empty($search)) {
    $query .= " AND (p.username LIKE :search OR p.nama_lengkap LIKE :search OR p.npm LIKE :search)";
}

$query .= " ORDER BY p.nama_lengkap ASC";

$stmt = $conn->prepare($query);

// Bind parameter jika ada filter
if (!empty($role_filter)) {
    $stmt->bindParam(':role', $role_filter);
}

if (!empty($search)) {
    $search_param = "%$search%";
    $stmt->bindParam(':search', $search_param);
}

$stmt->execute();
$pengguna_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Kelola Pengguna</title>
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
                            <a class="nav-link active" href="pengguna.php">
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
                    <h1 class="h2">Kelola Pengguna</h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                        <i class="bi bi-plus-lg"></i> Tambah Pengguna Baru
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
                            <div class="col-md-4">
                                <label for="role" class="form-label">Filter Role</label>
                                <select name="role" id="role" class="form-select">
                                    <option value="">Semua Role</option>
                                    <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                    <option value="mahasiswa" <?php echo $role_filter == 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label for="search" class="form-label">Cari</label>
                                <input type="text" class="form-control" id="search" name="search" placeholder="Username, nama, atau NPM" value="<?php echo $search; ?>">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Filter</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Daftar Pengguna -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Pengguna</h5>
                    </div>
                    <div class="card-body">
                        <?php if (count($pengguna_list) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Username</th>
                                            <th>Nama Lengkap</th>
                                            <th>NPM</th>
                                            <th>Role</th>
                                            <th>Peminjaman Aktif</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($pengguna_list as $pengguna): ?>
                                            <tr>
                                                <td><?php echo $pengguna['username']; ?></td>
                                                <td><?php echo $pengguna['nama_lengkap']; ?></td>
                                                <td><?php echo $pengguna['npm'] ?? '-'; ?></td>
                                                <td>
                                                    <?php if ($pengguna['role'] == 'admin'): ?>
                                                        <span class="badge bg-danger">Admin</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-primary">Mahasiswa</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($pengguna['peminjaman_aktif'] > 0): ?>
                                                        <span class="badge bg-warning text-dark"><?php echo $pengguna['peminjaman_aktif']; ?></span>
                                                    <?php else: ?>
                                                        <span class="badge bg-success">0</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group">
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $pengguna['id']; ?>">
                                                            <i class="bi bi-pencil"></i> Edit
                                                        </button>
                                                        <?php if ($pengguna['id'] != $user['id']): ?>
                                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal<?php echo $pengguna['id']; ?>">
                                                                <i class="bi bi-trash"></i> Hapus
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                    
                                                    <!-- Modal Edit Pengguna -->
                                                    <div class="modal fade" id="editUserModal<?php echo $pengguna['id']; ?>" tabindex="-1" aria-labelledby="editUserModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="editUserModalLabel">Edit Pengguna</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form action="" method="POST">
                                                                    <div class="modal-body">
                                                                        <div class="mb-3">
                                                                            <label for="username" class="form-label">Username</label>
                                                                            <input type="text" class="form-control" id="username" name="username" value="<?php echo $pengguna['username']; ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="password" class="form-label">Password (Kosongkan jika tidak ingin mengubah)</label>
                                                                            <input type="password" class="form-control" id="password" name="password">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                                                                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" value="<?php echo $pengguna['nama_lengkap']; ?>" required>
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="npm" class="form-label">NPM (Untuk Mahasiswa)</label>
                                                                            <input type="text" class="form-control" id="npm" name="npm" value="<?php echo $pengguna['npm']; ?>">
                                                                        </div>
                                                                        <div class="mb-3">
                                                                            <label for="role" class="form-label">Role</label>
                                                                            <select class="form-select" id="role" name="role" required>
                                                                                <option value="mahasiswa" <?php echo $pengguna['role'] == 'mahasiswa' ? 'selected' : ''; ?>>Mahasiswa</option>
                                                                                <option value="admin" <?php echo $pengguna['role'] == 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                                            </select>
                                                                        </div>
                                                                        <input type="hidden" name="id_pengguna" value="<?php echo $pengguna['id']; ?>">
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
                                                    
                                                    <!-- Modal Hapus Pengguna -->
                                                    <?php if ($pengguna['id'] != $user['id']): ?>
                                                        <div class="modal fade" id="deleteUserModal<?php echo $pengguna['id']; ?>" tabindex="-1" aria-labelledby="deleteUserModalLabel" aria-hidden="true">
                                                            <div class="modal-dialog">
                                                                <div class="modal-content">
                                                                    <div class="modal-header">
                                                                        <h5 class="modal-title" id="deleteUserModalLabel">Konfirmasi Hapus</h5>
                                                                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                    </div>
                                                                    <div class="modal-body">
                                                                        <p>Apakah Anda yakin ingin menghapus pengguna ini?</p>
                                                                        <p><strong>Username:</strong> <?php echo $pengguna['username']; ?></p>
                                                                        <p><strong>Nama:</strong> <?php echo $pengguna['nama_lengkap']; ?></p>
                                                                        <p><strong>Role:</strong> <?php echo $pengguna['role']; ?></p>
                                                                        
                                                                        <?php if ($pengguna['peminjaman_aktif'] > 0): ?>
                                                                            <div class="alert alert-warning">
                                                                                <i class="bi bi-exclamation-triangle"></i> Pengguna ini memiliki <?php echo $pengguna['peminjaman_aktif']; ?> peminjaman aktif. Anda harus menyelesaikan peminjaman tersebut terlebih dahulu.
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <form action="" method="POST">
                                                                            <input type="hidden" name="id_pengguna" value="<?php echo $pengguna['id']; ?>">
                                                                            <input type="hidden" name="action" value="delete">
                                                                            <button type="submit" class="btn btn-danger" <?php echo $pengguna['peminjaman_aktif'] > 0 ? 'disabled' : ''; ?>>Hapus</button>
                                                                        </form>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php else: ?>
                            <p class="text-center text-muted">Tidak ada data pengguna</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Tambah Pengguna -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Tambah Pengguna Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="" method="POST">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                            <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                        </div>
                        <div class="mb-3">
                            <label for="npm" class="form-label">NPM (Untuk Mahasiswa)</label>
                            <input type="text" class="form-control" id="npm" name="npm">
                        </div>
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role" required>
                                <option value="mahasiswa" selected>Mahasiswa</option>
                                <option value="admin">Admin</option>
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
