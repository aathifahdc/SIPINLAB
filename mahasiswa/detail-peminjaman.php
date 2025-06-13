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

// Cek apakah ada parameter id
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: riwayat.php");
    exit;
}

$id_peminjaman = (int)$_GET['id'];

// Handle pesan dari URL parameter
$message = '';
$message_type = '';

if (isset($_GET['success'])) {
    switch ($_GET['success']) {
        case 'cancel_success':
            $message = 'Peminjaman berhasil dibatalkan';
            $message_type = 'success';
            break;
    }
}

if (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'cancel_failed':
            $message = 'Gagal membatalkan peminjaman. Silakan coba lagi.';
            $message_type = 'danger';
            break;
        case 'database_error':
            $message = 'Terjadi kesalahan database. Silakan hubungi administrator.';
            $message_type = 'danger';
            break;
        case 'cannot_cancel':
            $message = 'Peminjaman tidak dapat dibatalkan. Mungkin sudah diproses atau bukan milik Anda.';
            $message_type = 'warning';
            break;
        default:
            $message = 'Terjadi kesalahan. Silakan coba lagi.';
            $message_type = 'danger';
    }
}

try {
    // Ambil data peminjaman dengan detail
    $conn = $database->getConnection();
    $query = "SELECT p.*, 
                     u.nama_lengkap as nama_peminjam, u.npm,
                     a.nama as nama_alat, a.kode as kode_alat, a.deskripsi as deskripsi_alat,
                     l.nama as nama_lab, l.lokasi as lokasi_lab,
                     k.nama as kategori_alat,
                     dp.jumlah, dp.status_kembali,
                     admin.nama_lengkap as nama_admin
              FROM peminjaman p
              JOIN pengguna u ON p.id_peminjam = u.id
              JOIN detail_peminjaman dp ON p.id = dp.id_peminjaman
              JOIN alat a ON dp.id_alat = a.id
              JOIN laboratorium l ON a.id_laboratorium = l.id
              JOIN kategori_alat k ON a.id_kategori = k.id
              LEFT JOIN pengguna admin ON p.disetujui_oleh = admin.id
              WHERE p.id = ? AND p.id_peminjam = ?";
    
    $stmt = $conn->prepare($query);
    $stmt->execute([$id_peminjaman, $user['id']]);
    $peminjaman = $stmt->fetch(PDO::FETCH_ASSOC);

    // Jika data tidak ditemukan atau bukan milik user yang login
    if (!$peminjaman) {
        header("Location: riwayat.php?error=not_found");
        exit;
    }

    // Ambil data laporan kerusakan jika ada
    $query_kerusakan = "SELECT lk.*, a.nama as nama_alat 
                        FROM laporan_kerusakan lk
                        JOIN alat a ON lk.id_alat = a.id
                        JOIN detail_peminjaman dp ON a.id = dp.id_alat
                        WHERE dp.id_peminjaman = ? AND lk.id_pelapor = ?";
    $stmt_kerusakan = $conn->prepare($query_kerusakan);
    $stmt_kerusakan->execute([$id_peminjaman, $user['id']]);
    $laporan_kerusakan = $stmt_kerusakan->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    error_log("Error in detail-peminjaman.php: " . $e->getMessage());
    header("Location: riwayat.php?error=database_error");
    exit;
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Detail Peminjaman</title>
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
        .detail-item {
            margin-bottom: 15px;
        }
        .detail-label {
            font-weight: bold;
            margin-bottom: 5px;
            color: #6c757d;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline-item {
            position: relative;
            padding-bottom: 20px;
        }
        .timeline-item:before {
            content: "";
            position: absolute;
            left: -30px;
            top: 0;
            width: 2px;
            height: 100%;
            background-color: #dee2e6;
        }
        .timeline-item:last-child:before {
            height: 0;
        }
        .timeline-badge {
            position: absolute;
            left: -38px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #0d6efd;
            border: 3px solid #fff;
            box-shadow: 0 0 0 1px #dee2e6;
        }
        .timeline-date {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .status-badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
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
                            <a class="nav-link active" href="riwayat.php">
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
                    <h1 class="h2">Detail Peminjaman #<?php echo $peminjaman['id']; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="riwayat.php" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-arrow-left me-1"></i> Kembali
                        </a>
                        <button type="button" class="btn btn-sm btn-primary ms-2" onclick="window.print()">
                            <i class="bi bi-printer me-1"></i> Cetak
                        </button>
                    </div>
                </div>
                
                <?php if (!empty($message)): ?>
                    <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                        <?php echo $message; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Status Peminjaman -->
                <div class="card card-dashboard mb-4">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h4>Status Peminjaman: 
                                    <?php if ($peminjaman['status'] == 'menunggu'): ?>
                                        <span class="badge bg-warning status-badge">Menunggu Persetujuan</span>
                                    <?php elseif ($peminjaman['status'] == 'dipinjam'): ?>
                                        <span class="badge bg-primary status-badge">Sedang Dipinjam</span>
                                    <?php elseif ($peminjaman['status'] == 'dikembalikan'): ?>
                                        <span class="badge bg-success status-badge">Sudah Dikembalikan</span>
                                    <?php elseif ($peminjaman['status'] == 'dibatalkan'): ?>
                                        <span class="badge bg-danger status-badge">Dibatalkan</span>
                                    <?php elseif ($peminjaman['status'] == 'terlambat'): ?>
                                        <span class="badge bg-danger status-badge">Terlambat</span>
                                    <?php endif; ?>
                                </h4>
                                <p class="mb-0 text-muted">Tanggal Pengajuan: <?php echo date('d/m/Y H:i', strtotime($peminjaman['created_at'])); ?></p>
                            </div>
                            <div class="col-md-4 text-md-end">
                                <?php if ($peminjaman['status'] == 'menunggu'): ?>
                                    <button type="button" class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#cancelModal">
                                        <i class="bi bi-x-circle me-1"></i> Batalkan Peminjaman
                                    </button>
                                <?php elseif ($peminjaman['status'] == 'dipinjam'): ?>
                                    <a href="lapor-kerusakan.php" class="btn btn-warning">
                                        <i class="bi bi-exclamation-triangle me-1"></i> Lapor Kerusakan
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Detail Peminjaman -->
                <div class="row">
                    <div class="col-md-6">
                        <div class="card card-dashboard">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle me-2"></i>Informasi Peminjaman</h5>
                            </div>
                            <div class="card-body">
                                <div class="detail-item">
                                    <div class="detail-label">ID Peminjaman</div>
                                    <div><?php echo $peminjaman['id']; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Tanggal Pinjam</div>
                                    <div><?php echo date('d/m/Y H:i', strtotime($peminjaman['tanggal_pinjam'])); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Tanggal Kembali</div>
                                    <div>
                                        <?php echo $peminjaman['tanggal_kembali'] ? date('d/m/Y H:i', strtotime($peminjaman['tanggal_kembali'])) : '-'; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Jumlah Dipinjam</div>
                                    <div><?php echo $peminjaman['jumlah']; ?> unit</div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Status Pengembalian</div>
                                    <div>
                                        <?php if ($peminjaman['status_kembali'] == 'belum'): ?>
                                            <span class="badge bg-warning">Belum Dikembalikan</span>
                                        <?php elseif ($peminjaman['status_kembali'] == 'sudah'): ?>
                                            <span class="badge bg-success">Sudah Dikembalikan</span>
                                        <?php elseif ($peminjaman['status_kembali'] == 'rusak'): ?>
                                            <span class="badge bg-danger">Dikembalikan Rusak</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Keterangan</div>
                                    <div><?php echo $peminjaman['keterangan'] ? htmlspecialchars($peminjaman['keterangan']) : '-'; ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Disetujui Oleh</div>
                                    <div><?php echo $peminjaman['nama_admin'] ? htmlspecialchars($peminjaman['nama_admin']) : '-'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card card-dashboard">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-tools me-2"></i>Informasi Alat</h5>
                            </div>
                            <div class="card-body">
                                <div class="detail-item">
                                    <div class="detail-label">Kode Alat</div>
                                    <div><code><?php echo htmlspecialchars($peminjaman['kode_alat']); ?></code></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Nama Alat</div>
                                    <div><strong><?php echo htmlspecialchars($peminjaman['nama_alat']); ?></strong></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Kategori</div>
                                    <div><span class="badge bg-info"><?php echo htmlspecialchars($peminjaman['kategori_alat']); ?></span></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Laboratorium</div>
                                    <div><?php echo htmlspecialchars($peminjaman['nama_lab']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Lokasi Lab</div>
                                    <div><i class="bi bi-geo-alt me-1"></i><?php echo htmlspecialchars($peminjaman['lokasi_lab']); ?></div>
                                </div>
                                <div class="detail-item">
                                    <div class="detail-label">Deskripsi Alat</div>
                                    <div><?php echo $peminjaman['deskripsi_alat'] ? htmlspecialchars($peminjaman['deskripsi_alat']) : '-'; ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Laporan Kerusakan -->
                <?php if ($laporan_kerusakan): ?>
                    <div class="card card-dashboard mt-4">
                        <div class="card-header bg-warning text-dark">
                            <h5 class="mb-0"><i class="bi bi-exclamation-triangle me-2"></i>Laporan Kerusakan</h5>
                        </div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <div class="detail-label">Tanggal Laporan</div>
                                        <div><?php echo date('d/m/Y H:i', strtotime($laporan_kerusakan['tanggal_laporan'])); ?></div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Tingkat Kerusakan</div>
                                        <div>
                                            <?php if ($laporan_kerusakan['tingkat_kerusakan'] == 'ringan'): ?>
                                                <span class="badge bg-success">Ringan</span>
                                            <?php elseif ($laporan_kerusakan['tingkat_kerusakan'] == 'sedang'): ?>
                                                <span class="badge bg-warning">Sedang</span>
                                            <?php elseif ($laporan_kerusakan['tingkat_kerusakan'] == 'berat'): ?>
                                                <span class="badge bg-danger">Berat</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="detail-item">
                                        <div class="detail-label">Status Laporan</div>
                                        <div>
                                            <?php if ($laporan_kerusakan['status'] == 'menunggu'): ?>
                                                <span class="badge bg-warning">Menunggu</span>
                                            <?php elseif ($laporan_kerusakan['status'] == 'diproses'): ?>
                                                <span class="badge bg-primary">Diproses</span>
                                            <?php elseif ($laporan_kerusakan['status'] == 'selesai'): ?>
                                                <span class="badge bg-success">Selesai</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="detail-item">
                                        <div class="detail-label">Deskripsi Kerusakan</div>
                                        <div><?php echo htmlspecialchars($laporan_kerusakan['deskripsi_kerusakan']); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- Timeline Peminjaman -->
                <div class="card card-dashboard mt-4">
                    <div class="card-header bg-secondary text-white">
                        <h5 class="mb-0"><i class="bi bi-clock-history me-2"></i>Timeline Peminjaman</h5>
                    </div>
                    <div class="card-body">
                        <div class="timeline">
                            <div class="timeline-item">
                                <div class="timeline-badge"></div>
                                <div class="timeline-content">
                                    <h6>Permintaan Peminjaman Dibuat</h6>
                                    <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($peminjaman['created_at'])); ?></div>
                                    <small class="text-muted">Peminjaman diajukan oleh <?php echo htmlspecialchars($peminjaman['nama_peminjam']); ?></small>
                                </div>
                            </div>
                            
                            <?php if ($peminjaman['status'] == 'dipinjam' || $peminjaman['status'] == 'dikembalikan'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-success"></div>
                                    <div class="timeline-content">
                                        <h6>Peminjaman Disetujui</h6>
                                        <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($peminjaman['tanggal_pinjam'])); ?></div>
                                        <small class="text-muted">Disetujui oleh: <?php echo htmlspecialchars($peminjaman['nama_admin'] ?? 'Admin'); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($peminjaman['status'] == 'dibatalkan'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-danger"></div>
                                    <div class="timeline-content">
                                        <h6>Peminjaman Dibatalkan</h6>
                                        <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($peminjaman['updated_at'])); ?></div>
                                        <small class="text-muted">Peminjaman dibatalkan</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($laporan_kerusakan): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-warning"></div>
                                    <div class="timeline-content">
                                        <h6>Laporan Kerusakan Dibuat</h6>
                                        <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($laporan_kerusakan['tanggal_laporan'])); ?></div>
                                        <small class="text-muted">Tingkat kerusakan: <?php echo ucfirst($laporan_kerusakan['tingkat_kerusakan']); ?></small>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <?php if ($peminjaman['status'] == 'dikembalikan'): ?>
                                <div class="timeline-item">
                                    <div class="timeline-badge bg-success"></div>
                                    <div class="timeline-content">
                                        <h6>Alat Dikembalikan</h6>
                                        <div class="timeline-date"><?php echo date('d/m/Y H:i', strtotime($peminjaman['tanggal_kembali'])); ?></div>
                                        <small class="text-muted">Peminjaman selesai</small>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Modal Konfirmasi Pembatalan -->
    <?php if ($peminjaman['status'] == 'menunggu'): ?>
    <div class="modal fade" id="cancelModal" tabindex="-1" aria-labelledby="cancelModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="cancelModalLabel">Konfirmasi Pembatalan</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Apakah Anda yakin ingin membatalkan peminjaman ini?</p>
                    <div class="alert alert-warning">
                        <i class="bi bi-exclamation-triangle me-2"></i>
                        <strong>Perhatian:</strong> Peminjaman yang sudah dibatalkan tidak dapat dikembalikan.
                    </div>
                    <p><strong>Detail Peminjaman:</strong></p>
                    <ul>
                        <li>Alat: <?php echo htmlspecialchars($peminjaman['nama_alat']); ?></li>
                        <li>Jumlah: <?php echo $peminjaman['jumlah']; ?> unit</li>
                        <li>Tanggal Pinjam: <?php echo date('d/m/Y', strtotime($peminjaman['tanggal_pinjam'])); ?></li>
                    </ul>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <a href="batalkan-peminjaman.php?id=<?php echo $peminjaman['id']; ?>" class="btn btn-danger" onclick="return confirm('Apakah Anda benar-benar yakin?')">
                        Ya, Batalkan Peminjaman
                    </a>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
