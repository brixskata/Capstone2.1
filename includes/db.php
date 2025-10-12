<?php
$host = '127.0.0.1'; // Database host
$db = 'ordering_system'; // Database name
$user = 'root'; // Database username
$pass = ''; // Database password


try {
    $pdo = new PDO("mysql:host=$host;dbname=$db", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
