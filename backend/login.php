<?php
require_once 'db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed');
}

$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['npm']) || !isset($input['password'])) {
    sendResponse(false, 'NPM dan password harus diisi');
}

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT npm, nama, email, role, fakultas, jurusan, status 
              FROM pengguna 
              WHERE npm = ? AND password = MD5(?) AND status = 'aktif'";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$input['npm'], $input['password']]);
    
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user) {
        session_start();
        $_SESSION['npm'] = $user['npm'];
        $_SESSION['nama'] = $user['nama'];
        $_SESSION['role'] = $user['role'];
        
        // Log login activity
        $log_query = "INSERT INTO log_aktivitas (npm, aktivitas, timestamp) VALUES (?, 'login', NOW())";
        $log_stmt = $db->prepare($log_query);
        $log_stmt->execute([$user['npm']]);
        
        sendResponse(true, 'Login berhasil', $user);
    } else {
        sendResponse(false, 'NPM atau password salah');
    }
    
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>
