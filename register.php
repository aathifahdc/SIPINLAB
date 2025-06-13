<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Inisialisasi database dan auth
$database = new Database();
$auth = new Auth($database);

// Cek apakah user sudah login
if ($auth->isLoggedIn()) {
    // Redirect berdasarkan role
    if ($auth->isAdmin()) {
        header("Location: admin/dashboard.php");
        exit;
    } else {
        header("Location: mahasiswa/dashboard.php");
        exit;
    }
}

// Proses registrasi
$error = '';
$success = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $nama_lengkap = $_POST['nama_lengkap'] ?? '';
    $npm = $_POST['npm'] ?? '';
    
    if (empty($username) || empty($password) || empty($confirm_password) || empty($nama_lengkap) || empty($npm)) {
        $error = 'Semua field harus diisi';
    } elseif ($password != $confirm_password) {
        $error = 'Password dan konfirmasi password tidak sama';
    } else {
        $result = $auth->register($username, $password, $nama_lengkap, $npm);
        
        if (isset($result['error'])) {
            $error = $result['error'];
        } else {
            $success = 'Registrasi berhasil, silahkan login';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Register</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .register-container {
            max-width: 500px;
            margin: 50px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
        }
        .logo {
            text-align: center;
            margin-bottom: 20px;
        }
        .logo h1 {
            color: #0d6efd;
            font-weight: bold;
        }
        .logo p {
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="register-container">
            <div class="logo">
                <h1>SIPINLAB</h1>
                <p>Sistem Peminjaman Alat Laboratorium</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-3">
                    <label for="username" class="form-label">Username</label>
                    <input type="text" class="form-control" id="username" name="username" required>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <input type="password" class="form-control" id="password" name="password" required>
                </div>
                <div class="mb-3">
                    <label for="confirm_password" class="form-label">Konfirmasi Password</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required>
                </div>
                <div class="mb-3">
                    <label for="nama_lengkap" class="form-label">Nama Lengkap</label>
                    <input type="text" class="form-control" id="nama_lengkap" name="nama_lengkap" required>
                </div>
                <div class="mb-3">
                    <label for="npm" class="form-label">NPM</label>
                    <input type="text" class="form-control" id="npm" name="npm" required>
                </div>
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Daftar</button>
                </div>
            </form>
            
            <div class="mt-3 text-center">
                <p>Sudah punya akun? <a href="index.php">Login</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
