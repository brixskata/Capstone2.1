
<?php
session_start();
include 'includes/db.php';

// Clear session cart
if (isset($_SESSION['cart'])) {
    unset($_SESSION['cart']);
    $_SESSION['cart'] = array();
}

// Clear database cart if user is logged in
$user_id = $_SESSION['user_id'] ?? null;
if ($user_id) {
    $sql = "DELETE FROM user_cart WHERE user_id = :user_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
}

// Redirect back to cart page
header('Location: cart.php');
exit;
?>
