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
            -- Count products sold
            COALESCE((
                SELECT SUM(oi.quantity) 
                FROM order_items oi
                INNER JOIN orders o ON oi.order_id = o.orders_id
                INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                WHERE oi.product_id = p.product_id 
                AND os.status_name IN ('Delivered','Completed','Finished')
            ), 0) AS products_sold,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1,
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE p.is_archive = 0 AND COALESCE(ps.current_stock, 0) > 0
        GROUP BY p.product_id
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
            background-color: #e6f3ff;
            color: #2c5aa0;
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(44, 90, 160, 0.1);
            box-shadow: 0 2px 8px rgba(44, 90, 160, 0.1);
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

        /* Typewriter Animation Styles */
        .cursor {
            animation: blink 1s infinite;
            color: var(--bs-secondary);
            font-weight: 300;
        }

        @keyframes blink {
            0%, 50% { opacity: 1; }
            51%, 100% { opacity: 0; }
        }

        #typewriter-text {
            display: inline;
        }

        #highlight-text {
            display: inline;
            opacity: 0;
            animation: fadeInHighlight 0.5s ease-in-out 3s forwards;
        }

        @keyframes fadeInHighlight {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero-subtitle {
            font-size: 1.2rem;
            color: #6c757d;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Hero Animations */
        .hero-badge {
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeInDown 0.8s ease-out 0.5s forwards;
        }

        .hero-title {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 1s forwards;
        }

        .hero-subtitle {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 1.3s forwards;
        }

        .hero-features {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 1.6s forwards;
        }

        .hero-buttons {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.8s ease-out 1.9s forwards;
        }

        @keyframes fadeInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes fadeInUp {
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
                min-height: auto;
            }
            
            .hero-content {
                margin-bottom: 2rem;
            }
            
            .hero-buttons {
                flex-direction: column;
                gap: 1rem;
                margin-top: 2rem;
            }
            
            .hero-buttons .btn-hero {
                width: 100%;
                text-align: center;
            }
            
            .hero-main-image {
                margin-top: 2rem;
                max-height: 300px;
                object-fit: cover;
                border-radius: 1rem;
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
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
            text-decoration: none;
            color: inherit;
        }

        .product-card:visited {
            color: inherit;
            text-decoration: none;
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

        .product-sold {
            font-size: 0.8rem;
            color: #6c757d;
            margin-bottom: 1rem;
            font-weight: 500;
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

        .quantity-input {
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: white;
            width: 80px;
        }

        .quantity-input:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            outline: none;
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
                margin-bottom: 2rem;
            }

            .hero-buttons {
                flex-direction: column;
                gap: 1rem;
            }

            .hero-buttons .btn-hero {
                width: 100%;
            }

            .hero-main-image {
                margin-top: 2rem;
                max-height: 300px;
                width: 100%;
                object-fit: cover;
                border-radius: 1rem;
            }

            .section-title {
                font-size: 2rem;
            }
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
                            <i class="fas fa-snowflake me-2"></i>
                            Always Freshly Frozen
                        </div>

                        <h1 class="hero-title">
                            <span id="typewriter-text"></span><span class="text-highlight" id="highlight-text"></span><span class="cursor">|</span>
                        </h1>

                        <p class="hero-subtitle">
                            Premium frozen meats, seafood, vegetables, and ready-to-cook meals delivered fresh to your home. 
                            Quality guaranteed, convenience delivered.
                        </p>

                        <div class="hero-features">
                            <div class="hero-feature">
                                <i class="fas fa-archive"></i>
                                <span>Long Shelf Life</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-heart"></i>
                                <span>Made with Care, Served with Love</span>
                            </div>
                            <div class="hero-feature">
                                <i class="fas fa-home"></i>
                                <span>Delivered Right to Your Door</span>
                            </div>
                        </div>

                        <div class="d-flex gap-3 flex-wrap hero-buttons">
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
                            <a href="product_detail.php?id=<?= $product['id'] ?>" class="product-card text-decoration-none">
                                <div class="product-badge">
                                    Featured
                                </div>

                                <img data-src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>" alt="<?= htmlspecialchars($product['name']) ?>" class="product-image lazy lazy-placeholder" onerror="this.src='images/placeholder.jpg'">

                                <h3 class="product-title"><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></h3>
                                <p class="product-desc"><?= htmlspecialchars($product['description']) ?></p>

                                <div class="product-price">From ₱<?= number_format($product['lowest_price'], 2) ?></div>
                                <div class="product-stock">Stock: <?= number_format((float)$product['stock'], 1) ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?> available</div>
                                <div class="product-sold"><?= number_format($product['products_sold']) ?> sold</div>
                            </a>
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

    <!-- SweetAlert2 CSS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Typewriter Animation
        function initTypewriter() {
            const typewriterText = document.getElementById('typewriter-text');
            const highlightText = document.getElementById('highlight-text');
            const cursor = document.querySelector('.cursor');
            
            const text = "Fresh frozen foods delivered ";
            const highlight = "fast & easy";
            
            let i = 0;
            
            function typeWriter() {
                if (i < text.length) {
                    typewriterText.textContent += text.charAt(i);
                    i++;
                    setTimeout(typeWriter, 100); // Speed of typing (100ms per character)
                } else {
                    // After typing is complete, show highlight text
                    setTimeout(() => {
                        highlightText.textContent = highlight;
                        // Hide cursor after highlight appears
                        setTimeout(() => {
                            cursor.style.display = 'none';
                        }, 500);
                    }, 500);
                }
            }
            
            // Start typing after a short delay
            setTimeout(typeWriter, 1000);
        }

        // Initialize typewriter when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initTypewriter();
        });

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