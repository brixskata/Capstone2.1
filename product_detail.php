<?php
session_start();
include 'includes/db.php';

$product = null;
$error = '';

// Get product ID from URL and clean it
$raw_id = isset($_GET['id']) ? $_GET['id'] : '';
$product_id = 0;

// Debug: Log the raw ID received
error_log("Raw ID received: " . $raw_id);

// Extract numeric part from ID (in case it has suffix like "4_kilo")
if (preg_match('/^(\d+)/', $raw_id, $matches)) {
    $product_id = (int)$matches[1];
    error_log("Extracted product ID: " . $product_id);
    error_log("Product ID type: " . gettype($product_id));
} else {
    // If no numeric part found, try to convert directly
    $product_id = (int)$raw_id;
    error_log("Direct conversion product ID: " . $product_id);
}

// Additional debugging
error_log("Final product_id before query: " . $product_id);
error_log("Final product_id type: " . gettype($product_id));

// Debug: Check if product_id is being modified
$debug_product_id = $product_id;

if ($product_id <= 0) {
    $error = 'Invalid product ID';
} else {
    try {
        // Debug: Log the product ID being searched
        error_log("Searching for product ID: " . $product_id);
        
        // Deterministic product query: use correlated subqueries to get latest stock/price to avoid duplicate join rows affecting stock display
        $stmt = $pdo->prepare("
            SELECT 
                p.product_id as db_product_id,
                p.product_name,
                p.product_description,
                c.category_name,
                COALESCE((
                    SELECT ps.current_stock
                    FROM product_stock ps
                    WHERE ps.product_id = p.product_id
                    ORDER BY ps.last_restock_date DESC, ps.productstock_id DESC
                    LIMIT 1
                ), 0) as current_stock,
                COALESCE((
                    SELECT pp.selling_price
                    FROM product_pricing pp
                    WHERE pp.product_id = p.product_id
                    ORDER BY pp.productpricing_id DESC
                    LIMIT 1
                ), 0) as selling_price
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            WHERE p.product_id = ? AND p.is_archive = 0
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Ensure product_id remains as our parsed value
        if ($product) {
            $product['product_id'] = $product_id;
        }

        // Debug: Log the product data found
        error_log("Product data found: " . print_r($product, true));
        error_log("Product ID after query: " . $product_id);
        error_log("Product ID type after query: " . gettype($product_id));

        if (!$product) {
            $error = 'Product not found or has been archived';
        } else {
            // Get image separately if product exists
            $img_stmt = $pdo->prepare("SELECT image_url FROM product_images WHERE product_id = ? AND is_primary = 1 LIMIT 1");
            $img_stmt->execute([$product_id]);
            $image_result = $img_stmt->fetch(PDO::FETCH_ASSOC);
            $product['primary_image'] = $image_result ? $image_result['image_url'] : null;
            
            // Debug: Log final product data
            error_log("Final product data: " . print_r($product, true));
            error_log("Product ID after image query: " . $product_id);
            error_log("Product ID type after image query: " . gettype($product_id));
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
        error_log("Database error: " . $e->getMessage());
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? htmlspecialchars($product['product_name']) . ' - MikeMadz' : 'Product Not Found' ?></title>
    <link rel="icon" type="image/png" href="favicon.png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
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
        
        .product-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(127, 23, 52, 0.1);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--brand-gradient);
        }
        
        .product-image {
            width: 100%;
            max-width: 500px;
            height: 450px;
            object-fit: cover;
            border-radius: 1.25rem;
            display: block;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .product-image:hover {
            transform: scale(1.02);
        }
        
        .product-title {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--brand-primary);
            margin-bottom: 1rem;
            line-height: 1.2;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .product-category {
            color: #6c757d;
            font-size: 1.2rem;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .product-category i {
            color: var(--brand-primary);
        }
        
        .product-description {
            font-size: 1.2rem;
            line-height: 1.7;
            color: #495057;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
            border-radius: 1rem;
            border-left: 4px solid var(--brand-primary);
        }
        
        .price-section {
            background: var(--brand-gradient);
            color: white;
            padding: 2rem;
            border-radius: 1.25rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(127, 23, 52, 0.3);
        }

        .price-section::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 100%;
            height: 100%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .price {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }
        
        .unit {
            font-size: 1.2rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .stock-info {
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            color: var(--bs-success);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid rgba(25, 135, 84, 0.2);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stock-info i {
            font-size: 1.3rem;
        }

        .purchase-options {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(127, 23, 52, 0.1);
        }

        .purchase-options::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--brand-gradient);
        }

        .option-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--brand-primary);
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .btn-add-cart {
            background: var(--brand-gradient);
            color: white;
            border: none;
            padding: 1.25rem 2rem;
            border-radius: 1rem;
            font-size: 1.2rem;
            font-weight: 700;
            width: 100%;
            margin-top: 1.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-add-cart::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-add-cart:hover::before {
            left: 100%;
        }
        
        .btn-add-cart:hover {
            background: linear-gradient(135deg, #6b1429 0%, #8b1a3a 100%);
            color: white;
            transform: translateY(-3px);
            box-shadow: 0 12px 35px rgba(127, 23, 52, 0.4);
        }
        
        .btn-add-cart:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .btn-add-cart:disabled::before {
            display: none;
        }
        
        .btn-back {
            background: var(--brand-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
            position: relative;
            overflow: hidden;
        }

        .btn-back::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-back:hover::before {
            left: 100%;
        }
        
        .btn-back:hover {
            background: linear-gradient(135deg, #6b1429 0%, #8b1a3a 100%);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 23, 52, 0.4);
        }

        .quantity-section {
            margin-bottom: 2rem;
        }

        .quantity-label {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 1rem;
            display: block;
        }
        
        .quantity-input {
            width: 120px;
            padding: 1rem;
            border: 3px solid #e9ecef;
            border-radius: 0.75rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            margin-right: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .quantity-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            transform: scale(1.05);
        }

        .badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 700;
        }

        .badge.bg-danger {
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%) !important;
        }

        .badge.bg-warning {
            background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%) !important;
            color: #212529 !important;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .product-container {
                padding: 1rem;
            }

            .product-card, .purchase-options {
                padding: 1.5rem;
            }

            .product-title {
                font-size: 2rem;
            }

            .product-image {
                height: 300px;
            }

            .price {
                font-size: 2rem;
            }

            .quantity-input {
                width: 100px;
                padding: 0.75rem;
            }
        }

        @media (max-width: 576px) {
            .product-card, .purchase-options {
                padding: 1rem;
            }

            .product-title {
                font-size: 1.75rem;
            }

            .product-image {
                height: 250px;
            }

            .btn-add-cart {
                padding: 1rem 1.5rem;
                font-size: 1.1rem;
            }
        }

        /* Loading Animation */
        .btn-add-cart.loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .btn-add-cart.loading::after {
            content: '';
            position: absolute;
            width: 20px;
            height: 20px;
            top: 50%;
            left: 50%;
            margin-left: -10px;
            margin-top: -10px;
            border: 2px solid transparent;
            border-top: 2px solid white;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
   <?php include 'includes/user_promo.php'; ?>

    <?php (function(){ include 'includes/user_navbar.php'; })(); ?>

    <div class="product-container">
        <button class="btn-back" onclick="history.back()">
            <i class="fas fa-arrow-left me-2"></i>Back to Products
        </button>

        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($product): ?>
            <!-- Product Meta -->
            <?php 
            // Fetch product rating (average and count) and total sold
            $avg_rating = 0; $rating_count = 0; $total_sold = 0;
            try {
                // Average rating and count from order_ratings joined to orders and order_items for this product
                $ratingStmt = $pdo->prepare("
                    SELECT 
                        AVG(orate.rating) AS avg_rating,
                        COUNT(orate.rating_id) AS rating_count
                    FROM order_ratings orate
                    INNER JOIN orders o ON orate.order_id = o.orders_id
                    INNER JOIN order_items oi ON oi.order_id = o.orders_id
                    WHERE oi.product_id = ?
                ");
                $ratingStmt->execute([$product_id]);
                $r = $ratingStmt->fetch(PDO::FETCH_ASSOC);
                if ($r) { $avg_rating = (float)($r['avg_rating'] ?? 0); $rating_count = (int)($r['rating_count'] ?? 0); }

                // Total sold based on order_items quantities for Completed/Delivered/Finished orders via order_status mapping
                $soldStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(oi.quantity),0) AS total_sold
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.orders_id
                    INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                    WHERE oi.product_id = ? AND os.status_name IN ('Delivered','Completed','Finished')
                ");
                $soldStmt->execute([$product_id]);
                $s = $soldStmt->fetch(PDO::FETCH_ASSOC);
                if ($s) { $total_sold = (int)($s['total_sold'] ?? 0); }
            } catch (Exception $e) { /* silently ignore */ }
            ?>

            <div class="d-flex align-items-center gap-3 mb-3">
                <div class="text-warning" aria-label="Average rating">
                    <?php 
                    $rounded = max(0, min(5, round($avg_rating))); 
                    for ($i=1; $i<=5; $i++): ?>
                        <i class="fas fa-star <?= $i <= $rounded ? 'text-warning' : 'text-muted' ?>"></i>
                    <?php endfor; ?>
                    <span class="ms-2 fw-semibold"><?= number_format($avg_rating, 1) ?></span>
                    <span class="text-muted">(<?= $rating_count ?> reviews)</span>
                </div>
                <div class="vr"></div>
                <div class="text-muted" aria-label="Units sold">
                    <i class="fas fa-shopping-bag me-1"></i>
                    <?= $total_sold ?> sold
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8">
                    <div class="product-card">
                        <img src="<?= !empty($product['primary_image']) ? 'admin/' . htmlspecialchars($product['primary_image']) : 'images/placeholder.jpg' ?>" 
                             alt="<?= htmlspecialchars($product['product_name']) ?>" 
                             class="product-image" 
                             onerror="this.src='images/placeholder.jpg'">
                        
                        <h1 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h1>
                        <div class="product-category">
                            <i class="fas fa-tag me-2"></i>
                            <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                        </div>
                        
                        <p class="product-description"><?= htmlspecialchars($product['product_description'] ?? 'No description available') ?></p>
                        
                        <div class="price-section">
                            <div class="price">₱<?= number_format($product['selling_price'] ?? 0, 2) ?></div>
                            <div class="unit">per kilo</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="product-card">
                        <h3>Add to Cart</h3>
                        
                        <div class="stock-info">
                            <i class="fas fa-boxes me-2"></i>
                            Stock: <?= number_format($product['current_stock'] ?? 0, 2) ?> kilos available
                            <?php if (($product['current_stock'] ?? 0) <= 0): ?>
                                <span class="badge bg-danger ms-2">Out of Stock</span>
                            <?php elseif (($product['current_stock'] ?? 0) <= 10): ?>
                                <span class="badge bg-warning ms-2">Low Stock</span>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label class="quantity-label">Quantity (kilos):</label>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="<?= htmlspecialchars($product['current_stock'] ?? 0) ?>">
                        </div>
                        
                        <button type="button" class="btn-add-cart" onclick="addToCart()" <?= ($product['current_stock'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-cart-plus me-2"></i>
                            <?= ($product['current_stock'] ?? 0) <= 0 ? 'Out of Stock' : 'Add to Cart' ?>
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Global variables for cart management
        let isAddingToCart = false;

        // Wait for page to fully load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Product detail page loaded');
            
            // Initialize cart functionality
            updateCartBadge();
            initializeCartRefresh();
            
            // Quantity validation
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantityInput.addEventListener('input', function() {
                    const max = parseInt(this.getAttribute('max'));
                    const value = parseInt(this.value);
                    
                    if (value > max) {
                        this.value = max;
                        showToast(`Maximum quantity is ${max} kilos`, 'warning');
                    }
                    
                    if (value < 1) {
                        this.value = 1;
                    }
                });
            }
        });

        // Function to refresh cart content
        function refreshCartContent() {
            fetch('cart_content.php')
                .then(response => response.text())
                .then(html => {
                    const cartBody = document.querySelector('.cart-body');
                    if (cartBody) {
                        cartBody.innerHTML = html;
                    }
                    updateCartFooter();
                })
                .catch(error => {
                    console.error('Error refreshing cart:', error);
                });
        }

        // Function to update cart footer with totals
        function updateCartFooter() {
            fetch('cart_total.php')
                .then(response => response.json())
                .then(data => {
                    const cartFooter = document.querySelector('.cart-footer');
                    const cartSubtotal = document.querySelector('.cart-subtotal');
                    const cartTotal = document.querySelector('.cart-total');
                    
                    if (data.total > 0) {
                        if (cartFooter) cartFooter.style.display = 'block';
                        if (cartSubtotal) cartSubtotal.textContent = '₱' + data.total.toFixed(2);
                        if (cartTotal) cartTotal.textContent = '₱' + data.total.toFixed(2);
                    } else {
                        if (cartFooter) cartFooter.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error updating cart footer:', error);
                });
        }

        // Toast notification function
        function showToast(message, type = 'info') {
            // Remove existing toasts
            const existingToasts = document.querySelectorAll('.toast');
            existingToasts.forEach(toast => toast.remove());

            const toast = document.createElement('div');
            toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
            toast.style.zIndex = '9999';
            
            let bgClass, icon, title;
            switch(type) {
                case 'success':
                    bgClass = 'bg-success';
                    icon = 'fas fa-check-circle';
                    title = 'Success';
                    break;
                case 'error':
                    bgClass = 'bg-danger';
                    icon = 'fas fa-exclamation-circle';
                    title = 'Error';
                    break;
                case 'info':
                    bgClass = 'bg-info';
                    icon = 'fas fa-info-circle';
                    title = 'Info';
                    break;
                case 'warning':
                    bgClass = 'bg-warning';
                    icon = 'fas fa-exclamation-triangle';
                    title = 'Warning';
                    break;
                default:
                    bgClass = 'bg-info';
                    icon = 'fas fa-info-circle';
                    title = 'Info';
            }
            
            toast.innerHTML = `
                <div class="toast-header ${bgClass} text-white">
                    <i class="${icon} me-2"></i>
                    <strong class="me-auto">${title}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            document.body.appendChild(toast);

            // Auto remove after 3 seconds
            setTimeout(() => {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 3000);
        }

        // Update cart badge function
        function updateCartBadge() {
            fetch('cart_count.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(count => {
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = count.trim();
                        if (parseInt(count) > 0) {
                            cartBadge.style.display = 'flex';
                        } else {
                            cartBadge.style.display = 'none';
                        }
                    } else if (parseInt(count) > 0) {
                        // Create badge if it doesn't exist and there are items
                        const cartButton = document.querySelector('[onclick*="toggleCart"], .cart-icon, .navbar-nav .nav-link[href*="cart"]');
                        if (cartButton) {
                            const newBadge = document.createElement('span');
                            newBadge.className = 'cart-badge position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger';
                            newBadge.textContent = count.trim();
                            newBadge.style.display = 'flex';
                            newBadge.style.alignItems = 'center';
                            newBadge.style.justifyContent = 'center';
                            newBadge.style.minWidth = '20px';
                            newBadge.style.height = '20px';
                            newBadge.style.fontSize = '0.75rem';
                            cartButton.style.position = 'relative';
                            cartButton.appendChild(newBadge);
                        }
                    }
                })
                .catch(error => {
                    console.error('Error updating cart badge:', error);
                });
        }

        // Initialize cart refresh functionality
        function initializeCartRefresh() {
            // Override the toggleCart function to refresh content when opened
            const originalToggleCart = window.toggleCart;
            if (originalToggleCart) {
                window.toggleCart = function() {
                    originalToggleCart();
                    // If cart is being opened, refresh the content
                    setTimeout(() => {
                        const cart = document.getElementById('slidingCart');
                        if (cart && cart.classList.contains('active')) {
                            refreshCartContent();
                        }
                    }, 100);
                };
            }

            // Also add a MutationObserver to watch for cart state changes
            const cart = document.getElementById('slidingCart');
            if (cart) {
                const observer = new MutationObserver(function(mutations) {
                    mutations.forEach(function(mutation) {
                        if (mutation.type === 'attributes' && mutation.attributeName === 'class') {
                            if (cart.classList.contains('active')) {
                                // Cart is now open, refresh content
                                refreshCartContent();
                            }
                        }
                    });
                });
                
                observer.observe(cart, {
                    attributes: true,
                    attributeFilter: ['class']
                });
            }
        }
        
        // Enhanced add to cart function with improved AJAX
        function addToCart() {
            // Prevent multiple simultaneous requests
            if (isAddingToCart) {
                showToast('Please wait, adding product to cart...', 'info');
                return;
            }

            const quantity = parseInt(document.getElementById('quantity').value);
            const addButton = document.querySelector('.btn-add-cart');
            
            console.log('Add to cart clicked. Product ID:', <?= $product ? $product['product_id'] : 0 ?>, 'Quantity:', quantity);
            
            if (!addButton) {
                showToast('Add to cart button not found', 'error');
                return;
            }
            
            if (addButton.disabled) {
                showToast('This product is out of stock', 'error');
                return;
            }
            
            if (quantity < 1) {
                showToast('Please enter a valid quantity', 'error');
                return;
            }

            isAddingToCart = true;
            
            // Store original button content
            const originalButtonContent = addButton.innerHTML;
            
            // Add loading state
            addButton.disabled = true;
            addButton.classList.add('loading');
            addButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding to Cart...';
            
            // Enhanced form data
            const formData = new FormData();
            formData.append('product_id', <?= $product ? $product['product_id'] : 0 ?>);
            formData.append('action', 'add');
            formData.append('quantity', quantity);
            formData.append('unit', 'kilo');
            formData.append('unit_price', <?= $product ? ($product['selling_price'] ?? 0) : 0 ?>);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            
            console.log('Sending data to cart.php:', {
                product_id: <?= $product ? $product['product_id'] : 0 ?>,
                action: 'add',
                quantity: quantity,
                unit: 'kilo',
                unit_price: <?= $product ? ($product['selling_price'] ?? 0) : 0 ?>
            });
            
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
                console.log('Response data:', data);
                if (data.success) {
                    showToast('Product added to cart successfully!', 'success');
                    updateCartBadge();
                    refreshCartContent();
                    
                    // Also refresh cart footer if cart is currently open
                    const cart = document.getElementById('slidingCart');
                    if (cart && cart.classList.contains('active')) {
                        updateCartFooter();
                    }
                    
                    // Reset quantity to 1
                    document.getElementById('quantity').value = 1;
                } else {
                    const errorMessage = data.error || data.message || 'Failed to add product to cart';
                    showToast(errorMessage, 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error. Please check your connection and try again.', 'error');
            })
            .finally(() => {
                isAddingToCart = false;
                // Reset button state
                addButton.disabled = false;
                addButton.classList.remove('loading');
                addButton.innerHTML = originalButtonContent;
            });
        }
    </script>
</body>
</html>