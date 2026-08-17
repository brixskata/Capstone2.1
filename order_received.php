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
    $sql = "SELECT o.orders_id, o.orderstatus_id, os.status_name, o.delivery_option,
                   a.address_line, a.address_line2, a.city, a.state, a.postal_code, a.country
            FROM orders o 
            LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id 
            LEFT JOIN addresses a ON o.address_id = a.address_id
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

    // Check if order is in the canonical delivery status (database uses existing casing).
    if (strcasecmp($order['status_name'] ?? '', 'Out for Delivery') !== 0) {
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

    // Notify the customer that the order is completed.
    try {
        $emailStmt = $pdo->prepare("
            SELECT ui.email, u.username, o.total_price,
                   GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') AS items
            FROM orders o
            JOIN users u ON o.user_id = u.user_id
            LEFT JOIN user_info ui ON ui.user_id = u.user_id
            LEFT JOIN order_items oi ON o.orders_id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE o.orders_id = ?
            GROUP BY ui.email, u.username, o.total_price
        ");
        $emailStmt->execute([$order_id]);
        $customerData = $emailStmt->fetch(PDO::FETCH_ASSOC);

        if (!empty($customerData['email'])) {
            require_once __DIR__ . '/includes/email_helper.php';
            $itemsHtml = '';
            foreach (explode(', ', $customerData['items'] ?? 'Items from your order') as $item) {
                $itemsHtml .= '<li>' . htmlspecialchars($item) . '</li>';
            }

            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background-color: #7F1734; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h2 style='margin: 0;'>MikeMadz</h2>
                </div>
                <div style='background-color: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px;'>
                    <h3 style='color: #7F1734;'>Order Status Update</h3>
                    <p>Hello " . htmlspecialchars($customerData['username'] ?? 'Customer') . ",</p>
                    <p><strong>Your order has been completed successfully.</strong></p>
                    <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #7F1734;'>
                        <p style='margin: 5px 0;'><strong>Order ID:</strong> #{$order_id}</p>
                        <p style='margin: 5px 0;'><strong>Status:</strong> Completed</p>
                        <p style='margin: 5px 0;'><strong>Total Amount:</strong> ₱" . number_format($customerData['total_price'] ?? 0, 2) . "</p>
                    </div>
                    <div style='margin: 20px 0;'>
                        <p><strong>Order Items:</strong></p>
                        <ul style='list-style-position: inside;'>{$itemsHtml}</ul>
                    </div>
                    <p>Thank you for choosing MikeMadz. We hope to serve you again!</p>
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;'>
                        <p>This is an automated email. Please do not reply to this message.</p>
                    </div>
                </div>
            </div>";

            if (!sendCustomerEmail($customerData['email'], "Order #{$order_id} Has Been Completed - MikeMadz", $emailBody)) {
                error_log("Failed to send completed-order email for order #{$order_id}.");
            }
        } else {
            error_log("Completed-order email skipped for order #{$order_id}: customer email is empty.");
        }
    } catch (Exception $emailError) {
        error_log("Failed to send completed-order email for order #{$order_id}: " . $emailError->getMessage());
    }

    // Log the action
    $log_message = $is_auto_confirm ? 
        'Order ID: ' . $order_id . ' automatically confirmed after 48 hours' : 
        'Order ID: ' . $order_id . ' marked as received by customer';
    logHistory($pdo, 'Order Received Confirmed', $log_message, $_SESSION['username']);

    if ($is_auto_confirm) {
        // Prepare delivery/pickup information for auto-confirm message
        $delivery_info = "";
        if ($order['delivery_option'] === 'delivery') {
            $address_parts = array_filter([
                $order['address_line'],
                $order['address_line2'],
                $order['city'],
                $order['state'],
                $order['postal_code'],
                $order['country']
            ]);
            $full_address = implode(', ', $address_parts);
            $delivery_info = " (Delivery to: " . $full_address . ")";
        } else {
            $delivery_info = " (Pickup order)";
        }
        
        // Return JSON response for AJAX calls
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true, 
            'message' => 'Order automatically confirmed after 48 hours' . $delivery_info,
            'order_id' => $order_id
        ]);
        exit;
    }

    // Prepare delivery/pickup information for display
    $delivery_info = "";
    if ($order['delivery_option'] === 'delivery') {
        $address_parts = array_filter([
            $order['address_line'],
            $order['address_line2'],
            $order['city'],
            $order['state'],
            $order['postal_code'],
            $order['country']
        ]);
        $full_address = implode(', ', $address_parts);
        $delivery_info = " (Delivery to: " . $full_address . ")";
    } else {
        $delivery_info = " (Pickup order)";
    }

    $_SESSION['success'] = "Thank you for confirming receipt of your order!" . $delivery_info;
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
