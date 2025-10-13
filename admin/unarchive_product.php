<?php
include '../includes/db.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Check if a product ID is provided in the URL
if (isset($_GET['id'])) {
    $productId = $_GET['id'];

    // Update the product to set 'is_archive' to 0 (unarchived)
    $stmt = $pdo->prepare("UPDATE products SET is_archive = 0 WHERE product_id = ?");
    $stmt->execute([$productId]);

    // Redirect back to the product catalog
    header("Location: products.php");
    exit;
} else {
    // If no product ID is provided, redirect to the product catalog
    header("Location: products.php");
    exit;
}
?>
