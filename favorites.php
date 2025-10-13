<?php
session_start();
include 'includes/db.php';

// Check login
if (!isset($_SESSION['user_id'])) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo 'not_logged_in';
        exit;
    }
    header('Location: login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Handle AJAX favorite toggle
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['product_id'])) {
    $productId = (int) $_POST['product_id'];
    $action = $_POST['action'] ?? 'toggle';

    if ($action === 'remove') {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
        echo $stmt->execute([$userId, $productId]) ? 'removed' : 'error';
        exit;
    }

    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND product_id = ?");
    $stmt->execute([$userId, $productId]);

    if ($stmt->fetch()) {
        $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$userId, $productId]);
        echo 'removed';
    } else {
        $stmt = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
        $stmt->execute([$userId, $productId]);
        echo 'added';
    }
    exit;
}

// Fetch favorites for page load
$stmt = $pdo->prepare("
    SELECT 
        p.product_id AS id,
        p.product_name AS name,
        p.product_description AS description,
        c.category_name,
        b.name AS brand_name,
        uom.name AS uom_name,
        COALESCE(ps.current_stock, 0) AS stock,
        -- Calculate total price: markup_price + (best available cost from batches or general cost_price)
        COALESCE(pp.markup_price, 0) + COALESCE((
            SELECT COALESCE(
                (SELECT pb.unit_cost 
                 FROM product_batches pb 
                 WHERE pb.product_id = p.product_id 
                 AND pb.quantity_remaining > 0 
                 AND pb.is_active = 1
                 ORDER BY pb.expiration_date ASC 
                 LIMIT 1),
                pp.cost_price, 
                0
            )
        ), 0) AS price,
        -- Get lowest price among all brands for this product
        MIN(COALESCE(pp.markup_price, 0) + COALESCE(
            (SELECT pb.unit_cost 
             FROM product_batches pb 
             WHERE pb.product_id = p.product_id 
             AND pb.quantity_remaining > 0 
             AND pb.is_active = 1
             ORDER BY pb.expiration_date ASC 
             LIMIT 1),
            pp.cost_price, 
            0
        )) AS lowest_price,
        -- Count products sold (only from completed/delivered orders)
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
        -- Rating count
        COALESCE((
            SELECT COUNT(ord_rat.rating_id) 
            FROM order_ratings ord_rat
            JOIN orders o ON ord_rat.order_id = o.orders_id
            JOIN order_items oi ON o.orders_id = oi.order_id
            WHERE oi.product_id = p.product_id
        ), 0) AS rating_count,
        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
    FROM favorites f
    INNER JOIN products p ON f.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN uom uom ON p.uom_id = uom.uom_id
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
    WHERE f.user_id = ? AND p.is_archive = 0
    GROUP BY p.product_id, p.product_name, p.product_description, c.category_name, b.name, uom.name, ps.current_stock, pp.markup_price, pp.cost_price
    ORDER BY p.product_name ASC
");
$stmt->execute([$userId]);
$favorites = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Favorites - BeefScrap</title>
    <link rel="icon" type="image/png" href="favicon.png">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
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
            background: linear-gradient(135deg, var(--bs-light) 0%, #ffffff 100%);
            min-height: 100vh;
            padding-bottom: 100px; /* Add padding to prevent footer overlap */
        }

        /* Promo Banner */
        .promo-banner {
            background: var(--bs-secondary);
            color: white;
            padding: 0.75rem 0;
            font-weight: 500;
            font-size: 0.9rem;
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
            color: var(--bs-secondary) !important;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--bs-dark) !important;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .navbar-nav .nav-link:hover {
            color: var(--bs-secondary) !important;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 0.5rem;
        }

        .page-subtitle {
            color: #6c757d;
            font-size: 1.1rem;
        }

        /* Product Cards */
        .product-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: 0.5s;
        }

        .product-card:hover::before {
            left: 100%;
        }

        .favorite-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: var(--bs-danger);
            color: white;
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
            z-index: 3;
        }

        .product-image {
            width: 100%;
            height: 200px;
            object-fit: contain;
            background-color: var(--bs-light);
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            border: 1px solid #e9ecef;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--bs-secondary);
            display: -webkit-box;
            -webkit-line-clamp: 2;
            line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
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
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .product-meta {
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: #6c757d;
        }

        .product-stock {
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .product-sold {
            font-weight: 500;
            display: flex;
            align-items: center;
        }

        .product-stock i,
        .product-sold i {
            color: var(--bs-secondary);
            font-size: 0.75rem;
        }

        .product-rating {
            margin-bottom: 1rem;
        }

        .product-rating .text-warning {
            color: #ffc107 !important;
        }

        .product-rating .text-muted {
            color: #6c757d !important;
        }

        .product-rating small {
            font-size: 0.8rem;
        }

        .product-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
            justify-content: center;
        }

        .btn-remove-favorite {
            background: var(--bs-danger);
            color: white;
            border: none;
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-remove-favorite:hover {
            background: #bb2d3b;
            color: white;
            transform: scale(1.1);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .empty-state i {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            color: #dee2e6;
        }

        .empty-state h3 {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--bs-dark);
        }

        .empty-state p {
            color: #6c757d;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .btn-shop-now {
            background: var(--bs-secondary);
            color: white;
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-shop-now:hover {
            background: #6b1429;
            color: white;
            transform: translateY(-2px);
        }

        /* Favorites Stats */
        .favorites-stats {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .stats-item {
            text-align: center;
        }

        .stats-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-secondary);
        }

        .stats-label {
            color: #6c757d;
            font-size: 0.9rem;
            font-weight: 500;
        }

        /* Main content wrapper */
        .main-content-wrapper {
            min-height: calc(100vh - 200px); /* Ensure minimum height */
            padding-bottom: 2rem;
        }

        /* Footer spacing */
        .footer {
            margin-top: auto;
            position: relative;
            z-index: 1;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .page-title {
                font-size: 1.5rem;
            }

            .favorites-stats {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .product-actions {
                flex-direction: column;
            }

            .btn-remove-favorite {
                width: 100%;
            }

            /* Mobile: Stack stock and sold vertically on small screens */
            .product-meta {
                flex-direction: column;
                gap: 0.5rem;
                align-items: flex-start !important;
            }

            .product-stock,
            .product-sold {
                width: 100%;
                justify-content: flex-start;
            }

            body {
                padding-bottom: 120px; /* More padding on mobile */
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/user_promo.php'; ?>

    <?php include 'includes/user_navbar.php'; ?>

    <!-- Main Content -->
    <div class="main-content-wrapper">
        <div class="container mt-4 mb-5">
        <!-- Page Header -->
        <div class="page-header">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h1 class="page-title">
                        <i class="fas fa-heart text-danger me-2"></i>
                        My Favorites
                    </h1>
                    <p class="page-subtitle">Your collection of favorite products</p>
                </div>
                <div class="col-md-4">
                    <div class="text-end">
                        <a href="product.php" class="btn-shop-now">
                            <i class="fas fa-shopping-bag"></i>
                            Continue Shopping
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <?php if (count($favorites) > 0): ?>
            <!-- Products Grid -->
            <div class="row g-4">
                <?php foreach ($favorites as $product): ?>
                    <div class="col-md-6 col-lg-4 col-xl-3">
                        <div class="product-card">
                            <!-- Favorite Badge -->
                            <span class="favorite-badge">
                                <i class="fas fa-heart"></i>
                                Favorite
                            </span>

                            <!-- Product Image -->
                            <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/beefscrap.png' ?>" 
                                 alt="<?= htmlspecialchars($product['name']) ?>" 
                                 class="product-image"
                                 onerror="this.src='images/beefscrap.png'">

                            <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                            <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>

                            <!-- Product Rating -->
                            <div class="product-rating mb-2">
                                <?php if ($product['avg_rating'] > 0): ?>
                                    <div class="d-flex align-items-center">
                                        <div class="text-warning me-2">
                                            <?php 
                                            $rounded_rating = max(0, min(5, round($product['avg_rating']))); 
                                            for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $rounded_rating ? 'text-warning' : 'text-muted' ?>" style="font-size: 0.9rem;"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted">
                                            <span class="fw-semibold"><?= number_format($product['avg_rating'], 1) ?></span>
                                            <span class="ms-1">(<?= $product['rating_count'] ?>)</span>
                                        </small>
                                    </div>
                                <?php else: ?>
                                    <div class="text-muted">
                                        <i class="fas fa-star text-muted" style="font-size: 0.9rem;"></i>
                                        <small class="ms-1">No rating yet</small>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="product-price">
                                From ₱<?= number_format($product['lowest_price'], 2) ?>
                            </div>

                            <!-- Stock and Sold Info - Aligned horizontally -->
                            <div class="product-meta d-flex justify-content-between align-items-center mb-3">
                                <div class="product-stock">
                                    <i class="fas fa-boxes me-1"></i>
                                    Stock: <?= number_format((float)$product['stock'], 1) ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                </div>
                                <div class="product-sold">
                                    <i class="fas fa-shopping-bag me-1"></i>
                                    <?= number_format($product['products_sold']) ?> sold
                                </div>
                            </div>

                            <!-- Product Actions -->
                            <div class="product-actions">
                                <button type="button" class="btn-remove-favorite" data-product-id="<?= $product['id'] ?>" title="Remove from Favorites">
                                    <i class="fas fa-heart-broken"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <!-- Empty State -->
            <div class="empty-state">
                <i class="fas fa-heart-broken"></i>
                <h3>No Favorites Yet</h3>
                <p>You haven't added any products to your favorites yet. Start browsing and add items you love!</p>
                <a href="product.php" class="btn-shop-now">
                    <i class="fas fa-shopping-bag"></i>
                    Start Shopping
                </a>
            </div>
        <?php endif; ?>

        </div>
    </div>

    <!-- Footer -->
    <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        document.querySelectorAll('.btn-remove-favorite').forEach(btn => {
            btn.addEventListener('click', function () {
                const productId = this.getAttribute('data-product-id');
                const card = this.closest('.col-md-6, .col-lg-4, .col-xl-3');

                fetch('favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: `product_id=${productId}&action=remove&csrf_token=<?= $_SESSION['csrf_token'] ?? '' ?>`
                })
                .then(res => res.json())
                .then(data => {
                    if (data.success && !data.is_favorite) {
                        // Fade out effect before removing
                        card.style.transition = 'opacity 0.4s';
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();

                            // Update favorites stats
                            const countEl = document.querySelector('.favorites-stats .stats-item:first-child .stats-number');
                            if (countEl) {
                                const newCount = document.querySelectorAll('.product-card').length;
                                countEl.textContent = newCount;
                            }

                            // If no favorites left, show empty state
                            if (document.querySelectorAll('.product-card').length === 0) {
                                document.querySelector('.container.mt-4').innerHTML = `
                                    <div class="page-header">
                                        <div class="row align-items-center">
                                            <div class="col-md-8">
                                                <h1 class="page-title">
                                                    <i class="fas fa-heart text-danger me-2"></i>
                                                    My Favorites
                                                </h1>
                                                <p class="page-subtitle">Your collection of favorite products</p>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-end">
                                                    <a href="product.php" class="btn-shop-now">
                                                        <i class="fas fa-shopping-bag"></i>
                                                        Continue Shopping
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="empty-state">
                                        <i class="fas fa-heart-broken"></i>
                                        <h3>No Favorites Yet</h3>
                                        <p>You haven't added any products to your favorites yet. Start browsing and add items you love!</p>
                                        <a href="product.php" class="btn-shop-now">
                                            <i class="fas fa-shopping-bag"></i>
                                            Start Shopping
                                        </a>
                                    </div>
                                `;
                            }
                        }, 400);
                    }
                });
            });
        });
    </script>
</body>
</html>