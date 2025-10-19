<?php
if (session_status() === PHP_SESSION_NONE) session_start();

// Include DB connection
include_once 'includes/db.php';
include_once 'includes/cart_manager.php';

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Calculate cart total
$cart_total = 0;
$cart_count = 0;

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
        
        // Get brand-specific price if available, otherwise use general price
        if ($brand_id) {
            $sql = "SELECT 
                        pb.unit_cost,
                        COALESCE(pp.markup_price, 0) as markup_price,
                        (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price
                    FROM product_batches pb
                    LEFT JOIN product_pricing pp ON pb.product_id = pp.product_id
                    WHERE pb.product_id = ? 
                    AND pb.brand_id = ?
                    AND pb.quantity_remaining > 0 
                    AND pb.is_active = 1
                    ORDER BY pb.received_date DESC, pb.batch_id DESC
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$product_id, $brand_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $price = $product['final_price'];
            } else {
                // Fallback to general price if brand-specific not found
                $sql = "SELECT COALESCE(pp.markup_price, 0) + COALESCE((
                            SELECT pb.unit_cost 
                            FROM product_batches pb 
                            WHERE pb.product_id = p.product_id 
                            AND pb.quantity_remaining > 0 
                            AND pb.is_active = 1
                            ORDER BY pb.received_date DESC, pb.batch_id DESC 
                            LIMIT 1
                        ), 0) AS final_price
                        FROM products p
                        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                        WHERE p.product_id = ? AND p.is_archive = 0";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$product_id]);
                $product = $stmt->fetch(PDO::FETCH_ASSOC);
                $price = $product ? $product['final_price'] : 0;
            }
        } else {
            // Use general price calculation
            $sql = "SELECT COALESCE(pp.markup_price, 0) + COALESCE((
                        SELECT pb.unit_cost 
                        FROM product_batches pb 
                        WHERE pb.product_id = p.product_id 
                        AND pb.quantity_remaining > 0 
                        AND pb.is_active = 1
                        ORDER BY pb.received_date DESC, pb.batch_id DESC 
                        LIMIT 1
                    ), 0) AS final_price
                    FROM products p
                    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                    WHERE p.product_id = ? AND p.is_archive = 0";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            $price = $product ? $product['final_price'] : 0;
        }

        if ($price > 0) {
            $quantity = $cart_item['quantity'] ?? 1;
            $cart_total += $price * $quantity;
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