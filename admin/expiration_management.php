<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle batch expiration update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expiration'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $expiration_date = $_POST['expiration_date'];
        $batch_number = $_POST['batch_number'] ?: null;
        $quantity = (int)$_POST['quantity'];
        
        // Insert batch record
        $stmt = $pdo->prepare("INSERT INTO product_batches (product_id, batch_number, quantity, expiration_date, created_at) VALUES (?, ?, ?, ?, NOW())");
        $stmt->execute([$product_id, $batch_number, $expiration_date, $expiration_date]);
        
        // Update product stock expiration date
        $stmt = $pdo->prepare("UPDATE product_stock SET expiration_date = ? WHERE product_id = ?");
        $stmt->execute([$expiration_date, $product_id]);
        
        logHistory($pdo, 'Batch Added', "Product ID: $product_id, Batch: $batch_number, Expiry: $expiration_date, Qty: $quantity", $_SESSION['username']);
        $_SESSION['success'] = "Batch expiration date updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating expiration: " . $e->getMessage();
    }
    header("Location: expiration_management.php");
    exit;
}

// Get products with expiration data
$stmt = $pdo->query("
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        ps.current_stock,
        ps.expiration_date,
        ps.last_restock_date,
        DATEDIFF(ps.expiration_date, CURDATE()) as days_until_expiry,
        CASE 
            WHEN ps.expiration_date IS NULL THEN 'No Expiry Set'
            WHEN DATEDIFF(ps.expiration_date, CURDATE()) < 0 THEN 'EXPIRED'
            WHEN DATEDIFF(ps.expiration_date, CURDATE()) <= 3 THEN 'CRITICAL'
            WHEN DATEDIFF(ps.expiration_date, CURDATE()) <= 7 THEN 'WARNING'
            ELSE 'GOOD'
        END as expiry_status
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    WHERE p.is_archive = 0
    ORDER BY 
        CASE 
            WHEN ps.expiration_date IS NULL THEN 1
            WHEN DATEDIFF(ps.expiration_date, CURDATE()) < 0 THEN 0
            ELSE 2
        END,
        ps.expiration_date ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expiration statistics
$expired_count = count(array_filter($products, fn($p) => $p['expiry_status'] === 'EXPIRED'));
$critical_count = count(array_filter($products, fn($p) => $p['expiry_status'] === 'CRITICAL'));
$warning_count = count(array_filter($products, fn($p) => $p['expiry_status'] === 'WARNING'));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expiration Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .expiry-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
            font-weight: 600;
        }
        .expired { background-color: #dc3545; color: white; }
        .critical { background-color: #fd7e14; color: white; }
        .warning { background-color: #ffc107; color: black; }
        .good { background-color: #198754; color: white; }
        .no-expiry { background-color: #6c757d; color: white; }
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
                    <i class="fa fa-clock me-3" style="color: #7F1734;"></i>Expiration Management
                </h1>
                <p class="text-muted">Track and manage product expiration dates for perishable items</p>
            </div>
            <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#expirationModal">
                <i class="fa fa-plus-circle me-1"></i> Set Expiration Date
            </button>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #dc3545;">
                                <i class="fa fa-exclamation-triangle"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Expired</h6>
                                <h3 class="mb-0"><?php echo $expired_count; ?></h3>
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
                                <i class="fa fa-exclamation-circle"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Critical (≤3 days)</h6>
                                <h3 class="mb-0"><?php echo $critical_count; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #ffc107;">
                                <i class="fa fa-clock"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Warning (≤7 days)</h6>
                                <h3 class="mb-0"><?php echo $warning_count; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="d-flex align-items-center">
                            <div class="stat-icon" style="background-color: #198754;">
                                <i class="fa fa-check-circle"></i>
                            </div>
                            <div class="ms-3">
                                <h6 class="card-title text-muted mb-1">Good</h6>
                                <h3 class="mb-0"><?php echo count($products) - $expired_count - $critical_count - $warning_count; ?></h3>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Table -->
        <div class="card table-card">
            <div class="card-header">
                <h5 class="card-title mb-0">Products by Expiration Status</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Category</th>
                                <th>Current Stock</th>
                                <th>Expiration Date</th>
                                <th>Days Until Expiry</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="fw-bold"><?php echo htmlspecialchars($product['product_name']); ?></div>
                                </td>
                                <td><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>
                                    <span class="badge bg-primary"><?php echo $product['current_stock']; ?></span>
                                </td>
                                <td>
                                    <?php if ($product['expiration_date']): ?>
                                        <?php echo date('M d, Y', strtotime($product['expiration_date'])); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not set</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['expiration_date']): ?>
                                        <?php echo $product['days_until_expiry']; ?> days
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="expiry-badge <?php echo strtolower($product['expiry_status']); ?>">
                                        <?php echo $product['expiry_status']; ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="setExpiration(<?php echo $product['product_id']; ?>, '<?php echo htmlspecialchars($product['product_name']); ?>')">
                                        <i class="fa fa-edit"></i> Set Expiry
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <!-- Set Expiration Modal -->
    <div class="modal fade" id="expirationModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Set Product Expiration Date</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="product_id" id="modal_product_id">
                        <div class="mb-3">
                            <label class="form-label">Product</label>
                            <input type="text" class="form-control" id="modal_product_name" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Batch Number (Optional)</label>
                            <input type="text" name="batch_number" class="form-control" placeholder="e.g., BATCH001">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Expiration Date</label>
                            <input type="date" name="expiration_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_expiration" class="btn btn-success">Set Expiration</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function setExpiration(productId, productName) {
            document.getElementById('modal_product_id').value = productId;
            document.getElementById('modal_product_name').value = productName;
            new bootstrap.Modal(document.getElementById('expirationModal')).show();
        }
    </script>
</body>
</html>
