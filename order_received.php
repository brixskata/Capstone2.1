<?php
session_start();
include 'includes/db.php';
include 'includes/log_history.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    if (isset($_POST['auto_confirm'])) {
        // Return JSON error for AJAX calls
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'User not logged in']);
        exit;
    }
    header('Location: login.php');
    exit;
}

// Check if order ID is provided
if (!isset($_POST['order_id']) || !is_numeric($_POST['order_id'])) {
    if (isset($_POST['auto_confirm'])) {
        // Return JSON error for AJAX calls
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
        exit;
    }
    $_SESSION['error'] = "Invalid order ID.";
    header('Location: orders.php');
    exit;
}

$order_id = (int)$_POST['order_id'];
$user_id = $_SESSION['user_id'];
$is_auto_confirm = isset($_POST['auto_confirm']) && $_POST['auto_confirm'] == '1';

try {
    // First, verify that the order belongs to the current user and is in "Out for delivery" status
    $sql = "SELECT o.orders_id, o.orderstatus_id, os.status_name 
            FROM orders o 
            LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id 
            WHERE o.orders_id = :order_id AND o.user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':order_id' => $order_id, ':user_id' => $user_id]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        if ($is_auto_confirm) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order not found or you don\'t have permission to access this order']);
            exit;
        }
        $_SESSION['error'] = "Order not found or you don't have permission to access this order.";
        header('Location: orders.php');
        exit;
    }

    // Check if order is in "Out for delivery" status
    if ($order['status_name'] !== 'Out for delivery') {
        if ($is_auto_confirm) {
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'message' => 'Order must be out for delivery before it can be confirmed']);
            exit;
        }
        $_SESSION['error'] = "Order must be out for delivery before you can confirm receipt.";
        header('Location: orders.php');
        exit;
    }

    // Check if "Completed" status exists, if not create it
    $sql = "SELECT orderstatus_id FROM order_status WHERE status_name = 'Completed'";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $completed_status = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$completed_status) {
        // Create "Completed" status
        $sql = "INSERT INTO order_status (status_name) VALUES ('Completed')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute();
        $completed_status_id = $pdo->lastInsertId();
    } else {
        $completed_status_id = $completed_status['orderstatus_id'];
    }

    // Update order status to "Completed"
    $sql = "UPDATE orders SET orderstatus_id = :status_id WHERE orders_id = :order_id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':status_id' => $completed_status_id, ':order_id' => $order_id]);

    // Log the action
    $log_message = $is_auto_confirm ? 
        'Order ID: ' . $order_id . ' automatically confirmed after 48 hours' : 
        'Order ID: ' . $order_id . ' marked as received by customer';
    logHistory($pdo, 'Order Received Confirmed', $log_message, $_SESSION['username']);

    if ($is_auto_confirm) {
        // Return JSON response for AJAX calls
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Order automatically confirmed after 48 hours',
            'order_id' => $order_id
        ]);
        exit;
    }

    $_SESSION['success'] = "Thank you for confirming receipt of your order!";
    $_SESSION['order_confirmed'] = true;
    header('Location: orders.php');
    exit;

} catch (Exception $e) {
    if ($is_auto_confirm) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'An error occurred while updating your order status']);
        exit;
    }
    $_SESSION['error'] = "An error occurred while updating your order status. Please try again.";
    error_log("Order received error: " . $e->getMessage());
    header('Location: orders.php');
    exit;
}
?>
