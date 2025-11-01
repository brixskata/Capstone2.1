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

// Set upload directory (relative to admin folder)
$uploadDir = __DIR__ . '/uploads/cancellation_receipts/';
$uploadPath = $uploadDir . $uniqueFilename;
$relativePath = 'uploads/cancellation_receipts/' . $uniqueFilename;

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
        
        // Update using the specific cancellation ID (more reliable than ORDER BY in UPDATE)
        $stmt = $pdo->prepare("UPDATE order_cancellations 
                              SET receipt_path = :path, 
                                  receipt_filename = :filename, 
                                  receipt_uploaded_at = NOW() 
                              WHERE id = :cancellation_id");
        
        $stmt->execute([
            'path' => $relativePath,
            'filename' => $uniqueFilename,
            'cancellation_id' => $cancellationId
        ]);
        
        // Check if the update was successful
        if ($stmt->rowCount() === 0) {
            unlink($uploadPath);
            error_log("Receipt upload failed: No rows updated for cancellation_id {$cancellationId}, order_id {$orderId}");
            echo json_encode(['success' => false, 'message' => 'Failed to update cancellation record. Cancellation ID: ' . $cancellationId]);
            exit;
        }
        
        error_log("Receipt uploaded successfully: order_id {$orderId}, cancellation_id {$cancellationId}, path: {$relativePath}");
        
        echo json_encode([
            'success' => true, 
            'message' => 'Receipt uploaded successfully',
            'file_path' => $relativePath,
            'filename' => $uniqueFilename,
            'cancellation_id' => $cancellationId
        ]);
        
    } catch (PDOException $e) {
        // If database update fails, delete the uploaded file
        if (file_exists($uploadPath)) {
            unlink($uploadPath);
        }
        error_log("Receipt upload error for order {$orderId}: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Failed to upload file']);
}
?>
