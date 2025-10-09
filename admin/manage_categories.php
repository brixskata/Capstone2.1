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
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories - Admin Dashboard</title>
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

        .category-card {
            background: white;
            border-radius: 20px;
            padding: 1.5rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
            height: 100%;
        }
        
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.12);
        }

        .btn-primary-custom {
            background-color: transparent;
            color: #E8B4C8;
            border: 2px solid #E8B4C8;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary-custom:hover {
            background-color: #E8B4C8;
            color: white;
            border-color: #E8B4C8;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(232, 180, 200, 0.4);
        }

        .btn-danger-custom {
            background-color: #dc3545;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.5rem 1rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-danger-custom:hover {
            background-color: #b02a37;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
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
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-tags me-2"></i>Manage Categories</h2>
                        <p class="mb-0 opacity-75">Create and manage product categories</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" 
                            style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                            data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                        <i class="fa fa-plus me-2"></i>Add Category
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-tags"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($categories); ?></h3>
                            <p class="card-label">Total Categories</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo array_sum(array_column($categories, 'product_count')); ?></h3>
                            <p class="card-label">Total Products</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-chart-bar"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count(array_filter($categories, function($cat) { return $cat['product_count'] > 0; })); ?></h3>
                            <p class="card-label">Active Categories</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Categories List -->
            <div class="row g-4">
                <?php if (empty($categories)): ?>
                    <div class="col-12">
                        <div class="text-center py-5">
                            <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                            <h4 class="text-muted">No categories found</h4>
                            <p class="text-muted">Start by creating your first category</p>
                            <button class="btn text-white fw-bold px-4" 
                                    style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                                    data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                                <i class="fa fa-plus me-2"></i>Add First Category
                            </button>
                        </div>
                    </div>
                <?php else: ?>
                    <?php foreach ($categories as $category): ?>
                        <div class="col-lg-4 col-md-6">
                            <div class="category-card">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <h5 class="fw-bold mb-0 text-dark"><?php echo htmlspecialchars($category['category_name']); ?></h5>
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
                        <button type="submit" name="create_category" class="btn text-white fw-bold px-4" 
                                style="background-color: #7F1734; border-radius: 8px;">Add Category</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
