<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle brand creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_brand'])) {
    $brand_name = trim($_POST['brand_name']);
    
    if (!empty($brand_name)) {
        try {
            // Check if brand already exists
            $stmt = $pdo->prepare("SELECT 1 FROM brands WHERE name = ? AND is_archived = 0");
            $stmt->execute([$brand_name]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Brand '$brand_name' already exists";
            } else {
                // Create new brand
                $stmt = $pdo->prepare("INSERT INTO brands (name, is_archived) VALUES (?, 0)");
                $stmt->execute([$brand_name]);
                
                $_SESSION['success'] = "Brand '$brand_name' created successfully";
                logHistory($pdo, 'Brand Created', "Created new brand: $brand_name", $_SESSION['username']);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating brand: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Brand name is required";
    }
    
    header("Location: manage_brands.php");
    exit;
}

// Handle brand deletion
if (isset($_GET['delete'])) {
    $brand_id = (int)$_GET['delete'];
    
    try {
        // Check if brand is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE brand_id = ?");
        $stmt->execute([$brand_id]);
        $productCount = $stmt->fetchColumn();
        
        if ($productCount > 0) {
            $_SESSION['error'] = "Cannot delete brand: $productCount product(s) are using this brand";
        } else {
            // Get brand name for logging
            $stmt = $pdo->prepare("SELECT name FROM brands WHERE id = ?");
            $stmt->execute([$brand_id]);
            $brand = $stmt->fetch();
            
            // Delete brand
            $stmt = $pdo->prepare("DELETE FROM brands WHERE id = ?");
            $stmt->execute([$brand_id]);
            
            $_SESSION['success'] = "Brand '{$brand['name']}' deleted successfully";
            logHistory($pdo, 'Brand Deleted', "Deleted brand: {$brand['name']}", $_SESSION['username']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting brand: " . $e->getMessage();
    }
    
    header("Location: manage_brands.php");
    exit;
}

// Fetch all brands with product counts
$stmt = $pdo->query("
    SELECT b.id, b.name, b.is_archived,
           COUNT(p.product_id) as product_count
    FROM brands b
    LEFT JOIN products p ON b.id = p.brand_id
    WHERE b.is_archived = 0
    GROUP BY b.id, b.name, b.is_archived
    ORDER BY b.name
");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Brands - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .brand-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }
        
        .brand-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
        }
        
        .stats-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            border-left: 4px solid #7F1734;
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
                    <i class="fa fa-trademark me-3" style="color: #7F1734;"></i>Manage Brands
                </h1>
                <p class="text-muted">Create and manage product brands</p>
            </div>
            <button class="btn" data-bs-toggle="modal" data-bs-target="#addBrandModal" style="background-color: #7F1734; color: white; border: none;">
                <i class="fa fa-plus me-1"></i> Add Brand
            </button>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-trademark fa-2x" style="color: #7F1734;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count($brands); ?></h4>
                            <p class="text-muted mb-0">Total Brands</p>
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
                            <h4 class="mb-0"><?php echo array_sum(array_column($brands, 'product_count')); ?></h4>
                            <p class="text-muted mb-0">Total Products</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-chart-bar fa-2x" style="color: #ffc107;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count(array_filter($brands, function($brand) { return $brand['product_count'] > 0; })); ?></h4>
                            <p class="text-muted mb-0">Active Brands</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Brands List -->
        <div class="row">
            <?php if (empty($brands)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fa fa-trademark fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No brands found</h4>
                        <p class="text-muted">Start by creating your first brand</p>
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#addBrandModal" style="background-color: #7F1734; color: white; border: none;">
                            <i class="fa fa-plus me-1"></i> Add First Brand
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($brands as $brand): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="brand-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($brand['name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item text-danger" href="manage_brands.php?delete=<?php echo $brand['id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this brand? This action cannot be undone.')">
                                                <i class="fa fa-trash me-2"></i>Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fa fa-box me-2 text-muted"></i>
                                    <span class="text-muted"><?php echo $brand['product_count']; ?> product(s)</span>
                                </div>
                                
                                <?php if ($brand['product_count'] > 0): ?>
                                    <span class="badge bg-success">Active</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary">Empty</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>

    <!-- Add Brand Modal -->
    <div class="modal fade" id="addBrandModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="manage_brands.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Brand</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Brand Name</label>
                            <input type="text" class="form-control" name="brand_name" required 
                                   placeholder="e.g., Coca-Cola, Nestle, Unilever" 
                                   maxlength="100">
                            <small class="text-muted">Enter the brand name</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_brand" class="btn" style="background-color: #7F1734; color: white; border: none;">Add Brand</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
