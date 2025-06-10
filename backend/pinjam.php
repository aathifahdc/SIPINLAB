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
$required_fields = ['id_alat', 'tanggal_kembali_rencana', 'keperluan'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        sendResponse(false, "Field $field harus diisi");
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    // Cek ketersediaan alat menggunakan function
    $ketersediaan = $database->executeFunction('cek_ketersediaan_alat', [$input['id_alat']]);
    
    if ($ketersediaan !== 'tersedia') {
        sendResponse(false, "Alat $ketersediaan, tidak dapat dipinjam");
    }
    
    // Cek status peminjam menggunakan function
    $status_peminjam = $database->executeFunction('cek_status_peminjam', [$user['npm']]);
    
    if ($status_peminjam !== 'boleh_pinjam') {
        sendResponse(false, "Status peminjam: $status_peminjam");
    }
    
    // Panggil stored procedure pinjam_alat
    $sql = "CALL pinjam_alat(?, ?, ?, ?, @result)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $user['npm'],
        $input['id_alat'],
        $input['tanggal_kembali_rencana'],
        $input['keperluan']
    ]);
    
    // Ambil hasil procedure
    $result_stmt = $db->query("SELECT @result as result");
    $result = $result_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (strpos($result['result'], 'SUCCESS') === 0) {
        // Get detail peminjaman yang baru dibuat
        $detail_query = "SELECT p.*, a.nama_alat, u.nama as nama_peminjam 
                        FROM peminjaman p 
                        JOIN alat a ON p.id_alat = a.id_alat 
                        JOIN pengguna u ON p.npm = u.npm 
                        WHERE p.npm = ? AND p.id_alat = ? 
                        ORDER BY p.created_at DESC LIMIT 1";
        
        $detail_stmt = $db->prepare($detail_query);
        $detail_stmt->execute([$user['npm'], $input['id_alat']]);
        $detail = $detail_stmt->fetch(PDO::FETCH_ASSOC);
        
        sendResponse(true, $result['result'], $detail);
    } else {
        sendResponse(false, $result['result']);
    }
    
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>
