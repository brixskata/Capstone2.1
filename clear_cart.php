<?php
// Simple script to clear the cart
session_start();

if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
    echo "Cart cleared successfully!";
} else {
    echo "Cart was already empty.";
}
?>