<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    header('Location: login.php');
    exit;
}

// Check if user has verified ID
$stmt = $pdo->prepare("SELECT id_verified FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_verification = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_verification || !$user_verification['id_verified']) {
    $_SESSION['verification_required'] = "Please verify your ID before placing an order.";
    header('Location: id_verification.php');
    exit;
}

// Check if user has a default address
$address_stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :user_id AND is_default = 1 LIMIT 1");
$address_stmt->execute(['user_id' => $_SESSION['user_id']]);
$default_address = $address_stmt->fetch(PDO::FETCH_ASSOC);

if (!$default_address) {
    $_SESSION['address_required'] = "Please add a delivery address before placing an order. You'll be redirected to add your address.";
    $_SESSION['show_address_modal'] = true; // Flag to auto-open address modal
    header('Location: orders.php');
    exit;
}

// Check if a file was uploaded
$notification = "";  // Variable to store notification message
$fileName = null;    // Initialize fileName to null by default

// Only process file upload if payment method is GCash
if (isset($_POST['payment_method']) && $_POST['payment_method'] === 'GCash') {
    if (isset($_FILES['payment_proof']) && $_FILES['payment_proof']['error'] == 0) {
        $uploadedFile = $_FILES['payment_proof'];
        $uploadDirectory = 'uploads/';

        // Create the directory if it does not exist
        if (!is_dir($uploadDirectory)) {
            mkdir($uploadDirectory, 0777, true);
        }

        // Generate a unique name for the file to avoid conflicts
        $fileName = uniqid() . '_' . basename($uploadedFile['name']);
        $targetFilePath = $uploadDirectory . $fileName;

        // Move the uploaded file to the target directory
        if (move_uploaded_file($uploadedFile['tmp_name'], $targetFilePath)) {
            $notification = "Payment proof uploaded successfully.";
        } else {
            $notification = "Failed to upload payment proof. Please try again.";
            // Return to checkout page if payment proof upload fails
            $_SESSION['upload_error'] = "Failed to upload payment proof. Please try again.";
            header('Location: checkout.php');
            exit;
        }
    } else {
        // If GCash selected but no file uploaded or there was an error
        $_SESSION['upload_error'] = "Please upload proof of payment for GCash transactions.";
        header('Location: checkout.php');
        exit;
    }
}

// Calculate the total price from cart items if not passed in form
if (!isset($_POST['total_price']) || empty($_POST['total_price'])) {
    // Calculate total from cart items
    $total_price = 0;
    foreach ($_SESSION['cart'] as $product_id => $cart_item) {
        // Get product price from normalized database structure
        $stmt = $pdo->prepare("SELECT pp.selling_price as price FROM products p 
                               LEFT JOIN product_pricing pp ON p.product_id = pp.product_id 
                               WHERE p.product_id = :product_id AND p.is_archive = 0");
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        $product = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($product) {
            $total_price += $product['price'] * $cart_item['quantity'];
        }
    }
} else {
    // Use the value from the form
    $total_price = floatval($_POST['total_price']);
}

// Make sure user_id is set
$user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;

// Double-check user_id again to be sure
if (empty($user_id)) {
    $_SESSION['upload_error'] = "Your session has expired. Please log in again.";
    header('Location: login.php');
    exit;
}

// Get shipping fee and delivery option
$shipping_fee = isset($_POST['shipping_fee']) ? floatval($_POST['shipping_fee']) : 0;
$delivery_option = isset($_POST['delivery_option']) ? $_POST['delivery_option'] : 'pickup';
$payment_method = isset($_POST['payment_method']) ? $_POST['payment_method'] : 'Cash';
$payment_proof = isset($fileName) ? $fileName : null;  // Store the filename if uploaded

// Double-check that GCash payments have file proof
if ($payment_method === 'GCash' && $payment_proof === null) {
    $_SESSION['upload_error'] = "Payment proof is required for GCash transactions.";
    header('Location: checkout.php');
    exit;
}

// Add shipping fee to total price if it's for delivery
if ($delivery_option === 'delivery') {
    $total_price = $total_price + $shipping_fee;
}

// Make sure total_price is not null or empty
if (empty($total_price)) {
    $_SESSION['upload_error'] = "Error calculating order total. Please try again.";
    header('Location: checkout.php');
    exit;
}

// Insert order into the orders table using normalized structure
$sql = "INSERT INTO orders (user_id, orderstatus_id, total_price, delivery_option) 
        VALUES (:user_id, 1, :total_price, :delivery_option)";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':total_price', $total_price);
$stmt->bindParam(':delivery_option', $delivery_option);
$stmt->execute();

$order_id = $pdo->lastInsertId();

// Insert payment information into payments table
if ($payment_method && $payment_method !== 'COD') {
    $sql = "INSERT INTO payments (orders_id, amount, method, proof, paymentstatus_id) 
            VALUES (:order_id, :amount, :method, :proof, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':amount', $total_price);
    $stmt->bindParam(':method', $payment_method);
    $stmt->bindParam(':proof', $payment_proof);
    $stmt->execute();
}

// Validate stock availability before processing order
foreach ($_SESSION['cart'] as $product_id => $cart_item) {
    $quantity = $cart_item['quantity'];
    
    // Check current stock availability
    $sql = "SELECT current_stock FROM product_stock WHERE product_id = :product_id";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$stock_data || $stock_data['current_stock'] < $quantity) {
        $_SESSION['error'] = "Insufficient stock for one or more products. Please update your cart and try again.";
        header('Location: checkout.php');
        exit;
    }
}

// Insert order items into the order_items table and update product stock
foreach ($_SESSION['cart'] as $product_id => $cart_item) {
    $quantity = $cart_item['quantity'];

    // Step 1: Check if the product exists and get its price
    $stmt = $pdo->prepare("SELECT p.product_id, pp.selling_price as price FROM products p 
                           LEFT JOIN product_pricing pp ON p.product_id = pp.product_id 
                           WHERE p.product_id = :product_id AND p.is_archive = 0");
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    // Step 2: If the product exists, insert it into the order_items table
    if ($product) {
        // Insert order items with price
        $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':price', $product['price']);
        $stmt->execute();

        // Update product stock in normalized structure
        $sql = "UPDATE product_stock SET current_stock = current_stock - :quantity WHERE product_id = :product_id AND current_stock >= :quantity";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
    }
}

// Clear the cart
unset($_SESSION['cart']);
$_SESSION['cart'] = [];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
        
        body {
            background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        .success-modal .modal-content {
            border: none;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            overflow: hidden;
        }
        .success-modal .modal-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            border-radius: 20px 20px 0 0;
            border: none;
            padding: 2rem;
            text-align: center;
        }
        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
            color: white;
        }
        .modal-title {
            font-weight: 700;
            font-size: 1.5rem;
        }
        .order-details {
            background: var(--bs-light);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid #e9ecef;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e9ecef;
            align-items: center;
        }
        .detail-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--bs-secondary);
            background: rgba(127, 23, 52, 0.05);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 0.5rem;
        }
        .btn-primary {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            color: white;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3);
            background: linear-gradient(135deg, #6b1429 0%, #8b1a36 100%);
            color: white;
        }
        .btn-outline-secondary {
            border: 2px solid var(--bs-secondary);
            color: var(--bs-secondary);
            border-radius: 10px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            background: transparent;
            transition: all 0.3s ease;
        }
        .btn-outline-secondary:hover {
            background: var(--bs-secondary);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.2);
        }
        .modal-body {
            color: var(--bs-dark);
        }
        .text-success {
            color: var(--bs-success) !important;
        }
    </style>
</head>
<body>
    <!-- Success Modal -->
    <div class="modal fade success-modal" id="successModal" tabindex="-1" aria-labelledby="successModalLabel" aria-hidden="true" data-bs-backdrop="static" data-bs-keyboard="false">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header text-center">
                    <div class="w-100">
                        <i class="fas fa-check-circle success-icon"></i>
                        <h4 class="modal-title w-100" id="successModalLabel">Order Placed Successfully!</h4>
                    </div>
                </div>
                <div class="modal-body text-center p-4">
                    <p class="mb-4">Your order has been placed and is now pending. Thank you for shopping with us!</p>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <span>Order Total:</span>
                            <span class="fw-bold text-success">₱<?= number_format($total_price, 2) ?></span>
                        </div>
                        <?php if ($shipping_fee > 0): ?>
                        <div class="detail-row">
                            <span>Shipping Fee:</span>
                            <span>₱<?= number_format($shipping_fee, 2) ?></span>
                        </div>
                        <?php endif; ?>
                        <div class="detail-row">
                            <span>Payment Method:</span>
                            <span><?= ucfirst($payment_method) ?></span>
                        </div>
                        <div class="detail-row">
                            <span>Delivery Option:</span>
                            <span><?= ucfirst($delivery_option) ?></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer justify-content-center border-0 p-4">
                    <button type="button" class="btn btn-outline-secondary me-3" onclick="goToProducts()">
                        <i class="fas fa-shopping-bag me-2"></i>Continue Shopping
                    </button>
                    <button type="button" class="btn btn-primary" onclick="goToOrders()">
                        <i class="fas fa-list me-2"></i>View Orders
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Show the modal automatically
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
        });

        function goToOrders() {
            window.location.href = 'orders.php';
        }

        function goToProducts() {
            window.location.href = 'product.php';
        }
    </script>
</body>
</html>
