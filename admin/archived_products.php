<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

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
    
    header("Location: archived_products.php");
    exit;
}


// Fetch archived products with related data
$stmt = $pdo->query("
    SELECT 
      p.product_id AS id,
      p.product_name AS name,
      p.product_description AS description,
      c.category_name,
      b.name as brand_name,
      s.name as supplier_name,
      u.name as uom_name,
      COALESCE(ps.current_stock,0) AS stock,
      COALESCE(pp.cost_price,0) AS cost_price,
      COALESCE(pp.selling_price,0) AS price,
      (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 ORDER BY pi.product_image_id DESC LIMIT 1) AS image1,
      ps.expiration_date,
      p.created_at as archived_date
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN brands b ON p.brand_id = b.id
    LEFT JOIN suppliers s ON p.supplier_id = s.supplier_id
    LEFT JOIN uom u ON p.uom_id = u.uom_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    LEFT JOIN product_pricing pp ON pp.product_id = p.product_id
    WHERE p.is_archive = 1
    ORDER BY p.created_at DESC
");
$archivedProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Products - Admin Dashboard</title>
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

    /* Badge Styles */
    .badge {
      font-size: 0.75rem;
      padding: 0.5rem 0.75rem;
      border-radius: 8px;
      font-weight: 600;
    }

    /* Action Buttons - Pastel Colors */
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

    .archived-badge {
      background-color: #F9E79F;
      color: #F57F17;
      border: 1px solid rgba(249, 231, 159, 0.3);
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
    }

    @media (max-width: 576px) {
      .btn.w-100 {
        font-size: 0.8rem;
        padding: 0.375rem 0.5rem;
      }
    }

    /* Unarchive Modal Styles */
    #unarchiveModal .modal-content {
      border-radius: 15px;
      border: none;
      box-shadow: 0 10px 40px rgba(0,0,0,0.15);
    }

    #unarchiveModal .modal-header {
      background: #198754;
      color: white;
      border-radius: 15px 15px 0 0;
      padding: 1.5rem;
    }

    #unarchiveModal .modal-title {
      font-weight: 600;
      font-size: 1.25rem;
    }

    #unarchiveModal .btn-close {
      filter: invert(1);
    }

    #unarchiveModal .modal-body {
      padding: 2rem 1.5rem;
    }

    #unarchiveModal .modal-footer {
      padding: 1rem 1.5rem 1.5rem;
      gap: 0.75rem;
    }

    #unarchiveModal .btn-success {
      background-color: #198754;
      border-color: #198754;
      color: white;
      font-weight: 600;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      transition: all 0.2s ease;
    }

    #unarchiveModal .btn-success:hover {
      background-color: #157347;
      border-color: #146c43;
      transform: translateY(-1px);
      box-shadow: 0 4px 12px rgba(25, 135, 84, 0.4);
    }

    #unarchiveModal .btn-secondary {
      background-color: #6c757d;
      border-color: #6c757d;
      color: white;
      font-weight: 500;
      border-radius: 8px;
      padding: 0.75rem 1.5rem;
      transition: all 0.2s ease;
    }

    #unarchiveModal .btn-secondary:hover {
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
                <h2><i class="fas fa-archive me-2"></i>Archived Products</h2>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-archive"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($archivedProducts); ?></h3>
                            <p class="card-label">Archived Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo array_sum(array_column($archivedProducts, 'stock')); ?></h3>
                            <p class="card-label">Total Stock</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number">₱<?php echo number_format(array_sum(array_column($archivedProducts, 'price')), 2); ?></h3>
                            <p class="card-label">Total Value</p>
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
                            <input type="text" class="form-control" id="productSearch" placeholder="Search archived products...">
                        </div>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="categoryFilter">
                            <option value="">All Categories</option>
                            <?php 
                            $stmt = $pdo->query("SELECT DISTINCT c.category_name FROM products p LEFT JOIN categories c ON p.category_id = c.category_id WHERE p.is_archive = 1");
                            $categories = $stmt->fetchAll(PDO::FETCH_COLUMN);
                            foreach ($categories as $category): ?>
                                <option value="<?= htmlspecialchars($category) ?>"><?= htmlspecialchars($category) ?></option>
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
            </div>

            <!-- Archived Products -->
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h4 class="fw-bold mb-0" style="color: var(--bs-dark);">Archived Products</h4>
                <div class="d-flex align-items-center gap-3">
                    <span class="badge" id="archivedCount" style="background-color: var(--bs-primary); color: white;"><?php echo count($archivedProducts); ?> products</span>
                    <a href="products.php" class="btn btn-secondary btn-sm">
                        <i class="fa fa-arrow-left me-1"></i> Back to Products
                    </a>
                </div>
            </div>

            <div class="row g-4 mb-5" id="productsGrid">
                <?php if (empty($archivedProducts)): ?>
                    <div class="col-12">
                        <div class="empty-state">
                            <i class="fas fa-archive"></i>
                            <h4>No archived products found</h4>
                            <p>All products are currently active</p>
                            <a href="products.php" class="btn" style="background-color: #7F1734; color: white; border: none;">
                                <i class="fa fa-arrow-left me-1"></i> Back to Products
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($archivedProducts as $product): ?>
                        <div class="col-lg-4 col-md-6 product-item" 
                             data-name="<?= strtolower(htmlspecialchars($product['name'])) ?>"
                             data-category="<?= strtolower(htmlspecialchars($product['category_name'])) ?>"
                             data-stock="<?= $product['stock'] ?>">
                            <div class="product-card">
                                <div class="carousel-container" id="carousel-<?= $product['id'] ?>">
                                    <?php if (!empty($product['image1'])): ?>
                                        <img src="<?= htmlspecialchars($product['image1']) ?>"
                                             class="carousel-img"
                                             onerror="this.src='uploads/default.png'">
                                    <?php else: ?>
                                        <div class="d-flex align-items-center justify-content-center h-100 bg-light">
                                            <i class="fa fa-image text-muted" style="font-size: 3rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="p-3">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="fw-bold mb-0"><?= htmlspecialchars($product['name']) ?></h5>
                                        <span class="badge archived-badge">Archived</span>
                                    </div>
                                    
                                    <div class="text-muted small mb-3">
                                        <div class="row">
                                            <div class="col-6"><strong>Cost:</strong> ₱<?= number_format($product['cost_price'], 2) ?></div>
                                            <div class="col-6"><strong>Price:</strong> ₱<?= number_format($product['price'], 2) ?></div>
                                            <div class="col-6"><strong>Stock:</strong> <?= htmlspecialchars($product['stock']) ?> <?= htmlspecialchars($product['uom_name']) ?></div>
                                            <div class="col-6"><strong>Category:</strong> <?= htmlspecialchars($product['category_name']) ?></div>
                                        </div>
                                        <hr class="my-2">
                                        <div><strong>Brand:</strong> <?= htmlspecialchars($product['brand_name']) ?></div>
                                        <div><strong>Supplier:</strong> <?= htmlspecialchars($product['supplier_name']) ?></div>
                                        <?php if (!empty($product['expiration_date'])): ?>
                                            <div><strong>Expires:</strong> <?= date('M d, Y', strtotime($product['expiration_date'])) ?></div>
                                        <?php endif; ?>
                                        <div><strong>Archived:</strong> <?= date('M d, Y', strtotime($product['archived_date'])) ?></div>
                                    </div>
                                    
                                    <div class="d-flex">
                                        <a href="archived_products.php?unarchive=<?= $product['id'] ?>" 
                                           class="btn btn-sm w-100 btn-unarchive unarchive-btn">
                                            <i class="fa fa-undo me-1"></i> Unarchive
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Unarchive Warning Modal -->
    <div class="modal fade" id="unarchiveModal" tabindex="-1" aria-labelledby="unarchiveModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title" id="unarchiveModalLabel">
                        <i class="fas fa-exclamation-triangle text-success me-2"></i>
                        Unarchive Product
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center py-4">
                    <div class="mb-3">
                        <i class="fas fa-undo text-success" style="font-size: 3rem;"></i>
                    </div>
                    <h6 class="mb-3">Are you sure you want to unarchive this product?</h6>
                    <p class="text-muted mb-0">
                        This action will restore the product to the active products section and make it available for sale again.
                    </p>
                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        <i class="fas fa-times me-1"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-success" id="confirmUnarchive">
                        <i class="fas fa-undo me-1"></i>Unarchive Product
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
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
            
            document.getElementById('archivedCount').textContent = `${visibleCount} products`;
        }

        // Unarchive confirmation with modal
        let unarchiveUrl = '';
        const unarchiveModal = new bootstrap.Modal(document.getElementById('unarchiveModal'));
        
        document.querySelectorAll('.unarchive-btn').forEach(button => {
            button.addEventListener('click', function(e) {
                e.preventDefault();
                unarchiveUrl = this.href;
                unarchiveModal.show();
            });
        });

        // Handle unarchive confirmation
        document.getElementById('confirmUnarchive').addEventListener('click', function() {
            if (unarchiveUrl) {
                window.location.href = unarchiveUrl;
            }
        });

        // Event listeners for search and filter
        document.getElementById('productSearch').addEventListener('input', filterProducts);
        document.getElementById('categoryFilter').addEventListener('change', filterProducts);
        document.getElementById('stockFilter').addEventListener('change', filterProducts);
    </script>
</body>
</html>