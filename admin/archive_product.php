<?php
include '../includes/db.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if a product ID is provided in the URL
if (isset($_GET['id'])) {
    $productId = $_GET['id'];

    try {
        // Start transaction
        $pdo->beginTransaction();
        
        // Update the product to set 'is_archive' to 1 (archived)
        $stmt = $pdo->prepare("UPDATE products SET is_archive = 1 WHERE product_id = ?");
        $stmt->execute([$productId]);
        
        // Remove archived product from all user carts
        $stmt = $pdo->prepare("
            DELETE ci FROM cart_items ci
            INNER JOIN cart c ON ci.cart_id = c.cart_id
            WHERE ci.product_id = ? AND c.is_active = 1
        ");
        $stmt->execute([$productId]);
        
        // Commit transaction
        $pdo->commit();
        
        // Redirect back to the product catalog with a status query
        header("Location: products.php?status=archived");
        exit;
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Error archiving product: " . $e->getMessage());
        header("Location: products.php?error=archive_failed");
        exit;
    }
} else {
    // If no product ID is provided, redirect to the product catalog
    header("Location: products.php");
    exit;
}
?>
