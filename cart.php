
<?php
session_start();
include 'includes/db.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle cart actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);

    if ($product_id > 0) {
        switch ($action) {
            case 'add':
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity']++;
                } else {
                    $_SESSION['cart'][$product_id] = ['quantity' => 1];
                }
                break;

            case 'decrease':
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity']--;
                    if ($_SESSION['cart'][$product_id]['quantity'] <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                    }
                }
                break;

            case 'delete':
                unset($_SESSION['cart'][$product_id]);
                break;

            case 'update':
                $quantity = intval($_POST['quantity'] ?? 0);
                if ($quantity > 0) {
                    $_SESSION['cart'][$product_id] = ['quantity' => $quantity];
                } else {
                    unset($_SESSION['cart'][$product_id]);
                }
                break;
        }

        // Calculate updated totals
        $cart_total = 0;
        $cart_qty = count($_SESSION['cart']);
        $item_total = 0;
        $quantity = 0;

        if (isset($_SESSION['cart'][$product_id])) {
            $sql = "SELECT price FROM products WHERE id = :product_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $quantity = $_SESSION['cart'][$product_id]['quantity'];
                $item_total = $product['price'] * $quantity;
            }
        }

        // Calculate total cart value
        foreach ($_SESSION['cart'] as $pid => $cart_item) {
            $sql = "SELECT price FROM products WHERE id = :product_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':product_id', $pid);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $cart_total += $product['price'] * $cart_item['quantity'];
            }
        }

        echo json_encode([
            'success' => true,
            'cart_total' => $cart_total,
            'cart_qty' => $cart_qty,
            'item_total' => $item_total,
            'quantity' => $quantity
        ]);
        exit;
    }
}

// Prepare cart items for display
$cart_items = [];
$cart_total = 0;

if (!empty($_SESSION['cart'])) {
    foreach ($_SESSION['cart'] as $product_id => $cart_item) {
        $sql = "SELECT * FROM products WHERE id = :product_id AND is_archived = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {
            $quantity = $cart_item['quantity'];
            $total = $product['price'] * $quantity;
            $cart_total += $total;

            $cart_items[] = [
                'id' => $product['id'],
                'name' => $product['name'],
                'price' => $product['price'],
                'image1' => $product['image1'],
                'stock' => $product['stock'],
                'quantity' => $quantity,
                'total' => $total
            ];
        }
    }
}

// Get product recommendations
$recommendations = [];
try {
    $sql = "SELECT id, name, price, image1 FROM products WHERE is_archived = 0 ORDER BY RAND() LIMIT 4";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Handle error silently
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Fresh Cart</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #db3030;
            --bs-warning: #ffc107;
            --bs-info: #016bf8;
            --bs-light: #f0f3f2;
            --bs-dark: #001e2b;
        }

        body {
            background: var(--bs-light);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }

        .page-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a01f3a 100%);
            color: white;
            padding: 3rem 0 2rem;
            margin-bottom: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .breadcrumb-nav {
            background: rgba(255,255,255,0.1);
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            margin-top: 1rem;
        }

        .breadcrumb-nav a {
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            transition: color 0.3s;
        }

        .breadcrumb-nav a:hover {
            color: white;
        }

        .cart-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .cart-header {
            background: var(--bs-light);
            padding: 1.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .cart-header h4 {
            color: var(--bs-secondary);
            margin: 0;
            font-weight: 700;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1.5rem;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.3s;
        }

        .cart-item:hover {
            background-color: #fafbfc;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 0.75rem;
            border: 2px solid #e9ecef;
            margin-right: 1.5rem;
        }

        .product-info {
            flex: 1;
        }

        .product-name {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.5rem;
        }

        .product-price {
            font-size: 1rem;
            color: var(--bs-secondary);
            font-weight: 600;
        }

        .stock-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 0 1.5rem;
        }

        .quantity-btn {
            background: var(--bs-light);
            border: 2px solid #e9ecef;
            color: var(--bs-secondary);
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .quantity-btn:hover {
            background: var(--bs-secondary);
            color: white;
            border-color: var(--bs-secondary);
        }

        .quantity-display {
            min-width: 60px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--bs-dark);
        }

        .item-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin: 0 1.5rem;
            min-width: 100px;
            text-align: right;
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--bs-danger);
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
        }

        .remove-btn:hover {
            background: var(--bs-danger);
            color: white;
        }

        .cart-summary {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            position: sticky;
            top: 2rem;
        }

        .summary-title {
            color: var(--bs-secondary);
            font-weight: 700;
            font-size: 1.4rem;
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--bs-light);
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            font-size: 1rem;
        }

        .summary-row .label {
            color: #6c757d;
        }

        .summary-row .value {
            font-weight: 600;
            color: var(--bs-dark);
        }

        .total-row {
            background: var(--bs-light);
            padding: 1rem;
            border-radius: 0.5rem;
            margin: 1.5rem 0;
        }

        .total-amount {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--bs-secondary);
        }

        .checkout-btn {
            background: linear-gradient(135deg, var(--bs-success) 0%, #15a045 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            width: 100%;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(25,135,84,0.3);
        }

        .checkout-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(25,135,84,0.4);
            color: white;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
        }

        .empty-cart i {
            font-size: 4rem;
            color: #dee2e6;
            margin-bottom: 1.5rem;
        }

        .empty-cart h3 {
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .continue-shopping {
            background: var(--bs-secondary);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .continue-shopping:hover {
            background: #6b1429;
            color: white;
            transform: translateY(-2px);
        }

        .recommendations {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }

        .recommendations h3 {
            color: var(--bs-secondary);
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
        }

        .recommendation-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }

        .recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--bs-secondary);
        }

        .recommendation-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .security-info {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            margin-top: 1rem;
        }

        .security-info small {
            color: #6c757d;
        }

        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .product-image {
                margin-right: 0;
            }

            .quantity-controls,
            .item-total {
                margin: 0;
            }

            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="page-header">
        <div class="container">
            <h1 class="page-title">
                <i class="fas fa-shopping-cart me-3"></i>Shopping Cart
            </h1>
            <p class="lead mb-0">Review your items and proceed to checkout</p>
            <div class="breadcrumb-nav">
                <a href="index.php">Home</a> / <a href="product.php">Products</a> / <span class="text-white">Cart</span>
            </div>
        </div>
    </div>

    <div class="container">
        <div class="row g-4">
            <!-- Cart Items -->
            <div class="col-lg-8">
                <?php if (empty($cart_items)): ?>
                    <div class="cart-container">
                        <div class="empty-cart">
                            <i class="fas fa-shopping-cart"></i>
                            <h3>Your cart is empty</h3>
                            <p class="mb-4 text-muted">Looks like you haven't added anything to your cart yet.</p>
                            <a href="product.php" class="continue-shopping">
                                <i class="fas fa-arrow-left me-2"></i>Continue Shopping
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="cart-container">
                        <div class="cart-header">
                            <h4><i class="fas fa-list me-2"></i>Cart Items (<?= count($cart_items) ?>)</h4>
                        </div>
                        
                        <?php foreach ($cart_items as $item): ?>
                            <div class="cart-item" data-product-id="<?= $item['id'] ?>">
                                <img src="admin/<?= htmlspecialchars($item['image1']) ?>" 
                                     alt="<?= htmlspecialchars($item['name']) ?>" 
                                     class="product-image">
                                
                                <div class="product-info">
                                    <h5 class="product-name"><?= htmlspecialchars($item['name']) ?></h5>
                                    <div class="product-price">₱<?= number_format($item['price'], 2) ?></div>
                                    <div class="stock-info">
                                        <i class="fas fa-box me-1"></i>
                                        <?= $item['stock'] > 0 ? $item['stock'] . ' in stock' : 'Out of stock' ?>
                                    </div>
                                </div>
                                
                                <div class="quantity-controls">
                                    <button class="quantity-btn decrease" data-product-id="<?= $item['id'] ?>">
                                        <i class="fas fa-minus"></i>
                                    </button>
                                    <span class="quantity-display cart-qty" data-product-id="<?= $item['id'] ?>" data-stock="<?= $item['stock'] ?>"><?= $item['quantity'] ?></span>
                                    <button class="quantity-btn increase" data-product-id="<?= $item['id'] ?>">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </div>
                                
                                <div class="item-total product-price">₱<?= number_format($item['total'], 2) ?></div>
                                
                                <button class="remove-btn remove-item" data-product-id="<?= $item['id'] ?>" title="Remove Item">
                                    <i class="fas fa-trash-alt"></i>
                                </button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Cart Summary -->
            <div class="col-lg-4">
                <?php if (!empty($cart_items)): ?>
                    <div class="cart-summary">
                        <h3 class="summary-title">
                            <i class="fas fa-calculator me-2"></i>Order Summary
                        </h3>

                        <div class="summary-row">
                            <span class="label">Subtotal:</span>
                            <span class="value">₱<?= number_format($cart_total, 2) ?></span>
                        </div>

                        <div class="summary-row">
                            <span class="label">Shipping:</span>
                            <span class="value text-success fw-bold">FREE</span>
                        </div>

                        <div class="summary-row">
                            <span class="label">Tax:</span>
                            <span class="value">₱0.00</span>
                        </div>

                        <div class="total-row">
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="h5 mb-0">Total:</span>
                                <span class="total-amount">₱<?= number_format($cart_total, 2) ?></span>
                            </div>
                        </div>

                        <button class="checkout-btn checkout-btn-action">
                            <i class="fas fa-lock me-2"></i>Secure Checkout
                        </button>

                        <div class="security-info">
                            <small>
                                <i class="fas fa-shield-alt me-1"></i>
                                SSL Secure Checkout • Your information is protected
                            </small>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recommendations -->
        <?php if (!empty($recommendations)): ?>
            <div class="recommendations mt-5">
                <h3>
                    <i class="fas fa-heart me-2"></i>You Might Also Like
                </h3>
                <div class="row g-4">
                    <?php foreach ($recommendations as $product): ?>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="recommendation-card">
                                <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'admin/uploads/placeholder.jpg' ?>"
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="recommendation-image">
                                <h6 class="product-name"><?= htmlspecialchars($product['name']) ?></h6>
                                <div class="product-price mb-3">₱<?= number_format($product['price'], 2) ?></div>
                                <a href="product.php?id=<?= $product['id'] ?>" class="btn btn-outline-secondary btn-sm">
                                    <i class="fas fa-eye me-1"></i>View Product
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Quantity control functions
        document.querySelectorAll('.increase').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                updateQuantity(productId, 'add');
            });
        });

        document.querySelectorAll('.decrease').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                updateQuantity(productId, 'decrease');
            });
        });

        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to remove this item from your cart?')) {
                    const productId = this.dataset.productId;
                    removeItem(productId);
                }
            });
        });

        function updateQuantity(productId, action) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', action);
            formData.append('csrf_token', '<?= $csrf_token ?>');

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const qtySpan = document.querySelector(`.cart-qty[data-product-id="${productId}"]`);
                    if (qtySpan) {
                        if (data.quantity > 0) {
                            qtySpan.textContent = data.quantity;
                            const itemTotalElement = qtySpan.closest('.cart-item').querySelector('.item-total');
                            if (itemTotalElement) {
                                itemTotalElement.textContent = '₱' + Number(data.item_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                            }
                        } else {
                            const row = qtySpan.closest('.cart-item');
                            if (row) row.remove();
                        }
                    }
                    
                    document.querySelectorAll('.total-amount').forEach(el => {
                        el.textContent = '₱' + Number(data.cart_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    });
                    
                    if (data.cart_qty === 0) {
                        location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        function removeItem(productId) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'delete');
            formData.append('csrf_token', '<?= $csrf_token ?>');

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const qtySpan = document.querySelector(`.cart-qty[data-product-id="${productId}"]`);
                    if (qtySpan) {
                        const row = qtySpan.closest('.cart-item');
                        if (row) row.remove();
                    }
                    
                    document.querySelectorAll('.total-amount').forEach(el => {
                        el.textContent = '₱' + Number(data.cart_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    });
                    
                    if (data.cart_qty === 0) {
                        location.reload();
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('An error occurred. Please try again.');
            });
        }

        // Checkout validation
        const checkoutBtn = document.querySelector('.checkout-btn-action');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function(e) {
                let valid = true;
                let message = '';

                document.querySelectorAll('.cart-qty').forEach(span => {
                    const qty = parseInt(span.textContent.trim(), 10);
                    const stock = parseInt(span.getAttribute('data-stock'), 10);
                    const productId = span.getAttribute('data-product-id');

                    if (!isNaN(stock) && qty > stock) {
                        valid = false;
                        message += `Product ID ${productId}: Quantity (${qty}) exceeds stock (${stock})\n`;
                    }
                });

                if (!valid) {
                    alert("Some items in your cart exceed available stock and cannot be checked out.\n\n" + message);
                    e.preventDefault();
                    return;
                }

                window.location.href = 'checkout.php';
            });
        }

        // Add loading states
        document.querySelectorAll('.quantity-btn, .remove-btn').forEach(button => {
            button.addEventListener('click', function() {
                this.disabled = true;
                setTimeout(() => {
                    this.disabled = false;
                }, 1000);
            });
        });

        // Update cart badge in navbar
        function updateCartBadge() {
            fetch('cart_count.php')
                .then(response => response.text())
                .then(count => {
                    const badge = document.querySelector('.cart-badge');
                    if (badge) {
                        badge.textContent = count;
                        badge.style.display = count > 0 ? 'block' : 'none';
                    }
                });
        }

        // Update cart badge on page load
        updateCartBadge();
    </script>
</body>
</html>
