<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login_admin.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $orderId = intval($_GET['id']);
    $action = $_GET['action'];

    // Ensure the action is valid (processing, shipped, or deliver)
    $validActions = ['processing', 'shipped', 'deliver'];

    if (in_array($action, $validActions)) {
        // Map action to orderstatus_id
        $statusMap = [
            'processing' => 2, // To Ship
            'shipped' => 3,    // Out for delivery
            'deliver' => 4     // Completed
        ];
        
        $orderstatusId = $statusMap[$action] ?? 1; // Default to Pending
        
        // Update the order status using foreign key
        $stmt = $pdo->prepare("UPDATE orders SET orderstatus_id = ? WHERE orders_id = ?");
        $stmt->execute([$orderstatusId, $orderId]);

        // Log the status update
        logHistory($pdo, 'Updated Order Status', 'Order ID: ' . $orderId . ', New Status: ' . ucfirst($action), $_SESSION['username']);

        // If the status is 'deliver', log the delivery status
        if ($action === 'deliver') {
            // Log the delivery status
            logHistory($pdo, 'Order Delivered', 'Order ID: ' . $orderId . ' marked as Delivered', $_SESSION['username']);
        }

        header("Location: admin_dashboard2.php");
        exit;
    }
}

header("Location: admin_dashboard2.php");
exit;
?>