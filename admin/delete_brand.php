<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle brand deletion
if (isset($_GET['id'])) {
    $brandId = intval($_GET['id']);
    
    try {
        // Check if brand has associated products
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
        $stmt->execute([$brandId]);
        $productCount = $stmt->fetchColumn();
        
        if ($productCount > 0) {
            $_SESSION['error'] = "Cannot delete brand. It has " . $productCount . " associated products.";
        } else {
            // Get brand name for logging
            $stmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
            $stmt->execute([$brandId]);
            $brand = $stmt->fetch();
            
            // Delete the brand
            $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$brandId]);
            
            logHistory($pdo, 'Deleted Brand', 'Brand ID: ' . $brandId . ', Name: ' . $brand['name'], $_SESSION['username']);
            
            $_SESSION['success'] = "Brand deleted successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting brand: " . $e->getMessage();
    }
}

header("Location: products.php");
exit;
?> 