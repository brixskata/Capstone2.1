<?php
session_start();
include 'includes/db.php';
include_once 'includes/batch_manager.php';

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

// Check if user has a selected address from checkout process
if (isset($_SESSION['delivery_address']) && !empty($_SESSION['delivery_address'])) {
    // Use the selected address from session (set during checkout)
    $selected_address = $_SESSION['delivery_address'];
} else {
    // Fallback: Check if user has any address (default or not)
    $address_stmt = $pdo->prepare("SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, date_created DESC LIMIT 1");
    $address_stmt->execute(['user_id' => $_SESSION['user_id']]);
    $fallback_address = $address_stmt->fetch(PDO::FETCH_ASSOC);

    if (!$fallback_address) {
        $_SESSION['address_required'] = "Please add a delivery address before placing an order. You'll be redirected to add your address.";
        $_SESSION['show_address_modal'] = true; // Flag to auto-open address modal
        header('Location: orders.php');
        exit;
    }
    
    // Convert fallback address to same format as selected address
    $selected_address = [
        'address' => $fallback_address['address_line'],
        'address_line2' => $fallback_address['address_line2'] ?? '',
        'city' => $fallback_address['city'],
        'state' => $fallback_address['state'] ?? '',
        'postal_code' => $fallback_address['postal_code'],
        'country' => $fallback_address['country'] ?? 'Philippines',
        'instructions' => ''
    ];
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

// Use the total price from the form (includes discount)
$total_price = isset($_POST['total_price']) ? floatval($_POST['total_price']) : 0;

// If total_price is 0 or empty, calculate from cart as fallback
if ($total_price <= 0) {
    $total_price = 0;
    foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $quantity = floatval($cart_item['quantity'] ?? 1);
        $unit_price = floatval($cart_item['unit_price'] ?? 0);
        
        if ($unit_price == 0) {
            $stmt = $pdo->prepare("SELECT COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as price FROM products p 
                                   LEFT JOIN product_pricing pp ON p.product_id = pp.product_id 
                                   WHERE p.product_id = :product_id AND p.is_archive = 0");
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $unit_price = floatval($product['price']);
            }
        }
        
        $total_price += $unit_price * $quantity;
    }
    
    // Apply discount if available in session
    if (isset($_SESSION['discount']) && $_SESSION['discount'] > 0) {
        $total_price -= $_SESSION['discount'];
        if ($total_price < 0) $total_price = 0;
    }
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
$payment_proof = isset($fileName) ? $fileName : 
                (isset($_POST['payment_proof_hidden']) ? $_POST['payment_proof_hidden'] : null);  // Store the filename if uploaded
$gcash_transaction_id = isset($_POST['gcash_transaction_id']) ? trim($_POST['gcash_transaction_id']) : 
                       (isset($_POST['gcash_transaction_id_hidden']) ? trim($_POST['gcash_transaction_id_hidden']) : null);

// Debug: Log what we received
error_log("Payment method: " . $payment_method);
error_log("GCash transaction ID received: " . ($gcash_transaction_id ?: 'NULL'));
error_log("POST data: " . print_r($_POST, true));

// Double-check that GCash payments have file proof and transaction ID
if ($payment_method === 'GCash') {
    if ($payment_proof === null) {
        $_SESSION['upload_error'] = "Payment proof is required for GCash transactions.";
        header('Location: checkout.php');
        exit;
    }
    if (empty($gcash_transaction_id)) {
        $_SESSION['upload_error'] = "GCash transaction ID is required for GCash payments.";
        header('Location: checkout.php');
        exit;
    }
    // Validate transaction ID format (should be exactly 13 digits)
    if (!preg_match('/^[0-9]{13}$/', $gcash_transaction_id)) {
        $_SESSION['upload_error'] = "GCash transaction ID must be exactly 13 digits.";
        header('Location: checkout.php');
        exit;
    }
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

// Process address selection and get the address_id
$address_id = null;
error_log("Place Order: Starting address_id resolution");
error_log("Place Order: Session data: " . print_r($_SESSION, true));
error_log("Place Order: POST data: " . print_r($_POST, true));

// Handle delivery address and customer information based on selection
if (isset($_POST['delivery_option']) && $_POST['delivery_option'] === 'delivery') {
    error_log("Place Order: Processing delivery option");
    error_log("Place Order: Address option selected: " . ($_POST['address_option'] ?? 'none'));
    
    if (isset($_POST['address_option']) && $_POST['address_option'] === 'new') {
        // Use new delivery address and customer info
        $delivery_address = $_POST['delivery_address'];
        $delivery_city = $_POST['delivery_city'];
        $delivery_postal_code = $_POST['delivery_postal_code'];
        $delivery_instructions = $_POST['delivery_instructions'] ?? '';
        
        // Insert new address into database
        error_log("Place Order: Creating new address - Address: $delivery_address, City: $delivery_city, Postal: $delivery_postal_code");
        $sql = "INSERT INTO addresses (user_id, address_line, address_line2, city, state, postal_code, country, is_default) 
                VALUES (:user_id, :address_line, :address_line2, :city, :state, :postal_code, :country, 0)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':address_line' => $delivery_address,
            ':address_line2' => '', // New addresses don't have address_line2
            ':city' => $delivery_city,
            ':state' => '', // New addresses don't have state
            ':postal_code' => $delivery_postal_code,
            ':country' => 'Philippines'
        ]);
        $address_id = $pdo->lastInsertId();
        error_log("Place Order: New address created with ID: $address_id");
        
    } elseif (isset($_POST['address_option']) && strpos($_POST['address_option'], 'saved_') === 0) {
        // Use selected saved address
        $address_id = str_replace('saved_', '', $_POST['address_option']);
        error_log("Place Order: Using saved address_id: " . $address_id);
        
    } else {
        // Use default address if available, otherwise use first available address
        $stmt = $pdo->prepare("SELECT address_id FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, date_created DESC LIMIT 1");
        $stmt->execute(['user_id' => $user_id]);
        $address_id = $stmt->fetchColumn();
        error_log("Place Order: Using fallback address_id (default or first available): " . ($address_id ?: 'NULL'));
    }
} else {
    // For pickup orders, we still need an address_id (can be any address)
    $stmt = $pdo->prepare("SELECT address_id FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, date_created DESC LIMIT 1");
    $stmt->execute(['user_id' => $user_id]);
    $address_id = $stmt->fetchColumn();
    error_log("Place Order: Pickup order, using fallback address_id: " . ($address_id ?: 'NULL'));
}

// Debug: Log the final address_id being used
error_log("Final address_id for order: " . ($address_id ?: 'NULL'));

// Safety check: Ensure address_id is never NULL
if (!$address_id) {
    error_log("ERROR: No address_id found! User has addresses but fallback failed.");
    $_SESSION['error'] = "No delivery address found. Please add an address before placing an order.";
    header('Location: checkout.php');
    exit;
}

// Insert order into the orders table using normalized structure
$sql = "INSERT INTO orders (user_id, orderstatus_id, total_price, delivery_option, address_id) 
        VALUES (:user_id, 1, :total_price, :delivery_option, :address_id)";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':total_price', $total_price);
$stmt->bindParam(':delivery_option', $delivery_option);
$stmt->bindParam(':address_id', $address_id);
error_log("Inserting order with address_id: " . ($address_id ?: 'NULL'));
error_log("Order data - user_id: $user_id, total_price: $total_price, delivery_option: $delivery_option, address_id: " . ($address_id ?: 'NULL'));
$stmt->execute();

$order_id = $pdo->lastInsertId();

// Insert payment information into payments table
if ($payment_method && $payment_method !== 'COD') {
    $sql = "INSERT INTO payments (orders_id, amount, method, proof, transaction_id, paymentstatus_id) 
            VALUES (:order_id, :amount, :method, :proof, :transaction_id, 1)";
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':amount', $total_price);
    $stmt->bindParam(':method', $payment_method);
    $stmt->bindParam(':proof', $payment_proof);
    $stmt->bindParam(':transaction_id', $gcash_transaction_id);
    
    // Debug: Log what we're inserting
    error_log("Inserting payment with transaction_id: " . ($gcash_transaction_id ?: 'NULL'));
    
    $stmt->execute();
    
    // Debug: Check if insertion was successful
    if ($stmt->rowCount() > 0) {
        error_log("Payment record inserted successfully");
    } else {
        error_log("Payment record insertion failed");
    }
}

// Start transaction for atomic stock management
$pdo->beginTransaction();

try {
    // Validate stock availability with row locking to prevent race conditions
    foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $quantity = floatval($cart_item['quantity'] ?? 1);
        
        // Check current stock availability with row locking
        $sql = "SELECT current_stock FROM product_stock WHERE product_id = :product_id FOR UPDATE";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->execute();
        $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$stock_data || floatval($stock_data['current_stock']) < $quantity) {
            $pdo->rollBack();
            $_SESSION['error'] = "Insufficient stock for one or more products. Please update your cart and try again.";
            header('Location: checkout.php');
            exit;
        }
    }

// Initialize batch manager
$batchManager = new BatchManager($pdo);

    // Insert order items into the order_items table and consume from batches
    foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $quantity = floatval($cart_item['quantity'] ?? 1);
        $unit_price = floatval($cart_item['unit_price'] ?? 0);

        // Step 1: Check if the product exists and get its price
        if ($unit_price == 0) {
            $stmt = $pdo->prepare("SELECT p.product_id, COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as price FROM products p 
                                   LEFT JOIN product_pricing pp ON p.product_id = pp.product_id 
                                   WHERE p.product_id = :product_id AND p.is_archive = 0");
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($product) {
                $unit_price = floatval($product['price']);
            }
        }

        // Step 2: If the product exists, insert it into the order_items table
        if ($unit_price > 0) {
            // Insert order items with price
            $sql = "INSERT INTO order_items (order_id, product_id, quantity, price) VALUES (:order_id, :product_id, :quantity, :price)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':price', $unit_price);
            $stmt->execute();

            // Consume stock from batches using FIFO
            $batches_used = $batchManager->consumeStock(
                $product_id, 
                $quantity, 
                'sale', 
                'order', 
                $order_id, 
                $_SESSION['user_id'], 
                "Order #{$order_id} - Customer purchase"
            );
            
            // Update product stock in normalized structure
            $sql = "UPDATE product_stock SET current_stock = current_stock - :quantity WHERE product_id = :product_id";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            
            // Verify stock update was successful
            if ($stmt->rowCount() == 0) {
                throw new Exception("Failed to update stock for product ID: {$product_id}");
            }
        }
    }

    // Clear the cart
    unset($_SESSION['cart']);
    $_SESSION['cart'] = [];
    
    // Record discount code usage if a discount was applied
    if (isset($_SESSION['discount_code_id']) && isset($_SESSION['discount']) && $_SESSION['discount'] > 0) {
        $discount_code_id = $_SESSION['discount_code_id'];
        $discount_amount = $_SESSION['discount'];
        
        $stmt = $pdo->prepare("INSERT INTO discount_code_usage (discount_code_id, user_id, order_id, discount_amount) VALUES (?, ?, ?, ?)");
        $stmt->execute([$discount_code_id, $user_id, $order_id, $discount_amount]);
        
        // Clear discount session variables
        unset($_SESSION['discount']);
        unset($_SESSION['discount_code']);
        unset($_SESSION['discount_code_id']);
    }
    
    // Commit the transaction
    $pdo->commit();
    
} catch (Exception $e) {
    // Rollback transaction on any error
    $pdo->rollBack();
    $_SESSION['error'] = "Order failed: " . $e->getMessage();
    header('Location: checkout.php');
    exit;
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Placed</title>
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        }
        
        /* Custom SweetAlert2 styling */
        .swal2-popup {
            border-radius: 20px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
        }
        
        .swal2-title {
            color: var(--bs-secondary) !important;
            font-weight: 700 !important;
            font-size: 1.8rem !important;
        }
        
        .swal2-html-container {
            color: var(--bs-dark) !important;
            font-size: 1rem !important;
        }
        
        .swal2-confirm {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3) !important;
            background: linear-gradient(135deg, #6b1429 0%, #8b1a36 100%) !important;
        }
        
        .swal2-cancel {
            border: 2px solid var(--bs-secondary) !important;
            color: var(--bs-secondary) !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            background: transparent !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-cancel:hover {
            background: var(--bs-secondary) !important;
            color: white !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.2) !important;
        }
        
        .order-details {
            background: var(--bs-light);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid #e9ecef;
            text-align: left;
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
        
        .text-success {
            color: var(--bs-success) !important;
        }
    </style>
</head>
<body>
    <!-- SweetAlert2 JS -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Create order details HTML
            let orderDetailsHtml = `
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
            `;

            // Show SweetAlert2
            Swal.fire({
                icon: 'success',
                title: 'Order Placed Successfully!',
                html: `
                    <p style="margin-bottom: 1rem;">Your order has been placed and is now pending. Thank you for shopping with us!</p>
                    ${orderDetailsHtml}
                `,
                showCancelButton: true,
                confirmButtonText: '<i class="fas fa-list me-2"></i>View Orders',
                cancelButtonText: '<i class="fas fa-shopping-bag me-2"></i>Continue Shopping',
                confirmButtonColor: '#7F1734',
                cancelButtonColor: '#7F1734',
                reverseButtons: true,
                allowOutsideClick: false,
                allowEscapeKey: false,
                customClass: {
                    popup: 'swal2-popup',
                    title: 'swal2-title',
                    htmlContainer: 'swal2-html-container',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // User clicked "View Orders"
                    window.location.href = 'orders.php';
                } else if (result.dismiss === Swal.DismissReason.cancel) {
                    // User clicked "Continue Shopping"
                    window.location.href = 'product.php';
                }
            });
        });
    </script>
</body>
</html>
