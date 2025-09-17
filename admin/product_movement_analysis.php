<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Get analysis period
$period = $_GET['period'] ?? '30'; // days
$period_days = (int)$period;

// Calculate movement analysis for all products
$stmt = $pdo->prepare("
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        b.name as brand_name,
        s.name as supplier_name,
        u.name as uom_name,
        COALESCE(ps.current_stock,0) as current_stock,
        COALESCE(ps.reorder_point, 10) as reorder_point,
        COUNT(sm.stockmovement_id) as total_movements,
        SUM(CASE WHEN sm.quantity > 0 THEN sm.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN sm.quantity < 0 THEN ABS(sm.quantity) ELSE 0 END) as total_out,
        AVG(sm.quantity) as avg_quantity_per_movement,
        MIN(sm.created_at) as first_movement,
        MAX(sm.created_at) as last_movement,
        DATEDIFF(NOW(), MAX(sm.created_at)) as days_since_last_movement
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    LEFT JOIN stock_movements sm ON p.product_id = sm.product_id 
        AND sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    WHERE p.is_archive = 0
    GROUP BY p.product_id, p.product_name, c.category_name, b.name, s.name, u.name, ps.current_stock, ps.reorder_point
    ORDER BY total_movements DESC, p.product_name
");
$stmt->execute([$period_days]);
$all_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Classify products based on movement patterns
$fast_moving = [];
$slow_moving = [];
$non_moving = [];

foreach ($all_products as $product) {
    $movement_rate = $product['total_movements'] > 0 ? round(($product['total_movements'] / $period_days) * 100, 1) : 0;
    $days_since_last = $product['days_since_last_movement'] ?? $period_days;
    
    // Classification logic - removed medium moving category
    if ($product['total_movements'] == 0) {
        $non_moving[] = $product;
    } elseif ($movement_rate > 7 || $product['total_movements'] > ($period_days * 0.2)) {
        $fast_moving[] = $product;
    } else {
        $slow_moving[] = $product;
    }
}

// Calculate summary statistics
$total_products = count($all_products);
$fast_count = count($fast_moving);
$slow_count = count($slow_moving);
$non_count = count($non_moving);

// Calculate inventory value by movement category
$fast_value = array_sum(array_map(function($p) { return $p['current_stock'] * 100; }, $fast_moving)); // Assuming 100 as base value
$slow_value = array_sum(array_map(function($p) { return $p['current_stock'] * 100; }, $slow_moving));
$non_value = array_sum(array_map(function($p) { return $p['current_stock'] * 100; }, $non_moving));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Movement Analysis - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
        
        .movement-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .category-section {
            margin-bottom: 30px;
        }
        
        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-2">
                    <i class="fa fa-chart-bar me-3" style="color: #7F1734;"></i>Product Movement Analysis
                </h1>
                <p class="text-muted">Analyze product performance and identify movement patterns</p>
            </div>
        </div>

        <!-- Period Filter -->
        <div class="filter-card">
            <h5 class="fw-bold mb-3">Analysis Period</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Analysis Period</label>
                    <select name="period" class="form-select" onchange="this.form.submit()">
                        <option value="7" <?= $period == '7' ? 'selected' : '' ?>>Last 7 Days</option>
                        <option value="30" <?= $period == '30' ? 'selected' : '' ?>>Last 30 Days</option>
                        <option value="90" <?= $period == '90' ? 'selected' : '' ?>>Last 90 Days</option>
                        <option value="180" <?= $period == '180' ? 'selected' : '' ?>>Last 6 Months</option>
                    </select>
                </div>
            </form>
        </div>

        <!-- Summary Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-rocket"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $fast_count ?></h4>
                            <small class="text-muted text-uppercase">Fast Moving</small>
                        </div>
                    </div>
                </div>
            </div>
            
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger">
                            <i class="fa fa-snail"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $slow_count ?></h4>
                            <small class="text-muted text-uppercase">Slow Moving</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-secondary">
                            <i class="fa fa-stop"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $non_count ?></h4>
                            <small class="text-muted text-uppercase">Non Moving</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fast Moving Products -->
        <div class="category-section">
            <div class="category-header">
                <h5 class="fw-bold mb-0">
                    <i class="fa fa-rocket me-2"></i>Fast Moving Products (<?= $fast_count ?>)
                </h5>
                <small>High demand, frequent sales</small>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Product</th>
                                <th class="fw-semibold">Category</th>
                                <th class="fw-semibold">Movements</th>
                                <th class="fw-semibold">Stock In</th>
                                <th class="fw-semibold">Stock Out</th>
                                <th class="fw-semibold">Current Stock</th>
                                <th class="fw-semibold">Last Movement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fast_moving as $product): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><span class="badge bg-success"><?= $product['total_movements'] ?></span></td>
                                    <td class="text-success">+<?= $product['total_in'] ?></td>
                                    <td class="text-danger">-<?= $product['total_out'] ?></td>
                                    <td><?= $product['current_stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></td>
                                    <td><?= $product['last_movement'] ? date('M d, Y', strtotime($product['last_movement'])) : 'Never' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


        <!-- Slow Moving Products -->
        <div class="category-section">
            <div class="category-header">
                <h5 class="fw-bold mb-0">
                    <i class="fa fa-snail me-2"></i>Slow Moving Products (<?= $slow_count ?>)
                </h5>
                <small>Low demand, infrequent sales</small>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Product</th>
                                <th class="fw-semibold">Category</th>
                                <th class="fw-semibold">Movements</th>
                                <th class="fw-semibold">Stock In</th>
                                <th class="fw-semibold">Stock Out</th>
                                <th class="fw-semibold">Current Stock</th>
                                <th class="fw-semibold">Last Movement</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slow_moving as $product): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><span class="badge bg-danger"><?= $product['total_movements'] ?></span></td>
                                    <td class="text-success">+<?= $product['total_in'] ?></td>
                                    <td class="text-danger">-<?= $product['total_out'] ?></td>
                                    <td><?= $product['current_stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></td>
                                    <td><?= $product['last_movement'] ? date('M d, Y', strtotime($product['last_movement'])) : 'Never' ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Non-Moving Products -->
        <div class="category-section">
            <div class="category-header">
                <h5 class="fw-bold mb-0">
                    <i class="fa fa-stop me-2"></i>Non-Moving Products (<?= $non_count ?>)
                </h5>
                <small>No sales activity - potential dead stock</small>
            </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Product</th>
                                <th class="fw-semibold">Category</th>
                                <th class="fw-semibold">Current Stock</th>
                                <th class="fw-semibold">Reorder Point</th>
                                <th class="fw-semibold">Status</th>
                                <th class="fw-semibold">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($non_moving as $product): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($product['product_name']) ?></td>
                                    <td><?= htmlspecialchars($product['category_name']) ?></td>
                                    <td><?= $product['current_stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></td>
                                    <td><?= $product['reorder_point'] ?></td>
                                    <td>
                                        <?php if ((int)$product['current_stock'] === 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php elseif ((int)$product['current_stock'] <= (int)$product['reorder_point']): ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-info">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="stock_levels.php" class="btn btn-sm btn-warning">
                                                <i class="fa fa-chart-line me-1"></i>Review
                                            </a>
                                            <a href="products.php" class="btn btn-sm btn-info">
                                                <i class="fa fa-edit me-1"></i>Edit
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
</body>
</html>
