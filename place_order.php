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

// If total_price is 0 or empty, calculate from selected items as fallback
if ($total_price <= 0) {
    error_log("Calculating total price from selected items as fallback");
    
    // Get selected cart items from POST data first
    $selected_items = [];
    if (isset($_POST['selected_items'])) {
        $selected_items = json_decode($_POST['selected_items'], true) ?? [];
    }
    
    $total_price = 0;
    
    if (!empty($selected_items)) {
        // Calculate price only for selected items
        foreach ($selected_items as $cart_key) {
            if (isset($_SESSION['cart'][$cart_key])) {
                $cart_item = $_SESSION['cart'][$cart_key];
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
                
                $item_total = $unit_price * $quantity;
                $total_price += $item_total;
                error_log("Selected item $cart_key: quantity=$quantity, unit_price=$unit_price, item_total=$item_total");
            }
        }
        error_log("Total calculated from selected items: $total_price");
    } else {
        // Fallback: calculate from all items (backward compatibility)
        error_log("No selected items - calculating from all cart items (backward compatibility)");
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
    // Validate transaction ID format (should be 1 to 13 digits)
    if (!preg_match('/^[0-9]{1,13}$/', $gcash_transaction_id)) {
        $_SESSION['upload_error'] = "GCash transaction ID must be 1 to 13 digits.";
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

// Initialize batch manager
$batchManager = new BatchManager($pdo);

// Get selected cart items from POST data
$selected_items = [];
if (isset($_POST['selected_items'])) {
    $selected_items = json_decode($_POST['selected_items'], true) ?? [];
    error_log("Selected items from POST: " . json_encode($selected_items));
} else {
    error_log("No selected_items in POST data - processing all items");
}

// Load cart data from database using CartManager (same as checkout.php)
include_once 'includes/cart_manager.php';
$cartManager = new CartManager($pdo);
$all_cart_data = $cartManager->loadCartFromDatabase($_SESSION['user_id']);

// Filter cart data to only include selected items
$cart_data = [];
if (!empty($selected_items)) {
    // Only process selected items
    foreach ($selected_items as $cart_key) {
        if (isset($all_cart_data[$cart_key])) {
            $cart_data[$cart_key] = $all_cart_data[$cart_key];
        }
    }
    error_log("Filtered cart data for selected items: " . json_encode($cart_data));
} else {
    // Fallback: process all items (backward compatibility)
    $cart_data = $all_cart_data;
    error_log("No selection data - processing all items (backward compatibility)");
}

// Debug: Log cart data
error_log("Cart data to process: " . json_encode($cart_data));
error_log("Cart data count: " . count($cart_data));

// Debug: Log each cart item structure
foreach ($cart_data as $cart_key => $cart_item) {
    error_log("Cart item $cart_key: " . json_encode($cart_item));
}

// Start transaction for atomic stock management
$pdo->beginTransaction();

try {
    // Validate stock availability with row locking to prevent race conditions
    foreach ($cart_data as $cart_key => $cart_item) {
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $quantity = floatval($cart_item['quantity'] ?? 1);
        $brand_id = $cart_item['brand_id'] ?? null;
        if ($brand_id !== null) {
            $brand_id = intval($brand_id);
        }
        
        error_log("STOCK VALIDATION - Product: $product_id, Brand: $brand_id, Quantity: $quantity");
        
        // Check current stock availability with brand-specific logic
        if ($brand_id) {
            // Check brand-specific stock
            $sql = "SELECT COALESCE(SUM(quantity_remaining), 0) as stock FROM product_batches WHERE product_id = :product_id AND brand_id = :brand_id AND is_active = 1 FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':brand_id', $brand_id);
            $stmt->execute();
            $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $available_stock = floatval($stock_data['stock'] ?? 0);
            error_log("Brand-specific stock check - Available: $available_stock");
        } else {
            // Check general product stock
            $sql = "SELECT current_stock FROM product_stock WHERE product_id = :product_id FOR UPDATE";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->execute();
            $stock_data = $stmt->fetch(PDO::FETCH_ASSOC);
            $available_stock = floatval($stock_data['current_stock'] ?? 0);
            error_log("General stock check - Available: $available_stock");
        }
        
        if ($available_stock < $quantity) {
            $pdo->rollBack();
            $_SESSION['error'] = "Insufficient stock for one or more products. Please update your cart and try again.";
            header('Location: checkout.php');
            exit;
        }
    }

    // Insert order items into the order_items table and consume from batches
    foreach ($cart_data as $cart_key => $cart_item) {
        // Handle both simple product_id keys and composite keys (product_id_unit_boxid)
        $product_id = $cart_item['product_id'] ?? $cart_key;
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        
        $quantity = floatval($cart_item['quantity'] ?? 1);
        $unit_price = floatval($cart_item['unit_price'] ?? 0);
        $brand_id = $cart_item['brand_id'] ?? null;
        $batch_id = $cart_item['batch_id'] ?? null;

        error_log("ORDER PROCESSING - Product: $product_id, Brand: $brand_id, Batch: $batch_id, Quantity: $quantity, Unit Price: $unit_price");

        // Step 1: Calculate price using the same logic as checkout.php
        if ($unit_price == 0) {
            // First try brand-specific pricing
            if ($brand_id) {
                $price_sql = "SELECT 
                    (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price
                FROM products p
                LEFT JOIN product_batches pb ON p.product_id = pb.product_id 
                    AND pb.brand_id = ?
                    AND pb.quantity_remaining > 0 
                    AND pb.is_active = 1
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                WHERE p.product_id = ? AND p.is_archive = 0
                ORDER BY pb.received_date DESC, pb.batch_id DESC
                LIMIT 1";
                
                $stmt = $pdo->prepare($price_sql);
                $stmt->execute([$brand_id, $product_id]);
                $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product_data && $product_data['final_price'] > 0) {
                    $unit_price = floatval($product_data['final_price']);
                }
            }
            
            // If no brand-specific price found, try general pricing
            if ($unit_price == 0) {
                $general_sql = "SELECT 
                    (COALESCE(pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as final_price
                FROM products p
                LEFT JOIN product_batches pb ON p.product_id = pb.product_id 
                    AND pb.quantity_remaining > 0 
                    AND pb.is_active = 1
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                WHERE p.product_id = ? AND p.is_archive = 0
                ORDER BY pb.received_date DESC, pb.batch_id DESC
                LIMIT 1";
                
                $stmt = $pdo->prepare($general_sql);
                $stmt->execute([$product_id]);
                $product_data = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($product_data && $product_data['final_price'] > 0) {
                    $unit_price = floatval($product_data['final_price']);
                }
            }
        }

        // Step 2: If the product exists, insert it into the order_items table
        error_log("ORDER ITEM INSERTION - Product: $product_id, Unit Price: $unit_price, Brand ID: $brand_id, Batch ID: $batch_id, Quantity: $quantity");
        
        if ($unit_price > 0) {
            // Extract brand_id and batch_id (ensure they're integers)
            if ($brand_id !== null) {
                $brand_id = intval($brand_id);
            }
            if ($batch_id !== null) {
                $batch_id = intval($batch_id);
            }
            
            // Insert order items with price, brand, and batch information
            $sql = "INSERT INTO order_items (order_id, product_id, brand_id, batch_id, quantity, price) VALUES (:order_id, :product_id, :brand_id, :batch_id, :quantity, :price)";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':order_id', $order_id);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':brand_id', $brand_id);
            $stmt->bindParam(':batch_id', $batch_id);
            $stmt->bindParam(':quantity', $quantity);
            $stmt->bindParam(':price', $unit_price);
            $stmt->execute();
            error_log("ORDER ITEM INSERTED SUCCESSFULLY - Product: $product_id, Order: $order_id");

            // Consume stock from batches using FIFO with brand-specific logic
            error_log("Calling consumeStock with: product_id=$product_id, quantity=$quantity, brand_id=$brand_id");
            $batches_used = $batchManager->consumeStock(
                $product_id, 
                $quantity, 
                'sale', 
                'order', 
                $order_id, 
                $_SESSION['user_id'], 
                "Order #{$order_id} - Customer purchase",
                null,      // $supplier_id parameter (position 8)
                $brand_id  // $brand_id parameter (position 9)
            );
            
            // Debug: Log batch consumption
            error_log("Stock consumption - Product: $product_id, Brand: $brand_id, Quantity: $quantity");
            error_log("Batches used: " . json_encode($batches_used));
            
            // Debug: Check if batches were actually consumed
            if (empty($batches_used)) {
                error_log("WARNING: No batches were consumed for product $product_id with brand $brand_id");
            } else {
                error_log("SUCCESS: " . count($batches_used) . " batches consumed for product $product_id");
            }
            
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

    // Clear only selected items from the cart
    $selected_items = [];
    if (isset($_POST['selected_items'])) {
        $selected_items = json_decode($_POST['selected_items'], true) ?? [];
    }
    
    if (!empty($selected_items)) {
        // Remove only selected items from cart
        foreach ($selected_items as $cart_key) {
            if (isset($_SESSION['cart'][$cart_key])) {
                unset($_SESSION['cart'][$cart_key]);
            }
        }
    } else {
        // Fallback: clear entire cart (backward compatibility)
        unset($_SESSION['cart']);
        $_SESSION['cart'] = [];
    }
    
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
    error_log("Order processing completed successfully. Order ID: $order_id");
    
    // Clear only selected items from database cart (after transaction is committed)
    if (!empty($selected_items)) {
        // Remove only selected items from database cart using proper database columns
        try {
            $cart_stmt = $pdo->prepare("SELECT cart_id FROM cart WHERE user_id = ? AND is_active = 1");
            $cart_stmt->execute([$_SESSION['user_id']]);
            $cart_id = $cart_stmt->fetchColumn();
            
            if ($cart_id) {
                foreach ($selected_items as $cart_key) {
                    // Parse cart_key to extract product_id, brand_id, batch_id, and unit
                    // Format: product_id_unit[_brand_brandId][_batch_batchId]
                    $parts = explode('_', $cart_key);
                    $product_id = intval($parts[0]);
                    $unit = $parts[1] ?? 'kilo';
                    $brand_id = null;
                    $batch_id = null;
                    
                    // Parse brand_id and batch_id from cart_key
                    for ($i = 2; $i < count($parts); $i += 2) {
                        if (isset($parts[$i]) && isset($parts[$i + 1])) {
                            if ($parts[$i] === 'brand') {
                                $brand_id = intval($parts[$i + 1]);
                            } elseif ($parts[$i] === 'batch') {
                                $batch_id = intval($parts[$i + 1]);
                            }
                        }
                    }
                    
                    // Build WHERE clause based on parsed values
                    $where_conditions = ["cart_id = ?", "user_id = ?", "product_id = ?", "unit = ?"];
                    $params = [$cart_id, $_SESSION['user_id'], $product_id, $unit];
                    
                    if ($brand_id !== null) {
                        $where_conditions[] = "brand_id = ?";
                        $params[] = $brand_id;
                    } else {
                        $where_conditions[] = "brand_id IS NULL";
                    }
                    
                    if ($batch_id !== null) {
                        $where_conditions[] = "batch_id = ?";
                        $params[] = $batch_id;
                    } else {
                        $where_conditions[] = "batch_id IS NULL";
                    }
                    
                    $remove_stmt = $pdo->prepare("DELETE FROM cart_items WHERE " . implode(" AND ", $where_conditions));
                    $remove_stmt->execute($params);
                    
                    error_log("Removed cart item from database: $cart_key (product_id=$product_id, brand_id=" . ($brand_id ?: 'NULL') . ", batch_id=" . ($batch_id ?: 'NULL') . ", unit=$unit)");
                }
                error_log("Removed selected items from database cart: " . json_encode($selected_items));
            }
        } catch (Exception $e) {
            error_log("Error removing selected items from database cart: " . $e->getMessage());
        }
    } else {
        // Fallback: clear entire database cart (backward compatibility)
        $cartManager->clearCartFromDatabase($_SESSION['user_id']);
        error_log("Cleared entire database cart (backward compatibility)");
    }
    
} catch (Exception $e) {
    // Rollback transaction on any error (only if transaction is active)
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log("Order processing error: " . $e->getMessage());
    error_log("Order processing error trace: " . $e->getTraceAsString());
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
    <title>Order Placed - MikeMadz</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <?php include 'includes/user_head.php'; ?>
    
    <!-- SweetAlert2 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
    <style>
        body {
            background: linear-gradient(135deg, var(--bg-secondary) 0%, var(--bg-primary) 100%);
            min-height: 100vh;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            color: var(--text-primary);
        }
        
        /* Custom SweetAlert2 styling */
        .swal2-popup {
            border-radius: 20px !important;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
            background: var(--bg-card) !important;
            border: 1px solid var(--border-light) !important;
        }
        
        .swal2-title {
            color: var(--brand-primary) !important;
            font-weight: 700 !important;
            font-size: 1.8rem !important;
        }
        
        .swal2-html-container {
            color: var(--text-primary) !important;
            font-size: 1rem !important;
        }
        
        .swal2-confirm {
            background: var(--brand-gradient) !important;
            border: none !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-confirm:hover {
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px var(--shadow-medium) !important;
            background: linear-gradient(135deg, #6d1429 0%, #8f1937 100%) !important;
        }
        
        .swal2-cancel {
            border: 2px solid var(--brand-primary) !important;
            color: var(--brand-primary) !important;
            border-radius: 10px !important;
            padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
            background: transparent !important;
            font-size: 1rem !important;
            transition: all 0.3s ease !important;
        }
        
        .swal2-cancel:hover {
            background: var(--brand-primary) !important;
            color: var(--text-light) !important;
            transform: translateY(-2px) !important;
            box-shadow: 0 8px 25px var(--shadow-medium) !important;
        }
        
        .order-details {
            background: var(--bg-tertiary);
            border-radius: 15px;
            padding: 1.5rem;
            margin: 1rem 0;
            border: 1px solid var(--border-light);
            text-align: left;
        }
        
        .detail-row {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.75rem;
            padding: 0.75rem 0;
            border-bottom: 1px solid var(--border-light);
            align-items: center;
            color: var(--text-primary);
        }
        
        .detail-row:last-child {
            border-bottom: none;
            font-weight: bold;
            font-size: 1.2rem;
            color: var(--brand-primary);
            background: var(--bg-card);
            padding: 1rem;
            border-radius: 10px;
            margin-top: 0.5rem;
            border: 1px solid var(--border-light);
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
                confirmButtonText: '<i class="fas fa-list" style="margin-right: 12px;"></i>View Orders',
                cancelButtonText: '<i class="fas fa-shopping-bag" style="margin-right: 12px;"></i>Continue Shopping',
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
