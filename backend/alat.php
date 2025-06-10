<?php
require_once 'db.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

$user = checkAuth();

$database = new Database();
$db = $database->getConnection();

try {
    $query = "SELECT a.*, k.nama_kategori, l.nama_lab 
              FROM alat a 
              LEFT JOIN kategori_alat k ON a.id_kategori = k.id_kategori 
              LEFT JOIN laboratorium l ON a.id_lab = l.id_lab 
              ORDER BY a.nama_alat";
    
    $stmt = $db->prepare($query);
    $stmt->execute();
    $alat = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    sendResponse(true, 'Alat data loaded successfully', $alat);
    
} catch(Exception $e) {
    sendResponse(false, 'Error: ' . $e->getMessage());
}
?>
