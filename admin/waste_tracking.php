<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
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

// Handle waste recording
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['record_waste'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $batch_id = $_POST['batch_id'] ?: null;
        $quantity_wasted = (int)$_POST['quantity_wasted'];
        $waste_reason = $_POST['waste_reason'];
        $waste_date = $_POST['waste_date'];
        $notes = $_POST['notes'] ?: null;
        
        if ($quantity_wasted <= 0) {
            throw new Exception("Quantity wasted must be greater than 0.");
        }
        
        // Record waste
        $stmt = $pdo->prepare("
            INSERT INTO waste_tracking (product_id, batch_id, quantity_wasted, waste_reason, waste_date, notes, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, $batch_id, $quantity_wasted, $waste_reason, $waste_date, $notes, $_SESSION['user_id']]);
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = current_stock - ? WHERE product_id = ?");
        $stmt->execute([$quantity_wasted, $product_id]);
        
        // Update batch remaining quantity if batch specified
        if ($batch_id) {
            $stmt = $pdo->prepare("UPDATE product_batches SET remaining_quantity = remaining_quantity - ? WHERE batch_id = ?");
            $stmt->execute([$quantity_wasted, $batch_id]);
        }
        
        // Record stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_type, created_by) 
            VALUES (?, 4, ?, (SELECT current_stock FROM product_stock WHERE product_id = ?) + ?, (SELECT current_stock FROM product_stock WHERE product_id = ?), 'Waste: $waste_reason', 'waste', ?)
        ");
        $stmt->execute([$product_id, $quantity_wasted, $product_id, $quantity_wasted, $product_id, $_SESSION['user_id']]);
        
        logHistory($pdo, 'Waste Recorded', "Product ID: $product_id, Quantity: $quantity_wasted, Reason: $waste_reason", $_SESSION['username']);
        $_SESSION['success'] = "Waste recorded successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error recording waste: " . $e->getMessage();
    }
    header("Location: waste_tracking.php");
    exit;
}

// Get waste statistics
$stmt = $pdo->query("
    SELECT 
        DATE(waste_date) as waste_day,
        COUNT(*) as waste_incidents,
        SUM(quantity_wasted) as total_wasted,
        waste_reason,
        COUNT(*) as reason_count
    FROM waste_tracking 
    WHERE waste_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
    GROUP BY DATE(waste_date), waste_reason
    ORDER BY waste_day DESC
");
$waste_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent waste records
$stmt = $pdo->query("
    SELECT 
        wt.*,
        p.product_name,
        pb.batch_number,
        pb.expiration_date
    FROM waste_tracking wt
    LEFT JOIN products p ON wt.product_id = p.product_id
    LEFT JOIN product_batches pb ON wt.batch_id = pb.batch_id
    ORDER BY wt.waste_date DESC, wt.created_at DESC
    LIMIT 50
");
$recent_waste = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get products with batches for waste recording
$stmt = $pdo->query("
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        ps.current_stock,
        pb.batch_id,
        pb.batch_number,
        pb.remaining_quantity,
        pb.expiration_date
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    LEFT JOIN product_batches pb ON pb.product_id = p.product_id AND pb.remaining_quantity > 0
    WHERE p.is_archive = 0 AND p.is_perishable = 1
    ORDER BY p.product_name, pb.expiration_date ASC
");
$products_with_batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Group products by product_id
$products_grouped = [];
foreach ($products_with_batches as $row) {
    $product_id = $row['product_id'];
    if (!isset($products_grouped[$product_id])) {
        $products_grouped[$product_id] = [
            'product_id' => $row['product_id'],
            'product_name' => $row['product_name'],
            'category_name' => $row['category_name'],
            'current_stock' => $row['current_stock'],
            'batches' => []
        ];
    }
    if ($row['batch_id']) {
        $products_grouped[$product_id]['batches'][] = [
            'batch_id' => $row['batch_id'],
            'batch_number' => $row['batch_number'],
            'remaining_quantity' => $row['remaining_quantity'],
            'expiration_date' => $row['expiration_date']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Waste Tracking</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .waste-reason {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .expired { background-color: #dc3545; color: white; }
        .damaged { background-color: #fd7e14; color: white; }
        .spoiled { background-color: #6f42c1; color: white; }
        .quality_issue { background-color: #20c997; color: white; }
        .other { background-color: #6c757d; color: white; }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

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
                    <i class="fa fa-trash-alt me-3" style="color: #7F1734;"></i>Waste Tracking
                </h1>
                <p class="text-muted">Track and analyze product waste and spoilage</p>
            </div>
            <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#wasteModal">
                <i class="fa fa-plus-circle me-1"></i> Record Waste
            </button>
        </div>

        <!-- Waste Statistics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #dc3545;">
                                <i class="fa fa-trash"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Total Waste (30 days)</h6>
                                <h3 class="mb-0"><?php echo array_sum(array_column($waste_stats, 'total_wasted')); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #fd7e14;">
                                <i class="fa fa-exclamation-triangle"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Waste Incidents</h6>
                                <h3 class="mb-0"><?php echo count($waste_stats); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #6f42c1;">
                                <i class="fa fa-clock"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Most Common Reason</h6>
                                <h6 class="mb-0"><?php 
                                    $reasons = array_column($waste_stats, 'reason_count', 'waste_reason');
                                    echo $reasons ? ucfirst(array_search(max($reasons), $reasons)) : 'N/A';
                                ?></h6>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #20c997;">
                                <i class="fa fa-chart-line"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Avg Daily Waste</h6>
                                <h3 class="mb-0"><?php echo round(array_sum(array_column($waste_stats, 'total_wasted')) / 30, 1); ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Waste Records -->
        <div class="card table-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Recent Waste Records</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Product</th>
                                <th>Batch</th>
                                <th>Quantity</th>
                                <th>Reason</th>
                                <th>Notes</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recent_waste as $waste): ?>
                            <tr>
                                <td><?php echo date('M d, Y', strtotime($waste['waste_date'])); ?></td>
                                <td><?php echo htmlspecialchars($waste['product_name']); ?></td>
                                <td>
                                    <?php if ($waste['batch_number']): ?>
                                        <?php echo htmlspecialchars($waste['batch_number']); ?>
                                        <br><small class="text-muted">Exp: <?php echo date('M d, Y', strtotime($waste['expiration_date'])); ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">No batch</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="badge bg-danger"><?php echo $waste['quantity_wasted']; ?></span></td>
                                <td>
                                    <span class="waste-reason <?php echo $waste['waste_reason']; ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $waste['waste_reason'])); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($waste['notes']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Record Waste Modal -->
    <div class="modal fade" id="wasteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Record Product Waste</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required onchange="loadBatches(this.value)">
                                <option value="">Select Product</option>
                                <?php foreach ($products_grouped as $product): ?>
                                    <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Batch (Optional)</label>
                            <select name="batch_id" id="batchSelect" class="form-select">
                                <option value="">No specific batch</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity Wasted</label>
                            <input type="number" name="quantity_wasted" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Waste Reason</label>
                            <select name="waste_reason" class="form-select" required>
                                <option value="">Select Reason</option>
                                <option value="expired">Expired</option>
                                <option value="damaged">Damaged</option>
                                <option value="spoiled">Spoiled</option>
                                <option value="quality_issue">Quality Issue</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Waste Date</label>
                            <input type="date" name="waste_date" class="form-control" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Notes (Optional)</label>
                            <textarea name="notes" class="form-control" rows="3" placeholder="Additional details about the waste..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="record_waste" class="btn btn-danger">Record Waste</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function loadBatches(productId) {
            const batchSelect = document.getElementById('batchSelect');
            batchSelect.innerHTML = '<option value="">No specific batch</option>';
            
            if (!productId) return;
            
            // This would typically be an AJAX call to get batches for the product
            // For now, we'll show a placeholder
            const products = <?php echo json_encode($products_grouped); ?>;
            const product = products[productId];
            
            if (product && product.batches) {
                product.batches.forEach(batch => {
                    const option = document.createElement('option');
                    option.value = batch.batch_id;
                    option.textContent = `${batch.batch_number} (${batch.remaining_quantity} units, expires ${batch.expiration_date})`;
                    batchSelect.appendChild(option);
                });
            }
        }
    </script>
</body>
</html>
