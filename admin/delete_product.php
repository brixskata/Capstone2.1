<?php
include 'db.php';
session_start();

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: index.php");
    exit;
}

if (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $product_id = intval($_GET['id']);

    // Delete related records from `order_items` first
    $stmt = $pdo->prepare("DELETE FROM order_items WHERE product_id = ?");
    $stmt->execute([$product_id]);

    // Then delete the product
    $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$product_id]);

    header("Location: products.php");
    exit;
} else {
    echo "Invalid product ID.";
}
?>