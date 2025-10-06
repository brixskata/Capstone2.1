<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Fetch categories from the database
$stmt = $pdo->query("SELECT category_id AS id, category_name AS name FROM categories");
$categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch brands from the database
$stmt = $pdo->query("SELECT id, name FROM brands WHERE is_archived = 0");
$brands = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Note: Suppliers will be set during restocking process

// Set UOM to Kilos only
$uoms = [['id' => 1, 'name' => 'Kilos']]; // Assuming Kilos has ID 1

// Handle form submission for adding product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        // Get form data
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $markup_value = (float)$_POST['markup_value'];
        $category_id = (int)$_POST['category_id'];
        $brand_id = (int)$_POST['brand_id'];
        $uom_id = 1; // Always set to Kilos

        // Validate required fields
        if (empty($name) || empty($description) || $markup_value < 0) {
            throw new Exception("Please fill in all required fields with valid values");
        }

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
                
                // Validate file type
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
                $fileType = $_FILES[$inputName]['type'];
                if (!in_array($fileType, $allowedTypes)) {
                    throw new Exception("Invalid file type for image $i. Only JPEG, PNG, and GIF files are allowed.");
                }
                
                if (move_uploaded_file($imageTmpName, $imagePath)) {
                    $imagePaths[$i] = $imagePath;
                }
            }
        }

        $pdo->beginTransaction();

        // Create product core (supplier will be set during restocking)
        $stmt = $pdo->prepare("INSERT INTO products (product_name, product_description, category_id, brand_id, supplier_id, uom_id) VALUES (?, ?, ?, ?, NULL, ?)");
        $stmt->execute([$name, $description, $category_id, $brand_id, $uom_id]);
        $newProductId = (int)$pdo->lastInsertId();

        // Pricing (cost price will be set during restocking)
        $stmt = $pdo->prepare("INSERT INTO product_pricing (product_id, cost_price, markup_percentage, selling_price, pricing_type) VALUES (?, 0, 0, ?, 'stored')");
        $stmt->execute([$newProductId, $markup_value]);

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

        $pdo->commit();

        logHistory($pdo, 'Product Added', "Added new product: $name (Markup Value: ₱$markup_value)", $_SESSION['username']);

        $_SESSION['success'] = "Product '$name' added successfully!";
        header("Location: products.php");
        exit;

    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error adding product: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Dashboard</title>
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

        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            color: var(--text-primary) !important;
        }
        
        .table-card .card-header {
            background: transparent;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-card .card-body {
            padding: 1.5rem;
        }

        .form-section {
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
        }
        
        .section-header {
            background: var(--bs-primary);
            color: white;
            padding: 1.5rem 2rem;
            border-radius: 15px;
            margin: -2rem -2rem 2rem -2rem;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
        }

        .section-header h5 {
            margin: 0;
            font-weight: 600;
            font-size: 1.2rem;
        }
        
        .preview-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 12px;
            border: 2px dashed #dee2e6;
        }
        
        .image-preview-container {
            position: relative;
            display: inline-block;
            margin: 10px;
        }
        
        .remove-image {
            position: absolute;
            top: -5px;
            right: -5px;
            background: #dc3545;
            color: white;
            border: none;
            border-radius: 50%;
            width: 25px;
            height: 25px;
            font-size: 12px;
            cursor: pointer;
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

        .btn-secondary-custom {
            background-color: #6c757d;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-secondary-custom:hover {
            background-color: #5a6268;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 12px rgba(108, 117, 125, 0.4);
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
                        <h2><i class="fas fa-plus-circle me-2"></i>Add New Product</h2>
                        <p class="mb-0 opacity-75">Create a new product for your inventory</p>
                    </div>
                    <a href="products.php" class="btn text-white fw-bold px-4 text-decoration-none" 
                       style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);">
                        <i class="fa fa-arrow-left me-2"></i>Back to Products
                    </a>
                </div>
            </div>

        <form action="add_product.php" method="POST" enctype="multipart/form-data" id="addProductForm">
            <!-- Product Information Card -->
            <div class="form-section">
                <div class="section-header">
                    <h5 class="mb-0"><i class="fa fa-plus-circle me-2"></i>Add Product</h5>
                </div>
                
                <!-- Product Name -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Product Name:</label>
                    </div>
                    <div class="col-md-9">
                        <input type="text" name="name" class="form-control" required 
                               placeholder="Enter product name" maxlength="255">
                    </div>
                </div>
                
                <!-- Category -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Category:</label>
                    </div>
                    <div class="col-md-9">
                        <select name="category_id" class="form-select" required>
                            <option value="">Select Category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Brand -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Brand:</label>
                    </div>
                    <div class="col-md-9">
                        <select name="brand_id" class="form-select" required>
                            <option value="">Select Brand</option>
                            <?php foreach ($brands as $brand): ?>
                                <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Note: Supplier will be set during restocking process -->
                
                <!-- Base UOM (Fixed to Kilos) -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Base UOM:</label>
                    </div>
                    <div class="col-md-9">
                        <input type="text" class="form-control" value="Kilos" readonly>
                        <input type="hidden" name="uom_id" value="1">
                        <div class="form-text">All products are measured in Kilos</div>
                    </div>
                </div>
                
                <!-- Markup Value -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Markup Value:</label>
                    </div>
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" name="markup_value" class="form-control" 
                                   placeholder="0.00" min="0" required>
                            <span class="input-group-text">per kilo</span>
                        </div>
                        <div class="form-text">Additional profit margin per kilo (cost price will be set during restocking)</div>
                    </div>
                </div>
                
                <!-- Note: All products are perishable by default -->
                
                <!-- Divider -->
                <hr class="my-4">
                
                
                <!-- Product Description -->
                <div class="mb-3">
                    <label class="form-label fw-bold">Product Description:</label>
                    <textarea name="description" class="form-control" rows="3" required 
                              placeholder="Describe the product features, benefits, and specifications"></textarea>
                </div>
            </div>

            <!-- Product Images Section -->
            <div class="form-section">
                <div class="section-header">
                    <h5 class="mb-0"><i class="fa fa-images me-2"></i>Product Images</h5>
                </div>
                
                <div class="row">
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Primary Image</label>
                            <input type="file" name="image1" accept="image/*" class="form-control" 
                                   onchange="previewImage(this, 'preview1')">
                            <div class="mt-2" id="preview1"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Secondary Image</label>
                            <input type="file" name="image2" accept="image/*" class="form-control" 
                                   onchange="previewImage(this, 'preview2')">
                            <div class="mt-2" id="preview2"></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="mb-3">
                            <label class="form-label">Third Image</label>
                            <input type="file" name="image3" accept="image/*" class="form-control" 
                                   onchange="previewImage(this, 'preview3')">
                            <div class="mt-2" id="preview3"></div>
                        </div>
                    </div>
                </div>
                
                <div class="alert alert-warning">
                    <i class="fa fa-exclamation-triangle me-2"></i>
                    <strong>Note:</strong> Images are optional. Only JPEG, PNG, and GIF formats are supported. Maximum file size: 5MB per image.
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="form-section">
                <div class="d-flex gap-3 justify-content-end">
                    <a href="products.php" class="btn-secondary-custom text-decoration-none">
                        <i class="fa fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" name="add_product" class="btn text-white fw-bold px-4" 
                            style="background-color: #7F1734; border-radius: 8px;">
                        <i class="fa fa-save me-2"></i>Add Product
                    </button>
                </div>
            </div>
        </form>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // No additional setup needed for simplified pricing
        });

        // Image preview function
        function previewImage(input, previewId) {
            const preview = document.getElementById(previewId);
            preview.innerHTML = '';
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    const imageContainer = document.createElement('div');
                    imageContainer.className = 'image-preview-container';
                    imageContainer.innerHTML = `
                        <img src="${e.target.result}" class="preview-image" alt="Preview">
                        <button type="button" class="remove-image" onclick="removeImage('${input.name}', '${previewId}')">
                            <i class="fa fa-times"></i>
                        </button>
                    `;
                    preview.appendChild(imageContainer);
                };
                reader.readAsDataURL(input.files[0]);
            }
        }

        // Remove image function
        function removeImage(inputName, previewId) {
            document.querySelector(`input[name="${inputName}"]`).value = '';
            document.getElementById(previewId).innerHTML = '';
        }

        // Form validation
        document.getElementById('addProductForm').addEventListener('submit', function(e) {
            const requiredFields = ['name', 'description', 'category_id', 'brand_id', 'markup_value'];
            let isValid = true;
            
            // Check basic required fields
            requiredFields.forEach(fieldName => {
                const field = document.querySelector(`[name="${fieldName}"]`);
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            // Check markup value
            const markupValue = parseFloat(document.querySelector('[name="markup_value"]').value) || 0;
            
            if (markupValue < 0) {
                document.querySelector('[name="markup_value"]').classList.add('is-invalid');
                isValid = false;
            } else {
                document.querySelector('[name="markup_value"]').classList.remove('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields with valid values.');
            }
        });
    </script>
</body>
</html>
