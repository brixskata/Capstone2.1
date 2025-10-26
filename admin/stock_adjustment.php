<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
include_once '../includes/batch_manager.php';
include_once '../includes/brand_stock_manager.php';
include_once '../includes/reorder_point_calculator.php';
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

// Initialize managers
$batchManager = new BatchManager($pdo);
$brandStockManager = new BrandStockManager($pdo);
$ropCalculator = new ReorderPointCalculator($pdo);

// Handle stock adjustment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    try {
        // Validate required fields
        $required_fields = ['product_id', 'adjustment_type', 'quantity', 'reason'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        $product_id = (int)$_POST['product_id'];
        $adjustment_type = $_POST['adjustment_type'];
        $quantity = (int)$_POST['quantity'];
        $reason = $_POST['reason'];
        $notes = $_POST['notes'] ?: null;
        $expiration_date = $_POST['expiration_date'] ?: null;
        $supplier_id = $_POST['supplier_id'] ?: null;
        $brand_id = $_POST['brand_id'] ?: null;
        
        // Validate supplier and brand are required for stock subtraction
        if ($adjustment_type === 'subtract') {
            if (empty($supplier_id)) {
                throw new Exception("Supplier is required when subtracting stock. You must specify which supplier's stock is being removed.");
            }
            if (empty($brand_id)) {
                throw new Exception("Brand is required when subtracting stock. You must specify which brand's stock is being removed.");
            }
        }

        // Validate quantity
        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than 0.");
        }
        
        // Validate expiration date for stock additions
        if ($adjustment_type === 'add' && !empty($expiration_date)) {
            $restock_date = date('Y-m-d'); // Use current date as restock date for adjustments
            $expiration_timestamp = strtotime($expiration_date);
            $restock_timestamp = strtotime($restock_date);
            $days_until_expiry = floor(($expiration_timestamp - $restock_timestamp) / (60 * 60 * 24));
            
            if ($days_until_expiry < 90) {
                throw new Exception("Expiration date is too close to adjustment date. Products must have at least 3 months (90 days) before expiration. Current: {$days_until_expiry} days.");
            }
        }

        $pdo->beginTransaction();

        // Validate supplier-product-brand relationship for stock subtraction
        if ($adjustment_type === 'subtract' && !empty($supplier_id) && !empty($brand_id)) {
            // Check if supplier provides this product
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM supplier_products 
                WHERE supplier_id = ? AND product_id = ? AND is_active = 1
            ");
            $stmt->execute([$supplier_id, $product_id]);
            $supplier_relationship_exists = $stmt->fetchColumn();
            
            if (!$supplier_relationship_exists) {
                // Get supplier and product names for error message
                $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$supplier_id]);
                $supplier_name = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $product_name = $stmt->fetchColumn();
                
                throw new Exception("Cannot adjust stock: {$supplier_name} does not supply {$product_name}. Only suppliers who provide this product can adjust its stock.");
            }
            
            // Check if this supplier-brand combination has batches for this product
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM product_batches 
                WHERE supplier_id = ? AND product_id = ? AND brand_id = ? AND quantity_remaining > 0 AND is_active = 1
            ");
            $stmt->execute([$supplier_id, $product_id, $brand_id]);
            $batch_exists = $stmt->fetchColumn();
            
            if (!$batch_exists) {
                // Get names for error message
                $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
                $stmt->execute([$supplier_id]);
                $supplier_name = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
                $stmt->execute([$brand_id]);
                $brand_name = $stmt->fetchColumn();
                
                $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $product_name = $stmt->fetchColumn();
                
                throw new Exception("Cannot adjust stock: No batches found for {$supplier_name} - {$brand_name} - {$product_name}. Only batches with remaining stock can be adjusted.");
            }
        }

        // Get current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();
        $previous_stock = $current_stock;

        // Calculate new stock
        switch ($adjustment_type) {
            case 'add':
                $new_stock = $current_stock + $quantity;
                break;
            case 'subtract':
                $new_stock = $current_stock - $quantity;
                if ($new_stock < 0) {
                    throw new Exception("Cannot subtract $quantity from current stock of $current_stock. Result would be negative.");
                }
                break;
            case 'set':
                $new_stock = $quantity;
                break;
            default:
                throw new Exception("Invalid adjustment type: $adjustment_type");
        }

        // Handle batch creation for stock additions
        if ($adjustment_type === 'add') {
            // Create a new batch for the added stock
            $batch_data = [
                'product_id' => $product_id,
                'supplier_id' => $supplier_id,
                'brand_id' => $brand_id,
                'quantity_received' => $quantity,
                'expiration_date' => $expiration_date,
                'received_date' => date('Y-m-d'),
                'created_by' => $_SESSION['user_id'],
                'reference_type' => 'adjustment',
                'notes' => "Stock adjustment: {$reason}"
            ];
            
            $batch_id = $batchManager->createBatch($batch_data);
        } elseif ($adjustment_type === 'subtract') {
            // Consume stock from existing batches (FIFO) - only from specified supplier and brand if provided
            $batches_used = $batchManager->consumeStock(
                $product_id, 
                $quantity, 
                'adjustment', 
                'stock_adjustment', 
                null, 
                $_SESSION['user_id'], 
                "Stock adjustment: {$reason}",
                $supplier_id, // Pass supplier_id to only consume from their batches
                $brand_id     // Pass brand_id to only consume from their brand batches
            );
        }

        // Update product_stock
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = ? WHERE product_id = ?");
        $stmt->execute([$new_stock, $product_id]);

        // Map adjustment type to proper ID
        $adjustment_type_map = [
            'add' => 1,      // Increase
            'subtract' => 2, // Decrease  
            'set' => 3       // Correction
        ];
        $adjustment_type_id = $adjustment_type_map[$adjustment_type] ?? 3;

        // Record stock adjustment (with optional supplier, brand, and expiration date)
        try {
            // Try to insert with all new columns first
            $stmt = $pdo->prepare("INSERT INTO stock_adjustment (product_id, adjustment_type_id, quantity, previous_stock, new_stock, reason, notes, supplier_id, brand_id, expiration_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $adjustment_type_id, $quantity, $previous_stock, $new_stock, $reason, $notes, $supplier_id, $brand_id, $expiration_date, $_SESSION['user_id']]);
        } catch (PDOException $e) {
            try {
                // Try to insert with supplier and expiration columns (without brand)
                $stmt = $pdo->prepare("INSERT INTO stock_adjustment (product_id, adjustment_type_id, quantity, previous_stock, new_stock, reason, notes, supplier_id, expiration_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $adjustment_type_id, $quantity, $previous_stock, $new_stock, $reason, $notes, $supplier_id, $expiration_date, $_SESSION['user_id']]);
            } catch (PDOException $e2) {
                // Fallback to original columns if new columns don't exist
                $stmt = $pdo->prepare("INSERT INTO stock_adjustment (product_id, adjustment_type_id, quantity, previous_stock, new_stock, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$product_id, $adjustment_type_id, $quantity, $previous_stock, $new_stock, $reason, $notes, $_SESSION['user_id']]);
            }
        }
        
        $adjustment_id = $pdo->lastInsertId();
        
        // Update batch reference if it was created
        if (isset($batch_id)) {
            $stmt = $pdo->prepare("UPDATE product_batches SET reference_id = ? WHERE batch_id = ?");
            $stmt->execute([$adjustment_id, $batch_id]);
        }

        // Record stock movement
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 4, ?, ?, ?, ?, ?, 'adjustment', ?)");
        $stmt->execute([$product_id, $quantity, $previous_stock, $new_stock, $reason, $adjustment_id, $_SESSION['user_id']]);

        $pdo->commit();

        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();

        logHistory($pdo, 'Stock Adjustment', "Product: $product_name, Type: $adjustment_type, Quantity: $quantity, Reason: $reason", $_SESSION['username']);
        $_SESSION['success'] = "Stock adjustment recorded successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error adjusting stock: " . $e->getMessage();
    }
    header("Location: stock_adjustment.php");
    exit;
}

// Fetch products with current stock from product_batches and brand-specific reorder points
$stmt = $pdo->query("
    SELECT 
        p.product_id AS id,
        p.product_name AS name,
        c.category_name as category_name,
        b.name as brand_name,
        s.name as supplier_name,
        u.name as uom_name,
        COALESCE(stock_summary.total_stock, 0) AS stock,
        COALESCE(AVG(bps.reorder_point), COALESCE(ps.reorder_point, 10)) AS reorder_point,
        COALESCE(AVG(bps.average_daily_sales), 0) AS avg_ads,
        COALESCE(AVG(bps.movement_type), 'Non-Moving') AS movement_type,
        stock_summary.brand_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    LEFT JOIN (
        SELECT 
            pb.product_id,
            SUM(pb.quantity_remaining) as total_stock,
            COUNT(DISTINCT pb.brand_id) as brand_count
        FROM product_batches pb
        WHERE pb.is_active = 1 AND pb.quantity_remaining > 0
        GROUP BY pb.product_id
    ) stock_summary ON stock_summary.product_id = p.product_id
    LEFT JOIN brand_product_stock bps ON bps.product_id = p.product_id
    WHERE p.is_archive = 0
    GROUP BY p.product_id, p.product_name, c.category_name, b.name, s.name, u.name, stock_summary.total_stock, stock_summary.brand_count, ps.reorder_point
    ORDER BY p.product_name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers for the form
$stmt = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_archive = 0 ORDER BY name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Handle AJAX request for getting brands by product
if ($_SERVER['REQUEST_METHOD'] == 'GET' && isset($_GET['action']) && $_GET['action'] == 'get_brands_by_product') {
    $product_id = (int)$_GET['product_id'];
    
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            b.id,
            b.name
        FROM product_batches pb
        INNER JOIN brands b ON pb.brand_id = b.id
        WHERE pb.product_id = ? 
        AND pb.is_active = 1
        AND b.is_archived = 0
        ORDER BY b.name
    ");
    $stmt->execute([$product_id]);
    $product_brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($product_brands);
    exit;
}

// Fetch recent stock adjustments with supplier and brand information
try {
    // Try to fetch with supplier and brand information first
    $stmt = $pdo->query("
        SELECT 
            sa.*,
            p.product_name,
            u.name as uom_name,
            at.name as adjustment_type_name,
            s.name as supplier_name,
            b.name as brand_name,
            CASE 
                WHEN sa.brand_id IS NOT NULL AND b.name IS NOT NULL THEN b.name
                WHEN sa.brand_id IS NOT NULL THEN CONCAT('Brand ID: ', sa.brand_id)
                ELSE NULL
            END as display_brand_name
        FROM stock_adjustment sa
        JOIN products p ON sa.product_id = p.product_id
        LEFT JOIN uom u ON p.uom_id = u.uom_id
        LEFT JOIN adjustment_types at ON sa.adjustment_type_id = at.adjustment_type_id
        LEFT JOIN suppliers s ON sa.supplier_id = s.supplier_id
        LEFT JOIN brands b ON sa.brand_id = b.id
        ORDER BY sa.created_at DESC
        LIMIT 20
    ");
    $recent_adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    try {
        // Try to fetch with supplier information (without brand)
        $stmt = $pdo->query("
            SELECT 
                sa.*,
                p.product_name,
                u.name as uom_name,
                at.name as adjustment_type_name,
                s.name as supplier_name,
                NULL as brand_name
            FROM stock_adjustment sa
            JOIN products p ON sa.product_id = p.product_id
            LEFT JOIN uom u ON p.uom_id = u.uom_id
            LEFT JOIN adjustment_types at ON sa.adjustment_type_id = at.adjustment_type_id
            LEFT JOIN suppliers s ON sa.supplier_id = s.supplier_id
            ORDER BY sa.created_at DESC
            LIMIT 20
        ");
        $recent_adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e2) {
        // Fallback query without supplier and brand information if columns don't exist
        $stmt = $pdo->query("
            SELECT 
                sa.*,
                p.product_name,
                u.name as uom_name,
                at.name as adjustment_type_name,
                NULL as supplier_name,
                NULL as brand_name
            FROM stock_adjustment sa
            JOIN products p ON sa.product_id = p.product_id
            LEFT JOIN uom u ON p.uom_id = u.uom_id
            LEFT JOIN adjustment_types at ON sa.adjustment_type_id = at.adjustment_type_id
            ORDER BY sa.created_at DESC
            LIMIT 20
        ");
        $recent_adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// Calculate adjustment statistics
$total_adjustments = $pdo->query("SELECT COUNT(*) FROM stock_adjustment")->fetchColumn();
$adjustments_today = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$adjustments_this_month = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();

// Calculate adjustment type statistics
$increase_count = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE adjustment_type_id = 1")->fetchColumn();
$decrease_count = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE adjustment_type_id = 2")->fetchColumn();
$correction_count = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE adjustment_type_id = 3")->fetchColumn();

// Calculate total quantity adjusted
$total_increased = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_adjustment WHERE adjustment_type_id = 1")->fetchColumn();
$total_decreased = $pdo->query("SELECT COALESCE(SUM(quantity), 0) FROM stock_adjustment WHERE adjustment_type_id = 2")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustment - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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

        /* Analytics Cards - Light Version */
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
        
        .adjustment-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
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
        
        /* SweetAlert2 Custom Styles */
        .swal2-popup-rounded {
            border-radius: 15px !important;
        }
        
        .swal2-confirm-rounded {
            border-radius: 8px !important;
        }
        
        .swal2-cancel-rounded {
            border-radius: 8px !important;
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
                            <i class="fa fa-edit me-3"></i>Stock Adjustment
                        </h2>
                        <p class="mb-0 opacity-75">Correct discrepancies between physical count and system records</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                        <i class="fa fa-edit me-2"></i>Adjust Stock
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-calculator"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $total_adjustments ?></h3>
                            <p class="card-label">Total Adjustments</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-calendar-day"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $adjustments_today ?></h3>
                            <p class="card-label">Today</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-calendar-alt"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $adjustments_this_month ?></h3>
                            <p class="card-label">This Month</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-boxes"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= count($products) ?></h3>
                            <p class="card-label">Products</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Adjustment Type Statistics -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-arrow-up"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $increase_count ?></h3>
                            <p class="card-label">Stock Increases</p>
                            <small class="text-white-50">+<?= number_format($total_increased) ?> units</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-arrow-down"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $decrease_count ?></h3>
                            <p class="card-label">Stock Decreases</p>
                            <small class="text-white-50">-<?= number_format($total_decreased) ?> units</small>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-edit"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $correction_count ?></h3>
                            <p class="card-label">Stock Corrections</p>
                            <small class="text-white-50">Manual fixes</small>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Products List -->
            <div class="table-card mb-4">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-list-alt me-2"></i>Products Available for Adjustment
                    </h5>
                </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Category</th>
                            <th class="fw-semibold">Current Stock</th>
                            <th class="fw-semibold">Status</th>
                            <th class="fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($product['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($product['supplier_name']) ?></small>
                                    <?php if ($product['brand_count'] > 1): ?>
                                        <br><small class="text-info"><i class="fa fa-tags me-1"></i><?= $product['brand_count'] ?> brands</small>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $stock_status = $ropCalculator->getStockStatus($product['stock'], $product['reorder_point']);
                                    $status_colors = [
                                        'In Stock' => ['bg' => '#d4edda', 'color' => '#155724'],
                                        'Low Stock' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                        'Out of Stock' => ['bg' => '#f5c6cb', 'color' => '#721c24']
                                    ];
                                    $status_color = $status_colors[$stock_status] ?? $status_colors['In Stock'];
                                    ?>
                                    <span class="badge" style="background: <?= $status_color['bg'] ?>; color: <?= $status_color['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                        <?= $stock_status ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm" style="background: #cce5ff; color: #004085; border-radius: 8px;" onclick="openAdjustModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= (int)$product['stock'] ?>)">
                                        <i class="fa fa-edit me-1"></i>Adjust
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

            <!-- Recent Adjustments -->
            <div class="table-card">
                <div class="card-header bg-transparent border-0 p-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fas fa-history me-2"></i>Recent Stock Adjustments
                        </h5>
                        <small class="text-muted">Showing last 20 adjustments</small>
                    </div>
                </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Date & Time</th>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Type</th>
                            <th class="fw-semibold">Quantity</th>
                            <th class="fw-semibold">Stock Change</th>
                            <th class="fw-semibold">Supplier</th>
                            <th class="fw-semibold">Reason</th>
                            <th class="fw-semibold">Notes</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recent_adjustments)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="fa fa-inbox fa-2x mb-2"></i><br>
                                    No stock adjustments found
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($recent_adjustments as $adjustment): ?>
                                <tr>
                                    <td>
                                        <div class="fw-semibold"><?= date('M d, Y', strtotime($adjustment['created_at'])) ?></div>
                                        <small class="text-muted"><?= date('H:i', strtotime($adjustment['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($adjustment['product_name']) ?></div>
                                        <small class="text-muted">ID: <?= $adjustment['product_id'] ?></small>
                                    </td>
                                    <td>
                                        <?php
                                        $type = $adjustment['adjustment_type_id'];
                                        if ($type == 1) {
                                            echo '<span class="badge" style="background: #d4edda; color: #155724; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><i class="fa fa-plus me-1"></i>Increase</span>';
                                        } elseif ($type == 2) {
                                            echo '<span class="badge" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><i class="fa fa-minus me-1"></i>Decrease</span>';
                                        } else {
                                            echo '<span class="badge" style="background: #cce5ff; color: #004085; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><i class="fa fa-edit me-1"></i>Correction</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= $adjustment['quantity'] ?></span>
                                        <small class="text-muted d-block"><?= htmlspecialchars($adjustment['uom_name']) ?></small>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <span class="text-muted me-2"><?= $adjustment['previous_stock'] ?></span>
                                            <i class="fa fa-arrow-right text-muted me-2"></i>
                                            <span class="fw-semibold"><?= $adjustment['new_stock'] ?></span>
                                        </div>
                                        <?php 
                                        $change = $adjustment['new_stock'] - $adjustment['previous_stock'];
                                        if ($change > 0) {
                                            echo '<small class="text-success"><i class="fa fa-arrow-up me-1"></i>+' . $change . '</small>';
                                        } elseif ($change < 0) {
                                            echo '<small class="text-danger"><i class="fa fa-arrow-down me-1"></i>' . $change . '</small>';
                                        } else {
                                            echo '<small class="text-muted">No change</small>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if (!empty($adjustment['supplier_name'])): ?>
                                            <span class="badge" style="background: #e2e3e5; color: #383d41; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><?= htmlspecialchars($adjustment['supplier_name']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= htmlspecialchars($adjustment['reason']) ?></span>
                                    </td>
                                    <td>
                                        <?php if (!empty($adjustment['notes'])): ?>
                                            <span class="text-muted"><?= htmlspecialchars($adjustment['notes']) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted">-</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        </div>
    </main>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="stock_adjustment.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Stock Adjustment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="adjust_stock" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product</label>
                                <select name="product_id" id="adjustProductId" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>" data-stock="<?= $product['stock'] ?>"><?= htmlspecialchars($product['name']) ?> (Current: <?= $product['stock'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Adjustment Type</label>
                                <select name="adjustment_type" id="adjustmentType" class="form-select" required>
                                    <option value="add">Add Stock</option>
                                    <option value="subtract">Subtract Stock</option>
                                    <option value="set">Set Stock Level</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Quantity</label>
                                <input type="number" name="quantity" id="quantityInput" class="form-control" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reason</label>
                                <select name="reason" class="form-select" required>
                                    <option value="">Select Reason</option>
                                    <option value="Damaged Items">Damaged Items</option>
                                    <option value="Theft/Loss">Theft/Loss</option>
                                    <option value="Supplier Return">Supplier Return</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-md-6" id="brandField">
                                <label class="form-label fw-semibold">Brand <span class="text-danger" id="brandRequired" style="display: none;">*</span></label>
                                <select name="brand_id" id="brandSelect" class="form-select">
                                    <option value="">Select Product First</option>
                                </select>
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>
                                    <span id="brandHelperMessage">Brands will be filtered based on the selected product</span>
                                </div>
                            </div>
                            <div class="col-md-6" id="supplierField" style="display: none;">
                                <label class="form-label fw-semibold">Supplier <span class="text-danger" id="supplierRequired" style="display: none;">*</span></label>
                                <select name="supplier_id" id="supplierSelect" class="form-select">
                                    <option value="">Select Product First</option>
                                </select>
                                <div class="form-text" id="supplierHelperText">
                                    <i class="fa fa-info-circle me-1"></i>
                                    <span id="supplierHelperMessage">Suppliers will be filtered based on the selected product</span>
                                </div>
                            </div>
                            <div class="col-md-6" id="expirationField" style="display: none;">
                                <label class="form-label fw-semibold">Expiration Date</label>
                                <input type="date" name="expiration_date" id="expirationDateInput" class="form-control">
                                <div class="form-text">
                                    <i class="fa fa-info-circle me-1"></i>
                                    Must be at least 3 months (90 days) after adjustment date to prevent waste
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Additional details about this adjustment..."></textarea>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle me-2"></i>
                                    <strong>Current Stock:</strong> <span id="currentStockDisplay">-</span>
                                    <br>
                                    <strong>New Stock Will Be:</strong> <span id="newStockDisplay">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734; border-radius: 8px;">
                            <i class="fa fa-save me-2"></i>Adjust Stock
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        function openAdjustModal(productId, productName, currentStock) {
            const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
            
            // Show modal first, then set the value after Select2 is initialized
            modal.show();
            
            // Wait for modal to be shown and Select2 to be initialized
            $('#adjustStockModal').on('shown.bs.modal', function () {
                const select = document.querySelector('#adjustStockModal select[name="product_id"]');
                if (select && $(select).hasClass('select2-hidden-accessible')) {
                    // Set value using Select2
                    $(select).val(productId).trigger('change');
                    // Load brands and suppliers for this product
                    loadBrandsForProduct(productId);
                    loadSuppliersForProduct(productId);
                }
                updateStockDisplay();
            });
        }

        function updateStockDisplay() {
            const productSelect = document.getElementById('adjustProductId');
            const adjustmentType = document.getElementById('adjustmentType');
            const quantityInput = document.getElementById('quantityInput');
            const currentStockDisplay = document.getElementById('currentStockDisplay');
            const newStockDisplay = document.getElementById('newStockDisplay');

            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const currentStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const type = adjustmentType.value;

            currentStockDisplay.textContent = currentStock;

            let newStock = currentStock;
            if (type === 'add') {
                newStock = currentStock + quantity;
            } else if (type === 'subtract') {
                newStock = currentStock - quantity;
            } else if (type === 'set') {
                newStock = quantity;
            }

            newStockDisplay.textContent = newStock;
        }

        // Function to load brands for a selected product
        function loadBrandsForProduct(productId) {
            const brandSelect = document.getElementById('brandSelect');
            
            if (!productId) {
                brandSelect.innerHTML = '<option value="">Select Product First</option>';
                // Update Select2 if it's initialized
                if ($(brandSelect).hasClass('select2-hidden-accessible')) {
                    $(brandSelect).trigger('change');
                }
                return;
            }
            
            // Show loading state
            brandSelect.innerHTML = '<option value="">Loading brands...</option>';
            brandSelect.disabled = true;
            
            // Update Select2 if it's initialized
            if ($(brandSelect).hasClass('select2-hidden-accessible')) {
                $(brandSelect).trigger('change');
            }
            
            fetch(`stock_adjustment.php?action=get_brands_by_product&product_id=${productId}`)
                .then(response => response.json())
                .then(brands => {
                    brandSelect.innerHTML = '<option value="">Select Brand</option>';
                    
                    if (brands.length === 0) {
                        brandSelect.innerHTML += '<option value="" disabled>No brands found for this product</option>';
                    } else {
                        brands.forEach(brand => {
                            const option = document.createElement('option');
                            option.value = brand.id;
                            option.textContent = brand.name;
                            brandSelect.appendChild(option);
                        });
                    }
                    
                    brandSelect.disabled = false;
                    
                    // Update Select2 if it's initialized
                    if ($(brandSelect).hasClass('select2-hidden-accessible')) {
                        $(brandSelect).trigger('change');
                    }
                })
                .catch(error => {
                    console.error('Error loading brands:', error);
                    brandSelect.innerHTML = '<option value="">Error loading brands</option>';
                    brandSelect.disabled = false;
                    
                    // Update Select2 if it's initialized
                    if ($(brandSelect).hasClass('select2-hidden-accessible')) {
                        $(brandSelect).trigger('change');
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
            
            fetch(`stock_adjustment.php?action=get_suppliers_by_product&product_id=${productId}`)
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

        // Initialize Select2 and event listeners when DOM is ready
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize Select2 on product and supplier dropdowns when modal is shown
            $('#adjustStockModal').on('shown.bs.modal', function () {
                const productSelect = document.querySelector('#adjustStockModal select[name="product_id"]');
                if (productSelect && !$(productSelect).hasClass('select2-hidden-accessible')) {
                    try {
                        $(productSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Search and select a product...',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#adjustStockModal')
                        });
                        
                        // Product selection change handler
                        $(productSelect).on('change', function() {
                            updateStockDisplay();
                            loadBrandsForProduct(this.value);
                            loadSuppliersForProduct(this.value);
                        });
                        
                        console.log('Product Select2 initialized successfully');
                    } catch (error) {
                        console.error('Error initializing Product Select2:', error);
                    }
                }
                
                // Initialize Select2 on brand dropdown
                const brandSelect = document.querySelector('#adjustStockModal select[name="brand_id"]');
                if (brandSelect && !$(brandSelect).hasClass('select2-hidden-accessible')) {
                    try {
                        $(brandSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Search and select a brand...',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#adjustStockModal')
                        });
                        
                        console.log('Brand Select2 initialized successfully');
                    } catch (error) {
                        console.error('Error initializing Brand Select2:', error);
                    }
                }
                
                // Initialize Select2 on supplier dropdown
                const supplierSelect = document.querySelector('#adjustStockModal select[name="supplier_id"]');
                if (supplierSelect && !$(supplierSelect).hasClass('select2-hidden-accessible')) {
                    try {
                        $(supplierSelect).select2({
                            theme: 'bootstrap-5',
                            placeholder: 'Search and select a supplier...',
                            allowClear: true,
                            width: '100%',
                            dropdownParent: $('#adjustStockModal')
                        });
                        
                        console.log('Supplier Select2 initialized successfully');
                    } catch (error) {
                        console.error('Error initializing Supplier Select2:', error);
                    }
                }
            });
            
            // Clean up Select2 when modal is hidden
            $('#adjustStockModal').on('hidden.bs.modal', function () {
                const productSelect = document.querySelector('#adjustStockModal select[name="product_id"]');
                if (productSelect && $(productSelect).hasClass('select2-hidden-accessible')) {
                    $(productSelect).select2('destroy');
                }
                
                const brandSelect = document.querySelector('#adjustStockModal select[name="brand_id"]');
                if (brandSelect && $(brandSelect).hasClass('select2-hidden-accessible')) {
                    $(brandSelect).select2('destroy');
                }
                
                const supplierSelect = document.querySelector('#adjustStockModal select[name="supplier_id"]');
                if (supplierSelect && $(supplierSelect).hasClass('select2-hidden-accessible')) {
                    $(supplierSelect).select2('destroy');
                }
            });
            
            // Add event listeners for other form elements
            document.getElementById('adjustmentType').addEventListener('change', function() {
                updateStockDisplay();
                toggleAdditionalFields();
            });
            document.getElementById('quantityInput').addEventListener('input', updateStockDisplay);
            
            // Add expiration date validation listener
            const expirationDateInput = document.getElementById('expirationDateInput');
            if (expirationDateInput) {
                expirationDateInput.addEventListener('change', validateExpirationDate);
            }
        });

        function toggleAdditionalFields() {
            const adjustmentType = document.getElementById('adjustmentType').value;
            const brandField = document.getElementById('brandField');
            const supplierField = document.getElementById('supplierField');
            const expirationField = document.getElementById('expirationField');
            const supplierHelperMessage = document.getElementById('supplierHelperMessage');
            const supplierRequired = document.getElementById('supplierRequired');
            const supplierSelect = document.getElementById('supplierSelect');
            const brandSelect = document.getElementById('brandSelect');
            const brandRequired = document.getElementById('brandRequired');
            
            if (adjustmentType === 'add') {
                brandField.style.display = 'block';
                supplierField.style.display = 'block';
                expirationField.style.display = 'block';
                supplierRequired.style.display = 'none';
                supplierSelect.required = false;
                brandSelect.required = false;
                brandRequired.style.display = 'none';
                supplierHelperMessage.textContent = 'Suppliers will be filtered based on the selected product';
                
                // Set default expiration date to 3 months from today
                const today = new Date();
                const expirationDate = new Date(today);
                expirationDate.setMonth(expirationDate.getMonth() + 3);
                document.getElementById('expirationDateInput').value = expirationDate.toISOString().split('T')[0];
                validateExpirationDate();
            } else if (adjustmentType === 'subtract') {
                brandField.style.display = 'block';
                supplierField.style.display = 'block';
                expirationField.style.display = 'none';
                supplierRequired.style.display = 'inline';
                supplierSelect.required = true;
                brandSelect.required = true;
                brandRequired.style.display = 'inline';
                supplierHelperMessage.textContent = 'Select the supplier and brand for the items being removed (required for accurate stock tracking)';
                
                // Clear validation messages when hiding expiration field
                const existingAlert = document.querySelector('.expiration-validation-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
            } else {
                brandField.style.display = 'block';
                supplierField.style.display = 'none';
                expirationField.style.display = 'none';
                supplierRequired.style.display = 'none';
                supplierSelect.required = false;
                brandSelect.required = false;
                brandRequired.style.display = 'none';
                // Clear validation messages when hiding expiration field
                const existingAlert = document.querySelector('.expiration-validation-alert');
                if (existingAlert) {
                    existingAlert.remove();
                }
            }
        }
        
        // Function to validate expiration date
        function validateExpirationDate() {
            const expirationDateInput = document.getElementById('expirationDateInput');
            const adjustmentDate = new Date(); // Current date for adjustments
            const expirationDate = new Date(expirationDateInput.value);
            
            if (expirationDateInput.value && !isNaN(expirationDate)) {
                const daysUntilExpiry = Math.floor((expirationDate - adjustmentDate) / (1000 * 60 * 60 * 24));
                
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
                        <strong>Warning:</strong> Expiration date is only ${daysUntilExpiry} days after adjustment date. 
                        Products must have at least 3 months (90 days) before expiration to prevent waste.
                    `;
                    expirationDateInput.parentNode.appendChild(alertDiv);
                    
                    // Disable submit button
                    const submitBtn = document.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.title = 'Expiration date is too close to adjustment date';
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
    </script>
</body>
</html>