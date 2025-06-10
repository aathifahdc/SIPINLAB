<?php
require_once 'db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendResponse(false, 'Method not allowed');
}

$user = checkAuth();
$input = json_decode(file_get_contents('php://input'), true);

// Validasi input
if (!isset($input['id_peminjaman']) || !isset($input['kondisi_alat'])) {
    sendResponse(false, 'ID peminjaman dan kondisi alat harus diisi');
}

$database = new Database();
$db = $database->getConnection();

try {
    // Verifikasi peminjaman milik user yang login
    $verify_query = "SELECT p.*, a.nama_alat 
                    FROM peminjaman p 
                    JOIN alat a ON p.id_alat = a.id_alat 
                    WHERE p.id_peminjaman = ? AND p.npm = ? AND p.status = 'dipinjam'";
    
    $verify_stmt = $db->prepare($verify_query);
    $verify_stmt->execute([$input['id_peminjaman'], $user['npm']]);
    $peminjaman = $verify_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$peminjaman) {
        sendResponse(false, 'Peminjaman tidak ditemukan atau sudah dikembalikan');
    }
    
    // Panggil stored procedure kembalikan_alat
    $sql = "CALL kembalikan_alat(?, ?, @result)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $input['id_peminjaman'],
        $input['kondisi_alat']
    ]);
    
    // Ambil hasil procedure
    $result_stmt = $db->query("SELECT @result as result");
    $result = $result_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (strpos($result['result'], 'SUCCESS') === 0) {
        // Get detail pengembalian
        $detail_query = "SELECT p.*, a.nama_alat, u.nama as nama_peminjam 
                        FROM peminjaman p 
                        JOIN alat a ON p.id_alat = a.id_alat 
                        JOIN pengguna u ON p.npm = u.npm 
                        WHERE p.id_peminjaman = ?";
        
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->execute([$input['id_peminjaman']]);
        $detail = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, $result['result'], $detail);
    } else {
        sendResponse(false, $result['result']);
    }
    
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>
