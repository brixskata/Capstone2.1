
<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
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

        // Insert restocking record (matches restocking table schema)
        $stmt = $pdo->prepare("INSERT INTO restocking (product_id, supplier_id, quantity_added, cost_per_unit, total_cost, restock_date, expected_delivery, status_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
        $stmt->execute([$product_id, $supplier_id, $quantity_added, $cost_per_unit, $total_cost, $restock_date, $expected_delivery, $notes, $_SESSION['user_id']]);

        // Update product_stock current_stock and last_restock_date
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ? WHERE product_id = ?");
        $stmt->execute([$quantity_added, $restock_date, $product_id]);

        // Fetch new current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();

        // Record stock movement (use stock_movements schema)
        $restock_id = $pdo->lastInsertId();
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

        // Record stock movement
        $adjustment_id = $pdo->lastInsertId();
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <?php include 'includes/admin_styles.php'; ?>
      <style>
      .stat-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
        transition: transform 0.2s ease;
      }
      
      .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
      }
      
      .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
      }
      
      .table-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
      }
      
      .product-image {
        width: 40px;
        height: 40px;
        object-fit: contain;
        border-radius: 6px;
      }
      
      .stock-badge {
        font-size: 0.75rem;
        padding: 4px 8px;
        border-radius: 12px;
      }
    </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
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

    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 fw-bold text-dark mb-2">
          <i class="fa fa-warehouse me-3" style="color: #7F1734;"></i>Inventory Overview
        </h1>
        <p class="text-muted">Monitor inventory status and access management tools</p>
      </div>
    </div>

    <!-- Quick Access Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='restocking.php'">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success">
              <i class="fa fa-plus-circle"></i>
            </div>
            <div class="ms-3">
              <h5 class="fw-bold mb-1">Restocking</h5>
              <small class="text-muted">Record new stock entries</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='stock_adjustment.php'">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-info">
              <i class="fa fa-edit"></i>
            </div>
            <div class="ms-3">
              <h5 class="fw-bold mb-1">Stock Adjustment</h5>
              <small class="text-muted">Correct discrepancies</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='stock_levels.php'">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-warning">
              <i class="fa fa-chart-line"></i>
            </div>
            <div class="ms-3">
              <h5 class="fw-bold mb-1">Stock Levels</h5>
              <small class="text-muted">Monitor real-time levels</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card" style="cursor: pointer;" onclick="window.location.href='stock_movements.php'">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-primary">
              <i class="fa fa-exchange-alt"></i>
            </div>
            <div class="ms-3">
              <h5 class="fw-bold mb-1">Stock Movements</h5>
              <small class="text-muted">Track product performance</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inventory Statistics -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-info">
              <i class="fa fa-boxes"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?= $total_products ?></h4>
              <small class="text-muted text-uppercase">Total Products</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-warning">
              <i class="fa fa-exclamation-triangle"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?= $low_stock_products ?></h4>
              <small class="text-muted text-uppercase">Low Stock Items</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-danger">
              <i class="fa fa-times-circle"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?= $out_of_stock_products ?></h4>
              <small class="text-muted text-uppercase">Out of Stock</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success">
              <i class="fa fa-money-bill"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0">₱<?= number_format($total_inventory_value, 2) ?></h4>
              <small class="text-muted text-uppercase">Total Value</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Charts Section -->
    <!-- Row 3: Product Movement Categories and Stock Status Distribution -->
    <div class="row g-4 mb-4">
      <!-- Product Movement Categories -->
      <div class="col-lg-6">
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <h5 class="fw-bold mb-0">
              <i class="fa fa-chart-bar me-2" style="color: #7F1734;"></i>Product Movement Categories (Last 30 Days)
            </h5>
          </div>
          <div class="p-4">
            <canvas id="movementCategoriesChart" height="300"></canvas>
          </div>
        </div>
      </div>

      <!-- Stock Status Distribution Chart -->
      <div class="col-lg-6">
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <h5 class="fw-bold mb-0">
              <i class="fa fa-chart-donut me-2" style="color: #7F1734;"></i>Stock Status Distribution
            </h5>
          </div>
          <div class="p-4">
            <canvas id="stockStatusChart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 4: Stock Movement Trend (Full Width) -->
    <div class="row g-4 mb-4">
      <!-- Stock Movement Trend Chart -->
      <div class="col-12">
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <h5 class="fw-bold mb-0">
              <i class="fa fa-chart-line me-2" style="color: #7F1734;"></i>Stock Movement Trend (Last 7 Days)
            </h5>
          </div>
          <div class="p-4">
            <canvas id="movementTrendChart" height="200"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 5: Top Products Value and Movement Distribution -->
    <div class="row g-4 mb-4">
      <!-- Top Products by Value -->
      <div class="col-lg-6">
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <h5 class="fw-bold mb-0">
              <i class="fa fa-trophy me-2" style="color: #7F1734;"></i>Top Products by Stock Value
            </h5>
          </div>
          <div class="p-4">
            <canvas id="topProductsChart" height="300"></canvas>
          </div>
        </div>
      </div>

      <!-- Movement Distribution -->
      <div class="col-lg-6">
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <h5 class="fw-bold mb-0">
              <i class="fa fa-chart-pie me-2" style="color: #7F1734;"></i>Movement Distribution
            </h5>
          </div>
          <div class="p-4">
            <canvas id="movementDistributionChart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 6: Category Distribution (Full Width) -->
    <div class="row g-4 mb-4">
      <!-- Category Distribution Chart -->
      <div class="col-12">
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <h5 class="fw-bold mb-0">
              <i class="fa fa-chart-pie me-2" style="color: #7F1734;"></i>Products by Category
            </h5>
          </div>
          <div class="p-4">
            <canvas id="categoryChart" height="300"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Inventory Table -->
    <div class="table-card">
      <div class="card-header bg-transparent border-0 p-4">
        <h5 class="fw-bold mb-0">Current Inventory Levels</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="fw-semibold">Product</th>
              <th class="fw-semibold">Category</th>
              <th class="fw-semibold">Current Stock</th>
              <th class="fw-semibold">Reorder Point</th>
              <th class="fw-semibold">Status</th>
              <th class="fw-semibold">Last Restock</th>
              <th class="fw-semibold">Actions</th>
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
                    <span class="badge bg-danger stock-badge">Out of Stock</span>
                  <?php elseif ((int)$product['stock'] <= (int)$product['reorder_point']): ?>
                    <span class="badge bg-warning stock-badge">Low Stock</span>
                  <?php else: ?>
                    <span class="badge bg-success stock-badge">In Stock</span>
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
  </main>


  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    // Chart.js configuration
    Chart.defaults.font.family = "'Inter', sans-serif";
    Chart.defaults.color = '#6c757d';

    // Category Distribution Chart (Pie Chart)
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    new Chart(categoryCtx, {
      type: 'pie',
      data: {
        labels: <?= json_encode(array_column($category_data, 'category_name')) ?>,
        datasets: [{
          data: <?= json_encode(array_column($category_data, 'product_count')) ?>,
          backgroundColor: [
            '#FF6384',
            '#36A2EB',
            '#FFCE56',
            '#4BC0C0',
            '#9966FF',
            '#FF9F40',
            '#FF6384',
            '#C9CBCF'
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
            '#28a745',
            '#ffc107',
            '#dc3545'
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
          borderColor: '#28a745',
          backgroundColor: 'rgba(40, 167, 69, 0.1)',
          tension: 0.4,
          fill: true
        }, {
          label: 'Stock Out',
          data: stockOutData,
          borderColor: '#dc3545',
          backgroundColor: 'rgba(220, 53, 69, 0.1)',
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
          backgroundColor: 'rgba(127, 23, 52, 0.8)',
          borderColor: '#7F1734',
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
            'rgba(40, 167, 69, 0.8)',
            'rgba(255, 87, 34, 0.8)',
            'rgba(108, 117, 125, 0.8)'
          ],
          borderColor: [
            '#28a745',
            '#ff5722',
            '#6c757d'
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

    // Movement Distribution Chart (Pie Chart)
    const movementDistributionCtx = document.getElementById('movementDistributionChart').getContext('2d');
    new Chart(movementDistributionCtx, {
      type: 'pie',
      data: {
        labels: ['Fast Moving', 'Slow Moving', 'Non Moving'],
        datasets: [{
          data: [
            <?= $movement_categories['Fast Moving'] ?>,
            <?= $movement_categories['Slow Moving'] ?>,
            <?= $movement_categories['Non Moving'] ?>
          ],
          backgroundColor: [
            '#28a745',
            '#ff5722',
            '#6c757d'
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
              usePointStyle: true,
              generateLabels: function(chart) {
                const data = chart.data;
                if (data.labels.length && data.datasets.length) {
                  const dataset = data.datasets[0];
                  const total = dataset.data.reduce((a, b) => a + b, 0);
                  return data.labels.map((label, i) => {
                    const value = dataset.data[i];
                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : 0;
                    return {
                      text: `${label}: ${value} (${percentage}%)`,
                      fillStyle: dataset.backgroundColor[i],
                      strokeStyle: dataset.borderColor[i],
                      lineWidth: dataset.borderWidth,
                      pointStyle: 'circle',
                      hidden: false,
                      index: i
                    };
                  });
                }
                return [];
              }
            }
          },
          tooltip: {
            callbacks: {
              label: function(context) {
                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                const percentage = ((context.parsed / total) * 100).toFixed(1);
                return context.label + ': ' + context.parsed + ' products (' + percentage + '%)';
              }
            }
          }
        }
      }
    });
  </script>
</body>
</html>
