<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
include_once '../includes/batch_manager.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Initialize batch manager
$batchManager = new BatchManager($pdo);

// Helper function to calculate default expiration date (3 months from today)
function calculateDefaultExpirationDate() {
    $expirationDate = new DateTime();
    $expirationDate->add(new DateInterval('P3M')); // Add 3 months
    return $expirationDate->format('Y-m-d');
}

// Handle expiration date update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expiration'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $expiration_date = $_POST['expiration_date'];
        
        // Validate expiration date
        if (empty($expiration_date) || !strtotime($expiration_date)) {
            throw new Exception("Invalid expiration date.");
        }
        
        // Update product_stock expiration_date
        $stmt = $pdo->prepare("UPDATE product_stock SET expiration_date = ? WHERE product_id = ?");
        $stmt->execute([$expiration_date, $product_id]);
        
        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();
        
        logHistory($pdo, 'Expiration Date Update', "Product: $product_name, Expiration: $expiration_date", $_SESSION['username']);
        $_SESSION['success'] = "Expiration date updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating expiration date: " . $e->getMessage();
    }
    header("Location: restocking.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    try {
        $restock_id = (int)$_POST['restocking_id'];
        $new_status = (int)$_POST['status_id'];
        
        if (!in_array($new_status, [1, 2, 3])) {
            throw new Exception("Invalid status.");
        }
        
        $pdo->beginTransaction();
        
        // Get current restocking record
        $stmt = $pdo->prepare("SELECT * FROM restocking WHERE restocking_id = ?");
        $stmt->execute([$restock_id]);
        $restock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$restock) {
            throw new Exception("Restocking record not found.");
        }
        
        // Validate status transition (enum-like behavior: Pending → Received, Pending → Cancelled)
        $current_status = $restock['status_id'];
        $valid_transitions = [
            1 => [2, 3], // Pending can go to Received or Cancelled
            2 => [],     // Received cannot change (final state)
            3 => []      // Cancelled cannot change (final state)
        ];
        
        if (!in_array($new_status, $valid_transitions[$current_status])) {
            $status_names = [1 => 'Pending', 2 => 'Received', 3 => 'Cancelled'];
            throw new Exception("Invalid status transition from {$status_names[$current_status]} to {$status_names[$new_status]}. Valid transitions: " . implode(', ', array_map(function($s) use ($status_names) { return $status_names[$s]; }, $valid_transitions[$current_status])));
        }
        
        // If changing to "Received" (status 2), get actual received quantity
        $actual_quantity_received = $restock['quantity_added']; // Default to ordered quantity
        if ($new_status == 2 && isset($_POST['actual_quantity_received'])) {
            $actual_quantity_received = (float)$_POST['actual_quantity_received'];
            
            // Validate received quantity
            if ($actual_quantity_received < 0) {
                throw new Exception("Received quantity cannot be negative.");
            }
            
            // Allow over-delivery but warn if significantly more than ordered
            if ($actual_quantity_received > $restock['quantity_added'] * 1.1) {
                // Log warning for over-delivery
                error_log("Warning: Over-delivery detected. Ordered: {$restock['quantity_added']}, Received: $actual_quantity_received");
            }
        }
        
        // Update status and actual received quantity
        $stmt = $pdo->prepare("UPDATE restocking SET status_id = ?, actual_quantity_received = ? WHERE restocking_id = ?");
        $stmt->execute([$new_status, $actual_quantity_received, $restock_id]);
        
        // If changing to "Received" (status 2), update stock and create batch
        if ($new_status == 2 && $restock['status_id'] != 2) {
            // Get cost from product_pricing table (fallback only)
            $stmt = $pdo->prepare("SELECT cost_price FROM product_pricing WHERE product_id = ?");
            $stmt->execute([$restock['product_id']]);
            $cost_price = $stmt->fetchColumn() ?: 0;
            
           
            // Get brand_id from the restocking record if available, otherwise use the first available brand for this product
            $brand_id = null;
            if (isset($restock['brand_id']) && $restock['brand_id']) {
                $brand_id = $restock['brand_id'];
            } else {
                // For legacy restocking records without brand_id, get the first available brand for this product
                $stmt = $pdo->prepare("SELECT id FROM brands WHERE is_archived = 0 ORDER BY id ASC LIMIT 1");
                $stmt->execute();
                $brand_id = $stmt->fetchColumn();
            }
            
            $batch_data = [
                'product_id' => $restock['product_id'],
                'supplier_id' => $restock['supplier_id'],
                'brand_id' => $brand_id,
                'quantity_received' => $actual_quantity_received, // Use actual received quantity
                'unit_cost' => $cost_price,
                'expiration_date' => $restock['expiration_date'] ?? calculateDefaultExpirationDate(),
                'received_date' => $restock['restock_date'],
                'created_by' => $_SESSION['user_id'],
                'reference_type' => 'restock',
                'reference_id' => $restock_id,
                'notes' => "Restocking: " . ($restock['notes'] ?? '')
            ];
            
            $batch_id = $batchManager->createBatch($batch_data);
            
            // Store the batch_id in the restocking record
            $stmt = $pdo->prepare("UPDATE restocking SET batch_id = ? WHERE restocking_id = ?");
            $stmt->execute([$batch_id, $restock_id]);
            
            // Update product_stock current_stock and last_restock_date
            $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ? WHERE product_id = ?");
            $stmt->execute([$actual_quantity_received, $restock['restock_date'], $restock['product_id']]);
            
            // Get new current stock
            $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
            $stmt->execute([$restock['product_id']]);
            $current_stock = (int)$stmt->fetchColumn();
            
            // Record stock movement
            $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 1, ?, ?, ?, 'Restocking - Status Updated', ?, 'restock', ?)");
            $stmt->execute([$restock['product_id'], $actual_quantity_received, $current_stock - $actual_quantity_received, $current_stock, $restock_id, $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        
        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$restock['product_id']]);
        $product_name = $stmt->fetchColumn();
        
        $status_names = [1 => 'Pending', 2 => 'Received', 3 => 'Cancelled'];
        $log_message = "Product: $product_name, Status: {$status_names[$new_status]}";
        if ($new_status == 2) {
            $log_message .= ", Ordered: {$restock['quantity_added']}, Received: $actual_quantity_received";
        }
        logHistory($pdo, 'Restocking Status Update', $log_message, $_SESSION['username']);
        $_SESSION['success'] = "Restocking status updated successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
    }
    header("Location: restocking.php");
    exit;
}

// Handle restocking form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock'])) {
    try {
        // Validate required fields
        $required_fields = ['product_id', 'supplier_id', 'brand_id', 'quantity_added', 'cost_per_unit', 'expiration_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        $product_id = (int)$_POST['product_id'];
        $supplier_id = (int)$_POST['supplier_id'];
        $brand_id = (int)$_POST['brand_id'];
        $quantity_added = (float)$_POST['quantity_added'];
        $cost_per_unit = (float)$_POST['cost_per_unit'];
        $total_cost = $quantity_added * $cost_per_unit;
        $restock_date = date('Y-m-d'); // Automatically set to today
        $expiration_date = $_POST['expiration_date'];
        $notes = $_POST['notes'] ?: null;

        // Validate positive values
        if ($quantity_added <= 0) {
            throw new Exception("Quantity added must be greater than 0.");
        }
        if ($cost_per_unit < 0) {
            throw new Exception("Cost per unit cannot be negative.");
        }
        
        // Validate expiration date
        if (strtotime($expiration_date) <= strtotime($restock_date)) {
            throw new Exception("Expiration date must be after restock date.");
        }
        
        // Check if expiration date is too close (less than 3 months from restock date)
        $expiration_timestamp = strtotime($expiration_date);
        $restock_timestamp = strtotime($restock_date);
        $days_until_expiry = floor(($expiration_timestamp - $restock_timestamp) / (60 * 60 * 24));
        
        if ($days_until_expiry < 90) {
            throw new Exception("Expiration date is too close to restock date. Products must have at least 3 months (90 days) before expiration. Current: {$days_until_expiry} days.");
        }

        $pdo->beginTransaction();

        // Insert restocking record with cost columns
        $stmt = $pdo->prepare("INSERT INTO restocking (product_id, supplier_id, brand_id, quantity_added, cost_per_unit, total_cost, restock_date, expiration_date, status_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2, ?, ?)");
        $stmt->execute([$product_id, $supplier_id, $brand_id, $quantity_added, $cost_per_unit, $total_cost, $restock_date, $expiration_date, $notes, $_SESSION['user_id']]);
        $restock_id = $pdo->lastInsertId();

        // Update cost_price in product_pricing table (keep as fallback only)
        // $stmt = $pdo->prepare("UPDATE product_pricing SET cost_price = ? WHERE product_id = ?");
        // $stmt->execute([$cost_per_unit, $product_id]);

        // Create a new batch for the restocked items
        $batch_data = [
            'product_id' => $product_id,
            'supplier_id' => $supplier_id,
            'brand_id' => $brand_id,
            'quantity_received' => $quantity_added,
            'unit_cost' => $cost_per_unit, // Use the cost_per_unit from form
            'expiration_date' => $expiration_date,
            'received_date' => $restock_date,
            'created_by' => $_SESSION['user_id'],
            'reference_type' => 'restock',
            'reference_id' => $restock_id,
            'notes' => "Restocking: " . ($notes ?? '')
        ];
        
        $batch_id = $batchManager->createBatch($batch_data);

        // Store the batch_id in the restocking record
        $stmt = $pdo->prepare("UPDATE restocking SET batch_id = ? WHERE restocking_id = ?");
        $stmt->execute([$batch_id, $restock_id]);

        // Update product_stock current_stock, last_restock_date, and expiration_date
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ?, expiration_date = ? WHERE product_id = ?");
        $stmt->execute([$quantity_added, $restock_date, $expiration_date, $product_id]);

        // Fetch new current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();

        // Record stock movement
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 1, ?, ?, ?, 'Restocking', ?, 'restock', ?)");
        $stmt->execute([$product_id, $quantity_added, $current_stock - $quantity_added, $current_stock, $restock_id, $_SESSION['user_id']]);

        $pdo->commit();

        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();

        logHistory($pdo, 'Restocking', "Product: $product_name, Quantity: $quantity_added, Cost: ₱$total_cost", $_SESSION['username']);
        $_SESSION['success'] = "Restocking recorded successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error recording restocking: " . $e->getMessage();
    }
    header("Location: restocking.php");
    exit;
}

// Fetch products with current stock
$stmt = $pdo->query("
    SELECT 
        p.product_id AS id,
        p.product_name AS name,
        c.category_name as category_name,
        b.name as brand_name,
        s.name as supplier_name,
        u.name as uom_name,
        COALESCE(ps.current_stock,0) AS stock,
        COALESCE(ps.reorder_point, 10) AS reorder_point
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    WHERE p.is_archive = 0
    ORDER BY ps.current_stock ASC, p.product_name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers
$stmt = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_archive = 0 ORDER BY name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands
$stmt = $pdo->query("SELECT id, name FROM brands WHERE is_archived = 0 ORDER BY name");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX request for getting suppliers by product
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_suppliers_by_product') {
    $product_id = (int)$_GET['product_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            s.supplier_id AS id,
            s.name,
            sp.is_primary
        FROM suppliers s
        INNER JOIN supplier_products sp ON s.supplier_id = sp.supplier_id
        WHERE sp.product_id = ? AND sp.is_active = 1 AND s.is_archive = 0
        ORDER BY sp.is_primary DESC, s.name
    ");
    $stmt->execute([$product_id]);
    $product_suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($product_suppliers);
    exit;
}

// Handle AJAX request for restocking details
if (isset($_GET['action']) && $_GET['action'] == 'get_restock_details' && isset($_GET['restocking_id'])) {
    $restocking_id = (int)$_GET['restocking_id'];
    
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            p.product_name,
            u.name as uom_name
        FROM restocking r
        LEFT JOIN products p ON r.product_id = p.product_id
        LEFT JOIN uom u ON p.uom_id = u.uom_id
        WHERE r.restocking_id = ?
    ");
    $stmt->execute([$restocking_id]);
    $restock = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($restock) {
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'product_name' => $restock['product_name'],
            'quantity_added' => $restock['quantity_added'],
            'uom_name' => $restock['uom_name']
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Restocking record not found']);
    }
    exit;
}

// Handle AJAX request for PO details
if (isset($_GET['action']) && $_GET['action'] == 'get_po_details' && isset($_GET['po_number'])) {
    $po_number = $_GET['po_number'];
    
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            p.product_name,
            b.name as brand_name,
            u.name as uom_name,
            COALESCE(r.cost_per_unit, pp.cost_price, 0) as unit_cost,
            COALESCE(r.total_cost, r.quantity_added * COALESCE(r.cost_per_unit, pp.cost_price, 0), 0) as total_cost,
            CASE 
                WHEN r.status_id = 1 THEN 'Pending'
                WHEN r.status_id = 2 THEN 'Received'
                WHEN r.status_id = 3 THEN 'Cancelled'
                ELSE 'Unknown'
            END as status_name
        FROM restocking r
        LEFT JOIN products p ON r.product_id = p.product_id
        LEFT JOIN brands b ON r.brand_id = b.id
        LEFT JOIN uom u ON p.uom_id = u.uom_id
        LEFT JOIN product_pricing pp ON r.product_id = pp.product_id
        WHERE r.po_number = ?
        ORDER BY p.product_name
    ");
    $stmt->execute([$po_number]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode(['items' => $items]);
    exit;
}

// Fetch recent restocking records (including Purchase Orders)
$stmt = $pdo->query("
    SELECT 
        r.*,
        p.product_name,
        s.name as supplier_name,
        b.name as brand_name,
        u.name as uom_name,
        ps.expiration_date,
        COALESCE(r.cost_per_unit, pp.cost_price, 0) as unit_cost,
        COALESCE(r.total_cost, r.quantity_added * COALESCE(r.cost_per_unit, pp.cost_price, 0), 0) as total_cost,
        COALESCE(r.actual_quantity_received, r.quantity_added) as actual_received_qty,
        CASE 
            WHEN r.status_id = 1 THEN 'Pending'
            WHEN r.status_id = 2 THEN 'Received'
            WHEN r.status_id = 3 THEN 'Cancelled'
            ELSE 'Unknown'
        END as status_name
    FROM restocking r
    JOIN products p ON r.product_id = p.product_id
    JOIN suppliers s ON r.supplier_id = s.supplier_id
    LEFT JOIN brands b ON r.brand_id = b.id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = r.product_id
    LEFT JOIN product_pricing pp ON pp.product_id = r.product_id
    ORDER BY r.restock_date DESC, r.created_at DESC
    LIMIT 50
");
$recent_restocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate restocking statistics
$total_restocks = $pdo->query("SELECT COUNT(*) FROM restocking WHERE status_id = 2")->fetchColumn();
$total_restock_value = $pdo->query("SELECT SUM(r.quantity_added * pp.cost_price) FROM restocking r JOIN product_pricing pp ON r.product_id = pp.product_id WHERE r.status_id = 2")->fetchColumn() ?: 0;
$pending_restocks = $pdo->query("SELECT COUNT(*) FROM restocking WHERE status_id = 1")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restocking Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bs-primary: #7F1734;
            --bs-secondary: #6c757d;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }
        
        /* Override admin styles for this page */
        .main-content {
            background-color: var(--bg-primary) !important;
        }
        
        .main-container {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .page-header {
            background: var(--bs-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
        }
        
        .page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

      
        .analytics-card {
            background: white;
            color: var(--bs-dark);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .analytics-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bs-primary);
        }

        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            background: rgba(127, 23, 52, 0.1);
            color: var(--bs-primary);
        }

        .card-content {
            flex: 1;
        }

        .card-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-primary);
            margin: 0;
            line-height: 1;
        }

        .card-label {
            color: var(--bs-secondary);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0.5rem 0 0 0;
        }
        
        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            color: var(--text-primary) !important;
        }
        
        .table-card .card-header {
            background: transparent;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-card .card-body {
            padding: 1.5rem;
        }
        
        .table-card .table {
            margin-bottom: 0;
        }
        
        .table-card .table th {
            border: none;
            padding: 1rem 1.25rem;
            font-weight: 600;
            color: var(--bs-dark);
        }
        
        .table-card .table td {
            border: none;
            padding: 1rem 1.25rem;
            vertical-align: middle;
        }
        
        .table-card .table-light {
            background: #f8f9fa;
        }
        
        .low-stock-item {
            background-color: #fff8e1;
            border-left: 4px solid #ffb74d;
        }
        
        .out-of-stock-item {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        
        /* Pastel button styles */
        .btn-pastel-primary {
            background-color: #e3f2fd;
            color: #1976d2;
            border: 1px solid #bbdefb;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .btn-pastel-primary:hover {
            background-color: #bbdefb;
            color: #0d47a1;
            transform: translateY(-1px);
        }
        
        .btn-pastel-success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #c8e6c9;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .btn-pastel-success:hover {
            background-color: #c8e6c9;
            color: #1b5e20;
            transform: translateY(-1px);
        }
        
        .btn-pastel-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #ffcdd2;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .btn-pastel-danger:hover {
            background-color: #ffcdd2;
            color: #b71c1c;
            transform: translateY(-1px);
        }
        
        .btn-pastel-info {
            background-color: #e0f2f1;
            color: #00695c;
            border: 1px solid #b2dfdb;
            border-radius: 8px;
            padding: 6px 12px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
        }
        
        .btn-pastel-info:hover {
            background-color: #b2dfdb;
            color: #004d40;
            transform: translateY(-1px);
        }
        
        /* Status badges with pastel colors */
        .badge-pastel-warning {
            background-color: #fff8e1;
            color: #f57c00;
            border: 1px solid #ffcc02;
        }
        
        .badge-pastel-success {
            background-color: #e8f5e8;
            color: #2e7d32;
            border: 1px solid #4caf50;
        }
        
        .badge-pastel-danger {
            background-color: #ffebee;
            color: #c62828;
            border: 1px solid #f44336;
        }
        
        .badge-pastel-info {
            background-color: #e0f2f1;
            color: #00695c;
            border: 1px solid #b2dfdb;
            padding: 6px 12px;
            border-radius: 8px;
            font-size: 0.8rem;
            font-weight: 500;
        }
        
        .badge-pastel-secondary {
            background-color: #f3e5f5;
            color: #7b1fa2;
            border: 1px solid #ba68c8;
        }
        
        /* Select2 Custom Styling */
        .select2-container--bootstrap-5 .select2-selection {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            min-height: 38px;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single {
            height: 38px;
            padding: 0.375rem 0.75rem;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
            line-height: 1.5;
            padding-left: 0;
            padding-right: 0;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
            height: 36px;
            right: 8px;
        }
        
        .select2-container--bootstrap-5 .select2-dropdown {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
        }
        
        .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
            border: 1px solid #ced4da;
            border-radius: 0.375rem;
            padding: 0.375rem 0.75rem;
        }
        
        .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
            background-color: #7F1734;
            color: white;
        }
        
        .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
            background-color: #7F1734;
            color: white;
        }
        
        .select2-container--bootstrap-5 .select2-selection--single:focus {
            border-color: #7F1734;
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="main-container">
            <?php if (isset($_SESSION['success'])): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>
                            <i class="fa fa-plus-circle me-3"></i>Restocking Management
                        </h2>
                        <p class="mb-0 opacity-75">Record new stock entries and track restocking activities</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" data-bs-toggle="modal" data-bs-target="#restockModal">
                        <i class="fa fa-plus me-2"></i>Add Stock
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $total_restocks ?></h3>
                            <p class="card-label">Completed Restocks</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-clock"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $pending_restocks ?></h3>
                            <p class="card-label">Pending Restocks</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-money-bill"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number">₱<?= number_format($total_restock_value, 2) ?></h3>
                            <p class="card-label">Total Value</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= count(array_filter($products, fn($p) => (float)$p['stock'] <= (float)$p['reorder_point'])) ?></h3>
                            <p class="card-label">Need Restocking</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products Needing Restocking -->
            <div class="table-card mb-4">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-exclamation-triangle me-2"></i>Products Needing Restocking
                    </h5>
                </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Current Stock</th>
                            <th class="fw-semibold">Reorder Point</th>
                            <th class="fw-semibold">Status</th>
                            <th class="fw-semibold">Supplier</th>
                            <th class="fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php if ((float)$product['stock'] <= (float)$product['reorder_point']): ?>
                                <tr class="<?= (float)$product['stock'] <= 0 ? 'out-of-stock-item' : 'low-stock-item' ?>">
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($product['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($product['category_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format((float)$product['stock'], 1) ?> <?= htmlspecialchars($product['uom_name']) ?></span>
                                    </td>
                                    <td><?= $product['reorder_point'] ?></td>
                                    <td>
                                        <?php if ((float)$product['stock'] <= 0): ?>
                                            <span class="badge badge-pastel-danger">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="badge badge-pastel-warning">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['supplier_name']) ?></td>
                                    <td>
                                        <button class="btn btn-pastel-success" onclick="openRestockModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                                            <i class="fa fa-plus me-1"></i>Restock
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

            <!-- Recent Restocking Records -->
            <div class="table-card">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-history me-2"></i>Recent Restocking Records
                    </h5>
                </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                         <tr>
                             <th class="fw-semibold">Date</th>
                             <th class="fw-semibold">Type/PO</th>
                             <th class="fw-semibold">Product</th>
                             <th class="fw-semibold">Brand</th>
                             <th class="fw-semibold">Supplier</th>
                             <th class="fw-semibold">Quantity (Ordered/Received)</th>
                             <th class="fw-semibold">Cost per Unit</th>
                             <th class="fw-semibold">Total Cost</th>
                             <th class="fw-semibold">Status</th>
                             <th class="fw-semibold">Actions</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_restocks as $restock): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($restock['restock_date'])) ?></td>
                                <td>
                                    <?php if ($restock['is_purchase_order']): ?>
                                        <span class="badge badge-pastel-info" style="cursor: pointer;" onclick="viewPODetails('<?= htmlspecialchars($restock['po_number']) ?>')">
                                            <i class="fa fa-file-invoice me-1"></i><?= htmlspecialchars($restock['po_number']) ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge badge-pastel-secondary">Manual</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($restock['product_name']) ?></td>
                                <td>
                                    <?php if (!empty($restock['brand_name'])): ?>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($restock['brand_name']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($restock['supplier_name']) ?></td>
                                <td>
                                    <?php if ($restock['status_id'] == 2 && $restock['actual_quantity_received'] != $restock['quantity_added']): ?>
                                        <span class="text-muted"><?= number_format((float)$restock['quantity_added'], 1) ?></span>
                                        <br><span class="fw-bold text-success"><?= number_format((float)$restock['actual_received_qty'], 1) ?></span>
                                        <small class="text-muted"><?= htmlspecialchars($restock['uom_name']) ?></small>
                                    <?php else: ?>
                                        <?= number_format((float)$restock['quantity_added'], 1) ?> <?= htmlspecialchars($restock['uom_name']) ?>
                                    <?php endif; ?>
                                </td>
                                <td>₱<?= number_format($restock['unit_cost'], 2) ?></td>
                                <td>₱<?= number_format($restock['total_cost'], 2) ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    switch($restock['status_id']) {
                                        case 1: $statusClass = 'badge-pastel-warning'; break;
                                        case 2: $statusClass = 'badge-pastel-success'; break;
                                        case 3: $statusClass = 'badge-pastel-danger'; break;
                                        default: $statusClass = 'badge-pastel-secondary';
                                    }
                                    ?>
                                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($restock['status_name']) ?></span>
                                </td>
                                <td>
                                    <?php if ($restock['status_id'] == 1): ?>
                                        <button class="btn btn-pastel-success" onclick="updateRestockStatus(<?= $restock['restocking_id'] ?>, 2)" title="Mark as Received">
                                            <i class="fa fa-check me-1"></i>Receive
                                        </button>
                                        <button class="btn btn-pastel-danger" onclick="updateRestockStatus(<?= $restock['restocking_id'] ?>, 3)" title="Cancel">
                                            <i class="fa fa-times"></i>
                                        </button>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </main>

    <!-- Restocking Modal -->
    <div class="modal fade" id="restockModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="restocking.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fa fa-plus-circle me-2" style="color: #7F1734;"></i>Add Stock
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="restock" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product</label>
                                <select name="product_id" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (Current: <?= number_format((float)$product['stock'], 1) ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supplier</label>
                                <select name="supplier_id" id="supplierSelect" class="form-select" required>
                                    <option value="">Select Product First</option>
                                </select>
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>
                                    Suppliers will be filtered based on the selected product
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Brand <span class="text-danger">*</span></label>
                                <select name="brand_id" class="form-select" required>
                                    <option value="">Select Brand</option>
                                    <?php foreach ($brands as $brand): ?>
                                        <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>
                                    Brand for this batch (affects pricing per brand)
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Quantity Added</label>
                                <input type="number" name="quantity_added" class="form-control" required min="0.1" step="0.1">
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>
                                    Enter decimal quantities (e.g., 1.5, 2.3)
                                </div>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cost per Unit (₱)</label>
                                <input type="number" step="0.01" name="cost_per_unit" class="form-control" required min="0">
                            </div>
                             <div class="col-md-6">
                                 <label class="form-label fw-semibold">Expiration Date</label>
                                 <input type="date" name="expiration_date" class="form-control" required>
                                 <div class="form-text">
                                     <i class="fa fa-info-circle me-1"></i>
                                     Must be at least 3 months (90 days) after today to prevent waste
                                 </div>
                             </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about this restocking..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734; border-radius: 8px;">
                            <i class="fa fa-save me-2"></i>Add Stock
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Quantity Adjustment Modal -->
    <div class="modal fade" id="quantityAdjustmentModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Adjust Received Quantity
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="quantityAdjustmentForm">
                    <div class="modal-body">
                        <input type="hidden" name="update_status" value="1">
                        <input type="hidden" name="restocking_id" id="adjust_restocking_id">
                        <input type="hidden" name="status_id" value="2">
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Product</label>
                            <input type="text" id="adjust_product_name" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Ordered Quantity</label>
                            <input type="text" id="adjust_ordered_qty" class="form-control" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Actual Received Quantity <span class="text-danger">*</span></label>
                            <input type="number" name="actual_quantity_received" id="adjust_received_qty" 
                                   class="form-control" min="0" step="0.01" required>
                            <div class="form-text">
                                <i class="fa fa-info-circle me-1"></i>
                                Enter the actual quantity received from supplier
                            </div>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="fa fa-info-circle me-2"></i>
                            <strong>Note:</strong> This will update your inventory with the actual received quantity.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success fw-bold">
                            <i class="fa fa-check me-2"></i>Confirm Receipt
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Purchase Order Modal -->
    <div class="modal fade" id="viewPOModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fa fa-file-invoice me-2" style="color: #7F1734;"></i>Purchase Order Details: <span id="view_po_number"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>Product</th>
                                    <th>Brand</th>
                                    <th>Quantity</th>
                                    <th>Unit Cost</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="po_items_body"></tbody>
                        </table>
                    </div>
                    <div class="alert alert-light mt-3">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="fw-bold fs-5">Grand Total:</span>
                            <span class="fw-bold fs-4 text-primary">₱<span id="po_grand_total">0.00</span></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
         function openRestockModal(productId, productName) {
             const modal = new bootstrap.Modal(document.getElementById('restockModal'));
             
             // Show modal first, then set the value after Select2 is initialized
             modal.show();
             
             // Wait for modal to be shown and Select2 to be initialized
             $('#restockModal').on('shown.bs.modal', function () {
                 const select = document.querySelector('#restockModal select[name="product_id"]');
                 if (select && $(select).hasClass('select2-hidden-accessible')) {
                     // Set value using Select2
                     $(select).val(productId).trigger('change');
                     // Load suppliers for this product
                     loadSuppliersForProduct(productId);
                 }
             });
         }
         
         function setExpirationDate(productId, productName) {
             const expirationDate = prompt(`Set expiration date for ${productName}:\n\nEnter date (YYYY-MM-DD):`);
             if (expirationDate && expirationDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                 // Validate date
                 const date = new Date(expirationDate);
                 if (date instanceof Date && !isNaN(date)) {
                     if (confirm(`Set expiration date to ${expirationDate} for ${productName}?`)) {
                         // Create a form to submit the expiration date update
                         const form = document.createElement('form');
                         form.method = 'POST';
                         form.action = 'restocking.php';
                         
                         const productIdInput = document.createElement('input');
                         productIdInput.type = 'hidden';
                         productIdInput.name = 'product_id';
                         productIdInput.value = productId;
                         
                         const expirationInput = document.createElement('input');
                         expirationInput.type = 'hidden';
                         expirationInput.name = 'expiration_date';
                         expirationInput.value = expirationDate;
                         
                         const updateExpirationInput = document.createElement('input');
                         updateExpirationInput.type = 'hidden';
                         updateExpirationInput.name = 'update_expiration';
                         updateExpirationInput.value = '1';
                         
                         form.appendChild(productIdInput);
                         form.appendChild(expirationInput);
                         form.appendChild(updateExpirationInput);
                         
                         document.body.appendChild(form);
                         form.submit();
                     }
                 } else {
                     Swal.fire({
                         icon: 'error',
                         title: 'Invalid Date',
                         text: 'Please use YYYY-MM-DD format.',
                         confirmButtonColor: '#7F1734'
                     });
                 }
             } else if (expirationDate) {
                 Swal.fire({
                     icon: 'error',
                     title: 'Invalid Date',
                     text: 'Please use YYYY-MM-DD format.',
                     confirmButtonColor: '#7F1734'
                 });
             }
         }
        
        function updateStatus(restockId, newStatus) {
            const statusNames = {1: 'Pending', 2: 'Received', 3: 'Cancelled'};
            const statusName = statusNames[newStatus];
            
            Swal.fire({
                title: 'Update Status',
                text: `Are you sure you want to mark this restocking as "${statusName}"?`,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7F1734',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, update it!',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form to submit the status update
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'restocking.php';
                    
                    const restockIdInput = document.createElement('input');
                    restockIdInput.type = 'hidden';
                    restockIdInput.name = 'restocking_id';
                    restockIdInput.value = restockId;
                    
                    const statusInput = document.createElement('input');
                    statusInput.type = 'hidden';
                    statusInput.name = 'status_id';
                    statusInput.value = newStatus;
                    
                    const updateInput = document.createElement('input');
                    updateInput.type = 'hidden';
                    updateInput.name = 'update_status';
                    updateInput.value = '1';
                    
                    form.appendChild(restockIdInput);
                    form.appendChild(statusInput);
                    form.appendChild(updateInput);
                    
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
         // Function to load suppliers for a selected product
         function loadSuppliersForProduct(productId) {
             const supplierSelect = document.getElementById('supplierSelect');
             
             if (!productId) {
                 supplierSelect.innerHTML = '<option value="">Select Product First</option>';
                 // Update Select2 if it's initialized
                 if ($(supplierSelect).hasClass('select2-hidden-accessible')) {
                     $(supplierSelect).trigger('change');
                 }
                 return;
             }
             
             // Show loading state
             supplierSelect.innerHTML = '<option value="">Loading suppliers...</option>';
             supplierSelect.disabled = true;
             
             // Update Select2 if it's initialized
             if ($(supplierSelect).hasClass('select2-hidden-accessible')) {
                 $(supplierSelect).trigger('change');
             }
             
             fetch(`restocking.php?action=get_suppliers_by_product&product_id=${productId}`)
                 .then(response => response.json())
                 .then(suppliers => {
                     supplierSelect.innerHTML = '<option value="">Select Supplier</option>';
                     
                     if (suppliers.length === 0) {
                         supplierSelect.innerHTML += '<option value="" disabled>No suppliers assigned to this product</option>';
                     } else {
                         suppliers.forEach(supplier => {
                             const option = document.createElement('option');
                             option.value = supplier.id;
                             option.textContent = supplier.name + (supplier.is_primary ? ' (Primary)' : '');
                             supplierSelect.appendChild(option);
                         });
                     }
                     
                     supplierSelect.disabled = false;
                     
                     // Update Select2 if it's initialized
                     if ($(supplierSelect).hasClass('select2-hidden-accessible')) {
                         $(supplierSelect).trigger('change');
                     }
                 })
                 .catch(error => {
                     console.error('Error loading suppliers:', error);
                     supplierSelect.innerHTML = '<option value="">Error loading suppliers</option>';
                     supplierSelect.disabled = false;
                     
                     // Update Select2 if it's initialized
                     if ($(supplierSelect).hasClass('select2-hidden-accessible')) {
                         $(supplierSelect).trigger('change');
                     }
                 });
         }
         
         // Event delegation for status update buttons
         document.addEventListener('DOMContentLoaded', function() {
             document.addEventListener('click', function(e) {
                 if (e.target.closest('.status-update-btn')) {
                     e.preventDefault();
                     const button = e.target.closest('.status-update-btn');
                     const restockId = button.getAttribute('data-restock-id');
                     const newStatus = button.getAttribute('data-status');
                     updateStatus(restockId, newStatus);
                 }
             });
             
             // Initialize Select2 on product dropdown when modal is shown
             $('#restockModal').on('shown.bs.modal', function () {
                 const productSelect = document.querySelector('#restockModal select[name="product_id"]');
                 if (productSelect && !$(productSelect).hasClass('select2-hidden-accessible')) {
                     try {
                         $(productSelect).select2({
                             theme: 'bootstrap-5',
                             placeholder: 'Search and select a product...',
                             allowClear: true,
                             width: '100%',
                             dropdownParent: $('#restockModal')
                         });
                         
                         // Product selection change handler
                         $(productSelect).on('change', function() {
                             loadSuppliersForProduct(this.value);
                         });
                         
                         console.log('Product Select2 initialized successfully');
                     } catch (error) {
                         console.error('Error initializing Product Select2:', error);
                     }
                 }
                 
                 // Initialize Select2 on supplier dropdown
                 const supplierSelect = document.querySelector('#restockModal select[name="supplier_id"]');
                 if (supplierSelect && !$(supplierSelect).hasClass('select2-hidden-accessible')) {
                     try {
                         $(supplierSelect).select2({
                             theme: 'bootstrap-5',
                             placeholder: 'Search and select a supplier...',
                             allowClear: true,
                             width: '100%',
                             dropdownParent: $('#restockModal')
                         });
                         
                         console.log('Supplier Select2 initialized successfully');
                     } catch (error) {
                         console.error('Error initializing Supplier Select2:', error);
                     }
                 }
             });
             
             // Clean up Select2 when modal is hidden
             $('#restockModal').on('hidden.bs.modal', function () {
                 const productSelect = document.querySelector('#restockModal select[name="product_id"]');
                 if (productSelect && $(productSelect).hasClass('select2-hidden-accessible')) {
                     $(productSelect).select2('destroy');
                 }
                 
                 const supplierSelect = document.querySelector('#restockModal select[name="supplier_id"]');
                 if (supplierSelect && $(supplierSelect).hasClass('select2-hidden-accessible')) {
                     $(supplierSelect).select2('destroy');
                 }
             });
             
             // Auto-set expiration date and validate
             const expirationDateInput = document.querySelector('input[name="expiration_date"]');
             
             if (expirationDateInput) {
                 // Set default expiration date to 3 months from today
                 const today = new Date();
                 const expirationDate = new Date(today);
                 expirationDate.setMonth(expirationDate.getMonth() + 3);
                 expirationDateInput.value = expirationDate.toISOString().split('T')[0];
                 
                 // Validate expiration date when it changes
                 expirationDateInput.addEventListener('change', function() {
                     validateExpirationDate();
                 });
                 
                 // Initial validation
                 validateExpirationDate();
             }
             
             // Function to validate expiration date
             function validateExpirationDate() {
                 const today = new Date();
                 const expirationDate = new Date(expirationDateInput.value);
                 
                 if (expirationDate && !isNaN(expirationDate)) {
                     const daysUntilExpiry = Math.floor((expirationDate - today) / (1000 * 60 * 60 * 24));
                     
                     // Remove existing validation messages
                     const existingAlert = document.querySelector('.expiration-validation-alert');
                     if (existingAlert) {
                         existingAlert.remove();
                     }
                     
                     if (daysUntilExpiry < 90) {
                         // Show warning
                         const alertDiv = document.createElement('div');
                         alertDiv.className = 'alert alert-warning expiration-validation-alert mt-2';
                         alertDiv.innerHTML = `
                             <i class="fa fa-exclamation-triangle me-2"></i>
                             <strong>Warning:</strong> Expiration date is only ${daysUntilExpiry} days from today. 
                             Products must have at least 3 months (90 days) before expiration to prevent waste.
                         `;
                         expirationDateInput.parentNode.appendChild(alertDiv);
                         
                         // Disable submit button
                         const submitBtn = document.querySelector('button[type="submit"]');
                         if (submitBtn) {
                             submitBtn.disabled = true;
                             submitBtn.title = 'Expiration date is too close to today';
                         }
                     } else {
                         // Enable submit button
                         const submitBtn = document.querySelector('button[type="submit"]');
                         if (submitBtn) {
                             submitBtn.disabled = false;
                             submitBtn.title = '';
                         }
                         
                         // Show success message for good expiration dates
                         if (daysUntilExpiry >= 90) {
                             const alertDiv = document.createElement('div');
                             alertDiv.className = 'alert alert-success expiration-validation-alert mt-2';
                             alertDiv.innerHTML = `
                                 <i class="fa fa-check-circle me-2"></i>
                                 <strong>Good:</strong> Product has ${daysUntilExpiry} days (${Math.round(daysUntilExpiry/30)} months) before expiration.
                             `;
                             expirationDateInput.parentNode.appendChild(alertDiv);
                         }
                     }
                 }
             }
         });
         
         // Purchase Order Functions
         function viewPODetails(poNumber) {
             document.getElementById('view_po_number').textContent = poNumber;
             
             // Fetch PO details via PHP query
             fetch(`restocking.php?action=get_po_details&po_number=${encodeURIComponent(poNumber)}`)
                 .then(response => response.json())
                 .then(data => {
                     const tbody = document.getElementById('po_items_body');
                     tbody.innerHTML = '';
                     let grandTotal = 0;
                     
                     if (data.items && data.items.length > 0) {
                         data.items.forEach(item => {
                             const statusClass = item.status_id == 1 ? 'warning' : (item.status_id == 2 ? 'success' : 'danger');
                             tbody.innerHTML += `
                                 <tr>
                                     <td>${item.product_name}</td>
                                     <td><span class="badge bg-secondary">${item.brand_name || 'No Brand'}</span></td>
                                     <td>${parseFloat(item.quantity_added).toFixed(1)} ${item.uom_name || ''}</td>
                                     <td>₱${parseFloat(item.unit_cost).toFixed(2)}</td>
                                     <td>₱${parseFloat(item.total_cost).toFixed(2)}</td>
                                     <td><span class="badge bg-${statusClass}">${item.status_name}</span></td>
                                 </tr>
                             `;
                             grandTotal += parseFloat(item.total_cost);
                         });
                     } else {
                         tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted">No items found</td></tr>';
                     }
                     
                     document.getElementById('po_grand_total').textContent = grandTotal.toFixed(2);
                     new bootstrap.Modal(document.getElementById('viewPOModal')).show();
                 })
                 .catch(error => {
                     console.error('Error loading PO details:', error);
                     Swal.fire({
                         icon: 'error',
                         title: 'Error Loading PO Details',
                         text: 'Please try again or contact support if the issue persists.',
                         confirmButtonColor: '#7F1734'
                     });
                 });
         }
         
         function updateRestockStatus(restockingId, newStatus) {
             const statusNames = {1: 'Pending', 2: 'Received', 3: 'Cancelled'};
             
             if (newStatus == 2) {
                 // For "Received" status, show quantity adjustment modal
                 openQuantityAdjustmentModal(restockingId);
             } else {
                 // For other statuses, use the original confirmation
                 const message = newStatus == 3 ? 'Cancel this restocking item?' : 'Update status?';
                 
                 Swal.fire({
                     title: 'Update Status',
                     text: message,
                     icon: 'question',
                     showCancelButton: true,
                     confirmButtonColor: '#7F1734',
                     cancelButtonColor: '#6c757d',
                     confirmButtonText: 'Yes, update it!',
                     cancelButtonText: 'Cancel'
                 }).then((result) => {
                     if (result.isConfirmed) {
                         submitStatusUpdate(restockingId, newStatus);
                     }
                 });
             }
         }
         
         function openQuantityAdjustmentModal(restockingId) {
             // Get restocking details via AJAX
             fetch(`restocking.php?action=get_restock_details&restocking_id=${restockingId}`)
                 .then(response => response.json())
                 .then(data => {
                     if (data.success) {
                         document.getElementById('adjust_restocking_id').value = restockingId;
                         document.getElementById('adjust_product_name').value = data.product_name;
                         document.getElementById('adjust_ordered_qty').value = data.quantity_added;
                         document.getElementById('adjust_received_qty').value = data.quantity_added; // Default to ordered quantity
                         
                         new bootstrap.Modal(document.getElementById('quantityAdjustmentModal')).show();
                     } else {
                         Swal.fire({
                             icon: 'error',
                             title: 'Error',
                             text: 'Could not load restocking details.',
                             confirmButtonColor: '#7F1734'
                         });
                     }
                 })
                 .catch(error => {
                     console.error('Error loading restocking details:', error);
                     Swal.fire({
                         icon: 'error',
                         title: 'Error',
                         text: 'Could not load restocking details.',
                         confirmButtonColor: '#7F1734'
                     });
                 });
         }
         
         function submitStatusUpdate(restockingId, newStatus, actualQuantity = null) {
             const form = document.createElement('form');
             form.method = 'POST';
             form.action = 'restocking.php';
             
             const restockingIdInput = document.createElement('input');
             restockingIdInput.type = 'hidden';
             restockingIdInput.name = 'restocking_id';
             restockingIdInput.value = restockingId;
             
             const statusInput = document.createElement('input');
             statusInput.type = 'hidden';
             statusInput.name = 'status_id';
             statusInput.value = newStatus;
             
             const updateStatusInput = document.createElement('input');
             updateStatusInput.type = 'hidden';
             updateStatusInput.name = 'update_status';
             updateStatusInput.value = '1';
             
             form.appendChild(restockingIdInput);
             form.appendChild(statusInput);
             form.appendChild(updateStatusInput);
             
             if (actualQuantity !== null) {
                 const quantityInput = document.createElement('input');
                 quantityInput.type = 'hidden';
                 quantityInput.name = 'actual_quantity_received';
                 quantityInput.value = actualQuantity;
                 form.appendChild(quantityInput);
             }
             
             document.body.appendChild(form);
             form.submit();
         }
         
         // Add validation for quantity adjustment modal
         document.addEventListener('DOMContentLoaded', function() {
             const quantityForm = document.getElementById('quantityAdjustmentForm');
             if (quantityForm) {
                 quantityForm.addEventListener('submit', function(e) {
                     const receivedQty = parseFloat(document.getElementById('adjust_received_qty').value);
                     const orderedQty = parseFloat(document.getElementById('adjust_ordered_qty').value);
                     
                     if (receivedQty < 0) {
                         e.preventDefault();
                         Swal.fire({
                             icon: 'error',
                             title: 'Invalid Quantity',
                             text: 'Received quantity cannot be negative.',
                             confirmButtonColor: '#7F1734'
                         });
                         return false;
                     }
                     
                     if (receivedQty > orderedQty * 1.2) {
                         e.preventDefault();
                         Swal.fire({
                             title: 'Over-Delivery Warning',
                             text: `You received ${receivedQty} units but only ordered ${orderedQty} units (${Math.round((receivedQty/orderedQty - 1) * 100)}% over). Are you sure this is correct?`,
                             icon: 'warning',
                             showCancelButton: true,
                             confirmButtonColor: '#7F1734',
                             cancelButtonColor: '#6c757d',
                             confirmButtonText: 'Yes, confirm',
                             cancelButtonText: 'Cancel'
                         }).then((result) => {
                             if (result.isConfirmed) {
                                 quantityForm.submit();
                             }
                         });
                         return false;
                     }
                     
                     if (receivedQty < orderedQty * 0.5) {
                         e.preventDefault();
                         Swal.fire({
                             title: 'Under-Delivery Warning',
                             text: `You received ${receivedQty} units but ordered ${orderedQty} units (${Math.round((1 - receivedQty/orderedQty) * 100)}% under). Are you sure this is correct?`,
                             icon: 'warning',
                             showCancelButton: true,
                             confirmButtonColor: '#7F1734',
                             cancelButtonColor: '#6c757d',
                             confirmButtonText: 'Yes, confirm',
                             cancelButtonText: 'Cancel'
                         }).then((result) => {
                             if (result.isConfirmed) {
                                 quantityForm.submit();
                             }
                         });
                         return false;
                     }
                 });
             }
         });
    </script>
</body>
</html>
