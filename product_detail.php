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
                    SELECT pp.markup_price
                    FROM product_pricing pp
                    WHERE pp.product_id = p.product_id
                    ORDER BY pp.productpricing_id DESC
                    LIMIT 1
                ), 0) as markup_price,
                COALESCE((
                    SELECT pp.cost_price
                    FROM product_pricing pp
                    WHERE pp.product_id = p.product_id
                    ORDER BY pp.productpricing_id DESC
                    LIMIT 1
                ), 0) as cost_per_unit,
                -- Calculate total price: markup_price + (newest batch cost for this product)
                COALESCE((
                    SELECT pp.markup_price
                    FROM product_pricing pp
                    WHERE pp.product_id = p.product_id
                    ORDER BY pp.productpricing_id DESC
                    LIMIT 1
                ), 0) + COALESCE((
                    SELECT pb.unit_cost 
                    FROM product_batches pb 
                    WHERE pb.product_id = p.product_id 
                    AND pb.quantity_remaining > 0 
                    AND pb.is_active = 1
                    ORDER BY pb.received_date DESC, pb.unit_cost DESC 
                    LIMIT 1
                ), (
                    SELECT pp.cost_price
                    FROM product_pricing pp
                    WHERE pp.product_id = p.product_id
                    ORDER BY pp.productpricing_id DESC
                    LIMIT 1
                ), 0) as total_price
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
            // Get up to 3 product images (primary first, then others)
            $img_stmt = $pdo->prepare("
                SELECT image_url, is_primary 
                FROM product_images 
                WHERE product_id = ? 
                ORDER BY is_primary DESC, product_image_id ASC 
                LIMIT 3
            ");
            $img_stmt->execute([$product_id]);
            $image_results = $img_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Set primary image and all images
            $product['primary_image'] = !empty($image_results) ? $image_results[0]['image_url'] : null;
            $product['all_images'] = array_column($image_results, 'image_url');
            
            // Get alternative brands for the same product type (same category)
            $alternative_brands = [];
            try {
                $brand_stmt = $pdo->prepare("
                    SELECT DISTINCT 
                        b.id as brand_id,
                        b.name as brand_name,
                        pb.batch_id,
                        pb.quantity_remaining,
                        pb.unit_cost,
                        pb.expiration_date,
                        pb.received_date,
                        pb.unit_cost as cost_price,
                        COALESCE(pp.markup_price, 0) as markup_price,
                        (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as total_price
                    FROM product_batches pb
                    INNER JOIN brands b ON pb.brand_id = b.id
                    LEFT JOIN product_pricing pp ON pb.product_id = pp.product_id
                    WHERE pb.product_id = ? 
                    AND pb.quantity_remaining > 0 
                    AND pb.is_active = 1
                    AND b.is_archived = 0
                    ORDER BY pb.received_date DESC, pb.unit_cost DESC, pb.batch_id DESC
                ");
                $brand_stmt->execute([$product_id]);
                $alternative_brands = $brand_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                
                // Group by brand and get the newest batch price/stock for each brand
                $brands_grouped = [];
                foreach ($alternative_brands as $brand) {
                    $brand_id = $brand['brand_id'];
                    if (!isset($brands_grouped[$brand_id])) {
                        $brands_grouped[$brand_id] = [
                            'brand_id' => $brand['brand_id'],
                            'brand_name' => $brand['brand_name'],
                            'total_stock' => 0,
                            'best_price' => $brand['total_price'], // Use newest batch price (first in DESC order)
                            'best_batch_id' => $brand['batch_id'],
                            'expiration_date' => $brand['expiration_date'],
                            'batches' => []
                        ];
                    }
                    
                    $brands_grouped[$brand_id]['total_stock'] += $brand['quantity_remaining'];
                    $brands_grouped[$brand_id]['batches'][] = $brand;
                    
                    // Since we're ordering by received_date DESC, the first batch per brand is the newest
                    // No need to compare prices - just use the first (newest) batch
                }
                
                $product['alternative_brands'] = array_values($brands_grouped);
                
            } catch (Exception $e) {
                error_log("Error fetching alternative brands: " . $e->getMessage());
                $product['alternative_brands'] = [];
            }
            
            // Debug: Log final product data
            error_log("Final product data: " . print_r($product, true));
            error_log("Product ID after image query: " . $product_id);
            error_log("Product ID type after image query: " . gettype($product_id));
            
            // Fetch customer ratings for this product with pagination
            $customer_ratings = [];
            $ratings_per_page = 5; // Show 5 ratings per page
            $current_page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
            $offset = ($current_page - 1) * $ratings_per_page;
            
            try {
                // Get total count of ratings for this product
                $count_stmt = $pdo->prepare("
                    SELECT COUNT(DISTINCT orate.rating_id) as total_ratings
                    FROM order_ratings orate
                    INNER JOIN orders o ON orate.order_id = o.orders_id
                    INNER JOIN order_items oi ON oi.order_id = o.orders_id AND oi.product_id = ?
                ");
                $count_stmt->execute([$product_id]);
                $total_ratings = $count_stmt->fetch(PDO::FETCH_ASSOC)['total_ratings'];
                
                // Calculate total pages
                $total_pages = ceil($total_ratings / $ratings_per_page);
                
                error_log("Total ratings for product $product_id: " . $total_ratings);
                error_log("Current page: $current_page, Total pages: $total_pages");
                
                $ratings_stmt = $pdo->prepare("
                    SELECT DISTINCT
                        orate.rating_id,
                        orate.rating,
                        orate.review,
                        orate.created_at,
                        COALESCE(ui.first_name, u.username) as customer_name,
                        COALESCE(ui.last_name, '') as customer_last_name,
                        oi.quantity,
                        'kilos' as unit_name,
                        b.name AS brand_name
                    FROM order_ratings orate
                    INNER JOIN orders o ON orate.order_id = o.orders_id
                    INNER JOIN order_items oi ON oi.order_id = o.orders_id AND oi.product_id = ?
                    LEFT JOIN users u ON orate.user_id = u.user_id
                    LEFT JOIN user_info ui ON u.user_id = ui.user_id
                    LEFT JOIN brands b ON b.id = oi.brand_id
                    ORDER BY orate.created_at DESC
                    LIMIT " . (int)$ratings_per_page . " OFFSET " . (int)$offset
                );
                $ratings_stmt->execute([$product_id]);
                $customer_ratings = $ratings_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Fetch images for each rating
                foreach ($customer_ratings as &$rating) {
                    $img_stmt = $pdo->prepare("
                        SELECT image_path 
                        FROM rating_images 
                        WHERE rating_id = ? 
                        ORDER BY image_order ASC
                    ");
                    $img_stmt->execute([$rating['rating_id']]);
                    $rating['images'] = $img_stmt->fetchAll(PDO::FETCH_COLUMN);
                }
                
                error_log("Customer ratings found for product $product_id: " . count($customer_ratings));
                error_log("Ratings data: " . print_r($customer_ratings, true));
            } catch (Exception $e) {
                error_log("Error fetching customer ratings: " . $e->getMessage());
                $customer_ratings = [];
                $total_ratings = 0;
                $total_pages = 0;
            }
            
            $product['customer_ratings'] = $customer_ratings;
            $product['ratings_pagination'] = [
                'current_page' => $current_page,
                'total_pages' => $total_pages,
                'total_ratings' => $total_ratings,
                'ratings_per_page' => $ratings_per_page
            ];
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
    <?php include 'includes/user_head.php'; ?>
    
    <!-- Cache busting meta tags -->
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
            color: var(--text-primary);
        }
        
        .product-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .product-card {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px var(--shadow-medium);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-light);
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

        /* Image Carousel Styles */
        .image-carousel {
            position: relative;
            margin-bottom: 2rem;
        }

        .main-image-container {
            position: relative;
            width: 100%;
            max-width: 500px;
            height: 450px;
            margin: 0 auto;
            border-radius: 1.25rem;
            overflow: hidden;
            box-shadow: 0 15px 35px rgba(0,0,0,0.1);
        }

        .main-image-container .product-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .main-image-container .product-image:hover {
            transform: scale(1.02);
        }

        /* Navigation Arrows */
        .carousel-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(255, 255, 255, 0.9);
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            z-index: 10;
        }

        .carousel-nav:hover {
            background: white;
            transform: translateY(-50%) scale(1.1);
            box-shadow: 0 6px 20px rgba(0,0,0,0.3);
        }

        .carousel-nav.prev {
            left: 15px;
        }

        .carousel-nav.next {
            right: 15px;
        }

        .carousel-nav i {
            font-size: 1.2rem;
            color: var(--brand-primary);
        }

        /* Thumbnail Container */
        .thumbnail-container {
            display: flex;
            justify-content: center;
            gap: 1rem;
            margin-top: 1.5rem;
            padding: 0 1rem;
        }

        .thumbnail {
            width: 80px;
            height: 80px;
            border-radius: 0.75rem;
            overflow: hidden;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid transparent;
            position: relative;
        }

        .thumbnail:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .thumbnail.active {
            border-color: var(--brand-primary);
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3);
        }

        .thumbnail.active::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(127, 23, 52, 0.1) 0%, rgba(169, 29, 66, 0.1) 100%);
            pointer-events: none;
        }

        .thumbnail img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.3s ease;
        }

        .thumbnail:hover img {
            transform: scale(1.1);
        }

        /* Responsive Design for Carousel */
        @media (max-width: 768px) {
            .main-image-container {
                height: 300px;
            }

            .carousel-nav {
                width: 40px;
                height: 40px;
            }

            .carousel-nav.prev {
                left: 10px;
            }

            .carousel-nav.next {
                right: 10px;
            }

            .thumbnail-container {
                gap: 0.75rem;
            }

            .thumbnail {
                width: 60px;
                height: 60px;
            }
        }

        @media (max-width: 576px) {
            .main-image-container {
                height: 250px;
            }

            .carousel-nav {
                width: 35px;
                height: 35px;
            }

            .carousel-nav i {
                font-size: 1rem;
            }

            .thumbnail-container {
                gap: 0.5rem;
            }

            .thumbnail {
                width: 50px;
                height: 50px;
            }
        }
        
        .product-title {
            font-size: 1.8rem;
            font-weight: 800;
            color: var(--brand-primary);
            margin-bottom: 1rem;
            line-height: 1.2;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .product-description {
            font-size: 1rem;
            line-height: 1.7;
            color: var(--text-secondary);
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: var(--bg-tertiary);
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
            font-size: 1.8rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .price.updating {
            transform: scale(1.05);
            text-shadow: 0 0 10px rgba(255, 255, 255, 0.5);
        }
        
        .unit {
            font-size: 1rem;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .stock-info {
            background: var(--bg-tertiary);
            color: var(--bs-success);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid var(--border-light);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stock-info i {
            font-size: 1.3rem;
        }

        .purchase-options {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px var(--shadow-medium);
            position: relative;
            overflow: hidden;
            border: 1px solid var(--border-light);
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
            font-size: 1.4rem;
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
            font-size: 1rem;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 1rem;
            display: block;
        }
        
        .quantity-input {
            width: 120px;
            padding: 1rem;
            border: 3px solid var(--border-light);
            border-radius: 0.75rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            margin-right: 1rem;
            transition: all 0.3s ease;
            background: var(--input-bg);
            color: var(--text-primary);
        }
        
        .quantity-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            transform: scale(1.05);
            background: var(--input-bg);
            color: var(--text-primary);
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
                font-size: 1.5rem;
            }

            .product-image {
                height: 300px;
            }

            .price {
                font-size: 1.5rem;
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
            background: var(--text-secondary) !important;
        }

        .swal2-success .swal2-confirm {
            background: #198754 !important; /* Green for success */
        }

        .swal2-danger .swal2-confirm {
            background: #dc3545 !important; /* Red for error */
        }

        .swal2-warning .swal2-confirm {
            background: #ffc107 !important; /* Yellow for warning */
            color: var(--text-primary) !important;
        }

        .swal2-info .swal2-confirm {
            background: #0dcaf0 !important; /* Blue for info */
        }

        .swal2-cancel {
            background: #6c757d !important; /* Gray for cancel */
        }

        /* Brand Selection Styles */
        .brand-selection {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .brand-option {
            border: 2px solid var(--border-light);
            border-radius: 0.75rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: var(--bg-card);
            position: relative;
        }

        .brand-option:hover {
            border-color: var(--brand-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px var(--shadow-medium);
        }

        .brand-option.selected {
            border-color: var(--brand-primary);
            background: var(--bg-tertiary);
            box-shadow: 0 4px 15px var(--shadow-medium);
        }

        .brand-option.selected::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--brand-gradient);
            border-radius: 0.75rem 0.75rem 0 0;
        }

        .brand-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .brand-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--brand-primary);
        }

        .brand-price {
            font-weight: 800;
            font-size: 1rem;
            color: var(--bs-success);
            background: linear-gradient(135deg, #198754 0%, #20c997 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .brand-details {
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .brand-details i {
            color: var(--brand-primary);
        }

        /* Responsive brand selection */
        @media (max-width: 768px) {
            .brand-selection {
                gap: 0.5rem;
            }

            .brand-option {
                padding: 0.75rem;
            }

            .brand-name {
                font-size: 1rem;
            }

            .brand-price {
                font-size: 1.1rem;
            }
        }
        
        /* Customer Ratings Styles */
        .ratings-container {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        
        .rating-card {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border-light);
            transition: all 0.3s ease;
            display: flex;
            flex-direction: column;
        }
        
        .rating-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px var(--shadow-medium);
            border-color: var(--brand-primary);
        }
        
        .rating-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }
        
        .customer-info {
            flex: 1;
        }
        
        .customer-name {
            font-weight: 700;
            font-size: 1rem;
            color: var(--brand-primary);
            margin-bottom: 0.25rem;
        }
        
        .order-quantity {
            font-size: 0.85rem;
            color: var(--text-secondary);
            display: flex;
            align-items: center;
        }
        
        .order-quantity i {
            color: var(--brand-primary);
        }
        
        .rating-stars {
            display: flex;
            gap: 0.25rem;
            margin-left: 1rem;
        }
        
        .rating-stars i {
            font-size: 1rem;
        }
        
        .rating-review {
            background: var(--bg-tertiary);
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border-left: 3px solid var(--brand-primary);
            flex: 1;
        }
        
        .rating-review p {
            font-size: 0.9rem;
            line-height: 1.5;
            color: var(--text-secondary);
            margin: 0;
        }
        
        .rating-images {
            margin-bottom: 1rem;
        }
        
        .rating-photo {
            max-width: 100%;
            max-height: 150px;
            width: auto;
            height: auto;
            border-radius: 0.75rem;
            cursor: pointer;
            transition: transform 0.3s ease;
            object-fit: cover;
        }
        
        .rating-photo:hover {
            transform: scale(1.05);
        }
        
        .rating-date {
            margin-top: auto;
            text-align: right;
        }
        
        .rating-date small {
            font-size: 0.8rem;
        }
        
        .rating-date i {
            color: var(--brand-primary);
        }

        /* Dark mode text fixes for specific elements */
        .text-muted {
            color: var(--text-secondary) !important;
        }

        /* Specific overrides for product detail page */
        .d-flex .text-muted {
            color: var(--text-secondary) !important;
        }

        .brand-details .text-muted {
            color: var(--text-secondary) !important;
        }

        .rating-date .text-muted {
            color: var(--text-secondary) !important;
        }

        .pagination-info .text-muted {
            color: var(--text-secondary) !important;
        }
        
        /* Pagination Styles */
        .ratings-pagination {
            background: var(--bg-card);
            border-radius: 1rem;
            padding: 1.5rem;
            border: 1px solid var(--border-light);
        }
        
        .pagination-info {
            font-weight: 600;
            color: var(--brand-primary);
        }
        
        .pagination .page-link {
            color: var(--brand-primary);
            border: 2px solid transparent;
            border-radius: 0.5rem;
            margin: 0 0.25rem;
            padding: 0.5rem 0.75rem;
            font-weight: 600;
            transition: all 0.3s ease;
            background: var(--bg-card);
        }
        
        .pagination .page-link:hover {
            color: white;
            background: var(--brand-primary);
            border-color: var(--brand-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }
        
        .pagination .page-item.active .page-link {
            color: white;
            background: var(--brand-gradient);
            border-color: var(--brand-primary);
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }
        
        .pagination .page-item.disabled .page-link {
            color: var(--text-secondary);
            background: var(--bg-tertiary);
            border-color: var(--border-light);
            cursor: not-allowed;
        }
        
        .pagination .page-item.disabled .page-link:hover {
            transform: none;
            box-shadow: none;
        }
        
        /* Responsive ratings */
        @media (max-width: 768px) {
            .rating-card {
                padding: 1rem;
            }
            
            .rating-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .rating-stars {
                margin-left: 0;
                margin-top: 0.5rem;
            }
            
            .customer-name {
                font-size: 0.9rem;
            }
            
            .order-quantity {
                font-size: 0.8rem;
            }
            
            .rating-review {
                padding: 0.75rem;
            }
            
            .rating-review p {
                font-size: 0.85rem;
            }
            
            .ratings-pagination {
                padding: 1rem;
            }
            
            .pagination-controls {
                margin-top: 1rem;
            }
            
            .pagination {
                justify-content: center;
            }
            
            .pagination .page-link {
                padding: 0.4rem 0.6rem;
                font-size: 0.9rem;
            }
        }
        
        @media (max-width: 576px) {
            .rating-card {
                padding: 0.75rem;
            }
            
            .rating-photo {
                max-height: 150px;
            }
            
            .ratings-pagination {
                padding: 0.75rem;
            }
            
            .pagination-info {
                text-align: center;
                margin-bottom: 1rem;
            }
            
            .pagination .page-link {
                padding: 0.3rem 0.5rem;
                font-size: 0.8rem;
                margin: 0 0.1rem;
            }
        }
    </style>
</head>
<body>
   <?php include 'includes/user_promo.php'; ?>
   <?php include 'includes/user_navbar.php'; ?>

    <div class="product-container">
        <button class="btn-back" onclick="window.location.href='product.php'">
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
                        COUNT(DISTINCT orate.rating_id) AS rating_count
                    FROM order_ratings orate
                    INNER JOIN orders o ON orate.order_id = o.orders_id
                    INNER JOIN order_items oi ON oi.order_id = o.orders_id
                    WHERE oi.product_id = ?
                ");
                $ratingStmt->execute([$product_id]);
                $r = $ratingStmt->fetch(PDO::FETCH_ASSOC);
                if ($r) { $avg_rating = (float)($r['avg_rating'] ?? 0); $rating_count = (int)($r['rating_count'] ?? 0); }

                // Total sold based on order_items quantities (only from completed/delivered orders)
                $soldStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(oi.quantity),0) AS total_sold
                    FROM order_items oi
                    INNER JOIN orders o ON oi.order_id = o.orders_id
                    INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
                    WHERE oi.product_id = ? 
                    AND os.status_name IN ('Delivered','Completed','Finished')
                ");
                $soldStmt->execute([$product_id]);
                $s = $soldStmt->fetch(PDO::FETCH_ASSOC);
                if ($s) { $total_sold = (float)($s['total_sold'] ?? 0); }
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
                    <?= number_format($total_sold) ?> sold
                </div>
            </div>
            <div class="row">
                <div class="col-lg-8">
                    <div class="product-card">
                        <!-- Image Carousel -->
                        <div class="image-carousel">
                            <!-- Main Image Display -->
                            <div class="main-image-container">
                                <img id="main-image" 
                                     src="<?= !empty($product['all_images']) ? 'admin/' . htmlspecialchars($product['all_images'][0]) : 'images/placeholder.jpg' ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                     class="product-image" 
                                     onerror="this.src='images/placeholder.jpg'">
                                
                                <!-- Navigation Arrows -->
                                <?php if (count($product['all_images']) > 1): ?>
                                <button class="carousel-nav prev" onclick="changeImage(-1)">
                                    <i class="fas fa-chevron-left"></i>
                                </button>
                                <button class="carousel-nav next" onclick="changeImage(1)">
                                    <i class="fas fa-chevron-right"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Thumbnail Navigation -->
                            <?php if (count($product['all_images']) > 1): ?>
                            <div class="thumbnail-container">
                                <?php foreach ($product['all_images'] as $index => $image): ?>
                                <div class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                                     onclick="setActiveImage(<?= $index ?>)" 
                                     data-index="<?= $index ?>">
                                    <img src="admin/<?= htmlspecialchars($image) ?>" 
                                         alt="<?= htmlspecialchars($product['product_name']) ?> - Image <?= $index + 1 ?>"
                                         onerror="this.src='images/placeholder.jpg'">
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <h1 class="product-title"><?= htmlspecialchars($product['product_name']) ?></h1>
                        
                        <p class="product-description"><?= htmlspecialchars($product['product_description'] ?? 'No description available') ?></p>
                        
                        <div class="price-section">
                            <div class="price" id="main-price">₱<?= number_format($product['total_price'] ?? 0, 2) ?></div>
                            <div class="unit">per kilo</div>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4">
                    <div class="product-card">
                        <h3>Add to Cart</h3>
                        
                        <!-- Brand Selection -->
                        <?php if (!empty($product['alternative_brands'])): ?>
                        <div class="mb-4">
                            <?php if (count($product['alternative_brands']) > 1): ?>
                                <label class="quantity-label">Choose Brand:</label>
                            <?php else: ?>
                                <label class="quantity-label">Available Brand:</label>
                            <?php endif; ?>
                            <div class="brand-selection">
                                <?php foreach ($product['alternative_brands'] as $index => $brand): ?>
                                <div class="brand-option <?= $index === 0 ? 'selected' : '' ?>" 
                                     data-brand-id="<?= $brand['brand_id'] ?>" 
                                     data-batch-id="<?= $brand['best_batch_id'] ?>"
                                     data-price="<?= $brand['best_price'] ?>"
                                     data-stock="<?= $brand['total_stock'] ?>"
                                     data-expiration="<?= $brand['expiration_date'] ?>">
                                    <div class="brand-header">
                                        <span class="brand-name"><?= htmlspecialchars($brand['brand_name']) ?></span>
                                        <span class="brand-price">₱<?= number_format($brand['best_price'], 2) ?></span>
                                    </div>
                                    <div class="brand-details">
                                        <small class="text-muted">
                                            <i class="fas fa-boxes me-1"></i><?= number_format($brand['total_stock'], 1) ?> kilos
                                            <?php if ($brand['expiration_date']): ?>
                                                <span class="ms-2">
                                                    <i class="fas fa-calendar me-1"></i>Expires: <?= date('M d, Y', strtotime($brand['expiration_date'])) ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <div class="stock-info">
                            <i class="fas fa-boxes me-2"></i>
                            <span id="stock-display">Stock: <?= number_format((float)($product['current_stock'] ?? 0), 1) ?> kilos available</span>
                            <span id="stock-badge">
                                <?php if ((float)($product['current_stock'] ?? 0) <= 0): ?>
                                    <span class="badge bg-danger ms-2">Out of Stock</span>
                                <?php elseif ((float)($product['current_stock'] ?? 0) <= 10): ?>
                                    <span class="badge bg-warning ms-2">Low Stock</span>
                                <?php endif; ?>
                            </span>
                        </div>
                        
                        <div class="mb-3">
                            <label class="quantity-label">Quantity (kilos):</label>
                            <input type="number" id="quantity" class="quantity-input" value="1" min="1.0" step="0.1" max="<?= htmlspecialchars($product['current_stock'] ?? 0) ?>">
                        </div>
                        
                        <button type="button" class="btn-add-cart" onclick="addToCart()" <?= (float)($product['current_stock'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-cart-plus me-2"></i>
                            <?= (float)($product['current_stock'] ?? 0) <= 0 ? 'Out of Stock' : 'Add to Cart' ?>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Customer Ratings Section -->
            <div class="row mt-4">
                <div class="col-12">
                    <div class="product-card">
                        <h3 class="mb-4">
                            <i class="fas fa-star text-warning me-2"></i>
                            Customer Reviews
                        </h3>
                        
                        <?php if (!empty($product['customer_ratings'])): ?>
                        <div class="ratings-container">
                            <?php foreach ($product['customer_ratings'] as $rating): ?>
                            <div class="rating-card mb-4">
                                <div class="rating-header">
                                    <div class="customer-info">
                                        <div class="customer-name">
                                            <?= htmlspecialchars($rating['customer_name']) ?>
                                            <?php if ($rating['customer_last_name']): ?>
                                                <?= htmlspecialchars($rating['customer_last_name']) ?>
                                            <?php endif; ?>
                                        </div>
                                        <div class="order-quantity">
                                            <i class="fas fa-shopping-bag me-1"></i>
                                            Ordered <?= number_format($rating['quantity'], 1) ?> <?= htmlspecialchars($rating['unit_name'] ?? 'kilos') ?>
                                            <?php if (!empty($rating['brand_name'])): ?>
                                                <span class="ms-2 text-muted">Brand: <?= htmlspecialchars($rating['brand_name']) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class="fas fa-star <?= $i <= $rating['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                                
                                <?php if ($rating['review']): ?>
                                <div class="rating-review">
                                    <p class="mb-0"><?= htmlspecialchars($rating['review']) ?></p>
                                </div>
                                <?php endif; ?>
                                
                                <?php if (!empty($rating['images'])): ?>
                                <div class="rating-images">
                                    <div class="row">
                                        <?php foreach ($rating['images'] as $index => $image): ?>
                                        <div class="col-md-4 mb-2">
                                            <img src="<?= htmlspecialchars($image) ?>" 
                                                 alt="Customer photo <?= $index + 1 ?>" 
                                                 class="img-thumbnail rating-photo"
                                                 onclick="openRatingImageModal('<?= htmlspecialchars($image) ?>')">
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                
                                <div class="rating-date">
                                    <small class="text-muted">
                                        <i class="fas fa-calendar me-1"></i>
                                        <?= date('M d, Y', strtotime($rating['created_at'])) ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <?php if (!empty($product['customer_ratings']) && $product['ratings_pagination']['total_pages'] > 1): ?>
                        <!-- Pagination Controls -->
                        <div class="ratings-pagination mt-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <div class="pagination-info">
                                    <small class="text-muted">
                                        Showing <?= (($product['ratings_pagination']['current_page'] - 1) * $product['ratings_pagination']['ratings_per_page']) + 1 ?>-<?= min($product['ratings_pagination']['current_page'] * $product['ratings_pagination']['ratings_per_page'], $product['ratings_pagination']['total_ratings']) ?> of <?= $product['ratings_pagination']['total_ratings'] ?> reviews
                                    </small>
                                </div>
                                <div class="pagination-controls">
                                    <nav aria-label="Ratings pagination">
                                        <ul class="pagination pagination-sm mb-0">
                                            <!-- Previous Button -->
                                            <?php if ($product['ratings_pagination']['current_page'] > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?= $product_id ?>&page=<?= $product['ratings_pagination']['current_page'] - 1 ?>" aria-label="Previous">
                                                    <i class="fas fa-chevron-left"></i>
                                                </a>
                                            </li>
                                            <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link" aria-label="Previous">
                                                    <i class="fas fa-chevron-left"></i>
                                                </span>
                                            </li>
                                            <?php endif; ?>
                                            
                                            <!-- Page Numbers -->
                                            <?php
                                            $start_page = max(1, $product['ratings_pagination']['current_page'] - 2);
                                            $end_page = min($product['ratings_pagination']['total_pages'], $product['ratings_pagination']['current_page'] + 2);
                                            
                                            // Show first page if not in range
                                            if ($start_page > 1): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?= $product_id ?>&page=1">1</a>
                                            </li>
                                            <?php if ($start_page > 2): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                            <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <!-- Current range of pages -->
                                            <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                                            <li class="page-item <?= $i == $product['ratings_pagination']['current_page'] ? 'active' : '' ?>">
                                                <a class="page-link" href="?id=<?= $product_id ?>&page=<?= $i ?>"><?= $i ?></a>
                                            </li>
                                            <?php endfor; ?>
                                            
                                            <!-- Show last page if not in range -->
                                            <?php if ($end_page < $product['ratings_pagination']['total_pages']): ?>
                                            <?php if ($end_page < $product['ratings_pagination']['total_pages'] - 1): ?>
                                            <li class="page-item disabled">
                                                <span class="page-link">...</span>
                                            </li>
                                            <?php endif; ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?= $product_id ?>&page=<?= $product['ratings_pagination']['total_pages'] ?>"><?= $product['ratings_pagination']['total_pages'] ?></a>
                                            </li>
                                            <?php endif; ?>
                                            
                                            <!-- Next Button -->
                                            <?php if ($product['ratings_pagination']['current_page'] < $product['ratings_pagination']['total_pages']): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?id=<?= $product_id ?>&page=<?= $product['ratings_pagination']['current_page'] + 1 ?>" aria-label="Next">
                                                    <i class="fas fa-chevron-right"></i>
                                                </a>
                                            </li>
                                            <?php else: ?>
                                            <li class="page-item disabled">
                                                <span class="page-link" aria-label="Next">
                                                    <i class="fas fa-chevron-right"></i>
                                                </span>
                                            </li>
                                            <?php endif; ?>
                                        </ul>
                                    </nav>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <?php else: ?>
                        <!-- No ratings yet - show placeholder -->
                        <div class="text-center py-5">
                            <div class="mb-3">
                                <i class="fas fa-star text-muted" style="font-size: 3rem;"></i>
                            </div>
                            <h5 class="text-muted mb-2">No Reviews Yet</h5>
                            <p class="text-muted mb-0">Be the first to review this product!</p>
                        </div>
                        <?php endif; ?>
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
        let selectedBrandId = null;
        let selectedBatchId = null;
        
        // Image carousel variables
        let currentImageIndex = 0;
        let productImages = <?= json_encode($product['all_images'] ?? []) ?>;

        // Wait for page to fully load
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Product detail page loaded');
            
            // Ensure quantity input starts with default value of 1
            const quantityInputInit = document.getElementById('quantity');
            if (quantityInputInit) {
                // Set to 1 if it's empty or invalid, but allow user to change to 0.1
                const currentValue = parseFloat(quantityInputInit.value);
                if (isNaN(currentValue) || currentValue <= 0) {
                    quantityInputInit.value = 1;
                }
            }
            
            // Initialize cart functionality
            updateCartBadge();
            initializeCartRefresh();
            
            // Initialize brand selection
            initializeBrandSelection();
            
            // Initialize pagination
            initializePagination();
            
            // Handle pagination refresh
            handlePaginationRefresh();
            
            // Refresh page when user returns from orders page (after rating submission)
            window.addEventListener('focus', function() {
                // Check if we should refresh (e.g., if user just submitted a rating)
                const lastActivity = localStorage.getItem('lastRatingSubmission');
                if (lastActivity) {
                    const timeSinceLastActivity = Date.now() - parseInt(lastActivity);
                    // If less than 30 seconds ago, refresh the page
                    if (timeSinceLastActivity < 30000) {
                        console.log('Detected recent rating submission, refreshing page...');
                        window.location.reload();
                    }
                }
            });
            
            // Quantity validation
            const quantityInputValidation = document.getElementById('quantity');
            if (quantityInputValidation) {
                // Validate on blur (when user finishes typing) instead of on every keystroke
                quantityInputValidation.addEventListener('blur', function() {
                    const max = parseFloat(this.getAttribute('max'));
                    const value = parseFloat(this.value);
                    
                    // Round to 1 decimal place to avoid floating point precision issues
                    const roundedValue = Math.round(value * 10) / 10;
                    
                    if (isNaN(value) || roundedValue < 1.0) {
                        this.value = 1.0;
                        Swal.fire({
                            title: 'Invalid Quantity',
                            text: 'Minimum quantity is 1.0 kilos',
                            icon: 'warning',
                            confirmButtonColor: '#ffc107',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Got it!',
                            customClass: {
                                popup: 'swal2-warning',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                    } else if (roundedValue > max) {
                        this.value = max;
                        Swal.fire({
                            title: 'Maximum Quantity',
                            text: `Maximum quantity is ${max.toFixed(1)} kilos`,
                            icon: 'warning',
                            confirmButtonColor: '#ffc107',
                            confirmButtonText: '<i class="fas fa-check me-1"></i>Got it!',
                            customClass: {
                                popup: 'swal2-warning',
                                confirmButton: 'swal2-confirm'
                            }
                        });
                    } else {
                        // Ensure the value is properly formatted
                        this.value = roundedValue.toFixed(1);
                    }
                });
                
                // Also validate on input for maximum value only (to prevent exceeding max immediately)
                quantityInputValidation.addEventListener('input', function() {
                    const max = parseFloat(this.getAttribute('max'));
                    const value = parseFloat(this.value);
                    
                    // Only check maximum on input, allow typing minimum values
                    if (!isNaN(value) && value > max) {
                        this.value = max;
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

        // Initialize brand selection functionality
        function initializeBrandSelection() {
            const brandOptions = document.querySelectorAll('.brand-option');
            
            brandOptions.forEach((option, index) => {
                
                option.addEventListener('click', function() {
                    // Remove selected class from all options
                    brandOptions.forEach(opt => opt.classList.remove('selected'));
                    
                    // Add selected class to clicked option
                    this.classList.add('selected');
                    
                    // Update global variables
                    selectedBrandId = this.dataset.brandId;
                    selectedBatchId = this.dataset.batchId;
                    
                    // Update stock display and quantity input
                    updateStockDisplay(this.dataset.stock);
                    updateQuantityInput(this.dataset.stock);
                    
                    // Update main price display
                    updateMainPrice(this.dataset.price);
                });
            });
            
            // Set default selection if brands exist (but don't update price display automatically)
            if (brandOptions.length > 0) {
                const firstOption = brandOptions[0];
                selectedBrandId = firstOption.dataset.brandId;
                selectedBatchId = firstOption.dataset.batchId;
                
                // Only update stock and quantity input, NOT the main price display
                // The main price should remain as the product's total_price
                updateStockDisplay(firstOption.dataset.stock);
                updateQuantityInput(firstOption.dataset.stock);
            }
        }

        // Update stock display based on selected brand
        function updateStockDisplay(stock) {
            const stockDisplay = document.getElementById('stock-display');
            const stockBadge = document.getElementById('stock-badge');
            
            if (stockDisplay) {
                stockDisplay.textContent = `Stock: ${parseFloat(stock).toFixed(1)} kilos available`;
            }
            
            if (stockBadge) {
                stockBadge.innerHTML = '';
                if (parseFloat(stock) <= 0) {
                    stockBadge.innerHTML = '<span class="badge bg-danger ms-2">Out of Stock</span>';
                } else if (parseFloat(stock) <= 10) {
                    stockBadge.innerHTML = '<span class="badge bg-warning ms-2">Low Stock</span>';
                }
            }
        }

        // Update quantity input max value
        function updateQuantityInput(stock) {
            const quantityInput = document.getElementById('quantity');
            const addButton = document.querySelector('.btn-add-cart');
            
            if (quantityInput) {
                quantityInput.setAttribute('max', stock);
                
                // If current value exceeds new max, adjust it to the maximum available stock
                // But preserve the user's input if it's valid (between min and max)
                const currentValue = parseFloat(quantityInput.value);
                const maxStock = parseFloat(stock);
                const minValue = 0.1;
                
                // Round to 1 decimal place to avoid floating point precision issues
                const roundedCurrentValue = Math.round(currentValue * 10) / 10;
                
                // Only adjust values if they're actually invalid
                if (roundedCurrentValue > maxStock) {
                    // Only adjust if current value exceeds stock
                    quantityInput.value = Math.max(minValue, maxStock).toFixed(1);
                } else if (roundedCurrentValue < minValue && roundedCurrentValue > 0) {
                    // Only adjust if current value is below minimum but not empty
                    quantityInput.value = minValue.toFixed(1);
                } else if (isNaN(roundedCurrentValue) || roundedCurrentValue <= 0) {
                    // Only set to minimum if value is invalid/empty
                    quantityInput.value = minValue.toFixed(1);
                } else {
                    // Ensure the value is properly formatted
                    quantityInput.value = roundedCurrentValue.toFixed(1);
                }
                // Don't change the value if it's already valid (including 0.1, 0.5, 0.9, etc.)
            }
            
            if (addButton) {
                if (parseFloat(stock) <= 0) {
                    addButton.disabled = true;
                    addButton.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Out of Stock';
                } else {
                    addButton.disabled = false;
                    addButton.innerHTML = '<i class="fas fa-cart-plus me-2"></i>Add to Cart';
                }
            }
        }

        function updateMainPrice(newPrice) {
            const mainPriceElement = document.getElementById('main-price');
            if (!mainPriceElement) return;
            
            const formattedPrice = '₱' + parseFloat(newPrice).toFixed(2);
            
            // Add updating animation
            mainPriceElement.classList.add('updating');
            
            // Update the price with a slight delay for smooth transition
            setTimeout(() => {
                mainPriceElement.textContent = formattedPrice;
                
                // Remove updating animation after a short delay
                setTimeout(() => {
                    mainPriceElement.classList.remove('updating');
                }, 300);
            }, 150);
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
        
        // Enhanced add to cart function with SweetAlert2
        function addToCart() {
            // Prevent multiple simultaneous requests
            if (isAddingToCart) {
                Swal.fire({
                    title: 'Please Wait',
                    text: 'Adding product to cart...',
                    icon: 'info',
                    confirmButtonColor: '#0dcaf0',
                    confirmButtonText: '<i class="fas fa-check me-1"></i>Got it!',
                    customClass: {
                        popup: 'swal2-info',
                        confirmButton: 'swal2-confirm'
                    }
                });
                return;
            }

            const quantity = parseFloat(document.getElementById('quantity').value);
            const addButton = document.querySelector('.btn-add-cart');
            
            console.log('Add to cart clicked. Product ID:', <?= $product ? $product['product_id'] : 0 ?>, 'Quantity:', quantity);
            console.log('Current brand selection:', {
                selectedBrandId: selectedBrandId,
                selectedBatchId: selectedBatchId
            });
            
            if (!addButton) {
                Swal.fire({
                    title: 'Error',
                    text: 'Add to cart button not found',
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
            
            if (addButton.disabled) {
                Swal.fire({
                    title: 'Out of Stock',
                    text: 'This product is currently out of stock',
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
            
            if (quantity < 1.0) {
                Swal.fire({
                    title: 'Invalid Quantity',
                    text: 'Please enter a valid quantity (minimum 1.0 kilos)',
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

            isAddingToCart = true;
            
            // Store original button content
            const originalButtonContent = addButton.innerHTML;
            
            // Add loading state
            addButton.disabled = true;
            addButton.classList.add('loading');
            addButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Adding to Cart...';
            
            // Show loading SweetAlert2
            Swal.fire({
                title: 'Adding to Cart...',
                text: 'Please wait while we add this item to your cart.',
                allowOutsideClick: false,
                showConfirmButton: false,
                didOpen: () => {
                    Swal.showLoading();
                },
                customClass: {
                    popup: 'swal2-info'
                }
            });
            
            // Enhanced form data with brand and batch information
            const formData = new FormData();
            formData.append('product_id', <?= $product ? $product['product_id'] : 0 ?>);
            formData.append('action', 'add');
            formData.append('quantity', quantity);
            formData.append('unit', 'kilo');
            
            // Use selected brand price if available, otherwise use main product price
            let unitPrice = <?= $product ? $product['total_price'] : 0 ?>;
            if (selectedBrandId) {
                const selectedBrand = document.querySelector('.brand-option.selected');
                if (selectedBrand) {
                    unitPrice = parseFloat(selectedBrand.dataset.price);
                }
            }
            formData.append('unit_price', unitPrice);
            formData.append('csrf_token', '<?= $_SESSION['csrf_token'] ?? '' ?>');
            
            // Add brand and batch information if available
            if (selectedBrandId) {
                formData.append('brand_id', selectedBrandId);
            } else {
                // Fallback: try to get brand from the first available brand option
                const firstBrandOption = document.querySelector('.brand-option');
                if (firstBrandOption) {
                    const fallbackBrandId = firstBrandOption.dataset.brandId;
                    const fallbackBatchId = firstBrandOption.dataset.batchId;
                    if (fallbackBrandId) {
                        formData.append('brand_id', fallbackBrandId);
                        console.log('Using fallback brand_id:', fallbackBrandId);
                    }
                    if (fallbackBatchId) {
                        formData.append('batch_id', fallbackBatchId);
                        console.log('Using fallback batch_id:', fallbackBatchId);
                    }
                } else {
                    console.warn('No brand options found and no fallback available!');
                }
            }
            if (selectedBatchId) {
                formData.append('batch_id', selectedBatchId);
            }
            
            console.log('Sending data to cart.php:', {
                product_id: <?= $product ? $product['product_id'] : 0 ?>,
                action: 'add',
                quantity: quantity,
                unit: 'kilo',
                unit_price: unitPrice,
                brand_id: selectedBrandId,
                batch_id: selectedBatchId
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
                    // Show success message (Simple Success style)
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
                    
                    // Reset quantity to default value (1) after adding to cart
                    document.getElementById('quantity').value = 1;
                } else {
                    const errorMessage = data.error || data.message || 'Failed to add product to cart';
                    
                    // Check if error is related to login requirement
                    if (errorMessage.toLowerCase().includes('log in') || errorMessage.toLowerCase().includes('login')) {
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
                        // Generic error handling for other errors
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
                }).then((retryResult) => {
                    if (retryResult.isConfirmed) {
                        // Retry the add to cart
                        addToCart();
                    }
                });
            })
            .finally(() => {
                isAddingToCart = false;
                // Reset button state
                addButton.disabled = false;
                addButton.classList.remove('loading');
                addButton.innerHTML = originalButtonContent;
            });
        }

        // Image Carousel Functions
        function changeImage(direction) {
            if (productImages.length <= 1) return;
            
            currentImageIndex += direction;
            
            // Loop around if at boundaries
            if (currentImageIndex >= productImages.length) {
                currentImageIndex = 0;
            } else if (currentImageIndex < 0) {
                currentImageIndex = productImages.length - 1;
            }
            
            updateMainImage();
            updateThumbnails();
        }

        function setActiveImage(index) {
            if (index < 0 || index >= productImages.length) return;
            
            currentImageIndex = index;
            updateMainImage();
            updateThumbnails();
        }

        function updateMainImage() {
            const mainImage = document.getElementById('main-image');
            if (mainImage && productImages[currentImageIndex]) {
                mainImage.src = 'admin/' + productImages[currentImageIndex];
                mainImage.alt = '<?= htmlspecialchars($product['product_name']) ?> - Image ' + (currentImageIndex + 1);
            }
        }

        function updateThumbnails() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            thumbnails.forEach((thumb, index) => {
                if (index === currentImageIndex) {
                    thumb.classList.add('active');
                } else {
                    thumb.classList.remove('active');
                }
            });
        }

        // Keyboard navigation for image carousel
        document.addEventListener('keydown', function(e) {
            if (productImages.length <= 1) return;
            
            if (e.key === 'ArrowLeft') {
                e.preventDefault();
                changeImage(-1);
            } else if (e.key === 'ArrowRight') {
                e.preventDefault();
                changeImage(1);
            }
        });

        // Touch/swipe support for mobile
        let touchStartX = 0;
        let touchEndX = 0;

        function handleSwipe() {
            const swipeThreshold = 50;
            const swipeDistance = touchEndX - touchStartX;
            
            if (Math.abs(swipeDistance) > swipeThreshold) {
                if (swipeDistance < 0) {
                    // Swipe left - next image
                    changeImage(1);
                } else {
                    // Swipe right - previous image
                    changeImage(-1);
                }
            }
        }
        
        // Function to open rating image modal
        function openRatingImageModal(imageSrc) {
            Swal.fire({
                title: 'Customer Photo',
                html: `<img src="${imageSrc}" alt="Customer photo" style="max-width: 100%; max-height: 80vh; object-fit: contain; border-radius: 0.5rem;">`,
                showConfirmButton: false,
                showCloseButton: true,
                customClass: {
                    popup: 'swal2-popup-large'
                }
            });
        }

        // Add touch event listeners to main image container
        document.addEventListener('DOMContentLoaded', function() {
            const mainImageContainer = document.querySelector('.main-image-container');
            if (mainImageContainer && productImages.length > 1) {
                mainImageContainer.addEventListener('touchstart', function(e) {
                    touchStartX = e.changedTouches[0].screenX;
                });

                mainImageContainer.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe();
                });
            }
        });

        // Pagination Functions
        function initializePagination() {
            const paginationLinks = document.querySelectorAll('.pagination .page-link');
            
            paginationLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    // Add loading state to pagination
                    const paginationContainer = document.querySelector('.ratings-pagination');
                    if (paginationContainer) {
                        paginationContainer.style.opacity = '0.7';
                        paginationContainer.style.pointerEvents = 'none';
                    }
                    
                    // Show loading indicator
                    const ratingsContainer = document.querySelector('.ratings-container');
                    if (ratingsContainer) {
                        ratingsContainer.innerHTML = `
                            <div class="text-center py-5">
                                <div class="spinner-border text-primary" role="status">
                                    <span class="visually-hidden">Loading...</span>
                                </div>
                                <p class="mt-3 text-muted">Loading reviews...</p>
                            </div>
                        `;
                    }
                });
            });
        }

        function handlePaginationRefresh() {
            // Smooth scroll to ratings section when pagination is used
            const urlParams = new URLSearchParams(window.location.search);
            const page = urlParams.get('page');
            
            if (page && page !== '1') {
                // Scroll to ratings section after a short delay
                setTimeout(() => {
                    const ratingsSection = document.querySelector('.ratings-container');
                    if (ratingsSection) {
                        ratingsSection.scrollIntoView({ 
                            behavior: 'smooth',
                            block: 'start'
                        });
                    }
                }, 500);
            }
        }

        // Enhanced pagination with smooth transitions
        function goToPage(pageNumber) {
            const currentUrl = new URL(window.location);
            currentUrl.searchParams.set('page', pageNumber);
            
            // Add loading state
            const ratingsContainer = document.querySelector('.ratings-container');
            if (ratingsContainer) {
                ratingsContainer.style.opacity = '0.5';
                ratingsContainer.style.transition = 'opacity 0.3s ease';
            }
            
            // Navigate to new page
            window.location.href = currentUrl.toString();
        }
    </script>
</body>
</html>