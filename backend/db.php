<?php
class Database {
    private $host = 'localhost';
    private $db_name = 'SIPINLAB';
    private $username = 'root';
    private $password = '';
    private $conn;

    public function getConnection() {
        $this->conn = null;
        try {
            $this->conn = new PDO(
                "mysql:host=" . $this->host . ";dbname=" . $this->db_name,
                $this->username,
                $this->password
            );
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Connection error: " . $exception->getMessage();
        }
        return $this->conn;
    }

    public function executeStoredProcedure($procedure, $params = []) {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $sql = "CALL $procedure($placeholders)";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            throw new Exception("Stored procedure error: " . $e->getMessage());
        }
    }

    public function executeFunction($function, $params = []) {
        try {
            $placeholders = str_repeat('?,', count($params) - 1) . '?';
            $sql = "SELECT $function($placeholders) as result";
            $stmt = $this->conn->prepare($sql);
            $stmt->execute($params);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result['result'];
        } catch(PDOException $e) {
            throw new Exception("Function error: " . $e->getMessage());
        }
    }
}

// Response helper
function sendResponse($success, $message, $data = null) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Authentication helper
function checkAuth() {
    session_start();
    if (!isset($_SESSION['npm'])) {
        sendResponse(false, 'Unauthorized access');
    }
    return $_SESSION;
}
?>
