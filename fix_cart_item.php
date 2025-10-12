<?php
session_start();
include 'includes/db.php';

echo "<h2>=== CART ITEM FIXER ===</h2>";

$user_id = $_SESSION['user_id'];

// Get the cart item that needs fixing
$stmt = $pdo->prepare("
    SELECT ci.*, c.cart_id
    FROM cart_items ci
    INNER JOIN cart c ON ci.cart_id = c.cart_id
    WHERE c.user_id = ? AND ci.product_id = 17 AND c.is_active = 1
    LIMIT 1
");
$stmt->execute([$user_id]);
$cart_item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cart_item) {
    echo "<p style='color: red;'>❌ Cart item not found</p>";
    exit;
}

echo "<h3>Current Cart Item:</h3>";
echo "<pre>" . json_encode($cart_item, JSON_PRETTY_PRINT) . "</pre>";

// Get the best available batch for this product
$stmt = $pdo->prepare("
    SELECT pb.batch_id, pb.brand_id, pb.quantity_remaining, b.name as brand_name
    FROM product_batches pb
    LEFT JOIN brands b ON pb.brand_id = b.id
    WHERE pb.product_id = ? 
    AND pb.quantity_remaining > 0 
    AND pb.is_active = 1
    ORDER BY pb.expiration_date ASC
    LIMIT 1
");
$stmt->execute([17]);
$best_batch = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$best_batch) {
    echo "<p style='color: red;'>❌ No available batches found</p>";
    exit;
}

echo "<h3>Best Available Batch:</h3>";
echo "<pre>" . json_encode($best_batch, JSON_PRETTY_PRINT) . "</pre>";

// Update the cart item with the correct brand_id and batch_id
try {
    $pdo->beginTransaction();
    
    $update_stmt = $pdo->prepare("
        UPDATE cart_items 
        SET brand_id = ?, batch_id = ?, updated_at = NOW()
        WHERE cartitem_id = ?
    ");
    $update_stmt->execute([
        $best_batch['brand_id'],
        $best_batch['batch_id'], 
        $cart_item['cartitem_id']
    ]);
    
    // Also update the session cart
    if (isset($_SESSION['cart']['17_kilo'])) {
        $_SESSION['cart']['17_kilo']['brand_id'] = $best_batch['brand_id'];
        $_SESSION['cart']['17_kilo']['batch_id'] = $best_batch['batch_id'];
    }
    
    $pdo->commit();
    
    echo "<p style='color: green;'>✅ Cart item updated successfully!</p>";
    echo "<p>Brand ID: {$best_batch['brand_id']} ({$best_batch['brand_name']})</p>";
    echo "<p>Batch ID: {$best_batch['batch_id']}</p>";
    
} catch (Exception $e) {
    $pdo->rollBack();
    echo "<p style='color: red;'>❌ Error updating cart item: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<h3>Updated Cart Item:</h3>";
$stmt = $pdo->prepare("
    SELECT ci.*, c.cart_id
    FROM cart_items ci
    INNER JOIN cart c ON ci.cart_id = c.cart_id
    WHERE c.user_id = ? AND ci.product_id = 17 AND c.is_active = 1
    LIMIT 1
");
$stmt->execute([$user_id]);
$updated_cart_item = $stmt->fetch(PDO::FETCH_ASSOC);
echo "<pre>" . json_encode($updated_cart_item, JSON_PRETTY_PRINT) . "</pre>";

echo "<hr>";
echo "<h3>SOLUTION:</h3>";
echo "<p><a href='checkout.php' style='background: green; color: white; padding: 10px; text-decoration: none; border-radius: 5px; font-size: 16px;'>🚀 Try Checkout Now</a></p>";
echo "<p><a href='cart.php'>Go to Cart</a></p>";
?>
