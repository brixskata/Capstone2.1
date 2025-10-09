<?php
include '../includes/db.php';
session_start();

if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
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