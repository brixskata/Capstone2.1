<?php
session_start();
require_once 'db.php';

// Check if user is super admin (usertype_id = 1)
if (!isset($_SESSION['user_id']) || $_SESSION['usertype_id'] != 1) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only super administrators can reject payment proofs']);
    exit;
}

// Check if required parameters are provided
if (!isset($_POST['order_id']) || !isset($_POST['payment_proof'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing required parameters']);
    exit;
}

$order_id = (int)$_POST['order_id'];
$payment_proof = $_POST['payment_proof'];

try {
    $pdo->beginTransaction();
    
    // Update order status to Cancelled (status_id = 5)
    $updateOrderStmt = $pdo->prepare("UPDATE orders SET orderstatus_id = 5 WHERE orders_id = ?");
    $updateOrderStmt->execute([$order_id]);
    
    // Remove payment proof from payments table
    $updatePaymentStmt = $pdo->prepare("UPDATE payments SET proof = NULL WHERE orders_id = ?");
    $updatePaymentStmt->execute([$order_id]);
    
    // Log the rejection action
    $logStmt = $pdo->prepare("
        INSERT INTO history_logs (history_action_type_id, reference_id, reference_type, details, performed_by, performed_at) 
        VALUES (1, ?, 'order', ?, ?, NOW())
    ");
    $logDetails = "Order #{$order_id} payment proof rejected by admin. Payment proof: {$payment_proof}";
    $logStmt->execute([$order_id, $logDetails, $_SESSION['user_id']]);
    
    // Create notification for customer
    $notificationStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, order_id, message, is_read, created_at) 
        VALUES ((SELECT user_id FROM orders WHERE orders_id = ?), ?, ?, 0, NOW())
    ");
    $notificationMessage = "Your payment proof for Order #{$order_id} has been rejected. Please contact support for assistance.";
    $notificationStmt->execute([$order_id, $order_id, $notificationMessage]);

    // Notify the customer by email that the order was cancelled.
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
            require_once __DIR__ . '/../includes/email_helper.php';
            $itemsHtml = '';
            foreach (explode(', ', $customerData['items'] ?? 'Items from your order') as $item) {
                $itemsHtml .= '<li>' . htmlspecialchars($item) . '</li>';
            }

            $emailBody = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px;'>
                <div style='background-color: #dc3545; color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0;'>
                    <h2 style='margin: 0;'>MikeMadz</h2>
                </div>
                <div style='background-color: #f9f9f9; padding: 20px; border-radius: 0 0 10px 10px;'>
                    <h3 style='color: #dc3545;'>Order Cancellation Notice</h3>
                    <p>Hello " . htmlspecialchars($customerData['username'] ?? 'Customer') . ",</p>
                    <p>We regret to inform you that your order has been cancelled because the payment proof was rejected.</p>
                    <div style='background-color: white; padding: 15px; border-radius: 5px; margin: 20px 0; border-left: 4px solid #dc3545;'>
                        <p style='margin: 5px 0;'><strong>Order ID:</strong> #{$order_id}</p>
                        <p style='margin: 5px 0;'><strong>Status:</strong> Cancelled</p>
                        <p style='margin: 5px 0;'><strong>Total Amount:</strong> ₱" . number_format($customerData['total_price'] ?? 0, 2) . "</p>
                        <p style='margin: 5px 0;'><strong>Reason:</strong> Payment proof rejected</p>
                    </div>
                    <div style='margin: 20px 0;'>
                        <p><strong>Order Items:</strong></p>
                        <ul style='list-style-position: inside;'>{$itemsHtml}</ul>
                    </div>
                    <p>Please contact support if you have any questions.</p>
                    <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #ddd; color: #666; font-size: 12px;'>
                        <p>This is an automated email. Please do not reply to this message.</p>
                    </div>
                </div>
            </div>";

            if (!sendCustomerEmail($customerData['email'], "Order #{$order_id} Has Been Cancelled - MikeMadz", $emailBody)) {
                error_log("Failed to send cancelled-order email for order #{$order_id}.");
            }
        } else {
            error_log("Cancelled-order email skipped for order #{$order_id}: customer email is empty.");
        }
    } catch (Exception $emailError) {
        error_log("Failed to send cancelled-order email for order #{$order_id}: " . $emailError->getMessage());
    }
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true, 
        'message' => 'Payment proof rejected successfully',
        'order_id' => $order_id
    ]);
    
} catch (Exception $e) {
    $pdo->rollBack();
    error_log("Error rejecting payment proof: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
