<?php
include '../includes/db.php';
include '../includes/permissions.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['receipt']) || $_FILES['receipt']['error'] !== UPLOAD_ERR_OK) {
    echo json_encode(['success' => false, 'message' => 'No file uploaded or upload error']);
    exit;
}

$file = $_FILES['receipt'];
$orderId = $_POST['order_id'] ?? null;

if (!$orderId) {
    echo json_encode(['success' => false, 'message' => 'Order ID required']);
    exit;
}

// Validate file type
$allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
$fileType = $file['type'];

if (!in_array($fileType, $allowedTypes)) {
    echo json_encode(['success' => false, 'message' => 'Invalid file type. Only JPG, PNG, and PDF files are allowed.']);
    exit;
}

// Validate file size (5MB max)
$maxSize = 5 * 1024 * 1024; 
if ($file['size'] > $maxSize) {
    echo json_encode(['success' => false, 'message' => 'File too large. Maximum size is 5MB.']);
    exit;
}

// Generate unique filename
$extension = pathinfo($file['name'], PATHINFO_EXTENSION);
$timestamp = date('Y-m-d_H-i-s');
$uniqueFilename = "order_{$orderId}_{$timestamp}.{$extension}";

// Set upload directory
$uploadDir = 'uploads/cancellation_receipts/';
$uploadPath = $uploadDir . $uniqueFilename;

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Move uploaded file
if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
    // Store receipt info in database
    try {
        // First, check if the cancellation record exists
        $checkStmt = $pdo->prepare("SELECT id FROM order_cancellations WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
        $checkStmt->execute([$orderId]);
        $cancellationId = $checkStmt->fetchColumn();
        
        if (!$cancellationId) {
            unlink($uploadPath);
            echo json_encode(['success' => false, 'message' => 'No cancellation record found for this order']);
            exit;
        }
        
        $stmt = $pdo->prepare("UPDATE order_cancellations 
                              SET receipt_path = :path, 
                                  receipt_filename = :filename, 
                                  receipt_uploaded_at = NOW() 
                              WHERE order_id = :order_id 
                              ORDER BY created_at DESC 
                              LIMIT 1");
        
        $stmt->execute([
            'path' => $uploadPath,
            'filename' => $uniqueFilename,
            'order_id' => $orderId
        ]);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Receipt uploaded successfully',
            'file_path' => $uploadPath,
            'filename' => $uniqueFilename
        ]);
        
    } catch (PDOException $e) {
        // If database update fails, delete the uploaded file
        unlink($uploadPath);
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>
