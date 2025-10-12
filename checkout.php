
<?php
session_start();
include 'includes/db.php';
include 'includes/cart_manager.php';
include 'includes/batch_manager.php';

// Prevent caching
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Check if user has verified ID
$stmt = $pdo->prepare("SELECT id_verified FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user_verification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verification || !$user_verification['id_verified']) {
    $_SESSION['verification_required'] = "Please verify your ID before placing an order.";
    header('Location: id_verification.php');
    exit;
}

// Check if user has a default address
$address_stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :user_id AND is_default = 1 LIMIT 1");
$address_stmt->execute(['user_id' => $user_id]);
$default_address = $address_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch all user addresses for selection
$all_addresses_stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, date_created DESC");
$all_addresses_stmt->execute(['user_id' => $user_id]);
$all_addresses = $all_addresses_stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($all_addresses)) {
    $_SESSION['address_required'] = "Please add a delivery address before placing an order. You'll be redirected to add your address.";
    $_SESSION['show_address_modal'] = true; // Flag to auto-open address modal
    header('Location: orders.php');
    exit;
}

// Fetch user profile info using normalized structure
$stmt = $pdo->prepare("SELECT u.user_id, u.username, ui.email, ui.first_name, ui.last_name, ui.phone 
                       FROM users u 
                       INNER JOIN user_info ui ON u.user_id = ui.user_id 
                       WHERE u.user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Default address already fetched above for validation

// Fetch the products in the cart with stock validation
$checkout_cart_items = [];
$total_price = 0;
$stock_errors = [];

// Use CartManager to load cart data from database (same as cart_total.php)
$cartManager = new CartManager($pdo);
$batchManager = new BatchManager($pdo);
$cart_data = $cartManager->loadCartFromDatabase($_SESSION['user_id']);

foreach ($cart_data as $cart_key => $cart_item) {
    // Extract product_id and brand_id from cart item
    $product_id = $cart_item['product_id'] ?? $cart_key;
    if (is_string($product_id) && strpos($product_id, '_') !== false) {
        $product_id = intval(explode('_', $product_id)[0]);
    }
    
    $brand_id = $cart_item['brand_id'] ?? null;
    if ($brand_id !== null) {
        $brand_id = intval($brand_id);
    }
    
    $quantity = floatval($cart_item['quantity'] ?? 1);
    $unit = $cart_item['unit'] ?? 'kilo';
    $batch_id = $cart_item['batch_id'] ?? null;
    
    // Use BatchManager to get proper stock availability
    $available_stock = $batchManager->getCombinedStock($product_id, $brand_id);
    
    // Simple pricing query: unit_cost + markup_price = final_price
    // First try brand-specific, then fallback to general pricing
    $price_sql = "SELECT 
        (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price,
        pb.batch_id,
        pb.brand_id,
        pb.quantity_remaining as brand_stock,
        b.name as brand_name,
        p.product_id,
        p.product_name,
        uom.name as uom_name,
        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as image1
    FROM products p
    LEFT JOIN product_batches pb ON p.product_id = pb.product_id 
        AND pb.brand_id = ?
        AND pb.quantity_remaining > 0 
        AND pb.is_active = 1
    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
    LEFT JOIN brands b ON pb.brand_id = b.id
    LEFT JOIN uom ON p.uom_id = uom.uom_id
    WHERE p.product_id = ? AND p.is_archive = 0
    ORDER BY pb.expiration_date ASC
    LIMIT 1";
    
    $stmt = $pdo->prepare($price_sql);
    $stmt->execute([$brand_id, $product_id]);
    $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // If no brand-specific batch found, try general pricing
    if (!$product_data || !$product_data['final_price'] || $product_data['final_price'] <= 0) {
        $general_sql = "SELECT 
            (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price,
            pb.batch_id,
            pb.brand_id,
            pb.quantity_remaining as brand_stock,
            b.name as brand_name,
            p.product_id,
            p.product_name,
            uom.name as uom_name,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as image1
        FROM products p
        LEFT JOIN product_batches pb ON p.product_id = pb.product_id 
            AND pb.quantity_remaining > 0 
            AND pb.is_active = 1
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        LEFT JOIN brands b ON pb.brand_id = b.id
        LEFT JOIN uom ON p.uom_id = uom.uom_id
        WHERE p.product_id = ? AND p.is_archive = 0
        ORDER BY pb.expiration_date ASC
        LIMIT 1";
        
        $stmt = $pdo->prepare($general_sql);
        $stmt->execute([$product_id]);
        $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    
    if ($product_data && $product_data['final_price'] > 0) {
        $final_price = floatval($product_data['final_price']);
        $current_stock = floatval($product_data['brand_stock']);
        
        // Check stock availability using BatchManager
        if ($available_stock < $quantity) {
            $stock_errors[] = "Insufficient stock for {$product_data['product_name']}. Available: {$available_stock}, Requested: {$quantity}";
        }
        
        // Build cart item with all necessary data
        $checkout_cart_items[] = [
            'product' => [
                'product_id' => $product_data['product_id'],
                'name' => $product_data['product_name'],
                'price' => $final_price,
                'uom_name' => $product_data['uom_name'],
                'current_stock' => $available_stock, // Use the proper available stock
                'image1' => $product_data['image1']
            ],
            'quantity' => $quantity,
            'unit' => $unit,
            'brand_id' => intval($product_data['brand_id']),
            'brand_name' => $product_data['brand_name'],
            'batch_id' => intval($product_data['batch_id']),
            'cart_key' => $cart_key
        ];
        
        $total_price += $final_price * $quantity;
    }
}

// If there are stock errors, redirect back to cart with error message
if (!empty($stock_errors)) {
    $_SESSION['stock_errors'] = $stock_errors;
    header('Location: cart.php');
    exit;
}

$discount = 0;
$discount_code = '';
$discount_error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['apply_discount'])) {
    $discount_code = strtoupper(trim($_POST['discount_code']));
    $stmt = $pdo->prepare("SELECT * FROM discount_codes WHERE code = ? AND is_active = 1 AND (expires_at IS NULL OR expires_at > NOW())");
    $stmt->execute([$discount_code]);
    $discount_row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($discount_row) {
        // Check if user has already used this discount code
        $usage_stmt = $pdo->prepare("SELECT 1 FROM discount_code_usage WHERE discount_code_id = ? AND user_id = ?");
        $usage_stmt->execute([$discount_row['id'], $user_id]);
        
        if ($usage_stmt->fetch()) {
            $discount_error = "You have already used this discount code.";
            unset($_SESSION['discount']);
            unset($_SESSION['discount_code']);
        } else {
            if ($discount_row['discount_type'] === 'percent') {
                $discount = $total_price * ($discount_row['discount_value'] / 100);
            } else {
                $discount = $discount_row['discount_value'];
            }
            $_SESSION['discount'] = $discount;
            $_SESSION['discount_code'] = $discount_code;
            $_SESSION['discount_code_id'] = $discount_row['id']; // Store discount code ID for later use
        }
    } else {
        $discount_error = "Invalid or expired discount code.";
        unset($_SESSION['discount']);
        unset($_SESSION['discount_code']);
        unset($_SESSION['discount_code_id']);
    }
} elseif (isset($_SESSION['discount'])) {
    $discount = $_SESSION['discount'];
    $discount_code = $_SESSION['discount_code'] ?? '';
}
$final_total = $total_price - $discount;
if ($final_total < 0) $final_total = 0;

// Always ensure user has a selected address_id, even if not submitting form
if (empty($_SESSION['selected_address_id']) && !empty($all_addresses)) {
    // Auto-select the first available address if none is selected
    $_SESSION['selected_address_id'] = $all_addresses[0]['address_id'];
    error_log("Checkout: Auto-selected first available address_id: " . $all_addresses[0]['address_id']);
}

// Address processing is now handled in place_order.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>Checkout - MikeMadz</title>
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

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }


        .checkout-container {
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            margin: 2rem 0;
            overflow: hidden;
        }

        .checkout-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .checkout-header h1 {
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

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
        }

        .form-control[readonly] {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
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
            padding-left: 1rem;
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

        /* Order grid styles */
        .order-header, .order-row {
            display: grid;
            grid-template-columns: 80px 1fr 120px 120px 140px; /* image, name, qty, unit, subtotal */
            gap: 1rem;
            align-items: center;
        }
        .order-header {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 1rem;
            font-weight: 600;
            color: #212529;
            margin-bottom: 0.75rem;
        }
        .order-row {
            padding: 1rem;
            border-bottom: 1px solid #e9ecef;
            background: #ffffff;
            border-radius: 0.5rem;
        }
        .order-row:last-child { border-bottom: none; }
        .order-cell--image { display: flex; align-items: center; }
        .order-col--sub { color: var(--bs-danger); font-weight: 700; }

        @media (max-width: 768px) {
            .order-header { display: none; }
            .order-row { grid-template-columns: 80px 1fr; row-gap: 0.25rem; }
            .order-row .od-qty, .order-row .od-unit, .order-row .od-sub { display: flex; gap: 0.5rem; font-size: 0.9rem; color: #6c757d; }
            .order-row .od-sub { color: var(--bs-danger); font-weight: 700; }
        }

        .order-summary {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
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
            justify-content: between;
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

        .discount-form {
            background: #f8f9fa;
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #6d1429 0%, #8f1937 100%);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
        }

        .btn-outline-secondary {
            border-color: var(--bs-secondary);
            color: var(--bs-secondary);
            border-width: 2px;
            font-weight: 600;
        }

        .btn-outline-secondary:hover {
            background-color: var(--bs-secondary);
            border-color: var(--bs-secondary);
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-option:hover {
            border-color: var(--bs-secondary);
            background: rgba(127, 23, 52, 0.05);
        }

        .radio-option input[type="radio"] {
            margin-right: 0.75rem;
            accent-color: var(--bs-secondary);
        }

        .gcash-section {
            background: #f8f9fa;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 1rem;
            text-align: center;
        }

        .gcash-qr {
            max-width: 300px;
            border-radius: 0.75rem;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            margin: 1rem auto;
        }

        /* GCash Transaction ID Styling */
        .gcash-section input[name="gcash_transaction_id"] {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .gcash-section .form-text {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .gcash-section .form-text i {
            color: var(--bs-info);
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

        .place-order-btn {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            padding: 1rem 2rem;
            border-radius: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
            color: #ffffff;
        }

        .place-order-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.4);
            color: #ffffff;
        }

        /* Delivery Address Styles */
        #delivery-address-section {
            background: #f8f9fa;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        #new-address-form {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .address-option-disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .address-option-disabled input[type="radio"] {
            cursor: not-allowed;
        }

        /* Saved Address Styles */
        .saved-addresses {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 1rem;
            background: #f8f9fa;
        }

        .address-option {
            margin-bottom: 0.75rem;
        }

        .address-option:last-child {
            margin-bottom: 0;
        }

        .address-badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }

        /* LocationIQ Address Autocomplete Styles */
        .address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .address-suggestion {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-suggestion:hover,
        .address-suggestion.active {
            background-color: #f8f9fa;
        }

        .address-suggestion.active {
            background-color: rgba(127, 23, 52, 0.1);
            border-left: 3px solid var(--bs-secondary);
        }

        .address-suggestion:last-child {
            border-bottom: none;
        }

        .address-suggestion i {
            color: var(--bs-secondary);
            font-size: 0.9rem;
        }

        .address-suggestion .address-text {
            flex: 1;
        }

        .address-suggestion .address-main {
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.25rem;
        }

        .address-suggestion .address-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .address-loading {
            padding: 1rem;
            text-align: center;
            color: #6c757d;
        }

        .address-loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .checkout-header h1 {
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
<?php include 'includes/user_promo.php'; ?>

    <?php include 'includes/user_navbar.php'; ?>

    <div class="container my-5">
        <?php if (isset($_SESSION['upload_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['upload_error']; unset($_SESSION['upload_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($checkout_cart_items)): ?>
            <div class="checkout-container">
                <div class="empty-cart">
                    <i class="fas fa-shopping-cart"></i>
                    <h3>Your cart is empty</h3>
                    <p class="mb-4">Add some products to your cart before checking out.</p>
                    <a href="product.php" class="btn btn-primary">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="checkout-container">
                <div class="checkout-header">
                    <h1><i class="fas fa-credit-card me-3"></i>Checkout</h1>
                </div>

                <div class="row p-4">
                    <!-- Main Checkout Form -->
                    <div class="col-lg-8">
                        <!-- Cart Items Review -->
                        <div class="form-section">
                            <h3 class="section-title">
                                <i class="fas fa-shopping-cart"></i>
                                Your Order
                            </h3>

                        
                            <div class="order-header">
                                <div></div>
                                <div>Product Name</div>
                                <div>Quantity</div>
                                <div>Item Price</div>
                                <div>Subtotal</div>
                            </div>

                            <?php foreach ($checkout_cart_items as $item): ?>
                                <?php 
                                    $displayName = $item['product']['name'] ?? 'Item';
                                    
                                    // Add brand name to display if available
                                    if (!empty($item['brand_name'])) {
                                        $displayName .= ' - ' . $item['brand_name'];
                                    }
                                    
                                    $unitPrice = $item['product']['price'] ?? 0; // Always use fresh database price
                                    $qty = $item['quantity'] ?? 0;
                                    $unit = $item['unit'] ?? 'kilo';
                                    $uomName = $item['product']['uom_name'] ?? '';
                                    $boxId = $item['box_id'] ?? null;
                                    $weight = $item['weight'] ?? null;
                                    $lineSubtotal = $unitPrice * $qty;
                                    
                                    
                                    // Debug: Check image field
                                    $imageField = $item['product']['image1'] ?? '';
                                   
                                    
                                    $imgSrc = !empty($imageField) ? 'admin/' . htmlspecialchars($imageField) : 'admin/uploads/placeholder.jpg';
                                    
                                    // Format display name with unit info
                                    if ($unit === 'piece') {
                                        $displayName .= ' (per piece)';
                                    } else if ($unit === 'box' && $weight) {
                                        $displayName .= ' (Box - ' . number_format($weight, 2) . 'kg)';
                                    }
                                    // Removed the else clause that added "(per kilo)" text
                                ?>
                                <div class="order-row">
                                    <div class="order-cell--image">
                                        <img src="<?= $imgSrc ?>" alt="<?= htmlspecialchars($displayName) ?>" class="product-image">
                                    </div>
                                    <div class="fw-semibold text-dark">
                                        <?= htmlspecialchars($displayName) ?>
                                        <?php if (isset($item['current_stock'])): ?>
                                            <div class="small text-muted">
                                                Stock: <span class="fw-bold <?= $item['current_stock'] > 10 ? 'text-success' : ($item['current_stock'] > 0 ? 'text-warning' : 'text-danger') ?>">
                                                    <?= number_format($item['current_stock'], 1) ?> <?= htmlspecialchars($uomName ?: $unit) ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="od-qty">
                                        <span class="d-none d-md-inline"> </span><?= number_format($qty, 1) ?> <?= htmlspecialchars($uomName ?: $unit) ?>
                                    </div>
                                    <div class="od-unit">
                                        <span class="d-none d-md-inline">₱</span><?= number_format($unitPrice, 2) ?>
                                    </div>
                                    <div class="od-sub order-col--sub">
                                        ₱<?= number_format($lineSubtotal, 2) ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <form action="place_order.php" method="post" enctype="multipart/form-data">

                            <!-- Delivery Options -->
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-truck"></i>
                                    Delivery Option
                                </h3>
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="radio-option">
                                            <input type="radio" name="delivery_option" value="pickup" required>
                                            <div>
                                                <strong>For Pickup (Free)</strong>
                                                <div class="text-muted small">Pick up your order at our store</div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="radio-option">
                                            <input type="radio" name="delivery_option" value="delivery" required>
                                            <div>
                                                <strong>For Delivery</strong>
                                                <div class="text-muted small">We'll deliver to your address</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Delivery Address Selection -->
                            <div class="form-section" id="delivery-address-section" style="display: none;">
                                <h3 class="section-title">
                                    <i class="fas fa-map-marker-alt"></i>
                                    Delivery Address
                                </h3>
                                
                                <!-- Address Selection -->
                                <div class="mb-3">
                                    <label class="form-label fw-bold">Choose Delivery Address</label>
                                    
                                    <?php if (!empty($all_addresses)): ?>
                                        <!-- Saved Addresses -->
                                        <div class="saved-addresses mb-3">
                                            <h6 class="text-muted mb-3">
                                                <i class="fas fa-bookmark me-2"></i>Saved Addresses
                                            </h6>
                                            <?php foreach ($all_addresses as $address): ?>
                                                <div class="address-option">
                                            <label class="radio-option">
                                                        <input type="radio" name="address_option" value="saved_<?= $address['address_id'] ?>" data-address-id="<?= $address['address_id'] ?>">
                                                <div>
                                                            <div class="d-flex justify-content-between align-items-start">
                                                                <div>
                                                                    <strong>
                                                                        <?= $address['is_default'] ? 'Default Address' : 'Address' ?>
                                                                        <?= $address['is_default'] ? '<span class="badge bg-success address-badge ms-2">Default</span>' : '' ?>
                                                                    </strong>
                                                                    <div class="text-muted small mt-1">
                                                            <strong><?= htmlspecialchars($user['first_name'] ?? '') ?> <?= htmlspecialchars($user['last_name'] ?? '') ?></strong><br>
                                                            <?= htmlspecialchars($user['phone'] ?? '') ?><br>
                                                                        <?= htmlspecialchars($address['address_line']) ?>
                                                                        <?php if ($address['address_line2']): ?>
                                                                            <br><?= htmlspecialchars($address['address_line2']) ?>
                                                                        <?php endif; ?>
                                                                        <br><?= htmlspecialchars($address['city']) ?>
                                                                        <?php if ($address['state']): ?>
                                                                            , <?= htmlspecialchars($address['state']) ?>
                                                                        <?php endif; ?>
                                                                        <?php if ($address['postal_code']): ?>
                                                                            <?= htmlspecialchars($address['postal_code']) ?>
                                                        <?php endif; ?>
                                                                    </div>
                                                                </div>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- New Address Option -->
                                    <div class="mb-2">
                                            <label class="radio-option">
                                                <input type="radio" name="address_option" value="new" id="new-address">
                                                <div>
                                                    <strong>Use Different Address</strong>
                                                    <div class="text-muted small">Enter a new delivery address</div>
                                                </div>
                                            </label>
                                    </div>
                                </div>

                                <!-- New Address Form -->
                                <div id="new-address-form" style="display: none;">
                                    <h5 class="mb-3 text-primary">
                                        <i class="fas fa-user me-2"></i>Customer Information
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">First Name *</label>
                                            <input type="text" name="firstname" class="form-control" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Last Name *</label>
                                            <input type="text" name="lastname" class="form-control" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Email *</label>
                                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Phone Number *</label>
                                            <input type="text" name="phone" class="form-control" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" required>
                                        </div>
                                    </div>
                                    
                                    <h5 class="mb-3 text-primary mt-4">
                                        <i class="fas fa-map-marker-alt me-2"></i>Delivery Address
                                    </h5>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Full Address *</label>
                                            <div class="position-relative">
                                                <input type="text" name="delivery_address" id="delivery_address" class="form-control" placeholder="Start typing your address..." autocomplete="off" required>
                                                <div id="address-suggestions" class="address-suggestions"></div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">City *</label>
                                            <input type="text" name="delivery_city" class="form-control" placeholder="Enter city" required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Postal Code *</label>
                                            <input type="text" name="delivery_postal_code" class="form-control" placeholder="Enter postal code" required>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Special Instructions (Optional)</label>
                                            <textarea name="delivery_instructions" class="form-control" rows="3" placeholder="Any special delivery instructionsor or Landmark"></textarea>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Payment Method -->
                            <div class="form-section">
                                <h3 class="section-title">
                                    <i class="fas fa-credit-card"></i>
                                    Payment Method
                                </h3>
                                
                                <?php if ($final_total > 2000): ?>
                                    <!-- High Value Order Notice -->
                                    <div class="alert alert-info mb-3">
                                        <i class="fas fa-info-circle me-2"></i>
                                        <strong>High Value Order Notice:</strong> Orders over ₱2,000 require GCash payment for security purposes. Cash on Delivery is not available for this order.
                                    </div>
                                <?php endif; ?>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="radio-option <?= $final_total > 2000 ? 'address-option-disabled' : '' ?>">
                                            <input type="radio" name="payment_method" value="Cash" id="cash-payment" 
                                                   <?= $final_total > 2000 ? 'disabled' : '' ?> 
                                                   <?= $final_total <= 2000 ? 'required' : '' ?>>
                                            <div>
                                                <strong>Cash Payment</strong>
                                                <div class="text-muted small">
                                                    <?php if ($final_total > 2000): ?>
                                                        <span class="text-danger">Not available for orders over ₱2,000</span>
                                                    <?php else: ?>
                                                        Pay with cash on pickup/delivery
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </label>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="radio-option">
                                            <input type="radio" name="payment_method" value="GCash" id="gcash-payment" required>
                                            <div>
                                                <strong>GCash Payment</strong>
                                                <div class="text-muted small">Pay instantly via GCash</div>
                                            </div>
                                        </label>
                                    </div>
                                </div>

                                <!-- GCash Upload Section -->
                                <div id="gcash-upload" class="gcash-section" style="display: none;">
                                    <h5 class="text-primary mb-3">
                                        <i class="fas fa-mobile-alt me-2"></i>Scan to Pay via GCash
                                    </h5>
                                    <img src="assets/gcashqr/gcash_qr.jpg" alt="GCash QR Code" class="img-fluid gcash-qr">
                                    <p class="text-muted mb-3">Scan the QR code above and complete your payment</p>
                                    
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold">GCash Reference Number *</label>
                                            <input type="text" name="gcash_transaction_id" class="form-control" 
                                                   placeholder="Enter your GCash Reference Number (13 digits)" 
                                                   pattern="[0-9]{13}" 
                                                   maxlength="13"
                                                   minlength="13"
                                                   title="Please enter exactly 13 digits for your GCash Reference Number">
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Enter exactly 13 digits from your GCash Reference Number
                                            </small>
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Upload Payment Proof *</label>
                                            <input type="file" name="payment_proof" class="form-control" accept="image/*">
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Upload a screenshot of your GCash payment confirmation
                                            </small>
                                    </div>
                                </div>
                            </div>

                                <!-- Hidden fields to ensure they're always submitted -->
                                <input type="hidden" name="gcash_transaction_id_hidden" id="gcash_transaction_id_hidden" value="">
                                <input type="hidden" name="payment_proof_hidden" id="payment_proof_hidden" value="">
                            </div>

                            <button type="button" class="btn place-order-btn w-100" onclick="confirmPlaceOrder(event)">
                                <i class="fas fa-lock me-2"></i>Place Order Securely
                            </button>
                        </form>
                    </div>

                    <!-- Order Summary Sidebar -->
                    <div class="col-lg-4">
                        <div class="order-summary">
                            <h3 class="summary-title">
                                <i class="fas fa-receipt me-2"></i>Order Summary
                            </h3>

                            <!-- Discount Code Form -->
                            <form method="post" class="discount-form">
                                <label class="form-label fw-bold">Discount Code</label>
                                <div class="input-group">
                                    <input type="text" name="discount_code" class="form-control" placeholder="Enter discount code" value="<?= htmlspecialchars($discount_code) ?>">
                                    <button type="submit" name="apply_discount" class="btn btn-outline-secondary">Apply</button>
                                </div>
                                <?php if ($discount_error): ?>
                                    <div class="text-danger small mt-2">
                                        <i class="fas fa-exclamation-circle me-1"></i><?= htmlspecialchars($discount_error) ?>
                                    </div>
                                <?php elseif ($discount > 0): ?>
                                    <div class="text-success small mt-2">
                                        <i class="fas fa-check-circle me-1"></i>Discount applied: <strong><?= htmlspecialchars($discount_code) ?></strong>
                                    </div>
                                <?php endif; ?>
                            </form>

                            <!-- Items Breakdown -->
                            <div class="mb-3">
                                <div class="small fw-bold text-muted mb-2">Items</div>
                                <?php foreach ($checkout_cart_items as $item): ?>
                                    <?php 
                                        $displayName = $item['product']['name'] ?? 'Item';
                                        
                                        // Add brand name to display if available
                                        if (!empty($item['brand_name'])) {
                                            $displayName .= ' - ' . $item['brand_name'];
                                        }
                                        
                                        $unitPrice = $item['product']['price'] ?? 0; // Always use fresh database price
                                        $qty = $item['quantity'] ?? 0;
                                        $unit = $item['unit'] ?? 'kilo';
                                        $uomName = $item['product']['uom_name'] ?? '';
                                        $boxId = $item['box_id'] ?? null;
                                        $weight = $item['weight'] ?? null;
                                        $lineSubtotal = $unitPrice * $qty;
                                        
                                        
                                        // Format display name with unit info
                                        if ($unit === 'piece') {
                                            $displayName .= ' (per piece)';
                                        } else if ($unit === 'box' && $weight) {
                                            $displayName .= ' (Box - ' . number_format($weight, 2) . 'kg)';
                                        }
                                        // Removed the else clause that added "(per kilo)" text
                                    ?>
                                    <div class="d-flex justify-content-between align-items-center small py-1 border-bottom">
                                        <span><?= htmlspecialchars($displayName) ?> × <?= number_format($qty, 1) ?> <?= htmlspecialchars($uomName ?: $unit) ?></span>
                                        <span>
                                            ₱<?= number_format($unitPrice, 2) ?>
                                            <span class="text-muted">|</span>
                                            <strong>₱<?= number_format($lineSubtotal, 2) ?></strong>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>

                            <div class="summary-row">
                                <span>Subtotal:</span>
                                <span class="fw-bold">₱<?= number_format($total_price, 2) ?></span>
                            </div>

                            <?php if ($discount > 0): ?>
                                <div class="summary-row text-success">
                                    <span>Discount:</span>
                                    <span class="fw-bold">-₱<?= number_format($discount, 2) ?></span>
                                </div>
                            <?php endif; ?>


                            <div class="summary-row">
                                <span>Total:</span>
                                <span id="total-with-shipping">₱<?= number_format($final_total, 2) ?></span>
                            </div>

                            <input type="hidden" id="base-total" value="<?= $final_total ?>">
                            <input type="hidden" name="shipping_fee" id="shipping-fee" value="0">
                            <input type="hidden" name="total_price" id="final-total" value="<?= $final_total ?>">

                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle GCash upload section
        const cashPayment = document.getElementById('cash-payment');
        const gcashPayment = document.getElementById('gcash-payment');
        const gcashUpload = document.getElementById('gcash-upload');
        
        // Check if order total is over ₱2,000
        const baseTotal = parseFloat(document.getElementById('base-total').value);
        const isHighValueOrder = baseTotal > 2000;
        
        if (cashPayment && gcashPayment && gcashUpload) {
            // Auto-select GCash for high value orders
            if (isHighValueOrder) {
                gcashPayment.checked = true;
                gcashUpload.style.display = 'block';
                const fileInput = gcashUpload.querySelector('input[type="file"]');
                const transactionIdInput = gcashUpload.querySelector('input[name="gcash_transaction_id"]');
                if (fileInput) fileInput.setAttribute('required', 'required');
                // Don't set required on transaction ID to avoid form validation issues
            }
            
            cashPayment.addEventListener('change', function() {
                if (this.checked && !this.disabled) {
                    gcashUpload.style.display = 'none';
                    const fileInput = gcashUpload.querySelector('input[type="file"]');
                    const transactionIdInput = gcashUpload.querySelector('input[name="gcash_transaction_id"]');
                    if (fileInput) fileInput.removeAttribute('required');
                    // Transaction ID field doesn't need required attribute management
                }
            });
            
            gcashPayment.addEventListener('change', function() {
                if (this.checked) {
                    gcashUpload.style.display = 'block';
                    const fileInput = gcashUpload.querySelector('input[type="file"]');
                    const transactionIdInput = gcashUpload.querySelector('input[name="gcash_transaction_id"]');
                    if (fileInput) fileInput.setAttribute('required', 'required');
                    // Don't set required on transaction ID to avoid form validation issues
                }
            });
        }

        // Toggle delivery address section
        const deliveryOption = document.querySelectorAll('input[name="delivery_option"]');
        const deliveryAddressSection = document.getElementById('delivery-address-section');
        const addressOption = document.querySelectorAll('input[name="address_option"]');
        const newAddressForm = document.getElementById('new-address-form');
        
        // Show/hide delivery address section based on delivery option
        deliveryOption.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'delivery') {
                    deliveryAddressSection.style.display = 'block';
                    // Auto-select the first saved address (default) if available
                    const firstSavedAddress = document.querySelector('input[name="address_option"][value^="saved_"]');
                    const newAddressRadio = document.getElementById('new-address');
                    
                    <?php if (!empty($all_addresses)): ?>
                        if (firstSavedAddress) {
                            firstSavedAddress.checked = true;
                        }
                    <?php else: ?>
                        if (newAddressRadio) {
                            newAddressRadio.checked = true;
                            newAddressForm.style.display = 'block';
                            // Make new address fields required
                            const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"]');
                            requiredFields.forEach(field => {
                                field.setAttribute('required', 'required');
                            });
                        }
                    <?php endif; ?>
                } else {
                    deliveryAddressSection.style.display = 'none';
                    // Remove required attributes from all delivery address fields when pickup is selected
                    const deliveryFields = deliveryAddressSection.querySelectorAll('input[type="text"], input[type="email"], textarea');
                    deliveryFields.forEach(field => {
                        field.removeAttribute('required');
                    });
                }
            });
        });

        // Show/hide new address form based on address option
        addressOption.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'new') {
                    newAddressForm.style.display = 'block';
                    // Ensure all required fields are marked as required
                    const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"], textarea');
                    requiredFields.forEach(field => {
                        if (field.name === 'delivery_address' || field.name === 'delivery_city' || field.name === 'delivery_postal_code') {
                            field.setAttribute('required', 'required');
                        }
                    });
                } else {
                    newAddressForm.style.display = 'none';
                    // Remove required attribute from new address fields when not selected
                    const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"], textarea');
                    requiredFields.forEach(field => {
                        field.removeAttribute('required');
                    });
                }
            });
        });

        // Allow editing of readonly fields on double-click
        document.querySelectorAll('input[readonly]').forEach(input => {
            input.addEventListener('dblclick', function() {
                this.removeAttribute('readonly');
                this.classList.remove('form-control');
                this.classList.add('form-control');
                this.style.backgroundColor = '#fff';
                this.focus();
            });
        });

        // Delivery option calculation (shipping is always free)
        document.querySelectorAll('input[name="delivery_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const baseTotal = parseFloat(document.getElementById('base-total').value);
                const shippingFeeElement = document.getElementById('shipping-fee');
                const finalTotalElement = document.getElementById('final-total');
                const totalDisplay = document.getElementById('total-with-shipping');
                
                let shippingFee = 0; // Always free shipping
                
                const finalTotal = baseTotal + shippingFee;
                
                shippingFeeElement.value = shippingFee;
                finalTotalElement.value = finalTotal;
                totalDisplay.textContent = '₱' + finalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                
                // Check if total is over ₱2,000 and handle payment method
                updatePaymentMethodAvailability(finalTotal);
            });
        });
        
        // Function to update payment method availability based on total
        function updatePaymentMethodAvailability(total) {
            const cashPayment = document.getElementById('cash-payment');
            const gcashPayment = document.getElementById('gcash-payment');
            const gcashUpload = document.getElementById('gcash-upload');
            const cashLabel = cashPayment.closest('label');
            
            if (total > 2000) {
                // Disable cash payment for high value orders
                cashPayment.disabled = true;
                cashPayment.checked = false;
                cashLabel.classList.add('address-option-disabled');
                
                // Auto-select GCash
                gcashPayment.checked = true;
                gcashUpload.style.display = 'block';
                const fileInput = gcashUpload.querySelector('input[type="file"]');
                const transactionIdInput = gcashUpload.querySelector('input[name="gcash_transaction_id"]');
                if (fileInput) fileInput.setAttribute('required', 'required');
                // Don't set required on transaction ID to avoid form validation issues
                
                // Show notice if not already shown
                if (!document.querySelector('.alert-info')) {
                    const notice = document.createElement('div');
                    notice.className = 'alert alert-info mb-3';
                    notice.innerHTML = '<i class="fas fa-info-circle me-2"></i><strong>High Value Order Notice:</strong> Orders over ₱2,000 require GCash payment for security purposes. Cash on Delivery is not available for this order.';
                    cashLabel.closest('.form-section').insertBefore(notice, cashLabel.closest('.row'));
                }
                    } else {
                // Enable cash payment for low value orders
                cashPayment.disabled = false;
                cashLabel.classList.remove('address-option-disabled');
                
                // Remove notice if exists
                const existingNotice = document.querySelector('.alert-info');
                if (existingNotice) {
                    existingNotice.remove();
                }
            }
        }

        // LocationIQ Address Autocomplete Implementation
        const LOCATIONIQ_API_KEY = 'pk.00c9590567d539faf9a471a17f1c5bf3';
        const LOCATIONIQ_BASE_URL = 'https://us1.locationiq.com/v1';
        
        let addressTimeout;
        let selectedAddress = null;
        
        // Initialize address autocomplete
        function initializeAddressAutocomplete() {
            const addressInput = document.getElementById('delivery_address');
            const suggestionsContainer = document.getElementById('address-suggestions');
            
            if (!addressInput || !suggestionsContainer) return;
            
            // Handle input events
            addressInput.addEventListener('input', function() {
                const query = this.value.trim();
                
                // Clear previous timeout
                clearTimeout(addressTimeout);
                
                // Hide suggestions if query is too short
                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }
                
                // Debounce the API call
                addressTimeout = setTimeout(() => {
                    searchAddresses(query);
                }, 300);
            });
            
            // Handle keyboard navigation
            addressInput.addEventListener('keydown', function(e) {
                const suggestions = suggestionsContainer.querySelectorAll('.address-suggestion');
                const activeSuggestion = suggestionsContainer.querySelector('.address-suggestion.active');
                
                if (e.key === 'ArrowDown') {
                    e.preventDefault();
                    if (activeSuggestion) {
                        activeSuggestion.classList.remove('active');
                        const next = activeSuggestion.nextElementSibling;
                        if (next) {
                            next.classList.add('active');
                    } else {
                            suggestions[0]?.classList.add('active');
                        }
                    } else {
                        suggestions[0]?.classList.add('active');
                    }
                } else if (e.key === 'ArrowUp') {
                    e.preventDefault();
                    if (activeSuggestion) {
                        activeSuggestion.classList.remove('active');
                        const prev = activeSuggestion.previousElementSibling;
                        if (prev) {
                            prev.classList.add('active');
                        } else {
                            suggestions[suggestions.length - 1]?.classList.add('active');
                        }
                    }
                } else if (e.key === 'Enter') {
                    e.preventDefault();
                    if (activeSuggestion) {
                        selectAddress(activeSuggestion);
                    }
                } else if (e.key === 'Escape') {
                    hideSuggestions();
                }
            });
            
            // Hide suggestions when clicking outside
            document.addEventListener('click', function(e) {
                if (!addressInput.contains(e.target) && !suggestionsContainer.contains(e.target)) {
                    hideSuggestions();
                }
            });
        }
        
        // Search addresses using LocationIQ API
        async function searchAddresses(query) {
            const suggestionsContainer = document.getElementById('address-suggestions');
            
            // Show loading state
            suggestionsContainer.innerHTML = `
                <div class="address-loading">
                    <i class="fas fa-spinner"></i>
                    <span>Searching addresses...</span>
                </div>
            `;
            suggestionsContainer.style.display = 'block';
            
            try {
                // Use LocationIQ autocomplete API
                const response = await fetch(
                    `${LOCATIONIQ_BASE_URL}/autocomplete?key=${LOCATIONIQ_API_KEY}&q=${encodeURIComponent(query)}&countrycodes=ph&limit=5&addressdetails=1`
                );
                
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const data = await response.json();
                displaySuggestions(data);
                
            } catch (error) {
                console.error('Error searching addresses:', error);
                suggestionsContainer.innerHTML = `
                    <div class="address-suggestion">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="address-text">
                            <div class="address-main">Error loading addresses</div>
                            <div class="address-details">Please try again or enter manually</div>
                        </div>
                    </div>
                `;
            }
        }
        
        // Display address suggestions
        function displaySuggestions(addresses) {
            const suggestionsContainer = document.getElementById('address-suggestions');
            
            if (!addresses || addresses.length === 0) {
                suggestionsContainer.innerHTML = `
                    <div class="address-suggestion">
                        <i class="fas fa-search"></i>
                        <div class="address-text">
                            <div class="address-main">No addresses found</div>
                            <div class="address-details">Try a different search term</div>
                        </div>
                    </div>
                `;
                return;
            }
            
            const suggestionsHTML = addresses.map(address => {
                const displayName = address.display_name || '';
                const mainAddress = address.address?.house_number ? 
                    `${address.address.house_number} ${address.address.road || ''}`.trim() : 
                    address.address?.road || displayName.split(',')[0];
                
                const details = [
                    address.address?.suburb,
                    address.address?.city,
                    address.address?.state,
                    address.address?.postcode
                ].filter(Boolean).join(', ');
                
                return `
                    <div class="address-suggestion" data-address='${JSON.stringify(address)}'>
                        <i class="fas fa-map-marker-alt"></i>
                        <div class="address-text">
                            <div class="address-main">${mainAddress}</div>
                            <div class="address-details">${details}</div>
                        </div>
                    </div>
                `;
            }).join('');
            
            suggestionsContainer.innerHTML = suggestionsHTML;
            
            // Add click event listeners to suggestions
            suggestionsContainer.querySelectorAll('.address-suggestion').forEach(suggestion => {
                suggestion.addEventListener('click', function() {
                    selectAddress(this);
                });
                
                suggestion.addEventListener('mouseenter', function() {
                    // Remove active class from all suggestions
                    suggestionsContainer.querySelectorAll('.address-suggestion').forEach(s => s.classList.remove('active'));
                    // Add active class to current suggestion
                    this.classList.add('active');
                });
            });
        }
        
        // Select an address from suggestions
        function selectAddress(suggestionElement) {
            const addressData = JSON.parse(suggestionElement.dataset.address);
            const addressInput = document.getElementById('delivery_address');
            const cityInput = document.querySelector('input[name="delivery_city"]');
            const postalCodeInput = document.querySelector('input[name="delivery_postal_code"]');
            
            // Set the full address
            addressInput.value = addressData.display_name || '';
            
            // Auto-fill city and postal code if available
            if (addressData.address) {
                // Try different city fields in order of preference
                if (cityInput) {
                    if (addressData.address.city) {
                        cityInput.value = addressData.address.city;
                    } else if (addressData.address.town) {
                        cityInput.value = addressData.address.town;
                    } else if (addressData.address.village) {
                        cityInput.value = addressData.address.village;
                    } else if (addressData.address.municipality) {
                        cityInput.value = addressData.address.municipality;
                    } else if (addressData.address.county) {
                        cityInput.value = addressData.address.county;
                    } else {
                        // Fallback: parse from display_name
                        const parts = addressData.display_name.split(', ');
                        for (let i = 1; i < parts.length; i++) {
                            const part = parts[i].trim();
                            // Skip common non-city terms
                            if (!part.match(/^(Philippines|Metro Manila|NCR|Region|Province|Quezon City|Manila|Makati|Taguig|Pasig|Mandaluyong|San Juan|Marikina|Parañaque|Las Piñas|Muntinlupa|Caloocan|Malabon|Navotas|Valenzuela|Pateros)$/i)) {
                                cityInput.value = part;
                                break;
                            }
                        }
                    }
                }
                
                if (postalCodeInput && addressData.address.postcode) {
                    postalCodeInput.value = addressData.address.postcode;
                }
            }
            
            // Store selected address data
            selectedAddress = addressData;
            
            // Hide suggestions
            hideSuggestions();
            
            // Focus on next field
            if (cityInput && !cityInput.value) {
                cityInput.focus();
            } else if (postalCodeInput && !postalCodeInput.value) {
                postalCodeInput.focus();
            }
        }
        
        // Hide address suggestions
        function hideSuggestions() {
            const suggestionsContainer = document.getElementById('address-suggestions');
            if (suggestionsContainer) {
                suggestionsContainer.style.display = 'none';
                suggestionsContainer.innerHTML = '';
            }
        }
        
        // Confirm order placement with SweetAlert2
        function confirmPlaceOrder(event) {
            if (event) event.preventDefault();
            
            const form = document.querySelector('form[action="place_order.php"]');
            
            // Validate required fields before showing confirmation
            const paymentMethod = document.querySelector('input[name="payment_method"]:checked');
            const deliveryOption = document.querySelector('input[name="delivery_option"]:checked');
            
            if (!deliveryOption) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please select a delivery option (Pickup or Delivery)',
                    confirmButtonColor: '#7F1734'
                });
                return;
            }
            
            if (!paymentMethod) {
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'Please select a payment method',
                    confirmButtonColor: '#7F1734'
                });
                return;
            }
            
            // If delivery is selected, check if address is selected
            if (deliveryOption.value === 'delivery') {
                const addressOption = document.querySelector('input[name="address_option"]:checked');
                if (!addressOption) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please select or enter a delivery address',
                        confirmButtonColor: '#7F1734'
                    });
                    return;
                }
                
                // If new address selected, validate the fields
                if (addressOption.value === 'new') {
                    const deliveryAddress = document.querySelector('input[name="delivery_address"]');
                    const deliveryCity = document.querySelector('input[name="delivery_city"]');
                    const deliveryPostal = document.querySelector('input[name="delivery_postal_code"]');
                    
                    if (!deliveryAddress.value || !deliveryCity.value || !deliveryPostal.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Missing Information',
                            text: 'Please fill in all required address fields',
                            confirmButtonColor: '#7F1734'
                        });
                        return;
                    }
                }
            }
            
            // If GCash is selected, validate payment proof
            if (paymentMethod.value === 'GCash') {
                const paymentProofField = document.querySelector('input[name="payment_proof"]');
                const transactionIdField = document.querySelector('input[name="gcash_transaction_id"]');
                
                if (!paymentProofField.files || paymentProofField.files.length === 0) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please upload payment proof for GCash transactions',
                        confirmButtonColor: '#7F1734'
                    });
                    return;
                }
                
                if (!transactionIdField.value || transactionIdField.value.length !== 13) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please enter a valid 13-digit GCash transaction ID',
                        confirmButtonColor: '#7F1734'
                    });
                    return;
                }
            }
            
            // Get the actual total from the hidden input
            const totalPrice = document.getElementById('final-total').value;
            const paymentMethodText = paymentMethod.value;
            const deliveryOptionText = deliveryOption.value;
            
            // Show confirmation dialog
            Swal.fire({
                title: 'Confirm Order Placement',
                html: `
                    <div class="text-start">
                        <p><strong>Payment Method:</strong> ${paymentMethodText}</p>
                        <p><strong>Delivery:</strong> ${deliveryOptionText === 'delivery' ? 'Delivery' : 'Pickup'}</p>
                        <p><strong>Total Amount:</strong> ₱${parseFloat(totalPrice).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</p>
                        <hr>
                        <p class="text-muted">Are you sure you want to place this order?</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7F1734',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Yes, Place Order',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                customClass: {
                    popup: 'swal2-popup',
                    title: 'swal2-title',
                    htmlContainer: 'swal2-html-container',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Processing Order...',
                        text: 'Please wait while we process your order.',
                        icon: 'info',
                        allowOutsideClick: false,
                        allowEscapeKey: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    // Submit the form
                    form.submit();
                }
            });
        }

        // Debug function to check form data before submission
        function debugFormSubmission() {
            // Copy values from visible fields to hidden fields before submission
            const transactionIdField = document.querySelector('input[name="gcash_transaction_id"]');
            const paymentProofField = document.querySelector('input[name="payment_proof"]');
            const hiddenTransactionId = document.getElementById('gcash_transaction_id_hidden');
            const hiddenPaymentProof = document.getElementById('payment_proof_hidden');
            
            if (transactionIdField && hiddenTransactionId) {
                hiddenTransactionId.value = transactionIdField.value;
            }
            if (paymentProofField && hiddenPaymentProof) {
                hiddenPaymentProof.value = paymentProofField.value;
            }
            
            const form = document.querySelector('form[action="place_order.php"]');
            const formData = new FormData(form);
            
            console.log('=== FORM SUBMISSION DEBUG ===');
            console.log('Payment method:', formData.get('payment_method'));
            console.log('GCash transaction ID (visible):', formData.get('gcash_transaction_id'));
            console.log('GCash transaction ID (hidden):', formData.get('gcash_transaction_id_hidden'));
            console.log('GCash upload section visible:', document.getElementById('gcash-upload').style.display);
            console.log('All form data:');
            for (let [key, value] of formData.entries()) {
                console.log(key + ':', value);
            }
            console.log('=== END DEBUG ===');
            
            // Return true to allow form submission to continue
            return true;
        }

        // GCash Transaction ID validation
        function initializeGCashValidation() {
            const transactionIdInput = document.querySelector('input[name="gcash_transaction_id"]');
            
            if (transactionIdInput) {
                // Only allow numbers
                transactionIdInput.addEventListener('input', function() {
                    // Remove any non-numeric characters
                    this.value = this.value.replace(/[^0-9]/g, '');
                    
                    // Limit to 13 digits
                    if (this.value.length > 13) {
                        this.value = this.value.substring(0, 13);
                    }
                });
                
                // Prevent non-numeric input
                transactionIdInput.addEventListener('keypress', function(e) {
                    // Allow backspace, delete, tab, escape, enter
                    if ([8, 9, 27, 13, 46].indexOf(e.keyCode) !== -1 ||
                        // Allow Ctrl+A, Ctrl+C, Ctrl+V, Ctrl+X
                        (e.keyCode === 65 && e.ctrlKey === true) ||
                        (e.keyCode === 67 && e.ctrlKey === true) ||
                        (e.keyCode === 86 && e.ctrlKey === true) ||
                        (e.keyCode === 88 && e.ctrlKey === true)) {
                        return;
                    }
                    // Ensure that it is a number and stop the keypress
                    if ((e.shiftKey || (e.keyCode < 48 || e.keyCode > 57)) && (e.keyCode < 96 || e.keyCode > 105)) {
                        e.preventDefault();
                    }
                });
                
                // Validate on blur
                transactionIdInput.addEventListener('blur', function() {
                    if (this.value.length > 0 && this.value.length !== 13) {
                        this.setCustomValidity('GCash Transaction ID must be exactly 13 digits');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
                
                // Clear validation on input
                transactionIdInput.addEventListener('input', function() {
                    if (this.value.length === 13) {
                        this.setCustomValidity('');
                    }
                });
            }
        }

        // Initialize address autocomplete when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeAddressAutocomplete();
            initializeGCashValidation();
            
            // Initialize form state - ensure pickup is selected by default and address fields are not required
            const pickupRadio = document.querySelector('input[name="delivery_option"][value="pickup"]');
            if (pickupRadio) {
                pickupRadio.checked = true;
                // Ensure delivery address fields are not required when pickup is selected
                const deliveryFields = document.querySelectorAll('#delivery-address-section input[type="text"], #delivery-address-section input[type="email"], #delivery-address-section textarea');
                deliveryFields.forEach(field => {
                    field.removeAttribute('required');
                });
            }
        });
    </script>
</body>
</html>

