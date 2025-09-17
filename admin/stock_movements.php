<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Get filter parameters
$product_filter = $_GET['product'] ?? '';
$type_filter = $_GET['type'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build the query with filters
$where_conditions = [];
$params = [];

if (!empty($product_filter)) {
    $where_conditions[] = "p.product_id = ?";
    $params[] = $product_filter;
}

if (!empty($type_filter)) {
    $where_conditions[] = "sm.stockmovementtype_id = ?";
    $params[] = $type_filter;
}

if (!empty($date_from)) {
    $where_conditions[] = "DATE(sm.created_at) >= ?";
    $params[] = $date_from;
}

if (!empty($date_to)) {
    $where_conditions[] = "DATE(sm.created_at) <= ?";
    $params[] = $date_to;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch stock movements with filters
$stmt = $pdo->prepare("
    SELECT 
        sm.*,
        p.product_name,
        c.category_name,
        s.name as supplier_name,
        u.name as uom_name,
        smt.name as movement_type_name,
        COALESCE(ps.current_stock,0) as current_stock
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.product_id
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN stock_movement_types smt ON sm.stockmovementtype_id = smt.stockmovementtype_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    $where_clause
    ORDER BY sm.created_at DESC
    LIMIT 100
");
$stmt->execute($params);
$stock_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for filter dropdown
$stmt = $pdo->query("
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    WHERE p.is_archive = 0
    ORDER BY p.product_name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch movement types for filter dropdown
$stmt = $pdo->query("SELECT stockmovementtype_id, name FROM stock_movement_types ORDER BY name");
$movement_types = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate movement statistics
$total_movements = $pdo->query("SELECT COUNT(*) FROM stock_movements")->fetchColumn();
$movements_today = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$movements_this_week = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();
$movements_this_month = $pdo->query("SELECT COUNT(*) FROM stock_movements WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)")->fetchColumn();

// Calculate product movement analysis
$stmt = $pdo->query("
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        COUNT(sm.stockmovement_id) as total_movements,
        SUM(CASE WHEN sm.quantity > 0 THEN sm.quantity ELSE 0 END) as total_in,
        SUM(CASE WHEN sm.quantity < 0 THEN ABS(sm.quantity) ELSE 0 END) as total_out,
        COALESCE(ps.current_stock,0) as current_stock
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN stock_movements sm ON p.product_id = sm.product_id AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    WHERE p.is_archive = 0
    GROUP BY p.product_id, p.product_name, c.category_name, ps.current_stock
    HAVING total_movements > 0
    ORDER BY total_movements DESC
    LIMIT 10
");
$product_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Movements - Admin Dashboard</title>
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
                    <i class="fa fa-exchange-alt me-3" style="color: #7F1734;"></i>Stock Movements
                </h1>
                <p class="text-muted">Track product performance and inventory movements over time</p>
            </div>
        </div>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-chart-line"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $total_movements ?></h4>
                            <small class="text-muted text-uppercase">Total Movements</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-calendar-day"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $movements_today ?></h4>
                            <small class="text-muted text-uppercase">Today</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-calendar-week"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $movements_this_week ?></h4>
                            <small class="text-muted text-uppercase">This Week</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary">
                            <i class="fa fa-calendar-alt"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $movements_this_month ?></h4>
                            <small class="text-muted text-uppercase">This Month</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="filter-card">
            <h5 class="fw-bold mb-3">Filter Movements</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Product</label>
                    <select name="product" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['product_id'] ?>" <?= $product_filter == $product['product_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['product_name']) ?> (<?= htmlspecialchars($product['category_name']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Movement Type</label>
                    <select name="type" class="form-select">
                        <option value="">All Types</option>
                        <?php foreach ($movement_types as $type): ?>
                            <option value="<?= $type['stockmovementtype_id'] ?>" <?= $type_filter == $type['stockmovementtype_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($type['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date From</label>
                    <input type="date" name="date_from" class="form-control" value="<?= $date_from ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">Date To</label>
                    <input type="date" name="date_to" class="form-control" value="<?= $date_to ?>">
                </div>
                <div class="col-md-2">
                    <label class="form-label fw-semibold">&nbsp;</label>
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fa fa-search me-1"></i>Filter
                        </button>
                        <a href="stock_movements.php" class="btn btn-outline-secondary">
                            <i class="fa fa-times me-1"></i>Clear
                        </a>
                    </div>
                </div>
            </form>
        </div>

        <!-- Product Movement Analysis -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Top Moving Products (Last 30 Days)</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Category</th>
                            <th class="fw-semibold">Total Movements</th>
                            <th class="fw-semibold">Stock In</th>
                            <th class="fw-semibold">Stock Out</th>
                            <th class="fw-semibold">Current Stock</th>
                            <th class="fw-semibold">Movement Rate</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($product_analysis as $analysis): ?>
                            <tr>
                                <td class="fw-semibold"><?= htmlspecialchars($analysis['product_name']) ?></td>
                                <td><?= htmlspecialchars($analysis['category_name']) ?></td>
                                <td>
                                    <span class="badge bg-info"><?= $analysis['total_movements'] ?></span>
                                </td>
                                <td>
                                    <span class="text-success fw-semibold">+<?= $analysis['total_in'] ?></span>
                                </td>
                                <td>
                                    <span class="text-danger fw-semibold">-<?= $analysis['total_out'] ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= $analysis['current_stock'] ?></span>
                                </td>
                                <td>
                                    <?php
                                    $movement_rate = $analysis['current_stock'] > 0 ? round(($analysis['total_movements'] / 30) * 100, 1) : 0;
                                    if ($movement_rate > 7) {
                                        echo '<span class="badge bg-success">Fast Moving</span>';
                                    } else {
                                        echo '<span class="badge bg-danger">Slow Moving</span>';
                                    }
                                    ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Stock Movements Table -->
        <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Recent Stock Movements</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Date & Time</th>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Type</th>
                            <th class="fw-semibold">Quantity</th>
                            <th class="fw-semibold">Previous Stock</th>
                            <th class="fw-semibold">New Stock</th>
                            <th class="fw-semibold">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stock_movements as $movement): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($movement['created_at'])) ?></td>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($movement['product_name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($movement['category_name']) ?></small>
                                </td>
                                <td>
                                    <?php
                                    $type = $movement['stockmovementtype_id'];
                                    if ($type == 1) {
                                        echo '<span class="badge bg-success movement-badge">Restocking</span>';
                                    } elseif ($type == 2) {
                                        echo '<span class="badge bg-danger movement-badge">Sale</span>';
                                    } elseif ($type == 3) {
                                        echo '<span class="badge bg-warning movement-badge">Return</span>';
                                    } elseif ($type == 4) {
                                        echo '<span class="badge bg-info movement-badge">Adjustment</span>';
                                    } else {
                                        echo '<span class="badge bg-secondary movement-badge">Other</span>';
                                    }
                                    ?>
                                </td>
                                <td>
                                    <?php if ($movement['quantity'] > 0): ?>
                                        <span class="text-success fw-semibold">+<?= $movement['quantity'] ?></span>
                                    <?php else: ?>
                                        <span class="text-danger fw-semibold"><?= $movement['quantity'] ?></span>
                                    <?php endif; ?>
                                    <small class="text-muted"><?= htmlspecialchars($movement['uom_name']) ?></small>
                                </td>
                                <td><?= $movement['previous_stock'] ?></td>
                                <td>
                                    <span class="fw-semibold"><?= $movement['new_stock'] ?></span>
                                </td>
                                <td><?= htmlspecialchars($movement['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>