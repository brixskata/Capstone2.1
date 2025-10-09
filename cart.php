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

// Force clear any old cart data with incorrect pricing
// This ensures we always use fresh database prices
foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
    if (isset($cart_item['unit_price']) && $cart_item['unit_price'] < 50) {
        // If unit_price is suspiciously low (likely just markup), remove it
        unset($_SESSION['cart'][$cart_key]['unit_price']);
    }
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
                    COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) AS price,
                    COALESCE(ps.current_stock, 0) AS stock,
                    (SELECT pi.image_url FROM product_images pi 
                     WHERE pi.product_id = p.product_id AND pi.is_primary = 1 
                     ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
                FROM products p
                LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                LEFT JOIN product_stock ps ON p.product_id = ps.product_id
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
        // Extract actual product IDs from cart keys (handle composite keys like "11_kilo")
        $product_ids = [];
        foreach ($cart as $cart_key => $cart_item) {
            $product_id = $cart_item['product_id'] ?? $cart_key;
            if (is_string($product_id) && strpos($product_id, '_') !== false) {
                $product_id = intval(explode('_', $product_id)[0]);
            }
            $product_ids[] = intval($product_id);
        }
        $product_ids = array_unique($product_ids);
        $products = getProductData($pdo, $product_ids);
        
        $total = 0;
        $quantity = 0;
        $items = [];
        
        foreach ($cart as $cart_key => $cart_item) {
            $product_id = $cart_item['product_id'] ?? $cart_key;
            if (isset($products[$product_id])) {
                $product = $products[$product_id];
                $qty = $cart_item['quantity'] ?? 1;
                $unit_price = $product['price']; // Always use fresh database price
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

    $action = $_POST['action'] ?? $_GET['action'] ?? '';
    $product_id = intval($_POST['product_id'] ?? 0);
    $cart_key = $_POST['cart_key'] ?? null;

    // Handle get_cart_data action (no product_id required)
    if ($action === 'get_cart_data') {
        $cart_data = calculateCartTotals($pdo, $_SESSION['cart']);
        echo json_encode([
            'success' => true,
            'cart_total' => $cart_data['total'],
            'cart_qty' => $cart_data['quantity'],
            'cart_count' => count($_SESSION['cart'])
        ]);
        exit;
    }

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
                $quantity = floatval($_POST['quantity'] ?? 1);
                $unit = $_POST['unit'] ?? 'kilo';
                $unit_price = $product['price']; // Always use complete database price (selling_price + cost_per_unit)
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
                // Use cart_key if provided, otherwise find by product_id
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                    $new_quantity = $_SESSION['cart'][$cart_key]['quantity'] - 0.1;
                    if ($new_quantity < 1) {
                        echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1']);
                        exit;
                    }
                    $_SESSION['cart'][$cart_key]['quantity'] = $new_quantity;
                } else {
                    // Find and decrease the cart item by product_id
                    $found = false;
                    foreach ($_SESSION['cart'] as $key => $cart_item) {
                        $item_product_id = $cart_item['product_id'] ?? $key;
                        // Handle both simple product_id and composite keys
                        if (is_numeric($item_product_id)) {
                            $item_product_id = intval($item_product_id);
                        } else {
                            // Extract product_id from composite key (e.g., "123_kilo" -> 123)
                            $item_product_id = intval(explode('_', $item_product_id)[0]);
                        }
                        
                        if ($item_product_id == $product_id) {
                            $new_quantity = $_SESSION['cart'][$key]['quantity'] - 0.1;
                            if ($new_quantity < 1) {
                                echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1']);
                                exit;
                            }
                            $_SESSION['cart'][$key]['quantity'] = $new_quantity;
                            $found = true;
                            break;
                        }
                    }
                    
                    // If not found, try to find by simple product_id key
                    if (!$found && isset($_SESSION['cart'][$product_id])) {
                        $new_quantity = $_SESSION['cart'][$product_id]['quantity'] - 0.1;
                        if ($new_quantity < 1) {
                            echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1']);
                            exit;
                        }
                        $_SESSION['cart'][$product_id]['quantity'] = $new_quantity;
                    }
                }
                break;

            case 'delete':
                // Use cart_key if provided, otherwise find by product_id
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                    unset($_SESSION['cart'][$cart_key]);
                } else {
                    // Find and remove the cart item by product_id
                    foreach ($_SESSION['cart'] as $key => $cart_item) {
                        $item_product_id = $cart_item['product_id'] ?? $key;
                        // Handle both simple product_id and composite keys
                        if (is_numeric($item_product_id)) {
                            $item_product_id = intval($item_product_id);
                        } else {
                            // Extract product_id from composite key (e.g., "123_kilo" -> 123)
                            $item_product_id = intval(explode('_', $item_product_id)[0]);
                        }
                        
                        if ($item_product_id == $product_id) {
                            unset($_SESSION['cart'][$key]);
                            break;
                        }
                    }
                }
                break;

            case 'update':
                $quantity = max(0.1, floatval($_POST['quantity'] ?? 0.1));
                
                // Check stock availability
                if ($quantity > $product['stock']) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                    exit;
                }
                
                if ($quantity >= 0.1) {
                    $_SESSION['cart'][$product_id] = ['quantity' => $quantity];
                } else {
                    unset($_SESSION['cart'][$product_id]);
                }
                break;

            case 'set_quantity':
                $quantity = max(1, floatval($_POST['quantity'] ?? 1));
                
                // Get product data to check stock availability
                $product_data = getProductData($pdo, [$product_id]);
                if (empty($product_data[$product_id])) {
                    echo json_encode(['success' => false, 'message' => 'Product not found']);
                    exit;
                }
                
                $product = $product_data[$product_id];
                
                // Check stock availability
                if ($quantity > $product['stock']) {
                    echo json_encode(['success' => false, 'message' => 'Insufficient stock']);
                    exit;
                }
                
                // Use cart_key if provided, otherwise find by product_id
                if ($cart_key && isset($_SESSION['cart'][$cart_key])) {
                    if ($quantity >= 1) {
                        $_SESSION['cart'][$cart_key]['quantity'] = $quantity;
                    } else {
                        echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1']);
                        exit;
                    }
                } else {
                    // Find and update the cart item by product_id
                    $found = false;
                    foreach ($_SESSION['cart'] as $key => $cart_item) {
                        $item_product_id = $cart_item['product_id'] ?? $key;
                        // Handle both simple product_id and composite keys
                        if (is_numeric($item_product_id)) {
                            $item_product_id = intval($item_product_id);
                        } else {
                            // Extract product_id from composite key (e.g., "123_kilo" -> 123)
                            $item_product_id = intval(explode('_', $item_product_id)[0]);
                        }
                        
                        if ($item_product_id == $product_id) {
                            if ($quantity >= 1) {
                                $_SESSION['cart'][$key]['quantity'] = $quantity;
                            } else {
                                echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1']);
                                exit;
                            }
                            $found = true;
                            break;
                        }
                    }
                    
                    // If not found, try to find by simple product_id key
                    if (!$found && isset($_SESSION['cart'][$product_id])) {
                        if ($quantity >= 1) {
                            $_SESSION['cart'][$product_id]['quantity'] = $quantity;
                        } else {
                            echo json_encode(['success' => false, 'message' => 'Minimum quantity is 1']);
                            exit;
                        }
                    }
                }
                break;
        }

        // Calculate updated totals using the same method as display
        $cart_data = calculateCartTotals($pdo, $_SESSION['cart']);
        $cart_items = $cart_data['items'] ?? [];
        
        // Calculate correct cart total using fresh prices (same as display logic)
        $cart_total = 0;
        $item_total = 0;
        $item_quantity = 0;
        
        foreach ($cart_items as $item) {
            $product = $item['product'] ?? $item;
            $quantity = $item['quantity'] ?? 0;
            
            // Get fresh price from database (same as display logic)
            if (isset($product['id'])) {
                $product_data = getProductData($pdo, [intval($product['id'])]);
                if (!empty($product_data[intval($product['id'])])) {
                    $correct_price = $product_data[intval($product['id'])]['price'];
                    $item_total_price = $correct_price * $quantity;
                    $cart_total += $item_total_price;
                    
                    // Check if this is the item we're looking for
                    if ($product['id'] == $product_id) {
                        $item_total = $item_total_price;
                        $item_quantity = $quantity;
                    }
                }
            }
        }
        
        // If no items found in calculateCartTotals, calculate manually
        if (empty($cart_items) && !empty($_SESSION['cart'])) {
            $cart_total = 0;
            $total_quantity = 0;
            
            foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
                $pid = $cart_item['product_id'] ?? $cart_key;
                $product_data = getProductData($pdo, [$pid]);
                if (!empty($product_data[$pid])) {
                    $product = $product_data[$pid];
                    $qty = $cart_item['quantity'] ?? 1;
                    $unit_price = $product['price'];
                    $item_total_price = $unit_price * $qty;
                    
                    $cart_total += $item_total_price;
                    $total_quantity += $qty;
                    
                    // Check if this is the item we're looking for
                    if ($pid == $product_id || (is_string($cart_key) && strpos($cart_key, $product_id . '_') === 0)) {
                        $item_total = $item_total_price;
                        $item_quantity = $qty;
                    }
                }
            }
        }
        
        echo json_encode([
            'success' => true,
            'cart_total' => $cart_total,
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

// Calculate correct cart total using fresh prices
$cart_total = 0;
foreach ($cart_items as $item) {
    $product = $item['product'] ?? $item;
    $quantity = $item['quantity'] ?? 0;
    
    // Get fresh price from database (same as display logic)
    if (isset($product['id'])) {
        $product_data = getProductData($pdo, [intval($product['id'])]);
        if (!empty($product_data[intval($product['id'])])) {
            $correct_price = $product_data[intval($product['id'])]['price'];
            $cart_total += $correct_price * $quantity;
        }
    }
}

// If calculateCartTotals returns empty items but we have cart data, use the session data directly
if (empty($cart_items) && !empty($_SESSION['cart'])) {
    $cart_items = [];
    $cart_total = 0;
    $cart_quantity = 0;
    
    foreach ($_SESSION['cart'] as $cart_key => $cart_item) {
        $product_id = $cart_item['product_id'] ?? $cart_key;
        // Handle composite keys like "11_kilo"
        if (is_string($product_id) && strpos($product_id, '_') !== false) {
            $product_id = intval(explode('_', $product_id)[0]);
        }
        $product_data = getProductData($pdo, [intval($product_id)]);
        if (!empty($product_data[intval($product_id)])) {
            $product = $product_data[intval($product_id)];
            $qty = $cart_item['quantity'] ?? 1;
            $unit_price = $product['price']; // Always use fresh database price
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
                   COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as price,
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

<?php
$page_title = 'Shopping Cart - MikeMadz';
$page_description = 'Review your selected items and proceed to checkout. Fresh meat and seafood delivered to your doorstep.';
$page_keywords = 'shopping cart, checkout, meat delivery, seafood delivery, MikeMadz';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/user_head.php'; ?>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <meta name="cache-buster" content="<?= time() ?>">
    <title><?= htmlspecialchars($page_title) ?></title>
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    
    <style>
        /* Cart Alignment Fix - Updated: <?= date('Y-m-d H:i:s') ?> */
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

        /* Ensure navbar styles are not overridden */
        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e9ecef;
            padding: 1rem 0;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--bs-secondary) !important;
            text-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        /* Fix cart badge positioning to prevent overlap */
        .cart-badge {
            background: linear-gradient(135deg, var(--bs-secondary), var(--bs-danger)) !important;
            color: white !important;
            border-radius: 50% !important;
            padding: 2px 7px !important;
            font-size: 0.7rem !important;
            position: absolute !important;
            top: -8px !important;
            right: -8px !important;
            transform: translate(50%, -50%) !important;
            min-width: 18px !important;
            height: 18px !important;
            display: flex !important;
            align-items: center !important;
            justify-content: center !important;
            font-weight: 600 !important;
            z-index: 10 !important;
            line-height: 1 !important;
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
            padding: 1.5rem 1rem;
            border-bottom: 1px solid #e9ecef;
            background: #f8f9fa;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            gap: 1rem;
            min-height: 80px;
        }

        /* Product Name Column - Takes 40% of width */
        .cart-item .product-info {
            flex: 0 0 40%;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        /* Quantity Column - Takes 15% of width */
        .cart-item .quantity-section {
            flex: 0 0 15%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }

        /* Item Price Column - Takes 15% of width */
        .cart-item .item-price {
            flex: 0 0 15%;
            text-align: center;
            font-weight: 600;
            color: var(--bs-dark);
            font-size: 1rem;
        }

        /* Subtotal Column - Takes 15% of width */
        .cart-item .item-total {
            flex: 0 0 15%;
            text-align: center;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--bs-secondary);
        }

        /* Actions Column - Takes remaining width */
        .cart-item .remove-btn {
            flex: 0 0 auto;
        }

        .cart-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }


        .product-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 0.5rem;
            border: 2px solid #e9ecef;
            flex-shrink: 0;
        }

        .product-details {
            flex: 1;
            min-width: 0;
        }

        .product-name {
            font-weight: 600;
            color: #000000;
            margin-bottom: 0.25rem;
            font-size: 1rem;
        }

        .stock-info {
            font-size: 0.8rem;
            color: #6c757d;
        }


        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .quantity-btn {
            background: var(--bs-light);
            border: 2px solid #e9ecef;
            color: var(--bs-secondary);
            width: 35px;
            height: 35px;
            border-radius: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s;
            font-weight: 600;
            font-size: 0.9rem;
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
            min-width: 50px;
            text-align: center;
            font-weight: 600;
            font-size: 1rem;
            color: #000000;
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.5rem;
            background: white;
            transition: all 0.3s ease;
        }

        .quantity-display:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            outline: none;
        }

        .quantity-display::-webkit-outer-spin-button,
        .quantity-display::-webkit-inner-spin-button {
            -webkit-appearance: none;
            margin: 0;
        }

        .quantity-display[type=number] {
            -moz-appearance: textfield;
            appearance: textfield;
        }


        .remove-btn {
            background: none;
            border: none;
            color: var(--bs-danger);
            padding: 0.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            flex-shrink: 0;
        }

        /* Cart Header */
        .cart-header {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: var(--bs-secondary);
            color: #000000;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            gap: 1rem;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            min-height: 50px;
        }

        .cart-header > div:nth-child(1) {
            flex: 0 0 40%;
            text-align: left;
        }

        .cart-header > div:nth-child(2) {
            flex: 0 0 15%;
            text-align: center;
        }

        .cart-header > div:nth-child(3) {
            flex: 0 0 15%;
            text-align: center;
        }

        .cart-header > div:nth-child(4) {
            flex: 0 0 15%;
            text-align: center;
        }

        .cart-header > div:nth-child(5) {
            flex: 0 0 auto;
            text-align: center;
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
            color: white;
        }

        .checkout-btn:hover:not(:disabled) {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.4);
            color: white;
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

        /* Enhanced AJAX animations */
        .cart-item {
            transition: all 0.3s ease;
        }

        .cart-item.updating {
            transform: scale(0.98);
            opacity: 0.7;
        }

        .cart-item.removing {
            animation: slideOut 0.3s ease forwards;
        }

        @keyframes slideOut {
            to {
                transform: translateX(-100%);
                opacity: 0;
                height: 0;
                margin: 0;
                padding: 0;
            }
        }

        .quantity-btn {
            transition: all 0.2s ease;
        }

        .quantity-btn:active {
            transform: scale(0.95);
        }

        .success-flash {
            animation: successFlash 0.5s ease;
        }

        @keyframes successFlash {
            0% { background-color: #d4edda; }
            100% { background-color: transparent; }
        }

        .error-shake {
            animation: errorShake 0.5s ease;
        }

        @keyframes errorShake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-5px); }
            75% { transform: translateX(5px); }
        }

        .alert-custom {
            border-radius: 0.75rem;
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        @media (max-width: 768px) {
            .cart-header {
                display: none;
            }
            
            .cart-item {
                display: flex;
                flex-direction: column;
                gap: 1rem;
                text-align: center;
                padding: 1rem;
            }

            .product-info {
                flex-direction: column;
                text-align: center;
                gap: 0.5rem;
            }

            .product-image {
                width: 80px;
                height: 80px;
                margin: 0 auto;
            }

            .quantity-section {
                flex-direction: row;
                justify-content: center;
            }

            .quantity-controls {
                gap: 1rem;
            }

            .item-price,
            .item-total {
                font-size: 1.1rem;
                font-weight: 700;
            }

            .main-cart-header h1 {
                font-size: 2rem;
            }
            
            .order-summary {
                position: static;
                margin-top: 2rem;
            }
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
    </style>
</head>
<body>
<?php include 'includes/user_promo.php'; ?>

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
                             
                             <!-- Cart Header -->
                             <div class="cart-header">
                                 <div>Product Name</div>
                                 <div>Quantity</div>
                                 <div>Item Price</div>
                                 <div>Subtotal</div>
                                 <div></div>
                             </div>
                             
                            <?php foreach ($cart_items as $item): ?>
                                <?php   
                                $product = $item['product'] ?? $item;
                                $quantity = $item['quantity'] ?? 0;
                                
                                // Ensure we always use the correct price from database
                                if (isset($product['id'])) {
                                    $product_data = getProductData($pdo, [intval($product['id'])]);
                                    if (!empty($product_data[intval($product['id'])])) {
                                        $product['price'] = $product_data[intval($product['id'])]['price'];
                                    }
                                }
                                
                                // Recalculate total with correct price
                                $total = ($product['price'] ?? 0) * $quantity;
                                ?>
                                <?php if (is_array($product) && isset($product['id'])): ?>
                                    <div class="cart-item" data-product-id="<?= $product['id'] ?? 0 ?>">
                                        <!-- Product Name Column -->
                                        <div class="product-info">
                                            <img src="<?= !empty($product['image1']) ? 'admin/' . htmlspecialchars($product['image1']) : 'images/placeholder.jpg' ?>" 
                                                 alt="<?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?>" 
                                                 class="product-image"
                                                 onerror="this.src='images/placeholder.jpg'">
                                            
                                            <div class="product-details">
                                                <div class="product-name"><?= htmlspecialchars($product['name'] ?? 'Unknown Product') ?></div>
                                                <div class="stock-info">
                                                    <i class="fas fa-box me-1"></i>
                                                    <?= ($product['stock'] ?? 0) > 0 ? ($product['stock'] ?? 0) . ' in stock' : 'Out of stock' ?>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Quantity Column -->
                                        <div class="quantity-section">
                                            <div class="quantity-controls">
                                                <button class="quantity-btn decrease" data-product-id="<?= $product['id'] ?? 0 ?>" 
                                                        <?= $quantity <= 1 ? 'disabled' : '' ?>>
                                                    <i class="fas fa-minus"></i>
                                                </button>
                                                <input type="number" class="quantity-display cart-qty quantity-input" 
                                                       value="<?= number_format((float)$quantity, 1, '.', '') ?>" 
                                                       step="0.1" min="1" max="<?= (float)($product['stock'] ?? 0) ?>" 
                                                       data-product-id="<?= $product['id'] ?? 0 ?>" 
                                                       data-stock="<?= $product['stock'] ?? 0 ?>" 
                                                       inputmode="decimal" aria-label="Quantity" />
                                                <button class="quantity-btn increase" data-product-id="<?= $product['id'] ?? 0 ?>" 
                                                        <?= $quantity >= ($product['stock'] ?? 0) ? 'disabled' : '' ?>>
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        </div>
                                        
                                        <!-- Item Price Column -->
                                        <div class="item-price">₱<?= number_format($product['price'] ?? 0, 2) ?></div>
                                        
                                        <!-- Subtotal Column -->
                                        <div class="item-total">₱<?= number_format($total, 2) ?></div>
                                        
                                        <!-- Remove Button Column -->
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
                                <i class="fas fa-credit-card me-2"></i>Proceed to Checkout
                            </button>
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

        // Enhanced loading state management
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

        // Enhanced cart item loading with animations
        function setCartItemLoading(cartItem, loading = true) {
            if (!cartItem) return;
            if (loading) {
                cartItem.classList.add('updating');
                // Disable all interactive elements within the cart item
                cartItem.querySelectorAll('button, input, select').forEach(el => {
                    el.disabled = true;
                });
            } else {
                cartItem.classList.remove('updating');
                // Re-enable all interactive elements within the cart item
                cartItem.querySelectorAll('button, input, select').forEach(el => {
                    el.disabled = false;
                });
            }
        }

        // Show success feedback
        function showSuccessFeedback(cartItem) {
            cartItem.classList.add('success-flash');
            setTimeout(() => {
                cartItem.classList.remove('success-flash');
            }, 500);
        }

        // Show error feedback
        function showErrorFeedback(cartItem) {
            cartItem.classList.add('error-shake');
            setTimeout(() => {
                cartItem.classList.remove('error-shake');
            }, 500);
        }

        // Initialize event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Use event delegation for better performance and reliability
            document.addEventListener('click', function(e) {
                // Handle increase button clicks
                if (e.target.closest('.increase')) {
                    const button = e.target.closest('.increase');
                    const productId = button.dataset.productId;
                    updateQuantity(productId, 'add');
                }
                
                // Handle decrease button clicks
                if (e.target.closest('.decrease')) {
                    const button = e.target.closest('.decrease');
                    const productId = button.dataset.productId;
                    updateQuantity(productId, 'decrease');
                }
                
                // Handle remove button clicks
                if (e.target.closest('.remove-item')) {
                    e.preventDefault();
                    const button = e.target.closest('.remove-item');
                    const productId = button.dataset.productId;
                    const cartItem = button.closest('.cart-item');
                    const productName = cartItem ? cartItem.querySelector('.product-name')?.textContent || 'this item' : 'this item';
                    
                    console.log('Remove button clicked for product:', productId, 'Name:', productName);
                    
                    // Show SweetAlert2 confirmation (Success Style)
                    Swal.fire({
                        title: 'Confirm Removal',
                        text: `Are you sure you want to remove ${productName} from your cart?`,
                        icon: 'question',
                        showCancelButton: true,
                        confirmButtonColor: '#dc3545',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-trash me-1"></i>Remove',
                        cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
                        customClass: {
                            popup: 'swal2-danger',
                            confirmButton: 'swal2-confirm',
                            cancelButton: 'swal2-cancel'
                        }
                    }).then((result) => {
                        if (result.isConfirmed) {
                            console.log('User confirmed removal for product:', productId);
                            removeItem(productId);
                        }
                    });
                }
            });

            // Handle direct quantity input changes (minimum 1)
            document.addEventListener('change', function(e) {
                const input = e.target.closest('.quantity-input');
                if (!input) return;
                const productId = input.getAttribute('data-product-id');
                let value = parseFloat(input.value);
                const min = 1; // Minimum quantity is 1
                const max = input.getAttribute('max') ? parseFloat(input.getAttribute('max')) : Number.POSITIVE_INFINITY;
                if (isNaN(value)) value = min;
                value = Math.max(min, Math.min(max, value));
                value = Math.round(value * 10) / 10; // one decimal place
                input.value = value.toFixed(1);
                updateQuantityExact(productId, value);
            });
        });

        function updateQuantity(productId, action) {
            console.log('updateQuantity called:', { productId, action });
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', action);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            // Set loading state
            const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
            setCartItemLoading(cartItem, true);

            // Add loading spinner to the button that was clicked
            const clickedButton = cartItem.querySelector(`.${action === 'add' ? 'increase' : 'decrease'}`);
            const originalButtonContent = clickedButton ? clickedButton.innerHTML : '';
            if (clickedButton) {
                clickedButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    console.log('Response not OK, status:', response.status);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Cart response:', data);
                
                if (data.success) {
                    const qtyInput = document.querySelector(`.cart-qty[data-product-id="${productId}"]`);
                    if (qtyInput) {
                        console.log('Current quantity:', qtyInput.value, 'New quantity:', data.quantity);
                        
                        if (data.quantity > 0) {
                            // Animate quantity change
                            qtyInput.style.transform = 'scale(1.2)';
                            setTimeout(() => {
                                qtyInput.value = parseFloat(data.quantity).toFixed(1);
                                qtyInput.style.transform = 'scale(1)';
                            }, 150);
                            
                            const itemTotalElement = qtyInput.closest('.cart-item').querySelector('.item-total');
                            if (itemTotalElement) {
                                itemTotalElement.textContent = '₱' + Number(data.item_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                            }
                            
                            // Update button states
                            const decreaseBtn = qtyInput.closest('.cart-item').querySelector('.decrease');
                            const increaseBtn = qtyInput.closest('.cart-item').querySelector('.increase');
                            const stock = parseFloat(qtyInput.getAttribute('data-stock'));
                            
                            decreaseBtn.disabled = data.quantity <= 1;
                            increaseBtn.disabled = data.quantity >= stock;
                            
                            // Show success feedback
                            showSuccessFeedback(cartItem);
                        } else {
                            console.log('Removing item from cart');
                            const row = qtyInput.closest('.cart-item');
                            if (row) {
                                row.classList.add('removing');
                                setTimeout(() => {
                                    row.remove();
                                }, 300);
                            }
                        }
                    }
                    
                    // Update cart totals using centralized function
                    updateCartTotalsInDOM(data.cart_total, data.cart_qty);
                    
                    if (data.cart_qty === 0) {
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        // Update all cart elements (badge, sliding cart, totals)
                        updateAllCartElements();
                    }
                } else {
                    showErrorFeedback(cartItem);
                    console.log('Cart operation failed:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorFeedback(cartItem);
                console.log('Network error occurred, but operation may have succeeded');
            })
            .finally(() => {
                setCartItemLoading(cartItem, false);
                // Restore button content
                if (clickedButton && originalButtonContent) {
                    clickedButton.innerHTML = originalButtonContent;
                }
            });
        }

        function updateQuantityExact(productId, quantity) {
            console.log('updateQuantityExact called:', { productId, quantity });
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'set_quantity');
            formData.append('quantity', quantity);
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            // Set loading state
            const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
            setCartItemLoading(cartItem, true);

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response status:', response.status);
                if (!response.ok) {
                    console.log('Response not OK, status:', response.status);
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Cart response:', data);
                
                if (data.success) {
                    // Update cart totals using centralized function
                    updateCartTotalsInDOM(data.cart_total, data.cart_qty);
                    
                    // Update item total
                    const qtyInput = document.querySelector(`.quantity-input[data-product-id="${productId}"]`);
                    if (qtyInput) {
                        const itemTotalElement = qtyInput.closest('.cart-item').querySelector('.item-total');
                        if (itemTotalElement) {
                            itemTotalElement.textContent = '₱' + Number(data.item_total).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                        }
                        
                        // Update button states
                        const decreaseBtn = qtyInput.closest('.cart-item').querySelector('.decrease');
                        const increaseBtn = qtyInput.closest('.cart-item').querySelector('.increase');
                        const stock = parseFloat(qtyInput.getAttribute('data-stock'));
                        
                        decreaseBtn.disabled = data.quantity <= 1;
                        increaseBtn.disabled = data.quantity >= stock;
                        
                        // Show success feedback
                        showSuccessFeedback(cartItem);
                    }
                    
                    if (data.cart_qty === 0) {
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        // Update all cart elements (badge, sliding cart, totals)
                        updateAllCartElements();
                    }
                } else {
                    showErrorFeedback(cartItem);
                    console.log('Cart operation failed:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorFeedback(cartItem);
                console.log('Network error occurred, but operation may have succeeded');
            })
            .finally(() => {
                setCartItemLoading(cartItem, false);
            });
        }

        function removeItem(productId) {
            console.log('removeItem function called with productId:', productId);
            
            const formData = new FormData();
            formData.append('product_id', productId);
            formData.append('action', 'delete');
            formData.append('csrf_token', '<?php echo $csrf_token; ?>');

            const cartItem = document.querySelector(`[data-product-id="${productId}"]`);
            console.log('Found cart item:', cartItem);
            
            if (!cartItem) {
                console.error('Cart item not found for productId:', productId);
                console.log('Item not found in cart');
                return;
            }
            
            setCartItemLoading(cartItem, true);

            // Add loading spinner to remove button
            const removeButton = cartItem.querySelector('.remove-item');
            const originalButtonContent = removeButton ? removeButton.innerHTML : '';
            if (removeButton) {
                removeButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i>';
            }

            console.log('Sending remove request to cart.php with data:', {
                product_id: productId,
                action: 'delete',
                csrf_token: '<?php echo $csrf_token; ?>'
            });

            fetch('cart.php', {
                method: 'POST',
                body: formData
            })
            .then(response => {
                console.log('Response received:', response.status, response.statusText);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                return response.json();
            })
            .then(data => {
                console.log('Response data:', data);
                if (data.success) {
                    const qtySpan = document.querySelector(`.cart-qty[data-product-id="${productId}"]`);
                    if (qtySpan) {
                        const row = qtySpan.closest('.cart-item');
                        if (row) {
                            // Animate removal
                            row.classList.add('removing');
                            setTimeout(() => {
                                row.remove();
                            }, 300);
                        }
                    }
                    
                    // Update cart totals using centralized function
                    updateCartTotalsInDOM(data.cart_total, data.cart_qty);
                    
                    if (data.cart_qty === 0) {
                        setTimeout(() => {
                            location.reload();
                        }, 500);
                    } else {
                        // Update all cart elements (badge, sliding cart, totals)
                        updateAllCartElements();
                    }
                    
                    // Show SweetAlert2 success message (Simple Success style)
                    Swal.fire({
                        title: 'Item Removed!',
                        text: 'Item has been successfully removed from your cart.',
                        icon: 'success',
                        confirmButtonColor: '#198754',
                        confirmButtonText: '<i class="fas fa-check me-1"></i>Great!',
                        customClass: {
                            popup: 'swal2-success',
                            confirmButton: 'swal2-confirm'
                        }
                    });
                } else {
                    showErrorFeedback(cartItem);
                    console.log('Cart operation failed:', data.message || 'Unknown error');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                showErrorFeedback(cartItem);
                console.log('Network error occurred, but operation may have succeeded');
            })
            .finally(() => {
                setCartItemLoading(cartItem, false);
                // Restore button content
                if (removeButton && originalButtonContent) {
                    removeButton.innerHTML = originalButtonContent;
                }
            });
        }

        // Checkout validation
        const checkoutBtn = document.querySelector('.checkout-btn-action');
        if (checkoutBtn) {
            checkoutBtn.addEventListener('click', function(e) {
                let valid = true;
                let message = '';

                document.querySelectorAll('.cart-qty').forEach(input => {
                    const qty = parseFloat(input.value.trim());
                    const stock = parseFloat(input.getAttribute('data-stock'));
                    const productId = input.getAttribute('data-product-id');

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
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(count => {
                    const badge = document.querySelector('.cart-badge');
                    if (badge) {
                        badge.textContent = count;
                        badge.style.display = count > 0 ? 'block' : 'none';
                        
                        // Animate badge update
                        badge.style.transform = 'scale(1.2)';
                        setTimeout(() => {
                            badge.style.transform = 'scale(1)';
                        }, 200);
                    }
                })
                .catch(error => {
                    console.error('Error updating cart badge:', error);
                });
        }

        // Refresh sliding cart content
        function refreshSlidingCart() {
            // Update cart content in sliding cart
            fetch('cart_content.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.text();
                })
                .then(html => {
                    // Find the sliding cart content area using correct selectors
                    const cartContent = document.getElementById('cartContent');
                    
                    if (cartContent) {
                        cartContent.innerHTML = html;
                        console.log('Sliding cart content updated');
                    }
                })
                .catch(error => {
                    console.error('Error refreshing sliding cart:', error);
                });
        }

        // Update cart totals in sliding cart
        function updateSlidingCartTotals() {
            fetch('cart_total.php')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Update cart footer with totals using correct selectors
                    const cartFooter = document.getElementById('cartFooter');
                    const cartSubtotal = document.querySelector('.cart-subtotal');
                    const cartTotal = document.querySelector('.cart-total');
                    
                    if (data.total > 0) {
                        if (cartFooter) cartFooter.style.display = 'block';
                        if (cartSubtotal) cartSubtotal.textContent = '₱' + data.total.toFixed(2);
                        if (cartTotal) cartTotal.textContent = '₱' + data.total.toFixed(2);
                        console.log('Sliding cart totals updated:', data.total);
                    } else {
                        if (cartFooter) cartFooter.style.display = 'none';
                    }
                })
                .catch(error => {
                    console.error('Error updating sliding cart totals:', error);
                });
        }
        
        // Centralized function to update cart totals in DOM
        function updateCartTotalsInDOM(cartTotal, cartQty) {
            const totalElement = document.getElementById('total-with-shipping');
            const subtotalElement = document.querySelector('.summary-row .fw-bold');
            
            if (totalElement) {
                totalElement.style.transform = 'scale(1.1)';
                totalElement.textContent = '₱' + Number(cartTotal).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
                setTimeout(() => {
                    totalElement.style.transform = 'scale(1)';
                }, 200);
            }
            
            if (subtotalElement) {
                subtotalElement.textContent = '₱' + Number(cartTotal).toLocaleString(undefined, {minimumFractionDigits:2, maximumFractionDigits:2});
            }
        }

        // Comprehensive cart update function - optimized to reduce redundant calls
        function updateAllCartElements() {
            console.log('Updating all cart elements...');
            
            // Use a single fetch to get all cart data at once
            fetch('cart.php?action=get_cart_data')
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    if (data.success) {
                        // Update cart badge
                        const badge = document.querySelector('.cart-badge');
                        if (badge) {
                            badge.textContent = data.cart_count || 0;
                            badge.style.display = (data.cart_count > 0) ? 'block' : 'none';
                            
                            // Animate badge update
                            badge.style.transform = 'scale(1.2)';
                            setTimeout(() => {
                                badge.style.transform = 'scale(1)';
                            }, 200);
                        }
                        
                        // Update cart totals in order summary
                        updateCartTotalsInDOM(data.cart_total, data.cart_qty);
                        
                        // Refresh sliding cart content
                        refreshSlidingCart();
                    }
                })
                .catch(error => {
                    console.error('Error updating cart elements:', error);
                    // Fallback to individual updates if batch fails
                    updateCartBadge();
                    refreshSlidingCart();
                });
        }

        // Auto-save cart state (optional feature)
        function autoSaveCart() {
            const cartData = {
                items: document.querySelectorAll('.cart-item').length,
                timestamp: new Date().toISOString()
            };
            localStorage.setItem('cart_auto_save', JSON.stringify(cartData));
        }

        // Add keyboard shortcuts for cart operations
        document.addEventListener('keydown', function(e) {
            // Ctrl + Plus to increase quantity of focused item
            if (e.ctrlKey && e.key === '=') {
                e.preventDefault();
                const focusedItem = document.activeElement.closest('.cart-item');
                if (focusedItem) {
                    const increaseBtn = focusedItem.querySelector('.increase');
                    if (increaseBtn && !increaseBtn.disabled) {
                        increaseBtn.click();
                    }
                }
            }
            
            // Ctrl + Minus to decrease quantity of focused item
            if (e.ctrlKey && e.key === '-') {
                e.preventDefault();
                const focusedItem = document.activeElement.closest('.cart-item');
                if (focusedItem) {
                    const decreaseBtn = focusedItem.querySelector('.decrease');
                    if (decreaseBtn && !decreaseBtn.disabled) {
                        decreaseBtn.click();
                    }
                }
            }
        });

        // Add touch/swipe gestures for mobile (optional)
        let touchStartX = 0;
        let touchEndX = 0;

        function handleSwipe(item) {
            const swipeThreshold = 50;
            const swipeDistance = touchEndX - touchStartX;
            
            if (Math.abs(swipeDistance) > swipeThreshold) {
                if (swipeDistance < 0) {
                    // Swipe left - show remove option
                    const removeBtn = item.querySelector('.remove-btn');
                    if (removeBtn) {
                        removeBtn.style.display = 'block';
                        removeBtn.style.animation = 'slideIn 0.3s ease';
                    }
                } else {
                    // Swipe right - hide remove option
                    const removeBtn = item.querySelector('.remove-btn');
                    if (removeBtn) {
                        removeBtn.style.display = 'none';
                    }
                }
            }
        }

        // Initialize everything when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Add touch/swipe gestures for mobile
            document.querySelectorAll('.cart-item').forEach(item => {
                item.addEventListener('touchstart', function(e) {
                    touchStartX = e.changedTouches[0].screenX;
                });

                item.addEventListener('touchend', function(e) {
                    touchEndX = e.changedTouches[0].screenX;
                    handleSwipe(item);
                });
            });

            // Update all cart elements on page load
            updateAllCartElements();
            
            // Auto-save cart state every 30 seconds
            setInterval(autoSaveCart, 30000);
            
            // Add event listener for sliding cart toggle to refresh content when opened
            const cartToggle = document.querySelector('[onclick="toggleCart()"]');
            if (cartToggle) {
                cartToggle.addEventListener('click', function() {
                    // Small delay to ensure the cart is opening
                    setTimeout(() => {
                        refreshSlidingCart();
                        updateSlidingCartTotals();
                    }, 100);
                });
            }
        });
    </script>
</body>
</html>

