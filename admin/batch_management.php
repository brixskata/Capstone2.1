<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
include_once '../includes/batch_manager.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Initialize batch manager
$batchManager = new BatchManager($pdo);

// Handle batch actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            switch ($_POST['action']) {
                case 'deactivate_batch':
                    $batch_id = (int)$_POST['batch_id'];
                    $batchManager->updateBatch($batch_id, ['is_active' => 0]);
                    $_SESSION['success'] = "Batch deactivated successfully!";
                    break;
                    
                case 'activate_batch':
                    $batch_id = (int)$_POST['batch_id'];
                    $batchManager->updateBatch($batch_id, ['is_active' => 1]);
                    $_SESSION['success'] = "Batch activated successfully!";
                    break;
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error: " . $e->getMessage();
        }
        header("Location: batch_management.php");
        exit;
    }
}

// Get filter parameters
$product_filter = $_GET['product_id'] ?? '';
$status_filter = $_GET['status'] ?? 'all';
$expiry_filter = $_GET['expiry'] ?? 'all';

// Build query for batches
$where_conditions = [];
$params = [];

if ($product_filter) {
    $where_conditions[] = "pb.product_id = ?";
    $params[] = $product_filter;
}

if ($status_filter === 'active') {
    $where_conditions[] = "pb.is_active = 1";
} elseif ($status_filter === 'inactive') {
    $where_conditions[] = "pb.is_active = 0";
}

if ($expiry_filter === 'expiring') {
    $where_conditions[] = "pb.expiration_date IS NOT NULL AND pb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)";
} elseif ($expiry_filter === 'expired') {
    $where_conditions[] = "pb.expiration_date IS NOT NULL AND pb.expiration_date < CURDATE()";
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Fetch batches
$stmt = $pdo->prepare("
    SELECT 
        pb.*,
        p.product_name,
        s.name as supplier_name,
        u.name as uom_name,
        DATEDIFF(pb.expiration_date, CURDATE()) as days_until_expiry
    FROM product_batches pb
    JOIN products p ON pb.product_id = p.product_id
    LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    {$where_clause}
    ORDER BY pb.received_date DESC, pb.batch_id DESC
");
$stmt->execute($params);
$batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch products for filter
$stmt = $pdo->query("SELECT product_id, product_name FROM products WHERE is_archive = 0 ORDER BY product_name");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get expiring batches count
$expiring_count = count($batchManager->getExpiringBatches(7));
$expired_count = count($batchManager->getExpiringBatches(-1));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Batch Management - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .batch-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .batch-header {
            display: flex;
            justify-content: between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .batch-number {
            font-weight: bold;
            color: #7F1734;
            font-size: 1.1rem;
        }
        
        .expiry-badge {
            font-size: 0.8rem;
            padding: 4px 8px;
            border-radius: 12px;
        }
        
        .quantity-bar {
            height: 8px;
            background-color: #e9ecef;
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .quantity-fill {
            height: 100%;
            background-color: #28a745;
            transition: width 0.3s ease;
        }
        
        .quantity-fill.warning {
            background-color: #ffc107;
        }
        
        .quantity-fill.danger {
            background-color: #dc3545;
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
                    <i class="fa fa-boxes me-3" style="color: #7F1734;"></i>Batch Management
                </h1>
                <p class="text-muted">Track product batches with FIFO inventory management</p>
            </div>
            <div class="d-flex gap-2">
                <?php if ($expiring_count > 0): ?>
                    <span class="badge bg-warning fs-6"><?= $expiring_count ?> Expiring Soon</span>
                <?php endif; ?>
                <?php if ($expired_count > 0): ?>
                    <span class="badge bg-danger fs-6"><?= $expired_count ?> Expired</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Filters -->
        <div class="batch-card mb-4">
            <h5 class="fw-bold mb-3">Filters</h5>
            <form method="GET" class="row g-3">
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Product</label>
                    <select name="product_id" class="form-select">
                        <option value="">All Products</option>
                        <?php foreach ($products as $product): ?>
                            <option value="<?= $product['product_id'] ?>" <?= $product_filter == $product['product_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($product['product_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $status_filter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="active" <?= $status_filter === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status_filter === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-semibold">Expiration</label>
                    <select name="expiry" class="form-select">
                        <option value="all" <?= $expiry_filter === 'all' ? 'selected' : '' ?>>All</option>
                        <option value="expiring" <?= $expiry_filter === 'expiring' ? 'selected' : '' ?>>Expiring Soon</option>
                        <option value="expired" <?= $expiry_filter === 'expired' ? 'selected' : '' ?>>Expired</option>
                    </select>
                </div>
                <div class="col-md-2">
                    <label class="form-label">&nbsp;</label>
                    <button type="submit" class="btn btn-primary d-block w-100">
                        <i class="fa fa-filter me-1"></i>Filter
                    </button>
                </div>
            </form>
        </div>

        <!-- Batches List -->
        <div class="row">
            <?php if (empty($batches)): ?>
                <div class="col-12">
                    <div class="batch-card text-center">
                        <i class="fa fa-inbox fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No batches found</h5>
                        <p class="text-muted">No batches match your current filters.</p>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($batches as $batch): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="batch-card">
                            <div class="batch-header">
                                <div class="batch-number"><?= htmlspecialchars($batch['batch_number']) ?></div>
                                <div>
                                    <?php if ($batch['is_active']): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Inactive</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="fw-bold mb-1"><?= htmlspecialchars($batch['product_name']) ?></h6>
                                <?php if ($batch['supplier_name']): ?>
                                    <small class="text-muted">Supplier: <?= htmlspecialchars($batch['supplier_name']) ?></small>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3">
                                <div class="d-flex justify-content-between mb-1">
                                    <small class="text-muted">Quantity</small>
                                    <small class="fw-semibold"><?= $batch['quantity_remaining'] ?> / <?= $batch['quantity_received'] ?> <?= htmlspecialchars($batch['uom_name']) ?></small>
                                </div>
                                <div class="quantity-bar">
                                    <?php 
                                    $percentage = ($batch['quantity_remaining'] / $batch['quantity_received']) * 100;
                                    $bar_class = $percentage > 50 ? '' : ($percentage > 20 ? 'warning' : 'danger');
                                    ?>
                                    <div class="quantity-fill <?= $bar_class ?>" style="width: <?= $percentage ?>%"></div>
                                </div>
                            </div>
                            
                            <?php if ($batch['expiration_date']): ?>
                                <div class="mb-3">
                                    <?php 
                                    $days_until_expiry = $batch['days_until_expiry'];
                                    if ($days_until_expiry < 0) {
                                        echo '<span class="badge bg-danger expiry-badge">Expired ' . abs($days_until_expiry) . ' days ago</span>';
                                    } elseif ($days_until_expiry <= 7) {
                                        echo '<span class="badge bg-warning expiry-badge">Expires in ' . $days_until_expiry . ' days</span>';
                                    } else {
                                        echo '<span class="badge bg-success expiry-badge">Expires ' . date('M d, Y', strtotime($batch['expiration_date'])) . '</span>';
                                    }
                                    ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <small class="text-muted">
                                    <i class="fa fa-calendar me-1"></i>
                                    Received: <?= date('M d, Y', strtotime($batch['received_date'])) ?>
                                </small>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <button class="btn btn-sm btn-outline-info" onclick="viewBatchDetails(<?= $batch['batch_id'] ?>)">
                                    <i class="fa fa-eye me-1"></i>Details
                                </button>
                                <?php if ($batch['is_active']): ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="deactivate_batch">
                                        <input type="hidden" name="batch_id" value="<?= $batch['batch_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-warning" onclick="return confirm('Deactivate this batch?')">
                                            <i class="fa fa-pause me-1"></i>Deactivate
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <form method="POST" style="display: inline;">
                                        <input type="hidden" name="action" value="activate_batch">
                                        <input type="hidden" name="batch_id" value="<?= $batch['batch_id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-success">
                                            <i class="fa fa-play me-1"></i>Activate
                                        </button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Batch Details Modal -->
    <div class="modal fade" id="batchDetailsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title fw-bold">
                        <i class="fa fa-box me-2" style="color: #7F1734;"></i>Batch Details
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="batchDetailsContent">
                    <!-- Content will be loaded via AJAX -->
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        function viewBatchDetails(batchId) {
            // Load batch details via AJAX
            fetch(`batch_details.php?batch_id=${batchId}`)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('batchDetailsContent').innerHTML = data;
                    new bootstrap.Modal(document.getElementById('batchDetailsModal')).show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading batch details');
                });
        }
    </script>
</body>
</html>
