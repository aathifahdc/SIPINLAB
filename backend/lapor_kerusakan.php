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
$required_fields = ['id_alat', 'deskripsi_kerusakan', 'tingkat_kerusakan'];
foreach ($required_fields as $field) {
    if (!isset($input[$field]) || empty($input[$field])) {
        sendResponse(false, "Field $field harus diisi");
    }
}

$database = new Database();
$db = $database->getConnection();

try {
    // Cari peminjaman terakhir user untuk alat ini
    $peminjaman_query = "SELECT id_peminjaman FROM peminjaman 
                        WHERE npm = ? AND id_alat = ? 
                        ORDER BY created_at DESC LIMIT 1";
    
    $peminjaman_stmt = $db->prepare($peminjaman_query);
    $peminjaman_stmt->execute([$user['npm'], $input['id_alat']]);
    $peminjaman = $peminjaman_stmt->fetch(PDO::FETCH_ASSOC);
    
    $id_peminjaman = $peminjaman ? $peminjaman['id_peminjaman'] : null;
    
    // Panggil stored procedure lapor_kerusakan
    $sql = "CALL lapor_kerusakan(?, ?, ?, ?, ?, @result)";
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $input['id_alat'],
        $user['npm'],
        $id_peminjaman,
        $input['deskripsi_kerusakan'],
        $input['tingkat_kerusakan']
    ]);
    
    // Ambil hasil procedure
    $result_stmt = $db->query("SELECT @result as result");
    $result = $result_stmt->fetch(PDO::FETCH_ASSOC);
    
    if (strpos($result['result'], 'SUCCESS') === 0) {
        // Get detail laporan yang baru dibuat
        $detail_query = "SELECT lk.*, a.nama_alat, u.nama as nama_pelapor 
                        FROM laporan_kerusakan lk 
                        JOIN alat a ON lk.id_alat = a.id_alat 
                        JOIN pengguna u ON lk.npm = u.npm 
                        WHERE lk.npm = ? AND lk.id_alat = ? 
                        ORDER BY lk.tanggal_lapor DESC LIMIT 1";
        
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
