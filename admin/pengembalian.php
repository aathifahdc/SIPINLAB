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

// Proses pengembalian alat
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $id_peminjaman = $_POST['id_peminjaman'] ?? 0;
    $kondisi = $_POST['kondisi'] ?? 'baik';
    
    if ($id_peminjaman) {
        try {
            // Jalankan stored procedure kembalikan_alat
            $result = $database->executeStoredProcedure('kembalikan_alat', [
                $id_peminjaman,
                $user['id'],
                $kondisi
            ]);
            
            if (isset($result['error'])) {
                $error = $result['error'];
            } else {
                $message = 'Pengembalian alat berhasil diproses';
            }
        } catch (Exception $e) {
            $error = $e->getMessage();
        }
    }
}

// Ambil data peminjaman yang sedang dipinjam
$conn = $database->getConnection();
$query = "SELECT p.*, u.nama_lengkap as nama_peminjam, u.npm, a.nama as nama_alat, a.kode as kode_alat, 
          l.nama as nama_lab, dp.jumlah, dp.id as id_detail
          FROM peminjaman p
          JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
          JOIN alat a ON dp.id_alat = a.id
          JOIN laboratorium l ON a.id_laboratorium = l.id
          JOIN pengguna u ON p.id_peminjam = u.id
          WHERE p.status = 'dipinjam'
          ORDER BY p.tanggal_pinjam ASC";
$stmt = $conn->prepare($query);
$stmt->execute();
$peminjaman_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Pengembalian Alat</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
<style>
    :root {
        /* Enhanced Professional Black & Gold Color Palette */
        --black-900: #0D0D0D;       /* Deepest black */
        --black-800: #1A1A1A;       /* Primary black */
        --black-700: #262626;       /* Secondary black */
        --black-600: #333333;       /* Lighter black */
        --gold-600: #E6B800;       /* Dark gold */
        --gold-500: #FFC107;       /* Primary gold */
        --gold-400: #FFD700;       /* Bright gold */
        --gold-300: #FFE44D;       /* Light gold */
        --white: #FFFFFF;
        --gray-100: #F5F5F5;
        --gray-200: #EEEEEE;
    }

    /* Sidebar - Premium Black Theme */
    .sidebar {
        min-height: 100vh;
        background-color: var(--black-800);
        color: var(--white);
        border-right: 1px solid rgba(255, 193, 7, 0.15);
        padding: 1.5rem 0;
        box-shadow: 2px 0 10px rgba(0, 0, 0, 0.1);
    }

    .sidebar .nav-link {
        color: rgba(255, 255, 255, 0.8);
        padding: 0.8rem 1.75rem;
        margin: 0.3rem 1.25rem;
        border-radius: 8px;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        display: flex;
        align-items: center;
        gap: 1rem;
        font-size: 0.95rem;
        position: relative;
        overflow: hidden;
    }

    .sidebar .nav-link:hover {
        color: var(--gold-400);
        background-color: var(--black-700);
        transform: translateX(6px);
    }

    .sidebar .nav-link.active {
        color: var(--black-900);
        background-color: var(--gold-500);
        font-weight: 600;
        box-shadow: 0 4px 14px rgba(255, 193, 7, 0.3);
        transform: translateX(0);
    }

    .sidebar .nav-link.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 0;
        height: 100%;
        width: 3px;
        background-color: var(--gold-600);
    }

    .sidebar .nav-link i {
        width: 22px;
        text-align: center;
        font-size: 1.15rem;
        transition: transform 0.3s ease;
    }

    .sidebar .nav-link:hover i {
        transform: scale(1.1);
    }

    /* Main Content Area */
    .main-content {
        padding: 2.5rem;
        background-color: var(--gray-100);
        min-height: 100vh;
    }

    /* Premium Dashboard Cards */
    .card-dashboard {
        margin-bottom: 2rem;
        border-radius: 12px;
        border: none;
        box-shadow: 0 4px 18px rgba(0, 0, 0, 0.06);
        transition: all 0.4s cubic-bezier(0.25, 0.8, 0.25, 1);
        overflow: hidden;
        background-color: var(--white);
        border-left: 4px solid var(--gold-500);
    }

    .card-dashboard:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
    }

    .card-dashboard .card-header {
        background-color: var(--white);
        border-bottom: 1px solid var(--gray-200);
        padding: 1.25rem 2rem;
        font-weight: 600;
        color: var(--black-800);
        display: flex;
        align-items: center;
        justify-content: space-between;
    }

    .card-dashboard .card-header .card-title {
        font-size: 1.1rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .card-dashboard .card-body {
        padding: 2rem;
    }

    /* Enhanced UI Components */
    .navbar {
        background-color: var(--black-900) !important;
        border-bottom: 1px solid rgba(255, 193, 7, 0.2);
        padding: 0.8rem 2rem;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
    }

    .navbar-brand {
        color: var(--gold-500) !important;
        font-weight: 700;
        font-size: 1.4rem;
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .navbar-brand i {
        color: var(--gold-400);
    }

    .btn-gold {
        background-color: var(--gold-500);
        color: var(--black-900);
        font-weight: 600;
        border: none;
        padding: 0.6rem 1.5rem;
        border-radius: 8px;
        transition: all 0.3s ease;
        box-shadow: 0 2px 8px rgba(255, 193, 7, 0.3);
    }

    .btn-gold:hover {
        background-color: var(--gold-400);
        color: var(--black-900);
        transform: translateY(-2px);
        box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
    }

    .btn-gold-outline {
        background-color: transparent;
        color: var(--gold-500);
        border: 2px solid var(--gold-500);
        font-weight: 600;
    }

    .btn-gold-outline:hover {
        background-color: var(--gold-500);
        color: var(--black-900);
    }

    /* Enhanced Utility Classes */
    .badge-gold {
        background-color: var(--gold-500);
        color: var(--black-900);
        font-weight: 600;
        padding: 0.4rem 0.9rem;
        border-radius: 50px;
        font-size: 0.85rem;
        letter-spacing: 0.5px;
        box-shadow: 0 2px 5px rgba(255, 193, 7, 0.2);
    }

    .text-gold {
        color: var(--gold-500);
    }

    .text-gold-light {
        color: var(--gold-400);
    }

    .bg-black {
        background-color: var(--black-800);
    }

    /* Table Styling */
    .table-premium {
        background-color: var(--white);
        border-radius: 10px;
        overflow: hidden;
    }

    .table-premium thead {
        background-color: var(--black-800);
        color: var(--gold-400);
    }

    .table-premium th {
        padding: 1rem 1.5rem;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 0.8rem;
        letter-spacing: 0.5px;
    }

    .table-premium td {
        padding: 1rem 1.5rem;
        border-top: 1px solid var(--gray-200);
        vertical-align: middle;
    }

    .table-premium tr:hover td {
        background-color: rgba(255, 193, 7, 0.05);
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
                            <a class="nav-link active" href="pengembalian.php">
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
                    <h1 class="h2">Pengembalian Alat</h1>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-success"><?php echo $message; ?></div>
                <?php endif; ?>
                
                <?php if (!empty($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                
                <!-- Daftar Peminjaman yang Sedang Dipinjam -->
                <div class="card card-dashboard">
                    <div class="card-header bg-white">
                        <h5 class="mb-0">Daftar Peminjaman yang Sedang Dipinjam</h5>
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
                                            <th>Durasi</th>
                                            <th>Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($peminjaman_list as $pinjam): ?>
                                            <?php 
                                                // Hitung durasi peminjaman
                                                $tanggal_pinjam = new DateTime($pinjam['tanggal_pinjam']);
                                                $tanggal_sekarang = new DateTime();
                                                $durasi = $tanggal_pinjam->diff($tanggal_sekarang);
                                                $durasi_text = '';
                                                
                                                if ($durasi->days > 0) {
                                                    $durasi_text .= $durasi->days . ' hari ';
                                                }
                                                
                                                $durasi_text .= $durasi->h . ' jam';
                                            ?>
                                            <tr>
                                                <td><?php echo $pinjam['id']; ?></td>
                                                <td><?php echo $pinjam['nama_peminjam']; ?></td>
                                                <td><?php echo $pinjam['npm']; ?></td>
                                                <td><?php echo $pinjam['kode_alat'] . ' - ' . $pinjam['nama_alat']; ?></td>
                                                <td><?php echo $pinjam['nama_lab']; ?></td>
                                                <td><?php echo $pinjam['jumlah']; ?></td>
                                                <td><?php echo date('d/m/Y H:i', strtotime($pinjam['tanggal_pinjam'])); ?></td>
                                                <td><?php echo $durasi_text; ?></td>
                                                <td>
                                                    <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#returnModal<?php echo $pinjam['id']; ?>">
                                                        <i class="bi bi-box-arrow-in-left"></i> Proses Pengembalian
                                                    </button>
                                                    
                                                    <!-- Modal Pengembalian -->
                                                    <div class="modal fade" id="returnModal<?php echo $pinjam['id']; ?>" tabindex="-1" aria-labelledby="returnModalLabel" aria-hidden="true">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header">
                                                                    <h5 class="modal-title" id="returnModalLabel">Proses Pengembalian Alat</h5>
                                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                                </div>
                                                                <form action="" method="POST">
                                                                    <div class="modal-body">
                                                                        <p><strong>Peminjam:</strong> <?php echo $pinjam['nama_peminjam']; ?></p>
                                                                        <p><strong>Alat:</strong> <?php echo $pinjam['nama_alat']; ?></p>
                                                                        <p><strong>Jumlah:</strong> <?php echo $pinjam['jumlah']; ?></p>
                                                                        <p><strong>Tanggal Pinjam:</strong> <?php echo date('d/m/Y H:i', strtotime($pinjam['tanggal_pinjam'])); ?></p>
                                                                        <p><strong>Durasi Peminjaman:</strong> <?php echo $durasi_text; ?></p>
                                                                        
                                                                        <div class="mb-3">
                                                                            <label for="kondisi" class="form-label">Kondisi Alat Saat Dikembalikan</label>
                                                                            <select name="kondisi" id="kondisi" class="form-select" required>
                                                                                <option value="baik">Baik</option>
                                                                                <option value="rusak_ringan">Rusak Ringan</option>
                                                                                <option value="rusak_berat">Rusak Berat</option>
                                                                            </select>
                                                                        </div>
                                                                        
                                                                        <input type="hidden" name="id_peminjaman" value="<?php echo $pinjam['id']; ?>">
                                                                    </div>
                                                                    <div class="modal-footer">
                                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                                                                        <button type="submit" class="btn btn-success">Proses Pengembalian</button>
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
                            <p class="text-center text-muted">Tidak ada peminjaman yang sedang aktif</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
