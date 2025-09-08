
<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle restocking form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['restock'])) {
    try {
        // Validate required fields
        $required_fields = ['product_id', 'supplier_id', 'quantity_added', 'cost_per_unit', 'restock_date'];
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
        $expected_delivery = $_POST['expected_delivery'] ?: null;
        $notes = $_POST['notes'] ?: null;

        // Validate positive values
        if ($quantity_added <= 0) {
            throw new Exception("Quantity added must be greater than 0.");
        }
        if ($cost_per_unit < 0) {
            throw new Exception("Cost per unit cannot be negative.");
        }

        // Insert restocking record (matches restocking table schema)
        $stmt = $pdo->prepare("INSERT INTO restocking (product_id, supplier_id, quantity_added, cost_per_unit, total_cost, restock_date, expected_delivery, status_id, notes, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, 1, ?, ?)");
        $stmt->execute([$product_id, $supplier_id, $quantity_added, $cost_per_unit, $total_cost, $restock_date, $expected_delivery, $notes, $_SESSION['user_id']]);

        // Update product_stock current_stock and last_restock_date
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = COALESCE(current_stock,0) + ?, last_restock_date = ? WHERE product_id = ?");
        $stmt->execute([$quantity_added, $restock_date, $product_id]);

        // Fetch new current stock
        $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $current_stock = (int)$stmt->fetchColumn();

        // Record stock movement (use stock_movements schema)
        $restock_id = $pdo->lastInsertId();
        $stmt = $pdo->prepare("INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) VALUES (?, 1, ?, ?, ?, 'Restocking', ?, 'restock', ?)");
        $stmt->execute([$product_id, $quantity_added, $current_stock - $quantity_added, $current_stock, $restock_id, $_SESSION['user_id']]);

        logHistory($pdo, 'Restocking', "Product ID: $product_id, Quantity: $quantity_added, Cost: ₱$total_cost", $_SESSION['username']);
        $_SESSION['success'] = "Restocking recorded successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error recording restocking: " . $e->getMessage();
    }
    header("Location: inventory.php");
    exit;
}

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

        logHistory($pdo, 'Stock Adjustment', "Product ID: $product_id, Type: $adjustment_type, Quantity: $quantity, Reason: $reason", $_SESSION['username']);
        $_SESSION['success'] = "Stock adjustment recorded successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adjusting stock: " . $e->getMessage();
    }
    header("Location: inventory.php");
    exit;
}

// Fetch products with inventory data
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

// Fetch suppliers for restocking
$stmt = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_archive = 0");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate inventory statistics
$total_products = count($products);
$low_stock_products = count(array_filter($products, fn($p) => (int)$p['stock'] <= (int)($p['reorder_point'] ?? 10)));
$out_of_stock_products = count(array_filter($products, fn($p) => (int)$p['stock'] === 0));

// Calculate total inventory value efficiently with single query
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
    // Fallback to 0 if query fails
    $total_inventory_value = 0;
}

// Fetch recent stock movements
$stmt = $pdo->query("
    SELECT sm.*, p.product_name as product_name, COALESCE(ps.current_stock,0) as current_stock
    FROM stock_movements sm
    JOIN products p ON sm.product_id = p.product_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    ORDER BY sm.created_at DESC
    LIMIT 10
");
$recent_movements = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory Management</title>
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
          <i class="fa fa-warehouse me-3" style="color: #7F1734;"></i>Inventory Management
        </h1>
      </div>
      <div class="d-flex gap-2">
        <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#restockModal">
          <i class="fa fa-plus-circle me-1"></i> Record Restocking
        </button>
        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#adjustStockModal">
          <i class="fa fa-edit me-1"></i> Stock Adjustment
        </button>
      </div>
    </div>

    <!-- Inventory Statistics -->
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
            <div class="stat-icon bg-warning">
              <i class="fa fa-exclamation-triangle"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?= $low_stock_products ?></h4>
              <small class="text-muted text-uppercase">Low Stock Items</small>
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
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success">
              <i class="fa fa-money-bill"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0">₱<?= number_format($total_inventory_value, 2) ?></h4>
              <small class="text-muted text-uppercase">Total Value</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Inventory Table -->
    <div class="table-card">
      <div class="card-header bg-transparent border-0 p-4">
        <h5 class="fw-bold mb-0">Current Inventory Levels</h5>
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
            <?php foreach ($products as $product): ?>
              <tr>
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
                <td><?= $product['reorder_point'] ?></td>
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
                    <button class="btn btn-sm btn-success" onclick="openRestockModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                      Restock
                    </button>
                    <button class="btn btn-sm btn-info" onclick="openAdjustModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>', <?= (int)$product['stock'] ?>)">
                      Adjust
                    </button>
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
    <div class="modal-dialog">
      <form action="inventory.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Record Restocking</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="restock" value="1">
            <div class="mb-3">
              <label class="form-label">Product</label>
              <select name="product_id" class="form-select" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= $product['id'] ?>"><?= htmlspecialchars($product['name']) ?> (Current: <?= $product['stock'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Supplier</label>
              <select name="supplier_id" class="form-select" required>
                <option value="">Select Supplier</option>
                <?php foreach ($suppliers as $supplier): ?>
                  <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Quantity Added</label>
                  <input type="number" name="quantity_added" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Cost per Unit</label>
                  <input type="number" step="0.01" name="cost_per_unit" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Restock Date</label>
                  <input type="date" name="restock_date" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Expected Delivery</label>
                  <input type="date" name="expected_delivery" class="form-control">
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Record Restocking</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Stock Adjustment Modal -->
  <div class="modal fade" id="adjustStockModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="inventory.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Stock Adjustment</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="adjust_stock" value="1">
            <div class="mb-3">
              <label class="form-label">Product</label>
              <select name="product_id" id="adjustProductId" class="form-select" required>
                <option value="">Select Product</option>
                <?php foreach ($products as $product): ?>
                  <option value="<?= $product['id'] ?>" data-stock="<?= $product['stock'] ?>"><?= htmlspecialchars($product['name']) ?> (Current: <?= $product['stock'] ?>)</option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Adjustment Type</label>
                  <select name="adjustment_type" class="form-select" required>
                    <option value="add">Add Stock</option>
                    <option value="subtract">Subtract Stock</option>
                    <option value="set">Set Stock Level</option>
                  </select>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Quantity</label>
                  <input type="number" name="quantity" class="form-control" required>
                </div>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Reason</label>
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
            <div class="mb-3">
              <label class="form-label">Notes</label>
              <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-info">Adjust Stock</button>
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

    function openAdjustModal(productId, productName, currentStock) {
      const modal = new bootstrap.Modal(document.getElementById('adjustStockModal'));
      const select = document.querySelector('#adjustStockModal select[name="product_id"]');
      if (select) select.value = productId;
      modal.show();
    }
  </script>
</body>
</html>
