<?php
session_start();
include 'includes/db.php';


// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Fetch 8 best selling products (not archived, in stock, ordered by most sold)
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
            -- DEBUG: Add individual components for debugging
            COALESCE(pp.markup_price, 0) AS markup_price,
            COALESCE(pp.cost_price, 0) AS cost_price,
            (SELECT pb.unit_cost 
             FROM product_batches pb 
             WHERE pb.product_id = p.product_id 
             AND pb.quantity_remaining > 0 
             AND pb.is_active = 1
             ORDER BY pb.received_date DESC, pb.unit_cost DESC 
             LIMIT 1) AS batch_unit_cost,
            -- Calculate total price: markup_price + (best available cost from batches or general cost_price)
            COALESCE(pp.markup_price, 0) + COALESCE((
                SELECT COALESCE(
                    (SELECT pb.unit_cost 
                     FROM product_batches pb 
                     WHERE pb.product_id = p.product_id 
                     AND pb.quantity_remaining > 0 
                     AND pb.is_active = 1
                     ORDER BY pb.received_date DESC, pb.unit_cost DESC 
                     LIMIT 1),
                    pp.cost_price, 
                    0
                )
            ), 0) AS price,
            -- Get newest batch price among all brands for this product
            MIN(COALESCE(pp.markup_price, 0) + COALESCE(
                (SELECT pb.unit_cost 
                 FROM product_batches pb 
                 WHERE pb.product_id = p.product_id 
                 AND pb.quantity_remaining > 0 
                 AND pb.is_active = 1
                 ORDER BY pb.received_date DESC, pb.unit_cost DESC 
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
        ORDER BY products_sold DESC, p.created_at DESC
        LIMIT 8
    ");
    $featuredProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $featuredProducts = [];
}

// Fetch customer testimonials from order ratings (only 5-star ratings)
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
        WHERE o.review IS NOT NULL AND TRIM(o.review) != '' AND o.rating >= 4
        ORDER BY o.created_at DESC
        LIMIT 10
    ");
    $testimonials = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $testimonials = [];
}

// Fetch latest products for floating cards (pork, beef, chicken)
$floatingProducts = [];
try {
    $stmt = $pdo->query("
        SELECT 
            p.product_id AS id,
            p.product_name AS name,
            c.category_name,
            b.name AS brand_name,
            uom.name AS uom_name,
            COALESCE(ps.current_stock, 0) AS stock,
            -- DEBUG: Add individual components for debugging
            COALESCE(pp.markup_price, 0) AS markup_price,
            COALESCE(pp.cost_price, 0) AS cost_price,
            (SELECT pb.unit_cost 
             FROM product_batches pb 
             WHERE pb.product_id = p.product_id 
             AND pb.quantity_remaining > 0 
             AND pb.is_active = 1
             ORDER BY pb.received_date DESC, pb.unit_cost DESC 
             LIMIT 1) AS batch_unit_cost,
            -- Calculate total price: markup_price + (best available cost from batches or general cost_price)
            COALESCE(pp.markup_price, 0) + COALESCE((
                SELECT COALESCE(
                    (SELECT pb.unit_cost 
                     FROM product_batches pb 
                     WHERE pb.product_id = p.product_id 
                     AND pb.quantity_remaining > 0 
                     AND pb.is_active = 1
                     ORDER BY pb.received_date DESC, pb.unit_cost DESC 
                     LIMIT 1),
                    pp.cost_price, 
                    0
                )
            ), 0) AS price,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1,
            p.created_at
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE p.is_archive = 0 
        AND COALESCE(ps.current_stock, 0) > 0
        AND LOWER(c.category_name) IN ('pork', 'beef', 'chicken')
        ORDER BY p.created_at DESC, c.category_name
        LIMIT 3
    ");
    $floatingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $floatingProducts = [];
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
            color: var(--text-primary);
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
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
            background: var(--bg-card) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid var(--border-light);
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--brand-primary) !important;
        }

        .navbar-nav .nav-link {
            font-weight: 500;
            color: var(--text-primary) !important;
            transition: all 0.3s ease;
            margin: 0 0.5rem;
        }

        .navbar-nav .nav-link:hover {
            color: var(--brand-primary) !important;
        }

        /* Hero Section - FreshCart Style */
        .hero-section {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
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
            color: var(--text-primary);
        }

        .hero-title .text-highlight {
            color: var(--brand-primary);
        }

        /* Typewriter Animation Styles */
        .cursor {
            animation: blink 1s infinite;
            color: var(--brand-primary);
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
            animation: fadeInHighlight 0.3s ease-in-out 0.8s forwards;
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
            color: var(--text-secondary);
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Hero Animations */
        .hero-badge {
            opacity: 0;
            transform: translateY(-20px);
            animation: fadeInDown 0.3s ease-out 0.1s forwards;
        }

        .hero-title {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.3s ease-out 0.2s forwards;
        }

        .hero-subtitle {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.3s ease-out 0.3s forwards;
        }

        .hero-features {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.3s ease-out 0.4s forwards;
        }

        .hero-buttons {
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.3s ease-out 0.5s forwards;
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
            color: var(--text-primary);
        }

        .hero-feature i {
            color: var(--brand-primary);
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
            background: var(--brand-primary);
            color: white;
        }

        .btn-hero-primary:hover {
            background: var(--brand-secondary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127,23,52,0.3);
        }

        .btn-hero-outline {
            background: transparent;
            color: var(--brand-primary);
            border: 2px solid var(--brand-primary);
        }

        .btn-hero-outline:hover {
            background: var(--brand-primary);
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
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 0.75rem;
            box-shadow: 0 10px 30px var(--shadow-medium);
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
            color: var(--text-primary);
        }

        .floating-card .price {
            color: var(--brand-primary);
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
            padding: 6rem 0;
            background: var(--bg-secondary);
            width: 100vw;
            position: relative;
            left: 50%;
            right: 50%;
            margin-left: -50vw;
            margin-right: -50vw;
        }

        .section-header {
            text-align: center;
            margin-bottom: 4rem;
            padding: 0 2rem;
        }

        .section-title {
            font-size: 3rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .title-divider {
            width: 60px;
            height: 3px;
            background: var(--brand-primary);
            margin: 0 auto;
        }

        .section-header.text-start .title-divider {
            margin: 0;
        }

        .category-card {
            background: var(--bg-card);
            border-radius: 1.5rem;
            overflow: hidden;
            transition: all 0.3s ease;
            border: 1px solid var(--border-light);
            height: 350px;
            position: relative;
            display: flex;
            align-items: end;
            box-shadow: 0 8px 25px var(--shadow-medium);
            cursor: pointer;
            text-decoration: none;
            color: inherit;
        }

        .category-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px var(--shadow-dark);
            text-decoration: none;
            color: inherit;
        }

        .category-card:visited {
            color: inherit;
            text-decoration: none;
        }

        .category-card-content {
            position: relative;
            z-index: 2;
            padding: 2.5rem;
            color: white;
            width: 100%;
            background: linear-gradient(transparent, rgba(0,0,0,0.8));
        }

        .category-card-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 1rem;
            line-height: 1.2;
        }

        .category-card-desc {
            font-size: 1rem;
            opacity: 0.9;
            line-height: 1.5;
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
            gap: 2rem;
            max-width: 1400px;
            margin: 0 auto;
        }

        .categories-carousel {
            flex: 1;
            overflow: hidden;
            width: 100%;
        }

        .categories-row {
            display: flex;
            gap: 1.5rem;
            overflow-x: auto;
            scroll-behavior: smooth;
            scrollbar-width: none; /* Firefox */
            -ms-overflow-style: none; /* IE and Edge */
            scroll-snap-type: x mandatory;
            padding: 0 2rem; /* Increased padding for better peeking */
            justify-content: flex-start;
        }

        .categories-row::-webkit-scrollbar {
            display: none; /* Chrome, Safari, Opera */
        }

        .category-carousel-item {
            flex: 0 0 auto;
            min-width: 500px;
            max-width: 600px;
            scroll-snap-align: center;
            margin: 0 auto;
        }

        .btn-arrow {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: var(--brand-primary);
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            cursor: pointer;
            box-shadow: 0 2px 10px var(--shadow-medium);
        }

        .btn-arrow:hover:not(:disabled) {
            background: var(--brand-secondary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px var(--shadow-medium);
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
            border: 3px solid var(--bg-tertiary);
            border-top: 3px solid var(--brand-primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 1200px) {
            .category-carousel-item {
                flex: 0 0 auto;
                min-width: 450px;
                max-width: 550px;
            }
        }

        @media (max-width: 992px) {
            .category-carousel-item {
                flex: 0 0 auto;
                min-width: 350px;
                max-width: 450px;
            }
            
            .category-card {
                height: 320px;
            }
            
            .category-card-content {
                padding: 2rem;
            }
            
            .category-card-title {
                font-size: 1.6rem;
            }
        }

        @media (max-width: 768px) {
            .categories-section {
                padding: 3rem 0;
            }

            .section-title {
                font-size: 2.2rem;
                margin-bottom: 0.5rem;
            }

            .section-header {
                margin-bottom: 2.5rem;
                padding: 0 1rem;
            }

            .categories-carousel-container {
                padding: 0 0.5rem;
            }

            .category-carousel-item {
                flex: 0 0 auto;
                min-width: 280px;
                max-width: 350px;
                padding: 0 0.5rem;
            }

            .categories-row {
                gap: 1rem;
            }

            .category-card {
                height: 280px;
                border-radius: 1rem;
            }

            .category-card-content {
                padding: 1.5rem;
            }

            .category-card-title {
                font-size: 1.4rem;
                margin-bottom: 0.75rem;
            }

            .category-card-desc {
                font-size: 0.9rem;
                line-height: 1.4;
            }
        }

        @media (max-width: 576px) {
            .categories-section {
                padding: 2.5rem 0;
            }

            .section-title {
                font-size: 1.8rem;
                letter-spacing: 0.5px;
            }

            .section-header {
                margin-bottom: 2rem;
                padding: 0 0.5rem;
            }

            .title-divider {
                width: 50px;
                height: 2px;
            }

            .categories-carousel-container {
                padding: 0 0.25rem;
            }

            .category-carousel-item {
                flex: 0 0 auto;
                min-width: 250px;
                max-width: 300px;
                padding: 0 0.5rem;
            }

            .categories-row {
                gap: 1rem;
            }

            .category-card {
                height: 240px;
                border-radius: 0.75rem;
            }

            .category-card-content {
                padding: 1.25rem;
            }

            .category-card-title {
                font-size: 1.2rem;
                margin-bottom: 0.5rem;
                line-height: 1.1;
            }

            .category-card-desc {
                font-size: 0.85rem;
                line-height: 1.3;
            }
        }

        @media (max-width: 480px) {
            .section-title {
                font-size: 1.6rem;
            }

            .category-carousel-item {
                flex: 0 0 auto;
                min-width: 200px;
                max-width: 250px;
                padding: 0 0.25rem;
            }

            .categories-row {
                gap: 0.75rem;
            }

            .category-card {
                height: 220px;
            }

            .category-card-content {
                padding: 1rem;
            }

            .category-card-title {
                font-size: 1.1rem;
            }

            .category-card-desc {
                font-size: 0.8rem;
            }
        }

        /* Featured Products Section */
        .featured-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
        }

        .product-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border-light);
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
            box-shadow: 0 15px 35px var(--shadow-medium);
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
            background: var(--brand-primary);
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
            background-color: var(--bg-secondary);
            flex-shrink: 0;
        }

        .product-title {
            font-size: 1.1rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: var(--text-primary);
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
            color: var(--text-secondary);
            margin-bottom: 1rem;
        }

        .product-price {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--brand-primary);
            margin-bottom: 1rem;
        }

        .product-meta {
            margin-bottom: 1rem;
            font-size: 0.8rem;
            color: var(--text-secondary);
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
            color: var(--brand-primary);
            font-size: 0.75rem;
        }

        .product-rating {
            margin-bottom: 1rem;
        }

        .product-rating .text-warning {
            color: #ffc107 !important;
        }

        .product-rating .text-muted {
            color: var(--text-secondary) !important;
        }

        .product-rating small {
            font-size: 0.8rem;
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
            background: var(--brand-primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .btn-add-cart:hover {
            background: var(--brand-secondary);
            color: white;
            transform: translateY(-2px);
        }

        .btn-add-cart:active {
            transform: translateY(0);
        }

        .quantity-input {
            border: 2px solid var(--border-light);
            border-radius: 0.5rem;
            padding: 0.5rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--input-bg);
            width: 80px;
        }

        .quantity-input:focus {
            border-color: var(--brand-primary);
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


        /* About Us Section */
        .about-section {
            padding: 5rem 0;
            background: var(--bg-secondary);
        }

        .about-content {
            padding: 0;
        }

        .about-story {
            margin-bottom: 2rem;
        }

        .about-text {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
        }

        .about-text strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        .about-stats {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .stat-item {
            text-align: center;
            padding: 1.5rem;
            background: var(--bg-card);
            border-radius: 1rem;
            box-shadow: 0 2px 10px var(--shadow-light);
            transition: all 0.3s ease;
        }

        .stat-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px var(--shadow-medium);
        }

        .stat-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--brand-primary);
            margin-bottom: 0.5rem;
        }

        .stat-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .about-actions {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
        }

        .about-actions .btn {
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            border-radius: 0.5rem;
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .about-actions .btn-primary {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
        }

        .about-actions .btn-primary:hover {
            background: var(--brand-secondary);
            border-color: var(--brand-secondary);
            transform: translateY(-2px);
        }

        .about-actions .btn-outline-primary {
            color: var(--brand-primary);
            border-color: var(--brand-primary);
        }

        .about-actions .btn-outline-primary:hover {
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            color: white;
        }

        .about-map {
            position: relative;
        }

        .map-container {
            position: relative;
            border-radius: 1rem;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
        }

        .map-info {
            margin-top: 1rem;
            padding: 1.5rem;
            background: var(--bg-card);
            border-radius: 1rem;
            box-shadow: 0 2px 10px var(--shadow-light);
        }

        .map-address {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
        }

        .map-address i {
            font-size: 1.2rem;
            margin-top: 0.25rem;
        }

        .map-address div {
            line-height: 1.5;
        }

        .map-address strong {
            color: var(--text-primary);
            font-weight: 600;
        }

        /* Mobile Responsive */
        @media (max-width: 768px) {
            .about-content {
                padding-right: 0;
                margin-bottom: 2rem;
            }

            .about-stats {
                grid-template-columns: repeat(2, 1fr);
                gap: 1rem;
            }

            .stat-item {
                padding: 1rem;
            }

            .stat-number {
                font-size: 1.5rem;
            }

            .about-actions {
                flex-direction: column;
            }

            .about-actions .btn {
                width: 100%;
                text-align: center;
            }

            .map-container iframe {
                height: 300px;
            }
        }

        @media (max-width: 576px) {
            .about-stats {
                grid-template-columns: 1fr;
            }
        }

        /* Customer Ratings Section */
        .customer-ratings-section {
            padding: 5rem 0;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            position: relative;
            overflow: hidden;
        }

        .customer-ratings-section .container {
            position: relative;
            z-index: 2;
        }

        /* Food Image Container */
        .food-image-container {
            position: relative;
            display: flex;
            justify-content: center;
            align-items: center;
            height: 400px;
        }

        .food-plate {
            position: relative;
            width: 300px;
            height: 300px;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
            transform: rotate(-5deg);
            transition: all 0.3s ease;
        }

        .food-plate:hover {
            transform: rotate(0deg) scale(1.05);
        }

        .food-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 50%;
        }


        /* Background Decorations */
        .bg-decoration {
            position: absolute;
            border-radius: 50%;
            opacity: 0.1;
            animation: floatDecoration 8s ease-in-out infinite;
        }

        .bg-decoration-1 {
            width: 100px;
            height: 100px;
            background: var(--bs-secondary);
            top: 10%;
            left: 10%;
            animation-delay: 0s;
        }

        .bg-decoration-2 {
            width: 60px;
            height: 60px;
            background: var(--bs-warning);
            top: 60%;
            right: 20%;
            animation-delay: 3s;
        }

        .bg-decoration-3 {
            width: 80px;
            height: 80px;
            background: var(--bs-info);
            bottom: 20%;
            left: 20%;
            animation-delay: 6s;
        }

        @keyframes floatDecoration {
            0%, 100% { transform: translateY(0px) scale(1); }
            50% { transform: translateY(-30px) scale(1.1); }
        }

        /* Testimonials Content */
        .testimonials-content {
            padding-left: 2rem;
        }

        .section-header.text-start .section-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }

        .title-underline {
            width: 60px;
            height: 4px;
            background: linear-gradient(90deg, var(--brand-primary), var(--bs-warning));
            border-radius: 2px;
            margin-bottom: 2rem;
        }

        /* Testimonial Carousel */
        .testimonial-carousel {
            position: relative;
            min-height: 300px;
            overflow: hidden;
        }

        .testimonial-slide {
            position: relative;
            width: 100%;
        }

        .testimonial-content {
            opacity: 0;
            transform: translateX(50px);
            transition: all 0.6s ease;
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            padding: 2rem 0;
        }

        .testimonial-content.active {
            opacity: 1;
            transform: translateX(0);
            position: relative;
        }

        .quote-mark {
            font-size: 4rem;
            color: var(--bs-warning);
            opacity: 0.3;
            margin-bottom: 1rem;
            line-height: 1;
        }

        .testimonial-text {
            font-size: 1.1rem;
            line-height: 1.7;
            color: var(--text-secondary);
            margin-bottom: 1.5rem;
            font-style: italic;
            max-width: 500px;
        }

        .testimonial-rating {
            margin-bottom: 2rem;
        }

        .testimonial-rating i {
            font-size: 1.2rem;
            color: #ddd;
            margin-right: 0.25rem;
            transition: all 0.3s ease;
        }

        .testimonial-rating i.active {
            color: var(--bs-warning);
            transform: scale(1.1);
        }

        .testimonial-author {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .author-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--bs-secondary);
            flex-shrink: 0;
        }

        .avatar-img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            width: 100%;
            height: 100%;
            background: var(--bs-secondary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
        }

        .author-info h4 {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .author-info p {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin: 0;
        }



        /* Mobile Responsive */
        @media (max-width: 768px) {
            .customer-ratings-section {
                padding: 3rem 0;
            }

            .food-image-container {
                height: 250px;
                margin-bottom: 2rem;
            }

            .food-plate {
                width: 200px;
                height: 200px;
            }

            .testimonials-content {
                padding-left: 0;
            }

            .section-header.text-start .section-title {
                font-size: 2rem;
                text-align: center;
            }

            .title-underline {
                margin: 0 auto 2rem auto;
            }

            .testimonial-text {
                font-size: 1rem;
                text-align: center;
            }

            .testimonial-author {
                justify-content: center;
            }
        }

        @media (max-width: 576px) {
            .food-plate {
                width: 150px;
                height: 150px;
            }

            .quote-mark {
                font-size: 3rem;
            }

            .testimonial-text {
                font-size: 0.95rem;
            }

            .author-avatar {
                width: 50px;
                height: 50px;
            }

            .nav-btn {
                width: 45px;
                height: 45px;
                font-size: 1rem;
            }
        }

        /* Footer */
        .footer {
            background: var(--bg-dark);
            color: var(--text-light);
            padding: 3rem 0 1rem;
        }

        .footer-title {
            color: var(--text-light);
            font-weight: 600;
            margin-bottom: 1rem;
        }

        .footer-link {
            color: var(--text-secondary);
            text-decoration: none;
            transition: all 0.3s ease;
        }

        .footer-link:hover {
            color: var(--text-light);
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
        }

        /* SweetAlert2 Custom Styles */
        .swal2-popup {
            border-radius: 1rem !important;
            font-family: 'Inter', sans-serif !important;
            background: var(--bg-card) !important;
            color: var(--text-primary) !important;
        }

        .swal2-title {
            font-weight: 600 !important;
            color: var(--text-primary) !important;
        }

        .swal2-content {
            color: var(--text-secondary) !important;
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
            background: var(--bs-success) !important; /* Green for success */
        }

        .swal2-warning .swal2-confirm {
            background: var(--bs-warning) !important; /* Yellow for warnings */
            color: var(--text-primary) !important;
        }

        .swal2-danger .swal2-confirm {
            background: var(--bs-danger) !important; /* Red for delete/danger */
        }

        .swal2-info .swal2-confirm {
            background: var(--bs-info) !important; /* Blue for info */
        }

        .swal2-cancel {
            background: var(--text-secondary) !important; /* Gray for cancel */
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
                        <?php if (!empty($floatingProducts)): ?>
                            <?php foreach ($floatingProducts as $index => $product): ?>
                                <div class="floating-card card-<?= $index + 1 ?>">
                                    <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>" 
                                         alt="<?= htmlspecialchars($product['name']) ?>" 
                                         onerror="this.src='images/placeholder.jpg'">
                                    <h6><?= htmlspecialchars($product['name']) ?></h6>
                                    <div class="price">₱<?= number_format($product['price'], 2) ?>/<?= htmlspecialchars($product['uom_name']) ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <!-- Fallback floating cards -->
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
                        <?php endif; ?>

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
                <h2 class="section-title">Categories</h2>
                <div class="title-divider"></div>
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
                <h2 class="section-title">Best Sellers</h2>
                <div class="title-divider"></div>
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

                                <div class="product-price">₱<?= number_format($product['lowest_price'], 2) ?></div>
                                
                                <!-- Stock and Sold Info - Aligned horizontally -->
                                <div class="product-meta d-flex justify-content-between align-items-center">
                                    <div class="product-stock">
                                        <i class="fas fa-boxes me-1"></i>
                                        Stock: <?= number_format((float)$product['stock'], 1) ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                    </div>
                                    <div class="product-sold">
                                        <i class="fas fa-shopping-bag me-1"></i>
                                        <?= number_format($product['products_sold']) ?> sold
                                    </div>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </section>


    <!-- About Us Section -->
    <section class="about-section">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-lg-8">
                    <div class="about-content text-center">
                        <div class="section-header">
                            <h2 class="section-title">About MikeMadz</h2>
                            <div class="title-divider"></div>
                        </div>
                        
                        <div class="about-story">
                            <p class="about-text">
                                Located in <strong>BIR Village Block 9 Lot 5 Franchise St., Brgy. Sauyo, Quezon City</strong>, 
                                MikeMadz quickly grew from a small online venture into a trusted frozen goods supplier. 
                        
                            </p>
                        </div>
                        
                        <div class="about-map">
                            <div class="map-container">
                                <iframe 
                                    src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3861.1234567890!2d121.1234567890!3d14.1234567890!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x0%3A0x0!2zMTTCsDA3JzI0LjQiTiAxMjHCsDA3JzI0LjQiRQ!5e0!3m2!1sen!2sph!4v1234567890123!5m2!1sen!2sph"
                                    width="100%" 
                                    height="400" 
                                    style="border:0; border-radius: 1rem;" 
                                    allowfullscreen="" 
                                    loading="lazy" 
                                    referrerpolicy="no-referrer-when-downgrade">
                                </iframe>
                            </div>
                        </div>
                        
                        <div class="about-actions mt-4">
                            <a href="https://maps.app.goo.gl/DANPXbebykqEPmkS7" target="_blank" class="btn btn-outline-primary">
                                <i class="fas fa-map-marker-alt me-2"></i>
                                Visit Our Store
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Customer Ratings Section -->
    <section class="customer-ratings-section">
        <div class="container">
            <div class="row align-items-center">
                <!-- Food Image Column -->
                <div class="col-lg-4 col-md-6 mb-4 mb-lg-0">
                    <div class="food-image-container">
                        <div class="food-plate">
                            <img src="images/homepage-chicken.png" alt="Fresh Chicken Drumsticks" class="food-image">
                        </div>
                                    </div>
                                </div>

                <!-- Testimonials Column -->
                <div class="col-lg-8 col-md-6">
                    <div class="testimonials-content">
                        <div class="section-header text-start mb-4">
                            <h2 class="section-title">Customer Ratings</h2>
                            <div class="title-divider"></div>
                            </div>

                        <div class="testimonial-carousel">
                            <div class="testimonial-slide" id="testimonialSlide">
                                <?php if (empty($testimonials)): ?>
                                    <!-- Enhanced fallback testimonials -->
                                    <div class="testimonial-content active" data-index="0">
                                        <div class="quote-mark">
                                            <i class="fas fa-quote-left"></i>
                        </div>
                                        <p class="testimonial-text">
                                            "The quality of meat from MikeMadz is exceptional! Fresh, properly frozen, and delivered right to my doorstep. 
                                            The chicken drumsticks are perfect for my family's needs and the pricing is very reasonable. Highly recommended!"
                                        </p>
                                    <div class="testimonial-rating">
                                            <i class="fas fa-star active"></i>
                                            <i class="fas fa-star active"></i>
                                            <i class="fas fa-star active"></i>
                                            <i class="fas fa-star active"></i>
                                            <i class="fas fa-star active"></i>
                                    </div>
                                        <div class="testimonial-author">
                                            <div class="author-avatar">
                                                <img src="https://images.unsplash.com/photo-1494790108755-2616b612b786?w=100&h=100&fit=crop&crop=face" alt="Marion Brix" class="avatar-img">
                                </div>
                                            <div class="author-info">
                                                <h4 class="author-name">Marion Brix</h4>
                                                <p class="author-title">Project Manager</p>
                            </div>
                        </div>
                    </div>
                                <?php else: ?>
                                    <?php foreach ($testimonials as $index => $testimonial): ?>
                                        <div class="testimonial-content <?= $index === 0 ? 'active' : '' ?>" data-index="<?= $index ?>">
                                            <div class="quote-mark">
                                                <i class="fas fa-quote-left"></i>
                                            </div>
                                            <p class="testimonial-text">
                                                "<?= htmlspecialchars($testimonial['review']) ?>"
                                            </p>
                                    <div class="testimonial-rating">
                                                <?php 
                                                $rating = (int)$testimonial['rating'];
                                                for ($i = 1; $i <= 5; $i++): ?>
                                                    <i class="fas fa-star <?= $i <= $rating ? 'active' : '' ?>"></i>
                                                <?php endfor; ?>
                                    </div>
                                            <div class="testimonial-author">
                                                <div class="author-avatar">
                                        <?php if (!empty($testimonial['profile_picture'])): ?>
                                                        <img src="<?= htmlspecialchars($testimonial['profile_picture']) ?>" alt="<?= htmlspecialchars($testimonial['first_name']) ?>" class="avatar-img">
                                        <?php else: ?>
                                                        <div class="avatar-placeholder">
                                            <?= strtoupper(substr($testimonial['first_name'], 0, 1)) ?>
                                                        </div>
                                        <?php endif; ?>
                                    </div>
                                                <div class="author-info">
                                                    <h4 class="author-name"><?= htmlspecialchars(trim($testimonial['first_name'] . ' ' . $testimonial['last_name'])) ?></h4>
                                                    <p class="author-title">Verified Customer</p>
                                        </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
                            </div>

                        </div>
                    </div>
                </div>
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
                    setTimeout(typeWriter, 50); // Speed of typing (50ms per character)
                } else {
                    // After typing is complete, show highlight text
                    setTimeout(() => {
                        highlightText.textContent = highlight;
                        // Hide cursor after highlight appears
                        setTimeout(() => {
                            cursor.style.display = 'none';
                        }, 200);
                    }, 200);
                }
            }
            
            // Start typing after a short delay
            setTimeout(typeWriter, 200);
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

        // Test function to check if categories are loading
        function testCategories() {
            console.log('Testing categories...');
            const categoriesRow = document.getElementById('categoriesRow');
            console.log('Categories row element:', categoriesRow);
            
            // Add a test category to see if the HTML structure works
            if (categoriesRow) {
                categoriesRow.innerHTML = `
                    <div class="category-carousel-item">
                        <a href="product.php?category=test" class="category-card text-decoration-none" style="background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('images/beef1.jpg'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">TEST CATEGORY</h3>
                                <p class="category-card-desc">Test description</p>
                            </div>
                        </a>
                    </div>
                `;
                console.log('Test category added');
            }
        }

        // Initialize page on load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Page loaded, initializing...');
            loadCategories();
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

        function loadCategories() {
            if (isLoading) return;
            
            isLoading = true;

            fetch('api/categories.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    console.log('Categories API Response:', data);
                    if (data.success) {
                        displayAllCategories(data.categories);
                        console.log(`Loaded ${data.categories.length} categories`);
                    } else {
                        console.error('Failed to load categories:', data.error);
                        showFallbackCategories();
                    }
                })
                .catch(error => {
                    console.error('Error loading categories:', error);
                    showFallbackCategories();
                })
                .finally(() => {
                    isLoading = false;
                });
        }

        function showFallbackCategories() {
            const categoriesRow = document.getElementById('categoriesRow');
            const fallbackCategories = [
                {
                    name: 'BEEF',
                    description: 'Premium beef cuts',
                    image: 'images/beef1.jpg',
                    url: 'product.php?category=beef'
                },
                {
                    name: 'CHICKEN',
                    description: 'Fresh chicken products',
                    image: 'images/breast.jpg',
                    url: 'product.php?category=chicken'
                },
                {
                    name: 'SEAFOOD',
                    description: 'Fresh fish and seafood',
                    image: 'images/bangus.jpg',
                    url: 'product.php?category=seafood'
                },
                {
                    name: 'PORK',
                    description: 'Quality pork products',
                    image: 'images/porkjowls.jpg',
                    url: 'product.php?category=pork'
                }
            ];
            
            displayCategories(fallbackCategories);
            
            // Disable navigation for fallback
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            prevBtn.disabled = true;
            nextBtn.disabled = true;
        }

        function displayAllCategories(categories) {
            console.log('Displaying categories:', categories);
            const categoriesRow = document.getElementById('categoriesRow');
            
            if (!categoriesRow) {
                console.error('categoriesRow element not found!');
                return;
            }
            
            if (categories.length === 0) {
                console.log('No categories to display');
                categoriesRow.innerHTML = '<div class="col-12 text-center text-muted">No categories available.</div>';
                return;
            }

            let html = '';
            
            // Add the last category at the beginning for seamless loop
            if (categories.length > 0) {
                const lastCategory = categories[categories.length - 1];
                html += `
                    <div class="category-carousel-item">
                        <a href="${lastCategory.url}" class="category-card text-decoration-none" style="background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('${lastCategory.image}'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">${lastCategory.name}</h3>
                                <p class="category-card-desc">${lastCategory.description}</p>
                            </div>
                        </a>
                    </div>
                `;
            }
            
            // Add all categories
            categories.forEach(category => {
                console.log('Processing category:', category);
                html += `
                    <div class="category-carousel-item">
                        <a href="${category.url}" class="category-card text-decoration-none" style="background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('${category.image}'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">${category.name}</h3>
                                <p class="category-card-desc">${category.description}</p>
                            </div>
                        </a>
                    </div>
                `;
            });
            
            // Add the first category at the end for seamless loop
            if (categories.length > 0) {
                const firstCategory = categories[0];
                html += `
                    <div class="category-carousel-item">
                        <a href="${firstCategory.url}" class="category-card text-decoration-none" style="background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('${firstCategory.image}'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">${firstCategory.name}</h3>
                                <p class="category-card-desc">${firstCategory.description}</p>
                            </div>
                        </a>
                    </div>
                `;
            }
            
            console.log('Generated HTML:', html);
            categoriesRow.innerHTML = html;
            
            // Set initial scroll position to center the first category (skip the duplicate at beginning)
            setTimeout(() => {
                if (categoriesRow.children.length > 1) {
                    const containerWidth = categoriesRow.parentElement.offsetWidth;
                    const firstItem = categoriesRow.children[1]; // Skip the duplicate last category
                    const itemWidth = firstItem.offsetWidth;
                    const centerOffset = (containerWidth - itemWidth) / 2;
                    categoriesRow.scrollLeft = centerOffset;
                    console.log('Initial scroll position set to:', centerOffset);
                }
            }, 200);
            
            // Enable navigation buttons
            const prevBtn = document.getElementById('prevBtn');
            const nextBtn = document.getElementById('nextBtn');
            if (prevBtn) {
                prevBtn.style.display = 'flex';
                prevBtn.disabled = false;
                console.log('Previous button enabled');
            }
            if (nextBtn) {
                nextBtn.style.display = 'flex';
                nextBtn.disabled = false;
                console.log('Next button enabled');
            }
            console.log('Categories loaded successfully');
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
                        <a href="${category.url}" class="category-card text-decoration-none" style="background: linear-gradient(rgba(0,0,0,0.2), rgba(0,0,0,0.2)), url('${category.image}'); background-size: cover; background-position: center;">
                            <div class="category-card-content">
                                <h3 class="category-card-title">${category.name}</h3>
                                <p class="category-card-desc">${category.description}</p>
                            </div>
                        </a>
                    </div>
                `;
            });
            
            categoriesRow.innerHTML = html;
        }



        // Simple index-based carousel
        let currentCategoryIndex = 0;
        let totalCategories = 0;

        function loadNextCategories() {
            console.log('Next button clicked');
            const categoriesRow = document.getElementById('categoriesRow');
            if (!categoriesRow || categoriesRow.children.length === 0) return;

            const firstItem = categoriesRow.children[0];
            const itemWidth = firstItem.offsetWidth;
            const gap = parseFloat(getComputedStyle(categoriesRow).gap);
            const scrollAmount = itemWidth + gap;
            
            // Always scroll forward by one category
            categoriesRow.scrollBy({
                left: scrollAmount,
                behavior: 'smooth'
            });
            
            // Check if we need to loop seamlessly
            setTimeout(() => {
                const maxScroll = categoriesRow.scrollWidth - categoriesRow.clientWidth;
                const currentScroll = categoriesRow.scrollLeft;
                
                // If we're at the duplicate at the end, instantly jump to the real beginning
                if (currentScroll >= maxScroll - 10) {
                    // Calculate position of the first real category (skip duplicate)
                    const firstRealItem = categoriesRow.children[1];
                    const firstItemWidth = firstRealItem.offsetWidth;
                    const centerOffset = (categoriesRow.parentElement.offsetWidth - firstItemWidth) / 2;
                    const targetPosition = centerOffset;
                    
                    // Instantly jump without animation
                    categoriesRow.style.scrollBehavior = 'auto';
                    categoriesRow.scrollLeft = targetPosition;
                    
                    // Re-enable smooth scrolling
                    setTimeout(() => {
                        categoriesRow.style.scrollBehavior = 'smooth';
                    }, 10);
                    
                    console.log('Seamlessly looped to beginning');
                }
            }, 300);
        }

        function loadPreviousCategories() {
            console.log('Previous button clicked');
            const categoriesRow = document.getElementById('categoriesRow');
            if (!categoriesRow || categoriesRow.children.length === 0) return;

            const firstItem = categoriesRow.children[0];
            const itemWidth = firstItem.offsetWidth;
            const gap = parseFloat(getComputedStyle(categoriesRow).gap);
            const scrollAmount = itemWidth + gap;
            
            console.log('Current scroll position:', categoriesRow.scrollLeft);
            console.log('Scroll amount:', scrollAmount);
            
            // Check if we're already at the beginning (duplicate SEA FOODS)
            if (categoriesRow.scrollLeft <= 50) {
                console.log('Already at beginning, jumping to end');
                
                // Calculate position of the last real category (skip duplicate BEEF at end)
                const lastRealItem = categoriesRow.children[categoriesRow.children.length - 2];
                const lastItemWidth = lastRealItem.offsetWidth;
                const centerOffset = (categoriesRow.parentElement.offsetWidth - lastItemWidth) / 2;
                
                // Instantly jump to the end
                categoriesRow.style.scrollBehavior = 'auto';
                categoriesRow.scrollLeft = centerOffset;
                
                // Re-enable smooth scrolling
                setTimeout(() => {
                    categoriesRow.style.scrollBehavior = 'smooth';
                }, 10);
                
                console.log('Jumped to end position:', centerOffset);
                return;
            }
            
            // Normal scroll backward
            categoriesRow.scrollBy({
                left: -scrollAmount,
                behavior: 'smooth'
            });
            
            console.log('Scrolled backward by:', scrollAmount);
        }

        function showCategory(index) {
            const categoriesRow = document.getElementById('categoriesRow');
            if (!categoriesRow || categoriesRow.children.length === 0) return;
            
            // Show all categories (for peeking effect)
            for (let i = 0; i < categoriesRow.children.length; i++) {
                categoriesRow.children[i].style.display = 'block';
            }
            
            // Calculate the actual index (accounting for duplicate at beginning)
            const actualIndex = index + 1; // Skip the duplicate at the beginning
            
            // Calculate scroll position to center the current category
            const containerWidth = categoriesRow.parentElement.offsetWidth;
            const itemWidth = categoriesRow.children[actualIndex].offsetWidth;
            const gap = parseFloat(getComputedStyle(categoriesRow).gap);
            
            // Calculate the scroll position to center the current item
            const scrollPosition = (itemWidth + gap) * actualIndex;
            const centerOffset = (containerWidth - itemWidth) / 2;
            const finalScrollPosition = scrollPosition - centerOffset;
            
            // Smooth scroll to the calculated position
            categoriesRow.scrollTo({
                left: finalScrollPosition,
                behavior: 'smooth'
            });
            
            console.log('Showing category index:', index, 'actual index:', actualIndex, 'scroll position:', finalScrollPosition);
        }

        // Testimonial Carousel Functionality
        let currentTestimonialIndex = 0;
        let testimonials = [];
        let isTransitioning = false;
        
        // Initialize testimonials array
        function initializeTestimonials() {
            const testimonialElements = document.querySelectorAll('.testimonial-content');
            testimonials = Array.from(testimonialElements);
            
            // If no testimonials, create fallback ones
            if (testimonials.length === 0) {
                testimonials = [
                    {
                        name: "Marion Brix",
                        title: "Project Manager",
                        rating: 5,
                        text: "The quality of meat from MikeMadz is exceptional! Fresh, properly frozen, and delivered right to my doorstep. The chicken drumsticks are perfect for my family's needs and the pricing is very reasonable. Highly recommended!",
                        avatar: "https://images.unsplash.com/photo-1494790108755-2616b612b786?w=100&h=100&fit=crop&crop=face"
                    },
                    {
                        name: "Jay Rodriguez",
                        title: "Chef",
                        rating: 5,
                        text: "As a professional chef, I demand the highest quality ingredients. MikeMadz consistently delivers premium cuts that exceed my expectations. Their frozen products maintain excellent texture and flavor.",
                        avatar: "https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=100&h=100&fit=crop&crop=face"
                    },
                    {
                        name: "Sarah Johnson",
                        title: "Food Blogger",
                        rating: 5,
                        text: "I've been ordering from MikeMadz for over a year now, and they never disappoint. The variety of products is amazing, and everything arrives perfectly frozen. Great customer service too!",
                        avatar: "https://images.unsplash.com/photo-1438761681033-6461ffad8d80?w=100&h=100&fit=crop&crop=face"
                    }
                ];
                createFallbackTestimonials();
            }
        }
        
        function createFallbackTestimonials() {
            const slideContainer = document.getElementById('testimonialSlide');
            if (!slideContainer) return;
            
            let html = '';
            testimonials.forEach((testimonial, index) => {
                html += `
                    <div class="testimonial-content ${index === 0 ? 'active' : ''}" data-index="${index}">
                        <div class="quote-mark">
                            <i class="fas fa-quote-left"></i>
                        </div>
                        <p class="testimonial-text">
                            "${testimonial.text}"
                        </p>
                        <div class="testimonial-rating">
                            ${Array.from({length: 5}, (_, i) => 
                                `<i class="fas fa-star ${i < testimonial.rating ? 'active' : ''}"></i>`
                            ).join('')}
                        </div>
                        <div class="testimonial-author">
                            <div class="author-avatar">
                                <img src="${testimonial.avatar}" alt="${testimonial.name}" class="avatar-img">
                            </div>
                            <div class="author-info">
                                <h4 class="author-name">${testimonial.name}</h4>
                                <p class="author-title">${testimonial.title}</p>
                            </div>
                        </div>
                    </div>
                `;
            });
            slideContainer.innerHTML = html;
            testimonials = Array.from(document.querySelectorAll('.testimonial-content'));
        }
        
        function showTestimonial(index) {
            if (isTransitioning || testimonials.length <= 1) return;
            
            isTransitioning = true;
            const testimonialContents = document.querySelectorAll('.testimonial-content');
            const currentActive = document.querySelector('.testimonial-content.active');
            
            // Remove active class from current testimonial
            if (currentActive) {
                currentActive.classList.remove('active');
            }
            
            // Add active class to new testimonial
            if (testimonialContents[index]) {
                testimonialContents[index].classList.add('active');
            }
            
            currentTestimonialIndex = index;
            
            // Reset transition flag after animation completes
            setTimeout(() => {
                isTransitioning = false;
            }, 600);
        }
        
        function nextTestimonial() {
            const nextIndex = (currentTestimonialIndex + 1) % testimonials.length;
            showTestimonial(nextIndex);
        }
        
        // Auto-rotate testimonials every 6 seconds
        function startTestimonialRotation() {
            if (testimonials.length > 1) {
                setInterval(() => {
                    nextTestimonial();
                }, 6000);
            }
        }
        
        // Initialize testimonials when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initializeTestimonials();
            startTestimonialRotation();
        });

    </script>
    
    <!-- Lazy Loading Script -->
    <script src="includes/lazy_loading.js"></script>
</body>
</html>