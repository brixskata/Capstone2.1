<?php
session_start();
include 'includes/db.php';

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$product = null;
$conversions = [];
$boxes = [];
$error = '';

// Get product ID from URL
$product_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($product_id <= 0) {
    $error = 'Invalid product ID';
} else {
    try {
        // Fetch product details with ratings
        $stmt = $pdo->prepare("
            SELECT 
                p.product_id,
                p.product_name,
                p.product_description,
                c.category_name,
                b.name AS brand_name,
                uom.name AS base_uom_name,
                COALESCE(ps.current_stock, 0) AS stock_kilos,
                COALESCE(pp.selling_price, 0) AS price_per_kilo,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1,
                COALESCE(AVG(pr.rating), 0) AS avg_rating,
                COUNT(pr.rating) AS total_ratings
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN brands b ON p.brand_id = b.id
            LEFT JOIN uom uom ON p.uom_id = uom.uom_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            LEFT JOIN product_ratings pr ON p.product_id = pr.product_id
            WHERE p.product_id = ? AND p.is_archive = 0
            GROUP BY p.product_id, p.product_name, p.product_description, c.category_name, b.name, uom.name, ps.current_stock, pp.selling_price
        ");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$product) {
            $error = 'Product not found';
        } else {
            // Fetch unit conversions
            $stmt = $pdo->prepare("
                SELECT 
                    puc.conversion_id,
                    puc.uom_id,
                    puc.conversion_rate,
                    u.name AS uom_name
                FROM product_uom_conversions puc
                JOIN uom u ON puc.uom_id = u.uom_id
                WHERE puc.product_id = ?
                ORDER BY u.name
            ");
            $stmt->execute([$product_id]);
            $conversions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fetch available boxes
            $stmt = $pdo->prepare("
                SELECT 
                    box_id,
                    batch_id,
                    weight,
                    is_sold
                FROM product_boxes
                WHERE product_id = ? AND is_sold = 0
                ORDER BY weight
            ");
            $stmt->execute([$product_id]);
            $boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (PDOException $e) {
        $error = 'Database error: ' . $e->getMessage();
    }
}

// Fetch cart items for sidebar
$cart_items = [];
$cart_total = 0;
foreach ($_SESSION['cart'] ?? [] as $product_id => $cart_item) {
    $sql = "SELECT 
                p.product_id AS id,
                p.product_name AS name,
                p.product_description AS description,
                COALESCE(pp.selling_price, 0) AS price,
                COALESCE(ps.current_stock, 0) AS stock
            FROM products p
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            WHERE p.product_id = :product_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $product_data = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product_data) {
        $item_total = $product_data['price'] * $cart_item['quantity'];
        $cart_total += $item_total;
        $cart_items[] = [
            'product' => $product_data,
            'quantity' => $cart_item['quantity'],
            'total' => $item_total
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $product ? htmlspecialchars($product['product_name']) . ' - MikeMadz' : 'Product Not Found' ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

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
            --brand-light: #f8f9fa;
            --brand-gradient: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--bs-dark);
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
        }

        /* Navigation */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--brand-primary) !important;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--bs-dark) !important;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .navbar-nav .nav-link:hover {
            color: var(--brand-primary) !important;
        }

        /* Product Section */
        .product-section {
            padding: 3rem 0;
        }

        .product-card {
            background: white;
            border-radius: 1.25rem;
            padding: 2rem;
            border: 1px solid rgba(127, 23, 52, 0.1);
            box-shadow: 0 10px 30px rgba(127, 23, 52, 0.1);
            margin-bottom: 2rem;
        }

        .product-image {
            width: 100%;
            height: 400px;
            object-fit: cover;
            border-radius: 1rem;
            margin-bottom: 1.5rem;
        }

        .product-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--brand-primary);
            margin-bottom: 1rem;
        }

        /* Product Rating Styles */
        .product-rating {
            margin: 1rem 0;
        }

        .rating-stars {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .star-filled {
            color: #ffc107;
            font-size: 1.1rem;
        }

        .star-empty {
            color: #dee2e6;
            font-size: 1.1rem;
        }

        .rating-score {
            font-weight: 600;
            color: var(--brand-primary);
            margin-left: 0.5rem;
            font-size: 1rem;
        }

        .rating-count {
            color: #6c757d;
            font-size: 0.9rem;
            margin-left: 0.25rem;
        }

        .no-rating-text {
            color: #6c757d;
            font-size: 0.9rem;
            font-style: italic;
        }

        .product-category {
            font-size: 1.1rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .product-description {
            font-size: 1.1rem;
            color: #495057;
            margin-bottom: 2rem;
            line-height: 1.7;
        }

        .price-section {
            background: var(--brand-gradient);
            color: white;
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
        }

        .base-price {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .base-unit {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .stock-info {
            background: #e8f5e8;
            color: var(--bs-success);
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            font-weight: 600;
        }

        /* Purchase Options */
        .purchase-options {
            background: white;
            border-radius: 1.25rem;
            padding: 2rem;
            border: 1px solid rgba(127, 23, 52, 0.1);
            box-shadow: 0 10px 30px rgba(127, 23, 52, 0.1);
        }

        .option-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--brand-primary);
            margin-bottom: 1.5rem;
        }

        .unit-option {
            border: 2px solid #e9ecef;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .unit-option:hover {
            border-color: var(--brand-primary);
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.1);
        }

        .unit-option.selected {
            border-color: var(--brand-primary);
            background: rgba(127, 23, 52, 0.05);
        }

        .unit-name {
            font-size: 1.2rem;
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.5rem;
        }

        .unit-price {
            font-size: 1.1rem;
            color: var(--brand-primary);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .unit-description {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .box-option {
            border: 2px solid #e9ecef;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .box-option:hover {
            border-color: var(--brand-primary);
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.1);
        }

        .box-option.selected {
            border-color: var(--brand-primary);
            background: rgba(127, 23, 52, 0.05);
        }

        .box-weight {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.5rem;
        }

        .box-price {
            font-size: 1.1rem;
            color: var(--brand-primary);
            font-weight: 600;
        }

        .quantity-section {
            margin-top: 2rem;
        }

        .quantity-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 1rem;
        }

        .quantity-input {
            width: 100px;
            padding: 0.75rem;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 600;
        }

        .quantity-input:focus {
            border-color: var(--brand-primary);
            outline: none;
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }

        .btn-add-cart {
            background: var(--brand-gradient);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-top: 1rem;
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }

        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 23, 52, 0.4);
            color: white;
        }

        .btn-add-cart:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        /* Cart Sidebar */
        .cart-sidebar {
            position: fixed;
            top: 0;
            right: -400px;
            width: 400px;
            height: 100vh;
            background: white;
            box-shadow: -5px 0 15px rgba(0,0,0,0.1);
            transition: right 0.3s ease;
            z-index: 1050;
            overflow-y: auto;
        }

        .cart-sidebar.show {
            right: 0;
        }

        .cart-header {
            background: var(--brand-gradient);
            color: white;
            padding: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cart-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
        }

        .cart-close {
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
        }

        .cart-body {
            padding: 1.5rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .cart-item:last-child {
            border-bottom: none;
        }

        .cart-item-info {
            flex: 1;
        }

        .cart-item-name {
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.25rem;
        }

        .cart-item-details {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .cart-item-price {
            font-weight: 600;
            color: var(--brand-primary);
        }

        .cart-footer {
            padding: 1.5rem;
            border-top: 1px solid #e9ecef;
            background: #f8f9fa;
        }

        .cart-total {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 1rem;
        }

        .btn-checkout {
            background: var(--brand-gradient);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 0.75rem;
            font-weight: 600;
            width: 100%;
            transition: all 0.3s ease;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 23, 52, 0.4);
            color: white;
        }

        .cart-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 1040;
            display: none;
        }

        .cart-overlay.show {
            display: block;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .cart-sidebar {
                width: 100%;
                right: -100%;
            }
            
            .product-title {
                font-size: 2rem;
            }
            
            .product-card, .purchase-options {
                padding: 1.5rem;
            }
        }

        .empty-cart {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--brand-primary);
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Promo Banner -->
    <div class="w-100 text-white text-center py-2 fw-bold d-flex flex-wrap justify-content-center align-items-center gap-3" style="background: var(--bs-secondary); font-size: 0.9rem;">
        <span>₱1,000 OFF on orders ₱10,000+</span>
        <span class="d-none d-md-inline">|</span>
        <span>Free Nationwide Delivery on ₱7,000+</span>
        <span class="d-none d-md-inline">|</span>
        <span>Sign up & get 10% OFF your first order</span>
    </div>

    <?php include 'includes/user_navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4 mb-5">
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i>
                <?= htmlspecialchars($error) ?>
            </div>
        <?php elseif ($product): ?>
            <div class="row">
                <div class="col-lg-8">
                    <!-- Product Details -->
                    <div class="product-card">
                        <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>" 
                             alt="<?= htmlspecialchars($product['product_name']) ?>" 
                             class="product-image" 
                             onerror="this.src='images/placeholder.jpg'">
                        
                        <h1 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h1>
                        
                        <!-- Product Rating -->
                        <div class="product-rating mb-3">
                            <?php if ($product['total_ratings'] > 0): ?>
                                <div class="rating-stars">
                                    <?php 
                                    $avgRating = round($product['avg_rating'], 1);
                                    $fullStars = floor($avgRating);
                                    $hasHalfStar = ($avgRating - $fullStars) >= 0.5;
                                    
                                    for ($i = 1; $i <= 5; $i++): 
                                        if ($i <= $fullStars):
                                    ?>
                                        <i class="fas fa-star star-filled"></i>
                                    <?php elseif ($i == $fullStars + 1 && $hasHalfStar): ?>
                                        <i class="fas fa-star-half-alt star-filled"></i>
                                    <?php else: ?>
                                        <i class="fas fa-star star-empty"></i>
                                    <?php endif; endfor; ?>
                                    <span class="rating-score"><?= $avgRating ?></span>
                                    <span class="rating-count">(<?= $product['total_ratings'] ?> reviews)</span>
                                </div>
                            <?php else: ?>
                                <div class="rating-stars no-rating">
                                    <span class="no-rating-text">No reviews yet</span>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="product-category">
                            <i class="fas fa-tag me-2"></i>
                            <?= htmlspecialchars($product['category_name'] ?? 'Uncategorized') ?>
                            <?php if ($product['brand_name']): ?>
                                <span class="ms-3">
                                    <i class="fas fa-award me-2"></i>
                                    <?= htmlspecialchars($product['brand_name']) ?>
                                </span>
                            <?php endif; ?>
                        </div>
                        
                        <p class="product-description"><?= htmlspecialchars($product['product_description']) ?></p>
                        
                        <div class="price-section">
                            <div class="base-price">₱<?= number_format($product['price_per_kilo'], 2) ?></div>
                            <div class="base-unit">per <?= htmlspecialchars($product['base_uom_name'] ?? 'kilo') ?></div>
                        </div>
                        
                        <div class="stock-info">
                            <i class="fas fa-boxes me-2"></i>
                            Stock: <?= number_format($product['stock_kilos'], 2) ?> <?= htmlspecialchars($product['base_uom_name'] ?? 'kilos') ?> available
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <!-- Purchase Options -->
                    <div class="purchase-options">
                        <h3 class="option-title">Purchase Options</h3>
                        
                        <!-- Per Kilo Option -->
                        <div class="unit-option selected" data-unit="kilo" data-price="<?= $product['price_per_kilo'] ?>">
                            <div class="unit-name">Per Kilo</div>
                            <div class="unit-price">₱<?= number_format($product['price_per_kilo'], 2) ?></div>
                            <div class="unit-description">Base unit - sold by weight</div>
                        </div>
                        
                        <!-- Per Piece Options -->
                        <?php foreach ($conversions as $conversion): ?>
                            <?php if ($conversion['uom_name'] === 'Pieces'): ?>
                                <?php 
                                $piece_price = $product['price_per_kilo'] * $conversion['conversion_rate'];
                                $pieces_available = floor($product['stock_kilos'] / $conversion['conversion_rate']);
                                ?>
                                <div class="unit-option" data-unit="piece" data-price="<?= $piece_price ?>" data-conversion="<?= $conversion['conversion_rate'] ?>">
                                    <div class="unit-name">Per Piece</div>
                                    <div class="unit-price">₱<?= number_format($piece_price, 2) ?></div>
                                    <div class="unit-description">
                                        1 piece = <?= number_format($conversion['conversion_rate'], 2) ?> kilos
                                        <br>Available: <?= $pieces_available ?> pieces
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                        
                        <!-- Per Box Options -->
                        <?php if (!empty($boxes)): ?>
                            <div class="mt-3">
                                <h5 class="mb-3">Available Boxes</h5>
                                <?php foreach ($boxes as $box): ?>
                                    <?php 
                                    $box_price = $product['price_per_kilo'] * $box['weight'];
                                    ?>
                                    <div class="box-option" data-unit="box" data-price="<?= $box_price ?>" data-weight="<?= $box['weight'] ?>" data-box-id="<?= $box['box_id'] ?>">
                                        <div class="box-weight">Box #<?= $box['box_id'] ?> - <?= number_format($box['weight'], 2) ?> kg</div>
                                        <div class="box-price">₱<?= number_format($box_price, 2) ?></div>
                                        <div class="unit-description">Batch: <?= htmlspecialchars($box['batch_id']) ?></div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Quantity and Add to Cart -->
                        <div class="quantity-section">
                            <div class="quantity-label">Quantity:</div>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1" max="100">
                            
                            <button type="button" class="btn-add-cart" onclick="addToCart()">
                                <i class="fas fa-cart-plus me-2"></i>
                                Add to Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Cart Sidebar -->
    <div class="cart-overlay" id="cartOverlay" onclick="toggleCart()"></div>
    <div class="cart-sidebar" id="cartSidebar">
        <div class="cart-header">
            <h3 class="cart-title">Shopping Cart</h3>
            <button class="cart-close" onclick="toggleCart()">
                <i class="fas fa-times"></i>
            </button>
        </div>
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
    </div>

    <!-- Footer -->
    <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        let selectedUnit = 'kilo';
        let selectedPrice = <?= $product ? $product['price_per_kilo'] : 0 ?>;
        let selectedBoxId = null;
        let selectedWeight = null;

        // Unit selection
        document.querySelectorAll('.unit-option, .box-option').forEach(option => {
            option.addEventListener('click', function() {
                // Remove selected class from all options
                document.querySelectorAll('.unit-option, .box-option').forEach(opt => {
                    opt.classList.remove('selected');
                });
                
                // Add selected class to clicked option
                this.classList.add('selected');
                
                // Update selected values
                selectedUnit = this.dataset.unit;
                selectedPrice = parseFloat(this.dataset.price);
                
                if (selectedUnit === 'box') {
                    selectedBoxId = this.dataset.boxId;
                    selectedWeight = parseFloat(this.dataset.weight);
                } else {
                    selectedBoxId = null;
                    selectedWeight = null;
                }
                
                // Update max quantity based on availability
                updateMaxQuantity();
            });
        });

        function updateMaxQuantity() {
            const quantityInput = document.getElementById('quantity');
            let maxQuantity = 100;
            
            if (selectedUnit === 'kilo') {
                maxQuantity = Math.floor(<?= $product ? $product['stock_kilos'] : 0 ?>);
            } else if (selectedUnit === 'piece') {
                const conversion = document.querySelector('.unit-option[data-unit="piece"]')?.dataset.conversion;
                if (conversion) {
                    maxQuantity = Math.floor(<?= $product ? $product['stock_kilos'] : 0 ?> / parseFloat(conversion));
                }
            } else if (selectedUnit === 'box') {
                maxQuantity = 1; // Only one box per selection
            }
            
            quantityInput.max = maxQuantity;
            if (parseInt(quantityInput.value) > maxQuantity) {
                quantityInput.value = maxQuantity;
            }
        }

        function addToCart() {
            const quantity = parseInt(document.getElementById('quantity').value);
            
            if (quantity < 1) {
                alert('Please enter a valid quantity');
                return;
            }
            
            const formData = new FormData();
            formData.append('product_id', <?= $product ? $product['product_id'] : 0 ?>);
            formData.append('action', 'add');
            formData.append('quantity', quantity);
            formData.append('unit', selectedUnit);
            formData.append('unit_price', selectedPrice);
            if (selectedBoxId) {
                formData.append('box_id', selectedBoxId);
                formData.append('weight', selectedWeight);
            }
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?>');
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Product added to cart!', 'success');
                    refreshCartContent();
                    updateCartBadge();
                } else {
                    showToast(data.error || 'Failed to add product to cart', 'error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showToast('Network error. Please try again.', 'error');
            });
        }

        function refreshCartContent() {
            fetch('cart_content.php')
                .then(response => response.text())
                .then(html => {
                    const cartBody = document.querySelector('.cart-body');
                    const cartFooter = document.querySelector('.cart-footer');
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');
                    
                    // Update cart body
                    const newCartBody = doc.querySelector('.cart-body');
                    if (cartBody && newCartBody) {
                        cartBody.innerHTML = newCartBody.innerHTML;
                    }
                    
                    // Update cart footer
                    const newCartFooter = doc.querySelector('.cart-footer');
                    if (newCartFooter) {
                        if (cartFooter) {
                            cartFooter.innerHTML = newCartFooter.innerHTML;
                            cartFooter.style.display = 'block';
                        } else {
                            const cartSidebar = document.querySelector('.cart-sidebar');
                            if (cartSidebar) {
                                cartSidebar.appendChild(newCartFooter);
                            }
                        }
                    } else if (cartFooter) {
                        cartFooter.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error refreshing cart:', error);
                });
        }

        function updateCartBadge() {
            fetch('cart_count.php')
                .then(response => response.text())
                .then(count => {
                    const cartBadge = document.querySelector('.cart-badge');
                    if (cartBadge) {
                        cartBadge.textContent = count;
                        if (parseInt(count) > 0) {
                            cartBadge.style.display = 'flex';
                        } else {
                            cartBadge.style.display = 'none';
                        }
                    }
                });
        }

        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
            toast.style.zIndex = '9999';
            
            const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-header ${bgClass} text-white">
                    <i class="${icon} me-2"></i>
                    <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Initialize
        updateMaxQuantity();
    </script>
</body>
</html> 