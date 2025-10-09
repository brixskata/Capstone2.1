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
                    COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) AS price,
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
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $product_data = getProductData($pdo, [$product_id]);
        if (!empty($product_data[$product_id])) {
            $product = $product_data[$product_id];
            $qty = $cart_item['quantity'] ?? 1;
            $unit_price = $product['price']; // Always use current database price
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
                    'stock' => (float)($product['stock'] ?? 0)
                ],
                'quantity' => $qty,
                'total' => $item_total,
                'unit' => $unit,
                'box_id' => $box_id,
                'weight' => $weight,
                'cart_key' => $cart_key  // Include cart key for proper identification
            ];
        }
    }
}
?>

<?php if (empty($cart_items)): ?>
    <div class="empty-cart text-center py-5">
        <i class="fas fa-shopping-cart text-muted" style="font-size: 3rem; margin-bottom: 1rem;"></i>
        <h5 class="text-muted">Your cart is empty</h5>
        <p class="text-muted small">Add some products to get started!</p>
        <button class="btn btn-outline-secondary btn-sm" onclick="closeCart(); window.location.href='product.php'">
            <i class="fas fa-shopping-bag me-1"></i>Browse Products
        </button>
    </div>
<?php else: ?>
    <div class="cart-items-container">
        <?php foreach ($cart_items as $item): ?>
            <div class="cart-item-sliding" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>">
                <div class="item-image">
                    <img src="<?= !empty($item['product']['image1']) ? 'admin/' . htmlspecialchars($item['product']['image1']) : 'images/placeholder.jpg' ?>" 
                         alt="<?= htmlspecialchars($item['product']['name']) ?>" 
                         class="cart-item-img"
                         onerror="this.src='images/placeholder.jpg'">
                </div>
                
                <div class="item-details">
                    <div class="item-name"><?= htmlspecialchars($item['product']['name']) ?></div>
                    <div class="item-price">₱<?= number_format($item['product']['price'], 2) ?> each</div>
                    <div class="item-stock text-muted small">
                        <i class="fas fa-box me-1"></i>
                        <?= (float)$item['product']['stock'] > 0 ? number_format((float)$item['product']['stock'], 1) . ' in stock' : 'Out of stock' ?>
                    </div>
                </div>
                
                <div class="item-controls">
                    <div class="quantity-controls">
                        <button class="quantity-btn decrease-cart" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>"
                                <?= (float)$item['quantity'] <= 1 ? 'disabled' : '' ?>>
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-display quantity-input" value="<?= number_format((float)$item['quantity'], 1, '.', '') ?>" step="0.1" min="1" max="<?= (float)$item['product']['stock'] ?>" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>" inputmode="decimal" aria-label="Quantity" />
                        <button class="quantity-btn increase-cart" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>"
                                <?= (float)$item['quantity'] >= (float)$item['product']['stock'] ? 'disabled' : '' ?>>
                            <i class="fas fa-plus"></i>
                        </button>
                    </div>
                    
                    <div class="item-total">₱<?= number_format($item['total'], 2) ?></div>
                    
                    <button class="remove-cart-item" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>" title="Remove Item">
                        <i class="fas fa-trash-alt"></i>
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>