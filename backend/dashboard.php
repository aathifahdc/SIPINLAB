<?php
require_once 'db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user = checkAuth();

$database = new Database();
$db = $database->getConnection();

try {
    // Get statistics
    $stats = [];
    
    // Total alat
    $query = "SELECT COUNT(*) as total FROM alat";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['total_alat'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Alat tersedia
    $query = "SELECT COUNT(*) as total FROM alat WHERE status = 'tersedia' AND kondisi = 'baik'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['alat_tersedia'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Alat dipinjam
    $query = "SELECT COUNT(*) as total FROM alat WHERE status = 'dipinjam'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['alat_dipinjam'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    // Alat rusak
    $query = "SELECT COUNT(*) as total FROM alat WHERE kondisi = 'rusak'";
    $stmt = $db->prepare($query);
    $stmt->execute();
    $stats['alat_rusak'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    
    sendResponse(true, 'Dashboard data loaded successfully', $stats);
    
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>
