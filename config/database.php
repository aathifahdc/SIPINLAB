<?php
class Database {
    private $host = "localhost";
    private $db_name = "SIPINLAB";
    private $username = "root";
    private $password = "";
    private $conn;

    // Koneksi database
    public function getConnection() {
        $this->conn = null;

        try {
            $this->conn = new PDO("mysql:host=" . $this->host . ";dbname=" . $this->db_name, $this->username, $this->password);
            $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->conn->exec("set names utf8");
        } catch(PDOException $exception) {
            echo "Koneksi error: " . $exception->getMessage();
        }

        return $this->conn;
    }

    // Fungsi untuk menjalankan stored procedure
    public function executeStoredProcedure($procedure, $params = []) {
        try {
            $conn = $this->getConnection();
            
            // Buat parameter placeholders
            $placeholders = implode(',', array_fill(0, count($params), '?'));
            
            // Buat query
            $query = "CALL {$procedure}({$placeholders})";
            
            // Prepare statement
            $stmt = $conn->prepare($query);
            
            // Bind parameters
            $i = 1;
            foreach ($params as $param) {
                $stmt->bindValue($i++, $param);
            }
            
            // Execute
            $stmt->execute();
            
            return true;
        } catch(PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // Fungsi untuk menjalankan function database
    public function executeFunction($function, $params = []) {
        try {
            $conn = $this->getConnection();
            
            // Buat parameter placeholders
            $placeholders = implode(',', array_fill(0, count($params), '?'));
            
            // Buat query
            $query = "SELECT {$function}({$placeholders}) AS result";
            
            // Prepare statement
            $stmt = $conn->prepare($query);
            
            // Bind parameters
            $i = 1;
            foreach ($params as $param) {
                $stmt->bindValue($i++, $param);
            }
            
            // Execute
            $stmt->execute();
            
            // Ambil hasil
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            
            return $row['result'];
        } catch(PDOException $e) {
            return ['error' => $e->getMessage()];
        }
    }

    // Fungsi untuk backup database
    public function backupDatabase($backup_dir) {
        $date = date("Y-m-d-H-i-s");
        $backup_file = $backup_dir . "backup-" . $date . ".sql";
        
        // Pastikan direktori backup ada
        if (!is_dir($backup_dir)) {
            mkdir($backup_dir, 0777, true);
        }
        
        // Command untuk backup database
        $command = "mysqldump --user={$this->username} --password={$this->password} --host={$this->host} {$this->db_name} > {$backup_file}";
        
        // Jalankan command
        exec($command, $output, $return_var);
        
        // Cek status backup
        if ($return_var === 0) {
            // Backup berhasil, catat ke log
            $file_size = filesize($backup_file);
            $conn = $this->getConnection();
            $query = "INSERT INTO backup_log (nama_file, ukuran_file, lokasi_penyimpanan, status) 
                      VALUES (?, ?, ?, 'sukses')";
            $stmt = $conn->prepare($query);
            $stmt->execute(["backup-" . $date . ".sql", $file_size, $backup_dir]);
            
            return true;
        } else {
            // Backup gagal, catat ke log
            $conn = $this->getConnection();
            $query = "INSERT INTO backup_log (nama_file, ukuran_file, lokasi_penyimpanan, status) 
                      VALUES (?, 0, ?, 'gagal')";
            $stmt = $conn->prepare($query);
            $stmt->execute(["backup-" . $date . ".sql", $backup_dir]);
            
            return false;
        }
    }
}
?>
