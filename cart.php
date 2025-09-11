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
                    COALESCE(pp.selling_price, 0) AS price,
                    COALESCE(ps.current_stock, 0) AS stock,
                    (SELECT pi.image_url FROM product_images pi 
                     WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                     ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                FROM products p
                LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
                LEFT JOIN product_stock ps ON ps.product_id = p.product_id
                WHERE p.product_id IN ($placeholders) AND p.is_archive = 0
                ORDER BY pp.productpricing_id DESC";
        
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
                if (isset($_SESSION['cart'][$product_id])) {
                    $_SESSION['cart'][$product_id]['quantity']--;
                    if ($_SESSION['cart'][$product_id]['quantity'] <= 0) {
                        unset($_SESSION['cart'][$product_id]);
                    }
                }
                break;

            case 'delete':
                unset($_SESSION['cart'][$product_id]);
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
            border-radius: 1.5rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.1);
            margin: 2rem 0;
            overflow: hidden;
            position: relative;
        }

        .cart-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--bs-secondary), #a91d42, var(--bs-secondary));
            background-size: 200% 100%;
            animation: gradientShift 3s ease-in-out infinite;
        }

        @keyframes gradientShift {
            0%, 100% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
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
            padding: 1.5rem;
            border-bottom: 1px solid #e9ecef;
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 1rem;
            margin-bottom: 1rem;
            gap: 1.5rem;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
            border: 1px solid #e9ecef;
        }

        .cart-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(127,23,52,0.03) 0%, rgba(169,29,66,0.03) 100%);
            opacity: 0;
            transition: all 0.3s ease;
            border-radius: 1rem;
        }

        .cart-item:hover::before {
            opacity: 1;
        }

        .cart-item:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.1);
            border-color: var(--bs-secondary);
        }

        .cart-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }

        .product-image {
            width: 100px;
            height: 100px;
            object-fit: cover;
            border-radius: 1rem;
            border: 3px solid #e9ecef;
            transition: all 0.3s ease;
            position: relative;
            z-index: 2;
        }

        .cart-item:hover .product-image {
            transform: scale(1.05);
            border-color: var(--bs-secondary);
            box-shadow: 0 8px 20px rgba(127,23,52,0.2);
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
            background: white;
            border: 2px solid #e9ecef;
            color: var(--bs-secondary);
            width: 45px;
            height: 45px;
            border-radius: 0.75rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            font-weight: 700;
            position: relative;
            overflow: hidden;
            z-index: 2;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .quantity-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: var(--bs-secondary);
            border-radius: 50%;
            transition: all 0.4s ease;
            transform: translate(-50%, -50%);
        }

        .quantity-btn:hover:not(:disabled)::before {
            width: 120%;
            height: 120%;
        }

        .quantity-btn:hover:not(:disabled) {
            color: white;
            border-color: var(--bs-secondary);
            transform: translateY(-2px) scale(1.05);
            box-shadow: 0 8px 25px rgba(127,23,52,0.3);
        }

        .quantity-btn:hover:not(:disabled) i {
            position: relative;
            z-index: 1;
        }

        .quantity-btn:disabled {
            opacity: 0.4;
            cursor: not-allowed;
            transform: none;
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
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            padding: 1.2rem 2.5rem;
            border-radius: 1rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1.1rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            box-shadow: 0 8px 30px rgba(127, 23, 52, 0.3);
            width: 100%;
            position: relative;
            overflow: hidden;
        }

        .checkout-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
            transition: all 0.6s ease;
        }

        .checkout-btn:hover:not(:disabled)::before {
            left: 100%;
        }

        .checkout-btn:hover:not(:disabled) {
            transform: translateY(-4px);
            box-shadow: 0 15px 40px rgba(127, 23, 52, 0.4);
        }

        .checkout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .checkout-btn:active:not(:disabled) {
            transform: translateY(-1px);
        }

        .empty-cart {
            text-align: center;
            padding: 5rem 2rem;
            color: #6c757d;
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            border-radius: 1.5rem;
            border: 2px dashed #dee2e6;
            margin: 2rem 0;
            position: relative;
            overflow: hidden;
        }

        .empty-cart::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="dots" width="20" height="20" patternUnits="userSpaceOnUse"><circle cx="10" cy="10" r="1" fill="%23dee2e6" opacity="0.3"/></pattern></defs><rect width="100" height="100" fill="url(%23dots)"/></svg>') repeat;
            opacity: 0.3;
        }

        .empty-cart > * {
            position: relative;
            z-index: 2;
        }

        .empty-cart i {
            font-size: 5rem;
            margin-bottom: 1.5rem;
            color: var(--bs-secondary);
            opacity: 0.4;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-10px); }
        }

        .empty-cart h3 {
            color: var(--bs-secondary);
            font-weight: 700;
            margin-bottom: 1rem;
            font-size: 2rem;
        }

        .empty-cart p {
            font-size: 1.1rem;
            margin-bottom: 2rem;
            opacity: 0.8;
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

        /* Enhanced Loading States */
        .loading-shimmer {
            background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
            background-size: 200% 100%;
            animation: shimmer 1.5s infinite;
        }

        @keyframes shimmer {
            0% { background-position: -200% 0; }
            100% { background-position: 200% 0; }
        }

        /* Enhanced Focus States */
        .quantity-btn:focus,
        .checkout-btn:focus,
        .continue-shopping:focus {
            outline: 3px solid rgba(127, 23, 52, 0.3) !important;
            outline-offset: 2px !important;
        }

        /* Enhanced Product Details */
        .product-details {
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }

        .cart-item:hover .product-details .product-name {
            color: var(--bs-secondary);
            transform: translateX(5px);
        }

        /* Enhanced Remove Button */
        .remove-btn {
            background: white;
            border: 2px solid #e9ecef;
            color: var(--bs-danger);
            padding: 0.75rem;
            border-radius: 0.75rem;
            transition: all 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            cursor: pointer;
            flex-shrink: 0;
            position: relative;
            overflow: hidden;
            z-index: 2;
        }

        .remove-btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: var(--bs-danger);
            border-radius: 50%;
            transition: all 0.4s ease;
            transform: translate(-50%, -50%);
        }

        .remove-btn:hover::before {
            width: 120%;
            height: 120%;
        }

        .remove-btn:hover {
            color: white;
            border-color: var(--bs-danger);
            transform: translateY(-2px) scale(1.1);
            box-shadow: 0 8px 25px rgba(220, 53, 69, 0.3);
        }

        .remove-btn:hover i {
            position: relative;
            z-index: 1;
        }

        /* Enhanced Order Summary */
        .order-summary {
            background: linear-gradient(135deg, #ffffff 0%, #f8f9fa 100%);
            border-radius: 1.5rem;
            padding: 2.5rem;
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            position: sticky;
            top: 2rem;
            border: 1px solid #e9ecef;
        }

        /* Reduced Motion Support */
        @media (prefers-reduced-motion: reduce) {
            * {
                animation-duration: 0.01ms !important;
                animation-iteration-count: 1 !important;
                transition-duration: 0.01ms !important;
            }

            .cart-container::before,
            .empty-cart i {
                animation: none;
            }

            .cart-item:hover,
            .quantity-btn:hover,
            .checkout-btn:hover {
                transform: none;
            }
        }

        @media (max-width: 768px) {
            .cart-item {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1.5rem 1rem;
            }

            .product-image {
                margin-right: 0;
                width: 80px;
                height: 80px;
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
                padding: 2rem 1.5rem;
            }

            .empty-cart {
                padding: 3rem 1rem;
            }

            .empty-cart i {
                font-size: 3rem;
            }

            .empty-cart h3 {
                font-size: 1.5rem;
            }
        }

        @media (max-width: 576px) {
            .checkout-btn {
                padding: 1rem 2rem;
                font-size: 1rem;
            }

            .quantity-btn {
                width: 40px;
                height: 40px;
            }
        }
    </style>