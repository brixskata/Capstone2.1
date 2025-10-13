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
        
        // Check if UOM already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM uom WHERE name = ?");
        $stmt->execute([$name]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            $_SESSION['error'] = "Unit of Measurement already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO uom (name) VALUES (?)");
            $stmt->execute([$name]);
            
            logHistory($pdo, 'Added UOM', 'UOM Name: ' . $name, $_SESSION['username']);
            
            $_SESSION['success'] = "Unit of Measurement added successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding UOM: " . $e->getMessage();
    }
    
    header("Location: products.php");
    exit;
} else {
    header("Location: products.php");
    exit;
}
?> 