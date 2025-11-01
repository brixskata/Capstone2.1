<?php
// Set PHP timezone to Asia/Manila (Philippines)
date_default_timezone_set('Asia/Manila');

$host = '127.0.0.1'; 
$db = 'ordering_system'; 
$user = 'root'; 
$pass = ''; 


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    
    $pdo->exec("SET time_zone = '+08:00'");
} catch (PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

if (!function_exists('log_history')) {
    function log_history($pdo, $action, $details, $performed_by) {
        $stmt = $pdo->prepare("INSERT INTO history_logs (action, details, performed_by) VALUES (?, ?, ?)");
        $stmt->execute([$action, $details, $performed_by]);
    }
}
?>
