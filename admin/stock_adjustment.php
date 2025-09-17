<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle stock adjustment form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['adjust_stock'])) {
    try {
        // Validate required fields
        $required_fields = ['product_id', 'adjustment_type', 'quantity', 'reason'];
        foreach ($required_fields as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Field '$field' is required.");
            }
        }

        $product_id = (int)$_POST['product_id'];
        $adjustment_type = $_POST['adjustment_type'];
        $quantity = (int)$_POST['quantity'];
        $reason = $_POST['reason'];
        $notes = $_POST['notes'] ?: null;

        // Validate quantity
        if ($quantity <= 0) {
            throw new Exception("Quantity must be greater than 0.");
        }

        $pdo->beginTransaction();

        // Get current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();
        $previous_stock = $current_stock;

        // Calculate new stock
        switch ($adjustment_type) {
            case 'add':
                $new_stock = $current_stock + $quantity;
                break;
            case 'subtract':
                $new_stock = $current_stock - $quantity;
                if ($new_stock < 0) {
                    throw new Exception("Cannot subtract $quantity from current stock of $current_stock. Result would be negative.");
                }
                break;
            case 'set':
                $new_stock = $quantity;
                break;
            default:
                throw new Exception("Invalid adjustment type: $adjustment_type");
        }

        // Update product_stock
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = ? WHERE product_id = ?");
        $stmt->execute([$new_stock, $product_id]);

        // Map adjustment type to proper ID
        $adjustment_type_map = [
            'add' => 1,      // Increase
            'subtract' => 2, // Decrease  
            'set' => 3       // Correction
        ];
        $adjustment_type_id = $adjustment_type_map[$adjustment_type] ?? 3;

        // Record stock adjustment
        $stmt = $pdo->prepare("INSERT INTO stock_adjustment (product_id, adjustment_type_id, quantity, previous_stock, new_stock, reason, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$product_id, $adjustment_type_id, $quantity, $previous_stock, $new_stock, $reason, $notes, $_SESSION['user_id']]);

        // Record stock movement
        $adjustment_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 4, ?, ?, ?, ?, ?, 'adjustment', ?)");
        $stmt->execute([$product_id, $quantity, $previous_stock, $new_stock, $reason, $adjustment_id, $_SESSION['user_id']]);

        $pdo->commit();

        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product_name = $stmt->fetchColumn();

        logHistory($pdo, 'Stock Adjustment', "Product: $product_name, Type: $adjustment_type, Quantity: $quantity, Reason: $reason", $_SESSION['username']);
        $_SESSION['success'] = "Stock adjustment recorded successfully!";
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error adjusting stock: " . $e->getMessage();
    }
    header("Location: stock_adjustment.php");
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
    ORDER BY p.product_name
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch recent stock adjustments
$stmt = $pdo->query("
    SELECT 
        sa.*,
        p.product_name,
        u.name as uom_name,
        at.name as adjustment_type_name
    FROM stock_adjustment sa
    JOIN products p ON sa.product_id = p.product_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN adjustment_types at ON sa.adjustment_type_id = at.adjustment_type_id
    ORDER BY sa.created_at DESC
    LIMIT 20
");
$recent_adjustments = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate adjustment statistics
$total_adjustments = $pdo->query("SELECT COUNT(*) FROM stock_adjustment")->fetchColumn();
$adjustments_today = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE DATE(created_at) = CURDATE()")->fetchColumn();
$adjustments_this_month = $pdo->query("SELECT COUNT(*) FROM stock_adjustment WHERE MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())")->fetchColumn();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Adjustment - Admin Dashboard</title>
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
        
        .adjustment-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            border-radius: 12px;
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
                    <i class="fa fa-edit me-3" style="color: #7F1734;"></i>Stock Adjustment
                </h1>
                <p class="text-muted">Correct discrepancies between physical count and system records</p>
            </div>
            <button class="btn text-white fw-bold px-4" style="background-color: #7F1734;" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
                <i class="fa fa-edit me-2"></i>Adjust Stock
            </button>
        </div>

        <!-- Statistics -->
        <div class="row g-4 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-info">
                            <i class="fa fa-calculator"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $total_adjustments ?></h4>
                            <small class="text-muted text-uppercase">Total Adjustments</small>
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
                            <h4 class="fw-bold mb-0"><?= $adjustments_today ?></h4>
                            <small class="text-muted text-uppercase">Today</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-warning">
                            <i class="fa fa-calendar-alt"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= $adjustments_this_month ?></h4>
                            <small class="text-muted text-uppercase">This Month</small>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-3 col-md-6">
                <div class="stat-card">
                    <div class="d-flex align-items-center">
                        <div class="stat-icon bg-primary">
                            <i class="fa fa-boxes"></i>
                        </div>
                        <div class="ms-3">
                            <h4 class="fw-bold mb-0"><?= count($products) ?></h4>
                            <small class="text-muted text-uppercase">Products</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Products List -->
        <div class="table-card mb-4">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Products Available for Adjustment</h5>
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
                            <th class="fw-semibold">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td>
                                    <div class="fw-semibold"><?= htmlspecialchars($product['name']) ?></div>
                                    <small class="text-muted"><?= htmlspecialchars($product['supplier_name']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($product['category_name']) ?></td>
                                <td>
                                    <span class="fw-semibold"><?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name']) ?></span>
                                </td>
                                <td><?= $product['reorder_point'] ?></td>
                                <td>
                                    <?php if ((int)$product['stock'] === 0): ?>
                                        <span class="badge bg-danger adjustment-badge">Out of Stock</span>
                                    <?php elseif ((int)$product['stock'] <= (int)$product['reorder_point']): ?>
                                        <span class="badge bg-warning adjustment-badge">Low Stock</span>
                                    <?php else: ?>
                                        <span class="badge bg-success adjustment-badge">In Stock</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-info" onclick="openAdjustModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= (int)$product['stock'] ?>)">
                                        <i class="fa fa-edit me-1"></i>Adjust
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Adjustments -->
        <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
                <h5 class="fw-bold mb-0">Recent Stock Adjustments</h5>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="fw-semibold">Date</th>
                            <th class="fw-semibold">Product</th>
                            <th class="fw-semibold">Type</th>
                            <th class="fw-semibold">Quantity</th>
                            <th class="fw-semibold">Previous Stock</th>
                            <th class="fw-semibold">New Stock</th>
                            <th class="fw-semibold">Reason</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_adjustments as $adjustment): ?>
                            <tr>
                                <td><?= date('M d, Y H:i', strtotime($adjustment['created_at'])) ?></td>
                                <td><?= htmlspecialchars($adjustment['product_name']) ?></td>
                                <td>
                                    <?php
                                    $type = $adjustment['adjustment_type_id'];
                                    if ($type == 1) {
                                        echo '<span class="badge bg-success">Increase</span>';
                                    } elseif ($type == 2) {
                                        echo '<span class="badge bg-danger">Decrease</span>';
                                    } else {
                                        echo '<span class="badge bg-info">Correction</span>';
                                    }
                                    ?>
                                </td>
                                <td><?= $adjustment['quantity'] ?> <?= htmlspecialchars($adjustment['uom_name']) ?></td>
                                <td><?= $adjustment['previous_stock'] ?></td>
                                <td><?= $adjustment['new_stock'] ?></td>
                                <td><?= htmlspecialchars($adjustment['reason']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>

    <!-- Stock Adjustment Modal -->
    <div class="modal fade" id="adjustStockModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="stock_adjustment.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title fw-bold">
                            <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Stock Adjustment
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="adjust_stock" value="1">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Product</label>
                                <select name="product_id" id="adjustProductId" class="form-select" required>
                                    <option value="">Select Product</option>
                                    <?php foreach ($products as $product): ?>
                                        <option value="<?= $product['id'] ?>" data-stock="<?= $product['stock'] ?>"><?= htmlspecialchars($product['name']) ?> (Current: <?= $product['stock'] ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Adjustment Type</label>
                                <select name="adjustment_type" id="adjustmentType" class="form-select" required>
                                    <option value="add">Add Stock</option>
                                    <option value="subtract">Subtract Stock</option>
                                    <option value="set">Set Stock Level</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Quantity</label>
                                <input type="number" name="quantity" id="quantityInput" class="form-control" required min="1">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label fw-semibold">Reason</label>
                                <select name="reason" class="form-select" required>
                                    <option value="">Select Reason</option>
                                    <option value="Damaged Items">Damaged Items</option>
                                    <option value="Counting Error">Counting Error</option>
                                    <option value="Theft/Loss">Theft/Loss</option>
                                    <option value="Quality Control">Quality Control</option>
                                    <option value="Manual Correction">Manual Correction</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label fw-semibold">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" placeholder="Additional details about this adjustment..."></textarea>
                            </div>
                            <div class="col-12">
                                <div class="alert alert-info">
                                    <i class="fa fa-info-circle me-2"></i>
                                    <strong>Current Stock:</strong> <span id="currentStockDisplay">-</span>
                                    <br>
                                    <strong>New Stock Will Be:</strong> <span id="newStockDisplay">-</span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold" style="background-color: #7F1734;">
                            <i class="fa fa-save me-2"></i>Adjust Stock
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        function openAdjustModal(productId, productName, currentStock) {
            const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
            const select = document.querySelector('#adjustStockModal select[name="product_id"]');
            if (select) select.value = productId;
            updateStockDisplay();
            modal.show();
        }

        function updateStockDisplay() {
            const productSelect = document.getElementById('adjustProductId');
            const adjustmentType = document.getElementById('adjustmentType');
            const quantityInput = document.getElementById('quantityInput');
            const currentStockDisplay = document.getElementById('currentStockDisplay');
            const newStockDisplay = document.getElementById('newStockDisplay');

            const selectedOption = productSelect.options[productSelect.selectedIndex];
            const currentStock = parseInt(selectedOption.getAttribute('data-stock')) || 0;
            const quantity = parseInt(quantityInput.value) || 0;
            const type = adjustmentType.value;

            currentStockDisplay.textContent = currentStock;

            let newStock = currentStock;
            if (type === 'add') {
                newStock = currentStock + quantity;
            } else if (type === 'subtract') {
                newStock = currentStock - quantity;
            } else if (type === 'set') {
                newStock = quantity;
            }

            newStockDisplay.textContent = newStock;
        }

        // Add event listeners
        document.getElementById('adjustProductId').addEventListener('change', updateStockDisplay);
        document.getElementById('adjustmentType').addEventListener('change', updateStockDisplay);
        document.getElementById('quantityInput').addEventListener('input', updateStockDisplay);
    </script>
</body>
</html>
