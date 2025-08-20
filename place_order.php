<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id']) || empty($_SESSION['cart'])) {
    header('Location: login.php');
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
        // Get product price from database
        $stmt = $pdo->prepare("SELECT price FROM products WHERE id = :product_id");
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

// Insert order into the orders table
$sql = "INSERT INTO orders (user_id, total_price, status, delivery_option, payment_method, payment_proof) 
        VALUES (:user_id, :total_price, 'Pending', :delivery_option, :payment_method, :payment_proof)";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':total_price', $total_price);
$stmt->bindParam(':delivery_option', $delivery_option);
$stmt->bindParam(':payment_method', $payment_method);
$stmt->bindParam(':payment_proof', $payment_proof);
$stmt->execute();

$order_id = $pdo->lastInsertId();

// Insert order items into the order_items table and update product stock
foreach ($_SESSION['cart'] as $product_id => $cart_item) {
    $quantity = $cart_item['quantity'];

    // Step 1: Check if the product exists in the products table
    $stmt = $pdo->prepare("SELECT id FROM products WHERE id = :product_id");
    $stmt->bindParam(':product_id', $product_id);
    $stmt->execute();

    // Step 2: If the product exists, insert it into the order_items table
    if ($stmt->rowCount() > 0) {
        // Insert order items
        $sql = "INSERT INTO order_items (order_id, product_id, quantity) VALUES (:order_id, :product_id, :quantity)";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':order_id', $order_id);
        $stmt->bindParam(':product_id', $product_id);
        $stmt->bindParam(':quantity', $quantity);
        $stmt->execute();

        // Update product stock
        $sql = "UPDATE products SET stock = stock - :quantity WHERE id = :product_id AND stock >= :quantity";
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
    <link href="assets/css/tailwind.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.css" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gradient-to-br from-white via-blue-50 to-purple-100 text-black min-h-screen">
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            Swal.fire({
                title: 'Order Placed Successfully!',
                html: 'Your order for ₱<?= number_format($total_price, 2) ?> has been placed and is now pending.<br><br>' + 
                      '<?= ($shipping_fee > 0) ? "Shipping: ₱" . number_format($shipping_fee, 2) . "<br>" : "" ?>' +
                      'Payment method: <?= $payment_method ?><br>' +
                      'Delivery option: <?= ucfirst($delivery_option) ?><br><br>' +
                      'Thank you for shopping with us!',
                icon: 'success',
                confirmButtonText: 'View Orders',
                confirmButtonColor: '#ec4899',
                background: '#fdf4ff',
                backdrop: `rgba(0, 0, 0, 0.4)`
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'orders.php';
                } else {
                    window.location.href = 'product.php';
                }
            });
        });
    </script>
</body>
</html>
