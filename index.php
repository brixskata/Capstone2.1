<?php
session_start();
include 'includes/db.php';

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


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
<?php
$page_title = 'MikeMadz - Premium Meat & Seafood Delivery | Fresh Quality Products';
$page_description = 'MikeMadz - Premium meat and seafood delivery. Fresh beef, chicken, fish, and seafood delivered to your doorstep. Quality guaranteed with nationwide delivery.';
$page_keywords = 'meat delivery, fresh beef, chicken, fish, seafood, online meat shop, MikeMadz';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/user_head.php'; ?>
    <title><?= htmlspecialchars($page_title) ?></title>

    <!-- Google Fonts for enhanced typography -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

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

            /* Modern enhancement variables */
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-2xl: 0 25px 50px -12px rgba(0, 0, 0, 0.25);

            --gradient-primary: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            --gradient-light: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            --gradient-overlay: linear-gradient(transparent, rgba(0,0,0,0.7));

            --border-radius-sm: 0.375rem;
            --border-radius-md: 0.5rem;
            --border-radius-lg: 1rem;
            --border-radius-xl: 1.5rem;

            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-normal: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-slow: all 0.5s cubic-bezier(0.4, 0, 0.2, 1);
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

        /* Hero Section - Modern Enhanced */
        .hero-section {
            background: var(--gradient-light);
            padding: 6rem 0 4rem;
            position: relative;
            overflow: hidden;
            min-height: 80vh;
            display: flex;
            align-items: center;
        }

        .hero-pattern {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 25% 25%, rgba(127,23,52,0.08) 0%, transparent 50%),
                radial-gradient(circle at 75% 75%, rgba(219,48,48,0.05) 0%, transparent 50%),
                radial-gradient(circle at 50% 50%, rgba(25,135,84,0.03) 0%, transparent 70%);
            z-index: 1;
            animation: patternShift 20s ease-in-out infinite;
        }

        @keyframes patternShift {
            0%, 100% { transform: translateX(0) translateY(0); }
            25% { transform: translateX(-10px) translateY(10px); }
            50% { transform: translateX(10px) translateY(-10px); }
            75% { transform: translateX(-5px) translateY(5px); }
        }

        .hero-content {
            position: relative;
            z-index: 2;
            animation: slideInLeft 1s ease-out;
        }

        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            background: linear-gradient(135deg, #e8f5e8 0%, #d1f2d1 100%);
            color: var(--bs-success);
            padding: 0.75rem 1.25rem;
            border-radius: var(--border-radius-xl);
            font-size: 0.95rem;
            font-weight: 700;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
            border: 1px solid rgba(25, 135, 84, 0.1);
            animation: badgePulse 2s ease-in-out infinite;
        }

        @keyframes badgePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        .hero-title {
            font-family: 'Poppins', sans-serif;
            font-size: clamp(2.5rem, 5.5vw, 4rem);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 1.2rem;
            color: var(--bs-dark);
            letter-spacing: -0.02em;
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.1), 0 4px 8px rgba(0, 0, 0, 0.05);
            background: linear-gradient(135deg, var(--bs-dark) 0%, #4a5568 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            animation: titleFadeIn 1.2s ease-out 0.2s both;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
            position: relative;
        }

        .hero-title::before {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 3px;
            background: var(--gradient-primary);
            transition: width 0.4s ease;
        }

        .hero-title:hover {
            transform: scale(1.02) translateY(-2px);
            text-shadow: 0 4px 8px rgba(0, 0, 0, 0.15), 0 8px 16px rgba(0, 0, 0, 0.1);
            filter: brightness(1.1);
        }

        .hero-title:hover::before {
            width: 100%;
        }

        .hero-title:active {
            animation: titlePulse 0.6s ease-in-out;
        }

        @keyframes titlePulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.05); }
        }

        @keyframes titleFadeIn {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-title .text-highlight {
            background: var(--gradient-primary);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-subtitle {
            font-family: 'Poppins', sans-serif;
            font-size: 1.15rem;
            color: #6c757d;
            margin-bottom: 1.5rem;
            line-height: 1.6;
            font-weight: 400;
            max-width: 480px;
            animation: subtitleFadeIn 1.2s ease-out 0.4s both;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            cursor: pointer;
        }

        .hero-subtitle:hover {
            color: var(--bs-secondary);
            transform: translateX(5px);
            text-shadow: 0 2px 4px rgba(127, 23, 52, 0.2);
        }

        .hero-subtitle:active {
            animation: subtitleBounce 0.4s ease-in-out;
        }

        @keyframes subtitleBounce {
            0%, 20%, 50%, 80%, 100% { transform: translateX(0); }
            40% { transform: translateX(8px); }
            60% { transform: translateX(4px); }
        }

        @keyframes subtitleFadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-features {
            display: flex;
            flex-wrap: wrap;
            gap: 2.5rem;
            margin-bottom: 3rem;
        }

        .hero-feature {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-weight: 600;
            color: var(--bs-dark);
            padding: 0.5rem 0;
            transition: var(--transition-normal);
            animation: featureSlideUp 0.8s ease-out 0.6s both;
        }

        .hero-feature:nth-child(1) { animation-delay: 0.6s; }
        .hero-feature:nth-child(2) { animation-delay: 0.8s; }
        .hero-feature:nth-child(3) { animation-delay: 1s; }

        @keyframes featureSlideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-feature:hover {
            transform: translateX(5px);
            color: var(--bs-secondary);
        }

        .hero-feature i {
            color: var(--bs-secondary);
            font-size: 1.25rem;
            transition: var(--transition-normal);
        }

        .hero-feature:hover i {
            transform: scale(1.1);
        }

        .btn-hero {
            padding: 1.25rem 2.5rem;
            font-weight: 700;
            border-radius: var(--border-radius-lg);
            border: none;
            font-size: 1.1rem;
            transition: var(--transition-normal);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .btn-hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.5s;
        }

        .btn-hero:hover::before {
            left: 100%;
        }

        .btn-hero-primary {
            background: var(--gradient-primary);
            color: white;
            animation: buttonGlow 2s ease-in-out infinite alternate;
        }

        @keyframes buttonGlow {
            from { box-shadow: var(--shadow-md); }
            to { box-shadow: var(--shadow-lg), 0 0 20px rgba(127,23,52,0.3); }
        }

        .btn-hero-primary:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl), 0 0 30px rgba(127,23,52,0.4);
            color: white;
        }

        .btn-hero-outline {
            background: transparent;
            color: var(--bs-secondary);
            border: 2px solid var(--bs-secondary);
        }

        .btn-hero-outline:hover {
            background: var(--gradient-primary);
            color: white;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .hero-image-container {
            position: relative;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 1rem;
            text-align: center;
            animation: slideInRight 1s ease-out;
            background: none;
            border-radius: 2rem;
            box-shadow: none;
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .hero-main-image {
            max-width: 90%;
            height: auto;
            border-radius: 2rem;
            box-shadow: none;
            transition: var(--transition-normal);
            background: transparent;
            opacity: 1;
        }

        .hero-main-image:hover {
            transform: scale(1.02);
            box-shadow: 0 30px 60px rgba(0,0,0,0.15);
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
            right: 0%;
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
                min-height: auto;
            }

            .hero-content {
                margin-bottom: 2rem;
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

        /* Categories Section - Modern Enhanced */
        .categories-section {
            padding: 6rem 0;
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            position: relative;
            overflow: hidden;
        }

        .categories-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(248,249,250,0.5) 0%, rgba(255,255,255,0.8) 100%);
            z-index: -1;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
            position: relative;
        }

        .section-title {
            font-size: clamp(2rem, 4vw, 3rem);
            font-weight: 900;
            color: var(--bs-dark);
            margin-bottom: 1.5rem;
            letter-spacing: -0.02em;
            position: relative;
            display: inline-block;
        }

        .section-title::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 60px;
            height: 4px;
            background: var(--gradient-primary);
            border-radius: 2px;
        }

        .section-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            max-width: 700px;
            margin: 0 auto;
            line-height: 1.6;
            font-weight: 400;
        }

        .category-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            border-radius: var(--border-radius-xl);
            overflow: hidden;
            transition: var(--transition-normal);
            border: 1px solid rgba(255, 255, 255, 0.3);
            height: 240px;
            position: relative;
            display: flex;
            align-items: end;
            box-shadow: var(--shadow-lg);
            cursor: pointer;
        }

        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(127,23,52,0.1) 0%, transparent 70%);
            opacity: 0;
            transition: var(--transition-normal);
            z-index: 1;
        }

        .category-card:hover::before {
            opacity: 1;
        }

        .category-card:hover {
            transform: translateY(-10px) scale(1.03);
            box-shadow: var(--shadow-2xl);
            border-color: rgba(127, 23, 52, 0.2);
        }

        .category-card-content {
            position: relative;
            z-index: 2;
            padding: 2rem 1.5rem;
            color: white;
            width: 100%;
            background: linear-gradient(transparent, rgba(0,0,0,0.8) 60%, rgba(0,0,0,0.9));
            transition: var(--transition-normal);
        }

        .category-card:hover .category-card-content {
            background: linear-gradient(transparent, rgba(127,23,52,0.8) 60%, rgba(127,23,52,0.9));
        }

        .category-card-title {
            font-size: 1.4rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            text-shadow: 0 2px 4px rgba(0,0,0,0.5);
            letter-spacing: -0.01em;
        }

        .category-card-desc {
            font-size: 1rem;
            opacity: 0.95;
            line-height: 1.5;
            text-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }

        /* Categories Carousel */
        .categories-carousel-container {
            position: relative;
            width: 100%;
            margin: 2rem 0;
        }

        .categories-carousel-wrapper {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .categories-carousel {
            flex: 1;
            overflow: hidden;
            border-radius: 1rem;
        }

        .categories-row {
            display: flex;
            transition: transform 0.5s ease-in-out;
            gap: 1.5rem;
        }

        .category-carousel-item {
            flex: 0 0 25%;
            min-width: 280px;
        }


        .btn-arrow {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--bs-secondary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 10px rgba(127, 23, 52, 0.2);
        }

        .btn-arrow:hover:not(:disabled) {
            background: #6b1429;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }

        .btn-arrow:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid var(--bs-secondary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .categories-carousel-wrapper {
                flex-direction: column;
                gap: 1.5rem;
            }

            .categories-carousel-wrapper .btn-arrow {
                position: absolute;
                z-index: 10;
            }

            .categories-carousel-wrapper .btn-arrow-prev {
                left: 10px;
                top: 50%;
                transform: translateY(-50%);
            }

            .categories-carousel-wrapper .btn-arrow-next {
                right: 10px;
                top: 50%;
                transform: translateY(-50%);
            }

            .category-carousel-item {
                flex: 0 0 50%;
                min-width: 250px;
            }

            .btn-arrow {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
        }

        @media (max-width: 576px) {
            .category-carousel-item {
                flex: 0 0 100%;
                min-width: 100%;
            }
        }

        /* Featured Products Section - Modern Enhanced */
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

        /* Features Section - Modern Enhanced */
        .features-section {
            padding: 6rem 0;
            background: var(--gradient-light);
            position: relative;
            overflow: hidden;
        }

        .features-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-image:
                radial-gradient(circle at 30% 70%, rgba(127,23,52,0.05) 0%, transparent 50%),
                radial-gradient(circle at 70% 30%, rgba(25,135,84,0.03) 0%, transparent 50%);
            z-index: 0;
        }

        .features-section > * {
            position: relative;
            z-index: 1;
        }

        .feature-card {
            text-align: center;
            padding: 3rem 2rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            border-radius: var(--border-radius-xl);
            border: 1px solid rgba(255, 255, 255, 0.3);
            box-shadow: var(--shadow-lg);
            transition: var(--transition-normal);
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .feature-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--gradient-primary);
            transform: scaleX(0);
            transition: var(--transition-normal);
        }

        .feature-card:hover::before {
            transform: scaleX(1);
        }

        .feature-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-2xl);
            border-color: rgba(127, 23, 52, 0.1);
        }

        .feature-icon {
            width: 100px;
            height: 100px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            color: white;
            font-size: 2.5rem;
            transition: var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .feature-icon::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            transition: left 0.6s;
        }

        .feature-card:hover .feature-icon::before {
            left: 100%;
        }

        .feature-card:hover .feature-icon {
            transform: scale(1.1) rotate(5deg);
            box-shadow: var(--shadow-xl);
        }

        .feature-title {
            font-size: 1.4rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: var(--bs-dark);
            transition: var(--transition-fast);
        }

        .feature-card:hover .feature-title {
            color: var(--bs-secondary);
        }

        .feature-desc {
            color: #6c757d;
            line-height: 1.7;
            font-size: 1rem;
        }

        /* Testimonials Section - Modern Enhanced */
        .testimonials-section {
            padding: 6rem 0;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
        }

        .testimonials-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(240,243,242,0.8) 0%, rgba(255,255,255,0.9) 100%);
            z-index: -1;
        }

        .testimonial-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--border-radius-xl);
            padding: 2.5rem;
            border: 1px solid rgba(255, 255, 255, 0.4);
            height: 100%;
            box-shadow: var(--shadow-lg);
            transition: var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .testimonial-card::before {
            content: '"';
            position: absolute;
            top: 1.5rem;
            left: 2rem;
            font-size: 4rem;
            color: rgba(127, 23, 52, 0.1);
            font-family: 'Georgia', serif;
            line-height: 1;
            z-index: 0;
        }

        .testimonial-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-2xl);
            border-color: rgba(127, 23, 52, 0.2);
        }

        .testimonial-header {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            position: relative;
            z-index: 1;
        }

        .testimonial-avatar {
            width: 60px;
            height: 60px;
            background: var(--gradient-primary);
            border-radius: var(--border-radius-xl);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 700;
            margin-right: 1.5rem;
            flex-shrink: 0;
            font-size: 1.2rem;
            box-shadow: var(--shadow-md);
            transition: var(--transition-normal);
        }

        .testimonial-card:hover .testimonial-avatar {
            transform: scale(1.1);
            box-shadow: var(--shadow-lg);
        }

        .testimonial-avatar img {
            width: 100%;
            height: 100%;
            border-radius: var(--border-radius-xl);
            object-fit: cover;
        }

        .testimonial-rating {
            color: var(--bs-warning);
            margin-top: 0.5rem;
            font-size: 1rem;
        }

        .testimonial-rating i {
            transition: var(--transition-fast);
        }

        .testimonial-card:hover .testimonial-rating i {
            transform: scale(1.1);
        }

        .testimonial-text {
            color: #6c757d;
            font-style: italic;
            line-height: 1.7;
            font-size: 1.05rem;
            position: relative;
            z-index: 1;
            margin-bottom: 1rem;
        }

        .testimonial-author {
            font-weight: 600;
            color: var(--bs-dark);
            font-size: 1rem;
            margin-top: 1rem;
            text-align: right;
            font-style: normal;
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

    <!-- Hero Section with Sliding Feature -->
    <section class="hero-section">
        <div class="hero-pattern"></div>
        <div class="container">
            <div id="heroCarousel" class="carousel slide" data-bs-ride="carousel" data-bs-interval="5000">
                <!-- Carousel indicators removed for clean look -->
                <div class="carousel-inner">
                    <div class="carousel-item active">
                        <div class="row align-items-center min-vh-75">
                            <div class="col-lg-5">
                                <div class="hero-content">
                                    <h1 class="hero-title">
                                        Top Quality <span class="text-highlight">You Deserve</span>
                                    </h1>
                                    <p class="hero-subtitle">
                                        Stock your kitchen with the best frozen meats and seafood. Freshly packed, quality guaranteed, delivery you can count on.
                                    </p>
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
                            <div class="col-lg-7">
                                <div class="hero-image-container position-relative">
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

                                    <img src="images/slide1.png" alt="Fresh Groceries" class="hero-main-image">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row align-items-center min-vh-75">
                            <div class="col-lg-5">
                                <div class="hero-content">
                                    <h1 class="hero-title">
                                        Premium Beef
                                    </h1>
                                    <p class="hero-subtitle">
                                        High quality beef cuts delivered fresh to your door.
                                    </p>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <a href="product_detail.php?id=1" class="btn-hero btn-hero-primary">
                                            <i class="fas fa-shopping-cart"></i>
                                            Buy Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="hero-image-container">
                                        <img src="images/slide2.png" alt="Premium Beef" class="hero-main-image">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row align-items-center min-vh-75">
                            <div class="col-lg-5">
                                <div class="hero-content">
                                    <h1 class="hero-title">
                                        Fresh Bangus
                                    </h1>
                                    <p class="hero-subtitle">
                                        Freshly caught bangus fish, perfect for your meals.
                                    </p>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <a href="product_detail.php?id=2" class="btn-hero btn-hero-primary">
                                            <i class="fas fa-shopping-cart"></i>
                                            Buy Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="hero-image-container">
                                        <img src="images/slide3.png" alt="Fresh Bangus" class="hero-main-image">
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="carousel-item">
                        <div class="row align-items-center min-vh-75">
                            <div class="col-lg-5">
                                <div class="hero-content">
                                    <h1 class="hero-title">
                                        Chicken Breast
                                    </h1>
                                    <p class="hero-subtitle">
                                        Fresh chicken breast, lean and healthy.
                                    </p>
                                    <div class="d-flex gap-3 flex-wrap">
                                        <a href="product_detail.php?id=3" class="btn-hero btn-hero-primary">
                                            <i class="fas fa-shopping-cart"></i>
                                            Buy Now
                                        </a>
                                    </div>
                                </div>
                            </div>
                            <div class="col-lg-7">
                                <div class="hero-image-container">
                                        <img src="images/slide4.png" alt="Chicken Breast" class="hero-main-image">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#heroCarousel" data-bs-slide="prev">
                    <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Previous</span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#heroCarousel" data-bs-slide="next">
                    <span class="carousel-control-next-icon" aria-hidden="true"></span>
                    <span class="visually-hidden">Next</span>
                </button>
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

            <!-- Categories Carousel -->
            <div class="categories-carousel-container">
                <div class="categories-carousel-wrapper">
                    <!-- Left Arrow -->
                    <button class="btn-arrow btn-arrow-prev" id="prevBtn" onclick="loadPreviousCategories()" disabled>
                        <i class="fas fa-chevron-left"></i>
                    </button>
                    
                    <div class="categories-carousel" id="categoriesCarousel">
                        <div class="categories-row" id="categoriesRow">
                            <!-- Categories will be loaded here -->
                        </div>
                    </div>
                    
                    <!-- Right Arrow -->
                    <button class="btn-arrow btn-arrow-next" id="nextBtn" onclick="loadNextCategories()">
                        <i class="fas fa-chevron-right"></i>
                    </button>
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
                                    <div class="product-stock">Stock: <?= number_format((float)$product['stock'], 1) ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?> available</div>

                                    <?php if ((float)$product['stock'] > 0): ?>
                                        <div class="d-flex align-items-center gap-2 mb-2">
                                            <input type="number" class="form-control quantity-input" value="1" step="0.1" min="1" max="<?= (float)$product['stock'] ?>" inputmode="decimal" aria-label="Quantity" />
                                            <span class="text-muted" style="white-space: nowrap;"><?= htmlspecialchars($product['uom_name'] ?? '') ?></span>
                                        </div>
                                        <div class="d-flex align-items-center gap-2">
                                            <button type="button" class="btn-add-cart flex-grow-1" data-product-id="<?= $product['id'] ?>">
                                                <i class="fas fa-cart-plus me-2"></i>Add to Cart
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
        // Global variables for cart management
        let isAddingToCart = false;

        // Function to refresh cart content
        function refreshCartContent() {
            fetch('cart_content.php')
                .then(response => response.text())
                .then(html => {
                    const cartBody = document.querySelector('.cart-body');
                    const cartFooter = document.querySelector('.cart-footer');
                    
                    if (cartBody) {
                        cartBody.innerHTML = html;
                    }
                    
                    // Update cart footer with totals
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

        // Categories Carousel functionality
        let currentPage = 1;
        let totalPages = 1;
        let isLoading = false;

        // Initialize page on load
        document.addEventListener('DOMContentLoaded', function() {
            loadCategories(1);
            updateCartBadge(); // Initialize cart badge
            initializeCartButtons(); // Initialize cart button event listeners
            initializeCartRefresh(); // Initialize cart refresh functionality
        });

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

        function loadCategories(page) {
            if (isLoading) return;
            
            isLoading = true;
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            // Disable buttons during loading
            prevBtn.disabled = true;
            nextBtn.disabled = true;

            fetch(`api/categories.php?page=${page}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        displayCategories(data.categories);
                        updateNavigation(data.pagination);
                        currentPage = page;
                        totalPages = data.pagination.total_pages;
                    } else {
                        console.error('Failed to load categories:', data.error);
                    }
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                })
                .finally(() => {
                    isLoading = false;
                });
        }

        function displayCategories(categories) {
            const categoriesRow = document.getElementById('categoriesRow');
            
            if (categories.length === 0) {
                categoriesRow.innerHTML = '<div class="col-12 text-center text-muted">No categories available.</div>';
                return;
            }

            let html = '';
            categories.forEach(category => {
                html += `
                    <div class="category-carousel-item">
                        <a href="${category.url}" class="text-decoration-none">
                            <div class="category-card" style="background: linear-gradient(rgba(0,0,0,0.3), rgba(0,0,0,0.3)), url('${category.image}'); background-size: cover; background-position: center;">
                                <div class="category-card-content">
                                    <h3 class="category-card-title">${category.name}</h3>
                                    <p class="category-card-desc">${category.description}</p>
                                </div>
                            </div>
                        </a>
                    </div>
                `;
            });
            
            categoriesRow.innerHTML = html;
        }

        function updateNavigation(pagination) {
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            
            prevBtn.disabled = !pagination.has_prev;
            nextBtn.disabled = !pagination.has_next;
        }

        function loadNextCategories() {
            if (currentPage < totalPages && !isLoading) {
                loadCategories(currentPage + 1);
            }
        }

        function loadPreviousCategories() {
            if (currentPage > 1 && !isLoading) {
                loadCategories(currentPage - 1);
            }
        }

    </script>
    
    <!-- Lazy Loading Script -->
    <script src="includes/lazy_loading.js"></script>
</body>
</html>