
<?php
session_start();
include 'includes/db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = $_SESSION['user_id'];

// Fetch user profile info using normalized structure
$stmt = $pdo->prepare("SELECT u.user_id, u.username, ui.email, ui.first_name, ui.last_name, ui.phone 
                       FROM users u 
                       INNER JOIN user_info ui ON u.user_id = ui.user_id 
                       WHERE u.user_id = :user_id");
$stmt->execute(['user_id' => $user_id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch user's default address
$address_stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :user_id AND is_default = 1 LIMIT 1");
$address_stmt->execute(['user_id' => $user_id]);
$default_address = $address_stmt->fetch(PDO::FETCH_ASSOC);

// Fetch the products in the cart
$cart_items = [];
$total_price = 0;
foreach ($_SESSION['cart'] as $product_id => $cart_item) {
    // Use normalized structure to get product with pricing
    $sql = "SELECT p.product_id, p.product_name, p.product_description, pp.selling_price as price,
                   (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as image
            FROM products p 
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id 
            WHERE p.product_id = :product_id AND p.is_archive = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($product) {
        $cart_items[] = [
            'product' => $product,
            'quantity' => $cart_item['quantity']
        ];
        $total_price += $product['price'] * $cart_item['quantity'];
    }
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
        if ($discount_row['discount_type'] === 'percent') {
            $discount = $total_price * ($discount_row['discount_value'] / 100);
        } else {
            $discount = $discount_row['discount_value'];
        }
        $_SESSION['discount'] = $discount;
        $_SESSION['discount_code'] = $discount_code;
    } else {
        $discount_error = "Invalid or expired discount code.";
        unset($_SESSION['discount']);
        unset($_SESSION['discount_code']);
    }
} elseif (isset($_SESSION['discount'])) {
    $discount = $_SESSION['discount'];
    $discount_code = $_SESSION['discount_code'] ?? '';
}
$final_total = $total_price - $discount;
if ($final_total < 0) $final_total = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['payment_method'])) {
    // Update user_info table with normalized structure
    $sql = "UPDATE user_info SET
        first_name = :first_name,
        last_name = :last_name,
        email = :email,
        phone = :phone
        WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $_POST['firstname'],
        ':last_name' => $_POST['lastname'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':user_id' => $user_id
    ]);

    // Handle delivery address and customer information based on selection
    if (isset($_POST['delivery_option']) && $_POST['delivery_option'] === 'delivery') {
        if (isset($_POST['address_option']) && $_POST['address_option'] === 'new') {
            // Use new delivery address and customer info
            $delivery_address = $_POST['delivery_address'];
            $delivery_city = $_POST['delivery_city'];
            $delivery_postal_code = $_POST['delivery_postal_code'];
            $delivery_instructions = $_POST['delivery_instructions'] ?? '';
            
            // Store customer info from new address form
            $customer_info = [
                'first_name' => $_POST['firstname'],
                'last_name' => $_POST['lastname'],
                'email' => $_POST['email'],
                'phone' => $_POST['phone']
            ];
        } else {
            // Use default address and existing customer info
            $delivery_address = $default_address['address_line'] ?? '';
            $delivery_city = $default_address['city'] ?? '';
            $delivery_postal_code = $default_address['postal_code'] ?? '';
            $delivery_instructions = '';
            
            // Use existing customer info
            $customer_info = [
                'first_name' => $user['first_name'],
                'last_name' => $user['last_name'],
                'email' => $user['email'],
                'phone' => $user['phone']
            ];
        }
        
        // Store delivery address and customer info in session for order processing
        $_SESSION['delivery_address'] = [
            'address' => $delivery_address,
            'city' => $delivery_city,
            'postal_code' => $delivery_postal_code,
            'instructions' => $delivery_instructions
        ];
        
        $_SESSION['customer_info'] = $customer_info;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - MikeMadz</title>
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
        }

        .place-order-btn:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.4);
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
    <?php include 'includes/promo_banner.php'; ?>

    <?php include 'includes/user_navbar.php'; ?>

    <div class="container my-5">
        <?php if (isset($_SESSION['upload_error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle me-2"></i>
                <?php echo $_SESSION['upload_error']; unset($_SESSION['upload_error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>
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
                            <?php foreach ($cart_items as $item): ?>
                                <div class="cart-item">
                                    <img src="<?php echo !empty($item['product']['image1']) ? 'admin/' . htmlspecialchars($item['product']['image1']) : 'admin/uploads/placeholder.jpg'; ?>" 
                                         alt="<?php echo htmlspecialchars($item['product']['name']); ?>" 
                                         class="product-image">
                                    <div class="product-details">
                                        <div class="product-name"><?= htmlspecialchars($item['product']['name']) ?></div>
                                        <div class="text-muted">Quantity: <?= $item['quantity'] ?></div>
                                    </div>
                                    <div class="product-price">₱<?= number_format($item['product']['price'] * $item['quantity'], 2) ?></div>
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
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="radio-option">
                                                <input type="radio" name="address_option" value="default" id="default-address">
                                                <div>
                                                    <strong>Use Default Address</strong>
                                                    <div class="text-muted small">
                                                        <?php if ($default_address): ?>
                                                            <strong><?= htmlspecialchars($user['first_name'] ?? '') ?> <?= htmlspecialchars($user['last_name'] ?? '') ?></strong><br>
                                                            <?= htmlspecialchars($user['phone'] ?? '') ?><br>
                                                            <?= htmlspecialchars($default_address['address_line']) ?>, <?= htmlspecialchars($default_address['city']) ?>
                                                        <?php else: ?>
                                                            No default address set
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="radio-option">
                                                <input type="radio" name="address_option" value="new" id="new-address">
                                                <div>
                                                    <strong>Use Different Address</strong>
                                                    <div class="text-muted small">Enter a new delivery address</div>
                                                </div>
                                            </label>
                                        </div>
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
                                            <input type="text" name="delivery_address" class="form-control" placeholder="Enter complete delivery address">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">City *</label>
                                            <input type="text" name="delivery_city" class="form-control" placeholder="Enter city">
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label fw-bold">Postal Code *</label>
                                            <input type="text" name="delivery_postal_code" class="form-control" placeholder="Enter postal code">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label fw-bold">Special Instructions (Optional)</label>
                                            <textarea name="delivery_instructions" class="form-control" rows="3" placeholder="Any special delivery instructions..."></textarea>
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
                                <div class="row">
                                    <div class="col-md-6">
                                        <label class="radio-option">
                                            <input type="radio" name="payment_method" value="Cash" id="cash-payment" required>
                                            <div>
                                                <strong>Cash Payment</strong>
                                                <div class="text-muted small">Pay with cash on pickup/delivery</div>
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
                                    <p class="text-muted mb-3">Scan the QR code above and upload your payment screenshot below</p>
                                    <div class="mb-3">
                                        <label class="form-label fw-bold">Upload Payment Proof</label>
                                        <input type="file" name="payment_proof" class="form-control" accept="image/*" required>
                                    </div>
                                </div>
                            </div>

                            <button type="submit" class="btn place-order-btn w-100">
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
                                <span>Shipping:</span>
                                <span class="text-success fw-bold">FREE</span>
                            </div>

                            <div class="summary-row">
                                <span>Total:</span>
                                <span id="total-with-shipping">₱<?= number_format($final_total, 2) ?></span>
                            </div>

                            <input type="hidden" id="base-total" value="<?= $final_total ?>">
                            <input type="hidden" name="shipping_fee" id="shipping-fee" value="0">
                            <input type="hidden" name="total_price" id="final-total" value="<?= $final_total ?>">

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
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Toggle GCash upload section
        const cashPayment = document.getElementById('cash-payment');
        const gcashPayment = document.getElementById('gcash-payment');
        const gcashUpload = document.getElementById('gcash-upload');
        
        if (cashPayment && gcashPayment && gcashUpload) {
            cashPayment.addEventListener('change', function() {
                if (this.checked) {
                    gcashUpload.style.display = 'none';
                    const fileInput = gcashUpload.querySelector('input[type="file"]');
                    if (fileInput) fileInput.removeAttribute('required');
                }
            });
            
            gcashPayment.addEventListener('change', function() {
                if (this.checked) {
                    gcashUpload.style.display = 'block';
                    const fileInput = gcashUpload.querySelector('input[type="file"]');
                    if (fileInput) fileInput.setAttribute('required', 'required');
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
                    // Set default address as selected if available, otherwise select new address
                    const defaultAddressRadio = document.getElementById('default-address');
                    const newAddressRadio = document.getElementById('new-address');
                    
                    <?php if ($default_address): ?>
                        if (defaultAddressRadio) {
                            defaultAddressRadio.checked = true;
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
                }
            });
        });

        // Show/hide new address form based on address option
        addressOption.forEach(radio => {
            radio.addEventListener('change', function() {
                if (this.value === 'new') {
                    newAddressForm.style.display = 'block';
                    // Make all required fields required
                    const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"]');
                    requiredFields.forEach(field => {
                        field.setAttribute('required', 'required');
                    });
                } else {
                    newAddressForm.style.display = 'none';
                    // Remove required attribute from new address fields
                    const requiredFields = newAddressForm.querySelectorAll('input[type="text"], input[type="email"]');
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

        // Delivery option shipping fee calculation
        document.querySelectorAll('input[name="delivery_option"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const baseTotal = parseFloat(document.getElementById('base-total').value);
                const shippingFeeElement = document.getElementById('shipping-fee');
                const finalTotalElement = document.getElementById('final-total');
                const totalDisplay = document.getElementById('total-with-shipping');
                
                let shippingFee = 0;
                if (this.value === 'delivery') {
                    shippingFee = baseTotal >= 7000 ? 0 : 100; // Free delivery on ₱7,000+
                }
                
                const finalTotal = baseTotal + shippingFee;
                
                shippingFeeElement.value = shippingFee;
                finalTotalElement.value = finalTotal;
                totalDisplay.textContent = '₱' + finalTotal.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
                
                // Update shipping display
                const shippingRow = document.querySelector('.summary-row:nth-child(3) span:last-child');
                if (shippingRow) {
                    if (shippingFee === 0) {
                        shippingRow.textContent = 'FREE';
                        shippingRow.className = 'text-success fw-bold';
                    } else {
                        shippingRow.textContent = '₱' + shippingFee.toFixed(2);
                        shippingRow.className = 'fw-bold';
                    }
                }
            });
        });
    </script>
</body>
</html>

