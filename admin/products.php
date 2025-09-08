<?php
include 'db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
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

// Handle Discount Code Add/Edit/Delete
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['discount_action'])) {
    $code = strtoupper(trim($_POST['code']));
    $discount_type = $_POST['discount_type'];
    $discount_value = floatval($_POST['discount_value']);
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $expires_at = !empty($_POST['expires_at']) ? $_POST['expires_at'] : null;

    if ($_POST['discount_action'] === 'add') {
        $stmt = $pdo->prepare("INSERT INTO discount_codes (code, discount_type, discount_value, is_active, expires_at) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$code, $discount_type, $discount_value, $is_active, $expires_at]);
    } elseif ($_POST['discount_action'] === 'edit' && !empty($_POST['discount_id'])) {
        $stmt = $pdo->prepare("UPDATE discount_codes SET code=?, discount_type=?, discount_value=?, is_active=?, expires_at=? WHERE id=?");
        $stmt->execute([$code, $discount_type, $discount_value, $is_active, $expires_at, $_POST['discount_id']]);
    }
    header("Location: products.php#discountModal");
    exit;
}
if (isset($_GET['delete_discount'])) {
    $stmt = $pdo->prepare("DELETE FROM discount_codes WHERE id=?");
    $stmt->execute([$_GET['delete_discount']]);
    header("Location: products.php#discountModal");
    exit;
}

// Fetch discount codes
$stmt = $pdo->query("SELECT * FROM discount_codes ORDER BY id DESC");
$discountCodes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for adding product only when explicitly requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'add_product') {
    try {
        // Get form data
        $name = $_POST['name'];
        $description = $_POST['description'];
        $cost_price = (float)$_POST['cost_price'];
        $markup_percentage = (float)$_POST['markup_percentage'];
        $selling_price = $cost_price * (1 + ($markup_percentage / 100));
        $category_id = (int)$_POST['category_id'];
        $brand_id = (int)$_POST['brand_id'];
        $supplier_id = (int)$_POST['supplier_id'];
        $uom_id = (int)$_POST['uom_id'];

        // Handle image upload (primary image to product_images)
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        $imagePaths = [];
        for ($i = 1; $i <= 3; $i++) {
            $inputName = "image$i";
            if (isset($_FILES[$inputName]) && $_FILES[$inputName]['error'] === 0) {
                $imageTmpName = $_FILES[$inputName]['tmp_name'];
                $imageName = basename($_FILES[$inputName]['name']);
                $uniqueName = uniqid() . '-' . $imageName;
                $imagePath = $uploadDir . $uniqueName;
                move_uploaded_file($imageTmpName, $imagePath);
                $imagePaths[$i] = $imagePath;
            }
        }

        // Create product core
        $stmt = $pdo->prepare("INSERT INTO products (product_name, product_description, category_id, brand_id, supplier_id, uom_id) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $category_id, $brand_id, $supplier_id, $uom_id]);
        $newProductId = (int)$pdo->lastInsertId();

        // Pricing
        $stmt = $pdo->prepare("INSERT INTO product_pricing (product_id, cost_price, markup_percentage, selling_price, pricing_type) VALUES (?, ?, ?, ?, 'stored')");
        $stmt->execute([$newProductId, $cost_price, $markup_percentage, $selling_price]);

        // Stock (initialize with 0 stock - to be managed through inventory)
        $stmt = $pdo->prepare("INSERT INTO product_stock (product_id, current_stock, reorder_point, expiration_date) VALUES (?, 0, 10, NULL)");
        $stmt->execute([$newProductId]);

        // Images (mark first as primary)
        $isPrimarySet = false;
        foreach ($imagePaths as $idx => $path) {
            $stmt = $pdo->prepare("INSERT INTO product_images (product_id, image_url, is_primary) VALUES (?, ?, ?)");
            $stmt->execute([$newProductId, $path, $isPrimarySet ? 0 : 1]);
            $isPrimarySet = true;
        }

        logHistory($pdo, 'Added Product', 'Product Name: ' . $name . ', Cost: ₱' . $cost_price . ', Markup: ' . $markup_percentage . '%, Selling Price: ₱' . $selling_price . ' (Stock: 0 - to be managed through inventory)', $_SESSION['username']);

        $_SESSION['success'] = "Product added successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding product: " . $e->getMessage();
    }

    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Product Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
  <style>
    :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #db3030;
            --bs-warning: #ffc107;
            --bs-info: #016bf8;
            --bs-light: #f0f3f2;
            --bs-dark: #001e2b;
        }
      .product-card {
        background: white;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
        transition: transform 0.2s ease;
        overflow: hidden;
      }

      .product-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
      }

      .product-image {
        width: 100%;
        height: 200px;
        object-fit: contain;
        background-color: #f8f9fa;
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
      }

      .carousel-btn:hover {
        background: rgba(0, 0, 0, 0.7);
      }

      .carousel-btn.prev {
        left: 10px;
      }

      .carousel-btn.next {
        right: 10px;
      }

      /* Enhanced Product Card Styles */
      .product-card {
        transition: all 0.3s ease;
        border: 1px solid #e9ecef;
        position: relative;
        overflow: hidden;
      }

      .product-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: #7F1734;
        transform: scaleX(0);
        transition: transform 0.3s ease;
      }

      .product-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 25px rgba(0,0,0,0.15);
        border-color: #7F1734;
      }

      .product-card:hover::before {
        transform: scaleX(1);
      }

      /* Search and Filter Styles */
      .input-group-text {
        background: #7F1734;
        color: white;
        border-color: #7F1734;
      }

      .form-control:focus, .form-select:focus {
        border-color: #7F1734;
        box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
      }

      /* Statistics Cards */
      .stat-card {
        background: white;
        border-radius: 0.5rem;
        padding: 1rem;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        border-left: 4px solid #7F1734;
      }

      /* Badge Styles */
      .badge {
        font-size: 0.75rem;
        padding: 0.5rem 0.75rem;
      }

      /* Button Group Styles */
      .btn-group .btn-check:checked + .btn {
        background-color: #7F1734;
        border-color: #7F1734;
      }

      /* Empty State */
      .empty-state {
        text-align: center;
        padding: 3rem 1rem;
        color: #6c757d;
      }

      .empty-state i {
        font-size: 4rem;
        margin-bottom: 1rem;
        color: #7F1734;
        opacity: 0.5;
      }

      /* Responsive Improvements */
      @media (max-width: 768px) {
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
          <i class="fa fa-cubes me-3" style="color: #7F1734;"></i>Product Management
        </h1>
        <p class="text-muted mb-0">Manage your product catalog, categories, and inventory</p>
      </div>
      <div class="d-flex align-items-center gap-3">
        <div class="text-end">
          <div class="h4 mb-0" id="totalProducts" style="color: #7F1734;">0</div>
          <small class="text-muted">Total Products</small>
        </div>
        <div class="text-end">
          <div class="h4 mb-0" id="activeProducts" style="color: #198754;">0</div>
          <small class="text-muted">Active</small>
        </div>
        <div class="text-end">
          <div class="h4 mb-0" id="archivedProducts" style="color: #ffc107;">0</div>
          <small class="text-muted">Archived</small>
        </div>
      </div>
    </div>

    <!-- Search and Filter Section -->
    <div class="row mb-4">
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
          <option value="in_stock">In Stock</option>
          <option value="low_stock">Low Stock</option>
          <option value="out_of_stock">Out of Stock</option>
        </select>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex flex-wrap gap-2 mb-4">
      <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#addProductModal" style="background-color: #7F1734; color: white; border: none;">
        <i class="fa fa-plus-circle me-1"></i> Add New Product
      </button>
      <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#categoryModal" style="background-color: #016bf8; color: white; border: none;">
        <i class="fa fa-tags me-1"></i> Manage Categories
      </button>
      <button type="button" class="btn btn-secondary" data-bs-toggle="modal" data-bs-target="#brandModal">
        <i class="fa fa-trademark me-1"></i> Manage Brands
      </button>
      <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#uomModal" style="background-color: #ffc107; color: black; border: none;">
        <i class="fa fa-ruler me-1"></i> Manage UOM
      </button>
      <button type="button" class="btn btn-dark" data-bs-toggle="modal" data-bs-target="#archiveModal">
        <i class="fa fa-archive me-1"></i> View Archived
      </button>
      <button type="button" class="btn" data-bs-toggle="modal" data-bs-target="#discountModal" style="background-color: #198754; color: white; border: none;">
        <i class="fa fa-percent me-1"></i> Discount Codes
      </button>
    </div>

    <!-- Active Products -->
    <div class="d-flex justify-content-between align-items-center mb-3">
      <h4 class="fw-bold mb-0">Active Products</h4>
      <div class="d-flex align-items-center gap-3">
        <span class="badge" id="activeCount" style="background-color: #7F1734; color: white;">0 products</span>
        <div class="btn-group" role="group">
          <input type="radio" class="btn-check" name="viewMode" id="gridView" autocomplete="off" checked>
          <label class="btn btn-outline-primary btn-sm" for="gridView" style="border-color: #7F1734; color: #7F1734;">
            <i class="fa fa-th"></i> Grid
          </label>
          <input type="radio" class="btn-check" name="viewMode" id="listView" autocomplete="off">
          <label class="btn btn-outline-primary btn-sm" for="listView" style="border-color: #7F1734; color: #7F1734;">
            <i class="fa fa-list"></i> List
          </label>
        </div>
      </div>
    </div>
    <div class="row g-4 mb-5" id="productsGrid">
      <?php
      // Fetch active products with related data from normalized tables
      $stmt = $pdo->query("
        SELECT 
          p.product_id AS id,
          p.product_name AS name,
          p.product_description AS description,
          c.category_name as category_name,
          b.name as brand_name,
          s.name as supplier_name,
          u.name as uom_name,
          COALESCE(ps.current_stock,0) AS stock,
          COALESCE(pp.cost_price,0) AS cost_price,
          COALESCE(pp.markup_percentage,0) AS markup_percentage,
          COALESCE(pp.selling_price,0) AS price,
          (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1,
          ps.expiration_date
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
        LEFT JOIN uom u ON p.uom_id = u.uom_id
        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
        LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
        WHERE p.is_archive = 0
        ORDER BY p.product_name
      ");
      $activeProducts = $stmt->fetchAll();
      foreach ($activeProducts as $product): ?>
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
                <span class="badge" style="background-color: <?= $product['stock'] > 10 ? '#198754' : ($product['stock'] > 0 ? '#ffc107' : '#db3030') ?>; color: <?= $product['stock'] > 0 && $product['stock'] <= 10 ? 'black' : 'white' ?>;">
                  <?= $product['stock'] > 10 ? 'In Stock' : ($product['stock'] > 0 ? 'Low Stock' : 'Out of Stock') ?>
                </span>
              </div>
              
              <div class="text-muted small mb-3">
                <div class="row">
                  <div class="col-6"><strong>Cost:</strong> ₱<?= number_format($product['cost_price'], 2) ?></div>
                  <div class="col-6"><strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?></div>
                  <div class="col-6"><strong>Markup:</strong> <?= $product['markup_percentage'] ?>%</div>
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
                <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-sm flex-fill" style="background-color: #198754; color: white; border: none;">
                  <i class="fa fa-edit me-1"></i> Edit
                </a>
                <button type="button" class="btn btn-sm flex-fill" style="background-color: #7F1734; color: white; border: none;" onclick="openUnitModal(<?= $product['id'] ?>, '<?= htmlspecialchars($product['name']) ?>')">
                  <i class="fa fa-cogs me-1"></i> Units
                </button>
                <a href="../product_detail.php?id=<?= $product['id'] ?>" class="btn btn-sm flex-fill" style="background-color: #016bf8; color: white; border: none;" target="_blank">
                  <i class="fa fa-eye me-1"></i> View
                </a>
                <a href="archive_product.php?id=<?= $product['id'] ?>" class="btn btn-sm flex-fill archive-btn" style="background-color: #ffc107; color: black; border: none;">
                  <i class="fa fa-archive me-1"></i> Archive
                </a>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Add Product Modal -->
  <div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <form action="products.php" method="POST" enctype="multipart/form-data">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Add New Product</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="form_type" value="add_product">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Product Name</label>
                  <input type="text" name="name" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Category</label>
                  <select name="category_id" class="form-select" required>
                    <option value="">Select Category</option>
                    <?php foreach ($categories as $category): ?>
                      <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea name="description" class="form-control" rows="3" required></textarea>
            </div>

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Cost Price (₱)</label>
                  <input type="number" step="0.01" name="cost_price" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Markup (%)</label>
                  <input type="number" step="0.01" name="markup_percentage" class="form-control" required>
                </div>
              </div>
            </div>

            <div class="alert alert-info">
              <i class="fa fa-info-circle me-2"></i>
              <strong>Note:</strong> Stock and expiration dates will be managed through the Inventory Management system after product creation.
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Brand</label>
                  <select name="brand_id" class="form-select" required>
                    <option value="">Select Brand</option>
                    <?php foreach ($brands as $brand): ?>
                      <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Supplier</label>
                  <select name="supplier_id" class="form-select" required>
                    <option value="">Select Supplier</option>
                    <?php foreach ($suppliers as $supplier): ?>
                      <option value="<?= $supplier['id'] ?>"><?= htmlspecialchars($supplier['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Unit of Measurement</label>
                  <select name="uom_id" class="form-select" required>
                    <option value="">Select UOM</option>
                    <?php foreach ($uoms as $uom): ?>
                      <option value="<?= $uom['id'] ?>"><?= htmlspecialchars($uom['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="row">
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Product Image 1</label>
                  <input type="file" name="image1" accept="image/*" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Product Image 2</label>
                  <input type="file" name="image2" accept="image/*" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="mb-3">
                  <label class="form-label">Product Image 3</label>
                  <input type="file" name="image3" accept="image/*" class="form-control">
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn" style="background-color: #198754; color: white; border: none;">Add Product</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Category Management Modal -->
  <div class="modal fade" id="categoryModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="add_category.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Manage Categories</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">New Category Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Category Name</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($categories as $category): ?>
                    <tr>
                      <td><?= htmlspecialchars($category['name']) ?></td>
                      <td>
                        <a href="delete_category.php?id=<?= $category['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this category?')">
                          <i class="fa fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn" style="background-color: #ffc107; color: black; border: none;">Add Category</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Brand Management Modal -->
  <div class="modal fade" id="brandModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="add_brand.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Manage Brands</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">New Brand Name</label>
              <input type="text" name="name" class="form-control" required>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>Brand Name</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($brands as $brand): ?>
                    <tr>
                      <td><?= htmlspecialchars($brand['name']) ?></td>
                      <td>
                        <a href="delete_brand.php?id=<?= $brand['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this brand?')">
                          <i class="fa fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn" style="background-color: #016bf8; color: white; border: none;">Add Brand</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- UOM Management Modal -->
  <div class="modal fade" id="uomModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="add_uom.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Manage Units of Measurement</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">New UOM Name</label>
              <input type="text" name="name" class="form-control" placeholder="e.g., pieces, kilos, boxes" required>
            </div>
            <div class="table-responsive">
              <table class="table">
                <thead>
                  <tr>
                    <th>UOM Name</th>
                    <th>Actions</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach ($uoms as $uom): ?>
                    <tr>
                      <td><?= htmlspecialchars($uom['name']) ?></td>
                      <td>
                        <a href="delete_uom.php?id=<?= $uom['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this UOM?')">
                          <i class="fa fa-trash"></i>
                        </a>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            <button type="submit" class="btn" style="background-color: #7F1734; color: white;">Add UOM</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Archived Products Modal -->
  <div class="modal fade" id="archiveModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Archived Products</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="row g-3">
            <?php
            $stmt = $pdo->query("
              SELECT p.*, c.category_name, b.name as brand_name, s.name as supplier_name, u.name as uom_name,
                     COALESCE(pp.selling_price, 0) as price,
                     COALESCE(ps.current_stock, 0) as stock,
                     (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as image1
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.category_id
              LEFT JOIN brands b ON p.brand_id = b.id
              LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
              LEFT JOIN uom u ON p.uom_id = u.uom_id
              LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
              LEFT JOIN product_stock ps ON p.product_id = ps.product_id
              WHERE p.is_archive = 1
              ORDER BY p.product_name
            ");
            $archivedProducts = $stmt->fetchAll();
            foreach ($archivedProducts as $product): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card">
                  <img src="<?= $product['image1'] ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" class="card-img-top" style="height: 150px; object-fit: contain;">
                  <div class="card-body">
                    <h6 class="card-title"><?= htmlspecialchars($product['product_name']) ?></h6>
                    <p class="card-text small">
                      <strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?><br>
                      <strong>Stock:</strong> <?= htmlspecialchars($product['stock']) ?> <?= htmlspecialchars($product['uom_name']) ?>
                    </p>
                    <a href="unarchive_product.php?id=<?= $product['product_id'] ?>" class="btn btn-sm w-100 unarchive-btn" style="background-color: #198754; color: white; border: none;">
                      <i class="fa fa-undo me-1"></i> Unarchive
                    </a>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Discount Code Management Modal -->
  <div class="modal fade" id="discountModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Manage Discount Codes</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <!-- Add/Edit Form -->
          <form action="products.php" method="POST" id="discountForm" class="mb-4">
            <input type="hidden" name="discount_action" value="add" id="discount_action">
            <input type="hidden" name="discount_id" id="discount_id">
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Code</label>
                  <input type="text" name="code" id="discount_code" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Type</label>
                  <select name="discount_type" id="discount_type" class="form-select" required>
                    <option value="percent">Percent (%)</option>
                    <option value="fixed">Fixed Amount</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Value</label>
                  <input type="number" step="0.01" name="discount_value" id="discount_value" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Expires At</label>
                  <input type="datetime-local" name="expires_at" id="discount_expires_at" class="form-control">
                </div>
              </div>
            </div>
            <div class="mb-3">
              <div class="form-check">
                <input type="checkbox" name="is_active" id="discount_is_active" class="form-check-input" checked>
                <label for="discount_is_active" class="form-check-label">Active</label>
              </div>
            </div>
            <div class="d-flex gap-2">
              <button type="submit" class="btn" id="discount_submit_btn" style="background-color: #7F1734; color: white; border: none;">Add Discount Code</button>
              <button type="button" onclick="resetDiscountForm()" class="btn btn-secondary">Reset</button>
            </div>
          </form>

          <!-- Existing Codes -->
          <h6 class="fw-bold mb-3">Existing Discount Codes</h6>
          <div class="table-responsive">
            <table class="table">
              <thead>
                <tr>
                  <th>Code</th>
                  <th>Type</th>
                  <th>Value</th>
                  <th>Active</th>
                  <th>Expires</th>
                  <th>Actions</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($discountCodes as $d): ?>
                  <tr>
                    <td><?= htmlspecialchars($d['code']) ?></td>
                    <td><?= $d['discount_type'] === 'percent' ? 'Percent' : 'Fixed' ?></td>
                    <td><?= $d['discount_type'] === 'percent' ? $d['discount_value'].'%' : '₱'.number_format($d['discount_value'],2) ?></td>
                    <td><?= $d['is_active'] ? 'Yes' : 'No' ?></td>
                    <td><?= $d['expires_at'] ? date('Y-m-d H:i', strtotime($d['expires_at'])) : 'Never' ?></td>
                    <td>
                      <button type="button" class="btn btn-sm edit-discount-btn" style="background-color: #016bf8; color: white; border: none;"
                        data-id="<?= $d['id'] ?>"
                        data-code="<?= htmlspecialchars($d['code']) ?>"
                        data-type="<?= $d['discount_type'] ?>"
                        data-value="<?= $d['discount_value'] ?>"
                        data-active="<?= $d['is_active'] ?>"
                        data-expires="<?= $d['expires_at'] ?>"
                      >Edit</button>
                      <a href="products.php?delete_discount=<?= $d['id'] ?>" class="btn btn-sm" style="background-color: #db3030; color: white; border: none;" onclick="return confirm('Delete this discount code?')">Delete</a>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Unit Management Modal -->
  <div class="modal fade" id="unitModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Unit Management - <span id="unitProductName"></span></h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <input type="hidden" id="unitProductId">
          
          <!-- Unit Conversions Tab -->
          <ul class="nav nav-tabs mb-4" id="unitTabs" role="tablist">
            <li class="nav-item" role="presentation">
              <button class="nav-link active" id="conversions-tab" data-bs-toggle="tab" data-bs-target="#conversions" type="button" role="tab">
                <i class="fa fa-exchange-alt me-2"></i>Unit Conversions
              </button>
            </li>
            <li class="nav-item" role="presentation">
              <button class="nav-link" id="boxes-tab" data-bs-toggle="tab" data-bs-target="#boxes" type="button" role="tab">
                <i class="fa fa-box me-2"></i>Variable Boxes
              </button>
            </li>
          </ul>

          <div class="tab-content" id="unitTabContent">
            <!-- Unit Conversions -->
            <div class="tab-pane fade show active" id="conversions" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Unit Conversion Rates</h6>
                <button type="button" class="btn btn-sm" style="background-color: #7F1734; color: white; border: none;" onclick="addConversion()">
                  <i class="fa fa-plus me-1"></i>Add Conversion
                </button>
              </div>
              
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>From Unit</th>
                      <th>To Unit</th>
                      <th>Conversion Rate</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="conversionsTable">
                    <!-- Dynamic content -->
                  </tbody>
                </table>
              </div>

              <!-- Add Conversion Form -->
              <div class="card mt-3" id="addConversionForm" style="display: none;">
                <div class="card-body">
                  <h6 class="card-title">Add New Conversion</h6>
                  <form id="conversionForm">
                    <div class="row">
                      <div class="col-md-4">
                        <label class="form-label">From Unit</label>
                        <select class="form-select" id="fromUom" required>
                          <option value="">Select Unit</option>
                          <?php foreach ($uoms as $uom): ?>
                            <option value="<?= $uom['id'] ?>"><?= htmlspecialchars($uom['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">To Unit</label>
                        <select class="form-select" id="toUom" required>
                          <option value="">Select Unit</option>
                          <?php foreach ($uoms as $uom): ?>
                            <option value="<?= $uom['id'] ?>"><?= htmlspecialchars($uom['name']) ?></option>
                          <?php endforeach; ?>
                        </select>
                      </div>
                      <div class="col-md-4">
                        <label class="form-label">Rate (how many from units = 1 to unit)</label>
                        <input type="number" step="0.0001" class="form-control" id="conversionRate" required>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button type="submit" class="btn btn-sm" style="background-color: #198754; color: white; border: none;">
                        <i class="fa fa-save me-1"></i>Save
                      </button>
                      <button type="button" class="btn btn-sm btn-secondary" onclick="cancelConversion()">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>

            <!-- Variable Boxes -->
            <div class="tab-pane fade" id="boxes" role="tabpanel">
              <div class="d-flex justify-content-between align-items-center mb-3">
                <h6 class="mb-0">Variable Weight Boxes</h6>
                <button type="button" class="btn btn-sm" style="background-color: #7F1734; color: white; border: none;" onclick="addBox()">
                  <i class="fa fa-plus me-1"></i>Add Box
                </button>
              </div>
              
              <div class="table-responsive">
                <table class="table table-sm">
                  <thead>
                    <tr>
                      <th>Box ID</th>
                      <th>Batch ID</th>
                      <th>Weight (kg)</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody id="boxesTable">
                    <!-- Dynamic content -->
                  </tbody>
                </table>
              </div>

              <!-- Add Box Form -->
              <div class="card mt-3" id="addBoxForm" style="display: none;">
                <div class="card-body">
                  <h6 class="card-title">Add New Box</h6>
                  <form id="boxForm">
                    <div class="row">
                      <div class="col-md-6">
                        <label class="form-label">Batch ID</label>
                        <input type="text" class="form-control" id="batchId" placeholder="e.g., BATCH001" required>
                      </div>
                      <div class="col-md-6">
                        <label class="form-label">Weight (kg)</label>
                        <input type="number" step="0.01" class="form-control" id="boxWeight" required>
                      </div>
                    </div>
                    <div class="mt-3">
                      <button type="submit" class="btn btn-sm" style="background-color: #198754; color: white; border: none;">
                        <i class="fa fa-save me-1"></i>Save
                      </button>
                      <button type="button" class="btn btn-sm btn-secondary" onclick="cancelBox()">Cancel</button>
                    </div>
                  </form>
                </div>
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    // Archive/Unarchive confirmation
    document.querySelectorAll('.archive-btn').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to archive this product?")) {
          window.location.href = this.href;
        }
      });
    });

    document.querySelectorAll('.unarchive-btn').forEach(button => {
      button.addEventListener('click', function(e) {
        e.preventDefault();
        if (confirm("Are you sure you want to unarchive this product?")) {
          window.location.href = this.href;
        }
      });
    });

    // Discount code management
    document.querySelectorAll('.edit-discount-btn').forEach(btn => {
      btn.addEventListener('click', function() {
        document.getElementById('discount_action').value = 'edit';
        document.getElementById('discount_id').value = this.dataset.id;
        document.getElementById('discount_code').value = this.dataset.code;
        document.getElementById('discount_type').value = this.dataset.type;
        document.getElementById('discount_value').value = this.dataset.value;
        document.getElementById('discount_is_active').checked = this.dataset.active == "1";
        document.getElementById('discount_expires_at').value = this.dataset.expires ? this.dataset.expires.replace(' ', 'T') : '';
        document.getElementById('discount_submit_btn').textContent = 'Update Discount Code';
        // Show the modal to edit
        var modal = new bootstrap.Modal(document.getElementById('discountModal'));
        modal.show();
      });
    });

    function resetDiscountForm() {
      document.getElementById('discount_action').value = 'add';
      document.getElementById('discount_id').value = '';
      document.getElementById('discount_code').value = '';
      document.getElementById('discount_type').value = 'percent';
      document.getElementById('discount_value').value = '';
      document.getElementById('discount_is_active').checked = true;
      document.getElementById('discount_expires_at').value = '';
      document.getElementById('discount_submit_btn').textContent = 'Add Discount Code';
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
      
      document.getElementById('activeCount').textContent = `${visibleCount} products`;
    }

    // Update statistics
    function updateStatistics() {
      const totalProducts = document.querySelectorAll('.product-item').length;
      const activeProducts = document.querySelectorAll('.product-item').length;
      
      // Get archived count (you might need to fetch this via AJAX)
      const archivedProducts = 0; // This should be fetched from server
      
      document.getElementById('totalProducts').textContent = totalProducts;
      document.getElementById('activeProducts').textContent = activeProducts;
      document.getElementById('archivedProducts').textContent = archivedProducts;
      document.getElementById('activeCount').textContent = `${activeProducts} products`;
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
          alert('Conversion added successfully!');
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error adding conversion');
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
          alert('Box added successfully!');
        } else {
          alert('Error: ' + data.message);
        }
      })
      .catch(error => {
        console.error('Error:', error);
        alert('Error adding box');
      });
    });

    function deleteConversion(conversionId) {
      if (confirm('Are you sure you want to delete this conversion?')) {
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
            alert('Conversion deleted successfully!');
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error deleting conversion');
        });
      }
    }

    function deleteBox(boxId) {
      if (confirm('Are you sure you want to delete this box?')) {
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
            alert('Box deleted successfully!');
          } else {
            alert('Error: ' + data.message);
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error deleting box');
        });
      }
    }
  </script>
</body>
</html>