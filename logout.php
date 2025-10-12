<?php
session_start();
include 'includes/db.php';

// Save cart to database before destroying session (for customers only)
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
    if (!empty($_SESSION['cart'])) {
        include_once 'includes/cart_manager.php';
        $cartManager = new CartManager($pdo);
        $cartManager->saveCartToDatabase($_SESSION['user_id'], $_SESSION['cart']);
    }
}

session_destroy();
header("Location: login.php");
exit;
