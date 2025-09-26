<?php
session_start();
include 'includes/db.php';

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

// Initialize cart if not set
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Helper function to get product data efficiently
function getProductData($pdo, $product_ids) {
    if (empty($product_ids)) return [];
    
    try {
        $placeholders = str_repeat('?,', count($product_ids) - 1) . '?';
        $sql = "SELECT 
                    p.product_id,
                    p.product_name,
                    p.product_description,
                    COALESCE((
                        SELECT pp.selling_price
                        FROM product_pricing pp
                        WHERE pp.product_id = p.product_id
                        ORDER BY pp.productpricing_id DESC
                        LIMIT 1
                    ), 0) AS price,
                    COALESCE((
                        SELECT ps.current_stock
                        FROM product_stock ps
                        WHERE ps.product_id = p.product_id
                        ORDER BY ps.last_restock_date DESC, ps.productstock_id DESC
                        LIMIT 1
                    ), 0) AS stock,
                    (SELECT pi.image_url FROM product_images pi 
                     WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                     ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                FROM products p
                WHERE p.product_id IN ($placeholders) AND p.is_archive = 0";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($product_ids);
        
        $products = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            if ($row && isset($row['product_id'])) {
                $products[$row['product_id']] = $row;
            }
        }
        return $products;
    } catch (Exception $e) {
        error_log("Error in getProductData: " . $e->getMessage());
        return [];
    }
}

// Helper function to calculate cart totals efficiently
function calculateCartTotals($pdo, $cart) {
    if (empty($cart)) {
        return ['total' => 0, 'quantity' => 0, 'items' => []];
    }
    
    try {
        $product_ids = array_keys($cart);
        $products = getProductData($pdo, $product_ids);
        
        $total = 0;
        $quantity = 0;
        $items = [];
        
        foreach ($cart as $cart_key => $cart_item) {
            $product_id = $cart_item['product_id'] ?? $cart_key;
            if (isset($products[$product_id])) {
                $product = $products[$product_id];
                $qty = $cart_item['quantity'] ?? 1;
                $unit_price = $cart_item['unit_price'] ?? $product['price'];
                $unit = $cart_item['unit'] ?? 'kilo';
                $box_id = $cart_item['box_id'] ?? null;
                $weight = $cart_item['weight'] ?? null;
                
                $item_total = $unit_price * $qty;
                
                $total += $item_total;
                $quantity += $qty;
                
                // Create display name with unit info
                $display_name = $product['product_name'] ?? 'Unknown Product';
                if ($unit === 'piece') {
                    $display_name .= ' (per piece)';
                } else if ($unit === 'box' && $weight) {
                    $display_name .= ' (Box - ' . number_format($weight, 2) . 'kg)';
                } else {
                    $display_name .= ' (per kilo)';
                }
                
                $items[$cart_key] = [
                    'id' => $product['product_id'] ?? 0,
                    'name' => $display_name,
                    'price' => (float)$unit_price,
                    'image1' => $product['image1'] ?? '',
                    'stock' => (int)($product['stock'] ?? 0),
                    'quantity' => $qty,
                    'total' => $item_total,
                    'unit' => $unit,
                    'box_id' => $box_id,
                    'weight' => $weight
                ];
            }
        }
        
        return ['total' => $total, 'quantity' => $quantity, 'items' => $items];
    } catch (Exception $e) {
        error_log("Error in calculateCartTotals: " . $e->getMessage());
        return ['total' => 0, 'quantity' => 0, 'items' => []];
    }
}

// Handle cart actions via AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }
    
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        echo json_encode(['success' => false, 'message' => 'Please log in to add items to cart']);
        exit;
    }

    $action = $_POST['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);

    if ($product_id > 0) {
        // Get current product data for validation
        $product_data = getProductData($pdo, [$product_id]);
        if (empty($product_data)) {
            echo json_encode(['success' => false, 'message' => 'Product not found']);
            exit;
        }
        
        $product = $product_data[$product_id];
        $current_qty = $_SESSION['cart'][$product_id]['quantity'] ?? 0;
        
        switch ($action) {
            case 'add':
                $quantity = intval($_POST['quantity'] ?? 1);
                $unit = $_POST['unit'] ?? 'kilo';
                $unit_price = floatval($_POST['unit_price'] ?? $product['price']);
                $box_id = $_POST['box_id'] ?? null;
                $weight = $_POST['weight'] ?? null;
                
                // Create unique cart key for different units/boxes
                $cart_key = $product_id . '_' . $unit;
                if ($unit === 'box' && $box_id) {
                    $cart_key .= '_' . $box_id;
                }
                
                // Check stock availability based on unit
                $stock_available = true;
                if ($unit === 'kilo') {
                    $stock_available = $quantity <= $product['stock'];
                } else if ($unit === 'piece') {
                    $conversion_rate = floatval($_POST['conversion'] ?? 1);
                    $kilos_needed = $quantity * $conversion_rate;
                    $stock_available = $kilos_needed <= $product['stock'];
                } else if ($unit === 'box') {
                    $stock_available = $quantity <= 1; // Only one box per selection
                }
                
                if (!$stock_available) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                    exit;
                }
                
                if (isset($_SESSION['cart'][$cart_key])) {
                    // Check if adding this quantity would exceed stock
                    $new_total_quantity = $_SESSION['cart'][$cart_key]['quantity'] + $quantity;
                    
                    // Re-check stock availability with new total quantity
                    $stock_available = true;
                    if ($unit === 'kilo') {
                        $stock_available = $new_total_quantity <= $product['stock'];
                    } else if ($unit === 'piece') {
                        $conversion_rate = floatval($_POST['conversion'] ?? 1);
                        $kilos_needed = $new_total_quantity * $conversion_rate;
                        $stock_available = $kilos_needed <= $product['stock'];
                    } else if ($unit === 'box') {
                        $stock_available = $new_total_quantity <= 1; // Only one box per selection
                    }
                    
                    if (!$stock_available) {
                        echo json_encode(['success' => false, 'message' => 'Adding this quantity would exceed available stock. Current in cart: ' . $_SESSION['cart'][$cart_key]['quantity'] . ', trying to add: ' . $quantity . ', available stock: ' . $product['stock']]);
                        exit;
                    }
                    
                    $_SESSION['cart'][$cart_key]['quantity'] += $quantity;
                } else {
                    $_SESSION['cart'][$cart_key] = [
                        'product_id' => $product_id,
                        'quantity' => $quantity,
                        'unit' => $unit,
                        'unit_price' => $unit_price,
                        'box_id' => $box_id,
                        'weight' => $weight
                    ];
                }
                break;

            case 'decrease':
                // Find and decrease the cart item by product_id
                foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
                    $item_product_id = $cart_item['product_id'] ?? $cart_key;
                    // Handle both simple product_id and composite keys
                    if (is_numeric($item_product_id)) {
                        $item_product_id = intval($item_product_id);
                    } else {
                        // Extract product_id from composite key (e.g., "123_kilo" -> 123)
                        $item_product_id = intval(explode('_', $item_product_id)[0]);
                    }
                    
                    if ($item_product_id == $product_id) {
                        $_SESSION['cart'][$cart_key]['quantity']--;
                        if ($_SESSION['cart'][$cart_key]['quantity'] <= 0) {
                            unset($_SESSION['cart'][$cart_key]);
                        }
                        break;
                    }
                }
                break;

            case 'delete':
                // Find and remove the cart item by product_id
                foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
                    $item_product_id = $cart_item['product_id'] ?? $cart_key;
                    // Handle both simple product_id and composite keys
                    if (is_numeric($item_product_id)) {
                        $item_product_id = intval($item_product_id);
                    } else {
                        // Extract product_id from composite key (e.g., "123_kilo" -> 123)
                        $item_product_id = intval(explode('_', $item_product_id)[0]);
                    }
                    
                    if ($item_product_id == $product_id) {
                        unset($_SESSION['cart'][$cart_key]);
                        break;
                    }
                }
                break;

            case 'update':
                $quantity = max(0, intval($_POST['quantity'] ?? 0));
                
                // Check stock availability
                if ($quantity > $product['stock']) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                    exit;
                }
                
                if ($quantity > 0) {
                    $_SESSION['cart'][$product_id] = ['quantity' => $quantity];
                } else {
                    unset($_SESSION['cart'][$product_id]);
                }
                break;
        }

        // Calculate updated totals efficiently
        $cart_data = calculateCartTotals($pdo, $_SESSION['cart']);
        
        // If calculateCartTotals returns empty items, calculate manually
        if (empty($cart_data['items']) && !empty($_SESSION['cart'])) {
            $total = 0;
            $quantity = 0;
            $item_total = 0;
            $item_quantity = 0;
            
            foreach ($_SESSION['cart'] as $pid => $cart_item) {
                $product_data = getProductData($pdo, [$pid]);
                if (!empty($product_data[$pid])) {
                    $product = $product_data[$pid];
                    $qty = $cart_item['quantity'] ?? 1;
                    $item_total_price = ($product['price'] ?? 0) * $qty;
                    
                    $total += $item_total_price;
                    $quantity += $qty;
                    
                    if ($pid == $product_id) {
                        $item_total = $item_total_price;
                        $item_quantity = $qty;
                    }
                }
            }
            
            $cart_data = ['total' => $total, 'quantity' => $quantity, 'items' => []];
        } else {
            $item_total = $cart_data['items'][$product_id]['total'] ?? 0;
            $item_quantity = $cart_data['items'][$product_id]['quantity'] ?? 0;
        }
        
        echo json_encode([
            'success' => true,
            'cart_total' => $cart_data['total'],
            'cart_qty' => $cart_data['quantity'],
            'cart_count' => count($_SESSION['cart']),
            'item_total' => $item_total,
            'quantity' => $item_quantity
        ]);
        exit;
    }
}

// Prepare cart items for display
$cart_data = calculateCartTotals($pdo, $_SESSION['cart']);
$cart_items = $cart_data['items'] ?? [];

// If calculateCartTotals returns empty items but we have cart data, use the session data directly
if (empty($cart_items) && !empty($_SESSION['cart'])) {
    $cart_items = [];
    $cart_total = 0;
    $cart_quantity = 0;
    
    foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
        $product_id = $cart_item['product_id'] ?? $cart_key;
        $product_data = getProductData($pdo, [$product_id]);
        if (!empty($product_data[$product_id])) {
            $product = $product_data[$product_id];
            $qty = $cart_item['quantity'] ?? 1;
            $unit_price = $cart_item['unit_price'] ?? $product['price'];
            $unit = $cart_item['unit'] ?? 'kilo';
            $box_id = $cart_item['box_id'] ?? null;
            $weight = $cart_item['weight'] ?? null;
            
            $item_total = $unit_price * $qty;
            
            $cart_total += $item_total;
            $cart_quantity += $qty;
            
            // Create display name with unit info
            $display_name = $product['product_name'] ?? 'Unknown Product';
            if ($unit === 'piece') {
                $display_name .= ' (per piece)';
            } else if ($unit === 'box' && $weight) {
                $display_name .= ' (Box - ' . number_format($weight, 2) . 'kg)';
            } else {
                $display_name .= ' (per kilo)';
            }
            
            $cart_items[] = [
                'product' => [
                    'id' => $product['product_id'] ?? 0,
                    'name' => $display_name,
                    'price' => (float)$unit_price,
                    'image1' => $product['image1'] ?? '',
                    'stock' => (int)($product['stock'] ?? 0)
                ],
                'quantity' => $qty,
                'total' => $item_total,
                'unit' => $unit,
                'box_id' => $box_id,
                'weight' => $weight
            ];
        }
    }
    
    $cart_data = ['total' => $cart_total, 'quantity' => $cart_quantity, 'items' => $cart_items];
} else {
    $cart_total = $cart_data['total'] ?? 0;
}

// Ensure cart_items is an array and validate structure
if (!is_array($cart_items)) {
    $cart_items = [];
}

// Get product recommendations
$recommendations = [];
try {
    $exclude_ids = empty($_SESSION['cart']) ? [0] : array_map('intval', array_keys($_SESSION['cart']));
    $placeholders = str_repeat('?,', count($exclude_ids) - 1) . '?';
    
    $sql = "SELECT p.product_id as id, p.product_name as name, 
                   COALESCE(pp.selling_price, 0) as price,
                   (SELECT pi.image_url FROM product_images pi 
                    WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                    ORDER BY pi.product_image_id DESC LIMIT 1) as image1
            FROM products p
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            WHERE p.is_archive = 0 AND p.product_id NOT IN ($placeholders)
            ORDER BY RAND() 
            LIMIT 4";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($exclude_ids);
    $recommendations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Recommendations error: " . $e->getMessage());
    $recommendations = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shopping Cart - Fresh Cart</title>
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

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }


        .cart-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 2rem 0;
            overflow: hidden;
        }

        .main-cart-header {
            background: var(--bs-secondary) !important;
            color: white !important;
            padding: 2rem;
            text-align: center;
        }

        .main-cart-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 0;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .form-section {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .section-title {
            color: var(--bs-secondary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            gap: 1rem;
        }

        .cart-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 2px solid #e9ecef;
        }

        .product-details {
            flex: 1;
            min-width: 0;
        }

        .product-name {
            font-weight: 600;
            color: var(--bs-secondary);
            margin-bottom: 0.25rem;
        }

        .product-price {
            font-weight: 700;
            color: var(--bs-danger);
            font-size: 1.25rem;
        }

        .stock-info {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 0.25rem;
        }

        .quantity-controls {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin: 0 1rem;
            flex-shrink: 0;
        }

        .quantity-btn {
            background: var(--bs-light);
            border: 2px solid #e9ecef;
            color: var(--bs-secondary);
            width: 40px;
            height: 40px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
        }

        .quantity-btn:hover:not(:disabled) {
            background: var(--bs-secondary);
            color: white;
            border-color: var(--bs-secondary);
        }

        .quantity-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .quantity-display {
            min-width: 60px;
            text-align: center;
            font-weight: 600;
            font-size: 1.1rem;
            color: var(--bs-dark);
        }

        .item-total {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            min-width: 100px;
            text-align: right;
            flex-shrink: 0;
        }

        .remove-btn {
            background: none;
            border: none;
            color: var(--bs-danger);
            padding: 0.5rem;
            border-radius: 0.5rem;
            transition: all 0.3s;
            cursor: pointer;
            flex-shrink: 0;
        }

        .remove-btn:hover {
            background: var(--bs-danger);
            color: white;
        }

        .order-summary {
            background: #f8f9fa;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            position: sticky;
            top: 2rem;
        }

        .summary-title {
            color: var(--bs-secondary);
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .summary-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }

        .summary-row:last-child {
            border-bottom: none;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--bs-secondary);
            border-top: 2px solid var(--bs-secondary);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .checkout-btn {
            background: var(--bs-secondary);
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
            width: 100%;
        }

        .checkout-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.4);
        }

        .checkout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: #6c757d;
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        .empty-cart h3 {
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .continue-shopping {
            background: var(--bs-secondary);
            color: white;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s;
            display: inline-block;
        }

        .continue-shopping:hover {
            background: #6d1429;
            color: white;
            transform: translateY(-2px);
        }

        .recommendations {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .recommendations h3 {
            color: var(--bs-secondary);
            font-weight: 700;
            text-align: center;
            margin-bottom: 2rem;
        }

        .recommendation-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }

        .recommendation-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--bs-secondary);
        }

        .recommendation-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }

        .security-info {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            text-align: center;
            margin-top: 1rem;
        }

        .security-info small {
            color: #6c757d;
        }

        .loading {
            opacity: 0.6;
            pointer-events: none;
        }

        .alert-custom {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .product-image {
                margin-right: 0;
            }

            .quantity-controls,
            .item-total {
                margin: 0;
            }

            .main-cart-header h1 {
                font-size: 2rem;
            }
            
            .order-summary {
                position: static;
                margin-top: 2rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/promo_banner.php'; ?>

    <?php include 'includes/user_navbar.php'; ?>

    <div class="container my-5">
        <!-- Alert Container -->
        <div id="alert-container"></div>

        <?php if (empty($cart_items)): ?>
            <div class="cart-container">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p class="mb-4">Add some products to your cart before checking out.</p>
                    <a href="product.php" class="continue-shopping">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="cart-container">
                <div class="main-cart-header">
                    <h1><i class="fas fa-shopping-cart me-3"></i>Shopping Cart</h1>
                </div>

                <div class="row p-4">
                    <!-- Main Cart Items -->
                    <div class="col-lg-8">
                        <!-- Cart Items Review -->
                        <div class="form-section">
                             <h3 class="section-title">
                                 <i class="fas fa-shopping-cart"></i>
                                 Your Order (<?= count($cart_items) ?>)
                             </h3>
                            <?php foreach ($cart_items as $item): ?>
                                <?php   
                                $product = $item['product'] ?? $item;
                                $quantity = $item['quantity'] ?? 0;
                                $total = $item['total'] ?? 0;
                                ?>
                                <?php if (is_array($product) && isset($product['id'])): ?>
                                    <div class="cart-item" data-product-id="<?= $product['id'] ?? 0 ?>">
                                        <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>" 
                                             alt="<?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?>" 
                                             class="product-image"
                                             onerror="this.src='images/placeholder.jpg'">
                                        
                                        <div class="product-details">
                                            <div class="product-name"><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></div>
                                            <div class="text-muted">Price: ₱<?= number_format($product['price'] ?? 0, 2) ?></div>
                                            <div class="stock-info">
                                                <i class="fas fa-box me-1"></i>
                                                <?= ($product['stock'] ?? 0) > 0 ? ($product['stock'] ?? 0) . ' in stock' : 'Out of stock' ?>
                                            </div>
                                        </div>
                                        
                                        <div class="quantity-controls">
                                            <button class="quantity-btn decrease" data-product-id="<?= $product['id'] ?? 0 ?>" 
                                                    <?= $quantity <= 1 ? 'disabled' : '' ?>>
                                                <i class="fas fa-minus"></i>
                                            </button>
                                            <span class="quantity-display cart-qty" data-product-id="<?= $product['id'] ?? 0 ?>" data-stock="<?= $product['stock'] ?? 0 ?>"><?= $quantity ?></span>
                                            <button class="quantity-btn increase" data-product-id="<?= $product['id'] ?? 0 ?>" 
                                                    <?= $quantity >= ($product['stock'] ?? 0) ? 'disabled' : '' ?>>
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                        
                                        <div class="product-price">₱<?= number_format($total, 2) ?></div>
                                        
                                        <button class="remove-btn remove-item" data-product-id="<?= $product['id'] ?? 0 ?>" title="Remove Item">
                                            <i class="fas fa-trash-alt"></i>
                                        </button>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Order Summary Sidebar -->
                    <div class="col-lg-4">
                        <div class="order-summary">
                            <h3 class="summary-title">
                                <i class="fas fa-receipt me-2"></i>Order Summary
                            </h3>

                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span class="fw-bold">₱<?= number_format($cart_total, 2) ?></span>
                            </div>

                            <div class="summary-row">
                                <span>Shipping:</span>
                                <span class="text-success fw-bold">FREE</span>
                            </div>

                            <div class="summary-row">
                                <span>Tax:</span>
                                <span class="fw-bold">₱0.00</span>
                            </div>

                            <div class="summary-row">
                                <span>Total:</span>
                                <span id="total-with-shipping">₱<?= number_format($cart_total, 2) ?></span>
                            </div>

                            <button class="checkout-btn checkout-btn-action">
                                <i class="fas fa-lock me-2"></i>Secure Checkout
                            </button>

                            <div class="text-center mt-4">
                                <small class="text-muted">
                                    <i class="fas fa-shield-alt me-1"></i>SSL Secure Checkout
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recommendations -->
        <?php if (!empty($recommendations)): ?>
            <div class="recommendations mt-5">
                <h3>
                    <i class="fas fa-heart me-2"></i>You Might Also Like
                </h3>
                <div class="row g-4">
                    <?php foreach ($recommendations as $product): ?>
                        <?php if (is_array($product) && isset($product['id'])): ?>
                            <div class="col-sm-6 col-md-4 col-lg-3">
                                <div class="recommendation-card">
                                    <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>"
                                         alt="<?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?>"
                                         class="recommendation-image"
                                         onerror="this.src='images/placeholder.jpg'">
                                    <h6 class="product-name"><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></h6>
                                    <div class="product-price mb-3">₱<?= number_format($product['price'] ?? 0, 2) ?></div>
                                    <a href="product.php?id=<?= $product['id'] ?? 0 ?>" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-eye me-1"></i>View Product
                                    </a>
                                </div>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- Debug: Show recommendations count -->
            <div class="recommendations mt-5">
                <h3>
                    <i class="fas fa-heart me-2"></i>You Might Also Like
                </h3>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle me-2"></i>
                    No recommendations available. Cart has <?= count($_SESSION['cart'] ?? []) ?> items.
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Loading States -->
    <?php include 'includes/loading_states.php'; ?>

    <script>
        // Show alert function
        function showAlert(message, type = 'info') {
            const alertContainer = document.getElementById('alert-container');
            const alertHtml = `
                <div class="alert alert-${type} alert-dismissible fade show alert-custom" role="alert">
                    <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'danger' ? 'exclamation-triangle' : 'info-circle'} me-2"></i>
                    ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            alertContainer.innerHTML = alertHtml;
            
            // Auto-dismiss after 5 seconds
            setTimeout(() => {
                const alert = alertContainer.querySelector('.alert');
                if (alert) {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }
            }, 5000);
        }

        // Loading state management
        function setLoading(element, loading = true) {
            if (!element) return;
            if (loading) {
                element.classList.add('loading');
                // Only disable form elements, not divs
                if (element.tagName === 'BUTTON' || element.tagName === 'INPUT' || element.tagName === 'SELECT') {
                    element.disabled = true;
                }
            } else {
                element.classList.remove('loading');
                if (element.tagName === 'BUTTON' || element.tagName === 'INPUT' || element.tagName === 'SELECT') {
                    element.disabled = false;
                }
            }
        }

        // Quantity control functions
        document.querySelectorAll('.increase').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                updateQuantity(productId, 'add');
            });
        });

        document.querySelectorAll('.decrease').forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.dataset.productId;
                updateQuantity(productId, 'decrease');
            });
        });

        document.querySelectorAll('.remove-item').forEach(button => {
            button.addEventListener('click', function() {
                if (confirm('Are you sure you want to remove this item from your cart?')) {
                    const productId = this.dataset.productId;
                    removeItem(productId);
                }
            });
        });

        function updateQuantity(productId, action) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', action);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            // Set loading state
            const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
            setCartItemLoading(cartItem, true);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const qtySpan = document.querySelector(`.cart-qty[data-product-id="${productId}"]`);
                    if (qtySpan) {
                        if (data.quantity > 0) {
                            qtySpan.textContent = data.quantity;
                            const itemTotalElement = qtySpan.closest('.cart-item').querySelector('.product-price');
                            if (itemTotalElement) {
                                itemTotalElement.textContent = '₱' + Number(data.item_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                            }
                            
                            // Update button states
                            const decreaseBtn = qtySpan.closest('.cart-item').querySelector('.decrease');
                            const increaseBtn = qtySpan.closest('.cart-item').querySelector('.increase');
                            const stock = parseInt(qtySpan.getAttribute('data-stock'));
                            
                            decreaseBtn.disabled = data.quantity <= 1;
                            increaseBtn.disabled = data.quantity >= stock;
                        } else {
                            const row = qtySpan.closest('.cart-item');
                            if (row) row.remove();
                        }
                    }
                    
                    // Update cart total in order summary
                    const totalElement = document.getElementById('total-with-shipping');
                    if (totalElement) {
                        totalElement.textContent = '₱' + Number(data.cart_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    }
                    
                    // Update subtotal in order summary
                    const subtotalElement = document.querySelector('.summary-row .fw-bold');
                    if (subtotalElement) {
                        subtotalElement.textContent = '₱' + Number(data.cart_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    }
                    
                    if (data.cart_qty === 0) {
                        location.reload();
                    }
                } else {
                    showAlert(data.message || 'An error occurred. Please try again.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
            })
            .finally(() => {
                setCartItemLoading(cartItem, false);
            });
        }

        function removeItem(productId) {
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'delete');
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
            setLoading(cartItem, true);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    const qtySpan = document.querySelector(`.cart-qty[data-product-id="${productId}"]`);
                    if (qtySpan) {
                        const row = qtySpan.closest('.cart-item');
                        if (row) row.remove();
                    }
                    
                    // Update cart total in order summary
                    const totalElement = document.getElementById('total-with-shipping');
                    if (totalElement) {
                        totalElement.textContent = '₱' + Number(data.cart_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    }
                    
                    // Update subtotal in order summary
                    const subtotalElement = document.querySelector('.summary-row .fw-bold');
                    if (subtotalElement) {
                        subtotalElement.textContent = '₱' + Number(data.cart_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                    }
                    
                    if (data.cart_qty === 0) {
                        location.reload();
                    }
                    
                    showAlert('Item removed from cart', 'success');
                } else {
                    showAlert(data.message || 'An error occurred. Please try again.', 'danger');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showAlert('An error occurred. Please try again.', 'danger');
            })
            .finally(() => {
                setCartItemLoading(cartItem, false);
            });
        }

        // Checkout validation
        const checkoutBtn = document.querySelector('.checkout-btn-action');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function(e) {
                let valid = true;
                let message = '';

                document.querySelectorAll('.cart-qty').forEach(span => {
                    const qty = parseInt(span.textContent.trim(), 10);
                    const stock = parseInt(span.getAttribute('data-stock'), 10);
                    const productId = span.getAttribute('data-product-id');

                    if (!isNaN(stock) && qty > stock) {
                        valid = false;
                        message += `Product ID ${productId}: Quantity (${qty}) exceeds stock (${stock})\n`;
                    }
                });

                if (!valid) {
                    showAlert("Some items in your cart exceed available stock and cannot be checked out.\n\n" + message, 'danger');
                    e.preventDefault();
                    return;
                }

                window.location.href = 'checkout.php';
            });
        }

        // Update cart badge in navbar
        function updateCartBadge() {
            fetch('cart_count.php')
                .then(response => response.text())
                .then(count => {
                    const badge = document.querySelector('.cart-badge');
                    if (badge) {
                        badge.textContent = count;
                        badge.style.display = count > 0 ? 'block' : 'none';
                    }
                });
        }

        // Update cart badge on page load
        updateCartBadge();
    </script>
</body>
</html>
