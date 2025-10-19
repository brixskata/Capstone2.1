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

// Handle unarchive action
if (isset($_GET['unarchive'])) {
    $product_id = (int)$_GET['unarchive'];
    
    try {
        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Unarchive product
            $stmt = $pdo->prepare("UPDATE products SET is_archive = 0 WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            $_SESSION['success'] = "Product '{$product['product_name']}' unarchived successfully";
            logHistory($pdo, 'Product Unarchived', "Unarchived product: {$product['product_name']}", $_SESSION['username']);
        } else {
            $_SESSION['error'] = "Product not found";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error unarchiving product: " . $e->getMessage();
    }
    
    header("Location: products.php?tab=archived");
    exit;
}

// Fetch categories from the database
$stmt = $pdo->query("SELECT category_id AS id, category_name AS name FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands from the database
$stmt = $pdo->query("SELECT id, name FROM brands WHERE is_archived = 0");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers from the database
$stmt = $pdo->query("SELECT supplier_id AS id, name FROM suppliers WHERE is_archive = 0");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch UOM from the database
$stmt = $pdo->query("SELECT uom_id AS id, name FROM uom");
$uoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get current tab (default to active)
$current_tab = isset($_GET['tab']) && $_GET['tab'] === 'archived' ? 'archived' : 'active';
$is_archived = $current_tab === 'archived' ? 1 : 0;

// Fetch products based on current tab
$stmt = $pdo->prepare("
    SELECT 
      p.product_id AS id,
      p.product_name AS name,
      p.product_description AS description,
      c.category_name as category_name,
      b.name as brand_name,
      s.name as supplier_name,
      u.name as uom_name,
      COALESCE(ps.current_stock,0) AS stock,
      COALESCE(pp.markup_price,0) AS markup_value,
      COALESCE(pp.markup_price,0) + COALESCE((
        SELECT pb.unit_cost 
        FROM product_batches pb 
        WHERE pb.product_id = p.product_id 
        AND pb.quantity_remaining > 0 
        AND pb.is_active = 1
        ORDER BY pb.received_date DESC 
        LIMIT 1
      ), pp.cost_price, 0) AS price,
      (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1,
      ps.expiration_date,
      COALESCE(pp.cost_price,0) AS cost_per_unit,
      p.is_archive
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
    WHERE p.is_archive = ?
    ORDER BY p.product_name
");
$stmt->execute([$is_archived]);
$products = $stmt->fetchAll();

// Get counts for analytics
$active_count = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archive = 0")->fetchColumn();
$archived_count = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archive = 1")->fetchColumn();

?>
<!DOCTYPE html>
<html lang="en">      
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
    
    /* SweetAlert2 Custom Styling */
    .swal-wide {
      width: 500px !important;
    }
    
    .swal2-popup {
      border-radius: 20px !important;
    }
    
    .swal2-title {
      font-weight: 700 !important;
      color: var(--bs-primary) !important;
    }
    
    .swal2-html-container {
      font-size: 14px !important;
    }
    
    .swal2-confirm {
      border-radius: 10px !important;
      font-weight: 600 !important;
    }
    
    .swal2-cancel {
      border-radius: 10px !important;
      font-weight: 600 !important;
    }
    
    .swal2-popup .alert {
      margin-bottom: 1rem !important;
      border-radius: 10px !important;
    }
    
    .swal2-popup .form-control {
      border-radius: 10px !important;
      border: 1px solid #e9ecef !important;
      padding: 0.75rem !important;
    }
    
    .swal2-popup .form-control:focus {
      border-color: var(--bs-primary) !important;
      box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25) !important;
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

    /* Filter Card */
    .filter-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid #e9ecef;
      padding: 1.5rem;
      margin-bottom: 2rem;
    }

    .filter-card .form-control,
    .filter-card .form-select {
      border-radius: 10px;
      border: 1px solid #e9ecef;
      padding: 0.75rem 1rem;
    }

    .filter-card .form-control:focus,
    .filter-card .form-select:focus {
      border-color: var(--bs-primary);
      box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
    }

    .filter-card .input-group-text {
      background: var(--bs-primary);
      color: white;
      border-color: var(--bs-primary);
      border-radius: 10px 0 0 10px;
    }

    /* Product Cards */
    .product-card {
      background: white;
      border-radius: 15px;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
      overflow: hidden;
      position: relative;
      height: 100%;
    }

    .product-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--bs-primary);
      transform: scaleX(0);
      transition: transform 0.3s ease;
    }

    .product-card:hover {
      transform: translateY(-5px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.15);
      border-color: var(--bs-primary);
    }

    .product-card:hover::before {
      transform: scaleX(1);
    }

    .carousel-container {
      position: relative;
      height: 200px;
      overflow: hidden;
      background-color: #f8f9fa;
    }

    .carousel-img {
      width: 100%;
      height: 100%;
      object-fit: contain;
      position: absolute;
      top: 0;
      left: 0;
      transition: opacity 0.5s ease;
    }
    
    .carousel-img.d-none {
      opacity: 0;
    }

    .carousel-btn {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(0, 0, 0, 0.5);
      color: white;
      border: none;
      padding: 8px 12px;
      cursor: pointer;
      z-index: 10;
      border-radius: 50%;
      transition: all 0.2s ease;
    }

    .carousel-btn:hover {
      background: rgba(0, 0, 0, 0.7);
      transform: translateY(-50%) scale(1.1);
    }

    .carousel-btn.prev {
      left: 10px;
    }

    .carousel-btn.next {
      right: 10px;
    }

    /* Badge Styles */
    .badge {
      font-size: 0.75rem;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
    }

    /* Button Group Styles */
    .btn-group .btn-check:checked + .btn {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
      color: white;
    }

    .btn-group .btn {
      border-radius: 8px;
      border-color: var(--bs-primary);
      color: var(--bs-primary);
      font-weight: 500;
    }

    /* Action Buttons - Pastel Colors */

    .btn-archive {
      background-color: #F9E79F;
      color: #F57F17;
      border: 1px solid rgba(249, 231, 159, 0.3);
      border-radius: 8px;
      padding: 0.5rem 1rem;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .btn-archive:hover {
      background-color: #FFD54F;
      color: #E65100;
      border-color: rgba(255, 213, 79, 0.4);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(249, 231, 159, 0.4);
    }

    .btn-unarchive {
      background-color: #A8D5BA;
      color: #2E7D32;
      border: 1px solid rgba(168, 213, 186, 0.3);
      border-radius: 8px;
      padding: 0.5rem 1rem;
      font-weight: 500;
      transition: all 0.2s ease;
    }

    .btn-unarchive:hover {
      background-color: #81C784;
      color: #1B5E20;
      border-color: rgba(129, 199, 132, 0.4);
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(168, 213, 186, 0.4);
    }

    /* Tab Button Styling */
    .btn-outline-primary {
      border-color: var(--bs-primary);
      color: var(--bs-primary);
      transition: all 0.2s ease;
    }

    .btn-outline-primary:hover {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
      color: white;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(127, 23, 52, 0.3);
    }

    .btn-primary {
      background-color: var(--bs-primary);
      border-color: var(--bs-primary);
      color: white;
      box-shadow: 0 2px 8px rgba(127, 23, 52, 0.2);
    }

    .btn-primary:hover {
      background-color: #6b1429;
      border-color: #6b1429;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(127, 23, 52, 0.4);
    }

    /* Empty State */
    .empty-state {
      text-align: center;
      padding: 3rem 1rem;
      color: var(--bs-secondary);
    }

    .empty-state i {
      font-size: 4rem;
      margin-bottom: 1rem;
      color: var(--bs-primary);
      opacity: 0.5;
    }

    /* Responsive Improvements */
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
      
      .product-card .p-3 {
        padding: 1rem !important;
      }
      
      .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.5rem !important;
      }
      
      .btn.flex-fill {
        width: 100%;
      }
    }

    @media (max-width: 576px) {
      .d-flex.gap-2 {
        flex-direction: column;
        gap: 0.25rem !important;
      }
      
      .btn.flex-fill {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
      }
    }

    /* Archive Modal Styles */
    #archiveModal .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    #archiveModal .modal-header {
      background: linear-gradient(135deg, #7F1734, #9B2B4A);
      color: white;
      border-radius: 15px 15px 0 0;
      padding: 1.5rem;
    }

    #archiveModal .modal-title {
      font-weight: 600;
      font-size: 1.25rem;
    }

    #archiveModal .btn-close {
      filter: invert(1);
    }

    #archiveModal .modal-body {
      padding: 2rem 1.5rem;
    }

    #archiveModal .modal-footer {
      padding: 1rem 1.5rem 1.5rem;
      gap: 0.75rem;
    }

    #archiveModal .btn-warning {
      background-color: #ffc107;
      border-color: #ffc107;
      color: #000;
      font-weight: 600;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      transition: all 0.2s ease;
    }

    #archiveModal .btn-warning:hover {
      background-color: #e0a800;
      border-color: #d39e00;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
    }

    #archiveModal .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
      color: white;
      font-weight: 500;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      transition: all 0.2s ease;
    }

    #archiveModal .btn-secondary:hover {
      background-color: #5a6268;
      border-color: #545b62;
      transform: translateY(-1px);
    }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="main-container">
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

      <div class="page-header">
        <h2><i class="fas fa-cubes me-2"></i>Product Management</h2>
      </div>

      <!-- Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-4">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $active_count + $archived_count ?></h3>
              <p class="card-label">Total Products</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $active_count ?></h3>
              <p class="card-label">Active Products</p>
            </div>
          </div>
        </div>
        <div class="col-md-4">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-archive"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $archived_count ?></h3>
              <p class="card-label">Archived Products</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Search and Filter Section -->
      <div class="filter-card">
        <div class="row g-3">
          <div class="col-md-6">
            <div class="input-group">
              <span class="input-group-text"><i class="fa fa-search"></i></span>
              <input type="text" class="form-control" id="productSearch" placeholder="Search products...">
            </div>
          </div>
          <div class="col-md-3">
            <select class="form-select" id="categoryFilter">
              <option value="">All Categories</option>
              <?php foreach ($categories as $category): ?>
                <option value="<?= htmlspecialchars($category['name']) ?>"><?= htmlspecialchars($category['name']) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <select class="form-select" id="stockFilter">
              <option value="">All Stock Levels</option>
              <option value="in_stock">Sufficient</option>
              <option value="low_stock">Low Stock</option>
              <option value="out_of_stock">Out of Stock</option>
            </select>
          </div>
        </div>
      </div>


      <!-- Tab Navigation -->
      <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="d-flex gap-2">
          <a class="btn <?= $current_tab === 'active' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm" 
             href="?tab=active">
            <i class="fas fa-check-circle me-1"></i>Active
          </a>
          <a class="btn <?= $current_tab === 'archived' ? 'btn-primary' : 'btn-outline-primary' ?> btn-sm" 
             href="?tab=archived">
            <i class="fas fa-archive me-1"></i>Archived
          </a>
        </div>
        <div class="d-flex align-items-center gap-3">
          <span class="badge" id="productCount" style="background-color: var(--bs-primary); color: white;">
            <?= count($products) ?> products
          </span>
          <div class="btn-group" role="group">
            <input type="radio" class="btn-check" name="viewMode" id="gridView" autocomplete="off" checked>
            <label class="btn btn-outline-primary btn-sm" for="gridView">
              <i class="fa fa-th"></i> Grid
            </label>
            <input type="radio" class="btn-check" name="viewMode" id="listView" autocomplete="off">
            <label class="btn btn-outline-primary btn-sm" for="listView">
              <i class="fa fa-list"></i> List
            </label>
          </div>
        </div>
      </div>
    <div class="row g-4 mb-5" id="productsGrid">
      <?php if (empty($products)): ?>
        <div class="col-12">
          <div class="text-center py-5">
            <i class="fas fa-<?= $current_tab === 'active' ? 'box' : 'archive' ?> fa-3x text-muted mb-3"></i>
            <h4 class="text-muted">No <?= $current_tab ?> products found</h4>
            <p class="text-muted"><?= $current_tab === 'active' ? 'All products are currently archived' : 'All products are currently active' ?></p>
          </div>
        </div>
      <?php else: ?>
        <?php foreach ($products as $product): ?>
        <div class="col-lg-4 col-md-6 product-item" 
             data-name="<?= strtolower(htmlspecialchars($product['name'])) ?>"
             data-category="<?= strtolower(htmlspecialchars($product['category_name'])) ?>"
             data-stock="<?= $product['stock'] ?>">
          <div class="product-card">
            <div class="carousel-container" id="carousel-<?= $product['id'] ?>">
              <?php
                $images = [];
                if (!empty($product['image1'])) $images[] = $product['image1'];
              ?>

              <?php if (!empty($images)): ?>
                <?php foreach ($images as $index => $img): ?>
                  <img src="<?= htmlspecialchars($img) ?>"
                       class="carousel-img <?= $index === 0 ? '' : 'd-none' ?>"
                       data-index="<?= $index ?>"
                       onerror="this.src='uploads/default.png'">
                <?php endforeach; ?>

                <?php if (count($images) > 1): ?>
                  <button class="carousel-btn prev" onclick="prevSlide(<?= $product['id'] ?>)">&lt;</button>
                  <button class="carousel-btn next" onclick="nextSlide(<?= $product['id'] ?>)">&gt;</button>
                <?php endif; ?>
              <?php else: ?>
                <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                  <i class="fa fa-image text-muted" style="font-size: 3rem;"></i>
                </div>
              <?php endif; ?>
            </div>

            <div class="p-3">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h5 class="fw-bold mb-0"><?= htmlspecialchars($product['name']) ?></h5>
                <div class="d-flex flex-column gap-1">
                  <?php if ($current_tab === 'archived'): ?>
                    <span class="badge" style="background-color: #F9E79F; color: #F57F17;">Archived</span>
                  <?php else: ?>
                    <span class="badge" style="background-color: <?= $product['stock'] > 10 ? '#198754' : ($product['stock'] > 0 ? '#ffc107' : '#db3030') ?>; color: <?= $product['stock'] > 0 && $product['stock'] <= 10 ? 'black' : 'white' ?>;">
                      <?= $product['stock'] > 10 ? 'Sufficient' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock') ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
              
              <div class="text-muted small mb-3">
                <div class="row">
                  <div class="col-6"><strong>Cost:</strong> ₱<?= number_format($product['cost_per_unit'] ?? 0, 2) ?></div>
                  <div class="col-6"><strong>Price:</strong> ₱<?= number_format($product['price'] ?? 0, 2) ?></div>
                  <div class="col-6"><strong>Markup:</strong> ₱<?= number_format($product['markup_value'], 2) ?></div>
                  <div class="col-6"><strong>Stock:</strong> <?= htmlspecialchars($product['stock']) ?> <?= htmlspecialchars($product['uom_name']) ?></div>
                </div>
                <hr class="my-2">
                <div><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></div>
                <div><strong>Brand:</strong> <?= htmlspecialchars($product['brand_name']) ?></div>
                <div><strong>Supplier:</strong> <?= htmlspecialchars($product['supplier_name']) ?></div>
                <?php if (!empty($product['expiration_date'])): ?>
                  <div><strong>Expires:</strong> <?= date('M d, Y', strtotime($product['expiration_date'])) ?></div>
                <?php endif; ?>
              </div>
              
              <div class="d-flex gap-2">
                <?php if ($current_tab === 'active'): ?>
                  <a href="archive_product.php?id=<?= $product['id'] ?>" class="btn btn-sm w-100 btn-archive archive-btn" onclick="return confirmArchive(event, this.href)">
                    <i class="fa fa-archive me-1"></i> Archive
                  </a>
                <?php else: ?>
                  <a href="products.php?unarchive=<?= $product['id'] ?>&tab=archived" class="btn btn-sm w-100 btn-unarchive unarchive-btn" onclick="return confirmUnarchive(event, this.href)">
                    <i class="fa fa-undo me-1"></i> Unarchive
                  </a>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
      <?php endif; ?>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    // Archive confirmation with SweetAlert2
    function confirmArchive(event, url) {
      event.preventDefault();
      
      Swal.fire({
        title: 'Archive Product',
        text: 'Are you sure you want to archive this product?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Archive Product',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = url;
        }
      });
      
      return false;
    }

    // Unarchive confirmation with SweetAlert2
    function confirmUnarchive(event, url) {
      event.preventDefault();
      
      Swal.fire({
        title: 'Unarchive Product',
        text: 'Are you sure you want to unarchive this product?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Unarchive Product',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = url;
        }
      });
      
      return false;
    }


    // Image carousel functionality
    const carousels = {};

    function showSlide(productId, index) {
      const images = document.querySelectorAll(`#carousel-${productId} .carousel-img`);
      if (!images.length) return;

      if (carousels[productId] === undefined) {
        carousels[productId] = 0;
      }

      const total = images.length;
      const newIndex = (index + total) % total;
      carousels[productId] = newIndex;

      images.forEach((img, i) => {
        img.classList.toggle('d-none', i !== newIndex);
      });
    }

    function nextSlide(productId) {
      showSlide(productId, (carousels[productId] ?? 0) + 1);
    }

    function prevSlide(productId) {
      showSlide(productId, (carousels[productId] ?? 0) - 1);
    }

    // Initialize carousels
    window.addEventListener("DOMContentLoaded", () => {
      document.querySelectorAll('[id^="carousel-"]').forEach(carousel => {
        const productId = carousel.id.replace('carousel-', '');
        showSlide(productId, 0);
      });
      
      // Initialize statistics
      updateStatistics();
      
    });

    // Search and Filter Functionality
    function filterProducts() {
      const searchTerm = document.getElementById('productSearch').value.toLowerCase();
      const categoryFilter = document.getElementById('categoryFilter').value.toLowerCase();
      const stockFilter = document.getElementById('stockFilter').value;
      const products = document.querySelectorAll('.product-item');
      
      let visibleCount = 0;
      
      products.forEach(product => {
        const name = product.dataset.name;
        const category = product.dataset.category;
        const stock = parseInt(product.dataset.stock);
        
        let show = true;
        
        // Search filter
        if (searchTerm && !name.includes(searchTerm)) {
          show = false;
        }
        
        // Category filter
        if (categoryFilter && !category.includes(categoryFilter)) {
          show = false;
        }
        
        // Stock filter
        if (stockFilter) {
          if (stockFilter === 'in_stock' && stock <= 10) show = false;
          if (stockFilter === 'low_stock' && (stock <= 0 || stock > 10)) show = false;
          if (stockFilter === 'out_of_stock' && stock > 0) show = false;
        }
        
        product.style.display = show ? 'block' : 'none';
        if (show) visibleCount++;
      });
      
      document.getElementById('productCount').textContent = `${visibleCount} products`;
    }

    // Update statistics
    function updateStatistics() {
      const currentProducts = document.querySelectorAll('.product-item').length;
      
      document.getElementById('productCount').textContent = `${currentProducts} products`;
    }

    // View mode toggle
    document.getElementById('gridView').addEventListener('change', function() {
      if (this.checked) {
        document.getElementById('productsGrid').className = 'row g-4 mb-5';
        document.querySelectorAll('.product-item').forEach(item => {
          item.className = 'col-lg-4 col-md-6 product-item';
        });
      }
    });

    document.getElementById('listView').addEventListener('change', function() {
      if (this.checked) {
        document.getElementById('productsGrid').className = 'row g-2 mb-5';
        document.querySelectorAll('.product-item').forEach(item => {
          item.className = 'col-12 product-item';
        });
      }
    });

    // Event listeners for search and filter
    document.getElementById('productSearch').addEventListener('input', filterProducts);
    document.getElementById('categoryFilter').addEventListener('change', filterProducts);
    document.getElementById('stockFilter').addEventListener('change', filterProducts);

    // Unit Management Functions
    function openUnitModal(productId, productName) {
      document.getElementById('unitProductId').value = productId;
      document.getElementById('unitProductName').textContent = productName;
      
      // Load conversions and boxes
      loadConversions(productId);
      loadBoxes(productId);
      
      // Show modal
      var modal = new bootstrap.Modal(document.getElementById('unitModal'));
      modal.show();
    }

    function loadConversions(productId) {
      fetch(`unit_management.php?action=get_conversions&product_id=${productId}`)
        .then(response => response.json())
        .then(data => {
          const tbody = document.getElementById('conversionsTable');
          tbody.innerHTML = '';
          
          if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted">No conversions found</td></tr>';
            return;
          }
          
          data.forEach(conversion => {
            const row = document.createElement('tr');
            row.innerHTML = `
              <td>${conversion.from_unit}</td>
              <td>${conversion.to_unit}</td>
              <td>${conversion.conversion_rate}</td>
              <td>
                <button class="btn btn-sm btn-danger" onclick="deleteConversion(${conversion.conversion_id})">
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            `;
            tbody.appendChild(row);
          });
        })
        .catch(error => {
          console.error('Error loading conversions:', error);
          document.getElementById('conversionsTable').innerHTML = '<tr><td colspan="4" class="text-center text-danger">Error loading conversions</td></tr>';
        });
    }

    function loadBoxes(productId) {
      fetch(`unit_management.php?action=get_boxes&product_id=${productId}`)
        .then(response => response.json())
        .then(data => {
          const tbody = document.getElementById('boxesTable');
          tbody.innerHTML = '';
          
          if (data.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted">No boxes found</td></tr>';
            return;
          }
          
          data.forEach(box => {
            const row = document.createElement('tr');
            row.innerHTML = `
              <td>${box.box_id}</td>
              <td>${box.batch_id}</td>
              <td>${box.weight} kg</td>
              <td><span class="badge ${box.is_sold ? 'bg-danger' : 'bg-success'}">${box.is_sold ? 'Sold' : 'Available'}</span></td>
              <td>
                <button class="btn btn-sm btn-danger" onclick="deleteBox(${box.box_id})" ${box.is_sold ? 'disabled' : ''}>
                  <i class="fa fa-trash"></i>
                </button>
              </td>
            `;
            tbody.appendChild(row);
          });
        })
        .catch(error => {
          console.error('Error loading boxes:', error);
          document.getElementById('boxesTable').innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading boxes</td></tr>';
        });
    }

    function addConversion() {
      document.getElementById('addConversionForm').style.display = 'block';
    }

    function cancelConversion() {
      document.getElementById('addConversionForm').style.display = 'none';
      document.getElementById('conversionForm').reset();
    }

    function addBox() {
      document.getElementById('addBoxForm').style.display = 'block';
    }

    function cancelBox() {
      document.getElementById('addBoxForm').style.display = 'none';
      document.getElementById('boxForm').reset();
    }

    // Conversion form submission
    document.getElementById('conversionForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const productId = document.getElementById('unitProductId').value;
      const fromUom = document.getElementById('fromUom').value;
      const toUom = document.getElementById('toUom').value;
      const rate = document.getElementById('conversionRate').value;
      
      const formData = new FormData();
      formData.append('action', 'add_conversion');
      formData.append('product_id', productId);
      formData.append('from_uom', fromUom);
      formData.append('to_uom', toUom);
      formData.append('rate', rate);
      
      fetch('unit_management.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          loadConversions(productId);
          cancelConversion();
          Swal.fire({
            title: 'Success!',
            text: 'Conversion added successfully!',
            icon: 'success',
            confirmButtonColor: '#198754'
          });
        } else {
          Swal.fire({
            title: 'Error',
            text: 'Error: ' + data.message,
            icon: 'error',
            confirmButtonColor: '#dc3545'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Swal.fire({
          title: 'Error',
          text: 'Error adding conversion',
          icon: 'error',
          confirmButtonColor: '#dc3545'
        });
      });
    });

    // Box form submission
    document.getElementById('boxForm').addEventListener('submit', function(e) {
      e.preventDefault();
      
      const productId = document.getElementById('unitProductId').value;
      const batchId = document.getElementById('batchId').value;
      const weight = document.getElementById('boxWeight').value;
      
      const formData = new FormData();
      formData.append('action', 'add_box');
      formData.append('product_id', productId);
      formData.append('batch_id', batchId);
      formData.append('weight', weight);
      
      fetch('unit_management.php', {
        method: 'POST',
        body: formData
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          loadBoxes(productId);
          cancelBox();
          Swal.fire({
            title: 'Success!',
            text: 'Box added successfully!',
            icon: 'success',
            confirmButtonColor: '#198754'
          });
        } else {
          Swal.fire({
            title: 'Error',
            text: 'Error: ' + data.message,
            icon: 'error',
            confirmButtonColor: '#dc3545'
          });
        }
      })
      .catch(error => {
        console.error('Error:', error);
        Swal.fire({
          title: 'Error',
          text: 'Error adding box',
          icon: 'error',
          confirmButtonColor: '#dc3545'
        });
      });
    });

    function deleteConversion(conversionId) {
      Swal.fire({
        title: 'Delete Conversion',
        text: 'Are you sure you want to delete this conversion?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_conversion');
          formData.append('conversion_id', conversionId);
          
          fetch('unit_management.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              const productId = document.getElementById('unitProductId').value;
              loadConversions(productId);
              Swal.fire({
                title: 'Success!',
                text: 'Conversion deleted successfully!',
                icon: 'success',
                confirmButtonColor: '#198754'
              });
            } else {
              Swal.fire({
                title: 'Error',
                text: 'Error: ' + data.message,
                icon: 'error',
                confirmButtonColor: '#dc3545'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error',
              text: 'Error deleting conversion',
              icon: 'error',
              confirmButtonColor: '#dc3545'
            });
          });
        }
      });
    }

    function deleteBox(boxId) {
      Swal.fire({
        title: 'Delete Box',
        text: 'Are you sure you want to delete this box?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Delete',
        cancelButtonText: 'Cancel'
      }).then((result) => {
        if (result.isConfirmed) {
          const formData = new FormData();
          formData.append('action', 'delete_box');
          formData.append('box_id', boxId);
          
          fetch('unit_management.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              const productId = document.getElementById('unitProductId').value;
              loadBoxes(productId);
              Swal.fire({
                title: 'Success!',
                text: 'Box deleted successfully!',
                icon: 'success',
                confirmButtonColor: '#198754'
              });
            } else {
              Swal.fire({
                title: 'Error',
                text: 'Error: ' + data.message,
                icon: 'error',
                confirmButtonColor: '#dc3545'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              title: 'Error',
              text: 'Error deleting box',
              icon: 'error',
              confirmButtonColor: '#dc3545'
            });
          });
        }
      });
    }
  </script>
</body>
</html>