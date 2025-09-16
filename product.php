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
                    COALESCE(pp.selling_price, 0) AS price,
                    (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1,
                    COALESCE(AVG(pr.rating), 0) AS avg_rating,
                    COUNT(pr.rating) AS total_ratings
                FROM products p
                LEFT JOIN uom uom ON p.uom_id = uom.uom_id
                LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                LEFT JOIN product_ratings pr ON p.product_id = pr.product_id
                WHERE p.is_archive = 0
                GROUP BY p.product_id, p.product_name, p.product_description, uom.name, ps.current_stock, pp.selling_price
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
                        COALESCE(pp.selling_price, 0) AS price,
                        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1,
                        COALESCE(AVG(pr.rating), 0) AS avg_rating,
                        COUNT(pr.rating) AS total_ratings
                    FROM products p
                    LEFT JOIN uom uom ON p.uom_id = uom.uom_id
                    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
                    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                    LEFT JOIN product_ratings pr ON p.product_id = pr.product_id
                    WHERE p.category_id = :category_id AND p.is_archive = 0
                    GROUP BY p.product_id, p.product_name, p.product_description, uom.name, ps.current_stock, pp.selling_price
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
                COALESCE(pp.selling_price, 0) AS price,
                (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1,
                COALESCE(AVG(pr.rating), 0) AS avg_rating,
                COUNT(pr.rating) AS total_ratings
            FROM products p
            LEFT JOIN uom uom ON p.uom_id = uom.uom_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            LEFT JOIN product_ratings pr ON p.product_id = pr.product_id
            WHERE p.is_archive = 0
            GROUP BY p.product_id, p.product_name, p.product_description, uom.name, ps.current_stock, pp.selling_price
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
                COALESCE(pp.selling_price, 0) AS price,
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

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Catalog - MikeMadz</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
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
            background: var(--brand-gradient);
            color: white;
            border: none;
            padding: 0.6rem 1.2rem;
            border-radius: 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            margin-right: 0.5rem;
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }

        .btn-add-cart:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(127, 23, 52, 0.4);
            color: white;
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

        /* Enhanced Product Grid */
        .row.g-4 {
            margin-bottom: 2rem;
        }

        /* Enhanced Product Cards */
        .product-card {
            position: relative;
            overflow: hidden;
            cursor: pointer;
        }

        .product-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(127,23,52,0.05) 0%, rgba(169,29,66,0.05) 100%);
            opacity: 0;
            transition: all 0.4s ease;
            border-radius: 1rem;
            z-index: 1;
        }

        .product-card:hover::before {
            opacity: 1;
        }

        .product-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 20px 40px rgba(127,23,52,0.15);
        }

        .product-card:hover .swiper {
            transform: scale(1.02);
        }

        .product-card .swiper {
            transition: all 0.4s ease;
            z-index: 2;
            position: relative;
        }

        /* Enhanced Stock Badges */
        .product-badge {
            z-index: 10;
            backdrop-filter: blur(10px);
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border: 2px solid rgba(255,255,255,0.3);
        }

        .product-badge.in-stock {
            background: linear-gradient(135deg, var(--bs-success) 0%, #20c997 100%);
            animation: pulse-success 2s infinite;
        }

        .product-badge.low-stock {
            background: linear-gradient(135deg, var(--bs-warning) 0%, #fd7e14 100%);
            animation: pulse-warning 2s infinite;
        }

        .product-badge.out-of-stock {
            background: linear-gradient(135deg, var(--bs-danger) 0%, #e74c3c 100%);
            opacity: 0.9;
        }

        @keyframes pulse-success {
            0%, 100% { box-shadow: 0 0 0 0 rgba(25, 135, 84, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(25, 135, 84, 0); }
        }

        @keyframes pulse-warning {
            0%, 100% { box-shadow: 0 0 0 0 rgba(255, 193, 7, 0.7); }
            50% { box-shadow: 0 0 0 10px rgba(255, 193, 7, 0); }
        }

        /* Enhanced Category Section */
        .category-section {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            margin-bottom: 3rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            position: relative;
            overflow: hidden;
        }

        .category-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--brand-gradient);
        }

        .category-title {
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 800;
            margin-bottom: 1rem;
        }

        .category-select {
            border: 2px solid #e9ecef;
            border-radius: 1rem;
            padding: 0.8rem 1.2rem;
            font-weight: 600;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
        }

        .category-select:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
            transform: translateY(-2px);
        }

        /* Enhanced Product Title and Description */
        .product-title {
            transition: all 0.3s ease;
            z-index: 2;
            position: relative;
        }

        .product-card:hover .product-title {
            color: var(--brand-primary);
            transform: translateY(-2px);
        }

        .product-desc {
            z-index: 2;
            position: relative;
            opacity: 0.8;
            transition: all 0.3s ease;
        }

        .product-card:hover .product-desc {
            opacity: 1;
        }

        /* Enhanced Price Display */
        .product-price {
            z-index: 2;
            position: relative;
            font-size: 1.3rem;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            transition: all 0.3s ease;
        }

        .product-card:hover .product-price {
            transform: scale(1.05);
        }

        /* Enhanced Buttons */
        .btn-primary {
            background: var(--brand-gradient);
            border: none;
            border-radius: 1rem;
            font-weight: 600;
            padding: 0.75rem 1.5rem;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            z-index: 2;
        }

        .btn-primary::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            transition: all 0.6s ease;
            transform: translate(-50%, -50%);
        }

        .btn-primary:hover::before {
            width: 300%;
            height: 300%;
        }

        .btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(127,23,52,0.3);
        }

        .btn-favorite {
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            position: relative;
            z-index: 2;
        }

        .btn-favorite:hover {
            transform: translateY(-3px) scale(1.1);
        }

        .btn-favorite.favorited {
            animation: heartBeat 1.5s ease-in-out infinite;
        }

        @keyframes heartBeat {
            0% { transform: scale(1); }
            14% { transform: scale(1.2); }
            28% { transform: scale(1); }
            42% { transform: scale(1.2); }
            70% { transform: scale(1); }
        }

        /* Enhanced Swiper Styling */
        .swiper {
            border-radius: 1rem;
            overflow: hidden;
            margin-bottom: 1.5rem;
        }

        .swiper-pagination {
            bottom: 10px !important;
        }

        .swiper-pagination-bullet {
            background: var(--brand-primary);
            opacity: 0.5;
        }

        .swiper-pagination-bullet-active {
            opacity: 1;
            transform: scale(1.2);
        }

        /* Loading States */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* Enhanced Empty State */
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 1.5rem;
            border: 2px dashed #dee2e6;
            margin: 2rem 0;
        }

        .empty-state i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: var(--brand-primary);
            opacity: 0.3;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .empty-state h3 {
            color: var(--brand-primary);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .empty-state p {
            font-size: 1.1rem;
            max-width: 400px;
            margin: 0 auto;
        }

        /* Enhanced Promo Banner */
        .promo-banner {
            background: var(--brand-gradient);
            position: relative;
            overflow: hidden;
        }

        .promo-banner::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.2), transparent);
            animation: bannerShine 3s infinite;
        }

        @keyframes bannerShine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        /* Search Enhancement */
        .search-container {
            position: relative;
            margin-bottom: 2rem;
        }

        .search-input {
            border: 2px solid #e9ecef;
            border-radius: 2rem;
            padding: 1rem 3rem 1rem 1.5rem;
            font-weight: 500;
            background: white;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.05);
            width: 100%;
        }

        .search-input:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
            transform: translateY(-2px);
        }

        .search-icon {
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--brand-primary);
            font-size: 1.2rem;
        }

        /* Filter Tags */
        .filter-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
            margin-bottom: 2rem;
        }

        .filter-tag {
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 2rem;
            padding: 0.5rem 1.5rem;
            text-decoration: none;
            color: #6c757d;
            font-weight: 600;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .filter-tag.active,
        .filter-tag:hover {
            background: var(--brand-gradient);
            color: white;
            border-color: var(--brand-primary);
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127,23,52,0.2);
        }

        /* Responsive Enhancements */
        @media (max-width: 768px) {
            .category-section {
                padding: 2rem 1.5rem;
                margin-bottom: 2rem;
            }

            .product-card:hover {
                transform: translateY(-4px);
            }

            .filter-tags {
                justify-content: center;
            }

            .search-container {
                margin-bottom: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .category-section {
                padding: 1.5rem 1rem;
            }

            .product-card {
                margin-bottom: 1.5rem;
            }

            .filter-tag {
                padding: 0.4rem 1rem;
                font-size: 0.9rem;
            }
        }

        /* Reduced Motion Support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .product-badge {
                animation: none;
            }

            .btn-favorite.favorited {
                animation: none;
            }

            .empty-state i {
                animation: none;
            }
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
                        <div class="swiper mySwiper">
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

                        <h3 class="product-title"><?= htmlspecialchars($product['name']) ?></h3>
                        
                        <!-- Product Rating -->
                        <div class="product-rating">
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
                                    <span class="rating-count">(<?= $product['total_ratings'] ?>)</span>
                                </div>
                            <?php else: ?>
                                <div class="rating-stars no-rating">
                                    <span class="no-rating-text">No reviews yet</span>
                                </div>
                            <?php endif; ?>
                        </div>

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
                                <a href="product_detail.php?id=<?= $product['id'] ?>" class="btn btn-primary flex-grow-1">
                                    <i class="fas fa-eye me-2"></i>View Details
                                </a>
                                <button type="button" class="btn-favorite <?= in_array($product['id'], $user_favorites) ? 'favorited' : '' ?>" data-product-id="<?= $product['id'] ?>">
                                    <i class="fas fa-heart"></i>
                                </button>
                            </div>
                        <?php else: ?>
                            <button class="btn btn-secondary" disabled>Out of Stock</button>
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
        // Enhanced Swiper initialization with better effects
        document.addEventListener("DOMContentLoaded", function () {
            document.querySelectorAll(".mySwiper").forEach((el, index) => {
                new Swiper(el, {
                    loop: true,
                    autoplay: {
                        delay: 2500 + (index * 200), // Stagger autoplay timing
                        disableOnInteraction: false,
                    },
                    pagination: {
                        el: el.querySelector(".swiper-pagination"),
                        clickable: true,
                        dynamicBullets: true,
                    },
                    effect: 'fade',
                    fadeEffect: {
                        crossFade: true
                    },
                    speed: 800,
                    on: {
                        slideChange: function() {
                            // Add slight scale effect on slide change
                            const activeSlide = this.slides[this.activeIndex];
                            if (activeSlide) {
                                const img = activeSlide.querySelector('img');
                                if (img) {
                                    img.style.transform = 'scale(1.02)';
                                    setTimeout(() => {
                                        img.style.transform = 'scale(1)';
                                    }, 300);
                                }
                            }
                        }
                    }
                });
            });

            // Initialize other enhancements
            initProductCardAnimations();
            initSearchEnhancements();
            initLoadingStates();
            initAccessibilityFeatures();
        });

        // Enhanced product card animations
        function initProductCardAnimations() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach((entry, index) => {
                    if (entry.isIntersecting) {
                        setTimeout(() => {
                            entry.target.style.opacity = '1';
                            entry.target.style.transform = 'translateY(0)';
                            entry.target.classList.add('animate-in');
                        }, index * 100); // Stagger animation
                    }
                });
            }, observerOptions);

            // Observe product cards for animation
            document.querySelectorAll('.product-card').forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(30px)';
                card.style.transition = `opacity 0.6s ease ${index * 0.1}s, transform 0.6s ease ${index * 0.1}s`;
                observer.observe(card);

                // Add enhanced hover interactions
                card.addEventListener('mouseenter', function() {
                    // Create subtle ripple effect
                    const ripple = document.createElement('div');
                    ripple.style.cssText = `
                        position: absolute;
                        top: 0;
                        left: 0;
                        right: 0;
                        bottom: 0;
                        background: radial-gradient(circle, rgba(127,23,52,0.1) 0%, transparent 70%);
                        opacity: 0;
                        transition: opacity 0.3s ease;
                        border-radius: 1rem;
                        pointer-events: none;
                        z-index: 1;
                    `;
                    
                    this.appendChild(ripple);
                    setTimeout(() => { ripple.style.opacity = '1'; }, 10);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 300);
                });
            });
        }

        // Enhanced search functionality
        function initSearchEnhancements() {
            // Add search functionality if not exists
            const categorySection = document.querySelector('.category-section .row');
            if (categorySection && !document.querySelector('.search-container')) {
                const searchContainer = document.createElement('div');
                searchContainer.className = 'col-12 search-container';
                searchContainer.innerHTML = `
                    <div class="position-relative">
                        <input type="text" class="search-input" placeholder="Search products..." id="productSearch">
                        <i class="fas fa-search search-icon"></i>
                    </div>
                `;
                
                categorySection.insertBefore(searchContainer, categorySection.firstChild);
                
                // Add search functionality
                const searchInput = document.getElementById('productSearch');
                let searchTimeout;
                
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(() => {
                        filterProducts(this.value);
                    }, 300);
                });
            }
        }

        // Product filtering function
        function filterProducts(searchTerm) {
            const products = document.querySelectorAll('.product-card');
            const searchLower = searchTerm.toLowerCase();
            
            products.forEach(card => {
                const title = card.querySelector('.product-title')?.textContent.toLowerCase() || '';
                const desc = card.querySelector('.product-desc')?.textContent.toLowerCase() || '';
                
                if (title.includes(searchLower) || desc.includes(searchLower) || searchTerm === '') {
                    card.closest('.col-md-6, .col-lg-4, .col-xl-3').style.display = 'block';
                    card.style.animation = 'fadeInUp 0.5s ease-out';
                } else {
                    card.closest('.col-md-6, .col-lg-4, .col-xl-3').style.display = 'none';
                }
            });
            
            // Check if no products are visible
            const visibleProducts = Array.from(products).filter(card => 
                card.closest('.col-md-6, .col-lg-4, .col-xl-3').style.display !== 'none'
            );
            
            showEmptyState(visibleProducts.length === 0 && searchTerm !== '');
        }

        // Show/hide empty state
        function showEmptyState(show) {
            let emptyState = document.querySelector('.empty-state');
            const productsGrid = document.querySelector('.row.g-4');
            
            if (show && !emptyState) {
                emptyState = document.createElement('div');
                emptyState.className = 'empty-state';
                emptyState.innerHTML = `
                    <i class="fas fa-search"></i>
                    <h3>No Products Found</h3>
                    <p>Sorry, we couldn't find any products matching your search. Try adjusting your search terms or browse our categories.</p>
                `;
                productsGrid.appendChild(emptyState);
            } else if (!show && emptyState) {
                emptyState.remove();
            }
        }

        // Enhanced loading states
        function initLoadingStates() {
            // Add loading state to images
            document.querySelectorAll('.swiper img').forEach(img => {
                if (!img.complete) {
                    img.classList.add('loading-shimmer');
                    img.onload = function() {
                        this.classList.remove('loading-shimmer');
                        this.style.animation = 'fadeIn 0.5s ease-out';
                    };
                }
            });
            
            // Add loading state to category select
            const categorySelect = document.querySelector('.category-select');
            if (categorySelect) {
                categorySelect.addEventListener('change', function() {
                    // Add loading state to the form
                    this.style.opacity = '0.7';
                    this.style.pointerEvents = 'none';
                    
                    // Create loading overlay
                    const loadingOverlay = document.createElement('div');
                    loadingOverlay.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
                    loadingOverlay.style.cssText = `
                        position: absolute;
                        top: 50%;
                        right: 2rem;
                        transform: translateY(-50%);
                        color: var(--brand-primary);
                        font-size: 1.2rem;
                        z-index: 10;
                    `;
                    
                    this.parentElement.style.position = 'relative';
                    this.parentElement.appendChild(loadingOverlay);
                });
            }
        }

        // Enhanced accessibility features
        function initAccessibilityFeatures() {
            // Add keyboard navigation for product cards
            document.querySelectorAll('.product-card').forEach(card => {
                card.setAttribute('tabindex', '0');
                card.setAttribute('role', 'article');
                card.setAttribute('aria-label', `Product: ${card.querySelector('.product-title')?.textContent || 'Unknown'}`);
                
                card.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        const viewDetailsBtn = this.querySelector('.btn-primary');
                        if (viewDetailsBtn) {
                            viewDetailsBtn.click();
                        }
                    }
                });
                
                // Add focus styles
                card.addEventListener('focus', function() {
                    this.style.outline = '3px solid rgba(127, 23, 52, 0.3)';
                    this.style.outlineOffset = '2px';
                });
                
                card.addEventListener('blur', function() {
                    this.style.outline = 'none';
                });
            });

            // Enhance favorite buttons
            document.querySelectorAll('.btn-favorite').forEach(btn => {
                btn.setAttribute('aria-label', 'Add to favorites');
                
                btn.addEventListener('click', function() {
                    const isFavorited = this.classList.contains('favorited');
                    this.setAttribute('aria-label', isFavorited ? 'Remove from favorites' : 'Add to favorites');
                });
            });
        }

        // Add CSS animations dynamically
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
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
            
            .animate-in {
                animation: slideInUp 0.6s ease-out;
            }
            
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            /* Product Rating Styles */
        .product-rating {
            margin: 0.75rem 0;
            z-index: 2;
            position: relative;
        }

        .rating-stars {
            display: flex;
            align-items: center;
            gap: 0.25rem;
            margin-bottom: 0.5rem;
        }

        .star-filled {
            color: #ffc107;
            font-size: 0.9rem;
        }

        .star-empty {
            color: #dee2e6;
            font-size: 0.9rem;
        }

        .rating-score {
            font-weight: 600;
            color: var(--brand-primary);
            margin-left: 0.5rem;
            font-size: 0.9rem;
        }

        .rating-count {
            color: #6c757d;
            font-size: 0.85rem;
            margin-left: 0.25rem;
        }

        .no-rating-text {
            color: #6c757d;
            font-size: 0.85rem;
            font-style: italic;
        }

        /* Enhanced focus styles */
            .product-card:focus-within .btn-primary {
                opacity: 1;
                transform: translateY(0);
            }

            /* Improved button focus states */
            .btn-primary:focus,
            .btn-favorite:focus,
            .category-select:focus {
                outline: 3px solid rgba(127, 23, 52, 0.3) !important;
                outline-offset: 2px !important;
            }
        `;
        document.head.appendChild(style);

        // Cart functionality is now handled by the shared navbar

        // Add to cart functionality
        document.querySelectorAll('.add-to-cart-form').forEach(form => {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(this);
                const button = this.querySelector('.btn-add-cart');
                const originalText = button.innerHTML;
                
                button.disabled = true;
                button.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';

                fetch('cart.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // Update cart badge and show/hide based on quantity
                        const cartBadge = document.querySelector('.cart-badge');
                        if (cartBadge) {
                            cartBadge.textContent = data.cart_qty;
                            if (data.cart_qty > 0) {
                                cartBadge.style.display = 'flex';
                            } else {
                                cartBadge.style.display = 'none';
                            }
                        } else if (data.cart_qty > 0) {
                            // Create badge if it doesn't exist and there are items
                            const cartButton = document.querySelector('[onclick="toggleCart()"]');
                            if (cartButton) {
                                const newBadge = document.createElement('span');
                                newBadge.className = 'cart-badge';
                                newBadge.textContent = data.cart_qty;
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
                        
                        // Reset quantity to 1
                        const quantityInput = this.querySelector('input[name="quantity"]');
                        if (quantityInput) {
                            quantityInput.value = 1;
                        }
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

        // Favorite functionality
        document.querySelectorAll('.btn-favorite').forEach(btn => {
            btn.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const isFavorited = this.classList.contains('favorited');
                
                fetch('favorite.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'product_id=' + productId
                })
                .then(response => response.text())
                .then(data => {
                    let message, icon, headerClass;
                    
                    if (data === 'added') {
                        message = 'Added to favorites!';
                        icon = 'fas fa-heart text-danger';
                        headerClass = 'bg-success text-white';
                        
                        // Update button appearance to show it's favorited
                        this.classList.add('favorited');
                        this.style.background = 'var(--bs-danger)';
                        this.style.color = 'white';
                        this.style.borderColor = 'var(--bs-danger)';
                        
                    } else if (data === 'removed') {
                        message = 'Removed from favorites!';
                        icon = 'fas fa-heart-broken text-warning';
                        headerClass = 'bg-warning text-dark';
                        
                        // Update button appearance to show it's not favorited
                        this.classList.remove('favorited');
                        this.style.background = 'white';
                        this.style.color = 'var(--bs-secondary)';
                        this.style.borderColor = 'var(--bs-secondary)';
                        
                    } else if (data === 'not_logged_in') {
                        message = 'Please log in to add favorites';
                        icon = 'fas fa-exclamation-circle text-warning';
                        headerClass = 'bg-warning text-dark';
                        
                        // Redirect to login after showing message
                        setTimeout(() => {
                            window.location.href = 'login.php';
                        }, 2000);
                    } else {
                        message = 'Something went wrong. Please try again.';
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

        // --- Cart Item Manipulation Functionality ---
        // This section adds functionality to update quantity and remove items from the cart

        function updateCartItem(productId, quantity) {
            fetch('cart.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: `product_id=${productId}&action=${quantity > 0 ? 'update' : 'remove'}&quantity=${quantity}`
            })
            .then(response => response.text())
            .then(() => {
                window.location.reload(); // Reload to reflect changes
            });
        }

        // Event listeners for quantity change (plus/minus buttons)
        document.querySelectorAll('.cart-quantity-update').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                const currentQuantityInput = document.querySelector(`input[name="quantity"][data-product-id="${productId}"]`);
                let currentQuantity = parseInt(currentQuantityInput.value);

                if (this.classList.contains('fa-plus')) {
                    currentQuantity++;
                } else if (this.classList.contains('fa-minus')) {
                    currentQuantity = Math.max(0, currentQuantity - 1); // Ensure quantity doesn't go below 0
                }
                currentQuantityInput.value = currentQuantity;
                updateCartItem(productId, currentQuantity);
            });
        });

        // Event listeners for remove button
        document.querySelectorAll('.cart-item-remove').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-product-id');
                updateCartItem(productId, 0); // 0 quantity signifies removal
            });
        });
    </script>
</body>
</html>