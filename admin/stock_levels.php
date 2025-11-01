<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
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

// Initialize reorder point calculator
$ropCalculator = new ReorderPointCalculator($pdo);

// Fetch brands with detailed stock information using new reorder point system
$stmt = $pdo->query("
    SELECT 
        b.id as brand_id,
        b.name as brand_name,
        COUNT(DISTINCT pb.product_id) as product_count,
        COALESCE(SUM(pb.quantity_remaining), 0) as total_stock,
        COALESCE(AVG(bps.reorder_point), 0) as avg_reorder_point,
        COALESCE(AVG(bps.average_daily_sales), 0) as avg_ads,
        MAX(ps.last_restock_date) as last_restock_date,
        COALESCE(SUM((SELECT COUNT(*) FROM batch_movements bm WHERE bm.batch_id = pb.batch_id AND bm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY))), 0) as movements_30_days,
        COALESCE(SUM((SELECT COUNT(*) FROM restocking WHERE product_id = pb.product_id AND status_id = 2)), 0) as restock_count,
        COUNT(CASE WHEN pb.quantity_remaining <= COALESCE(bps.reorder_point, 0) AND pb.quantity_remaining > 0 THEN 1 END) as low_stock_products,
        COUNT(CASE WHEN pb.quantity_remaining = 0 THEN 1 END) as out_of_stock_products,
        COUNT(CASE WHEN bps.movement_type = 'Fast-Moving' THEN 1 END) as fast_moving_products,
        COUNT(CASE WHEN bps.movement_type = 'Slow-Moving' THEN 1 END) as slow_moving_products,
        COUNT(CASE WHEN bps.movement_type = 'Non-Moving' THEN 1 END) as non_moving_products
    FROM brands b
    LEFT JOIN product_batches pb ON b.id = pb.brand_id AND pb.is_active = 1
    LEFT JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
    LEFT JOIN (
        SELECT DISTINCT product_id, reorder_point, last_restock_date
        FROM product_stock
    ) ps ON ps.product_id = pb.product_id
    LEFT JOIN brand_product_stock bps ON bps.product_id = pb.product_id AND bps.brand_id = pb.brand_id
    WHERE b.is_archived = 0
    GROUP BY b.id, b.name
    ORDER BY total_stock ASC, b.name
");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch product-brand details for each brand (for expandable rows)
$brandProducts = [];
foreach ($brands as $brand) {
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            b.id as brand_id,
            b.name as brand_name,
            uom.name as uom_name,
            COALESCE(SUM(pb.quantity_remaining), 0) as current_stock,
            COALESCE(bps.reorder_point, ps.reorder_point, 10) as reorder_point,
            COALESCE(bps.average_daily_sales, 0) as ads,
            COALESCE(bps.movement_type, 'Non-Moving') as movement_type,
            ps.last_restock_date
        FROM product_batches pb
        INNER JOIN products p ON pb.product_id = p.product_id
        INNER JOIN brands b ON pb.brand_id = b.id
        LEFT JOIN uom ON p.uom_id = uom.uom_id
        LEFT JOIN brand_product_stock bps ON bps.product_id = p.product_id AND bps.brand_id = b.id
        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
        WHERE b.id = ? AND pb.is_active = 1 AND p.is_archive = 0
        GROUP BY p.product_id, b.id, b.name, p.product_name, uom.name, bps.reorder_point, ps.reorder_point, bps.average_daily_sales, bps.movement_type, ps.last_restock_date
        ORDER BY p.product_name
    ");
    $stmt->execute([$brand['brand_id']]);
    $brandProducts[$brand['brand_id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Calculate stock level statistics using consistent stock status logic
$total_brands = count($brands);
$low_stock_brands = 0;
$out_of_stock_brands = 0;
$in_stock_brands = 0;

foreach ($brands as $brand) {
    $avg_rop = (float)$brand['avg_reorder_point'];
    $total_stock = (float)$brand['total_stock'];
    $stock_status = $ropCalculator->getStockStatus($total_stock, $avg_rop);
    
    if ($stock_status === 'Out of Stock') {
        $out_of_stock_brands++;
    } elseif ($stock_status === 'Low Stock') {
        $low_stock_brands++;
    } else {
        $in_stock_brands++;
    }
}

// Calculate total inventory value
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
    $total_inventory_value = 0;
}

// Filter brands based on status using consistent stock status logic
$filter = $_GET['filter'] ?? 'all';
$filtered_brands = $brands;

if ($filter === 'low_stock') {
    $filtered_brands = array_filter($brands, function($b) use ($ropCalculator) {
        $avg_rop = (float)$b['avg_reorder_point'];
        $total_stock = (float)$b['total_stock'];
        return $ropCalculator->getStockStatus($total_stock, $avg_rop) === 'Low Stock';
    });
} elseif ($filter === 'out_of_stock') {
    $filtered_brands = array_filter($brands, function($b) use ($ropCalculator) {
        $avg_rop = (float)$b['avg_reorder_point'];
        $total_stock = (float)$b['total_stock'];
        return $ropCalculator->getStockStatus($total_stock, $avg_rop) === 'Out of Stock';
    });
} elseif ($filter === 'in_stock') {
    $filtered_brands = array_filter($brands, function($b) use ($ropCalculator) {
        $avg_rop = (float)$b['avg_reorder_point'];
        $total_stock = (float)$b['total_stock'];
        return $ropCalculator->getStockStatus($total_stock, $avg_rop) === 'Sufficient';
    });
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Stock Levels - Admin Dashboard</title>
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
        
        .low-stock-item {
            background-color: #fff3cd;
            border-left: 4px solid #856404;
        }
        
        .out-of-stock-item {
            background-color: #f5c6cb;
            border-left: 4px solid #721c24;
        }
        
        .filter-btn {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.875rem;
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
                            <i class="fa fa-chart-line me-3"></i>Brand Stock Levels
                        </h2>
                        <p class="mb-0 opacity-75">Monitor real-time stock levels by brand (reorder points are automatically calculated based on sales data)</p>
                    </div>
                    <div class="text-white opacity-75">
                        <small><i class="fa fa-info-circle me-1"></i>Reorder points are automatically calculated based on sales data</small>
                    </div>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-boxes"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $total_brands ?></h3>
                            <p class="card-label">Total Brands</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $in_stock_brands ?></h3>
                            <p class="card-label">Sufficient</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $low_stock_brands ?></h3>
                            <p class="card-label">Low Stock</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-times-circle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= $out_of_stock_brands ?></h3>
                            <p class="card-label">Out of Stock</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="d-flex gap-2 mb-4">
                <a href="?filter=all" class="btn filter-btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                    All Brands (<?= $total_brands ?>)
                </a>
                <a href="?filter=in_stock" class="btn filter-btn <?= $filter === 'in_stock' ? 'btn-success' : 'btn-outline-success' ?>">
                    Sufficient (<?= $in_stock_brands ?>)
                </a>
                <a href="?filter=low_stock" class="btn filter-btn <?= $filter === 'low_stock' ? 'btn-warning' : 'btn-outline-warning' ?>">
                    Low Stock (<?= $low_stock_brands ?>)
                </a>
                <a href="?filter=out_of_stock" class="btn filter-btn <?= $filter === 'out_of_stock' ? 'btn-danger' : 'btn-outline-danger' ?>">
                    Out of Stock (<?= $out_of_stock_brands ?>)
                </a>
            </div>

            <!-- Stock Levels Table -->
            <div class="table-card">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-list-alt me-2"></i>Current Brand Stock Levels
                    </h5>
                </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Brand</th>
                            <th class="fw-semibold">Products</th>
                            <th class="fw-semibold">Total Stock</th>
                            <th class="fw-semibold">Average Sales Per Day</th>
                            <th class="fw-semibold">Reorder Point</th>
                            <th class="fw-semibold">Status</th>
                            <th class="fw-semibold">Last Restock</th>
                            <th class="fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($filtered_brands as $brand): ?>
                            <?php 
                            $avg_rop = (float)$brand['avg_reorder_point'];
                            $total_stock = (float)$brand['total_stock'];
                            
                            // Use consistent stock status logic
                            $stock_status = $ropCalculator->getStockStatus($total_stock, $avg_rop);
                            $is_low_stock = $stock_status === 'Low Stock';
                            $is_out_of_stock = $stock_status === 'Out of Stock';
                            ?>
                            <tr class="brand-row <?= $is_out_of_stock ? 'out-of-stock-item' : ($is_low_stock ? 'low-stock-item' : '') ?>" 
                                style="cursor: pointer;" 
                                onclick="toggleBrandProducts(<?= $brand['brand_id'] ?>)"
                                data-brand-id="<?= $brand['brand_id'] ?>">
                                <td>
                                    <div class="d-flex align-items-center gap-2">
                                        <i class="fa fa-chevron-right text-muted" id="chevron-<?= $brand['brand_id'] ?>" style="font-size: 0.75rem; transition: transform 0.3s;"></i>
                                        <div class="fw-semibold"><?= htmlspecialchars($brand['brand_name']) ?></div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge" style="background: #cce5ff; color: #004085; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;"><?= $brand['product_count'] ?> products</span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= number_format($brand['total_stock'], 2) ?></span>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= number_format($brand['avg_ads'], 2) ?></span>
                                    <br><small class="text-muted">units/day</small>
                                </td>
                                <td>
                                    <span class="fw-semibold"><?= number_format($avg_rop, 2) ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $status_colors = [
                                        'Sufficient' => ['bg' => '#d4edda', 'color' => '#155724'],
                                        'Low Stock' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                        'Out of Stock' => ['bg' => '#f5c6cb', 'color' => '#721c24']
                                    ];
                                    $colors = $status_colors[$stock_status] ?? $status_colors['Sufficient'];
                                    ?>
                                    <span class="badge" style="background: <?= $colors['bg'] ?>; color: <?= $colors['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                        <?= $stock_status ?>
                                    </span>
                                </td>
                                <td class="text-muted">
                                    <?= !empty($brand['last_restock_date']) ? date('M d, Y', strtotime($brand['last_restock_date'])) : 'Never' ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group" onclick="event.stopPropagation();">
                                        <a href="restocking.php" class="btn btn-sm" style="background: #d4edda; color: #155724; border-radius: 8px;">
                                            <i class="fa fa-plus me-1"></i>Restock
                                        </a>
                                        <a href="product_movement_analysis.php" class="btn btn-sm" style="background: #cce5ff; color: #004085; border-radius: 8px;">
                                            <i class="fa fa-chart-bar me-1"></i>Analysis
                                        </a>
                                    </div>
                                </td>
                            </tr>
                            <!-- Expandable Product-Brand Details Row -->
                            <tr id="products-row-<?= $brand['brand_id'] ?>" class="products-detail-row" style="display: none;">
                                <td colspan="8">
                                    <div class="p-4 bg-light rounded">
                                        <div class="d-flex justify-content-between align-items-center mb-3">
                                            <h6 class="fw-semibold mb-0">
                                                <i class="fa fa-box me-2 text-primary"></i>Product Details for <?= htmlspecialchars($brand['brand_name']) ?>
                                            </h6>
                                        </div>
                                        <?php 
                                        $products = $brandProducts[$brand['brand_id']] ?? [];
                                        if (!empty($products)): ?>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-hover mb-0 bg-white rounded">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th class="fw-semibold">Product</th>
                                                            <th class="fw-semibold">Current Stock</th>
                                                            <th class="fw-semibold">Sales/Day</th>
                                                            <th class="fw-semibold">Reorder Point</th>
                                                            <th class="fw-semibold">Status</th>
                                                            <th class="fw-semibold">Movement Type</th>
                                                            <th class="fw-semibold">Last Restock</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php foreach ($products as $product): ?>
                                                            <?php
                                                            $productStock = (float)$product['current_stock'];
                                                            $productROP = (float)$product['reorder_point'];
                                                            $productStatus = $ropCalculator->getStockStatus($productStock, $productROP);
                                                            $isProductLow = $productStatus === 'Low Stock';
                                                            $isProductOut = $productStatus === 'Out of Stock';
                                                            ?>
                                                            <tr class="<?= $isProductOut ? 'out-of-stock-item' : ($isProductLow ? 'low-stock-item' : '') ?>">
                                                                <td>
                                                                    <div class="fw-semibold"><?= htmlspecialchars($product['product_name']) ?></div>
                                                                    <small class="text-muted"><?= htmlspecialchars($product['uom_name'] ?? '') ?></small>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-semibold <?= $isProductOut ? 'text-danger' : ($isProductLow ? 'text-warning' : 'text-success') ?>">
                                                                        <?= number_format($productStock, 2) ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-semibold"><?= number_format($product['ads'], 2) ?></span>
                                                                </td>
                                                                <td>
                                                                    <span class="fw-semibold"><?= number_format($productROP, 2) ?></span>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    $statusColors = [
                                                                        'Sufficient' => ['bg' => '#d4edda', 'color' => '#155724'],
                                                                        'Low Stock' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                                                        'Out of Stock' => ['bg' => '#f5c6cb', 'color' => '#721c24']
                                                                    ];
                                                                    $statusColor = $statusColors[$productStatus] ?? $statusColors['Sufficient'];
                                                                    ?>
                                                                    <span class="badge" style="background: <?= $statusColor['bg'] ?>; color: <?= $statusColor['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                                                        <?= $productStatus ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php
                                                                    $movementColors = [
                                                                        'Fast-Moving' => ['bg' => '#d4edda', 'color' => '#155724'],
                                                                        'Slow-Moving' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                                                        'Non-Moving' => ['bg' => '#e2e3e5', 'color' => '#383d41']
                                                                    ];
                                                                    $movColor = $movementColors[$product['movement_type']] ?? $movementColors['Non-Moving'];
                                                                    ?>
                                                                    <span class="badge" style="background: <?= $movColor['bg'] ?>; color: <?= $movColor['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                                                        <?= htmlspecialchars($product['movement_type']) ?>
                                                                    </span>
                                                                </td>
                                                                <td class="text-muted">
                                                                    <?= !empty($product['last_restock_date']) ? date('M d, Y', strtotime($product['last_restock_date'])) : 'Never' ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center py-4">
                                                <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                                                <h6 class="text-muted">No Products Found</h6>
                                                <p class="text-muted">This brand doesn't have any active products with stock.</p>
                                            </div>
                                        <?php endif; ?>
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
        function toggleBrandProducts(brandId) {
            const productsRow = document.getElementById('products-row-' + brandId);
            const chevron = document.getElementById('chevron-' + brandId);
            
            if (productsRow.style.display === 'none' || productsRow.style.display === '') {
                // Show products
                productsRow.style.display = 'table-row';
                chevron.style.transform = 'rotate(90deg)';
            } else {
                // Hide products
                productsRow.style.display = 'none';
                chevron.style.transform = 'rotate(0deg)';
            }
        }
    </script>
</body>
</html>
