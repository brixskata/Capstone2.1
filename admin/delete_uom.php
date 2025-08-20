<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle UOM deletion
if (isset($_GET['id'])) {
    $uomId = intval($_GET['id']);
    
    try {
        // Check if UOM has associated products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE uom_id = ?");
        $stmt->execute([$uomId]);
        $productCount = $stmt->fetchColumn();
        
        if ($productCount > 0) {
            $_SESSION['error'] = "Cannot delete UOM. It has " . $productCount . " associated products.";
        } else {
            // Get UOM name for logging
            $stmt = $pdo->prepare("SELECT name FROM units_of_measurement WHERE id = ?");
            $stmt->execute([$uomId]);
            $uom = $stmt->fetch();
            
            // Delete the UOM
            $stmt = $pdo->prepare("DELETE FROM units_of_measurement WHERE id = ?");
            $stmt->execute([$uomId]);
            
            logHistory($pdo, 'Deleted UOM', 'UOM ID: ' . $uomId . ', Name: ' . $uom['name'], $_SESSION['username']);
            
            $_SESSION['success'] = "Unit of Measurement deleted successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting UOM: " . $e->getMessage();
    }
}

header("Location: products.php");
exit;
?> 