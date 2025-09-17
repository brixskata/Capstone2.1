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

// Handle permanent deletion
if (isset($_GET['delete'])) {
    $product_id = (int)$_GET['delete'];
    
    try {
        // Get product name for logging
        $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
        $stmt->execute([$product_id]);
        $product = $stmt->fetch();
        
        if ($product) {
            // Delete related data first
            $pdo->beginTransaction();
            
            // Delete product images
            $stmt = $pdo->prepare("DELETE FROM product_images WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Delete product pricing
            $stmt = $pdo->prepare("DELETE FROM product_pricing WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Delete product stock
            $stmt = $pdo->prepare("DELETE FROM product_stock WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            // Delete the product
            $stmt = $pdo->prepare("DELETE FROM products WHERE product_id = ?");
            $stmt->execute([$product_id]);
            
            $pdo->commit();
            
            $_SESSION['success'] = "Product '{$product['product_name']}' permanently deleted";
            logHistory($pdo, 'Product Deleted', "Permanently deleted product: {$product['product_name']}", $_SESSION['username']);
        } else {
            $_SESSION['error'] = "Product not found";
        }
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error deleting product: " . $e->getMessage();
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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Archived Products - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .product-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
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
            border-radius: 8px;
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            border-left: 4px solid #7F1734;
        }
        
        .archived-badge {
            background-color: #ffc107;
            color: black;
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
                    <i class="fa fa-archive me-3" style="color: #7F1734;"></i>Archived Products
                </h1>
                <p class="text-muted">View and manage archived products</p>
            </div>
            <a href="products.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left me-1"></i> Back to Products
            </a>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-archive fa-2x" style="color: #7F1734;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count($archivedProducts); ?></h4>
                            <p class="text-muted mb-0">Archived Products</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-box fa-2x" style="color: #198754;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo array_sum(array_column($archivedProducts, 'stock')); ?></h4>
                            <p class="text-muted mb-0">Total Stock</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-dollar-sign fa-2x" style="color: #ffc107;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0">₱<?php echo number_format(array_sum(array_column($archivedProducts, 'price')), 2); ?></h4>
                            <p class="text-muted mb-0">Total Value</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Archived Products List -->
        <div class="row">
            <?php if (empty($archivedProducts)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fa fa-archive fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No archived products found</h4>
                        <p class="text-muted">All products are currently active</p>
                        <a href="products.php" class="btn" style="background-color: #7F1734; color: white; border: none;">
                            <i class="fa fa-arrow-left me-1"></i> Back to Products
                        </a>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($archivedProducts as $product): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="product-card">
                            <div class="text-center mb-3">
                                <?php if (!empty($product['image1'])): ?>
                                    <img src="<?php echo htmlspecialchars($product['image1']); ?>" 
                                         class="product-image" 
                                         onerror="this.src='uploads/default.png'">
                                <?php else: ?>
                                    <div class="product-image d-flex align-items-center justify-content-center bg-light">
                                        <i class="fa fa-image text-muted" style="font-size: 3rem;"></i>
                                    </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($product['name']); ?></h5>
                                <span class="badge archived-badge">Archived</span>
                            </div>
                            
                            <div class="text-muted small mb-3">
                                <div class="row">
                                    <div class="col-6"><strong>Cost:</strong> ₱<?php echo number_format($product['cost_price'], 2); ?></div>
                                    <div class="col-6"><strong>Price:</strong> ₱<?php echo number_format($product['price'], 2); ?></div>
                                    <div class="col-6"><strong>Stock:</strong> <?php echo htmlspecialchars($product['stock']); ?> <?php echo htmlspecialchars($product['uom_name']); ?></div>
                                    <div class="col-6"><strong>Category:</strong> <?php echo htmlspecialchars($product['category_name']); ?></div>
                                </div>
                                <hr class="my-2">
                                <div><strong>Brand:</strong> <?php echo htmlspecialchars($product['brand_name']); ?></div>
                                <div><strong>Supplier:</strong> <?php echo htmlspecialchars($product['supplier_name']); ?></div>
                                <?php if (!empty($product['expiration_date'])): ?>
                                    <div><strong>Expires:</strong> <?php echo date('M d, Y', strtotime($product['expiration_date'])); ?></div>
                                <?php endif; ?>
                                <div><strong>Archived:</strong> <?php echo date('M d, Y', strtotime($product['archived_date'])); ?></div>
                            </div>
                            
                            <div class="d-flex gap-2">
                                <a href="archived_products.php?unarchive=<?php echo $product['id']; ?>" 
                                   class="btn btn-sm flex-fill" 
                                   style="background-color: #198754; color: white; border: none;"
                                   onclick="return confirm('Are you sure you want to unarchive this product?')">
                                    <i class="fa fa-undo me-1"></i> Unarchive
                                </a>
                                <a href="archived_products.php?delete=<?php echo $product['id']; ?>" 
                                   class="btn btn-sm flex-fill" 
                                   style="background-color: #dc3545; color: white; border: none;"
                                   onclick="return confirm('Are you sure you want to permanently delete this product? This action cannot be undone!')">
                                    <i class="fa fa-trash me-1"></i> Delete
                                </a>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
