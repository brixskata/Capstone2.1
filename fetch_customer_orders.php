<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

include 'includes/db.php';

$user_id = $_SESSION['user_id'];

// Fetch all orders for the current user
$sql = "SELECT o.orders_id, o.created_at, os.status_name as status, o.total_price,
               o.plate_number, o.transaction_number,
               oi.quantity, p.product_name, COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as price,
               oc.reason AS cancel_reason, oc.receipt_path, oc.receipt_filename,
               a.address_line, a.address_line2, a.city, a.state, a.postal_code, a.country
        FROM orders o
        LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
        LEFT JOIN order_items oi ON o.orders_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        LEFT JOIN addresses a ON a.address_id = o.address_id
        LEFT JOIN (
            SELECT oc1.order_id, oc1.reason, oc1.receipt_path, oc1.receipt_filename
            FROM order_cancellations oc1
            INNER JOIN (
                SELECT order_id, MAX(id) AS max_id
                FROM order_cancellations
                GROUP BY order_id
            ) latest ON latest.order_id = oc1.order_id AND latest.max_id = oc1.id
        ) oc ON oc.order_id = o.orders_id
        WHERE o.user_id = :user_id
        ORDER BY o.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$allOrders = [];
foreach ($rawOrders as $row) {
    $orderId = $row['orders_id'];
    if (!isset($allOrders[$orderId])) {
        $allOrders[$orderId] = [
            'id' => $row['orders_id'],
            'created_at' => $row['created_at'],
            'status' => $row['status'],
            'total_price' => $row['total_price'],
            'plate_number' => $row['plate_number'] ?? null,
            'transaction_number' => $row['transaction_number'] ?? null,
            'cancel_reason' => $row['cancel_reason'] ?? null,
            'receipt_path' => $row['receipt_path'] ?? null,
            'receipt_filename' => $row['receipt_filename'] ?? null,
            'address' => [
                'address_line' => $row['address_line'] ?? '',
                'address_line2' => $row['address_line2'] ?? '',
                'city' => $row['city'] ?? '',
                'state' => $row['state'] ?? '',
                'postal_code' => $row['postal_code'] ?? '',
                'country' => $row['country'] ?? ''
            ],
            'items' => []
        ];
    }

    if ($row['product_name']) {
        $allOrders[$orderId]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price']
        ];
    }
}

// Separate orders by status
$pendingOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Pending';
});

$toShipOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'To Ship';
});

$outForDeliveryOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Out for delivery';
});

$completedOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Completed';
});

$cancelledOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Cancelled';
});

$readyForPickupOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Ready for Pick Up';
});

$currentOrders = array_filter($allOrders, function($order) {
    return !in_array($order['status'], ['Completed', 'Cancelled']);
});

// Prepare response
$response = [
    'success' => true,
    'timestamp' => time(),
    'orders' => [
        'all' => array_values($allOrders),
        'pending' => array_values($pendingOrders),
        'to_ship' => array_values($toShipOrders),
        'out_for_delivery' => array_values($outForDeliveryOrders),
        'completed' => array_values($completedOrders),
        'cancelled' => array_values($cancelledOrders),
        'ready_for_pickup' => array_values($readyForPickupOrders),
        'current' => array_values($currentOrders)
    ],
    'counts' => [
        'total' => count($allOrders),
        'pending' => count($pendingOrders),
        'to_ship' => count($toShipOrders),
        'out_for_delivery' => count($outForDeliveryOrders),
        'completed' => count($completedOrders),
        'cancelled' => count($cancelledOrders),
        'ready_for_pickup' => count($readyForPickupOrders),
        'current' => count($currentOrders)
    ]
];

header('Content-Type: application/json');
echo json_encode($response);
