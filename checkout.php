
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

$has_any_address = !empty($all_addresses);
if (!$has_any_address) {
    // Let the page render with a visible banner and block place order instead of redirecting
    $_SESSION['address_required'] = "Please add a delivery address before placing an order.";
    $_SESSION['show_address_modal'] = true; // Orders page can use this flag
}

// Fetch user profile info using normalized structure
$stmt = $pdo->prepare("SELECT u.user_id, u.username, ui.email, ui.first_name, ui.last_name, ui.phone, ui.gcash_number 
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

// Get selected cart items from sessionStorage (passed from cart page)
$selected_cart_items = [];
if (isset($_POST['selected_items'])) {
    $selected_cart_items = json_decode($_POST['selected_items'], true) ?? [];
} else {
    // Fallback: if no selection data, process all items (backward compatibility)
    $selected_cart_items = null;
}

// Use CartManager to load cart data from database (same as cart_total.php)
$cartManager = new CartManager($pdo);
$batchManager = new BatchManager($pdo);
$cart_data = $cartManager->loadCartFromDatabase($_SESSION['user_id']);

foreach ($cart_data as $cart_key => $cart_item) {
    // Skip if this item is not selected (when selection is active)
    if ($selected_cart_items !== null && !in_array($cart_key, $selected_cart_items)) {
        continue;
    }
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
    ORDER BY pb.received_date DESC, pb.batch_id DESC
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
        ORDER BY pb.received_date DESC, pb.batch_id DESC
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

// Fetch GCash settings from database
$gcash_settings_stmt = $pdo->prepare("SELECT setting_key, setting_value FROM system_settings WHERE setting_key LIKE 'gcash_%'");
$gcash_settings_stmt->execute();
$gcash_settings = $gcash_settings_stmt->fetchAll(PDO::FETCH_KEY_PAIR);

// Set default values if not set
$gcash_defaults = [
    'gcash_qr_code' => 'assets/gcashqr/gcash_qr.jpg',
    'gcash_instructions' => 'Scan the QR code above and complete your payment',
    'gcash_enabled' => '1'
];

foreach ($gcash_defaults as $key => $default_value) {
    if (!isset($gcash_settings[$key])) {
        $gcash_settings[$key] = $default_value;
    }
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
    <?php include 'includes/user_head.php'; ?>
    
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            min-height: 100vh;
            color: var(--text-primary);
        }

        .checkout-container {
            background: var(--bg-card);
            border-radius: 1rem;
            box-shadow: 0 10px 30px var(--shadow-medium);
            margin: 2rem 0;
            overflow: hidden;
            border: 1px solid var(--border-light);
        }

        .checkout-header {
            background: var(--bg-card);
            border-radius: 1.5rem;
            padding: 2rem;
            box-shadow: 0 20px 40px var(--shadow-medium);
            margin-bottom: 2rem;
            text-align: center;
        }

        .checkout-header h1 {
            font-size: 2.5rem;
            font-weight: 800;
            color: var(--brand-primary);
            margin: 0;
            background: var(--brand-gradient);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .form-section {
            background: var(--bg-card);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 10px var(--shadow-light);
            border: 1px solid var(--border-light);
        }

        .section-title {
            color: var(--brand-primary);
            font-weight: 700;
            font-size: 1.25rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-control {
            border: 2px solid var(--border-light);
            border-radius: 0.5rem;
            padding: 0.75rem;
            font-weight: 500;
            transition: all 0.3s ease;
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
            background: var(--input-bg);
            color: var(--text-primary);
        }

        .form-control[readonly] {
            background-color: var(--bg-tertiary);
            cursor: not-allowed;
            color: var(--text-secondary);
        }

        .cart-item {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-tertiary);
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
            border: 2px solid var(--border-light);
        }

        .product-details {
            flex: 1;
            padding-left: 1rem;
        }

        .product-name {
            font-weight: 600;
            color: var(--brand-primary);
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
            background: var(--bg-tertiary);
            border: 1px solid var(--border-light);
            border-radius: 0.5rem;
            padding: 1rem;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.75rem;
        }
        .order-row {
            padding: 1rem;
            border-bottom: 1px solid var(--border-light);
            background: var(--bg-card);
            border-radius: 0.5rem;
        }
        .order-row:last-child { border-bottom: none; }
        .order-cell--image { display: flex; align-items: center; }
        .order-col--sub { color: var(--bs-danger); font-weight: 700; }

        @media (max-width: 768px) {
            .order-header { display: none; }
            .order-row { grid-template-columns: 80px 1fr; row-gap: 0.25rem; }
            .order-row .od-qty, .order-row .od-unit, .order-row .od-sub { display: flex; gap: 0.5rem; font-size: 0.9rem; color: var(--text-secondary); }
            .order-row .od-sub { color: var(--bs-danger); font-weight: 700; }
        }

        .order-summary {
            background: linear-gradient(135deg, var(--bg-tertiary) 0%, var(--bg-card) 100%);
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 5px 15px var(--shadow-medium);
            position: sticky;
            top: 2rem;
            border: 1px solid var(--border-light);
        }

        .summary-title {
            color: var(--brand-primary);
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
            border-bottom: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        .summary-row:last-child {
            border-bottom: none;
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--brand-primary);
            border-top: 2px solid var(--brand-primary);
            padding-top: 1rem;
            margin-top: 1rem;
        }

        .discount-form {
            background: var(--bg-tertiary);
            border-radius: 0.5rem;
            padding: 1rem;
            margin-bottom: 1rem;
            border: 1px solid var(--border-light);
        }

        .btn-primary {
            background: var(--brand-gradient);
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
            border-color: var(--brand-primary);
            color: var(--brand-primary);
            border-width: 2px;
            font-weight: 600;
        }

        .btn-outline-secondary:hover {
            background-color: var(--brand-primary);
            border-color: var(--brand-primary);
        }

        .radio-option {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--bg-tertiary);
            border: 2px solid var(--border-light);
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .radio-option:hover {
            border-color: var(--brand-primary);
            background: var(--bg-card);
        }

        .radio-option input[type="radio"] {
            margin-right: 0.75rem;
            accent-color: var(--brand-primary);
        }

        .gcash-section {
            background: var(--bg-tertiary);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 1rem;
            text-align: center;
            border: 1px solid var(--border-light);
        }

        .gcash-qr {
            max-width: 300px;
            border-radius: 0.75rem;
            box-shadow: 0 5px 15px var(--shadow-medium);
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
            color: var(--text-secondary);
        }

        .gcash-section .form-text i {
            color: var(--bs-info);
        }

        .empty-cart {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--text-secondary);
        }

        .empty-cart i {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: var(--text-muted);
        }

        .place-order-btn {
            background: var(--brand-gradient);
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
            background: var(--bg-tertiary);
            border: 2px solid var(--border-light);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 1rem;
        }

        #new-address-form {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
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
            border: 1px solid var(--border-light);
            border-radius: 0.5rem;
            padding: 1rem;
            background: var(--bg-tertiary);
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
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 4px 6px var(--shadow-medium);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .address-suggestion {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid var(--bg-tertiary);
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: var(--text-primary);
        }

        .address-suggestion:hover,
        .address-suggestion.active {
            background-color: var(--bg-tertiary);
        }

        .address-suggestion.active {
            background-color: var(--bg-card);
            border-left: 3px solid var(--brand-primary);
        }

        .address-suggestion:last-child {
            border-bottom: none;
        }

        .address-suggestion i {
            color: var(--brand-primary);
            font-size: 0.9rem;
        }

        .address-suggestion .address-text {
            flex: 1;
        }

        .address-suggestion .address-main {
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 0.25rem;
        }

        .address-suggestion .address-details {
            font-size: 0.85rem;
            color: var(--text-secondary);
        }

        .address-loading {
            padding: 1rem;
            text-align: center;
            color: var(--text-secondary);
        }

        .address-loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Dark mode text fixes */
        .text-muted {
            color: var(--text-secondary) !important;
        }

        .fw-semibold.text-dark {
            color: var(--text-primary) !important;
        }

        .small.text-muted {
            color: var(--text-secondary) !important;
        }

        .text-primary {
            color: var(--brand-primary) !important;
        }

        .text-success {
            color: var(--bs-success) !important;
        }

        .text-warning {
            color: var(--bs-warning) !important;
        }

        .text-danger {
            color: var(--bs-danger) !important;
        }

        .text-info {
            color: var(--bs-info) !important;
        }

        .alert {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            color: var(--text-primary);
        }

        .alert-info {
            background: var(--bg-tertiary);
            border-color: var(--bs-info);
            color: var(--text-primary);
        }

        .alert-warning {
            background: var(--bg-tertiary);
            border-color: var(--bs-warning);
            color: var(--text-primary);
        }

        .alert-danger {
            background: var(--bg-tertiary);
            border-color: var(--bs-danger);
            color: var(--text-primary);
        }

        .alert-success {
            background: var(--bg-tertiary);
            border-color: var(--bs-success);
            color: var(--text-primary);
        }

        /* GCash Reference Validation Feedback */
        #gcash-reference-validation-feedback {
            font-size: 0.85rem;
            min-height: 20px;
        }
        .reference-valid {
            color: var(--bs-success);
        }
        .reference-invalid {
            color: var(--bs-danger);
        }
        .reference-checking {
            color: var(--bs-info);
        }

        /* Placeholder text color fix */
        .form-control::placeholder {
            color: var(--text-secondary) !important;
            opacity: 1;
        }

        .form-control::-webkit-input-placeholder {
            color: var(--text-secondary) !important;
            opacity: 1;
        }

        .form-control::-moz-placeholder {
            color: var(--text-secondary) !important;
            opacity: 1;
        }

        .form-control:-ms-input-placeholder {
            color: var(--text-secondary) !important;
            opacity: 1;
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
        <?php if (isset($_SESSION['address_required'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-map-marker-alt me-2"></i>
                <?= $_SESSION['address_required'] ?>
                <a href="orders.php#addresses" class="btn btn-sm btn-primary ms-2">
                    <i class="fas fa-map-marker-alt me-1"></i>Go to My Addresses
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php unset($_SESSION['address_required']); ?>
        <?php endif; ?>
        <?php if (empty($user['phone']) || empty($user['gcash_number'])): ?>
            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                Please provide your Phone Number and GCash Number in My Account before placing an order.
                <a href="orders.php#account" class="btn btn-sm btn-primary ms-2">
                    <i class="fas fa-user-cog me-1"></i>Go to My Account
                </a>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
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
                            <?php if (!empty($user['gcash_number'])): ?>
                                <span>GCash: <?= htmlspecialchars($user['gcash_number']) ?></span><br>
                            <?php endif; ?>
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
                                            <input type="text" name="delivery_address" class="form-control" placeholder="Enter your street address..." required>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Region *</label>
                                            <select name="delivery_region" id="delivery_region" class="form-control" required>
                                                <option value="">Select Region</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">City/Municipality *</label>
                                            <div class="position-relative">
                                                <input type="text" name="delivery_city" id="delivery_city" class="form-control" placeholder="Start typing city name..." autocomplete="off" required>
                                                <div id="delivery-city-suggestions" class="address-suggestions"></div>
                                            </div>
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
                                    <?php if (file_exists($gcash_settings['gcash_qr_code'])): ?>
                                        <img src="<?= htmlspecialchars($gcash_settings['gcash_qr_code']) ?>" alt="GCash QR Code" class="img-fluid gcash-qr">
                                    <?php else: ?>
                                        <div class="alert alert-warning">
                                            <i class="fas fa-exclamation-triangle me-2"></i>
                                            GCash QR code not found. Please contact the administrator.
                                        </div>
                                    <?php endif; ?>
                                    <p class="text-muted mb-3"><?= htmlspecialchars($gcash_settings['gcash_instructions']) ?></p>
                                    
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <label class="form-label fw-bold">GCash Reference Number *</label>
                                            <input type="text" name="gcash_transaction_id" class="form-control" 
                                                   placeholder="Enter your GCash Reference Number (8-13 digits)" 
                                                   pattern="[0-9]{8,13}" 
                                                   maxlength="13"
                                                   minlength="8"
                                                   title="Please enter 8 to 13 digits for your GCash Reference Number">
                                            <small class="form-text text-muted">
                                                <i class="fas fa-info-circle me-1"></i>
                                                Enter 8 to 13 digits from your GCash Reference Number
                                            </small>
                                            <div id="gcash-reference-validation-feedback" class="mt-2"></div>
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
                                <input type="hidden" name="selected_items" value="<?= htmlspecialchars(json_encode($selected_cart_items ?? [])) ?>">
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
                    const deliveryFields = deliveryAddressSection.querySelectorAll('input[type="text"], input[type="email"], textarea, select');
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
                    const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"], textarea, select');
                    requiredFields.forEach(field => {
                        if (field.name === 'delivery_address' || field.name === 'delivery_region' || field.name === 'delivery_city' || field.name === 'delivery_postal_code') {
                            field.setAttribute('required', 'required');
                        }
                    });
                } else {
                    newAddressForm.style.display = 'none';
                    // Remove required attribute from new address fields when not selected
                    const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"], textarea, select');
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

        // Philippine Regions and Cities API
        const PHILIPPINE_REGIONS = [
            { code: 'NCR', name: 'National Capital Region (NCR)' },
            { code: 'CAR', name: 'Cordillera Administrative Region (CAR)' },
            { code: '01', name: 'Region I - Ilocos Region' },
            { code: '02', name: 'Region II - Cagayan Valley' },
            { code: '03', name: 'Region III - Central Luzon' },
            { code: '04A', name: 'Region IV-A - CALABARZON' },
            { code: '04B', name: 'Region IV-B - MIMAROPA' },
            { code: '05', name: 'Region V - Bicol Region' },
            { code: '06', name: 'Region VI - Western Visayas' },
            { code: '07', name: 'Region VII - Central Visayas' },
            { code: '08', name: 'Region VIII - Eastern Visayas' },
            { code: '09', name: 'Region IX - Zamboanga Peninsula' },
            { code: '10', name: 'Region X - Northern Mindanao' },
            { code: '11', name: 'Region XI - Davao Region' },
            { code: '12', name: 'Region XII - SOCCSKSARGEN' },
            { code: '13', name: 'Region XIII - Caraga' },
            { code: 'BARMM', name: 'Bangsamoro Autonomous Region in Muslim Mindanao (BARMM)' }
        ];

        // Philippine Cities with Postal Codes
        const PHILIPPINE_CITIES = {
            'NCR': [
                { name: 'Manila', postalCode: '1000' },
                { name: 'Quezon City', postalCode: '1100' },
                { name: 'Caloocan', postalCode: '1400' },
                { name: 'Las Piñas', postalCode: '1740' },
                { name: 'Makati', postalCode: '1200' },
                { name: 'Malabon', postalCode: '1470' },
                { name: 'Mandaluyong', postalCode: '1550' },
                { name: 'Marikina', postalCode: '1800' },
                { name: 'Muntinlupa', postalCode: '1770' },
                { name: 'Navotas', postalCode: '1485' },
                { name: 'Parañaque', postalCode: '1700' },
                { name: 'Pasay', postalCode: '1300' },
                { name: 'Pasig', postalCode: '1600' },
                { name: 'Pateros', postalCode: '1620' },
                { name: 'San Juan', postalCode: '1500' },
                { name: 'Taguig', postalCode: '1630' },
                { name: 'Valenzuela', postalCode: '1440' }
            ],
            '03': [
                { name: 'Angeles City', postalCode: '2009' },
                { name: 'Balanga', postalCode: '2100' },
                { name: 'Cabanatuan', postalCode: '3100' },
                { name: 'Gapan', postalCode: '3105' },
                { name: 'Mabalacat', postalCode: '2010' },
                { name: 'Malolos', postalCode: '3000' },
                { name: 'Meycauayan', postalCode: '3020' },
                { name: 'Muñoz', postalCode: '3119' },
                { name: 'Olongapo', postalCode: '2200' },
                { name: 'Palayan', postalCode: '3136' },
                { name: 'San Fernando', postalCode: '2000' },
                { name: 'San Jose', postalCode: '3121' },
                { name: 'Tarlac City', postalCode: '2300' }
            ],
            '04A': [
                { name: 'Antipolo', postalCode: '1870' },
                { name: 'Bacoor', postalCode: '4102' },
                { name: 'Batangas City', postalCode: '4200' },
                { name: 'Biñan', postalCode: '4024' },
                { name: 'Cabuyao', postalCode: '4025' },
                { name: 'Cainta', postalCode: '1900' },
                { name: 'Calamba', postalCode: '4027' },
                { name: 'Cavite City', postalCode: '4100' },
                { name: 'Dasmariñas', postalCode: '4114' },
                { name: 'Imus', postalCode: '4103' },
                { name: 'Laguna', postalCode: '4000' },
                { name: 'Lucena', postalCode: '4301' },
                { name: 'San Pedro', postalCode: '4023' },
                { name: 'Santa Rosa', postalCode: '4026' },
                { name: 'Taytay', postalCode: '1920' }
            ]
        };

        function initializeDeliveryRegionDropdown() {
            const select = document.getElementById('delivery_region');
            if (!select) return;

            // Clear existing options except the first one
            select.innerHTML = '<option value="">Select Region</option>';

            // Add region options
            PHILIPPINE_REGIONS.forEach(region => {
                const option = document.createElement('option');
                option.value = region.code;
                option.textContent = region.name;
                select.appendChild(option);
            });
        }

        function initializeDeliveryCityAutocomplete() {
            const input = document.getElementById('delivery_city');
            const suggestions = document.getElementById('delivery-city-suggestions');
            const regionSelect = document.getElementById('delivery_region');
            let currentSuggestions = [];
            let selectedIndex = -1;
            let debounceTimer;

            if (!input || !suggestions || !regionSelect) return;

            // Update cities when region changes
            regionSelect.addEventListener('change', function() {
                input.value = '';
                suggestions.style.display = 'none';
            });

            input.addEventListener('input', function() {
                const query = this.value.trim();
                const selectedRegion = regionSelect.value;
                
                clearTimeout(debounceTimer);
                
                if (query.length < 2 || !selectedRegion) {
                    hideSuggestions();
                    return;
                }

                debounceTimer = setTimeout(() => {
                    searchCities(query, selectedRegion);
                }, 300);
            });

            input.addEventListener('keydown', function(e) {
                if (!suggestions.style.display || suggestions.style.display === 'none') return;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
                        updateSelection();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        updateSelection();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (selectedIndex >= 0 && currentSuggestions[selectedIndex]) {
                            selectCity(currentSuggestions[selectedIndex]);
                        }
                        break;
                    case 'Escape':
                        hideSuggestions();
                        break;
                }
            });

            input.addEventListener('blur', function() {
                setTimeout(() => hideSuggestions(), 200);
            });

            function searchCities(query, regionCode) {
                showLoading();
                
                const cities = PHILIPPINE_CITIES[regionCode] || [];
                const filteredCities = cities.filter(city => 
                    city.name.toLowerCase().includes(query.toLowerCase())
                );

                currentSuggestions = filteredCities;
                displaySuggestions(filteredCities);
            }

            function displaySuggestions(cities) {
                if (cities.length === 0) {
                    hideSuggestions();
                    return;
                }

                suggestions.innerHTML = cities.map((city, index) => {
                    return `
                        <div class="address-suggestion" data-index="${index}">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="address-text">
                                <div class="address-main">${city.name}</div>
                                <div class="address-details">Postal Code: ${city.postalCode}</div>
                            </div>
                        </div>
                    `;
                }).join('');

                // Add click event listeners
                suggestions.querySelectorAll('.address-suggestion').forEach((item, index) => {
                    item.addEventListener('click', () => selectCity(cities[index]));
                });

                suggestions.style.display = 'block';
                selectedIndex = -1;
            }

            function showLoading() {
                suggestions.innerHTML = `
                    <div class="address-loading">
                        <i class="fas fa-spinner"></i>
                        <span>Searching cities...</span>
                    </div>
                `;
                suggestions.style.display = 'block';
            }

            function hideSuggestions() {
                suggestions.style.display = 'none';
                currentSuggestions = [];
                selectedIndex = -1;
            }

            function updateSelection() {
                const items = suggestions.querySelectorAll('.address-suggestion');
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === selectedIndex);
                });
            }

            function selectCity(city) {
                input.value = city.name;
                
                // Auto-fill postal code
                const postalInput = input.closest('form').querySelector('input[name="delivery_postal_code"]');
                if (postalInput) {
                    postalInput.value = city.postalCode;
                }
                
                hideSuggestions();
            }
        }
        
        // Confirm order placement with SweetAlert2
        function confirmPlaceOrder(event) {
            if (event) event.preventDefault();
            
            const form = document.querySelector('form[action="place_order.php"]');
            // Require phone and gcash number on file
            const userHasPhone = <?= json_encode(!empty($user['phone'])) ?>;
            const userHasGCash = <?= json_encode(!empty($user['gcash_number'])) ?>;
            const userHasAnyAddress = <?= json_encode($has_any_address) ?>;

            // Block if no saved address exists at all
            if (!userHasAnyAddress) {
                Swal.fire({
                    icon: 'warning',
                    title: 'No Address on File',
                    html: 'Please add a delivery address in My Addresses before placing an order.',
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-map-marker-alt me-2"></i>Go to My Addresses',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Stay Here',
                    confirmButtonColor: '#7F1734',
                    cancelButtonColor: '#6c757d'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'orders.php#addresses';
                    }
                });
                return;
            }

            if (!userHasPhone || !userHasGCash) {
                const missing = [
                    !userHasPhone ? 'Phone Number' : null,
                    !userHasGCash ? 'GCash Number' : null
                ].filter(Boolean).join(' and ');

                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Account Information',
                    html: `Please add your <strong>${missing}</strong> in My Account before placing an order.`,
                    showCancelButton: true,
                    confirmButtonText: '<i class="fas fa-user-cog me-2"></i>Go to My Account',
                    cancelButtonText: '<i class="fas fa-times me-2"></i>Stay Here',
                    confirmButtonColor: '#7F1734',
                    cancelButtonColor: '#6c757d',
                    customClass: { popup: 'swal2-popup' }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'orders.php#account';
                    }
                });
                return;
            }
            
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
                    const deliveryRegion = document.querySelector('select[name="delivery_region"]');
                    const deliveryCity = document.querySelector('input[name="delivery_city"]');
                    const deliveryPostal = document.querySelector('input[name="delivery_postal_code"]');
                    
                    if (!deliveryAddress.value || !deliveryRegion.value || !deliveryCity.value || !deliveryPostal.value) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Missing Information',
                            text: 'Please fill in all required address fields (Address, Region, City, Postal Code)',
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
                
                if (!transactionIdField.value || transactionIdField.value.length < 8 || transactionIdField.value.length > 13) {
                    Swal.fire({
                        icon: 'warning',
                        title: 'Missing Information',
                        text: 'Please enter a valid GCash transaction ID (8-13 digits)',
                        confirmButtonColor: '#7F1734'
                    });
                    return;
                }
                
                // Check if reference number has been validated and is unique
                if (window.gcashReferenceIsValid === false && transactionIdField.value.trim().length > 0) {
                    Swal.fire({
                        icon: 'error',
                        title: 'Invalid Reference Number',
                        text: 'This GCash reference number has already been used or is invalid. Please use a different reference number.',
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
                    if (this.value.length > 0 && (this.value.length < 8 || this.value.length > 13)) {
                        this.setCustomValidity('GCash Transaction ID must be 8 to 13 digits');
                        this.reportValidity();
                    } else {
                        this.setCustomValidity('');
                    }
                });
                
                // Clear validation on input
                transactionIdInput.addEventListener('input', function() {
                    if (this.value.length >= 8 && this.value.length <= 13) {
                        this.setCustomValidity('');
                    }
                });
            }
        }

        // GCash Reference Uniqueness Validation
        function validateGCashReferenceUniqueness() {
            const transactionIdInput = document.querySelector('input[name="gcash_transaction_id"]');
            const feedbackDiv = document.getElementById('gcash-reference-validation-feedback');
            
            if (!transactionIdInput || !feedbackDiv) {
                return; // Elements not found (e.g., GCash section not visible)
            }
            
            let validationDebounce;
            let isReferenceValid = false;
            
            // Store validation state globally
            window.gcashReferenceIsValid = false;
            
            function checkReferenceUniqueness(referenceNumber) {
                if (!referenceNumber || referenceNumber.length < 8) {
                    feedbackDiv.innerHTML = '';
                    isReferenceValid = false;
                    window.gcashReferenceIsValid = false;
                    return;
                }
                
                // Validate format
                if (!/^[0-9]+$/.test(referenceNumber)) {
                    feedbackDiv.innerHTML = '<i class="fas fa-times me-1"></i><span class="reference-invalid">Only numbers are allowed</span>';
                    isReferenceValid = false;
                    window.gcashReferenceIsValid = false;
                    return;
                }
                
                // Validate length
                if (referenceNumber.length < 8 || referenceNumber.length > 13) {
                    feedbackDiv.innerHTML = '<i class="fas fa-times me-1"></i><span class="reference-invalid">Must be 8 to 13 digits</span>';
                    isReferenceValid = false;
                    window.gcashReferenceIsValid = false;
                    return;
                }
                
                // Show loading indicator
                feedbackDiv.innerHTML = '<i class="fas fa-spinner fa-spin me-1 reference-checking"></i><span class="reference-checking">Checking availability...</span>';
                
                // Send AJAX request to check reference
                const formData = new FormData();
                formData.append('reference_number', referenceNumber);
                
                fetch('check_gcash_reference.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.exists) {
                        feedbackDiv.innerHTML = '<i class="fas fa-times-circle me-1"></i><span class="reference-invalid">' + data.message + '</span>';
                        isReferenceValid = false;
                        window.gcashReferenceIsValid = false;
                    } else {
                        feedbackDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i><span class="reference-valid">' + data.message + '</span>';
                        isReferenceValid = true;
                        window.gcashReferenceIsValid = true;
                    }
                })
                .catch(error => {
                    console.error('Error validating reference:', error);
                    feedbackDiv.innerHTML = '<i class="fas fa-exclamation-triangle me-1"></i><span class="reference-invalid">Error checking reference number</span>';
                    isReferenceValid = false;
                    window.gcashReferenceIsValid = false;
                });
            }
            
            // Add debounced input event listener
            transactionIdInput.addEventListener('input', function() {
                clearTimeout(validationDebounce);
                const refNumber = this.value.trim();
                
                if (refNumber.length < 8) {
                    feedbackDiv.innerHTML = '';
                    isReferenceValid = false;
                    window.gcashReferenceIsValid = false;
                    return;
                }
                
                // Debounce validation (wait 500ms after user stops typing)
                validationDebounce = setTimeout(function() {
                    checkReferenceUniqueness(refNumber);
                }, 500);
            });
            
            // Add blur event listener (immediate check when user leaves field)
            transactionIdInput.addEventListener('blur', function() {
                clearTimeout(validationDebounce);
                checkReferenceUniqueness(this.value.trim());
            });
        }

        // Initialize address autocomplete when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            initializeDeliveryRegionDropdown();
            initializeDeliveryCityAutocomplete();
            initializeGCashValidation();
            validateGCashReferenceUniqueness();
            
            // Initialize form state - ensure pickup is selected by default and address fields are not required
            const pickupRadio = document.querySelector('input[name="delivery_option"][value="pickup"]');
            if (pickupRadio) {
                pickupRadio.checked = true;
                // Ensure delivery address fields are not required when pickup is selected
                const deliveryFields = document.querySelectorAll('#delivery-address-section input[type="text"], #delivery-address-section input[type="email"], #delivery-address-section textarea, #delivery-address-section select');
                deliveryFields.forEach(field => {
                    field.removeAttribute('required');
                });
            }
        });
    </script>
</body>
</html>

