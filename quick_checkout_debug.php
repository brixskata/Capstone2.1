<?php
session_start();
include 'includes/db.php';
include 'includes/cart_manager.php';

echo "<h2>=== QUICK CHECKOUT DEBUG ===</h2>";

// Check 1: Session errors
echo "<h3>1. Session Errors Check:</h3>";
$error_keys = ['stock_errors', 'error', 'verification_required', 'address_required'];
$found_errors = [];
foreach($error_keys as $key) {
    if (isset($_SESSION[$key])) {
        $found_errors[] = $key . ": " . (is_array($_SESSION[$key]) ? json_encode($_SESSION[$key]) : $_SESSION[$key]);
    }
}

if (!empty($found_errors)) {
    echo "<p style='color: red;'>❌ Found session errors:</p>";
    echo "<ul>";
    foreach($found_errors as $error) {
        echo "<li style='color: red;'>$error</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✅ No session errors found</p>";
}

// Check 2: Cart data
echo "<h3>2. Cart Data Check:</h3>";
$user_id = $_SESSION['user_id'] ?? null;
if (!$user_id) {
    echo "<p style='color: red;'>❌ Not logged in</p>";
    exit;
}

$cartManager = new CartManager($pdo);
$cart_data = $cartManager->loadCartFromDatabase($user_id);

if (empty($cart_data)) {
    echo "<p style='color: red;'>❌ Empty cart</p>";
    exit;
}

echo "<p style='color: green;'>✅ Cart has " . count($cart_data) . " items</p>";

// Check 3: Stock validation for each cart item
echo "<h3>3. Stock Validation Check:</h3>";
$stock_errors = [];

foreach ($cart_data as $cart_key => $cart_item) {
    $product_id = $cart_item['product_id'] ?? $cart_key;
    if (is_string($product_id) && strpos($product_id, '_') !== false) {
        $product_id = intval(explode('_', $product_id)[0]);
    }
    
    $quantity = floatval($cart_item['quantity'] ?? 1);
    $brand_id = $cart_item['brand_id'] ?? null;
    
    echo "<p>Checking: Product $product_id, Qty $quantity, Brand: " . ($brand_id ?: 'NULL') . "</p>";
    
    // Get available stock
    $stmt = $pdo->prepare("
        SELECT COALESCE(ps.current_stock, 0) AS current_stock
        FROM products p
        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
        WHERE p.product_id = ? AND p.is_archive = 0
    ");
    $stmt->execute([$product_id]);
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $available_stock = floatval($stock_data['current_stock'] ?? 0);
    
    echo "<p>Available stock: $available_stock</p>";
    
    if ($available_stock < $quantity) {
        $error_msg = "Insufficient stock for product $product_id. Available: $available_stock, Requested: $quantity";
        echo "<p style='color: red;'>❌ $error_msg</p>";
        $stock_errors[] = $error_msg;
    } else {
        echo "<p style='color: green;'>✅ Stock OK</p>";
    }
}

// Check 4: Final result
echo "<h3>4. Final Check:</h3>";
if (!empty($stock_errors)) {
    echo "<p style='color: red;'>❌ STOCK ERRORS FOUND - This is why checkout redirects to cart.php:</p>";
    echo "<ul>";
    foreach($stock_errors as $error) {
        echo "<li style='color: red;'>$error</li>";
    }
    echo "</ul>";
} else {
    echo "<p style='color: green;'>✅ No stock errors - Checkout should work!</p>";
}

echo "<hr>";
echo "<h3>Quick Fixes:</h3>";
echo "<p><a href='comprehensive_session_cleaner.php' style='background: orange; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🧹 Clear Session Errors</a></p>";
echo "<p><a href='checkout.php' style='background: green; color: white; padding: 10px; text-decoration: none; border-radius: 5px;'>🚀 Try Checkout</a></p>";
?>
