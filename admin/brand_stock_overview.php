<?php
include 'includes/db.php';
include_once 'includes/brand_stock_manager.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Initialize brand stock manager
$brandStockManager = new BrandStockManager($pdo);

// Get all brands with their stock levels
$brands_stock = $brandStockManager->getAllBrandsStock();

// Get low stock brands
$low_stock_brands = $brandStockManager->getLowStockBrands(50);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Stock Overview - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
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
                        <i class="fa fa-chart-bar me-3"></i>Brand Stock Overview
                    </h2>
                    <p class="mb-0 opacity-75">Real-time stock levels by brand using product_batches</p>
                </div>
            </div>

            <!-- Low Stock Alert -->
            <?php if (!empty($low_stock_brands)): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <i class="fa fa-exclamation-triangle me-2"></i>
                    <strong>Low Stock Alert:</strong> <?= count($low_stock_brands) ?> brand(s) have low stock levels.
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Brands Stock Table -->
            <div class="table-card">
                <div class="card-header bg-transparent border-0 p-4">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fas fa-list-alt me-2"></i>Brand Stock Levels
                    </h5>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Brand</th>
                                <th class="fw-semibold">Total Stock</th>
                                <th class="fw-semibold">Products</th>
                                <th class="fw-semibold">Batches</th>
                                <th class="fw-semibold">Earliest Expiration</th>
                                <th class="fw-semibold">Latest Expiration</th>
                                <th class="fw-semibold">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($brands_stock as $brand): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($brand['brand_name']) ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format($brand['total_stock'], 2) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #cce5ff; color: #004085; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                            <?= $brand['product_count'] ?> products
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge" style="background: #d1ecf1; color: #0c5460; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                            <?= $brand['batch_count'] ?> batches
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($brand['earliest_expiration']): ?>
                                            <?php 
                                            $exp_date = strtotime($brand['earliest_expiration']);
                                            $today = time();
                                            $days_diff = ($exp_date - $today) / (60 * 60 * 24);
                                            
                                            if ($days_diff < 0) {
                                                echo '<span class="badge" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Expired</span>';
                                            } elseif ($days_diff <= 7) {
                                                echo '<span class="badge" style="background: #fff3cd; color: #856404; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Expires Soon</span>';
                                            } else {
                                                echo '<span class="badge" style="background: #d4edda; color: #155724; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Valid</span>';
                                            }
                                            ?>
                                            <br><small class="text-muted"><?= date('M d, Y', $exp_date) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($brand['latest_expiration']): ?>
                                            <small class="text-muted"><?= date('M d, Y', strtotime($brand['latest_expiration'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((float)$brand['total_stock'] === 0): ?>
                                            <span class="badge" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Out of Stock</span>
                                        <?php elseif ((float)$brand['total_stock'] <= 50): ?>
                                            <span class="badge" style="background: #fff3cd; color: #856404; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge" style="background: #d4edda; color: #155724; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">In Stock</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Summary Statistics -->
            <div class="row g-4 mt-4">
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-tags"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= count($brands_stock) ?></h3>
                            <p class="card-label">Total Brands</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-boxes"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= number_format(array_sum(array_column($brands_stock, 'total_stock')), 0) ?></h3>
                            <p class="card-label">Total Stock Units</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?= count($low_stock_brands) ?></h3>
                            <p class="card-label">Low Stock Brands</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
