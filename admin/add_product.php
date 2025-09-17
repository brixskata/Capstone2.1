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

// Fetch UOM from the database
$stmt = $pdo->query("SELECT uom_id AS id, name FROM uom");
$uoms = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission for adding product
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_product'])) {
    try {
        // Get form data
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        $cost_price = (float)$_POST['cost_price'];
        $markup_percentage = (float)$_POST['markup_percentage'];
        $selling_price = $cost_price * (1 + ($markup_percentage / 100));
        $category_id = (int)$_POST['category_id'];
        $brand_id = (int)$_POST['brand_id'];
        $uom_id = (int)$_POST['uom_id'];

        // Validate required fields
        if (empty($name) || empty($description) || $cost_price <= 0 || $markup_percentage < 0) {
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

        $pdo->commit();

        logHistory($pdo, 'Product Added', "Added new product: $name (Cost: ₱$cost_price, Markup: $markup_percentage%, Selling Price: ₱$selling_price)", $_SESSION['username']);

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Product - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .form-section {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 25px;
        }
        
        .section-header {
            background: #7F1734;
            color: white;
            padding: 15px 20px;
            border-radius: 8px;
            margin: -25px -25px 25px -25px;
        }
        
        .preview-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 8px;
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
                    <i class="fa fa-plus-circle me-3" style="color: #7F1734;"></i>Add New Product
                </h1>
                <p class="text-muted">Create a new product for your inventory</p>
            </div>
            <a href="products.php" class="btn btn-secondary">
                <i class="fa fa-arrow-left me-1"></i> Back to Products
            </a>
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
                
                <!-- Base UOM -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Base UOM:</label>
                    </div>
                    <div class="col-md-9">
                        <select name="uom_id" class="form-select" required>
                            <option value="">Select UOM</option>
                            <?php foreach ($uoms as $uom): ?>
                                <option value="<?= $uom['id'] ?>"><?= htmlspecialchars($uom['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                
                <!-- Selling Price -->
                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label fw-bold">Selling Price:</label>
                    </div>
                    <div class="col-md-9">
                        <div class="input-group">
                            <span class="input-group-text">₱</span>
                            <input type="number" step="0.01" class="form-control" id="sellingPrice" readonly 
                                   placeholder="Auto-calculated">
                            <span class="input-group-text">per <span id="uomDisplay">unit</span></span>
                        </div>
                    </div>
                </div>
                
                <!-- Note: All products are perishable by default -->
                
                <!-- Divider -->
                <hr class="my-4">
                
                <!-- Pricing Details (Hidden by default, shown when needed) -->
                <div id="pricingDetails" style="display: none;">
                    <h6 class="mb-3 text-muted">Pricing Details</h6>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Cost Price (₱):</label>
                        </div>
                        <div class="col-md-9">
                            <input type="number" step="0.01" name="cost_price" class="form-control" 
                                   placeholder="0.00" min="0" id="costPriceInput">
                        </div>
                    </div>
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">Markup Percentage (%):</label>
                        </div>
                        <div class="col-md-9">
                            <input type="number" step="0.01" name="markup_percentage" class="form-control" 
                                   placeholder="0.00" min="0" id="markupInput" value="20">
                        </div>
                    </div>
                </div>
                
                <!-- Toggle Pricing Details -->
                <div class="text-center mb-3">
                    <button type="button" class="btn btn-outline-secondary btn-sm" id="togglePricing">
                        <i class="fa fa-cog me-1"></i> Configure Pricing Details
                    </button>
                </div>
                
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
                    <a href="products.php" class="btn btn-secondary">
                        <i class="fa fa-times me-1"></i> Cancel
                    </a>
                    <button type="submit" name="add_product" class="btn" style="background-color: #7F1734; color: white; border: none;">
                        <i class="fa fa-save me-1"></i> Add Product
                    </button>
                </div>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        // Auto-calculate selling price
        function calculateSellingPrice() {
            const costPrice = parseFloat(document.getElementById('costPriceInput').value) || 0;
            const markup = parseFloat(document.getElementById('markupInput').value) || 0;
            const sellingPrice = costPrice * (1 + (markup / 100));
            document.getElementById('sellingPrice').value = sellingPrice.toFixed(2);
        }

        // Update UOM display when UOM changes
        function updateUOMDisplay() {
            const uomSelect = document.querySelector('select[name="uom_id"]');
            const uomDisplay = document.getElementById('uomDisplay');
            const selectedOption = uomSelect.options[uomSelect.selectedIndex];
            uomDisplay.textContent = selectedOption.text || 'unit';
        }

        // Toggle pricing details visibility
        function togglePricingDetails() {
            const pricingDetails = document.getElementById('pricingDetails');
            const toggleBtn = document.getElementById('togglePricing');
            
            if (pricingDetails.style.display === 'none') {
                pricingDetails.style.display = 'block';
                toggleBtn.innerHTML = '<i class="fa fa-eye-slash me-1"></i> Hide Pricing Details';
            } else {
                pricingDetails.style.display = 'none';
                toggleBtn.innerHTML = '<i class="fa fa-cog me-1"></i> Configure Pricing Details';
            }
        }

        // Add event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Price calculation
            document.getElementById('costPriceInput').addEventListener('input', calculateSellingPrice);
            document.getElementById('markupInput').addEventListener('input', calculateSellingPrice);
            
            // UOM display update
            document.querySelector('select[name="uom_id"]').addEventListener('change', updateUOMDisplay);
            
            // Toggle pricing details
            document.getElementById('togglePricing').addEventListener('click', togglePricingDetails);
            
            // Set default values
            updateUOMDisplay();
            calculateSellingPrice();
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
            const requiredFields = ['name', 'description', 'category_id', 'brand_id', 'uom_id'];
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
            
            // Check if pricing details are configured
            const costPrice = parseFloat(document.getElementById('costPriceInput').value) || 0;
            const markup = parseFloat(document.getElementById('markupInput').value) || 0;
            
            if (costPrice <= 0) {
                document.getElementById('costPriceInput').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('costPriceInput').classList.remove('is-invalid');
            }
            
            if (markup < 0) {
                document.getElementById('markupInput').classList.add('is-invalid');
                isValid = false;
            } else {
                document.getElementById('markupInput').classList.remove('is-invalid');
            }
            
            if (!isValid) {
                e.preventDefault();
                alert('Please fill in all required fields and configure pricing details.');
            }
        });
    </script>
</body>
</html>
