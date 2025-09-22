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
        
        .filter-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .category-section {
            margin-bottom: 30px;
        }
        
        .category-header {
            background: var(--bs-primary);
            color: white;
            padding: 1.5rem;
            border-radius: 15px;
            margin-bottom: 1.5rem;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
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
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h2>
                        <i class="fa fa-chart-bar me-3"></i>Product Movement Analysis
                    </h2>
                    <p class="mb-0 opacity-75">Analyze product performance and identify movement patterns</p>
                </div>
            </div>

            <!-- Period Filter -->
            <div class="filter-card">
                <h5 class="fw-bold mb-3 text-dark">
                    <i class="fas fa-calendar-alt me-2"></i>Analysis Period
                </h5>
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

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-rocket"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $fast_count ?></h3>
                            <p class="card-label">Fast Moving</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-hourglass-half"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $slow_count ?></h3>
                            <p class="card-label">Slow Moving</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-4 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-stop"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $non_count ?></h3>
                            <p class="card-label">Non Moving</p>
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
                    <small class="opacity-75">High demand, frequent sales</small>
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
                                    <td><span class="badge" style="background: #d4edda; color: #155724; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><?= $product['total_movements'] ?></span></td>
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
                        <i class="fa fa-hourglass-half me-2"></i>Slow Moving Products (<?= $slow_count ?>)
                    </h5>
                    <small class="opacity-75">Low demand, infrequent sales</small>
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
                                    <td><span class="badge" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><?= $product['total_movements'] ?></span></td>
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
                    <small class="opacity-75">No sales activity - potential dead stock</small>
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
                                            <span class="badge" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Out of Stock</span>
                                        <?php elseif ((int)$product['current_stock'] <= (int)$product['reorder_point']): ?>
                                            <span class="badge" style="background: #fff3cd; color: #856404; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #cce5ff; color: #004085; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <a href="stock_levels.php" class="btn btn-sm" style="background: #fff3cd; color: #856404; border-radius: 8px;">
                                                <i class="fa fa-chart-line me-1"></i>Review
                                            </a>
                                            <a href="products.php" class="btn btn-sm" style="background: #cce5ff; color: #004085; border-radius: 8px;">
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
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
