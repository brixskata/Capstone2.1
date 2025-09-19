<?php
session_start();
include 'includes/db.php';

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch 8 featured products (not archived, in stock, ordered by is_hot/is_new/created_at)
$featuredProducts = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.product_id AS id,
            p.product_name AS name,
            p.product_description AS description,
            c.category_name,
            b.name AS brand_name,
            uom.name AS uom_name,
            COALESCE(ps.current_stock, 0) AS stock,
            COALESCE(pp.selling_price, 0) AS price,
            COALESCE(pp.cost_price, 0) AS cost_price,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1,
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE p.is_archive = 0 AND COALESCE(ps.current_stock, 0) > 0
        ORDER BY p.created_at DESC
        LIMIT 8
    ");
    $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featuredProducts = [];
}

// Fetch customer testimonials from order ratings
$testimonials = [];
try {
    $stmt = $pdo->query("
        SELECT 
            o.rating,
            o.review,
            o.created_at,
            u.username,
            COALESCE(ui.first_name, u.username) as first_name,
            COALESCE(ui.last_name, '') as last_name,
            ui.profile_picture
        FROM order_ratings o
        JOIN users u ON o.user_id = u.user_id
        LEFT JOIN user_info ui ON u.user_id = ui.user_id
        WHERE o.review IS NOT NULL AND TRIM(o.review) != ''
        ORDER BY o.created_at DESC
        LIMIT 3
    ");
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $testimonials = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="MikeMadz - Premium meat and seafood delivery. Fresh beef, chicken, fish, and seafood delivered to your doorstep. Quality guaranteed with nationwide delivery.">
    <meta name="keywords" content="meat delivery, fresh beef, chicken, fish, seafood, online meat shop, MikeMadz">
    <meta name="author" content="MikeMadz">
    <meta property="og:title" content="MikeMadz - Premium Meat & Seafood Delivery">
    <meta property="og:description" content="Fresh, quality meat and seafood delivered to your doorstep. Shop beef, chicken, fish, and more.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://mikemadz.com">
    <title>MikeMadz - Premium Meat & Seafood Delivery | Fresh Quality Products</title>

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
            --bs-danger: #db3030;
            --bs-warning: #ffc107;
            --bs-info: #016bf8;
            --bs-light: #f0f3f2;
            --bs-dark: #001e2b;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        /* Prevent layout shifts */
        .row {
            margin-left: 0;
            margin-right: 0;
        }

        .col-md-6, .col-lg-3 {
            padding-left: 0.75rem;
            padding-right: 0.75rem;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            line-height: 1.6;
            color: var(--bs-dark);
            background-color: #ffffff;
            overflow-x: hidden;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        /* Smooth scrolling */
        html {
            scroll-behavior: smooth;
        }

        .btn, .product-card, .floating-card {
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
        }

      
        .product-title, .product-desc {
            -webkit-user-select: text;
            -moz-user-select: text;
            -ms-user-select: text;
            user-select: text;
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

        /* Hero Section - FreshCart Style */
        .hero-section {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            padding: 5rem 0;
            position: relative;
            overflow: hidden;
        }

        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image: 
                radial-gradient(circle at 25% 25%, rgba(127,23,52,0.05) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(219,48,48,0.03) 0%, transparent 50%);
            z-index: 1;
        }

        .hero-content {
            position: relative;
            z-index: 2;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            background: #e8f5e8;
            color: var(--bs-success);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }

        .hero-title {
            font-size: 3.5rem;
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.5rem;
            color: var(--bs-dark);
        }

        .hero-title .text-highlight {
            color: var(--bs-secondary);
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .hero-features {
            display: flex;
            flex-wrap: wrap;
            gap: 2rem;
            margin-bottom: 2.5rem;
        }

        .hero-feature {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            color: var(--bs-dark);
        }

        .hero-feature i {
            color: var(--bs-secondary);
            font-size: 1.1rem;
        }

        .btn-hero {
            padding: 1rem 2rem;
            font-weight: 600;
            border-radius: 0.5rem;
            border: none;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-hero-primary {
            background: var(--bs-secondary);
            color: white;
        }

        .btn-hero-primary:hover {
            background: #6b1429;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127,23,52,0.3);
        }

        .btn-hero-outline {
            background: transparent;
            color: var(--bs-secondary);
            border: 2px solid var(--bs-secondary);
        }

        .btn-hero-outline:hover {
            background: var(--bs-secondary);
            color: white;
        }

        .hero-image-container {
            position: relative;
            text-align: center;
        }

        .hero-main-image {
            max-width: 100%;
            height: auto;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        /* Floating Elements - Simplified */
        .floating-card {
            position: absolute;
            background: white;
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 180px;
            animation: float 6s ease-in-out infinite;
            z-index: 1;
        }

        .floating-card.card-1 {
            top: 10%;
            left: -10%;
            animation-delay: 0s;
        }

        .floating-card.card-2 {
            top: 60%;
            right: -15%;
            animation-delay: 2s;
        }

        .floating-card.card-3 {
            bottom: 10%;
            left: -5%;
            animation-delay: 4s;
        }

        .floating-card img {
            width: 100%;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .floating-card h6 {
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: var(--bs-dark);
        }

        .floating-card .price {
            color: var(--bs-secondary);
            font-weight: 700;
            font-size: 0.9rem;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-20px); }
        }

        /* Mobile optimizations */
        @media (max-width: 768px) {
            .floating-card {
                display: none;
            }
            
            .hero-section {
                padding: 3rem 0;
            }
            
            .product-card {
                margin-bottom: 1rem;
            }
            
            .product-image {
                height: 150px;
            }
        }

        @media (max-width: 576px) {
            .hero-section {
                padding: 2rem 0;
            }
            
            .product-card {
                padding: 1rem;
            }
            
            .product-image {
                height: 120px;
            }
        }

        /* Categories Section */
        .categories-section {
            padding: 5rem 0;
            background: #ffffff;
        }

        .section-header {
            text-align: center;
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--bs-dark);
            margin-bottom: 1rem;
        }

        .section-subtitle {
            font-size: 1.1rem;
            color: #6c757d;
            max-width: 600px;
            margin: 0 auto;
        }

        .category-card {
            background: white;
            border-radius: 1rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid #e9ecef;
            height: 200px;
            position: relative;
            display: flex;
            align-items: end;
        }

        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .category-card-content {
            position: relative;
            z-index: 2;
            padding: 1.5rem;
            color: white;
            width: 100%;
            background: linear-gradient(transparent, rgba(0,0,0,0.7));
        }

        .category-card-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .category-card-desc {
            font-size: 0.9rem;
            opacity: 0.9;
        }

        /* Featured Products Section */
        .featured-section {
            padding: 5rem 0;
            background: var(--bs-light);
        }

        .product-card {
            background: white;
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            position: relative;
            display: flex;
            flex-direction: column;
            overflow: hidden;
            will-change: transform;
            backface-visibility: hidden;
            transform: translateZ(0);
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .product-badge {
            position: absolute;
            top: 1rem;
            left: 1rem;
            background: var(--bs-secondary);
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
            object-fit: cover;
            border-radius: 0.75rem;
            margin-bottom: 1rem;
            background-color: #f8f9fa;
            flex-shrink: 0;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--bs-dark);
            line-height: 1.4;
            word-wrap: break-word;
            overflow-wrap: break-word;
            hyphens: auto;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
            text-rendering: optimizeLegibility;
        }

        .product-desc {
            font-size: 0.9rem;
            color: #6c757d;
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .product-stock {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 1rem;
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

        .add-to-cart-form {
            margin-top: auto;
            padding-top: 1rem;
        }

        .btn-add-cart {
            width: 100%;
            background: var(--bs-secondary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-add-cart:hover {
            background: #6b1429;
            color: white;
            transform: translateY(-2px);
        }

        .btn-add-cart:active {
            transform: translateY(0);
        }

        /* Features Section */
        .features-section {
            padding: 5rem 0;
            background: white;
        }

        .feature-card {
            text-align: center;
            padding: 2rem 1rem;
        }

        .feature-icon {
            width: 80px;
            height: 80px;
            background: var(--bs-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .feature-title {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            color: var(--bs-dark);
        }

        .feature-desc {
            color: #6c757d;
            line-height: 1.6;
        }

        /* Testimonials Section */
        .testimonials-section {
            padding: 5rem 0;
            background: var(--bs-light);
        }

        .testimonial-card {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            border: 1px solid #e9ecef;
            height: 100%;
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .testimonial-avatar {
            width: 50px;
            height: 50px;
            background: var(--bs-secondary);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            margin-right: 1rem;
            flex-shrink: 0;
        }

        .testimonial-avatar img {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            object-fit: cover;
        }

        .testimonial-rating {
            color: var(--bs-warning);
            margin-top: 0.25rem;
        }

        .testimonial-text {
            color: #6c757d;
            font-style: italic;
            line-height: 1.6;
        }

        /* Footer */
        .footer {
            background: var(--bs-dark);
            color: white;
            padding: 3rem 0 1rem;
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
        }

        .footer-link:hover {
            color: white;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .hero-title {
                font-size: 2.5rem;
            }

            .floating-card {
                display: none;
            }

            .hero-features {
                flex-direction: column;
                gap: 1rem;
            }

            .section-title {
                font-size: 2rem;
            }
        }
       
        
    </style>
</head>
<body>
<!-- Promo Banner -->
    <?php include 'includes/user_promo.php'; ?>
    
   

    <!-- Navigation -->
    <?php include 'includes/user_navbar.php'; ?>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="hero-pattern"></div>
        <div class="container">
            <div class="row align-items-center min-vh-75">
                <div class="col-lg-6">
                    <div class="hero-content">
                        <div class="hero-badge">
                            <i class="fas fa-leaf me-2"></i>
                            100% Fresh & Organic
                        </div>

                        <h1 class="hero-title">
                            Groceries delivered in <span class="text-highlight">90 minutes</span>
                        </h1>

                        <p class="hero-subtitle">
                            Get your healthy foods & snacks delivered at your doorsteps all day everyday. 
                            Fresh meat, seafood, and quality products guaranteed.
                        </p>

                        <div class="hero-features">
                            <div class="hero-feature">
                                <i class="fas fa-shipping-fast"></i>
                                <span>Free Delivery</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-medal"></i>
                                <span>Premium Quality</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-clock"></i>
                                <span>90 Min Delivery</span>
                            </div>
                        </div>

                        <div class="d-flex gap-3 flex-wrap">
                            <a href="product.php" class="btn-hero btn-hero-primary">
                                <i class="fas fa-shopping-cart"></i>
                                Start Shopping
                            </a>
                            <a href="#featured" class="btn-hero btn-hero-outline">
                                <i class="fas fa-play"></i>
                                See Offers
                            </a>
                        </div>
                    </div>
                </div>

                <div class="col-lg-6">
                    <div class="hero-image-container">
                        <!-- Floating Cards -->
                        <div class="floating-card card-1">
                            <img src="images/beef1.jpg" alt="Premium Beef">
                            <h6>Premium Beef</h6>
                            <div class="price">₱599/kg</div>
                        </div>

                        <div class="floating-card card-2">
                            <img src="images/bangus.jpg" alt="Fresh Fish">
                            <h6>Fresh Bangus</h6>
                            <div class="price">₱299/kg</div>
                        </div>

                        <div class="floating-card card-3">
                            <img src="images/breast.jpg" alt="Chicken">
                            <h6>Chicken Breast</h6>
                            <div class="price">₱199/kg</div>
                        </div>

                        <img src="images/beef2.jpg" alt="Fresh Groceries" class="hero-main-image">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Categories Section -->
    <section class="categories-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Shop by Category</h2>
                <p class="section-subtitle">Discover our premium selection of fresh meat and seafood, carefully curated for quality and taste.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <a href="product.php?category=beef" class="text-decoration-none">
                        <div class="category-card" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('images/beefflank.jpg'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">BEEF</h3>
                                <p class="category-card-desc">Premium cuts & quality</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="product.php?category=chicken" class="text-decoration-none">
                        <div class="category-card" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('images/breast.jpg'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">CHICKEN</h3>
                                <p class="category-card-desc">Fresh & tender cuts</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="product.php?category=pork" class="text-decoration-none">
                        <div class="category-card" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('images/porkribs.jpg'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">PORK</h3>
                                <p class="category-card-desc">Quality pork cuts</p>
                            </div>
                        </div>
                    </a>
                </div>
                <div class="col-md-6 col-lg-3">
                    <a href="product.php?category=fish" class="text-decoration-none">
                        <div class="category-card" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('images/bangus.jpg'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">FISH</h3>
                                <p class="category-card-desc">Fresh from the sea</p>
                            </div>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Featured Products -->
    <section id="featured" class="featured-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Popular Products</h2>
                <p class="section-subtitle">Hand-picked premium selections for your table</p>
            </div>

            <div class="row g-4">
                <?php if (empty($featuredProducts)): ?>
                    <div class="col-12 text-center text-muted">No featured products available at the moment.</div>
                <?php else: ?>
                    <?php foreach ($featuredProducts as $product): ?>
                        <div class="col-md-6 col-lg-3">
                            <div class="product-card">
                                    <div class="product-badge">
                                        Featured
                                    </div>

                                    <img data-src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image lazy lazy-placeholder" onerror="this.src='images/placeholder.jpg'" onclick="window.location.href='product_detail.php?id=<?= $product['id'] ?>'" style="cursor: pointer;">

                                    <h3 class="product-title" onclick="window.location.href='product_detail.php?id=<?= $product['id'] ?>'" style="cursor: pointer;"><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></h3>
                                    <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>

                                    <div class="product-price">₱<?= number_format($product['price'], 2) ?></div>
                                    <div class="product-stock">Stock: <?= (int)$product['stock'] ?> available</div>

                                    <div class="mt-3">
                                        <button type="button" class="btn-add-cart" onclick="addToCart(<?= $product['id'] ?>, 1)">
                                            <i class="fas fa-cart-plus me-2"></i>Add to Cart
                                        </button>
                                    </div>
                                </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Why Choose MikeMadz?</h2>
                <p class="section-subtitle">We're committed to delivering the highest quality products with exceptional service to your doorstep.</p>
            </div>

            <div class="row g-4">
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <h3 class="feature-title">Express Delivery</h3>
                        <p class="feature-desc">Fast & reliable shipping nationwide. Get your fresh meat delivered within 24-48 hours.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-shield-alt"></i>
                        </div>
                        <h3 class="feature-title">Secure Payment</h3>
                        <p class="feature-desc">100% secure transactions with multiple payment options. Your data is always protected.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-award"></i>
                        </div>
                        <h3 class="feature-title">Quality Products</h3>
                        <p class="feature-desc">Premium cuts sourced from trusted suppliers. Quality guaranteed with every order.</p>
                    </div>
                </div>
                <div class="col-md-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <h3 class="feature-title">Affordable Prices</h3>
                        <p class="feature-desc">Competitive pricing with regular promotions and discounts for our valued customers.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Testimonials Section -->
    <section class="testimonials-section">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">What Our Customers Say</h2>
                <p class="section-subtitle">Don't just take our word for it - hear from our satisfied customers.</p>
            </div>

            <div class="row g-4">
                <?php if (empty($testimonials)): ?>
                    <!-- Fallback testimonials if no real ratings exist -->
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <div class="testimonial-header">
                                <div class="testimonial-avatar">M</div>
                                <div>
                                    <h4 class="mb-1">Marion Brix</h4>
                                    <div class="testimonial-rating">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="testimonial-text">"Nice Nice!"</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <div class="testimonial-header">
                                <div class="testimonial-avatar">J</div>
                                <div>
                                    <h4 class="mb-1">Jay</h4>
                                    <div class="testimonial-rating">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="testimonial-text">"Angas!"</p>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="testimonial-card">
                            <div class="testimonial-header">
                                <div class="testimonial-avatar">E</div>
                                <div>
                                    <h4 class="mb-1">Ekko</h4>
                                    <div class="testimonial-rating">
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                        <i class="fas fa-star"></i>
                                    </div>
                                </div>
                            </div>
                            <p class="testimonial-text">"Solid"</p>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($testimonials as $testimonial): ?>
                        <div class="col-md-4">
                            <div class="testimonial-card">
                                <div class="testimonial-header">
                                    <div class="testimonial-avatar">
                                        <?php if (!empty($testimonial['profile_picture'])): ?>
                                            <img src="<?= htmlspecialchars($testimonial['profile_picture']) ?>" alt="<?= htmlspecialchars($testimonial['first_name']) ?>" style="width: 50px; height: 50px; border-radius: 50%; object-fit: cover;">
                                        <?php else: ?>
                                            <?= strtoupper(substr($testimonial['first_name'], 0, 1)) ?>
                                        <?php endif; ?>
                                    </div>
                                    <div>
                                        <h4 class="mb-1"><?= htmlspecialchars(trim($testimonial['first_name'] . ' ' . $testimonial['last_name'])) ?></h4>
                                        <div class="testimonial-rating">
                                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                                <i class="fas fa-star <?= $i <= $testimonial['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                            <?php endfor; ?>
                                        </div>
                                        <small class="text-muted"><?= date('M d, Y', strtotime($testimonial['created_at'])) ?></small>
                                    </div>
                                </div>
                                <p class="testimonial-text">"<?= htmlspecialchars($testimonial['review']) ?>"</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Add to cart functionality
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const button = this.querySelector('.btn-add-cart');
                const originalText = button.innerHTML;
                
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding...';

                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart badge and show/hide based on product count
                        const cartBadge = document.querySelector('.cart-badge');
                        if (cartBadge) {
                            cartBadge.textContent = data.cart_count;
                            if (data.cart_count > 0) {
                                cartBadge.style.display = 'flex';
                            } else {
                                cartBadge.style.display = 'none';
                            }
                        } else if (data.cart_count > 0) {
                            // Create badge if it doesn't exist and there are items
                            const cartButton = document.querySelector('[onclick="toggleCart()"]');
                            if (cartButton) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'cart-badge';
                                newBadge.textContent = data.cart_count;
                                cartButton.appendChild(newBadge);
                            }
                        }

                        // Refresh cart content
                        refreshCartContent();

                        // Show success message
                        showToast('Product added to cart!', 'success');
                        
                        // Reset button
                        button.disabled = false;
                        button.innerHTML = originalText;
                    } else {
                        showToast(data.error || 'Failed to add product to cart', 'error');
                        button.disabled = false;
                        button.innerHTML = originalText;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    showToast('Network error. Please try again.', 'error');
                    button.disabled = false;
                    button.innerHTML = originalText;
                });
            });
        });

        // Function to refresh cart content
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
                            // Create footer if it doesn't exist
                            const slidingCart = document.querySelector('.sliding-cart');
                            if (slidingCart) {
                                slidingCart.appendChild(newCartFooter);
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

        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                }
            });
        }, observerOptions);

        // Observe elements for animation
        document.querySelectorAll('.category-card, .product-card, .feature-card, .testimonial-card').forEach(el => {
            el.style.opacity = '0';
            el.style.transform = 'translateY(20px)';
            el.style.transition = 'opacity 0.6s ease, transform 0.6s ease';
            observer.observe(el);
        });

        // Add to cart function
        function addToCart(productId, quantity) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'add');
            formData.append('quantity', quantity);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            
            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showToast('Product added to cart!', 'success');
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
    </script>
    
    <!-- Lazy Loading Script -->
    <script src="includes/lazy_loading.js"></script>
</body>
</html>