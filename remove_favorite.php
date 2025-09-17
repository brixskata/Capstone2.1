
<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo 'not_logged_in';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo 'invalid_token';
        exit;
    }
    
    $productId = $_POST['product_id'];
    $userId = $_SESSION['user_id'];
    
    // Remove from favorites table
    $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
    $result = $stmt->execute([$userId, $productId]);
    
    if ($result) {
        echo 'removed';
    } else {
        echo 'error';
    }
} else {
    echo 'invalid_request';
}
?>
