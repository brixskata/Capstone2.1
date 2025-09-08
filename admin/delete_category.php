<?php
include 'db.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if a category ID is provided in the URL
if (isset($_GET['id'])) {
    $categoryId = $_GET['id'];

    // Delete the category from the database
    $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
    $stmt->execute([$categoryId]);

    // Redirect back to the manage categories page after deletion
    header("Location: products.php");
    exit;
} else {
    // If no category ID is provided, redirect to the categories page
    header("Location: products.php");
    exit;
}
?>
