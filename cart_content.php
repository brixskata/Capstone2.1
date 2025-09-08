<?php
session_start();
include 'includes/db.php';

// Helper function to get product data efficiently
function getProductData($pdo, $product_ids) {
    if (empty($product_ids)) return [];
    
    try {
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        $sql = "SELECT 
                    p.product_id,
                    p.product_name,
                    p.product_description,
                    COALESCE(pp.selling_price, 0) AS price,
                    COALESCE(ps.current_stock, 0) AS stock,
                    (SELECT pi.image_url FROM product_images pi 
                     WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                     ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                FROM products p
                LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
                LEFT JOIN product_stock ps ON ps.product_id = p.product_id
                WHERE p.product_id IN ($placeholders) AND p.is_archive = 0
                ORDER BY pp.productpricing_id DESC";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($product_ids);
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row && isset($row['product_id'])) {
                $products[$row['product_id']] = $row;
            }
        }
        return $products;
    } catch (Exception $e) {
        error_log("Error in getProductData: " . $e->getMessage());
        return [];
    }
}

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
        $product_id = $cart_item['product_id'] ?? $cart_key;
        $product_data = getProductData($pdo, [$product_id]);
        if (!empty($product_data[$product_id])) {
            $product = $product_data[$product_id];
            $qty = $cart_item['quantity'] ?? 1;
            $unit_price = $cart_item['unit_price'] ?? $product['price'];
            $unit = $cart_item['unit'] ?? 'kilo';
            $box_id = $cart_item['box_id'] ?? null;
            $weight = $cart_item['weight'] ?? null;
            
            $item_total = $unit_price * $qty;
            
            $cart_total += $item_total;
            
            // Create display name with unit info
            $display_name = $product['product_name'] ?? 'Unknown Product';
            if ($unit === 'piece') {
                $display_name .= ' (per piece)';
            } else if ($unit === 'box' && $weight) {
                $display_name .= ' (Box - ' . number_format($weight, 2) . 'kg)';
            } else {
                $display_name .= ' (per kilo)';
            }
            
            $cart_items[] = [
                'product' => [
                    'id' => $product['product_id'] ?? 0,
                    'name' => $display_name,
                    'price' => (float)$unit_price,
                    'image1' => $product['image1'] ?? '',
                    'stock' => (int)($product['stock'] ?? 0)
                ],
                'quantity' => $qty,
                'total' => $item_total,
                'unit' => $unit,
                'box_id' => $box_id,
                'weight' => $weight
            ];
        }
    }
}
?>

<div class="cart-body">
    <?php if (empty($cart_items)): ?>
        <div class="empty-cart">
            <i class="fas fa-shopping-cart"></i>
            <h5>Your cart is empty</h5>
            <p>Add some products to get started!</p>
        </div>
    <?php else: ?>
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <div class="cart-item-info">
                    <div class="cart-item-name"><?= htmlspecialchars($item['product']['name']) ?></div>
                    <div class="cart-item-details">Qty: <?= $item['quantity'] ?></div>
                </div>
                <div class="cart-item-price">₱<?= number_format($item['total'], 2) ?></div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php if (!empty($cart_items)): ?>
    <div class="cart-footer">
        <div class="cart-total">
            Total: ₱<?= number_format($cart_total, 2) ?>
        </div>
        <button class="btn-checkout" onclick="window.location.href='checkout.php'">
            <i class="fas fa-credit-card me-2"></i>
            Proceed to Checkout
        </button>
    </div>
<?php endif; ?>