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

    // Update the product to set 'is_archive' to 1 (archived)
    $stmt = $pdo->prepare("UPDATE products SET is_archive = 1 WHERE product_id = ?");
    $stmt->execute([$productId]);

    // Redirect back to the product catalog with a status query
    header("Location: products.php?status=archived");
    exit;
} else {
    // If no product ID is provided, redirect to the product catalog
    header("Location: products.php");
    exit;
}
?>
