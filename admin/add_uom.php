<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $name = $_POST['name'];
        
        // Check if UOM already exists
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM units_of_measurement WHERE name = ?");
        $stmt->execute([$name]);
        $exists = $stmt->fetchColumn();
        
        if ($exists > 0) {
            $_SESSION['error'] = "Unit of Measurement already exists!";
        } else {
            $stmt = $pdo->prepare("INSERT INTO units_of_measurement (name) VALUES (?)");
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