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
            // Fetch all products (one card per product, not per brand)
            $products = $pdo->query("
                SELECT 
                    p.product_id AS id,
                    p.product_name AS name,
                    p.product_description AS description,
                    uom.name AS uom_name,
                    -- Get newest batch price for this specific brand
                    COALESCE(pp.markup_price, 0) + COALESCE(
                        (SELECT pb.unit_cost FROM product_batches pb 
                         WHERE pb.product_id = p.product_id AND pb.brand_id = b.id 
                         AND pb.quantity_remaining > 0 AND pb.is_active = 1
                         ORDER BY pb.received_date DESC LIMIT 1),
                        pp.cost_price, 0
                    ) AS price,
                    -- Total stock from all brands combined
                    COALESCE(SUM(
                        (SELECT SUM(pb.quantity_remaining) FROM product_batches pb 
                         WHERE pb.product_id = p.product_id AND pb.brand_id = b.id AND pb.is_active = 1)
                    ), 0) AS stock,
                    -- Products sold (only from completed/delivered orders)
                    COALESCE((
                        SELECT SUM(oi.quantity) 
                        FROM order_items oi
                        INNER JOIN orders o ON oi.order_id = o.orders_id
                        INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                        WHERE oi.product_id = p.product_id 
                        AND os.status_name IN ('Delivered','Completed','Finished')
                    ), 0) AS products_sold,
                        -- Average rating (from order_ratings via order_items)
                        COALESCE((
                            SELECT AVG(ord_rat.rating) 
                            FROM order_ratings ord_rat
                            JOIN orders o ON ord_rat.order_id = o.orders_id
                            JOIN order_items oi ON o.orders_id = oi.order_id
                            WHERE oi.product_id = p.product_id
                        ), 0) AS avg_rating,
                    -- Count of brands available
                    COUNT(DISTINCT b.id) AS brand_count,
                    (SELECT pi.image_url FROM product_images pi 
                     WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
                FROM products p
                LEFT JOIN uom uom ON p.uom_id = uom.uom_id
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                LEFT JOIN brands b ON b.is_archived = 0
                WHERE p.is_archive = 0 
                AND EXISTS (
                    SELECT 1 FROM product_batches pb 
                    WHERE pb.product_id = p.product_id AND pb.brand_id = b.id AND pb.is_active = 1
                )
                GROUP BY p.product_id, p.product_name, p.product_description, uom.name
                ORDER BY stock DESC, name ASC
            ")->fetchAll(PDO::FETCH_ASSOC);
        } else {
            // Get the selected category's ID
            $stmt = $pdo->prepare("SELECT category_id FROM categories WHERE category_name = :name");
            $stmt->execute(['name' => $selectedCategory]);
            $categoryData = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($categoryData) {
                // Fetch products for selected category (one card per product, not per brand)
                $stmt = $pdo->prepare("
                    SELECT 
                        p.product_id AS id,
                        p.product_name AS name,
                        p.product_description AS description,
                        uom.name AS uom_name,
                        -- Get newest batch price for this specific brand
                        COALESCE(pp.markup_price, 0) + COALESCE(
                            (SELECT pb.unit_cost FROM product_batches pb 
                             WHERE pb.product_id = p.product_id AND pb.brand_id = b.id 
                             AND pb.quantity_remaining > 0 AND pb.is_active = 1
                             ORDER BY pb.received_date DESC LIMIT 1),
                            pp.cost_price, 0
                        ) AS price,
                        -- Total stock from all brands combined
                        COALESCE(SUM(
                            (SELECT SUM(pb.quantity_remaining) FROM product_batches pb 
                             WHERE pb.product_id = p.product_id AND pb.brand_id = b.id AND pb.is_active = 1)
                        ), 0) AS stock,
                        -- Products sold (only from completed/delivered orders)
                        COALESCE((
                            SELECT SUM(oi.quantity) 
                            FROM order_items oi
                            INNER JOIN orders o ON oi.order_id = o.orders_id
                            INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                            WHERE oi.product_id = p.product_id 
                            AND os.status_name IN ('Delivered','Completed','Finished')
                        ), 0) AS products_sold,
                        -- Average rating (from order_ratings via order_items)
                        COALESCE((
                            SELECT AVG(ord_rat.rating) 
                            FROM order_ratings ord_rat
                            JOIN orders o ON ord_rat.order_id = o.orders_id
                            JOIN order_items oi ON o.orders_id = oi.order_id
                            WHERE oi.product_id = p.product_id
                        ), 0) AS avg_rating,
                        -- Count of brands available
                        COUNT(DISTINCT b.id) AS brand_count,
                        (SELECT pi.image_url FROM product_images pi 
                         WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
                    FROM products p
                    LEFT JOIN uom uom ON p.uom_id = uom.uom_id
                    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                    LEFT JOIN brands b ON b.is_archived = 0
                    WHERE p.category_id = :category_id AND p.is_archive = 0 
                    AND EXISTS (
                        SELECT 1 FROM product_batches pb 
                        WHERE pb.product_id = p.product_id AND pb.brand_id = b.id AND pb.is_active = 1
                    )
                    GROUP BY p.product_id, p.product_name, p.product_description, uom.name
                    ORDER BY stock DESC, name ASC
                ");
                $stmt->execute(['category_id' => $categoryData['category_id']]);
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
            }
        }
    } else {
        // Fetch all products by default (one card per product, not per brand)
        $products = $pdo->query("
            SELECT 
                p.product_id AS id,
                p.product_name AS name,
                p.product_description AS description,
                uom.name AS uom_name,
                -- Get newest batch price for this specific brand
                COALESCE(pp.markup_price, 0) + COALESCE(
                    (SELECT pb.unit_cost FROM product_batches pb 
                     WHERE pb.product_id = p.product_id AND pb.brand_id = b.id 
                     AND pb.quantity_remaining > 0 AND pb.is_active = 1
                     ORDER BY pb.received_date DESC LIMIT 1),
                    pp.cost_price, 0
                ) AS price,
                -- Total stock from all brands combined
                COALESCE(SUM(
                    (SELECT SUM(pb.quantity_remaining) FROM product_batches pb 
                     WHERE pb.product_id = p.product_id AND pb.brand_id = b.id AND pb.is_active = 1)
                ), 0) AS stock,
                -- Products sold (only from completed/delivered orders)
                COALESCE((
                    SELECT SUM(oi.quantity) 
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.orders_id
                    INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                    WHERE oi.product_id = p.product_id 
                    AND os.status_name IN ('Delivered','Completed','Finished')
                ), 0) AS products_sold,
                        -- Average rating (from order_ratings via order_items)
                        COALESCE((
                            SELECT AVG(ord_rat.rating) 
                            FROM order_ratings ord_rat
                            JOIN orders o ON ord_rat.order_id = o.orders_id
                            JOIN order_items oi ON o.orders_id = oi.order_id
                            WHERE oi.product_id = p.product_id
                        ), 0) AS avg_rating,
                -- Count of brands available
                COUNT(DISTINCT b.id) AS brand_count,
                (SELECT pi.image_url FROM product_images pi 
                 WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
            FROM products p
            LEFT JOIN uom uom ON p.uom_id = uom.uom_id
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            LEFT JOIN brands b ON b.is_archived = 0
            WHERE p.is_archive = 0 
            AND EXISTS (
                SELECT 1 FROM product_batches pb 
                WHERE pb.product_id = p.product_id AND pb.brand_id = b.id AND pb.is_active = 1
            )
            GROUP BY p.product_id, p.product_name, p.product_description, uom.name
            ORDER BY stock DESC, name ASC
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
                COALESCE(pp.markup_price, 0) + COALESCE(
                    (SELECT pb.unit_cost FROM product_batches pb 
                     WHERE pb.product_id = p.product_id AND pb.quantity_remaining > 0 
                     ORDER BY pb.received_date DESC LIMIT 1),
                    pp.cost_price, 0
                ) AS price,
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
    
    <!-- SweetAlert2 CSS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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

        /* SweetAlert2 Custom Styles */
        .swal2-popup {
            border-radius: 1rem !important;
            font-family: 'Inter', sans-serif !important;
        }

        .swal2-title {
            font-weight: 600 !important;
        }

        .swal2-confirm {
            border: none !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            order: 1 !important; /* Left side */
        }

        .swal2-cancel {
            border: none !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            order: 2 !important; /* Right side */
        }

        .swal2-success .swal2-confirm {
            background: #198754 !important; /* Green for success */
        }

        .swal2-warning .swal2-confirm {
            background: #ffc107 !important; /* Yellow for warnings */
            color: #212529 !important;
        }

        .swal2-danger .swal2-confirm {
            background: #dc3545 !important; /* Red for delete/danger */
        }

        .swal2-info .swal2-confirm {
            background: #0dcaf0 !important; /* Blue for info */
        }

        .swal2-cancel {
            background: #6c757d !important; /* Gray for cancel */
        }

        .swal2-actions {
            justify-content: space-between !important;
            gap: 1rem !important;
        }

        /* Product Meta Styles */
        .product-meta {
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .product-meta small {
            display: inline-flex;
            align-items: center;
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
                        <?php if ((float)$product['stock'] > 10): ?>
                            <span class="product-badge in-stock">In Stock</span>
                        <?php elseif ((float)$product['stock'] > 0): ?>
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

                        <h3 class="product-title" onclick="window.location.href='product_detail.php?id=<?= $product['id'] ?>'" style="cursor: pointer;">
                            <?= htmlspecialchars($product['name']) ?>
                        </h3>

                        <div class="product-price">
                            ₱<?= number_format($product['price'], 2) ?>
                            <span class="fs-6 text-muted"> / <?= htmlspecialchars($product['uom_name'] ?? '') ?></span>
                        </div>

                        <div class="product-stock">
                            <?php if ($product['stock'] > 0): ?>
                                <span class="stock-available">
                                    Stock: <?= number_format((float)$product['stock'], 1) ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                </span>
                            <?php else: ?>
                                <span class="stock-out">Out of Stock</span>
                            <?php endif; ?>
                        </div>

                        <!-- Product Meta: Sold, Rating, Brands -->
                        <div class="product-meta mb-2">
                            <small class="text-muted">
                                <i class="fas fa-shopping-bag me-1"></i><?= number_format($product['products_sold']) ?> sold
                            </small>
                            <?php if ($product['avg_rating'] > 0): ?>
                                <small class="text-warning ms-2">
                                    <i class="fas fa-star"></i> <?= number_format($product['avg_rating'], 1) ?>
                                </small>
                            <?php else: ?>
                                <small class="text-muted ms-2">
                                    <i class="fas fa-star"></i> No rating yet
                                </small>
                            <?php endif; ?>
                            <?php if ($product['brand_count'] > 1): ?>
                                <small class="text-info ms-2">
                                    <i class="fas fa-tags me-1"></i><?= $product['brand_count'] ?> brands
                                </small>
                            <?php endif; ?>
                        </div>

                        <div class="d-flex align-items-center gap-2">
                            <button type="button" class="btn-add-cart flex-grow-1" onclick="window.location.href='product_detail.php?id=<?= $product['id'] ?>'">
                                <i class="fas fa-eye me-2"></i>View Details
                            </button>
                            <button type="button" class="btn-favorite <?= in_array($product['id'], $user_favorites) ? 'favorited' : '' ?>" data-product-id="<?= $product['id'] ?>">
                                <i class="fas fa-heart"></i>
                            </button>
                        </div>
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
                    
                    // Get product ID
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
                    
                    if (!productId) {
                        Swal.fire({
                            title: 'Error',
                            text: 'Product ID not found',
                            icon: 'error',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: '<i class="fas fa-times me-1"></i>OK',
                            customClass: {
                                popup: 'swal2-danger',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                        return;
                    }
                    
                    // Read quantity from the nearest quantity input (allow decimals like 1.5)
                    const card = this.closest('.product-card');
                    const qtyInput = card ? card.querySelector('.quantity-input') : null;
                    let quantity = qtyInput ? parseFloat(qtyInput.value) : 1;
                    
                    if (isNaN(quantity)) quantity = 1;
                    if (quantity < 1) {
                        Swal.fire({
                            title: 'Invalid Quantity',
                            text: 'Minimum quantity is 1. Please enter a valid quantity.',
                            icon: 'warning',
                            confirmButtonColor: '#ffc107',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>OK',
                            customClass: {
                                popup: 'swal2-warning',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                        return;
                    }
                    
                    // Enforce min, max, and normalize to 1 decimal place
                    const min = qtyInput && qtyInput.getAttribute('min') ? parseFloat(qtyInput.getAttribute('min')) : 0.1;
                    const max = qtyInput && qtyInput.getAttribute('max') ? parseFloat(qtyInput.getAttribute('max')) : Number.POSITIVE_INFINITY;
                    quantity = Math.max(min, Math.min(max, quantity));
                    quantity = Math.round(quantity * 10) / 10; // one decimal place
                    
                    // Reflect normalized value back to the input
                    if (qtyInput) qtyInput.value = quantity;
                    
                    addToCart(parseInt(productId, 10), quantity, this);
                });
            });
        }

        // Add to cart function with SweetAlert2
        function addToCart(productId, quantity = 1, clickedButton = null) {
            // Prevent multiple simultaneous requests
            if (isAddingToCart) {
                Swal.fire({
                    title: 'Please wait...',
                    text: 'Adding product to cart...',
                    icon: 'info',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    }
                });
                return;
            }

            isAddingToCart = true;
            const originalButtonContent = clickedButton ? clickedButton.innerHTML : '';
            
            // Show loading state on button
            if (clickedButton) {
                clickedButton.disabled = true;
                clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';
            }

            // Show loading SweetAlert2
            Swal.fire({
                title: 'Adding to Cart...',
                text: 'Please wait while we add this item to your cart.',
                allowOutsideClick: false,
                allowEscapeKey: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'swal2-info'
                }
            });

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
                    // Show success message
                    Swal.fire({
                        title: 'Added to Cart!',
                        text: 'Item has been successfully added to your cart.',
                        icon: 'success',
                        confirmButtonColor: '#198754',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Great!',
                        customClass: {
                            popup: 'swal2-success',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                    
                    updateCartBadge();
                    refreshCartContent();
                    
                    // Also refresh cart footer if cart is currently open
                    const cart = document.getElementById('slidingCart');
                    if (cart && cart.classList.contains('active')) {
                        updateCartFooter();
                    }
                } else {
                    const errorMessage = data.error || data.message || 'Failed to add product to cart';
                    
                    // Handle specific error cases
                    if (errorMessage.includes('Insufficient stock')) {
                        Swal.fire({
                            title: 'Insufficient Stock',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-boxes text-warning mb-3" style="font-size: 3rem;"></i>
                                    <p>Only <strong>${data.available_stock || 0} kg</strong> available in stock.</p>
                                    <p class="text-muted">You requested ${quantity} kg</p>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonColor: '#ffc107',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Add Available',
                            cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
                            customClass: {
                                popup: 'swal2-warning',
                                confirmButton: 'swal2-confirm',
                                cancelButton: 'swal2-cancel'
                            }
                        }).then((result) => {
                            if (result.isConfirmed && data.available_stock) {
                                // Add available quantity
                                addToCart(productId, data.available_stock, clickedButton);
                            }
                        });
                    } else if (errorMessage.includes('log in')) {
                        Swal.fire({
                            title: 'Login Required',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-user-lock text-primary mb-3" style="font-size: 3rem;"></i>
                                    <p>You need to be logged in to add items to your cart.</p>
                                    <p class="text-muted">Please log in or create an account to continue.</p>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonColor: '#7F1734',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-sign-in-alt me-1"></i>Login',
                            cancelButtonText: '<i class="fas fa-user-plus me-1"></i>Register',
                            customClass: {
                                popup: 'swal2-popup',
                                confirmButton: 'swal2-confirm',
                                cancelButton: 'swal2-cancel'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'login.php';
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                window.location.href = 'register.php';
                            }
                        });
                    } else {
                        Swal.fire({
                            title: 'Error',
                            text: errorMessage,
                            icon: 'error',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: '<i class="fas fa-times me-1"></i>OK',
                            customClass: {
                                popup: 'swal2-danger',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                    title: 'Connection Error',
                    html: `
                        <div class="text-center">
                            <i class="fas fa-wifi text-danger mb-3" style="font-size: 3rem;"></i>
                            <p>Unable to add item to cart.</p>
                            <p class="text-muted">Please check your internet connection and try again.</p>
                        </div>
                    `,
                    showCancelButton: true,
                    confirmButtonColor: '#dc3545',
                    cancelButtonColor: '#6c757d',
                    confirmButtonText: '<i class="fas fa-redo me-1"></i>Try Again',
                    cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
                    customClass: {
                        popup: 'swal2-danger',
                        confirmButton: 'swal2-confirm',
                        cancelButton: 'swal2-cancel'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Retry adding to cart
                        addToCart(productId, quantity, clickedButton);
                    }
                });
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


        // Favorite functionality with SweetAlert2
        document.querySelectorAll('.btn-favorite').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const isFavorited = this.classList.contains('favorited');
                const productCard = this.closest('.product-card');
                const productName = productCard ? productCard.querySelector('.product-title')?.textContent || 'Product' : 'Product';
                
                // Show loading state
                Swal.fire({
                    title: 'Processing...',
                    text: isFavorited ? 'Removing from favorites...' : 'Adding to favorites...',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: () => {
                        Swal.showLoading();
                    },
                    customClass: {
                        popup: 'swal2-info'
                    }
                });
                
                fetch('favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + productId + '&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>'
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.is_favorite) {
                        // Added to favorites - Success style
                        Swal.fire({
                            title: 'Added to Favorites!',
                            text: 'Item has been successfully added to your favorites.',
                            icon: 'success',
                            confirmButtonColor: '#198754',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Great!',
                            customClass: {
                                popup: 'swal2-success',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                        
                        // Update button appearance
                        this.classList.add('favorited');
                        this.style.background = 'var(--bs-danger)';
                        this.style.color = 'white';
                        this.style.borderColor = 'var(--bs-danger)';
                        
                    } else if (data.success && !data.is_favorite) {
                        // Removed from favorites - Success style
                        Swal.fire({
                            title: 'Removed from Favorites',
                            text: 'Item has been successfully removed from your favorites.',
                            icon: 'success',
                            confirmButtonColor: '#198754',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Got it!',
                            customClass: {
                                popup: 'swal2-success',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                        
                        // Update button appearance
                        this.classList.remove('favorited');
                        this.style.background = 'white';
                        this.style.color = 'var(--bs-secondary)';
                        this.style.borderColor = 'var(--bs-secondary)';
                        
                    } else if (!data.success && data.message && data.message.includes('log in')) {
                        // Login required
                        Swal.fire({
                            title: 'Login Required',
                            html: `
                                <div class="text-center">
                                    <i class="fas fa-user-lock text-primary mb-3" style="font-size: 3rem;"></i>
                                    <p>You need to be logged in to manage your favorites.</p>
                                    <p class="text-muted">Please log in or create an account to continue.</p>
                                </div>
                            `,
                            showCancelButton: true,
                            confirmButtonColor: '#7F1734',
                            cancelButtonColor: '#6c757d',
                            confirmButtonText: '<i class="fas fa-sign-in-alt me-1"></i>Login',
                            cancelButtonText: '<i class="fas fa-user-plus me-1"></i>Register',
                            customClass: {
                                popup: 'swal2-popup',
                                confirmButton: 'swal2-confirm',
                                cancelButton: 'swal2-cancel'
                            }
                        }).then((result) => {
                            if (result.isConfirmed) {
                                window.location.href = 'login.php';
                            } else if (result.dismiss === Swal.DismissReason.cancel) {
                                window.location.href = 'register.php';
                            }
                        });
                    } else {
                        // General error
                        Swal.fire({
                            title: 'Error',
                            text: data.message || 'Something went wrong. Please try again.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: '<i class="fas fa-times me-1"></i>OK',
                            customClass: {
                                popup: 'swal2-danger',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire({
                        title: 'Connection Error',
                        html: `
                            <div class="text-center">
                                <i class="fas fa-wifi text-danger mb-3" style="font-size: 3rem;"></i>
                                <p>Unable to update favorites.</p>
                                <p class="text-muted">Please check your internet connection and try again.</p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-redo me-1"></i>Try Again',
                        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
                        customClass: {
                            popup: 'swal2-danger',
                            confirmButton: 'swal2-confirm',
                            cancelButton: 'swal2-cancel'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Retry the favorite action
                            this.click();
                        }
                    });
                });
            });
        });

    </script>
</body>
</html>