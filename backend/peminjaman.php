<?php
require_once 'db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user = checkAuth();

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT p.*, a.nama_alat, u.nama as nama_peminjam 
              FROM peminjaman p 
              JOIN alat a ON p.id_alat = a.id_alat 
              JOIN pengguna u ON p.npm = u.npm 
              WHERE p.npm = ? 
              ORDER BY p.created_at DESC";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$user['npm']]);
    $peminjaman = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, 'Peminjaman data loaded successfully', $peminjaman);
    
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>
