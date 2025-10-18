<?php
include '../includes/db.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get optional status filter
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';

// Fetch order statistics
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN os.status_name = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN os.status_name = 'To Ship' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN os.status_name = 'Ready for Pick Up' THEN 1 ELSE 0 END) as pickup_orders,
        SUM(CASE WHEN os.status_name = 'Out for delivery' THEN 1 ELSE 0 END) as shipped_orders,
        SUM(CASE WHEN os.status_name = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN os.status_name = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders
    FROM orders o
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
")->fetch();

// Build base query
$query = "
    SELECT o.orders_id AS id,
           u.username,
           ui.email,
           ui.phone,
           a.address_line,
           a.address_line2,
           a.city,
           a.state,
           a.postal_code,
           a.country,
           os.status_name AS status,
           o.total_price as total_amount,
           o.delivery_option,
           o.created_at,
           COALESCE(pay.method, '') as payment_method,
           COALESCE(pay.proof, '') as payment_proof,
           COALESCE(pay.transaction_id, '') as gcash_transaction_id,
           oc.reason AS cancel_reason,
           oc.receipt_path,
           oc.receipt_filename,
           oc.receipt_uploaded_at,
           GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    LEFT JOIN user_info ui ON ui.user_id = u.user_id
    LEFT JOIN addresses a ON a.address_id = o.address_id
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
    LEFT JOIN order_items oi ON o.orders_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN payments pay ON pay.orders_id = o.orders_id
    LEFT JOIN (
        SELECT oc1.order_id, oc1.reason, oc1.receipt_path, oc1.receipt_filename, oc1.receipt_uploaded_at
        FROM order_cancellations oc1
        INNER JOIN (
            SELECT order_id, MAX(id) AS max_id
            FROM order_cancellations
            GROUP BY order_id
        ) latest ON latest.order_id = oc1.order_id AND latest.max_id = oc1.id
    ) oc ON oc.order_id = o.orders_id
    WHERE os.status_name IS NOT NULL
";

// Apply status filter if provided
if ($status_filter && $status_filter !== 'all') {
    $query .= " AND os.status_name = '" . str_replace("'", "''", $status_filter) . "'";
}

$query .= " GROUP BY o.orders_id, u.username, ui.email, ui.phone, a.address_line, a.address_line2, a.city, a.state, a.postal_code, a.country, os.status_name, o.total_price, o.delivery_option, o.created_at, pay.method, pay.proof, pay.transaction_id, oc.reason, oc.receipt_path, oc.receipt_filename, oc.receipt_uploaded_at ORDER BY o.created_at DESC";

try {
    $orders = $pdo->query($query)->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

// Prepare response
$response = [
    'success' => true,
    'timestamp' => time(),
    'stats' => $stats,
    'orders' => $orders,
    'user_type' => $_SESSION['usertype_id'] ?? 2,
    'is_super_admin' => (isset($_SESSION['usertype_id']) && $_SESSION['usertype_id'] == 1) || (isset($_SESSION['role']) && $_SESSION['role'] === 'super_admin')
];

header('Content-Type: application/json');
echo json_encode($response);

