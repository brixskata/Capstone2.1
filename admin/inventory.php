
<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/batch_manager.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle restocking form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock'])) {
    try {
        // Validate required fields
        $required_fields = ['product_id', 'supplier_id', 'quantity_added', 'cost_per_unit', 'restock_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        $product_id = (int)$_POST['product_id'];
        $supplier_id = (int)$_POST['supplier_id'];
        $quantity_added = (int)$_POST['quantity_added'];
        $cost_per_unit = (float)$_POST['cost_per_unit'];
        $total_cost = $quantity_added * $cost_per_unit;
        $restock_date = $_POST['restock_date'];
        $expected_delivery = $_POST['expected_delivery'] ?: null;
        $notes = $_POST['notes'] ?: null;

        // Validate positive values
        if ($quantity_added <= 0) {
            throw new Exception("Quantity added must be greater than 0.");
        }
        if ($cost_per_unit < 0) {
            throw new Exception("Cost per unit cannot be negative.");
        }

        // Start transaction for restocking
        $pdo->beginTransaction();
        
        try {
            // Insert restocking record (without cost columns)
            $stmt = $pdo->prepare("INSERT INTO restocking (product_id, supplier_id, quantity_added, restock_date, expected_delivery, status_id, notes, created_by) VALUES (?, ?, ?, ?, ?, 1, ?, ?)");
            $stmt->execute([$product_id, $supplier_id, $quantity_added, $restock_date, $expected_delivery, $notes, $_SESSION['user_id']]);
            $restock_id = $pdo->lastInsertId();

            // Update cost_price in product_pricing table (keep as fallback only)
            // $stmt = $pdo->prepare("UPDATE product_pricing SET cost_price = ? WHERE product_id = ?");
            // $stmt->execute([$cost_per_unit, $product_id]);

            // Update product_stock current_stock and last_restock_date
            $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ? WHERE product_id = ?");
            $stmt->execute([$quantity_added, $restock_date, $product_id]);
            
            // Create batch for the restocked items
            $batchManager = new BatchManager($pdo);
            $batch_id = $batchManager->createBatch([
                'product_id' => $product_id,
                'supplier_id' => $supplier_id,
                'quantity_received' => $quantity_added,
                'unit_cost' => $cost_per_unit,
                'received_date' => $restock_date,
                'created_by' => $_SESSION['user_id'],
                'reference_type' => 'restock',
                'reference_id' => $restock_id,
                'notes' => $notes
            ]);
            
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Fetch new current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();

        // Record stock movement (use stock_movements schema)
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 1, ?, ?, ?, 'Restocking', ?, 'restock', ?)");
        $stmt->execute([$product_id, $quantity_added, $current_stock - $quantity_added, $current_stock, $restock_id, $_SESSION['user_id']]);

        logHistory($pdo, 'Restocking', "Product ID: $product_id, Quantity: $quantity_added, Cost: ₱$total_cost", $_SESSION['username']);
        $_SESSION['success'] = "Restocking recorded successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error recording restocking: " . $e->getMessage();
    }
    header("Location: inventory.php");
    exit;
}

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

        // Validate quantity
        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than 0.");
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

        // Start transaction for stock adjustment
        $pdo->beginTransaction();
        
        try {
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

            // Record stock adjustment
            $stmt = $pdo->prepare("INSERT INTO stock_adjustment (product_id, adjustment_type_id, quantity, previous_stock, new_stock, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$product_id, $adjustment_type_id, $quantity, $previous_stock, $new_stock, $reason, $notes, $_SESSION['user_id']]);
            $adjustment_id = $pdo->lastInsertId();
            
            // Handle batch operations based on adjustment type
            $batchManager = new BatchManager($pdo);
            
            if ($adjustment_type === 'add') {
                // Create a batch for added stock
                $batch_id = $batchManager->createBatch([
                    'product_id' => $product_id,
                    'quantity_received' => $quantity,
                    'received_date' => date('Y-m-d'),
                    'created_by' => $_SESSION['user_id'],
                    'reference_type' => 'adjustment',
                    'reference_id' => $adjustment_id,
                    'notes' => "Stock adjustment: $reason"
                ]);
            } elseif ($adjustment_type === 'subtract') {
                // Consume from batches using FIFO
                $batches_used = $batchManager->consumeStock(
                    $product_id,
                    $quantity,
                    'adjustment',
                    'adjustment',
                    $adjustment_id,
                    $_SESSION['user_id'],
                    "Stock adjustment: $reason"
                );
            }
            
            $pdo->commit();
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }

        // Record stock movement
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 4, ?, ?, ?, ?, ?, 'adjustment', ?)");
        $stmt->execute([$product_id, $quantity, $previous_stock, $new_stock, $reason, $adjustment_id, $_SESSION['user_id']]);

        logHistory($pdo, 'Stock Adjustment', "Product ID: $product_id, Type: $adjustment_type, Quantity: $quantity, Reason: $reason", $_SESSION['username']);
        $_SESSION['success'] = "Stock adjustment recorded successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adjusting stock: " . $e->getMessage();
    }
    header("Location: inventory.php");
    exit;
}

// Fetch products with inventory data
$stmt = $pdo->query("
    SELECT 
        p.product_id AS id,
        p.product_name AS name,
        c.category_name as category_name,
        b.name as brand_name,
        s.name as supplier_name,
        u.name as uom_name,
        COALESCE(ps.current_stock,0) AS stock,
        COALESCE(ps.reorder_point, 10) AS reorder_point,
        ps.last_restock_date,
        (SELECT COUNT(*) FROM stock_movements WHERE product_id = p.product_id AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) as movements_30_days,
        (SELECT COUNT(*) FROM restocking WHERE product_id = p.product_id AND status_id = 2) as restock_count,
        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1
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

// Fetch suppliers for restocking
$stmt = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_archive = 0");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate inventory statistics
$total_products = count($products);
$low_stock_products = count(array_filter($products, fn($p) => (int)$p['stock'] <= (int)($p['reorder_point'] ?? 10)));
$out_of_stock_products = count(array_filter($products, fn($p) => (int)$p['stock'] === 0));

// Calculate total inventory value efficiently with single query
$total_inventory_value = 0;
try {
    $stmt = $pdo->query("
        SELECT SUM(COALESCE(ps.current_stock, 0) * COALESCE(pp.cost_price, 0)) as total_value
        FROM products p
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE p.is_archive = 0
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_inventory_value = (float)($result['total_value'] ?? 0);
} catch (Exception $e) {
    // Fallback to 0 if query fails
    $total_inventory_value = 0;
}

// Prepare chart data
// Category distribution
$stmt = $pdo->query("
    SELECT 
        c.category_name,
        COUNT(p.product_id) as product_count,
        SUM(COALESCE(ps.current_stock, 0)) as total_stock
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id AND p.is_archive = 0
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    GROUP BY c.category_id, c.category_name
    HAVING product_count > 0
    ORDER BY product_count DESC
");
$category_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stock status distribution
$stock_status_data = [
    'In Stock' => count(array_filter($products, fn($p) => (int)$p['stock'] > (int)($p['reorder_point'] ?? 10))),
    'Low Stock' => count(array_filter($products, fn($p) => (int)$p['stock'] <= (int)($p['reorder_point'] ?? 10) && (int)$p['stock'] > 0)),
    'Out of Stock' => count(array_filter($products, fn($p) => (int)$p['stock'] === 0))
];

// Recent stock movements (last 7 days)
$stmt = $pdo->query("
    SELECT 
        DATE(sm.created_at) as movement_date,
        COUNT(sm.stockmovement_id) as movement_count,
        SUM(CASE WHEN sm.quantity > 0 THEN sm.quantity ELSE 0 END) as stock_in,
        SUM(CASE WHEN sm.quantity < 0 THEN ABS(sm.quantity) ELSE 0 END) as stock_out
    FROM stock_movements sm
    WHERE sm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY DATE(sm.created_at)
    ORDER BY movement_date
");
$movement_trend = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Top products by stock value
$stmt = $pdo->query("
    SELECT 
        p.product_name,
        COALESCE(ps.current_stock, 0) as current_stock,
        COALESCE(pp.cost_price, 0) as cost_price,
        (COALESCE(ps.current_stock, 0) * COALESCE(pp.cost_price, 0)) as stock_value
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
    WHERE p.is_archive = 0 AND COALESCE(ps.current_stock, 0) > 0
    ORDER BY stock_value DESC
    LIMIT 10
");
$top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Product movement analysis (last 30 days)
$stmt = $pdo->query("
    SELECT 
        p.product_id,
        p.product_name,
        COUNT(sm.stockmovement_id) as total_movements,
        COALESCE(ps.current_stock, 0) as current_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    LEFT JOIN stock_movements sm ON p.product_id = sm.product_id 
        AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    WHERE p.is_archive = 0
    GROUP BY p.product_id, p.product_name, ps.current_stock
    ORDER BY total_movements DESC
");
$movement_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Classify products by movement - removed medium moving category
$fast_moving = 0;
$slow_moving = 0;
$non_moving = 0;

foreach ($movement_analysis as $product) {
    $movement_rate = $product['total_movements'] > 0 ? round(($product['total_movements'] / 30) * 100, 1) : 0;
    
    if ($product['total_movements'] == 0) {
        $non_moving++;
    } elseif ($movement_rate > 7 || $product['total_movements'] > 6) {
        $fast_moving++;
    } else {
        $slow_moving++;
    }
}

$movement_categories = [
    'Fast Moving' => $fast_moving,
    'Slow Moving' => $slow_moving,
    'Non Moving' => $non_moving
];

// Fetch recent stock movements
$stmt = $pdo->query("
    SELECT sm.*, p.product_name as product_name, COALESCE(ps.current_stock,0) as current_stock
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.product_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    ORDER BY sm.created_at DESC
    LIMIT 10
");
$recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        .product-image {
            width: 40px;
            height: 40px;
            object-fit: contain;
            border-radius: 6px;
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
      <div class="page-header">
        <h2><i class="fas fa-warehouse me-2"></i>Inventory Overview</h2>
      </div>

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

      <!-- Quick Access Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card" style="cursor: pointer;" onclick="window.location.href='restocking.php'">
            <div class="card-icon">
              <i class="fas fa-plus-circle"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">Restock</h3>
              <p class="card-label">Record new stock entries</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card" style="cursor: pointer;" onclick="window.location.href='stock_adjustment.php'">
            <div class="card-icon">
              <i class="fas fa-edit"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">Adjust</h3>
              <p class="card-label">Correct discrepancies</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card" style="cursor: pointer;" onclick="window.location.href='stock_levels.php'">
            <div class="card-icon">
              <i class="fas fa-chart-line"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">Levels</h3>
              <p class="card-label">Monitor real-time levels</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card" style="cursor: pointer;" onclick="window.location.href='stock_movements.php'">
            <div class="card-icon">
              <i class="fas fa-exchange-alt"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">Movements</h3>
              <p class="card-label">Track product performance</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Inventory Statistics Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-boxes"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $total_products ?></h3>
              <p class="card-label">Total Products</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $low_stock_products ?></h3>
              <p class="card-label">Low Stock Items</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-times-circle"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $out_of_stock_products ?></h3>
              <p class="card-label">Out of Stock</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-money-bill"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">₱<?= number_format($total_inventory_value, 2) ?></h3>
              <p class="card-label">Total Value</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Product Movement Categories and Stock Status Distribution -->
      <div class="row g-4 mb-4">
        <!-- Product Movement Categories -->
        <div class="col-lg-6">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-bar me-2"></i>Product Movement Categories (Last 30 Days)
              </h5>
            </div>
            <div class="card-body">
              <canvas id="movementCategoriesChart" height="300"></canvas>
            </div>
          </div>
        </div>

        <!-- Stock Status Distribution Chart -->
        <div class="col-lg-6">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-donut me-2"></i>Stock Status Distribution
              </h5>
            </div>
            <div class="card-body">
              <canvas id="stockStatusChart" height="300"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Stock Movement Trend -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-line me-2"></i>Stock Movement Trend (Last 7 Days)
              </h5>
            </div>
            <div class="card-body">
              <canvas id="movementTrendChart" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Products by Value -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-trophy me-2"></i>Top Products by Stock Value
              </h5>
            </div>
            <div class="card-body">
              <canvas id="topProductsChart" height="300"></canvas>
            </div>
          </div>
        </div>
      </div>


      <!-- Inventory Table -->
      <div class="table-card">
        <div class="card-header bg-transparent border-0 p-4">
          <h5 class="fw-bold mb-0 text-dark">
            <i class="fas fa-list-alt me-2"></i>Current Inventory Levels
          </h5>
        </div>
        <div class="table-responsive">
          <table class="table table-hover mb-0">
            <thead class="table-light">
              <tr>
                <th class="fw-semibold text-dark">Product</th>
                <th class="fw-semibold text-dark">Category</th>
                <th class="fw-semibold text-dark">Current Stock</th>
                <th class="fw-semibold text-dark">Reorder Point</th>
                <th class="fw-semibold text-dark">Status</th>
                <th class="fw-semibold text-dark">Last Restock</th>
                <th class="fw-semibold text-dark">Actions</th>
              </tr>
            </thead>
          <tbody>
            <?php foreach ($products as $product): ?>
              <tr>
                <td>
                  <div class="d-flex align-items-center">
                    <img src="<?= $product['image1'] ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" 
                         class="product-image me-3" alt="Product">
                    <div>
                      <div class="fw-semibold"><?= htmlspecialchars($product['name']) ?></div>
                      <small class="text-muted"><?= htmlspecialchars($product['supplier_name']) ?></small>
                    </div>
                  </div>
                </td>
                <td><?= htmlspecialchars($product['category_name']) ?></td>
                <td>
                  <span class="fw-semibold"><?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></span>
                </td>
                <td><?= $product['reorder_point'] ?></td>
                <td>
                  <?php if ((int)$product['stock'] === 0): ?>
                    <span class="badge" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Out of Stock</span>
                  <?php elseif ((int)$product['stock'] <= (int)$product['reorder_point']): ?>
                    <span class="badge" style="background: #fff3cd; color: #856404; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Low Stock</span>
                  <?php else: ?>
                    <span class="badge" style="background: #d4edda; color: #155724; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">In Stock</span>
                  <?php endif; ?>
                </td>
                <td class="text-muted">
                  <?= !empty($product['last_restock_date']) ? date('M d, Y', strtotime($product['last_restock_date'])) : 'Never' ?>
                </td>
                <td>
                  <div class="btn-group" role="group">
                    <a href="restocking.php" class="btn btn-sm btn-success">
                      <i class="fa fa-plus me-1"></i>Restock
                    </a>
                    <a href="stock_adjustment.php" class="btn btn-sm btn-info">
                      <i class="fa fa-edit me-1"></i>Adjust
                    </a>
                    <a href="stock_levels.php" class="btn btn-sm btn-warning">
                      <i class="fa fa-chart-line me-1"></i>Levels
                    </a>
                  </div>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    // Chart.js configuration
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6c757d';


    // Stock Status Distribution Chart (Doughnut Chart)
    const stockStatusCtx = document.getElementById('stockStatusChart').getContext('2d');
    new Chart(stockStatusCtx, {
      type: 'doughnut',
      data: {
        labels: ['In Stock', 'Low Stock', 'Out of Stock'],
        datasets: [{
          data: [
            <?= $stock_status_data['In Stock'] ?>,
            <?= $stock_status_data['Low Stock'] ?>,
            <?= $stock_status_data['Out of Stock'] ?>
          ],
          backgroundColor: [
            'rgba(212, 237, 218, 0.8)',   // Light green
            'rgba(255, 243, 205, 0.8)',   // Light yellow
            'rgba(245, 198, 203, 0.8)'    // Light red
          ],
          borderWidth: 2,
          borderColor: '#fff'
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: {
              padding: 20,
              usePointStyle: true
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((context.parsed / total) * 100).toFixed(1);
                return context.label + ': ' + context.parsed + ' (' + percentage + '%)';
              }
            }
          }
        }
      }
    });

    // Stock Movement Trend Chart (Line Chart)
    const movementTrendCtx = document.getElementById('movementTrendChart').getContext('2d');
    const movementData = <?= json_encode($movement_trend) ?>;
    const dates = movementData.map(item => new Date(item.movement_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' }));
    const stockInData = movementData.map(item => parseInt(item.stock_in));
    const stockOutData = movementData.map(item => parseInt(item.stock_out));

    new Chart(movementTrendCtx, {
      type: 'line',
      data: {
        labels: dates,
        datasets: [{
          label: 'Stock In',
          data: stockInData,
          borderColor: '#155724',
          backgroundColor: 'rgba(212, 237, 218, 0.3)',
          tension: 0.4,
          fill: true
        }, {
          label: 'Stock Out',
          data: stockOutData,
          borderColor: '#721c24',
          backgroundColor: 'rgba(245, 198, 203, 0.3)',
          tension: 0.4,
          fill: true
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          },
          x: {
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          }
        },
        plugins: {
          legend: {
            position: 'top',
            labels: {
              usePointStyle: true,
              padding: 20
            }
          }
        }
      }
    });

    // Top Products by Value Chart (Horizontal Bar Chart)
    const topProductsCtx = document.getElementById('topProductsChart').getContext('2d');
    const topProductsData = <?= json_encode($top_products) ?>;
    const productNames = topProductsData.map(item => item.product_name.length > 15 ? item.product_name.substring(0, 15) + '...' : item.product_name);
    const stockValues = topProductsData.map(item => parseFloat(item.stock_value));

    new Chart(topProductsCtx, {
      type: 'bar',
      data: {
        labels: productNames,
        datasets: [{
          label: 'Stock Value (₱)',
          data: stockValues,
          backgroundColor: 'rgba(212, 237, 218, 0.8)',
          borderColor: '#155724',
          borderWidth: 1
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: 'y',
        scales: {
          x: {
            beginAtZero: true,
            grid: {
              color: 'rgba(0,0,0,0.1)'
            },
            ticks: {
              callback: function(value) {
                return '₱' + value.toLocaleString();
              }
            }
          },
          y: {
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          }
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                return 'Value: ₱' + context.parsed.x.toLocaleString();
              }
            }
          }
        }
      }
    });

    // Product Movement Categories Chart (Bar Chart)
    const movementCategoriesCtx = document.getElementById('movementCategoriesChart').getContext('2d');
    new Chart(movementCategoriesCtx, {
      type: 'bar',
      data: {
        labels: ['Fast Moving', 'Slow Moving', 'Non Moving'],
        datasets: [{
          label: 'Number of Products',
          data: [
            <?= $movement_categories['Fast Moving'] ?>,
            <?= $movement_categories['Slow Moving'] ?>,
            <?= $movement_categories['Non Moving'] ?>
          ],
          backgroundColor: [
            'rgba(212, 237, 218, 0.8)',   // Light green
            'rgba(245, 198, 203, 0.8)',   // Light red
            'rgba(226, 227, 229, 0.8)'    // Light gray
          ],
          borderColor: [
            '#155724',   // Dark green
            '#721c24',   // Dark red
            '#383d41'    // Dark gray
          ],
          borderWidth: 2
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        scales: {
          y: {
            beginAtZero: true,
            ticks: {
              stepSize: 1
            },
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          },
          x: {
            grid: {
              color: 'rgba(0,0,0,0.1)'
            }
          }
        },
        plugins: {
          legend: {
            display: false
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((context.parsed.y / total) * 100).toFixed(1);
                return context.label + ': ' + context.parsed.y + ' products (' + percentage + '%)';
              }
            }
          }
        }
      }
    });

  </script>
</body>
</html>
