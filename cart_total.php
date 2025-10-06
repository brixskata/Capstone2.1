<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Include DB connection
include_once 'includes/db.php';

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculate cart total
$cart_total = 0;
$cart_count = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $cart_item) {
        $sql = "SELECT COALESCE(pp.selling_price, 0) AS price
                FROM products p
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                WHERE p.product_id = :product_id AND p.is_archive = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $quantity = $cart_item['quantity'] ?? 1;
            $cart_total += $product['price'] * $quantity;
            $cart_count += $quantity;
        }
    }
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode([
    'total' => $cart_total,
    'count' => $cart_count,
    'has_items' => !empty($_SESSION['cart'])
]);
?>
