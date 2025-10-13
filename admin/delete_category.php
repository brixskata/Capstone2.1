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
