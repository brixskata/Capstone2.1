<?php
session_start();
include 'includes/db.php';

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$products = [];
$categories = [];

try {
    // Fetch all categories for the sidebar
    $categories = $pdo->query("SELECT category_id, category_name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
    
    // Get user's favorite products if logged in
    $user_favorites = [];
    if (isset($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("SELECT product_id FROM favorites WHERE user_id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user_favorites = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // Check if a category is selected
    if (isset($_GET['category'])) {
        $selectedCategory = $_GET['category'];

        if ($selectedCategory === 'all') {
            // Fetch all non-archived products with normalized data
            $products = $pdo->query("
                SELECT 
                    p.product_id AS id,
                    p.product_name AS name,
                    p.product_description AS description,
                    uom.name AS uom_name,
                    COALESCE(ps.current_stock, 0) AS stock,
                    COALESCE(pp.selling_price, 0) AS markup_value,
                    (SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1) AS cost_per_unit,
                    COALESCE(pp.selling_price, 0) + COALESCE((SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1), 0) AS price,
                    (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
                FROM products p
                LEFT JOIN uom uom ON p.uom_id = uom.uom_id
                LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                WHERE p.is_archive = 0
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get the selected category's ID
            $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = :name");
            $stmt->execute(['name' => $selectedCategory]);
            $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($categoryData) {
                // Fetch products for selected category with normalized data
                $stmt = $pdo->prepare("
                    SELECT 
                        p.product_id AS id,
                        p.product_name AS name,
                        p.product_description AS description,
                        uom.name AS uom_name,
                        COALESCE(ps.current_stock, 0) AS stock,
                        COALESCE(pp.selling_price, 0) AS markup_value,
                        (SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1) AS cost_per_unit,
                        COALESCE(pp.selling_price, 0) + COALESCE((SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1), 0) AS price,
                        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
                    FROM products p
                    LEFT JOIN uom uom ON p.uom_id = uom.uom_id
                    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                    WHERE p.category_id = :category_id AND p.is_archive = 0
                ");
                $stmt->execute(['category_id' => $categoryData['category_id']]);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } else {
        // Fetch all non-archived products with normalized data by default
        $products = $pdo->query("
            SELECT 
                p.product_id AS id,
                p.product_name AS name,
                p.product_description AS description,
                uom.name AS uom_name,
                COALESCE(ps.current_stock, 0) AS stock,
                COALESCE(pp.selling_price, 0) AS markup_value,
                (SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1) AS cost_per_unit,
                COALESCE(pp.selling_price, 0) + COALESCE((SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1), 0) AS price,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
            FROM products p
            LEFT JOIN uom uom ON p.uom_id = uom.uom_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            WHERE p.is_archive = 0
        ")->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo "Error fetching data: " . $e->getMessage();
}

// Fetch cart items for sliding cart display
$cart_items = [];
$cart_total = 0;
foreach ($_SESSION['cart'] ?? [] as $product_id => $cart_item) {
    $sql = "SELECT 
                p.product_id AS id,
                p.product_name AS name,
                p.product_description AS description,
                COALESCE(pp.selling_price, 0) + COALESCE((SELECT r.cost_per_unit FROM restocking r WHERE r.product_id = p.product_id AND r.status_id = 2 ORDER BY r.restock_date DESC LIMIT 1), 0) AS price,
                COALESCE(ps.current_stock, 0) AS stock
            FROM products p
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            WHERE p.product_id = :product_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $item_total = $product['price'] * $cart_item['quantity'];
        $cart_total += $item_total;
        $cart_items[] = [
            'product' => $product,
            'quantity' => $cart_item['quantity'],
            'total' => $item_total
        ];
    }
}
?>

<?php
$page_title = 'Product Catalog - MikeMadz';
$page_description = 'Browse our complete catalog of fresh meat and seafood products. Quality guaranteed with nationwide delivery.';
$page_keywords = 'meat catalog, seafood catalog, fresh products, MikeMadz products';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/user_head.php'; ?>
    <title><?= htmlspecialchars($page_title) ?></title>
    <!-- Swiper CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">

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
            padding-bottom: 120px; /* Add space for footer */
        }

        /* Promo Banner */
        .promo-banner {
            background: var(--brand-gradient);
            color: white;
            padding: 0.75rem 0;
            font-weight: 500;
            font-size: 0.9rem;
            box-shadow: 0 2px 10px rgba(127, 23, 52, 0.2);
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
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--bs-dark) !important;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
            position: relative;
        }

        .navbar-nav .nav-link:hover {
            color: var(--brand-primary) !important;
        }

        .navbar-nav .nav-link::after {
            content: '';
            position: absolute;
            width: 0;
            height: 2px;
            bottom: -5px;
            left: 50%;
            background: var(--brand-gradient);
            transition: all 0.3s ease;
            transform: translateX(-50%);
        }

        .navbar-nav .nav-link:hover::after {
            width: 100%;
        }

        /* Cart styles are now handled by the shared navbar */

        /* Category Section */
        .category-section {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 4px 20px rgba(127, 23, 52, 0.1);
            border: 1px solid rgba(127, 23, 52, 0.1);
        }

        .category-title {
            font-size: 1.8rem;
            font-weight: 700;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 1rem;
        }

        .category-select {
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
        }

        .category-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            outline: none;
        }

        /* Product Cards */
        .product-card {
            background: white;
            border-radius: 1.25rem;
            padding: 1.5rem;
            border: 1px solid rgba(127, 23, 52, 0.1);
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--brand-gradient);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.15);
            border-color: var(--brand-primary);
        }

        .product-card:hover::before {
            transform: scaleX(1);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--brand-gradient);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-size: 0.75rem;
            font-weight: 600;
            z-index: 3;
            box-shadow: 0 2px 8px rgba(127, 23, 52, 0.3);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .product-badge.in-stock {
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
        }

        .product-badge.low-stock {
            background: linear-gradient(135deg, #ffc107 0%, #fd7e14 100%);
            color: #212529;
        }

        .product-badge.out-of-stock {
            background: linear-gradient(135deg, #6c757d 0%, #495057 100%);
        }

        .swiper {
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            overflow: hidden;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .swiper-slide img {
            border-radius: 0.75rem;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--brand-primary);
            line-height: 1.4;
        }

        .product-desc {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .product-price {
            font-size: 1.3rem;
            font-weight: 700;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .product-stock {
            font-size: 0.85rem;
            margin-bottom: 1rem;
        }

        .stock-available {
            color: var(--bs-success);
            font-weight: 600;
        }

        .stock-out {
            color: var(--bs-danger);
            font-weight: 600;
        }

        .btn-add-cart {
            background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
            width: 100%;
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
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
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 23, 52, 0.4);
            color: white;
        }

        .btn-add-cart:active {
            transform: translateY(0);
        }

        .btn-out-of-stock {
            background: #6c757d;
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.9rem;
            width: 100%;
            cursor: not-allowed;
            opacity: 0.7;
        }

        .btn-favorite {
            background: white;
            color: var(--brand-primary);
            border: 2px solid var(--brand-primary);
            padding: 0.6rem;
            border-radius: 0.75rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(127, 23, 52, 0.1);
        }

        .btn-favorite:hover {
            background: var(--brand-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }

        .btn-favorite.favorited {
            background: var(--bs-danger) !important;
            color: white !important;
            border-color: var(--bs-danger) !important;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3) !important;
        }

        /* Footer */
        .footer {
            background: linear-gradient(135deg, #212529 0%, #343a40 100%);
            color: white;
            padding: 3rem 0 1rem;
            margin-top: 4rem;
            position: relative;
            z-index: 10;
        }

        .footer-title {
            color: white;
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: #adb5bd;
            text-decoration: none;
            transition: all 0.3s ease;
            display: block;
            padding: 0.25rem 0;
        }

        .footer-link:hover {
            color: white;
        }

        .footer-bottom {
            border-top: 1px solid #495057;
            padding-top: 1rem;
            margin-top: 2rem;
            text-align: center;
            color: #adb5bd;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sliding-cart {
                width: 100%;
                right: -100%;
            }

            .category-title {
                font-size: 1.5rem;
            }

            .product-card {
                margin-bottom: 1.5rem;
            }

            .category-section {
                padding: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .sliding-cart {
                width: 100%;
            }

            .product-card {
                padding: 1rem;
            }

            .btn-add-cart {
                padding: 0.5rem 1rem;
                font-size: 0.9rem;
            }

            .btn-favorite {
                padding: 0.5rem;
            }
        }

        /* Loading animation for images */
        .swiper-slide img {
            transition: opacity 0.3s ease;
        }

        .swiper-slide img[src=""] {
            opacity: 0;
        }

        /* Enhanced product grid spacing */
        .row.g-4 {
            margin-bottom: 2rem;
        }

        /* Better spacing for empty states */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--brand-primary);
            opacity: 0.5;
        }
    </style>
</head>
<body>
    <!-- Promo Banner -->
    <?php include 'includes/user_promo.php'; ?>

    <?php include 'includes/user_navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mt-4 mb-5">
        <!-- Category Section -->
        <div class="category-section">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <?php if (isset($_GET['category'])): ?>
                        <h1 class="category-title">Category: <?= htmlspecialchars(ucfirst($_GET['category'])) ?></h1>
                    <?php else: ?>
                        <h1 class="category-title">All Products</h1>
                    <?php endif; ?>
                </div>
                <div class="col-md-4">
                    <form method="get">
                        <select name="category" class="form-select category-select" onchange="this.form.submit()">
                            <option value="all" <?= !isset($_GET['category']) || $_GET['category'] === 'all' ? 'selected' : '' ?>>All Products</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category['category_name']) ?>" <?= isset($_GET['category']) && $_GET['category'] === $category['category_name'] ? 'selected' : '' ?>>
                                    <?= htmlspecialchars(ucfirst($category['category_name'])) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                </div>
            </div>
        </div>

        <!-- Products Grid -->
        <div class="row g-4">
            <?php foreach ($products as $product): ?>
                <!-- Debug: Product ID = <?= $product['id'] ?> -->
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="product-card">
                        <!-- Stock Badge -->
                        <?php if ($product['stock'] > 10): ?>
                            <span class="product-badge in-stock">In Stock</span>
                        <?php elseif ($product['stock'] > 0): ?>
                            <span class="product-badge low-stock">Low Stock</span>
                        <?php else: ?>
                            <span class="product-badge out-of-stock">Out of Stock</span>
                        <?php endif; ?>

                        <!-- Product Images Carousel -->
                        <div class="swiper mySwiper" data-product-id="<?= $product['id'] ?>" style="cursor: pointer;">
                            <div class="swiper-wrapper">
                                <?php if (!empty($product['image1'])): ?>
                                    <div class="swiper-slide">
                                        <img src="admin/<?= htmlspecialchars($product['image1']) ?>" class="w-100" style="height: 200px; object-fit: cover;" onerror="this.src='images/placeholder.jpg'">
                                    </div>
                                <?php else: ?>
                                    <div class="swiper-slide">
                                        <div class="w-100 d-flex align-items-center justify-content-center" style="height: 200px; background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%); border-radius: 0.75rem;">
                                            <i class="fas fa-image text-muted" style="font-size: 3rem;"></i>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="swiper-pagination"></div>
                        </div>

                        <h3 class="product-title" onclick="window.location.href='product_detail.php?id=<?= $product['id'] ?>'" style="cursor: pointer;"><?= htmlspecialchars($product['name']) ?></h3>
                        <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>

                        <div class="product-price">
                            ₱<?= number_format($product['price'], 2) ?>
                            <span class="fs-6 text-muted"> / <?= htmlspecialchars($product['uom_name'] ?? '') ?></span>
                        </div>

                        <div class="product-stock">
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-available">
                                    Stock: <?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                </span>
                            <?php else: ?>
                                <span class="stock-out">Out of Stock</span>
                            <?php endif; ?>
                        </div>

                        <?php if ($product['stock'] > 0): ?>
                            <div class="d-flex align-items-center gap-2">
                                <button type="button" class="btn-add-cart flex-grow-1" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                </button>
                                <button type="button" class="btn-favorite <?= in_array($product['id'], $user_favorites) ? 'favorited' : '' ?>" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <button class="btn-out-of-stock" disabled>
                                <i class="fas fa-times-circle me-2"></i>Out of Stock
                            </button>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Footer -->
        <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- Swiper JS -->
    <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>

    <script>
        // Global variables for cart management
        let isAddingToCart = false;

        // Initialize Swiper
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".mySwiper").forEach((el) => {
                const productId = el.getAttribute('data-product-id');
                
                new Swiper(el, {
                    loop: true,
                    autoplay: {
                        delay: 2000,
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: el.querySelector(".swiper-pagination"),
                        clickable: true,
                    },
                    on: {
                        click: function(swiper, event) {
                            console.log('Swiper clicked. Product ID:', productId);
                            if (productId) {
                                console.log('Navigating to product_detail.php?id=' + productId);
                                window.location.href = 'product_detail.php?id=' + productId;
                            }
                        }
                    }
                });
                
                // Remove the onclick attribute to prevent conflicts
                el.removeAttribute('onclick');
            });

            // Initialize cart functionality
            updateCartBadge();
            initializeCartButtons();
            initializeCartRefresh();
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

        // Initialize cart button event listeners
        function initializeCartButtons() {
            // Add event listeners to all add to cart buttons
            document.querySelectorAll('.btn-add-cart').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    // Get product ID from data attribute or onclick attribute
                    let productId = this.getAttribute('data-product-id');
                    if (!productId) {
                        const onclickAttr = this.getAttribute('onclick');
                        if (onclickAttr) {
                            const match = onclickAttr.match(/addToCart\((\d+)/);
                            if (match) {
                                productId = match[1];
                            }
                        }
                    }
                    
                    if (productId) {
                        addToCart(parseInt(productId), 1);
                    } else {
                        showToast('Error: Product ID not found', 'error');
                    }
                });
            });
        }

        // Add to cart function with improved AJAX
        function addToCart(productId, quantity = 1) {
            // Prevent multiple simultaneous requests
            if (isAddingToCart) {
                showToast('Please wait, adding product to cart...', 'info');
                return;
            }

            isAddingToCart = true;
            
            // Find the button that was clicked to show loading state
            const clickedButton = event.target.closest('.btn-add-cart');
            const originalButtonContent = clickedButton ? clickedButton.innerHTML : '';
            
            // Show loading state on button
            if (clickedButton) {
                clickedButton.disabled = true;
                clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            }

            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'add');
            formData.append('quantity', quantity);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            
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
                    showToast('Product added to cart successfully!', 'success');
                    updateCartBadge();
                    refreshCartContent();
                    
                    // Also refresh cart footer if cart is currently open
                    const cart = document.getElementById('slidingCart');
                    if (cart && cart.classList.contains('active')) {
                        updateCartFooter();
                    }
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
                if (clickedButton) {
                    clickedButton.disabled = false;
                    clickedButton.innerHTML = originalButtonContent;
                }
            });
        }


        // Favorite functionality
        document.querySelectorAll('.btn-favorite').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const isFavorited = this.classList.contains('favorited');
                
                fetch('favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + productId + '&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
                })
                .then(response => response.json())
                .then(data => {
                    let message, icon, headerClass;
                    
                    if (data.success && data.is_favorite) {
                        message = data.message || 'Added to favorites!';
                        icon = 'fas fa-heart text-danger';
                        headerClass = 'bg-success text-white';
                        
                        // Update button appearance to show it's favorited
                        this.classList.add('favorited');
                        this.style.background = 'var(--bs-danger)';
                        this.style.color = 'white';
                        this.style.borderColor = 'var(--bs-danger)';
                        
                    } else if (data.success && !data.is_favorite) {
                        message = data.message || 'Removed from favorites!';
                        icon = 'fas fa-heart-broken text-warning';
                        headerClass = 'bg-warning text-dark';
                        
                        // Update button appearance to show it's not favorited
                        this.classList.remove('favorited');
                        this.style.background = 'white';
                        this.style.color = 'var(--bs-secondary)';
                        this.style.borderColor = 'var(--bs-secondary)';
                        
                    } else if (!data.success && data.message && data.message.includes('log in')) {
                        message = data.message;
                        icon = 'fas fa-exclamation-circle text-warning';
                        headerClass = 'bg-warning text-dark';
                        
                        // Redirect to login after showing message
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        message = data.message || 'Something went wrong. Please try again.';
                        icon = 'fas fa-exclamation-circle text-danger';
                        headerClass = 'bg-danger text-white';
                    }
                    
                    // Show message
                    const toast = document.createElement('div');
                    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast-header ${headerClass}">
                            <i class="${icon} me-2"></i>
                            <strong class="me-auto">Favorites</strong>
                            <button type="button" class="btn-close ${headerClass.includes('text-white') ? 'btn-close-white' : ''}" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            ${message}
                        </div>
                    `;
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                })
                .catch(error => {
                    console.error('Error:', error);
                    
                    const toast = document.createElement('div');
                    toast.className = 'toast show position-fixed bottom-0 end-0 m-3';
                    toast.style.zIndex = '9999';
                    toast.innerHTML = `
                        <div class="toast-header bg-danger text-white">
                            <i class="fas fa-exclamation-circle me-2"></i>
                            <strong class="me-auto">Error</strong>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                        </div>
                        <div class="toast-body">
                            Network error. Please try again.
                        </div>
                    `;
                    document.body.appendChild(toast);

                    setTimeout(() => {
                        toast.remove();
                    }, 3000);
                });
            });
        });

    </script>
</body>
</html>