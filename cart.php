<?php
session_start();
include 'includes/db.php';
include_once 'includes/cart_manager.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Helper function to sync cart to database
function syncCartToDatabase($pdo, $session_cart) {
    if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
        try {
        $cartManager = new CartManager($pdo);
        $cartManager->saveCartToDatabase($_SESSION['user_id'], $session_cart);
        } catch (Exception $e) {
            error_log("Cart sync error: " . $e->getMessage());
            // Don't fail the cart operation if sync fails
        }
    }
}

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Load and merge cart from database if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role']) && $_SESSION['role'] === 'customer') {
    // Only merge if we haven't already done it in this session
    if (!isset($_SESSION['cart_merged'])) {
        $cartManager = new CartManager($pdo);
        
        // Get current session cart
        $session_cart = $_SESSION['cart'];
        
        // Load cart from database and merge
        $merged_cart = $cartManager->mergeCarts($_SESSION['user_id'], $session_cart);
        
        // Update session with merged cart
        $_SESSION['cart'] = $merged_cart;
        
        // Save merged cart to database if there are changes
        if ($merged_cart !== $session_cart) {
            $cartManager->saveCartToDatabase($_SESSION['user_id'], $merged_cart);
        }
        
        // Mark as merged to prevent multiple merges
        $_SESSION['cart_merged'] = true;
    }
}

// Handle AJAX requests for cart operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        $action = $_POST['action'];
        $product_id = intval($_POST['product_id'] ?? 0);
        $cart_key = $_POST['cart_key'] ?? null;
        $brand_id = $_POST['brand_id'] ?? null;
        $batch_id = $_POST['batch_id'] ?? null;
        
        // CSRF protection
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $csrf_token) {
            echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
            exit;
        }
        
        // Check if user is logged in
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'customer') {
            echo json_encode(['success' => false, 'message' => 'Please log in to add items to cart']);
            exit;
        }
        
        if ($product_id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
            exit;
        }
    
    // Get product data with brand-specific pricing
    if ($brand_id) {
        $product_sql = "SELECT p.product_name, p.product_description,
                        b.name as brand_name,
                        pb.unit_cost,
                        pb.quantity_remaining,
                        u.name as unit_name,
                        COALESCE(pp.markup_price, 0) as markup_price,
                        (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as price
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
        
        $stmt = $pdo->prepare($product_sql);
        $stmt->execute([$product_id, $brand_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$product) {
            // Fallback to general pricing if brand-specific not found
            $product_sql = "SELECT p.product_name, p.product_description,
                            COALESCE(pp.markup_price, 0) + COALESCE((
                                SELECT pb.unit_cost 
                                FROM product_batches pb 
                                WHERE pb.product_id = p.product_id 
                                AND pb.quantity_remaining > 0 
                                AND pb.is_active = 1
                                ORDER BY pb.expiration_date ASC 
                                LIMIT 1
                            ), 0) AS price,
                            COALESCE(ps.current_stock, 0) AS stock
                        FROM products p
                        LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
                        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
                           WHERE p.product_id = ? AND p.is_archive = 0";
            
            $stmt = $pdo->prepare($product_sql);
            $stmt->execute([$product_id]);
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
        }
    } else {
        $product_sql = "SELECT p.product_name, p.product_description,
                        COALESCE(pp.markup_price, 0) + COALESCE((
                            SELECT pb.unit_cost 
                            FROM product_batches pb 
                            WHERE pb.product_id = p.product_id 
                            AND pb.quantity_remaining > 0 
                            AND pb.is_active = 1
                            ORDER BY pb.expiration_date ASC 
                            LIMIT 1
                        ), 0) AS price,
                        COALESCE(ps.current_stock, 0) AS stock
                    FROM products p
                    LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
                    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
                       WHERE p.product_id = ? AND p.is_archive = 0";
        
        $stmt = $pdo->prepare($product_sql);
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    if (!$product) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        switch ($action) {
            case 'add':
                // For increase button, add 0.1 to existing quantity
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                    $new_quantity = $_SESSION['cart'][$cart_key]['quantity'] + 0.1;
                    
                    // Check stock availability
                    $stock_available = true;
                    if ($brand_id) {
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(quantity_remaining), 0) as stock 
                            FROM product_batches 
                            WHERE product_id = ? AND brand_id = ? AND is_active = 1 AND quantity_remaining > 0
                        ");
                        $stmt->execute([$product_id, $brand_id]);
                        $brand_stock = $stmt->fetch(PDO::FETCH_ASSOC)['stock'];
                        $stock_available = $new_quantity <= $brand_stock;
                    } else {
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(quantity_remaining), 0) as stock 
                            FROM product_batches 
                            WHERE product_id = ? AND is_active = 1 AND quantity_remaining > 0
                        ");
                        $stmt->execute([$product_id]);
                        $total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['stock'];
                        $stock_available = $new_quantity <= $total_stock;
                    }
                    
                    if (!$stock_available) {
                        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                        exit;
                    }
                    
                    $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
                    
                    // Sync to database
                    syncCartToDatabase($pdo, $_SESSION['cart']);
                    
                    // Calculate fresh price for this item
                    $fresh_price = floatval($product['price']);
                    $item_total = $new_quantity * $fresh_price;
                    
                    echo json_encode([
                        'success' => true,
                        'quantity' => $new_quantity,
                        'item_total' => $item_total,
                        'cart_total' => $item_total, // This will be recalculated by cart_content.php
                        'cart_qty' => array_sum(array_column($_SESSION['cart'], 'quantity'))
                    ]);
                } else {
                    // For new items, add with default quantity
                    $quantity = floatval($_POST['quantity'] ?? 1);
                    $unit = $_POST['unit'] ?? 'kilo';
                    $unit_price = floatval($product['price']);
                    
                    // Create unique cart key
                    $new_cart_key = $product_id . '_' . $unit;
                    if ($brand_id) {
                        $new_cart_key .= '_brand_' . $brand_id;
                    }
                    if ($batch_id) {
                        $new_cart_key .= '_batch_' . $batch_id;
                    }
                    
                    $final_cart_key = $cart_key ?: $new_cart_key;
                    
                    // Check stock availability
                    $stock_available = true;
                    if ($brand_id) {
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(quantity_remaining), 0) as stock 
                            FROM product_batches 
                            WHERE product_id = ? AND brand_id = ? AND is_active = 1 AND quantity_remaining > 0
                        ");
                        $stmt->execute([$product_id, $brand_id]);
                        $brand_stock = $stmt->fetch(PDO::FETCH_ASSOC)['stock'];
                        $stock_available = $quantity <= $brand_stock;
                    } else {
                        $stmt = $pdo->prepare("
                            SELECT COALESCE(SUM(quantity_remaining), 0) as stock 
                            FROM product_batches 
                            WHERE product_id = ? AND is_active = 1 AND quantity_remaining > 0
                        ");
                        $stmt->execute([$product_id]);
                        $total_stock = $stmt->fetch(PDO::FETCH_ASSOC)['stock'];
                        $stock_available = $quantity <= $total_stock;
                    }
                    
                    if (!$stock_available) {
                        echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                        exit;
                    }
                    
                    // Add to cart
                    if (isset($_SESSION['cart'][$final_cart_key])) {
                        $_SESSION['cart'][$final_cart_key]['quantity'] += $quantity;
                    } else {
                        $_SESSION['cart'][$final_cart_key] = [
                            'product_id' => $product_id,
                            'quantity' => $quantity,
                            'unit' => $unit,
                            'unit_price' => $unit_price,
                            'brand_id' => $brand_id,
                            'batch_id' => $batch_id
                        ];
                    }
                    
                    // Sync to database
                    syncCartToDatabase($pdo, $_SESSION['cart']);
                    
                    echo json_encode([
                        'success' => true,
                        'quantity' => $_SESSION['cart'][$final_cart_key]['quantity'],
                        'item_total' => $_SESSION['cart'][$final_cart_key]['quantity'] * $unit_price,
                        'cart_total' => array_sum(array_map(function($item) { return $item['quantity'] * $item['unit_price']; }, $_SESSION['cart'])),
                        'cart_qty' => array_sum(array_column($_SESSION['cart'], 'quantity'))
                    ]);
                }
            exit;

            case 'decrease':
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                    $new_quantity = $_SESSION['cart'][$cart_key]['quantity'] - 0.1;
                    if ($new_quantity < 0.1) {
                        echo json_encode(['success' => false, 'message' => 'Minimum quantity is 0.1']);
                        exit;
                    }
                    $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
                
                // Sync to database
                syncCartToDatabase($pdo, $_SESSION['cart']);
                
                echo json_encode([
                    'success' => true,
                    'quantity' => $new_quantity,
                    'item_total' => $new_quantity * $_SESSION['cart'][$cart_key]['unit_price'],
                    'cart_total' => array_sum(array_map(function($item) { return $item['quantity'] * $item['unit_price']; }, $_SESSION['cart'])),
                    'cart_qty' => array_sum(array_column($_SESSION['cart'], 'quantity'))
                ]);
                } else {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            }
                                exit;

            case 'delete':
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                    unset($_SESSION['cart'][$cart_key]);
                
                // Sync to database
                syncCartToDatabase($pdo, $_SESSION['cart']);
                
                echo json_encode([
                    'success' => true,
                    'cart_total' => array_sum(array_map(function($item) { return $item['quantity'] * $item['unit_price']; }, $_SESSION['cart'])),
                    'cart_qty' => array_sum(array_column($_SESSION['cart'], 'quantity'))
                ]);
                } else {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
                }
            exit;

            case 'set_quantity':
                $quantity = max(0.1, floatval($_POST['quantity'] ?? 1));
                
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                        $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
                
                // Sync to database
                syncCartToDatabase($pdo, $_SESSION['cart']);
        
        echo json_encode([
            'success' => true,
                    'quantity' => $quantity,
                    'item_total' => $quantity * $_SESSION['cart'][$cart_key]['unit_price'],
                    'cart_total' => array_sum(array_map(function($item) { return $item['quantity'] * $item['unit_price']; }, $_SESSION['cart'])),
                    'cart_qty' => array_sum(array_column($_SESSION['cart'], 'quantity'))
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'Cart item not found']);
            }
            exit;
    }
    } catch (Exception $e) {
        error_log("Cart AJAX error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'An error occurred while processing your request']);
        exit;
    }
}

// Use cart_content.php for unified cart display
ob_start();
include 'cart_content.php';
$cart_content_html = ob_get_clean();

// Initialize cart total - will be updated via JavaScript using cart_total.php
$cart_total = 0;
?>

<?php
$page_title = 'Shopping Cart - MikeMadz';
$page_description = 'Review your selected items and proceed to checkout. Fresh meat and seafood delivered to your doorstep.';
$page_keywords = 'shopping cart, checkout, meat delivery, seafood delivery, MikeMadz';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/user_head.php'; ?>
    
    <style>
        :root {
            --bs-primary: #7F1734;
            --bs-secondary: #a91d42;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
            --brand-primary: #7F1734;
            --brand-secondary: #a91d42;
            --brand-gradient: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            --brand-light: #f8f9fa;
        }

        * {
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
            color: var(--bs-dark);
        }

        .cart-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .main-cart-header {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            margin-bottom: 2rem;
            text-align: center;
        }

        .main-cart-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--brand-primary);
            margin: 0;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .empty-cart {
            background: white;
            border-radius: 1.5rem;
            padding: 4rem 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
        }
        
        .empty-cart i {
            font-size: 4rem;
            color: var(--bs-secondary);
            margin-bottom: 2rem;
            opacity: 0.7;
        }
        
        .empty-cart h3 {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 1rem;
        }

        .empty-cart p {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 2rem;
        }
        
        .continue-shopping {
            background: var(--brand-gradient);
            color: white;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            text-decoration: none;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }

        .continue-shopping:hover {
            background: linear-gradient(135deg, #6b1429 0%, #8b1a3a 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 23, 52, 0.4);
        }
        
        .cart-total-section {
            background: white;
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            text-align: center;
        }
        
        .total-display {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 400px;
            margin: 0 auto;
        }
        
        .total-label {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--bs-dark);
            margin: 0;
        }
        
        .total-amount {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--brand-primary);
            margin: 0;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .cart-container {
                padding: 1rem;
            }

            .main-cart-header {
                padding: 1.5rem;
            }

            .main-cart-header h1 {
                font-size: 2rem;
            }
            
            .empty-cart {
                padding: 3rem 1.5rem;
            }
            
            .empty-cart i {
                font-size: 3rem;
            }
            
            .empty-cart h3 {
                font-size: 1.5rem;
            }
            
            .cart-total-section {
                padding: 1.5rem;
            }
            
            .total-display {
                flex-direction: column;
                gap: 1rem;
            }
            
            .total-label {
                font-size: 1.3rem;
            }
            
            .total-amount {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
<?php include 'includes/user_promo.php'; ?>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="cart-container">
        <!-- Alert Container -->
        <div id="alert-container"></div>

        <?php if (empty($_SESSION['cart'])): ?>
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p class="mb-4">Add some products to your cart before checking out.</p>
                    <a href="product.php" class="continue-shopping">
                        <i class="fas me-2"></i>Continue Shopping
                    </a>
            </div>
        <?php else: ?>
                <div class="main-cart-header">
                    <h1><i class="fas fa-shopping-cart me-3"></i>Shopping Cart</h1>
                </div>

            <!-- Use unified cart content -->
            <?= $cart_content_html ?>
            
            <!-- Cart Total Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="cart-total-section">
                        <div class="total-display">
                            <h3 class="total-label">Total:</h3>
                            <h2 class="total-amount" id="cart-total-display">₱0.00</h2>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Checkout Section -->
            <div class="row mt-3">
                <div class="col-12 text-center">
                    <a href="checkout.php" class="btn btn-success btn-lg">
                                <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                    </a>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Load cart total from cart_total.php when page loads
        document.addEventListener('DOMContentLoaded', function() {
            updateCartTotal();
        });

        function updateCartTotal() {
            fetch('cart_total.php')
                .then(response => response.json())
                .then(data => {
                    const cartTotalDisplay = document.getElementById('cart-total-display');
                    if (cartTotalDisplay && data.total !== undefined) {
                        cartTotalDisplay.textContent = '₱' + data.total.toFixed(2);
                    }
                })
                .catch(error => {
                    console.error('Error loading cart total:', error);
                });
        }

        // updateCartQuantity function is defined in user_navbar.php to avoid duplication

        function updateCartQuantityExact(productId, quantity, cartKey = null) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'set_quantity');
            formData.append('quantity', quantity);
            if (cartKey) {
                formData.append('cart_key', cartKey);
            }
            formData.append('csrf_token', '<?= $csrf_token ?>');

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                if (data.success) {
                    // Update cart total and reload the page to show updated cart
                    updateCartTotal();
                    setTimeout(() => location.reload(), 100);
                } else {
                    console.error('Cart update failed:', data.message || 'Unknown error');
                    Swal.fire({
                        title: 'Error',
                        text: data.message || 'Failed to update cart',
                        icon: 'error',
                        confirmButtonColor: '#dc3545',
                        confirmButtonText: 'OK'
                    });
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Connection Error',
                    text: 'Unable to update cart. Please check your connection and try again.',
                    icon: 'error',
                    confirmButtonColor: '#dc3545',
                    confirmButtonText: 'OK'
                });
            });
        }

        // Event delegation for cart interactions - only handle remove items here
        // Increase/decrease buttons are handled by user_navbar.php to avoid duplicate execution
        document.addEventListener('click', function(e) {
            if (e.target.closest('.remove-cart-item')) {
                e.preventDefault();
                const button = e.target.closest('.remove-cart-item');
                const productId = button.getAttribute('data-product-id');
                const cartKey = button.getAttribute('data-cart-key');
                
                Swal.fire({
                    title: 'Confirm Removal',
                    text: 'Are you sure you want to remove this item from your cart?',
                    icon: 'question',
                    showCancelButton: true,
                    confirmButtonColor: '#198754',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: 'Yes, remove it',
                    cancelButtonText: 'Cancel'
                }).then((result) => {
                    if (result.isConfirmed) {
                        updateCartQuantity(productId, 'delete', cartKey);
                    }
                });
            }
        });

        // Handle direct quantity input changes
        document.addEventListener('change', function(e) {
            const input = e.target.closest('.quantity-input');
            if (!input) return;
            const productId = input.getAttribute('data-product-id');
            const cartKey = input.getAttribute('data-cart-key');
            let value = parseFloat(input.value);
            const min = 0.1;
            const max = input.getAttribute('max') ? parseFloat(input.getAttribute('max')) : Number.POSITIVE_INFINITY;
            if (isNaN(value)) value = min;
            value = Math.max(min, Math.min(max, value));
            value = Math.round(value * 10) / 10;
            input.value = value.toFixed(1);
            updateCartQuantityExact(productId, value, cartKey);
        });
    </script>
</body>
</html>
