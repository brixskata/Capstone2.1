<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

if (isset($_GET['id']) && isset($_GET['action'])) {
    $orderId = intval($_GET['id']);
    $action = $_GET['action'];

    // Ensure the action is valid (processing, shipped, or deliver)
    $validActions = ['processing', 'shipped', 'deliver'];

    if (in_array($action, $validActions)) {
        // Update the order status based on the action
        $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
        $stmt->execute([$action, $orderId]);

        // Log the status update
        logHistory($pdo, 'Updated Order Status', 'Order ID: ' . $orderId . ', New Status: ' . ucfirst($action), $_SESSION['username']);

        // If the status is 'deliver', update status to 'Delivered' and add to delivered_orders for admin tracking
        if ($action === 'deliver') {
            // Update the order status to 'Delivered' instead of deleting
            $stmt = $pdo->prepare("UPDATE orders SET status = 'Delivered' WHERE id = ?");
            $stmt->execute([$orderId]);

            // Log the delivery status
            logHistory($pdo, 'Order Delivered', 'Order ID: ' . $orderId . ' marked as Delivered', $_SESSION['username']);

            // Fetch order details for delivered_orders tracking
            $stmt = $pdo->prepare("SELECT orders.id, users.username, orders.total_price FROM orders INNER JOIN users ON orders.user_id = users.id WHERE orders.id = ?");
            $stmt->execute([$orderId]);
            $order = $stmt->fetch();

            if ($order) {
                // Add to delivered_orders table for admin tracking (but keep original order intact)
                $stmt = $pdo->prepare("INSERT INTO delivered_orders (order_id, customer, total_price) VALUES (?, ?, ?)");
                $stmt->execute([$orderId, $order['username'], $order['total_price']]);
            }
        }

        header("Location: admin_dashboard2.php");
        exit;
    }
}

header("Location: admin_dashboard2.php");
exit;
?>