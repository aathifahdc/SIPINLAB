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

// Proses login
$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    
    if (empty($username) || empty($password)) {
        $error = 'Username dan password harus diisi';
    } else {
        if ($auth->login($username, $password)) {
            // Redirect berdasarkan role
            if ($auth->isAdmin()) {
                header("Location: admin/dashboard.php");
                exit;
            } else {
                header("Location: mahasiswa/dashboard.php");
                exit;
            }
        } else {
            $error = 'Username atau password salah';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SIPINLAB - Login</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .login-container {
            max-width: 400px;
            margin: 100px auto;
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
        <div class="login-container">
            <div class="logo">
                <h1>SIPINLAB</h1>
                <p>Sistem Peminjaman Alat Laboratorium</p>
            </div>
            
            <?php if (!empty($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
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
                <div class="d-grid gap-2">
                    <button type="submit" class="btn btn-primary">Login</button>
                </div>
            </form>
            
            <div class="mt-3 text-center">
                <p>Belum punya akun? <a href="register.php">Daftar</a></p>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
