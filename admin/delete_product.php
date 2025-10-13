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

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Delete related records from `order_items` first
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE product_id = ?");
    $stmt->execute([$product_id]);

    // Then delete the product
    $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
    $stmt->execute([$product_id]);

    header("Location: products.php");
    exit;
} else {
    echo "Invalid product ID.";
}
?>