<?php
// Session already started in cart.php, so we don't need to start it again
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
include 'includes/db.php';
include_once 'includes/cart_manager.php';

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart']) && isset($_SESSION['user_id'])) {
    // Use CartManager to load cart data from database
    $cartManager = new CartManager($pdo);
    $cart_data = $cartManager->loadCartFromDatabase($_SESSION['user_id']);
    
    foreach ($cart_data as $cart_key => $cart_item) {
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $brand_id = $cart_item['brand_id'] ?? null;
        // Ensure brand_id is integer for SQL queries
        if ($brand_id !== null) {
            $brand_id = intval($brand_id);
        }
        $qty = $cart_item['quantity'] ?? 1;
        $unit = $cart_item['unit'] ?? 'kilo';
        $weight = $cart_item['weight'] ?? null;
        
        // Get product basic info
        $product_sql = "SELECT p.product_name, p.product_description,
                        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                       FROM products p 
                       WHERE p.product_id = ? AND p.is_archive = 0";
        $product_stmt = $pdo->prepare($product_sql);
        $product_stmt->execute([$product_id]);
        $product = $product_stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) continue;
        
        // Get brand-specific pricing and stock if brand_id is provided
            if ($brand_id) {
            $brand_sql = "SELECT 
                            b.name as brand_name,
                            pb.unit_cost,
                            pb.quantity_remaining,
                            u.name as unit_name,
                            COALESCE(pp.markup_price, 0) as markup_price,
                            (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price
                         FROM product_batches pb
                         JOIN brands b ON pb.brand_id = b.id
                         JOIN products p ON pb.product_id = p.product_id
                         JOIN uom u ON p.uom_id = u.uom_id
                         LEFT JOIN product_pricing pp ON pb.product_id = pp.product_id
                         WHERE pb.product_id = ? 
                         AND pb.brand_id = ?
                         AND pb.quantity_remaining > 0 
                         AND pb.is_active = 1
                         ORDER BY pb.expiration_date ASC
                         LIMIT 1";
            
            $brand_stmt = $pdo->prepare($brand_sql);
            $brand_stmt->execute([$product_id, $brand_id]);
            $brand_data = $brand_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($brand_data) {
                $unit_price = $brand_data['unit_cost'];
                $markup_price = $brand_data['markup_price'];
                $final_price = $brand_data['final_price'];
                $stock = $brand_data['quantity_remaining'];
                $brand_name = $brand_data['brand_name'];
                $unit_name = $brand_data['unit_name'];
            } else {
                // Fallback to general pricing if brand-specific not found
                $general_sql = "SELECT 
                                  COALESCE(pp.markup_price, 0) + COALESCE((
                                      SELECT pb.unit_cost 
                                      FROM product_batches pb 
                                      WHERE pb.product_id = p.product_id 
                                      AND pb.quantity_remaining > 0 
                                      AND pb.is_active = 1
                                      ORDER BY pb.expiration_date ASC 
                                      LIMIT 1
                                  ), 0) AS final_price,
                                  COALESCE(ps.current_stock, 0) AS stock,
                                  u.name as unit_name
                               FROM products p
                               LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                               LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                               LEFT JOIN uom u ON p.uom_id = u.uom_id
                               WHERE p.product_id = ? AND p.is_archive = 0";
                
                $general_stmt = $pdo->prepare($general_sql);
                $general_stmt->execute([$product_id]);
                $general_data = $general_stmt->fetch(PDO::FETCH_ASSOC);
                
                $unit_price = 0;
                $markup_price = $general_data['final_price'] ?? 0;
                $final_price = $general_data['final_price'] ?? 0;
                $stock = $general_data['stock'] ?? 0;
                $brand_name = 'General';
                $unit_name = 'kilo';
            }
        } else {
            // Use general pricing
            $general_sql = "SELECT 
                              COALESCE(pp.markup_price, 0) + COALESCE((
                                  SELECT pb.unit_cost 
                                  FROM product_batches pb 
                                  WHERE pb.product_id = p.product_id 
                                  AND pb.quantity_remaining > 0 
                                  AND pb.is_active = 1
                                  ORDER BY pb.expiration_date ASC 
                                  LIMIT 1
                              ), 0) AS final_price,
                              COALESCE(ps.current_stock, 0) AS stock,
                              u.name as unit_name
                           FROM products p
                           LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                           LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                           LEFT JOIN uom u ON p.uom_id = u.uom_id
                           WHERE p.product_id = ? AND p.is_archive = 0";
            
            $general_stmt = $pdo->prepare($general_sql);
            $general_stmt->execute([$product_id]);
            $general_data = $general_stmt->fetch(PDO::FETCH_ASSOC);
            
            $unit_price = 0;
            $markup_price = $general_data['final_price'] ?? 0;
            $final_price = $general_data['final_price'] ?? 0;
            $stock = $general_data['stock'] ?? 0;
            $brand_name = 'General';
            $unit_name = 'kilo';
        }
        
        // Calculate item total using final_price (unit_cost + markup_price)
        $item_total = $final_price * $qty;
            $cart_total += $item_total;
            
        // Create display name with brand info
        $display_name = $product['product_name'];
        if ($brand_name && $brand_name !== 'General') {
            $display_name .= " - " . $brand_name;
        }
        
        // Add unit info
            if ($unit === 'piece') {
            $display_name .= " (per piece)";
            } else if ($unit === 'box' && $weight) {
            $display_name .= " (Box - " . number_format($weight, 2) . "kg)";
            } else {
            $display_name .= " (per " . $unit_name . ")";
            }
            
            $cart_items[] = [
                'product' => [
                'id' => $product_id,
                    'name' => $display_name,
                'price' => (float)$final_price, // This is unit_cost + markup_price
                'image1' => $product['image1'] ?? '',
                    'stock' => (float)$stock
                ],
                'quantity' => $qty,
                'total' => $item_total,
            'unit' => $unit_name,
                'weight' => $weight,
                'brand_id' => $brand_id,
                'brand_name' => $brand_name ?? null,
            'cart_key' => $cart_key
            ];
        }
    }

// Store cart total in session
$_SESSION['cart_total'] = $cart_total;
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
                    <?php if ($item['brand_id'] && isset($item['brand_name'])): ?>
                        <div class="item-brand text-primary small">
                            <i class="fas fa-tag me-1"></i>
                            Brand: <?= htmlspecialchars($item['brand_name']) ?>
                        </div>
                    <?php endif; ?>
                    <div class="item-price">₱<?= number_format($item['product']['price'], 2) ?> each</div>
                    <div class="item-stock text-muted small">
                        <i class="fas fa-box me-1"></i>
                        <?= (float)$item['product']['stock'] > 0 ? number_format((float)$item['product']['stock'], 1) . ' in stock' : 'Out of stock' ?>
                    </div>
                </div>
                
                <div class="item-controls">
                    <div class="quantity-controls">
                        <button class="quantity-btn decrease-cart" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>"
                                <?= (float)$item['quantity'] <= 0.1 ? 'disabled' : '' ?>>
                            <i class="fas fa-minus"></i>
                        </button>
                        <input type="number" class="quantity-display quantity-input" value="<?= number_format((float)$item['quantity'], 1, '.', '') ?>" step="0.1" min="0.1" max="<?= (float)$item['product']['stock'] ?>" data-product-id="<?= $item['product']['id'] ?>" data-cart-key="<?= htmlspecialchars($item['cart_key']) ?>" inputmode="decimal" aria-label="Quantity" />
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