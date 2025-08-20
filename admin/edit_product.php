<?php
include 'db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Fetch categories, brands, suppliers, and UOM for dropdowns
$categories = $pdo->query("SELECT id, name FROM categories")->fetchAll(PDO::FETCH_ASSOC);
$brands = $pdo->query("SELECT id, name FROM brands WHERE is_archived = 0")->fetchAll(PDO::FETCH_ASSOC);
$suppliers = $pdo->query("SELECT id, name FROM suppliers WHERE is_archived = 0")->fetchAll(PDO::FETCH_ASSOC);
$uoms = $pdo->query("SELECT id, name FROM units_of_measurement WHERE is_archived = 0")->fetchAll(PDO::FETCH_ASSOC);

// Check if form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form values
    $id = $_POST['id'];
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

    // Prepare variables
    $imagePath = null;

    // Check if a new image is uploaded
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $imageTmpName = $_FILES['image']['tmp_name'];
        $imageName = $_FILES['image']['name'];
        $imagePath = 'uploads/' . uniqid() . '-' . $imageName;
        move_uploaded_file($imageTmpName, $imagePath);

        // Update product with new image
        $stmt = $pdo->prepare("UPDATE products 
            SET name = ?, description = ?, cost_price = ?, markup_percentage = ?, price = ?, stock = ?, 
                category_id = ?, brand_id = ?, supplier_id = ?, uom_id = ?, expiration_date = ?, 
                image = ?, is_new = ?, is_hot = ?
            WHERE id = ?");
        $stmt->execute([$name, $description, $cost_price, $markup_percentage, $selling_price, $stock, 
                       $category_id, $brand_id, $supplier_id, $uom_id, $expiration_date, 
                       $imagePath, $is_new, $is_hot, $id]);
    } else {
        // Update product without changing image
        $stmt = $pdo->prepare("UPDATE products 
            SET name = ?, description = ?, cost_price = ?, markup_percentage = ?, price = ?, stock = ?, 
                category_id = ?, brand_id = ?, supplier_id = ?, uom_id = ?, expiration_date = ?, 
                is_new = ?, is_hot = ?
            WHERE id = ?");
        $stmt->execute([$name, $description, $cost_price, $markup_percentage, $selling_price, $stock, 
                       $category_id, $brand_id, $supplier_id, $uom_id, $expiration_date, 
                       $is_new, $is_hot, $id]);
    }

    // Log the edit action
    logHistory($pdo, 'Edited Product', 'Product ID: ' . $id . ', Name: ' . $name . ', Cost: ₱' . $cost_price . ', Markup: ' . $markup_percentage . '%, Selling Price: ₱' . $selling_price, $_SESSION['username']);

    header("Location: products.php"); // Redirect back to product list
    exit;
}

// Get product data for editing
if (isset($_GET['id'])) {
    $productId = intval($_GET['id']);
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
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
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>body { font-family: 'Poppins', 'Arial', sans-serif; }</style>
</head>
<body class="bg-gradient-to-br from-gray-900 via-gray-950 to-gray-900 min-h-screen">
    <div class="container mx-auto px-4 py-8">
        <div class="max-w-4xl mx-auto">
            <!-- Header -->
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-cyan-400 via-blue-400 to-pink-400">
                    <i class="fa fa-edit"></i> Edit Product
                </h1>
                <a href="products.php" class="rounded-lg bg-gradient-to-r from-gray-600 to-gray-700 text-white font-bold px-6 py-2 hover:from-gray-700 hover:to-gray-600 transition">
                    <i class="fa fa-arrow-left"></i> Back to Products
                </a>
            </div>

            <!-- Edit Form -->
            <div class="bg-gradient-to-br from-gray-800 to-gray-900 rounded-2xl p-8 shadow-lg">
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="id" value="<?= $product['id'] ?>">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Product Name -->
                        <div class="md:col-span-2">
                            <label for="name" class="block text-cyan-100 font-bold mb-2">Product Name</label>
                            <input type="text" name="name" id="name" value="<?= htmlspecialchars($product['name']) ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                        </div>

                        <!-- Description -->
                        <div class="md:col-span-2">
                            <label for="description" class="block text-cyan-100 font-bold mb-2">Description</label>
                            <textarea name="description" id="description" rows="3" 
                                      class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required><?= htmlspecialchars($product['description']) ?></textarea>
                        </div>

                        <!-- Cost Price and Markup -->
                        <div>
                            <label for="cost_price" class="block text-cyan-100 font-bold mb-2">Cost Price (₱)</label>
                            <input type="number" step="0.01" name="cost_price" id="cost_price" value="<?= $product['cost_price'] ?? 0 ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                        </div>

                        <div>
                            <label for="markup_percentage" class="block text-cyan-100 font-bold mb-2">Markup (%)</label>
                            <input type="number" step="0.01" name="markup_percentage" id="markup_percentage" value="<?= $product['markup_percentage'] ?? 0 ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                        </div>

                        <!-- Stock and Expiration Date -->
                        <div>
                            <label for="stock" class="block text-cyan-100 font-bold mb-2">Stock</label>
                            <input type="number" name="stock" id="stock" value="<?= $product['stock'] ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                        </div>

                        <div>
                            <label for="expiration_date" class="block text-cyan-100 font-bold mb-2">Expiration Date</label>
                            <input type="date" name="expiration_date" id="expiration_date" value="<?= $product['expiration_date'] ?>" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
                        </div>

                        <!-- Category and Brand -->
                        <div>
                            <label for="category_id" class="block text-cyan-100 font-bold mb-2">Category</label>
                            <select name="category_id" id="category_id" class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= ($product['category_id'] == $category['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="brand_id" class="block text-cyan-100 font-bold mb-2">Brand</label>
                            <select name="brand_id" id="brand_id" class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                                <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" <?= ($product['brand_id'] == $brand['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Supplier and UOM -->
                        <div>
                            <label for="supplier_id" class="block text-cyan-100 font-bold mb-2">Supplier</label>
                            <select name="supplier_id" id="supplier_id" class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                                <?php foreach ($suppliers as $supplier): ?>
                                    <option value="<?= $supplier['id'] ?>" <?= ($product['supplier_id'] == $supplier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($supplier['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label for="uom_id" class="block text-cyan-100 font-bold mb-2">Unit of Measurement</label>
                            <select name="uom_id" id="uom_id" class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400" required>
                                <?php foreach ($uoms as $uom): ?>
                                    <option value="<?= $uom['id'] ?>" <?= ($product['uom_id'] == $uom['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($uom['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Image Upload -->
                        <div class="md:col-span-2">
                            <label for="image" class="block text-cyan-100 font-bold mb-2">Product Image</label>
                            <input type="file" name="image" id="image" accept="image/*" 
                                   class="rounded-lg bg-gray-700 text-cyan-100 px-4 py-2 w-full focus:outline-none focus:ring-2 focus:ring-cyan-400">
                            <?php if ($product['image']): ?>
                                <p class="text-sm text-cyan-300 mt-1">Current image: <?= htmlspecialchars($product['image']) ?></p>
                            <?php endif; ?>
                        </div>

                        <!-- Checkboxes -->
                        <div class="md:col-span-2">
                            <div class="flex items-center mb-2">
                                <input class="rounded mr-2" type="checkbox" name="is_new" value="1" id="is_new" <?= $product['is_new'] ? 'checked' : '' ?>>
                                <label class="text-cyan-100" for="is_new">Mark as New</label>
                            </div>
                            <div class="flex items-center mb-4">
                                <input class="rounded mr-2" type="checkbox" name="is_hot" value="1" id="is_hot" <?= $product['is_hot'] ? 'checked' : '' ?>>
                                <label class="text-cyan-100" for="is_hot">Mark as Hot</label>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Button -->
                    <div class="flex justify-end gap-4 mt-8">
                        <a href="products.php" class="rounded-lg bg-gradient-to-r from-gray-600 to-gray-700 text-white font-bold px-6 py-2 hover:from-gray-700 hover:to-gray-600 transition">
                            Cancel
                        </a>
                        <button type="submit" class="rounded-lg bg-gradient-to-r from-cyan-500 to-blue-500 text-white font-bold px-6 py-2 hover:from-blue-500 hover:to-cyan-500 transition">
                            <i class="fa fa-save"></i> Update Product
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
