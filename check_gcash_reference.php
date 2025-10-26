<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if reference number is provided
if (!isset($_POST['reference_number']) || empty(trim($_POST['reference_number']))) {
    echo json_encode(['exists' => false, 'message' => '']);
    exit;
}

$referenceNumber = trim($_POST['reference_number']);

// Sanitize reference number (should be numeric, 8-13 digits)
if (!preg_match('/^[0-9]{8,13}$/', $referenceNumber)) {
    echo json_encode(['exists' => false, 'message' => 'Reference number must be 8 to 13 digits']);
    exit;
}

try {
    // Check if reference number already exists in the payments table
    // We'll check all payment records regardless of status
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as count 
        FROM payments 
        WHERE transaction_id = ? 
        AND transaction_id IS NOT NULL 
        AND transaction_id != ''
    ");
    $stmt->execute([$referenceNumber]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $exists = $result['count'] > 0;
    
    if ($exists) {
        echo json_encode([
            'exists' => true, 
            'message' => 'This reference number has already been used'
        ]);
    } else {
        echo json_encode([
            'exists' => false, 
            'message' => 'Reference number available'
        ]);
    }
    
} catch (PDOException $e) {
    error_log("Error checking GCash reference: " . $e->getMessage());
    echo json_encode([
        'exists' => false, 
        'message' => 'Error checking reference number'
    ]);
}

