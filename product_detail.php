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
                    ORDER BY pb.received_date DESC 
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
                    ORDER BY pb.received_date DESC, pb.batch_id DESC
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- SweetAlert2 CDN -->
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
            --brand-gradient: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            --brand-light: #f8f9fa;
        }

        * {
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            line-height: 1.6;
            color: var(--bs-dark);
        }
        
        .product-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }
        
        .product-card {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(127, 23, 52, 0.1);
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
            color: #495057;
            margin-bottom: 2rem;
            padding: 1.5rem;
            background: #f8f9fa;
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
            background: linear-gradient(135deg, #e8f5e8 0%, #d4edda 100%);
            color: var(--bs-success);
            padding: 1.5rem;
            border-radius: 1rem;
            margin-bottom: 2rem;
            font-weight: 700;
            font-size: 1.1rem;
            border: 2px solid rgba(25, 135, 84, 0.2);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .stock-info i {
            font-size: 1.3rem;
        }

        .purchase-options {
            background: white;
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(127, 23, 52, 0.1);
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
            color: var(--bs-dark);
            margin-bottom: 1rem;
            display: block;
        }
        
        .quantity-input {
            width: 120px;
            padding: 1rem;
            border: 3px solid #e9ecef;
            border-radius: 0.75rem;
            text-align: center;
            font-size: 1.2rem;
            font-weight: 700;
            margin-right: 1rem;
            transition: all 0.3s ease;
            background: white;
        }
        
        .quantity-input:focus {
            outline: none;
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            transform: scale(1.05);
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

        .swal2-danger .swal2-confirm {
            background: #dc3545 !important; /* Red for error */
        }

        .swal2-warning .swal2-confirm {
            background: #ffc107 !important; /* Yellow for warning */
            color: #212529 !important;
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
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1rem;
            cursor: pointer;
            transition: all 0.3s ease;
            background: white;
            position: relative;
        }

        .brand-option:hover {
            border-color: var(--brand-primary);
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.1);
        }

        .brand-option.selected {
            border-color: var(--brand-primary);
            background: linear-gradient(135deg, rgba(127, 23, 52, 0.05) 0%, rgba(169, 29, 66, 0.05) 100%);
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.2);
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
            color: #6c757d;
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
    </style>
</head>
<body>
   <?php include 'includes/user_promo.php'; ?>

    <?php (function(){ include 'includes/user_navbar.php'; })(); ?>

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
                        COUNT(orate.rating_id) AS rating_count
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
                            <input type="number" id="quantity" class="quantity-input" value="1" min="0.1" step="0.1" max="<?= htmlspecialchars($product['current_stock'] ?? 0) ?>">
                        </div>
                        
                        <button type="button" class="btn-add-cart" onclick="addToCart()" <?= (float)($product['current_stock'] ?? 0) <= 0 ? 'disabled' : '' ?>>
                            <i class="fas fa-cart-plus me-2"></i>
                            <?= (float)($product['current_stock'] ?? 0) <= 0 ? 'Out of Stock' : 'Add to Cart' ?>
                        </button>
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
            
            // Initialize cart functionality
            updateCartBadge();
            initializeCartRefresh();
            
            // Initialize brand selection
            initializeBrandSelection();
            
            // Quantity validation
            const quantityInput = document.getElementById('quantity');
            if (quantityInput) {
                quantityInput.addEventListener('input', function() {
                    const max = parseFloat(this.getAttribute('max'));
                    const value = parseFloat(this.value);
                    
                    if (value > max) {
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
                    }
                    
                    if (value < 0.1) {
                        this.value = 0.1;
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
                
                // If current value exceeds new max, adjust it
                if (parseFloat(quantityInput.value) > parseFloat(stock)) {
                    quantityInput.value = Math.min(parseFloat(stock), 1);
                }
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
            
            if (quantity < 0.1) {
                Swal.fire({
                    title: 'Invalid Quantity',
                    text: 'Please enter a valid quantity (minimum 0.1 kilos)',
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
                    
                    // Reset quantity to 1
                    document.getElementById('quantity').value = 1;
                } else {
                    const errorMessage = data.error || data.message || 'Failed to add product to cart';
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
    </script>
</body>
</html>