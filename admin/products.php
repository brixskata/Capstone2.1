<?php
include '../includes/db.php';
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