<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'includes/db.php';

$user_id = $_SESSION['user_id'];
$order_id = $_GET['order_id'] ?? null;

if (!$order_id || !is_numeric($order_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid order ID']);
    exit;
}

try {
    // First, check if out_for_delivery_at column exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM orders LIKE 'out_for_delivery_at'");
    $columnExists = $checkColumn->rowCount() > 0;
    
    // Build query based on whether column exists
    if ($columnExists) {
        $sql = "SELECT 
                    o.orders_id,
                    o.created_at,
                    o.out_for_delivery_at,
                    o.total_price,
                    os.status_name as current_status
                FROM orders o
                INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                WHERE o.orders_id = :order_id 
                AND o.user_id = :user_id";
    } else {
        // Fallback: use created_at if column doesn't exist
        $sql = "SELECT 
                    o.orders_id,
                    o.created_at,
                    NULL as out_for_delivery_at,
                    o.total_price,
                    os.status_name as current_status
                FROM orders o
                INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                WHERE o.orders_id = :order_id 
                AND o.user_id = :user_id";
    }
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => 'Order not found or access denied']);
        exit;
    }
    
    // Check if order is "Out for delivery"
    if ($order['current_status'] !== 'Out for delivery') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'error' => 'Order is not out for delivery',
            'current_status' => $order['current_status']
        ]);
        exit;
    }
    
    // Use out_for_delivery_at timestamp if available, otherwise fallback to created_at
    $out_for_delivery_time = !empty($order['out_for_delivery_at']) 
        ? $order['out_for_delivery_at'] 
        : $order['created_at'];
    
    // Calculate hours elapsed
    $out_for_delivery_timestamp = strtotime($out_for_delivery_time);
    $current_timestamp = time();
    $hours_elapsed = ($current_timestamp - $out_for_delivery_timestamp) / 3600;
    
    $response = [
        'success' => true,
        'order_id' => $order['orders_id'],
        'out_for_delivery_time' => $out_for_delivery_time,
        'hours_elapsed' => round($hours_elapsed, 2),
        'current_status' => $order['current_status'],
        'total_price' => $order['total_price']
    ];
    
    header('Content-Type: application/json');
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
?>