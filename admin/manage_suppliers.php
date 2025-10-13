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

// Handle form submission for adding new supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address_line, notes, is_archive) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $phone, $email, $address, $notes]);

        logHistory($pdo, 'Added Supplier', 'Supplier Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier added successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding supplier: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier editing (modal submit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    try {
        $supplierId = (int)$_POST['supplier_id'];
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info']; // for log only
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, address_line = ?, notes = ? WHERE supplier_id = ?");
        $stmt->execute([$name, $phone, $email, $address, $notes, $supplierId]);

        logHistory($pdo, 'Edited Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating supplier: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier archiving
if (isset($_GET['archive']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE suppliers SET is_archive = 1 WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);

    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    logHistory($pdo, 'Archived Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $supplier['name'], $_SESSION['username']);

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier unarchiving
if (isset($_GET['unarchive']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE suppliers SET is_archive = 0 WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);

    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    logHistory($pdo, 'Unarchived Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $supplier['name'], $_SESSION['username']);

    header("Location: manage_suppliers.php");
    exit;
}


// Handle supplier deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);

    // Check if supplier has associated products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $productCount = $stmt->fetchColumn();

    if ($productCount > 0) {
        $_SESSION['error'] = "Cannot delete supplier. They have " . $productCount . " associated products.";
    } else {
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);

        logHistory($pdo, 'Deleted Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . ($supplier['name'] ?? ''), $_SESSION['username']);
        $_SESSION['success'] = "Supplier deleted successfully!";
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle assign product to supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'assign_product') {
    try {
        $supplierId = (int)$_POST['supplier_id'];
        $productId = (int)$_POST['product_id'];

        // Check if relationship already exists
        $stmt = $pdo->prepare("SELECT id FROM supplier_products WHERE supplier_id = ? AND product_id = ?");
        $stmt->execute([$supplierId, $productId]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "This product is already assigned to this supplier.";
        } else {
            // Add the relationship
            $stmt = $pdo->prepare("INSERT INTO supplier_products (supplier_id, product_id, is_primary, is_active, created_by) VALUES (?, ?, 0, 1, ?)");
            $stmt->execute([$supplierId, $productId, $_SESSION['user_id']]);

            // Get supplier and product names for logging
            $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$supplierId]);
            $supplierName = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
            $stmt->execute([$productId]);
            $productName = $stmt->fetchColumn();

            logHistory($pdo, 'Assigned Product to Supplier', "Supplier: $supplierName, Product: $productName" . ($isPrimary ? ' (Primary)' : ''), $_SESSION['username']);
            $_SESSION['success'] = "Product assigned to supplier successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error assigning product: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}


// Fetch active suppliers with their products
$activeSuppliers = $pdo->query("
    SELECT 
        s.supplier_id AS id, 
        s.name, 
        s.phone, 
        s.email, 
        CONCAT_WS(', ', s.address_line, s.city, s.country) AS address, 
        s.notes,
        COUNT(DISTINCT sp.product_id) AS product_count
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.supplier_id = sp.supplier_id AND sp.is_active = 1
    LEFT JOIN products p ON sp.product_id = p.product_id AND p.is_archive = 0
    WHERE s.is_archive = 0
    GROUP BY s.supplier_id, s.name, s.phone, s.email, s.address_line, s.city, s.country, s.notes
    ORDER BY s.name
")->fetchAll();

// Fetch detailed products for each supplier
$supplierProducts = [];
foreach ($activeSuppliers as $supplier) {
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.product_description,
            c.category_name,
            b.name AS brand_name,
            uom.name AS uom_name,
            COALESCE(ps.current_stock, 0) AS stock,
            COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) AS price,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
        FROM supplier_products sp
        INNER JOIN products p ON sp.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE sp.supplier_id = ? AND sp.is_active = 1 AND p.is_archive = 0
        ORDER BY p.product_name
    ");
    $stmt->execute([$supplier['id']]);
    $supplierProducts[$supplier['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch archived suppliers with their products
$archivedSuppliers = $pdo->query("
    SELECT 
        s.supplier_id AS id, 
        s.name, 
        s.phone, 
        s.email, 
        CONCAT_WS(', ', s.address_line, s.city, s.country) AS address, 
        s.notes,
        COUNT(DISTINCT sp.product_id) AS product_count
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.supplier_id = sp.supplier_id AND sp.is_active = 1
    LEFT JOIN products p ON sp.product_id = p.product_id AND p.is_archive = 0
    WHERE s.is_archive = 1
    GROUP BY s.supplier_id, s.name, s.phone, s.email, s.address_line, s.city, s.country, s.notes
    ORDER BY s.name
")->fetchAll();

// Fetch detailed products for archived suppliers
$archivedSupplierProducts = [];
foreach ($archivedSuppliers as $supplier) {
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.product_description,
            c.category_name,
            b.name AS brand_name,
            uom.name AS uom_name,
            COALESCE(ps.current_stock, 0) AS stock,
            COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) AS price,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
        FROM supplier_products sp
        INNER JOIN products p ON sp.product_id = p.product_id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE sp.supplier_id = ? AND sp.is_active = 1 AND p.is_archive = 0
        ORDER BY p.product_name
    ");
    $stmt->execute([$supplier['id']]);
    $archivedSupplierProducts[$supplier['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Suppliers - Admin Dashboard</title>
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
    
    .supplier-row {
      transition: none;
    }

    .modal-content { background-color: var(--card-bg) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important; }
    .modal-header, .modal-footer { border-color: var(--border-color) !important; }
    
    .product-item {
      background: var(--bg-primary);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 8px;
      transition: all 0.2s ease;
    }
    
    .product-item:hover {
      background: var(--card-bg);
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .product-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border-color);
    }
    
    .product-details {
      flex: 1;
    }
    
    .product-name {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 4px;
    }
    
    .product-meta {
      font-size: 0.85rem;
      color: var(--text-secondary);
    }
    
    .product-price {
      font-weight: 600;
      color: #7F1734;
    }
    
    .product-stock {
      font-size: 0.8rem;
    }
    
    .stock-available { color: #28a745; }
    .stock-low { color: #ffc107; }
    .stock-out { color: #dc3545; }
    
    .products-container {
      max-height: 300px;
      overflow-y: auto;
    }
    
    .products-container::-webkit-scrollbar {
      width: 6px;
    }
    
    .products-container::-webkit-scrollbar-track {
      background: var(--bg-primary);
      border-radius: 3px;
    }
    
    .products-container::-webkit-scrollbar-thumb {
      background: var(--border-color);
      border-radius: 3px;
    }
    
    .products-container::-webkit-scrollbar-thumb:hover {
      background: #7F1734;
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
        <h2>
          <i class="fa fa-truck me-2"></i>Supplier Management
        </h2>
        <p class="mb-0 opacity-75">Manage your suppliers and their product assignments</p>
      </div>

      <!-- Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-truck"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= count($activeSuppliers) ?></h3>
              <p class="card-label">Active Suppliers</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-archive"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= count($archivedSuppliers) ?></h3>
              <p class="card-label">Archived Suppliers</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= array_sum(array_column($activeSuppliers, 'product_count')) ?></h3>
              <p class="card-label">Total Product Assignments</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-plus"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= count($activeSuppliers) + count($archivedSuppliers) ?></h3>
              <p class="card-label">Total Suppliers</p>
            </div>
          </div>
        </div>
    </div>

    <!-- Alerts -->
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

    <!-- Add Supplier Button -->
    <div class="mb-4 d-flex gap-2">
      <button type="button" class="btn fw-bold px-4" style="background: #b3d9ff; color: #003d82; border-radius: 8px;" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="fa fa-plus me-2"></i>Add New Supplier
      </button>
    </div>

    <!-- Active Suppliers Table -->
    <div class="mb-5">
      <div class="table-card">
        <div class="card-header">
          <h4 class="fw-bold text-dark mb-3">
            <i class="fa fa-truck me-2" style="color: var(--bs-primary);"></i>Active Suppliers
          </h4>
        </div>
        <div class="card-body">
        <?php if (empty($activeSuppliers)): ?>
            <div class="text-center py-5">
              <i class="fa fa-truck display-4 text-muted mb-3"></i>
              <h5 class="text-muted">No active suppliers found</h5>
              <p class="text-muted">Add your first supplier using the form above.</p>
          </div>
        <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Supplier Name</th>
                    <th class="fw-semibold">Contact Info</th>
                    <th class="fw-semibold">Products</th>
                    <th class="fw-semibold">Notes</th>
                    <th class="fw-semibold text-center">Actions</th>
                  </tr>
                </thead>
            <tbody>
          <?php foreach ($activeSuppliers as $supplier): ?>
                <tr class="supplier-row" style="cursor: pointer;" 
                    onclick="toggleProductsView(<?= $supplier['id'] ?>)"
                        data-id="<?= $supplier['id'] ?>"
                        data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                        data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>">
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($supplier['name']) ?></div>
                  </td>
                  <td>
                    <div class="small">
                  <?php if ($supplier['phone']): ?>
                        <div><i class="fa fa-phone me-1 text-muted"></i><?= htmlspecialchars($supplier['phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['email']): ?>
                        <div><i class="fa fa-envelope me-1 text-muted"></i><?= htmlspecialchars($supplier['email']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['address']): ?>
                        <div><i class="fa fa-map-marker me-1 text-muted"></i><?= htmlspecialchars($supplier['address']) ?></div>
                  <?php endif; ?>
                </div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <span class="badge bg-primary me-2"><?= $supplier['product_count'] ?></span>
                      <button class="btn btn-sm" 
                              style="background: #b3d9ff; color: #003d82; border-radius: 8px;"
                              onclick="event.stopPropagation(); openAssignProductsModal(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')"
                              title="Assign Products">
                        <i class="fa fa-plus"></i>
                      </button>
                      <?php 
                      $products = $supplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <button class="btn btn-sm" 
                                style="background: #d1ecf1; color: #0c5460; border-radius: 8px;"
                                onclick="event.stopPropagation(); toggleProductsView(<?= $supplier['id'] ?>)"
                                title="View All Products">
                          <i class="fa fa-eye"></i>
                        </button>
                      <?php endif; ?>
                  </div>
                    <?php if (!empty($products)): ?>
                      <div class="mt-2">
                      <small class="text-muted">
                          <?php 
                          $productNames = array_slice(array_map(function($p) { return $p['product_name']; }, $products), 0, 3);
                          echo htmlspecialchars(implode(', ', $productNames));
                          if (count($products) > 3) echo '...';
                          ?>
                      </small>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($supplier['notes'])): ?>
                      <small class="text-muted"><?= htmlspecialchars(substr($supplier['notes'], 0, 50)) ?><?= strlen($supplier['notes']) > 50 ? '...' : '' ?></small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm" 
                              style="background: #cce5ff; color: #004085; border-radius: 8px;"
                              onclick="event.stopPropagation(); openEditSupplierModal(this)"
                              data-id="<?= $supplier['id'] ?>"
                              data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                              data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                              data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                              data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                              data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>"
                              title="Edit Supplier">
                        <i class="fa fa-edit"></i>
                      </button>
                      <a class="btn btn-sm" 
                         style="background: #fff3cd; color: #856404; border-radius: 8px;"
                         href="manage_suppliers.php?archive=1&id=<?= $supplier['id'] ?>" 
                         onclick="return confirm('Archive this supplier?')"
                         title="Archive Supplier">
                        <i class="fa fa-archive"></i>
                      </a>
                    </div>
                  </td>
                </tr>
                <!-- Expandable Products Row -->
                <tr id="products-row-<?= $supplier['id'] ?>" class="products-detail-row" style="display: none;">
                  <td colspan="5">
                    <div class="p-3 bg-light rounded">
                      <h6 class="fw-semibold mb-3">
                        <i class="fa fa-box me-2 text-primary"></i>Products Supplied by <?= htmlspecialchars($supplier['name']) ?>
                      </h6>
                      <?php 
                      $products = $supplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <div class="row g-3">
                          <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4">
                              <div class="product-item d-flex align-items-center gap-3 p-3 bg-white rounded border">
                                <img src="<?= !empty($product['image1']) ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                     class="product-image"
                                     onerror="this.src='uploads/default.png'">
                                <div class="product-details">
                                  <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                  <div class="product-meta">
                                    <span class="product-price">₱<?= number_format($product['price'], 2) ?></span>
                                    <span class="mx-2">•</span>
                                    <span class="product-stock <?= $product['stock'] > 10 ? 'stock-available' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out') ?>">
                                      Stock: <?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                    </span>
                                  </div>
                                  <div class="product-meta">
                                    <small><?= htmlspecialchars($product['category_name'] ?? '') ?></small>
                                    <?php if (!empty($product['brand_name'])): ?>
                                      <span class="mx-1">•</span>
                                      <small><?= htmlspecialchars($product['brand_name']) ?></small>
                                    <?php endif; ?>
                                  </div>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                        <div class="text-center py-4">
                          <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                          <h6 class="text-muted">No products assigned</h6>
                          <p class="text-muted">This supplier doesn't have any products assigned yet.</p>
                    </div>
                  <?php endif; ?>
                </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
              </table>
                  </div>
                <?php endif; ?>
              </div>
      </div>
    </div>

    <!-- Archived Suppliers Table -->
    <?php if (!empty($archivedSuppliers)): ?>
      <div class="mb-5">
        <div class="table-card">
          <div class="card-header">
            <h4 class="fw-bold text-dark mb-0">
              <i class="fa fa-archive me-2" style="color: var(--bs-danger);"></i>Archived Suppliers
            </h4>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Supplier Name</th>
                    <th class="fw-semibold">Contact Info</th>
                    <th class="fw-semibold">Products</th>
                    <th class="fw-semibold">Notes</th>
                    <th class="fw-semibold text-center">Actions</th>
                  </tr>
                </thead>
            <tbody>
          <?php foreach ($archivedSuppliers as $supplier): ?>
                <tr class="supplier-row" style="cursor: pointer; opacity: 0.7;" 
                    onclick="toggleProductsView(<?= $supplier['id'] ?>)"
                        data-id="<?= $supplier['id'] ?>"
                        data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                        data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>">
                  <td>
                    <div class="fw-semibold text-danger"><?= htmlspecialchars($supplier['name']) ?></div>
                    <small class="badge bg-danger">Archived</small>
                  </td>
                  <td>
                    <div class="small">
                  <?php if ($supplier['phone']): ?>
                        <div><i class="fa fa-phone me-1 text-muted"></i><?= htmlspecialchars($supplier['phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['email']): ?>
                        <div><i class="fa fa-envelope me-1 text-muted"></i><?= htmlspecialchars($supplier['email']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['address']): ?>
                        <div><i class="fa fa-map-marker me-1 text-muted"></i><?= htmlspecialchars($supplier['address']) ?></div>
                  <?php endif; ?>
                </div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <span class="badge bg-secondary me-2"><?= $supplier['product_count'] ?></span>
                      <?php 
                      $products = $archivedSupplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <button class="btn btn-sm" 
                                style="background: #d1ecf1; color: #0c5460; border-radius: 8px;"
                                onclick="event.stopPropagation(); toggleProductsView(<?= $supplier['id'] ?>)"
                                title="View All Products">
                          <i class="fa fa-eye"></i>
                        </button>
                      <?php endif; ?>
                  </div>
                    <?php if (!empty($products)): ?>
                      <div class="mt-2">
                      <small class="text-muted">
                          <?php 
                          $productNames = array_slice(array_map(function($p) { return $p['product_name']; }, $products), 0, 3);
                          echo htmlspecialchars(implode(', ', $productNames));
                          if (count($products) > 3) echo '...';
                          ?>
                      </small>
                    </div>
                  <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($supplier['notes'])): ?>
                      <small class="text-muted"><?= htmlspecialchars(substr($supplier['notes'], 0, 50)) ?><?= strlen($supplier['notes']) > 50 ? '...' : '' ?></small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm" 
                              style="background: #cce5ff; color: #004085; border-radius: 8px;"
                              onclick="event.stopPropagation(); openEditSupplierModal(this)"
                              data-id="<?= $supplier['id'] ?>"
                              data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                              data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                              data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                              data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                              data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>"
                              title="Edit Supplier">
                        <i class="fa fa-edit"></i>
                      </button>
                      <a class="btn btn-sm" 
                         style="background: #d4edda; color: #155724; border-radius: 8px;"
                         href="manage_suppliers.php?unarchive=1&id=<?= $supplier['id'] ?>" 
                         onclick="return confirm('Unarchive this supplier?')"
                         title="Unarchive Supplier">
                        <i class="fa fa-undo"></i>
                      </a>
                      <a class="btn btn-sm" 
                         style="background: #f5c6cb; color: #721c24; border-radius: 8px;"
                         href="manage_suppliers.php?delete=1&id=<?= $supplier['id'] ?>" 
                         onclick="return confirm('Permanently delete this supplier?')"
                         title="Delete Supplier">
                        <i class="fa fa-trash"></i>
                      </a>
                    </div>
                  </td>
                </tr>
                <!-- Expandable Products Row for Archived Suppliers -->
                <tr id="products-row-<?= $supplier['id'] ?>" class="products-detail-row" style="display: none;">
                  <td colspan="5">
                    <div class="p-3 bg-light rounded">
                      <h6 class="fw-semibold mb-3">
                        <i class="fa fa-box me-2 text-primary"></i>Products Supplied by <?= htmlspecialchars($supplier['name']) ?>
                      </h6>
                      <?php 
                      $products = $archivedSupplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <div class="row g-3">
                          <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4">
                              <div class="product-item d-flex align-items-center gap-3 p-3 bg-white rounded border">
                                <img src="<?= !empty($product['image1']) ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                     class="product-image"
                                     onerror="this.src='uploads/default.png'">
                                <div class="product-details">
                                  <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                  <div class="product-meta">
                                    <span class="product-price">₱<?= number_format($product['price'], 2) ?></span>
                                    <span class="mx-2">•</span>
                                    <span class="product-stock <?= $product['stock'] > 10 ? 'stock-available' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out') ?>">
                                      Stock: <?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                    </span>
                  </div>
                                  <div class="product-meta">
                                    <small><?= htmlspecialchars($product['category_name'] ?? '') ?></small>
                                    <?php if (!empty($product['brand_name'])): ?>
                                      <span class="mx-1">•</span>
                                      <small><?= htmlspecialchars($product['brand_name']) ?></small>
                <?php endif; ?>
                                  </div>
                                </div>
              </div>
            </div>
          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center py-4">
                          <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                          <h6 class="text-muted">No products assigned</h6>
                          <p class="text-muted">This supplier doesn't have any products assigned yet.</p>
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
      </div>
    <?php endif; ?>
    </div>
  </main>

  <!-- Add Supplier Modal -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-plus me-2" style="color: #7F1734;"></i>Add New Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Person</label>
                <input type="text" name="contact_info" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="tel" name="phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn fw-bold" style="background: #b3d9ff; color: #003d82; border-radius: 8px;">
              <i class="fa fa-plus me-2"></i>Add Supplier
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Supplier Modal (same design as Add) -->
  <div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Edit Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Person</label>
                <input type="text" name="contact_info" id="edit_contact_info" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn fw-bold" style="background: #b3d9ff; color: #003d82; border-radius: 8px;">
              <i class="fa fa-save me-2"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Assign Products Modal -->
  <div class="modal fade" id="assignProductsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-plus me-2" style="color: #7F1734;"></i>Assign Products to Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="assign_product">
            <input type="hidden" name="supplier_id" id="assign_supplier_id">
            
            <div class="mb-3">
              <label class="form-label fw-semibold">Supplier</label>
              <input type="text" id="assign_supplier_name" class="form-control" readonly>
            </div>
            
            <div class="mb-3">
              <label class="form-label fw-semibold">Select Product</label>
              <select name="product_id" id="assign_product_id" class="form-select" required>
                <option value="">Choose a product...</option>
                <?php
                // Fetch all active products that are not already assigned to this supplier
                $allProducts = $pdo->query("
                  SELECT p.product_id, p.product_name, c.category_name
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.category_id
                  WHERE p.is_archive = 0
                  ORDER BY p.product_name
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($allProducts as $product): ?>
                  <option value="<?= $product['product_id'] ?>" data-category="<?= htmlspecialchars($product['category_name'] ?? '') ?>">
                    <?= htmlspecialchars($product['product_name']) ?> 
                    <?php if (!empty($product['category_name'])): ?>
                      (<?= htmlspecialchars($product['category_name']) ?>)
                    <?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn fw-bold" style="background: #b3d9ff; color: #003d82; border-radius: 8px;">
              <i class="fa fa-plus me-2"></i>Assign Product
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    function openEditSupplierModal(btn) {
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name') || '';
      const phone = btn.getAttribute('data-phone') || '';
      const email = btn.getAttribute('data-email') || '';
      const address = btn.getAttribute('data-address') || '';
      const notes = btn.getAttribute('data-notes') || '';
      // Optional: contact_info not in schema; keep for UI consistency
      const contact = '';

      document.getElementById('edit_supplier_id').value = id;
      document.getElementById('edit_name').value = name;
      document.getElementById('edit_phone').value = phone;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_address').value = address;
      document.getElementById('edit_notes').value = notes;
      const ci = document.getElementById('edit_contact_info');
      if (ci) ci.value = contact;

      const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
      modal.show();
    }

    function openAssignProductsModal(supplierId, supplierName) {
      document.getElementById('assign_supplier_id').value = supplierId;
      document.getElementById('assign_supplier_name').value = supplierName;
      
      // Reset form
      document.getElementById('assign_product_id').value = '';
      
      const modal = new bootstrap.Modal(document.getElementById('assignProductsModal'));
      modal.show();
    }

    function toggleProductsView(supplierId) {
      const productsRow = document.getElementById('products-row-' + supplierId);
      
      if (productsRow.style.display === 'none' || productsRow.style.display === '') {
        // Show products
        productsRow.style.display = 'table-row';
      } else {
        // Hide products
        productsRow.style.display = 'none';
      }
    }

  </script>
</body>
</html>