<?php
include 'db.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Check if a product ID is provided in the URL
if (isset($_GET['id'])) {
    $productId = $_GET['id'];

    // Update the product to set 'is_archived' to 1 (archived)
    $stmt = $pdo->prepare("UPDATE products SET is_archived = 1 WHERE id = ?");
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
