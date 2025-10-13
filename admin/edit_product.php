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

// Fetch categories, suppliers, and UOM for dropdowns
// Note: Brands are set during restocking, not during product editing
$categories = $pdo->query("SELECT category_id as id, category_name as name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $pdo->query("SELECT supplier_id as id, name FROM suppliers WHERE is_archive = 0")->fetchAll(PDO::FETCH_ASSOC);
$uoms = $pdo->query("SELECT uom_id as id, name FROM uom WHERE is_archive = 0")->fetchAll(PDO::FETCH_ASSOC);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form values
    $id = $_POST['id'];
    $name = $_POST['name'];
    $description = $_POST['description'];
    $cost_price = $_POST['cost_price'];
    $markup_percentage = $_POST['markup_percentage'];
    $markup_price = $cost_price * ($markup_percentage / 100); // Calculate markup amount from percentage
    $stock = $_POST['stock'];
    $category_id = $_POST['category_id'];
    // Note: brand_id is not editable here, brands are managed through restocking
    $supplier_id = $_POST['supplier_id'];
    $uom_id = $_POST['uom_id'];
    $expiration_date = $_POST['expiration_date'];
    $is_new = isset($_POST['is_new']) ? 1 : 0;
    $is_hot = isset($_POST['is_hot']) ? 1 : 0;

    // Prepare variables
    $imagePath = null;

    // Check if a new image is uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $imageTmpName = $_FILES['image']['tmp_name'];
        $imageName = $_FILES['image']['name'];
        $imagePath = 'uploads/' . uniqid() . '-' . $imageName;
        move_uploaded_file($imageTmpName, $imagePath);

        // Update product with new image using normalized structure
        $pdo->beginTransaction();
        try {
            // Update main product table
            $stmt = $pdo->prepare("UPDATE products 
                SET product_name = ?, product_description = ?, category_id = ?, supplier_id = ?, uom_id = ?
                WHERE product_id = ?");
            $stmt->execute([$name, $description, $category_id, $supplier_id, $uom_id, $id]);
            
            // Update pricing table
            $stmt = $pdo->prepare("UPDATE product_pricing 
                SET cost_price = ?, markup_price = ?
                WHERE product_id = ?");
            $stmt->execute([$cost_price, $markup_price, $id]);
            
            // Update stock table
            $stmt = $pdo->prepare("UPDATE product_stock 
                SET current_stock = ?, expiration_date = ?
                WHERE product_id = ?");
            $stmt->execute([$stock, $expiration_date, $id]);
            
            // Update primary image
            $stmt = $pdo->prepare("UPDATE product_images 
                SET image_url = ?
                WHERE product_id = ? AND is_primary = 1");
            $stmt->execute([$imagePath, $id]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    } else {
        // Update product without changing image using normalized structure
        $pdo->beginTransaction();
        try {
            // Update main product table
            $stmt = $pdo->prepare("UPDATE products 
                SET product_name = ?, product_description = ?, category_id = ?, supplier_id = ?, uom_id = ?
                WHERE product_id = ?");
            $stmt->execute([$name, $description, $category_id, $supplier_id, $uom_id, $id]);
            
            // Update pricing table
            $stmt = $pdo->prepare("UPDATE product_pricing 
                SET cost_price = ?, markup_price = ?
                WHERE product_id = ?");
            $stmt->execute([$cost_price, $markup_price, $id]);
            
            // Update stock table
            $stmt = $pdo->prepare("UPDATE product_stock 
                SET current_stock = ?, expiration_date = ?
                WHERE product_id = ?");
            $stmt->execute([$stock, $expiration_date, $id]);
            
            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            throw $e;
        }
    }

    // Log the edit action
    logHistory($pdo, 'Edited Product', 'Product ID: ' . $id . ', Name: ' . $name . ', Cost: ₱' . $cost_price . ', Markup: ' . $markup_percentage . '%, Markup Amount: ₱' . $markup_price, $_SESSION['username']);

    header("Location: products.php"); // Redirect back to product list
    exit;
}

// Get product data for editing using normalized structure
if (isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    $stmt = $pdo->prepare("
        SELECT p.product_id, p.product_name, p.product_description, p.category_id, p.brand_id, p.supplier_id, p.uom_id,
               pp.cost_price, pp.markup_price,
               ps.current_stock, ps.expiration_date,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) as image
        FROM products p
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        WHERE p.product_id = ?
    ");
    $stmt->execute([$productId]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$product) {
        header("Location: products.php");
        exit;
    }
} else {
    header("Location: products.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Product - Admin</title>
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

        /* Form Card */
        .form-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            padding: 2rem;
        }

        .form-card .form-control,
        .form-card .form-select {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem 1rem;
            transition: all 0.2s ease;
        }

        .form-card .form-control:focus,
        .form-card .form-select:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }

        .form-card .form-label {
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.5rem;
        }

        .btn-primary {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background-color: #5a0f25;
            border-color: #5a0f25;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(127, 23, 52, 0.3);
        }

        .btn-secondary {
            background-color: var(--bs-secondary);
            border-color: var(--bs-secondary);
            border-radius: 10px;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: #5a6268;
            border-color: #5a6268;
            transform: translateY(-1px);
        }

        .current-image {
            background: #f8f9fa;
            border: 2px dashed #dee2e6;
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            margin-top: 0.5rem;
        }

        .current-image img {
            max-width: 200px;
            max-height: 150px;
            border-radius: 8px;
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
            
            .form-card {
                padding: 1.5rem;
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
            <div class="page-header">
                <h2><i class="fas fa-edit me-2"></i>Edit Product</h2>
            </div>

            <!-- Edit Form -->
            <div class="form-card">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $product['product_id'] ?>">
                    
                    <div class="row g-4">
                        <!-- Product Name -->
                        <div class="col-12">
                            <label for="name" class="form-label">Product Name</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($product['product_name']) ?>" 
                                   class="form-control" required>
                        </div>

                        <!-- Description -->
                        <div class="col-12">
                            <label for="description" class="form-label">Description</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="form-control" required><?= htmlspecialchars($product['product_description']) ?></textarea>
                        </div>

                        <!-- Cost Price and Markup -->
                        <div class="col-md-6">
                            <label for="cost_price" class="form-label">Cost Price (₱)</label>
                            <input type="number" step="0.01" name="cost_price" id="cost_price" value="<?= $product['cost_price'] ?? 0 ?>" 
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label for="markup_percentage" class="form-label">Markup (%)</label>
                            <input type="number" step="0.01" name="markup_percentage" id="markup_percentage" value="<?= $product['cost_price'] > 0 ? round(($product['markup_price'] / $product['cost_price']) * 100, 2) : 0 ?>" 
                                   class="form-control" required>
                        </div>

                        <!-- Stock and Expiration Date -->
                        <div class="col-md-6">
                            <label for="stock" class="form-label">Stock</label>
                            <input type="number" name="stock" id="stock" value="<?= $product['current_stock'] ?? 0 ?>" 
                                   class="form-control" required>
                        </div>

                        <div class="col-md-6">
                            <label for="expiration_date" class="form-label">Expiration Date</label>
                            <input type="date" name="expiration_date" id="expiration_date" value="<?= $product['expiration_date'] ?? '' ?>" 
                                   class="form-control">
                        </div>

                        <!-- Category -->
                        <div class="col-md-6">
                            <label for="category_id" class="form-label">Category</label>
                            <select name="category_id" id="category_id" class="form-select" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= ($product['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- Note: Brand is managed through restocking, not here -->
                        <div class="col-md-6">
                            <div class="alert alert-info mb-0">
                                <i class="fa fa-info-circle me-2"></i>
                                <small>Brand is set during restocking process</small>
                            </div>
                        </div>

                        <!-- Supplier and UOM -->
                        <div class="col-md-6">
                            <label for="supplier_id" class="form-label">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="form-select" required>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" <?= ($product['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label for="uom_id" class="form-label">Unit of Measurement</label>
                            <select name="uom_id" id="uom_id" class="form-select" required>
                                <?php foreach ($uoms as $uom): ?>
                                    <option value="<?= $uom['id'] ?>" <?= ($product['uom_id'] == $uom['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($uom['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Image Upload -->
                        <div class="col-12">
                            <label for="image" class="form-label">Product Image</label>
                            <input type="file" name="image" id="image" accept="image/*" 
                                   class="form-control">
                            <?php if ($product['image']): ?>
                                <div class="current-image">
                                    <p class="text-muted mb-2">Current image:</p>
                                    <img src="<?= htmlspecialchars($product['image']) ?>" alt="Current product image" class="img-fluid">
                                    <p class="text-muted mt-2 small"><?= htmlspecialchars($product['image']) ?></p>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Info Note -->
                        <div class="col-12">
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle me-2"></i>
                                Additional product flags can be added to the products table if needed.
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="d-flex justify-content-end gap-3 mt-4">
                        <a href="products.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
