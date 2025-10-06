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
