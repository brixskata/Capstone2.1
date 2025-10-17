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

// Check if reorder points need to be calculated (if table is empty)
$stmt = $pdo->query('SELECT COUNT(*) as count FROM brand_product_stock');
$rop_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

if ($rop_count == 0) {
    // Automatically calculate reorder points on first load
    try {
        $result = $ropCalculator->calculateAllReorderPoints();
        if ($result['success']) {
            logHistory($pdo, 'Auto Reorder Points Calculation', "Automatically calculated {$result['count']} reorder points on first load", $_SESSION['username']);
        }
    } catch (Exception $e) {
        // Log error but don't stop page loading
        error_log("Auto reorder point calculation failed: " . $e->getMessage());
    }
}

// Handle AJAX request for recalculating reorder points
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'recalculate_rop') {
    header('Content-Type: application/json');
    
    try {
        $result = $ropCalculator->calculateAllReorderPoints();
        
        if ($result['success']) {
            logHistory($pdo, 'Reorder Points Recalculated', "Recalculated {$result['count']} brand-product reorder points", $_SESSION['username']);
        }
        
        echo json_encode($result);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage(),
            'count' => 0
        ]);
    }
    exit;
}

// Get analysis period
$period = $_GET['period'] ?? '30'; // days
$period_days = (int)$period;

// Get brand-product stock data with ADS and reorder points
$brand_product_data = $ropCalculator->getBrandProductStockData();

// Group data by movement type for display
$fast_moving = [];
$slow_moving = [];
$non_moving = [];

foreach ($brand_product_data as $item) {
    $movement_type = $item['movement_type'];
    $ads = (float)$item['ads'];
    
    // Add stock status
    $item['stock_status'] = $ropCalculator->getStockStatus($item['current_stock'], $item['reorder_point']);
    
    if ($movement_type === 'Fast-Moving') {
        $fast_moving[] = $item;
    } elseif ($movement_type === 'Slow-Moving') {
        $slow_moving[] = $item;
    } else {
        $non_moving[] = $item;
    }
}

// Calculate summary statistics
$total_items = count($brand_product_data);
$fast_count = count($fast_moving);
$slow_count = count($slow_moving);
$non_count = count($non_moving);

// Calculate inventory value by movement category
$fast_value = array_sum(array_map(function($item) { return $item['current_stock'] * 100; }, $fast_moving)); // Assuming 100 as base value
$slow_value = array_sum(array_map(function($item) { return $item['current_stock'] * 100; }, $slow_moving));
$non_value = array_sum(array_map(function($item) { return $item['current_stock'] * 100; }, $non_moving));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Brand Movement Analysis - Admin Dashboard</title>
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
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2>
                            <i class="fa fa-chart-bar me-3"></i>Brand Movement Analysis
                        </h2>
                        <p class="mb-0 opacity-75">Analyze brand performance and identify movement patterns</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" 
                            style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); cursor: pointer; border-radius: 10px;" 
                            onclick="recalculateReorderPoints()"
                            id="recalculateBtn">
                        <i class="fa fa-calculator me-2"></i>Recalculate Reorder Points
                    </button>
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

            <!-- Fast Moving Brands -->
            <div class="category-section">
                <div class="category-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa fa-rocket me-2"></i>Fast Moving Brands (<?= $fast_count ?>)
                    </h5>
                    <small class="opacity-75">High demand, frequent sales</small>
                </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Brand</th>
                                <th class="fw-semibold">Product</th>
                                <th class="fw-semibold">Average Sales Per Day</th>
                                <th class="fw-semibold">Current Stock</th>
                                <th class="fw-semibold">Reorder Point</th>
                                <th class="fw-semibold">Stock Status</th>
                                <th class="fw-semibold">Last Calculated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($fast_moving as $item): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($item['brand_name']) ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format($item['ads'], 2) ?></span>
                                        <br><small class="text-muted">(<?= $item['total_sales_7d'] ?> sold)</small>
                                    </td>
                                    <td><?= number_format($item['current_stock'], 2) ?></td>
                                    <td><?= number_format($item['reorder_point'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $status = $item['stock_status'];
                                        $status_colors = [
                                            'In Stock' => ['bg' => '#d4edda', 'color' => '#155724'],
                                            'Low Stock' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                            'Out of Stock' => ['bg' => '#f5c6cb', 'color' => '#721c24']
                                        ];
                                        $colors = $status_colors[$status] ?? $status_colors['In Stock'];
                                        ?>
                                        <span class="badge" style="background: <?= $colors['bg'] ?>; color: <?= $colors['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                            <?= $status ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['last_calculated']): ?>
                                            <small class="text-muted"><?= date('M d, Y H:i', strtotime($item['last_calculated'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-warning">Not Calculated</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>


            <!-- Slow Moving Brands -->
            <div class="category-section">
                <div class="category-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa fa-hourglass-half me-2"></i>Slow Moving Brands (<?= $slow_count ?>)
                    </h5>
                    <small class="opacity-75">Low demand, infrequent sales</small>
                </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Brand</th>
                                <th class="fw-semibold">Product</th>
                                <th class="fw-semibold">Average Sales Per Day</th>
                                <th class="fw-semibold">Current Stock</th>
                                <th class="fw-semibold">Reorder Point</th>
                                <th class="fw-semibold">Stock Status</th>
                                <th class="fw-semibold">Last Calculated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($slow_moving as $item): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($item['brand_name']) ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format($item['ads'], 2) ?></span>
                                        <br><small class="text-muted">(<?= $item['total_sales_7d'] ?> sold)</small>
                                    </td>
                                    <td><?= number_format($item['current_stock'], 2) ?></td>
                                    <td><?= number_format($item['reorder_point'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $status = $item['stock_status'];
                                        $status_colors = [
                                            'In Stock' => ['bg' => '#d4edda', 'color' => '#155724'],
                                            'Low Stock' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                            'Out of Stock' => ['bg' => '#f5c6cb', 'color' => '#721c24']
                                        ];
                                        $colors = $status_colors[$status] ?? $status_colors['In Stock'];
                                        ?>
                                        <span class="badge" style="background: <?= $colors['bg'] ?>; color: <?= $colors['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                            <?= $status ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['last_calculated']): ?>
                                            <small class="text-muted"><?= date('M d, Y H:i', strtotime($item['last_calculated'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-warning">Not Calculated</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

            <!-- Non-Moving Brands -->
            <div class="category-section">
                <div class="category-header">
                    <h5 class="fw-bold mb-0">
                        <i class="fa fa-stop me-2"></i>Non-Moving Brands (<?= $non_count ?>)
                    </h5>
                    <small class="opacity-75">No sales activity - potential dead stock</small>
                </div>
            <div class="table-card">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th class="fw-semibold">Brand</th>
                                <th class="fw-semibold">Product</th>
                                <th class="fw-semibold">Average Sales Per Day</th>
                                <th class="fw-semibold">Current Stock</th>
                                <th class="fw-semibold">Reorder Point</th>
                                <th class="fw-semibold">Stock Status</th>
                                <th class="fw-semibold">Last Calculated</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($non_moving as $item): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($item['brand_name']) ?></td>
                                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                                    <td>
                                        <span class="fw-semibold"><?= number_format($item['ads'], 2) ?></span>
                                        <br><small class="text-muted">(<?= $item['total_sales_7d'] ?> sold)</small>
                                    </td>
                                    <td><?= number_format($item['current_stock'], 2) ?></td>
                                    <td><?= number_format($item['reorder_point'], 2) ?></td>
                                    <td>
                                        <?php 
                                        $status = $item['stock_status'];
                                        $status_colors = [
                                            'In Stock' => ['bg' => '#d4edda', 'color' => '#155724'],
                                            'Low Stock' => ['bg' => '#fff3cd', 'color' => '#856404'],
                                            'Out of Stock' => ['bg' => '#f5c6cb', 'color' => '#721c24']
                                        ];
                                        $colors = $status_colors[$status] ?? $status_colors['In Stock'];
                                        ?>
                                        <span class="badge" style="background: <?= $colors['bg'] ?>; color: <?= $colors['color'] ?>; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                                            <?= $status ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($item['last_calculated']): ?>
                                            <small class="text-muted"><?= date('M d, Y H:i', strtotime($item['last_calculated'])) ?></small>
                                        <?php else: ?>
                                            <span class="text-warning">Not Calculated</span>
                                        <?php endif; ?>
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
    <script>
        // Test button click
        document.addEventListener('DOMContentLoaded', function() {
            const btn = document.getElementById('recalculateBtn');
            if (btn) {
                console.log('Recalculate button found:', btn);
                btn.addEventListener('click', function(e) {
                    console.log('Button click event triggered');
                });
            } else {
                console.log('Recalculate button not found');
            }
        });
        
        function recalculateReorderPoints() {
            console.log('Button clicked - recalculateReorderPoints function called');
            
            // Check if SweetAlert2 is loaded
            if (typeof Swal === 'undefined') {
                alert('SweetAlert2 is not loaded. Please refresh the page.');
                return;
            }
            
            Swal.fire({
                title: 'Recalculate Reorder Points?',
                text: 'This will recalculate reorder points for all brand-product combinations based on current sales data. This may take a moment.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7F1734',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, Recalculate',
                cancelButtonText: 'Cancel',
                allowOutsideClick: false,
                customClass: {
                    popup: 'swal2-popup-rounded',
                    confirmButton: 'swal2-confirm-rounded',
                    cancelButton: 'swal2-cancel-rounded'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Calculating...',
                        text: 'Please wait while we recalculate reorder points',
                        icon: 'info',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        customClass: {
                            popup: 'swal2-popup-rounded'
                        },
                        didOpen: () => {
                            Swal.showLoading();
                        }
                    });
                    
                    fetch('product_movement_analysis.php', {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: 'action=recalculate_rop'
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            Swal.fire({
                                title: 'Success!',
                                text: `Successfully recalculated ${data.count} reorder points!`,
                                icon: 'success',
                                confirmButtonColor: '#7F1734',
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'swal2-popup-rounded',
                                    confirmButton: 'swal2-confirm-rounded'
                                }
                            }).then(() => {
                                location.reload();
                            });
                        } else {
                            Swal.fire({
                                title: 'Error',
                                text: data.message || 'Failed to recalculate reorder points',
                                icon: 'error',
                                confirmButtonColor: '#7F1734',
                                confirmButtonText: 'OK',
                                customClass: {
                                    popup: 'swal2-popup-rounded',
                                    confirmButton: 'swal2-confirm-rounded'
                                }
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        Swal.fire({
                            title: 'Error',
                            text: 'Failed to recalculate reorder points. Please try again.',
                            icon: 'error',
                            confirmButtonColor: '#7F1734',
                            confirmButtonText: 'OK',
                            customClass: {
                                popup: 'swal2-popup-rounded',
                                confirmButton: 'swal2-confirm-rounded'
                            }
                        });
                    });
                }
            });
        }
    </script>
</body>
</html>
