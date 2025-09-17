<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle category creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_category'])) {
    $category_name = trim($_POST['category_name']);
    
    if (!empty($category_name)) {
        try {
            // Check if category already exists
            $stmt = $pdo->prepare("SELECT 1 FROM categories WHERE category_name = ?");
            $stmt->execute([$category_name]);
            if ($stmt->fetch()) {
                $_SESSION['error'] = "Category '$category_name' already exists";
            } else {
                // Create new category
                $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
                $stmt->execute([$category_name]);
                
                $_SESSION['success'] = "Category '$category_name' created successfully";
                logHistory($pdo, 'Category Created', "Created new category: $category_name", $_SESSION['username']);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating category: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Category name is required";
    }
    
    header("Location: manage_categories.php");
    exit;
}

// Handle category deletion
if (isset($_GET['delete'])) {
    $category_id = (int)$_GET['delete'];
    
    try {
        // Check if category is in use
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ?");
        $stmt->execute([$category_id]);
        $productCount = $stmt->fetchColumn();
        
        if ($productCount > 0) {
            $_SESSION['error'] = "Cannot delete category: $productCount product(s) are using this category";
        } else {
            // Get category name for logging
            $stmt = $pdo->prepare("SELECT category_name FROM categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            $category = $stmt->fetch();
            
            // Delete category
            $stmt = $pdo->prepare("DELETE FROM categories WHERE category_id = ?");
            $stmt->execute([$category_id]);
            
            $_SESSION['success'] = "Category '{$category['category_name']}' deleted successfully";
            logHistory($pdo, 'Category Deleted', "Deleted category: {$category['category_name']}", $_SESSION['username']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting category: " . $e->getMessage();
    }
    
    header("Location: manage_categories.php");
    exit;
}

// Fetch all categories with product counts
$stmt = $pdo->query("
    SELECT c.category_id, c.category_name, 
           COUNT(p.product_id) as product_count
    FROM categories c
    LEFT JOIN products p ON c.category_id = p.category_id
    GROUP BY c.category_id, c.category_name
    ORDER BY c.category_name
");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .category-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
            transition: transform 0.2s ease;
        }
        
        .category-card:hover {
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
                    <i class="fa fa-tags me-3" style="color: #7F1734;"></i>Manage Categories
                </h1>
                <p class="text-muted">Create and manage product categories</p>
            </div>
            <button class="btn" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background-color: #7F1734; color: white; border: none;">
                <i class="fa fa-plus me-1"></i> Add Category
            </button>
        </div>

        <!-- Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-card">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <i class="fa fa-tags fa-2x" style="color: #7F1734;"></i>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h4 class="mb-0"><?php echo count($categories); ?></h4>
                            <p class="text-muted mb-0">Total Categories</p>
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
                            <h4 class="mb-0"><?php echo array_sum(array_column($categories, 'product_count')); ?></h4>
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
                            <h4 class="mb-0"><?php echo count(array_filter($categories, function($cat) { return $cat['product_count'] > 0; })); ?></h4>
                            <p class="text-muted mb-0">Active Categories</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories List -->
        <div class="row">
            <?php if (empty($categories)): ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fa fa-tags fa-3x text-muted mb-3"></i>
                        <h4 class="text-muted">No categories found</h4>
                        <p class="text-muted">Start by creating your first category</p>
                        <button class="btn" data-bs-toggle="modal" data-bs-target="#addCategoryModal" style="background-color: #7F1734; color: white; border: none;">
                            <i class="fa fa-plus me-1"></i> Add First Category
                        </button>
                    </div>
                </div>
            <?php else: ?>
                <?php foreach ($categories as $category): ?>
                    <div class="col-lg-4 col-md-6 mb-4">
                        <div class="category-card">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <h5 class="fw-bold mb-0"><?php echo htmlspecialchars($category['category_name']); ?></h5>
                                <div class="dropdown">
                                    <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                                        <i class="fa fa-ellipsis-v"></i>
                                    </button>
                                    <ul class="dropdown-menu">
                                        <li>
                                            <a class="dropdown-item text-danger" href="manage_categories.php?delete=<?php echo $category['category_id']; ?>" 
                                               onclick="return confirm('Are you sure you want to delete this category? This action cannot be undone.')">
                                                <i class="fa fa-trash me-2"></i>Delete
                                            </a>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="fa fa-box me-2 text-muted"></i>
                                    <span class="text-muted"><?php echo $category['product_count']; ?> product(s)</span>
                                </div>
                                
                                <?php if ($category['product_count'] > 0): ?>
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

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="manage_categories.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Add New Category</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Category Name</label>
                            <input type="text" class="form-control" name="category_name" required 
                                   placeholder="e.g., Meat, Vegetables, Dairy" 
                                   maxlength="100">
                            <small class="text-muted">Enter a descriptive name for the category</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_category" class="btn" style="background-color: #7F1734; color: white; border: none;">Add Category</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
