<?php
require_once 'config/database.php';
require_once 'config/auth.php';

// Inisialisasi database dan auth
$database = new Database();
$auth = new Auth($database);

// Proses logout
$auth->logout();

// Redirect ke halaman login
header("Location: index.php");
exit;
?>
