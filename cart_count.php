
<?php
session_start();

// Simple count of items in cart
$count = 0;
if (isset($_SESSION['cart']) && is_array($_SESSION['cart'])) {
    $count = count($_SESSION['cart']);
}

echo $count;
?>
