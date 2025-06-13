<?php
session_start();

class Auth {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    // Login user
    public function login($username, $password) {
        try {
            $conn = $this->db->getConnection();
            
            // Cek username
            $query = "SELECT * FROM pengguna WHERE username = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Debug info - hapus pada produksi
                error_log("Login attempt for: " . $username);
                error_log("Stored hash: " . $user['password']);
                error_log("Password verification result: " . (password_verify($password, $user['password']) ? 'true' : 'false'));
                
                // Verifikasi password
                if (password_verify($password, $user['password'])) {
                    // Set session
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
                    $_SESSION['role'] = $user['role'];
                    
                    // Catat log aktivitas
                    $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                              VALUES (?, 'Login', 'Login berhasil')";
                    $stmt = $conn->prepare($query);
                    $stmt->execute([$user['id']]);
                    
                    return true;
                } else {
                    error_log("Password verification failed for user: " . $username);
                    return false;
                }
            } else {
                error_log("User not found: " . $username);
                return false;
            }
        } catch(PDOException $e) {
            error_log("Login error: " . $e->getMessage());
            return false;
        }
    }
    
    // Logout user
    public function logout() {
        if (isset($_SESSION['user_id'])) {
            $conn = $this->db->getConnection();
            
            // Catat log aktivitas
            $query = "INSERT INTO log_aktivitas (id_pengguna, aktivitas, detail) 
                      VALUES (?, 'Logout', 'Logout berhasil')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            
            // Hapus semua session
            session_unset();
            session_destroy();
            
            return true;
        }
        
        return false;
    }
    
    // Cek apakah user sudah login
    public function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    // Cek role user
    public function isAdmin() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'admin';
    }
    
    // Cek role user
    public function isMahasiswa() {
        return isset($_SESSION['role']) && $_SESSION['role'] == 'mahasiswa';
    }
    
    // Ambil data user yang sedang login
    public function getUser() {
        if ($this->isLoggedIn()) {
            $conn = $this->db->getConnection();
            
            $query = "SELECT * FROM pengguna WHERE id = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$_SESSION['user_id']]);
            
            return $stmt->fetch(PDO::FETCH_ASSOC);
        }
        
        return null;
    }
    
    // Register user baru (khusus mahasiswa)
    public function register($username, $password, $nama_lengkap, $npm) {
        try {
            $conn = $this->db->getConnection();
            
            // Cek apakah username sudah ada
            $query = "SELECT * FROM pengguna WHERE username = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$username]);
            
            if ($stmt->rowCount() > 0) {
                return ['error' => 'Username sudah digunakan'];
            }
            
            // Cek apakah NPM sudah ada
            $query = "SELECT * FROM pengguna WHERE npm = ?";
            $stmt = $conn->prepare($query);
            $stmt->execute([$npm]);
            
            if ($stmt->rowCount() > 0) {
                return ['error' => 'NPM sudah terdaftar'];
            }
            
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Insert user baru
            $query = "INSERT INTO pengguna (username, password, nama_lengkap, npm, role) 
                      VALUES (?, ?, ?, ?, 'mahasiswa')";
            $stmt = $conn->prepare($query);
            $stmt->execute([$username, $hashed_password, $nama_lengkap, $npm]);
            
            return ['success' => 'Registrasi berhasil'];
        } catch(PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
?>
