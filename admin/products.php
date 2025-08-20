<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch categories from the database
$stmt = $pdo->query("SELECT id, name FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands from the database
$stmt = $pdo->query("SELECT id, name FROM brands WHERE is_archived = 0");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch suppliers from the database
$stmt = $pdo->query("SELECT id, name FROM suppliers WHERE is_archived = 0");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch UOM from the database
$stmt = $pdo->query("SELECT id, name FROM units_of_measurement WHERE is_archived = 0");
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


// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Get form data
        $name = $_POST['name'];
        $description = $_POST['description'];
        $cost_price = $_POST['cost_price'];
        $markup_percentage = $_POST['markup_percentage'];
        $selling_price = $cost_price * (1 + ($markup_percentage / 100));
        $stock = $_POST['stock'];
        $category_id = $_POST['category_id'];
        $brand_id = $_POST['brand_id'];
        $supplier_id = $_POST['supplier_id'];
        $uom_id = $_POST['uom_id'];
        $expiration_date = $_POST['expiration_date'];
        $is_new = isset($_POST['is_new']) ? 1 : 0;
        $is_hot = isset($_POST['is_hot']) ? 1 : 0;

        // Handle image upload (now for 3 images)
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
            } else {
                $imagePaths[$i] = $uploadDir . 'default.png'; // fallback default
            }
        }
        // Insert into database with all new fields
        $stmt = $pdo->prepare("INSERT INTO products (name, description, cost_price, markup_percentage, price, stock, category_id, brand_id, supplier_id, uom_id, expiration_date, image1, image2, image3, is_new, is_hot)
                               VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $description, $cost_price, $markup_percentage, $selling_price, $stock, $category_id, $brand_id, $supplier_id, $uom_id, $expiration_date, $imagePaths[1], $imagePaths[2], $imagePaths[3], $is_new, $is_hot]);

        logHistory($pdo, 'Added Product', 'Product Name: ' . $name . ', Cost: ₱' . $cost_price . ', Markup: ' . $markup_percentage . '%, Selling Price: ₱' . $selling_price, $_SESSION['username']);

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
            --primary-color: #7F1734;
            --secondary-color: #a91d42;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
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
          <i class="fa fa-cubes text-primary me-3"></i>Product Management
        </h1>
      </div>
    </div>

    <!-- Action Buttons -->
    <div class="d-flex flex-wrap gap-2 mb-4">
      <button class="btn text-white" style="background-color: var(--primary-color);" data-bs-toggle="modal" data-bs-target="#addProductModal">
        <i class="fa fa-plus-circle me-1"></i> Add New Product
      </button>
      <button class="btn text-white" style="background-color: var(--primary-color);" data-bs-toggle="modal" data-bs-target="#categoryModal">
        <i class="fa fa-tags me-1"></i> Manage Categories
      </button>
        <button class="btn text-white" style="background-color: var(--primary-color);" data-bs-target="#brandModal">
        <i class="fa fa-trademark me-1"></i> Manage Brands
      </button>
      <button class="btn text-white" style="background-color: var(--primary-color);" data-bs-toggle="modal" data-bs-target="#uomModal">
        <i class="fa fa-ruler me-1"></i> Manage UOM
      </button>
        <button class="btn text-white" style="background-color: var(--primary-color);"data-bs-target="#archiveModal">
        <i class="fa fa-archive me-1"></i> View Archived
      </button>
       <button class="btn text-white" style="background-color: var(--primary-color);" data-bs-target="#discountModal">
        <i class="fa fa-percent me-1"></i> Discount Codes
      </button>
    </div>

    <!-- Active Products -->
    <h4 class="fw-bold mb-3">Active Products</h4>
    <div class="row g-4 mb-5">
      <?php
      // Fetch active products with all related data
      $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, b.name as brand_name, s.name as supplier_name, u.name as uom_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        LEFT JOIN units_of_measurement u ON p.uom_id = u.id
        WHERE p.is_archived = 0
        ORDER BY p.name
      ");
      $activeProducts = $stmt->fetchAll();
      foreach ($activeProducts as $product): ?>
        <div class="col-lg-4 col-md-6">
          <div class="product-card">
            <div class="carousel-container" id="carousel-<?= $product['id'] ?>">
              <?php
                $images = [];
                if (!empty($product['image1'])) $images[] = $product['image1'];
                if (!empty($product['image2']) && $product['image2'] !== 'uploads/default.png') $images[] = $product['image2'];
                if (!empty($product['image3']) && $product['image3'] !== 'uploads/default.png') $images[] = $product['image3'];
              ?>

              <?php foreach ($images as $index => $img): ?>
                <img src="<?= htmlspecialchars($img) ?>"
                     class="carousel-img <?= $index === 0 ? '' : 'd-none' ?>"
                     data-index="<?= $index ?>">
              <?php endforeach; ?>

              <?php if (count($images) > 1): ?>
                <button class="carousel-btn prev" onclick="prevSlide(<?= $product['id'] ?>)">&lt;</button>
                <button class="carousel-btn next" onclick="nextSlide(<?= $product['id'] ?>)">&gt;</button>
              <?php endif; ?>
            </div>

            <div class="p-3">
              <h5 class="fw-bold mb-2"><?= htmlspecialchars($product['name']) ?></h5>
              <div class="text-muted small mb-3">
                <div><strong>Cost:</strong> ₱<?= number_format($product['cost_price'], 2) ?></div>
                <div><strong>Markup:</strong> <?= $product['markup_percentage'] ?>%</div>
                <div><strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?></div>
                <div><strong>Stock:</strong> <?= htmlspecialchars($product['stock']) ?> <?= htmlspecialchars($product['uom_name']) ?></div>
                <div><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></div>
                <div><strong>Brand:</strong> <?= htmlspecialchars($product['brand_name']) ?></div>
                <div><strong>Supplier:</strong> <?= htmlspecialchars($product['supplier_name']) ?></div>
                <?php if ($product['expiration_date']): ?>
                  <div><strong>Expires:</strong> <?= date('M d, Y', strtotime($product['expiration_date'])) ?></div>
                <?php endif; ?>
              </div>
              <div class="d-flex gap-2">
                <a href="edit_product.php?id=<?= $product['id'] ?>" class="btn btn-success btn-sm flex-fill">
                  <i class="fa fa-edit me-1"></i> Edit
                </a>
                <a href="archive_product.php?id=<?= $product['id'] ?>" class="btn btn-warning btn-sm flex-fill archive-btn">
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

            <div class="row">
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Stock</label>
                  <input type="number" name="stock" class="form-control" required>
                </div>
              </div>
              <div class="col-md-6">
                <div class="mb-3">
                  <label class="form-label">Expiration Date</label>
                  <input type="date" name="expiration_date" class="form-control">
                </div>
              </div>
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

            <div class="row">
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_new" value="1" id="isNew">
                  <label class="form-check-label" for="isNew">Mark as New</label>
                </div>
              </div>
              <div class="col-md-6">
                <div class="form-check">
                  <input class="form-check-input" type="checkbox" name="is_hot" value="1" id="isHot">
                  <label class="form-check-label" for="isHot">Mark as Hot</label>
                </div>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success">Add Product</button>
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
            <button type="submit" class="btn btn-warning">Add Category</button>
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
            <button type="submit" class="btn btn-info">Add Brand</button>
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
            <button type="submit" class="btn" style="background-color: var(--bs-secondary); color: white;">Add UOM</button>
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
              SELECT p.*, c.name as category_name, b.name as brand_name, s.name as supplier_name, u.name as uom_name
              FROM products p
              LEFT JOIN categories c ON p.category_id = c.id
              LEFT JOIN brands b ON p.brand_id = b.id
              LEFT JOIN suppliers s ON p.supplier_id = s.id
              LEFT JOIN units_of_measurement u ON p.uom_id = u.id
              WHERE p.is_archived = 1
              ORDER BY p.name
            ");
            $archivedProducts = $stmt->fetchAll();
            foreach ($archivedProducts as $product): ?>
              <div class="col-md-6 col-lg-4">
                <div class="card">
                  <img src="<?= $product['image1'] ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" class="card-img-top" style="height: 150px; object-fit: contain;">
                  <div class="card-body">
                    <h6 class="card-title"><?= htmlspecialchars($product['name']) ?></h6>
                    <p class="card-text small">
                      <strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?><br>
                      <strong>Stock:</strong> <?= htmlspecialchars($product['stock']) ?> <?= htmlspecialchars($product['uom_name']) ?>
                    </p>
                    <a href="unarchive_product.php?id=<?= $product['id'] ?>" class="btn btn-success btn-sm w-100 unarchive-btn">
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
              <button type="submit" class="btn btn-primary" id="discount_submit_btn">Add Discount Code</button>
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
                      <button type="button" class="btn btn-info btn-sm edit-discount-btn"
                        data-id="<?= $d['id'] ?>"
                        data-code="<?= htmlspecialchars($d['code']) ?>"
                        data-type="<?= $d['discount_type'] ?>"
                        data-value="<?= $d['discount_value'] ?>"
                        data-active="<?= $d['is_active'] ?>"
                        data-expires="<?= $d['expires_at'] ?>"
                      >Edit</button>
                      <a href="products.php?delete_discount=<?= $d['id'] ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this discount code?')">Delete</a>
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
    });
  </script>
</body>
</html>