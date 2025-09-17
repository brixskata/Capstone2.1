<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle expiration date update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_expiration'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $expiration_date = $_POST['expiration_date'];
        
        // Validate expiration date
        if (empty($expiration_date) || !strtotime($expiration_date)) {
            throw new Exception("Invalid expiration date.");
        }
        
        // Update product_stock expiration_date
        $stmt = $pdo->prepare("UPDATE product_stock SET expiration_date = ? WHERE product_id = ?");
        $stmt->execute([$expiration_date, $product_id]);
        
        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();
        
        logHistory($pdo, 'Expiration Date Update', "Product: $product_name, Expiration: $expiration_date", $_SESSION['username']);
        $_SESSION['success'] = "Expiration date updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating expiration date: " . $e->getMessage();
    }
    header("Location: restocking.php");
    exit;
}

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    try {
        $restock_id = (int)$_POST['restocking_id'];
        $new_status = (int)$_POST['status_id'];
        
        if (!in_array($new_status, [1, 2, 3])) {
            throw new Exception("Invalid status.");
        }
        
        $pdo->beginTransaction();
        
        // Get current restocking record
        $stmt = $pdo->prepare("SELECT * FROM restocking WHERE restocking_id = ?");
        $stmt->execute([$restock_id]);
        $restock = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$restock) {
            throw new Exception("Restocking record not found.");
        }
        
        // Validate status transition (enum-like behavior: Pending → Received, Pending → Cancelled)
        $current_status = $restock['status_id'];
        $valid_transitions = [
            1 => [2, 3], // Pending can go to Received or Cancelled
            2 => [],     // Received cannot change (final state)
            3 => []      // Cancelled cannot change (final state)
        ];
        
        if (!in_array($new_status, $valid_transitions[$current_status])) {
            $status_names = [1 => 'Pending', 2 => 'Received', 3 => 'Cancelled'];
            throw new Exception("Invalid status transition from {$status_names[$current_status]} to {$status_names[$new_status]}. Valid transitions: " . implode(', ', array_map(function($s) use ($status_names) { return $status_names[$s]; }, $valid_transitions[$current_status])));
        }
        
        // Update status
        $stmt = $pdo->prepare("UPDATE restocking SET status_id = ? WHERE restocking_id = ?");
        $stmt->execute([$new_status, $restock_id]);
        
        // If changing to "Received" (status 2), update stock
        if ($new_status == 2 && $restock['status_id'] != 2) {
            // Update product_stock current_stock and last_restock_date
            $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ? WHERE product_id = ?");
            $stmt->execute([$restock['quantity_added'], $restock['restock_date'], $restock['product_id']]);
            
            // Get new current stock
            $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
            $stmt->execute([$restock['product_id']]);
            $current_stock = (int)$stmt->fetchColumn();
            
            // Record stock movement
            $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 1, ?, ?, ?, 'Restocking - Status Updated', ?, 'restock', ?)");
            $stmt->execute([$restock['product_id'], $restock['quantity_added'], $current_stock - $restock['quantity_added'], $current_stock, $restock_id, $_SESSION['user_id']]);
        }
        
        $pdo->commit();
        
        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$restock['product_id']]);
        $product_name = $stmt->fetchColumn();
        
        $status_names = [1 => 'Pending', 2 => 'Received', 3 => 'Cancelled'];
        logHistory($pdo, 'Restocking Status Update', "Product: $product_name, Status: {$status_names[$new_status]}", $_SESSION['username']);
        $_SESSION['success'] = "Restocking status updated successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error updating status: " . $e->getMessage();
    }
    header("Location: restocking.php");
    exit;
}

// Handle restocking form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock'])) {
    try {
        // Validate required fields
        $required_fields = ['product_id', 'supplier_id', 'quantity_added', 'cost_per_unit', 'restock_date', 'expiration_date'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        $product_id = (int)$_POST['product_id'];
        $supplier_id = (int)$_POST['supplier_id'];
        $quantity_added = (int)$_POST['quantity_added'];
        $cost_per_unit = (float)$_POST['cost_per_unit'];
        $total_cost = $quantity_added * $cost_per_unit;
        $restock_date = $_POST['restock_date'];
        $expiration_date = $_POST['expiration_date'];
        $expected_delivery = $_POST['expected_delivery'] ?: null;
        $notes = $_POST['notes'] ?: null;

        // Validate positive values
        if ($quantity_added <= 0) {
            throw new Exception("Quantity added must be greater than 0.");
        }
        if ($cost_per_unit < 0) {
            throw new Exception("Cost per unit cannot be negative.");
        }
        
        // Validate expiration date
        if (strtotime($expiration_date) <= strtotime($restock_date)) {
            throw new Exception("Expiration date must be after restock date.");
        }

        $pdo->beginTransaction();

        // Insert restocking record
        $stmt = $pdo->prepare("INSERT INTO restocking (product_id, supplier_id, quantity_added, cost_per_unit, total_cost, restock_date, expected_delivery, status_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 2, ?, ?)");
        $stmt->execute([$product_id, $supplier_id, $quantity_added, $cost_per_unit, $total_cost, $restock_date, $expected_delivery, $notes, $_SESSION['user_id']]);

        // Update product_stock current_stock, last_restock_date, and expiration_date
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ?, expiration_date = ? WHERE product_id = ?");
        $stmt->execute([$quantity_added, $restock_date, $expiration_date, $product_id]);

        // Fetch new current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();

        // Record stock movement
        $restock_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 1, ?, ?, ?, 'Restocking', ?, 'restock', ?)");
        $stmt->execute([$product_id, $quantity_added, $current_stock - $quantity_added, $current_stock, $restock_id, $_SESSION['user_id']]);

        $pdo->commit();

        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();

        logHistory($pdo, 'Restocking', "Product: $product_name, Quantity: $quantity_added, Cost: ₱$total_cost", $_SESSION['username']);
        $_SESSION['success'] = "Restocking recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error recording restocking: " . $e->getMessage();
    }
    header("Location: restocking.php");
    exit;
}

// Fetch products with current stock
$stmt = $pdo->query("
    SELECT 
        p.product_id AS id,
        p.product_name AS name,
        c.category_name as category_name,
        b.name as brand_name,
        s.name as supplier_name,
        u.name as uom_name,
        COALESCE(ps.current_stock,0) AS stock,
        COALESCE(ps.reorder_point, 10) AS reorder_point
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

// Fetch suppliers
$stmt = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_archive = 0 ORDER BY name");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent restocking records
$stmt = $pdo->query("
    SELECT 
        r.*,
        p.product_name,
        s.name as supplier_name,
        u.name as uom_name,
        ps.expiration_date
    FROM restocking r
    JOIN products p ON r.product_id = p.product_id
    JOIN suppliers s ON r.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = r.product_id
    ORDER BY r.restock_date DESC, r.created_at DESC
    LIMIT 20
");
$recent_restocks = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate restocking statistics
$total_restocks = $pdo->query("SELECT COUNT(*) FROM restocking WHERE status_id = 2")->fetchColumn();
$total_restock_value = $pdo->query("SELECT SUM(total_cost) FROM restocking WHERE status_id = 2")->fetchColumn() ?: 0;
$pending_restocks = $pdo->query("SELECT COUNT(*) FROM restocking WHERE status_id = 1")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Restocking Management - Admin Dashboard</title>
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
        
        .low-stock-item {
            background-color: #fff3cd;
            border-left: 4px solid #ffc107;
        }
        
        .out-of-stock-item {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
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
                    <i class="fa fa-plus-circle me-3" style="color: #7F1734;"></i>Restocking Management
                </h1>
                <p class="text-muted">Record new stock entries and track restocking activities</p>
            </div>
            <button class="btn text-white fw-bold px-4" style="background-color: #7F1734;" data-bs-toggle="modal" data-bs-target="#restockModal">
                <i class="fa fa-plus me-2"></i>Record Restocking
            </button>
        </div>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-success">
                            <i class="fa fa-check-circle"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $total_restocks ?></h4>
                            <small class="text-muted text-uppercase">Completed Restocks</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-clock"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $pending_restocks ?></h4>
                            <small class="text-muted text-uppercase">Pending Restocks</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-money-bill"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0">₱<?= number_format($total_restock_value, 2) ?></h4>
                            <small class="text-muted text-uppercase">Total Value</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-danger">
                            <i class="fa fa-exclamation-triangle"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= count(array_filter($products, fn($p) => (int)$p['stock'] <= (int)$p['reorder_point'])) ?></h4>
                            <small class="text-muted text-uppercase">Need Restocking</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products Needing Restocking -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Products Needing Restocking</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Current Stock</th>
                            <th class="fw-semibold">Reorder Point</th>
                            <th class="fw-semibold">Status</th>
                            <th class="fw-semibold">Supplier</th>
                            <th class="fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <?php if ((int)$product['stock'] <= (int)$product['reorder_point']): ?>
                                <tr class="<?= (int)$product['stock'] === 0 ? 'out-of-stock-item' : 'low-stock-item' ?>">
                                    <td>
                                        <div class="fw-semibold"><?= htmlspecialchars($product['name']) ?></div>
                                        <small class="text-muted"><?= htmlspecialchars($product['category_name']) ?></small>
                                    </td>
                                    <td>
                                        <span class="fw-semibold"><?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></span>
                                    </td>
                                    <td><?= $product['reorder_point'] ?></td>
                                    <td>
                                        <?php if ((int)$product['stock'] === 0): ?>
                                            <span class="badge bg-danger">Out of Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Low Stock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($product['supplier_name']) ?></td>
                                    <td>
                                        <button class="btn btn-sm btn-success" onclick="openRestockModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                                            <i class="fa fa-plus me-1"></i>Restock
                                        </button>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Restocking Records -->
        <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Recent Restocking Records</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                         <tr>
                             <th class="fw-semibold">Date</th>
                             <th class="fw-semibold">Product</th>
                             <th class="fw-semibold">Supplier</th>
                             <th class="fw-semibold">Quantity</th>
                             <th class="fw-semibold">Cost per Unit</th>
                             <th class="fw-semibold">Total Cost</th>
                             <th class="fw-semibold">Expiration</th>
                             <th class="fw-semibold">Status</th>
                             <th class="fw-semibold">Actions</th>
                         </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_restocks as $restock): ?>
                            <tr>
                                <td><?= date('M d, Y', strtotime($restock['restock_date'])) ?></td>
                                <td><?= htmlspecialchars($restock['product_name']) ?></td>
                                <td><?= htmlspecialchars($restock['supplier_name']) ?></td>
                                <td><?= $restock['quantity_added'] ?> <?= htmlspecialchars($restock['uom_name']) ?></td>
                                <td>₱<?= number_format($restock['cost_per_unit'], 2) ?></td>
                                <td>₱<?= number_format($restock['total_cost'], 2) ?></td>
                                <td>
                                    <?php if (!empty($restock['expiration_date']) && $restock['expiration_date'] !== '0000-00-00'): ?>
                                        <?php 
                                        $exp_date = strtotime($restock['expiration_date']);
                                        $today = time();
                                        $days_until_expiry = floor(($exp_date - $today) / (60 * 60 * 24));
                                        
                                        if ($days_until_expiry < 0): ?>
                                            <span class="badge bg-danger">Expired</span>
                                        <?php elseif ($days_until_expiry <= 7): ?>
                                            <span class="badge bg-warning"><?= date('M d, Y', $exp_date) ?></span>
                                        <?php else: ?>
                                            <span class="text-muted"><?= date('M d, Y', $exp_date) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <div class="d-flex align-items-center gap-2">
                                            <span class="text-muted small">
                                                <i class="fa fa-info-circle me-1"></i>No expiration set
                                            </span>
                                            <button class="btn btn-sm btn-outline-primary" 
                                                    onclick="setExpirationDate(<?= $restock['product_id'] ?>, '<?= htmlspecialchars($restock['product_name']) ?>')"
                                                    title="Set expiration date">
                                                <i class="fa fa-calendar-plus"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($restock['status_id'] == 1): ?>
                                        <span class="badge bg-warning">Pending</span>
                                    <?php elseif ($restock['status_id'] == 2): ?>
                                        <span class="badge bg-success">Received</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Cancelled</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group" role="group">
                                        <?php 
                                        $restock_id = $restock['restocking_id'];
                                        $current_status = $restock['status_id'];
                                        
                                        // Define valid transitions for each status
                                        $valid_transitions = [
                                            1 => [2, 3], // Pending can go to Received or Cancelled
                                            2 => [],     // Received cannot change (final state)
                                            3 => []      // Cancelled cannot change (final state)
                                        ];
                                        
                                        $status_buttons = [
                                            2 => ['class' => 'btn-success', 'icon' => 'fa-check', 'title' => 'Mark as Received'],
                                            3 => ['class' => 'btn-danger', 'icon' => 'fa-times', 'title' => 'Cancel']
                                        ];
                                        
                                        // Show buttons for valid transitions only
                                        foreach ($valid_transitions[$current_status] as $status_id):
                                            $button = $status_buttons[$status_id];
                                        ?>
                                            <button class="btn btn-sm <?php echo $button['class']; ?> status-update-btn" 
                                                    data-restock-id="<?php echo $restock_id; ?>" 
                                                    data-status="<?php echo $status_id; ?>" 
                                                    title="<?php echo $button['title']; ?>">
                                                <i class="fa <?php echo $button['icon']; ?>"></i>
                                            </button>
                                        <?php endforeach; ?>
                                        
                                        <?php if (empty($valid_transitions[$current_status])): ?>
                                            <span class="text-muted small">No actions available</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Restocking Modal -->
    <div class="modal fade" id="restockModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="restocking.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fa fa-plus-circle me-2" style="color: #7F1734;"></i>Record Restocking
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="restock" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product</label>
                                <select name="product_id" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (Current: <?= $product['stock'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Supplier</label>
                                <select name="supplier_id" class="form-select" required>
                                    <option value="">Select Supplier</option>
                                    <?php foreach ($suppliers as $supplier): ?>
                                        <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Quantity Added</label>
                                <input type="number" name="quantity_added" class="form-control" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Cost per Unit (₱)</label>
                                <input type="number" step="0.01" name="cost_per_unit" class="form-control" required min="0">
                            </div>
                             <div class="col-md-6">
                                 <label class="form-label fw-semibold">Restock Date</label>
                                 <input type="date" name="restock_date" class="form-control" required value="<?= date('Y-m-d') ?>">
                             </div>
                             <div class="col-md-6">
                                 <label class="form-label fw-semibold">Expiration Date</label>
                                 <input type="date" name="expiration_date" class="form-control" required>
                             </div>
                             <div class="col-md-6">
                                 <label class="form-label fw-semibold">Expected Delivery</label>
                                 <input type="date" name="expected_delivery" class="form-control">
                             </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Additional notes about this restocking..."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734;">
                            <i class="fa fa-save me-2"></i>Record Restocking
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
         function openRestockModal(productId, productName) {
             const modal = new bootstrap.Modal(document.getElementById('restockModal'));
             const select = document.querySelector('#restockModal select[name="product_id"]');
             if (select) select.value = productId;
             modal.show();
         }
         
         function setExpirationDate(productId, productName) {
             const expirationDate = prompt(`Set expiration date for ${productName}:\n\nEnter date (YYYY-MM-DD):`);
             if (expirationDate && expirationDate.match(/^\d{4}-\d{2}-\d{2}$/)) {
                 // Validate date
                 const date = new Date(expirationDate);
                 if (date instanceof Date && !isNaN(date)) {
                     if (confirm(`Set expiration date to ${expirationDate} for ${productName}?`)) {
                         // Create a form to submit the expiration date update
                         const form = document.createElement('form');
                         form.method = 'POST';
                         form.action = 'restocking.php';
                         
                         const productIdInput = document.createElement('input');
                         productIdInput.type = 'hidden';
                         productIdInput.name = 'product_id';
                         productIdInput.value = productId;
                         
                         const expirationInput = document.createElement('input');
                         expirationInput.type = 'hidden';
                         expirationInput.name = 'expiration_date';
                         expirationInput.value = expirationDate;
                         
                         const updateExpirationInput = document.createElement('input');
                         updateExpirationInput.type = 'hidden';
                         updateExpirationInput.name = 'update_expiration';
                         updateExpirationInput.value = '1';
                         
                         form.appendChild(productIdInput);
                         form.appendChild(expirationInput);
                         form.appendChild(updateExpirationInput);
                         
                         document.body.appendChild(form);
                         form.submit();
                     }
                 } else {
                     alert('Invalid date format. Please use YYYY-MM-DD format.');
                 }
             } else if (expirationDate) {
                 alert('Invalid date format. Please use YYYY-MM-DD format.');
             }
         }
        
        function updateStatus(restockId, newStatus) {
            const statusNames = {1: 'Pending', 2: 'Received', 3: 'Cancelled'};
            const statusName = statusNames[newStatus];
            
            if (confirm(`Are you sure you want to mark this restocking as "${statusName}"?`)) {
                // Create a form to submit the status update
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'restocking.php';
                
                const restockIdInput = document.createElement('input');
                restockIdInput.type = 'hidden';
                restockIdInput.name = 'restocking_id';
                restockIdInput.value = restockId;
                
                const statusInput = document.createElement('input');
                statusInput.type = 'hidden';
                statusInput.name = 'status_id';
                statusInput.value = newStatus;
                
                const updateInput = document.createElement('input');
                updateInput.type = 'hidden';
                updateInput.name = 'update_status';
                updateInput.value = '1';
                
                form.appendChild(restockIdInput);
                form.appendChild(statusInput);
                form.appendChild(updateInput);
                
                document.body.appendChild(form);
                form.submit();
            }
        }
        
         // Event delegation for status update buttons
         document.addEventListener('DOMContentLoaded', function() {
             document.addEventListener('click', function(e) {
                 if (e.target.closest('.status-update-btn')) {
                     e.preventDefault();
                     const button = e.target.closest('.status-update-btn');
                     const restockId = button.getAttribute('data-restock-id');
                     const newStatus = button.getAttribute('data-status');
                     updateStatus(restockId, newStatus);
                 }
             });
             
             // Auto-set expiration date when restock date changes
             const restockDateInput = document.querySelector('input[name="restock_date"]');
             const expirationDateInput = document.querySelector('input[name="expiration_date"]');
             
             if (restockDateInput && expirationDateInput) {
                 restockDateInput.addEventListener('change', function() {
                     const restockDate = new Date(this.value);
                     if (restockDate) {
                         // Set expiration date to 30 days after restock date by default
                         const expirationDate = new Date(restockDate);
                         expirationDate.setDate(expirationDate.getDate() + 30);
                         expirationDateInput.value = expirationDate.toISOString().split('T')[0];
                     }
                 });
             }
         });
    </script>
</body>
</html>
