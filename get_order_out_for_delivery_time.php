<?php
// Suppress any PHP errors that might interfere with JSON output
error_reporting(0);
ini_set('display_errors', 0);

session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'User not logged in', 'session_data' => $_SESSION]);
    exit;
}

// Check if order ID is provided
if (!isset($_GET['order_id']) || !is_numeric($_GET['order_id'])) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

$order_id = (int)$_GET['order_id'];
$user_id = $_SESSION['user_id'];

try {
    // First, verify that the order belongs to the current user and is in "Out for delivery" status
    $sql = "SELECT o.orders_id, o.orderstatus_id, os.status_name, o.created_at
            FROM orders o 
            LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id 
            WHERE o.orders_id = :order_id AND o.user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order not found or you don\'t have permission to access this order']);
        exit;
    }

    // Check if order is in "Out for delivery" status
    if ($order['status_name'] !== 'Out for delivery') {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Order is not out for delivery']);
        exit;
    }

    // Try to get the exact timestamp when the order went "Out for delivery" from history logs
    $sql = "SELECT hl.performed_at 
            FROM history_logs hl
            JOIN history_action_types hat ON hl.history_action_type_id = hat.history_action_type_id
            WHERE hat.name = 'Update Order Status' 
            AND hl.details LIKE :search_pattern
            ORDER BY hl.performed_at DESC
            LIMIT 1";
    
    $search_pattern = "%Order ID: {$order_id}%Out for delivery%";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':search_pattern' => $search_pattern]);
    $history_log = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug: Log the query result
    error_log("History log query for order {$order_id}: " . ($history_log ? json_encode($history_log) : 'No result'));

    if ($history_log) {
        // Use the timestamp from history log
        $out_for_delivery_time = $history_log['performed_at'];
        error_log("Using history log timestamp for order {$order_id}: {$out_for_delivery_time}");
    } else {
        // Fallback: Use created_at timestamp if history log is not available
        // This is not ideal but we don't have updated_at column
        $out_for_delivery_time = $order['created_at'];
        error_log("Using fallback timestamp (created_at) for order {$order_id}: {$out_for_delivery_time}");
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'out_for_delivery_time' => $out_for_delivery_time,
        'order_id' => $order_id,
        'debug' => [
            'history_log_found' => $history_log ? true : false,
            'fallback_used' => $history_log ? false : true,
            'order_created_at' => $order['created_at']
        ]
    ]);

} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false, 
        'message' => 'An error occurred while fetching order timestamp',
        'error_details' => $e->getMessage(),
        'error_file' => $e->getFile(),
        'error_line' => $e->getLine()
    ]);
    error_log("Get order out for delivery time error: " . $e->getMessage());
    error_log("Error file: " . $e->getFile() . " Line: " . $e->getLine());
}
?>
