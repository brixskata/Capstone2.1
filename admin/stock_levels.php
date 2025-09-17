<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle reorder point update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_reorder_point'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $reorder_point = (int)$_POST['reorder_point'];
        
        if ($reorder_point < 0) {
            throw new Exception("Reorder point cannot be negative.");
        }

        // Update reorder point
        $stmt = $pdo->prepare("UPDATE product_stock SET reorder_point = ? WHERE product_id = ?");
        $stmt->execute([$reorder_point, $product_id]);

        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();

        logHistory($pdo, 'Reorder Point Updated', "Product: $product_name, New Reorder Point: $reorder_point", $_SESSION['username']);
        $_SESSION['success'] = "Reorder point updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating reorder point: " . $e->getMessage();
    }
    header("Location: stock_levels.php");
    exit;
}

// Fetch products with detailed stock information
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

// Calculate stock level statistics
$total_products = count($products);
$low_stock_products = count(array_filter($products, fn($p) => (int)$p['stock'] <= (int)($p['reorder_point'] ?? 10)));
$out_of_stock_products = count(array_filter($products, fn($p) => (int)$p['stock'] === 0));
$in_stock_products = $total_products - $low_stock_products;

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

// Filter products based on status
$filter = $_GET['filter'] ?? 'all';
$filtered_products = $products;

if ($filter === 'low_stock') {
    $filtered_products = array_filter($products, fn($p) => (int)$p['stock'] <= (int)($p['reorder_point'] ?? 10) && (int)$p['stock'] > 0);
} elseif ($filter === 'out_of_stock') {
    $filtered_products = array_filter($products, fn($p) => (int)$p['stock'] === 0);
} elseif ($filter === 'in_stock') {
    $filtered_products = array_filter($products, fn($p) => (int)$p['stock'] > (int)($p['reorder_point'] ?? 10));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Levels - Admin Dashboard</title>
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
        
        .low-stock-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .out-of-stock-item {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        
        .filter-btn {
            border-radius: 20px;
            padding: 8px 16px;
            font-size: 0.875rem;
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
                    <i class="fa fa-chart-line me-3" style="color: #7F1734;"></i>Stock Levels
                </h1>
                <p class="text-muted">Monitor real-time stock levels and manage reorder points</p>
            </div>
        </div>

        <!-- Statistics -->
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
                        <div class="stat-icon bg-success">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $in_stock_products ?></h4>
                            <small class="text-muted text-uppercase">In Stock</small>
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
                            <small class="text-muted text-uppercase">Low Stock</small>
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
        </div>

        <!-- Filters -->
        <div class="d-flex gap-2 mb-4">
            <a href="?filter=all" class="btn filter-btn <?= $filter === 'all' ? 'btn-primary' : 'btn-outline-primary' ?>">
                All Products (<?= $total_products ?>)
            </a>
            <a href="?filter=in_stock" class="btn filter-btn <?= $filter === 'in_stock' ? 'btn-success' : 'btn-outline-success' ?>">
                In Stock (<?= $in_stock_products ?>)
            </a>
            <a href="?filter=low_stock" class="btn filter-btn <?= $filter === 'low_stock' ? 'btn-warning' : 'btn-outline-warning' ?>">
                Low Stock (<?= $low_stock_products ?>)
            </a>
            <a href="?filter=out_of_stock" class="btn filter-btn <?= $filter === 'out_of_stock' ? 'btn-danger' : 'btn-outline-danger' ?>">
                Out of Stock (<?= $out_of_stock_products ?>)
            </a>
        </div>

        <!-- Stock Levels Table -->
        <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Current Stock Levels</h5>
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
                        <?php foreach ($filtered_products as $product): ?>
                            <tr class="<?= (int)$product['stock'] === 0 ? 'out-of-stock-item' : ((int)$product['stock'] <= (int)$product['reorder_point'] ? 'low-stock-item' : '') ?>">
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
                                <td>
                                    <span class="fw-semibold"><?= $product['reorder_point'] ?></span>
                                </td>
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
                                        <button class="btn btn-sm btn-info" onclick="openReorderModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= $product['reorder_point'] ?>)">
                                            <i class="fa fa-edit me-1"></i>Edit Reorder Point
                                        </button>
                                        <a href="restocking.php" class="btn btn-sm btn-success">
                                            <i class="fa fa-plus me-1"></i>Restock
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

    <!-- Reorder Point Modal -->
    <div class="modal fade" id="reorderModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="stock_levels.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Update Reorder Point
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="update_reorder_point" value="1">
                        <input type="hidden" name="product_id" id="reorderProductId">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Product</label>
                            <input type="text" id="reorderProductName" class="form-control" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">New Reorder Point</label>
                            <input type="number" name="reorder_point" id="reorderPointInput" class="form-control" required min="0">
                            <small class="text-muted">Set the minimum stock level at which to trigger reordering</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734;">
                            <i class="fa fa-save me-2"></i>Update Reorder Point
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        function openReorderModal(productId, productName, currentReorderPoint) {
            const modal = new bootstrap.Modal(document.getElementById('reorderModal'));
            document.getElementById('reorderProductId').value = productId;
            document.getElementById('reorderProductName').value = productName;
            document.getElementById('reorderPointInput').value = currentReorderPoint;
            modal.show();
        }
    </script>
</body>
</html>
