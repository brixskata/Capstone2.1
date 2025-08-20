<?php
require_once '../includes/log_history.php';

function logHistory($pdo, $action, $details, $performed_by) {
    $stmt = $pdo->prepare("INSERT INTO history_logs (action, details, performed_by, performed_at) VALUES (?, ?, ?, NOW())");
    $stmt->execute([$action, $details, $performed_by]);
}
?>
