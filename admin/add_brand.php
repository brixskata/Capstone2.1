<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
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

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $name = $_POST['name'];
        
        // Check if brand already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM brands WHERE name = ?");
        $stmt->execute([$name]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            $_SESSION['error'] = "Brand already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO brands (name) VALUES (?)");
            $stmt->execute([$name]);
            
            logHistory($pdo, 'Added Brand', 'Brand Name: ' . $name, $_SESSION['username']);
            
            $_SESSION['success'] = "Brand added successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding brand: " . $e->getMessage();
    }
    
    header("Location: products.php");
    exit;
} else {
    header("Location: products.php");
    exit;
}
?> 