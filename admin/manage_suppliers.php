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

// Handle form submission for adding new supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add') {
    try {
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("INSERT INTO suppliers (name, phone, email, address_line, notes, is_archive) VALUES (?, ?, ?, ?, ?, 0)");
        $stmt->execute([$name, $phone, $email, $address, $notes]);

        logHistory($pdo, 'Added Supplier', 'Supplier Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier added successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error adding supplier: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier editing (modal submit)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'edit') {
    try {
        $supplierId = (int)$_POST['supplier_id'];
        $name = $_POST['name'];
        $contact_info = $_POST['contact_info']; // for log only
        $email = $_POST['email'];
        $phone = $_POST['phone'];
        $address = $_POST['address'];
        $notes = $_POST['notes'];

        $stmt = $pdo->prepare("UPDATE suppliers SET name = ?, phone = ?, email = ?, address_line = ?, notes = ? WHERE supplier_id = ?");
        $stmt->execute([$name, $phone, $email, $address, $notes, $supplierId]);

        logHistory($pdo, 'Edited Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $name . ', Contact: ' . $contact_info, $_SESSION['username']);

        $_SESSION['success'] = "Supplier updated successfully!";
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating supplier: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier archiving
if (isset($_GET['archive']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    
    // Check if supplier has active product assignments
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM supplier_products WHERE supplier_id = ? AND is_active = 1");
    $stmt->execute([$supplierId]);
    $productCount = $stmt->fetchColumn();
    
    // Check if supplier has active product batches
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM product_batches WHERE supplier_id = ? AND is_active = 1 AND quantity_remaining > 0");
    $stmt->execute([$supplierId]);
    $batchCount = $stmt->fetchColumn();
    
    if ($productCount > 0 || $batchCount > 0) {
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        $supplierName = $supplier['name'] ?? 'Unknown';
        
        $message = "Cannot archive supplier <strong>$supplierName</strong>. ";
        if ($productCount > 0 && $batchCount > 0) {
            $message .= "They have $productCount active product assignments and $batchCount active product batches.";
        } elseif ($productCount > 0) {
            $message .= "They have $productCount active product assignments.";
        } else {
            $message .= "They have $batchCount active product batches.";
        }
        
        $_SESSION['error'] = $message;
    } else {
        $stmt = $pdo->prepare("UPDATE suppliers SET is_archive = 1 WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);

        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        logHistory($pdo, 'Archived Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $supplier['name'], $_SESSION['username']);
        $_SESSION['success'] = "Supplier archived successfully!";
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle supplier unarchiving
if (isset($_GET['unarchive']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);
    $stmt = $pdo->prepare("UPDATE suppliers SET is_archive = 0 WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);

    $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $supplier = $stmt->fetch();
    logHistory($pdo, 'Unarchived Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . $supplier['name'], $_SESSION['username']);

    header("Location: manage_suppliers.php");
    exit;
}


// Handle supplier deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $supplierId = intval($_GET['id']);

    // Check if supplier has associated products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE supplier_id = ?");
    $stmt->execute([$supplierId]);
    $productCount = $stmt->fetchColumn();

    if ($productCount > 0) {
        $_SESSION['error'] = "Cannot delete supplier. They have " . $productCount . " associated products.";
    } else {
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);
        $supplier = $stmt->fetch();
        $stmt = $pdo->prepare("DELETE FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplierId]);

        logHistory($pdo, 'Deleted Supplier', 'Supplier ID: ' . $supplierId . ', Name: ' . ($supplier['name'] ?? ''), $_SESSION['username']);
        $_SESSION['success'] = "Supplier deleted successfully!";
    }

    header("Location: manage_suppliers.php");
    exit;
}

// Handle PO creation
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'create_po') {
    try {
        $supplier_id = (int)$_POST['supplier_id'];
        $expected_delivery = date('Y-m-d'); // Use today's date
        $po_notes = $_POST['po_notes'] ?? '';
        $selected_products = $_POST['selected_products'] ?? [];
        
        if (empty($selected_products)) {
            throw new Exception("Please select at least one product for the PO.");
        }
        
        // Generate PO number
        $date_part = date('Ymd');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM restocking WHERE po_number LIKE ? AND is_purchase_order = 1");
        $stmt->execute(["PO-$date_part-%"]);
        $count = $stmt->fetchColumn() + 1;
        $po_number = sprintf("PO-%s-%04d", $date_part, $count);
        
        $pdo->beginTransaction();
        
        $total_items = 0;
        foreach ($selected_products as $product_key) {
            list($product_id, $brand_id) = explode('_', $product_key);
            $product_id = (int)$product_id;
            $brand_id = (int)$brand_id;
            
            $quantity = (float)$_POST["quantity_{$product_key}"];
            
            // Debug logging
            error_log("PO Creation Debug - Product: $product_id, Brand: $brand_id, Quantity: $quantity");
            
            if ($quantity <= 0) {
                throw new Exception("Quantity must be greater than 0 for all products.");
            }
            
            // Insert into restocking table (cost and expiration will be added during receipt)
            $stmt = $pdo->prepare("
                INSERT INTO restocking 
                (po_number, is_purchase_order, product_id, supplier_id, brand_id, quantity_added, cost_per_unit, total_cost, restock_date, expiration_date, status_id, notes, created_by)
                VALUES (?, 1, ?, ?, ?, ?, NULL, NULL, ?, NULL, 1, ?, ?)
            ");
            $stmt->execute([
                $po_number,
                $product_id,
                $supplier_id,
                $brand_id,
                $quantity,
                $expected_delivery,
                $po_notes,
                $_SESSION['user_id']
            ]);
            $total_items++;
        }
        
        $pdo->commit();
        
        // Get supplier name for logging
        $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
        $stmt->execute([$supplier_id]);
        $supplier_name = $stmt->fetchColumn();
        
        logHistory($pdo, 'Purchase Order Created', "PO: $po_number, Supplier: $supplier_name, Items: $total_items", $_SESSION['username']);
        
        $_SESSION['success'] = "Purchase Order $po_number created successfully with $total_items items!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $_SESSION['error'] = "Error creating PO: " . $e->getMessage();
    }
    
    header("Location: manage_suppliers.php");
    exit;
}

// Handle AJAX request for low stock products
if (isset($_GET['action']) && $_GET['action'] == 'get_low_stock' && isset($_GET['supplier_id'])) {
    $supplier_id = (int)$_GET['supplier_id'];
    
    // First, let's get all products assigned to this supplier
    $stmt = $pdo->prepare("
        SELECT DISTINCT
            p.product_id,
            p.product_name,
            uom.name as uom_name,
            COALESCE(ps.current_stock, 0) as current_stock,
            COALESCE(ps.reorder_point, 10) as reorder_point,
            COALESCE(pp.cost_price, 0) as last_cost
        FROM supplier_products sp
        INNER JOIN products p ON sp.product_id = p.product_id
        LEFT JOIN product_stock ps ON ps.product_id = p.product_id
        LEFT JOIN uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE sp.supplier_id = ? AND sp.is_active = 1 AND p.is_archive = 0
        ORDER BY p.product_name
    ");
    $stmt->execute([$supplier_id]);
    $allProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $lowStockProducts = [];
    
    foreach ($allProducts as $product) {
        // Check if this product has low stock
        if ($product['current_stock'] <= $product['reorder_point']) {
            // Get brands for this product that are actually assigned to this supplier
            $brandStmt = $pdo->prepare("
                SELECT DISTINCT b.id as brand_id, b.name as brand_name
                FROM product_batches pb
                INNER JOIN brands b ON pb.brand_id = b.id
                WHERE pb.product_id = ? AND pb.is_active = 1 AND pb.supplier_id = ?
                UNION
                SELECT 1 as brand_id, 'No Brand' as brand_name
                WHERE NOT EXISTS (
                    SELECT 1 FROM product_batches pb2 
                    WHERE pb2.product_id = ? AND pb2.is_active = 1 AND pb2.supplier_id = ?
                )
            ");
            $brandStmt->execute([$product['product_id'], $supplier_id, $product['product_id'], $supplier_id]);
            $brands = $brandStmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($brands)) {
                // If no brands found, add a default entry
                $brands = [['brand_id' => 1, 'brand_name' => 'No Brand']];
            }
            
            foreach ($brands as $brand) {
                // Calculate brand-specific stock
                $stockStmt = $pdo->prepare("
                    SELECT COALESCE(SUM(quantity_remaining), 0) as brand_stock
                    FROM product_batches 
                    WHERE product_id = ? AND brand_id = ? AND is_active = 1 AND quantity_remaining > 0
                ");
                $stockStmt->execute([$product['product_id'], $brand['brand_id']]);
                $brandStock = $stockStmt->fetchColumn();
                
                // Only include if brand stock is low or out
                if ($brandStock <= $product['reorder_point']) {
                    $lowStockProducts[] = [
                        'product_id' => $product['product_id'],
                        'product_name' => $product['product_name'],
                        'brand_id' => $brand['brand_id'],
                        'brand_name' => $brand['brand_name'],
                        'current_stock' => $brandStock,
                        'reorder_point' => $product['reorder_point'],
                        'uom_name' => $product['uom_name'],
                        'last_cost' => $product['last_cost'],
                        'suggested_qty' => max($product['reorder_point'] - $brandStock, 1)
                    ];
                }
            }
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode($lowStockProducts);
    exit;
}

// Handle assign product to supplier
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'assign_product') {
    try {
        $supplierId = (int)$_POST['supplier_id'];
        $productId = (int)$_POST['product_id'];

        // Check if relationship already exists
        $stmt = $pdo->prepare("SELECT id FROM supplier_products WHERE supplier_id = ? AND product_id = ?");
        $stmt->execute([$supplierId, $productId]);
        
        if ($stmt->fetch()) {
            $_SESSION['error'] = "This product is already assigned to this supplier.";
        } else {
            // Add the relationship
            $stmt = $pdo->prepare("INSERT INTO supplier_products (supplier_id, product_id, is_primary, is_active, created_by) VALUES (?, ?, 0, 1, ?)");
            $stmt->execute([$supplierId, $productId, $_SESSION['user_id']]);

            // Get supplier and product names for logging
            $stmt = $pdo->prepare("SELECT name FROM suppliers WHERE supplier_id = ?");
            $stmt->execute([$supplierId]);
            $supplierName = $stmt->fetchColumn();

            $stmt = $pdo->prepare("SELECT product_name FROM products WHERE product_id = ?");
            $stmt->execute([$productId]);
            $productName = $stmt->fetchColumn();

            logHistory($pdo, 'Assigned Product to Supplier', "Supplier: $supplierName, Product: $productName" . ($isPrimary ? ' (Primary)' : ''), $_SESSION['username']);
            $_SESSION['success'] = "Product assigned to supplier successfully!";
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error assigning product: " . $e->getMessage();
    }

    header("Location: manage_suppliers.php");
    exit;
}


// Fetch active suppliers with their products
$activeSuppliers = $pdo->query("
    SELECT 
        s.supplier_id AS id, 
        s.name, 
        s.phone, 
        s.email, 
        CONCAT_WS(', ', s.address_line, s.city, s.country) AS address, 
        s.notes,
        COUNT(DISTINCT sp.product_id) AS product_count
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.supplier_id = sp.supplier_id AND sp.is_active = 1
    LEFT JOIN products p ON sp.product_id = p.product_id AND p.is_archive = 0
    WHERE s.is_archive = 0
    GROUP BY s.supplier_id, s.name, s.phone, s.email, s.address_line, s.city, s.country, s.notes
    ORDER BY s.name
")->fetchAll();

// Fetch detailed products for each supplier (with brand-specific stock)
$supplierProducts = [];
foreach ($activeSuppliers as $supplier) {
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.product_description,
            c.category_name,
            b.name AS brand_name,
            b.id AS brand_id,
            uom.name AS uom_name,
            COALESCE(SUM(pb.quantity_remaining), 0) AS stock,
            COALESCE(pp.markup_price, 0) + COALESCE(AVG(pb.unit_cost), pp.cost_price, 0) AS price,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
        FROM supplier_products sp
        INNER JOIN products p ON sp.product_id = p.product_id
        LEFT JOIN product_batches pb ON p.product_id = pb.product_id AND pb.is_active = 1 AND pb.quantity_remaining > 0
        LEFT JOIN brands b ON pb.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE sp.supplier_id = ? AND sp.is_active = 1 AND p.is_archive = 0
        GROUP BY p.product_id, p.product_name, p.product_description, c.category_name, b.id, b.name, uom.name, pp.markup_price, pp.cost_price
        ORDER BY p.product_name, b.name
    ");
    $stmt->execute([$supplier['id']]);
    $supplierProducts[$supplier['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Fetch archived suppliers with their products
$archivedSuppliers = $pdo->query("
    SELECT 
        s.supplier_id AS id, 
        s.name, 
        s.phone, 
        s.email, 
        CONCAT_WS(', ', s.address_line, s.city, s.country) AS address, 
        s.notes,
        COUNT(DISTINCT sp.product_id) AS product_count
    FROM suppliers s
    LEFT JOIN supplier_products sp ON s.supplier_id = sp.supplier_id AND sp.is_active = 1
    LEFT JOIN products p ON sp.product_id = p.product_id AND p.is_archive = 0
    WHERE s.is_archive = 1
    GROUP BY s.supplier_id, s.name, s.phone, s.email, s.address_line, s.city, s.country, s.notes
    ORDER BY s.name
")->fetchAll();

// Fetch detailed products for archived suppliers (with brand-specific stock)
$archivedSupplierProducts = [];
foreach ($archivedSuppliers as $supplier) {
    $stmt = $pdo->prepare("
        SELECT 
            p.product_id,
            p.product_name,
            p.product_description,
            c.category_name,
            b.name AS brand_name,
            b.id AS brand_id,
            uom.name AS uom_name,
            COALESCE(SUM(pb.quantity_remaining), 0) AS stock,
            COALESCE(pp.markup_price, 0) + COALESCE(AVG(pb.unit_cost), pp.cost_price, 0) AS price,
            (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image1
        FROM supplier_products sp
        INNER JOIN products p ON sp.product_id = p.product_id
        LEFT JOIN product_batches pb ON p.product_id = pb.product_id AND pb.is_active = 1 AND pb.quantity_remaining > 0
        LEFT JOIN brands b ON pb.brand_id = b.id
        LEFT JOIN categories c ON p.category_id = c.category_id
        LEFT JOIN uom uom ON p.uom_id = uom.uom_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE sp.supplier_id = ? AND sp.is_active = 1 AND p.is_archive = 0
        GROUP BY p.product_id, p.product_name, p.product_description, c.category_name, b.id, b.name, uom.name, pp.markup_price, pp.cost_price
        ORDER BY p.product_name, b.name
    ");
    $stmt->execute([$supplier['id']]);
    $archivedSupplierProducts[$supplier['id']] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Manage Suppliers - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet" />
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
        padding: 1.5rem 1.5rem 1rem 1.5rem;
        margin: 0;
    }
    
    .table-card .card-body {
        padding: 1.5rem;
    }
    
    .table-card .table {
        margin-bottom: 0;
    }
    
    .table-card .table th {
        border: none;
        padding: 1rem 1.25rem;
        font-weight: 600;
        color: var(--bs-dark);
    }
    
    .table-card .table td {
        border: none;
        padding: 1rem 1.25rem;
        vertical-align: middle;
    }
    
    .table-card .table-light {
        background: #f8f9fa;
    }
    
    .supplier-row {
      transition: none;
    }

    .modal-content { background-color: var(--card-bg) !important; border-color: var(--border-color) !important; color: var(--text-primary) !important; }
    .modal-header, .modal-footer { border-color: var(--border-color) !important; }
    
    .product-item {
      background: var(--bg-primary);
      border: 1px solid var(--border-color);
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 8px;
      transition: all 0.2s ease;
    }
    
    .product-item:hover {
      background: var(--card-bg);
      box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .product-image {
      width: 50px;
      height: 50px;
      object-fit: cover;
      border-radius: 6px;
      border: 1px solid var(--border-color);
    }
    
    .product-details {
      flex: 1;
    }
    
    .product-name {
      font-weight: 600;
      color: var(--text-primary);
      margin-bottom: 4px;
    }
    
    .product-meta {
      font-size: 0.85rem;
      color: var(--text-secondary);
    }
    
    .product-price {
      font-weight: 600;
      color: #7F1734;
    }
    
    .product-stock {
      font-size: 0.8rem;
    }
    
    .stock-available { color: #28a745; }
    .stock-low { color: #ffc107; }
    .stock-out { color: #dc3545; }
    
    .products-container {
      max-height: 300px;
      overflow-y: auto;
    }
    
    .products-container::-webkit-scrollbar {
      width: 6px;
    }
    
    .products-container::-webkit-scrollbar-track {
      background: var(--bg-primary);
      border-radius: 3px;
    }
    
    .products-container::-webkit-scrollbar-thumb {
      background: var(--border-color);
      border-radius: 3px;
    }
    
    .products-container::-webkit-scrollbar-thumb:hover {
      background: #7F1734;
    }
    
    /* Select2 Custom Styling */
    .select2-container--bootstrap-5 .select2-selection {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        min-height: 38px;
    }
    
    .select2-container--bootstrap-5 .select2-selection--single {
        height: 38px;
        padding: 0.375rem 0.75rem;
    }
    
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__rendered {
        line-height: 1.5;
        padding-left: 0;
        padding-right: 0;
    }
    
    .select2-container--bootstrap-5 .select2-selection--single .select2-selection__arrow {
        height: 36px;
        right: 8px;
    }
    
    .select2-container--bootstrap-5 .select2-dropdown {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    }
    
    .select2-container--bootstrap-5 .select2-search--dropdown .select2-search__field {
        border: 1px solid #ced4da;
        border-radius: 0.375rem;
        padding: 0.375rem 0.75rem;
    }
    
    .select2-container--bootstrap-5 .select2-results__option--highlighted[aria-selected] {
        background-color: #7F1734;
        color: white;
    }
    
    .select2-container--bootstrap-5 .select2-results__option[aria-selected=true] {
        background-color: #7F1734;
        color: white;
    }
    
    .select2-container--bootstrap-5 .select2-selection--single:focus {
        border-color: #7F1734;
        box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
    }
    
    /* SweetAlert2 Custom Styles */
    .swal2-popup-custom {
        border-radius: 20px !important;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15) !important;
        border: 1px solid #e9ecef !important;
    }
    
    .swal2-title-custom {
        color: var(--bs-primary) !important;
        font-weight: 700 !important;
        font-size: 1.5rem !important;
    }
    
    .swal2-content-custom {
        font-size: 1rem !important;
        color: var(--bs-dark) !important;
    }
    
    .swal2-confirm-custom {
        background: var(--bs-success) !important;
        border: none !important;
        border-radius: 25px !important;
        padding: 0.75rem 2rem !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }
    
    .swal2-confirm-custom:hover {
        background: #157347 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
    }
    
    .swal2-cancel-custom {
        background: #6c757d !important;
        border: none !important;
        border-radius: 25px !important;
        padding: 0.75rem 2rem !important;
        font-weight: 600 !important;
        transition: all 0.3s ease !important;
    }
    
    .swal2-cancel-custom:hover {
        background: #5a6268 !important;
        transform: translateY(-2px) !important;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2) !important;
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
      <!-- Page Header -->
      <div class="page-header">
        <h2>
          <i class="fa fa-truck me-2"></i>Supplier Management
        </h2>
        <p class="mb-0 opacity-75">Manage your suppliers and their product assignments</p>
      </div>

      <!-- Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-truck"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= count($activeSuppliers) ?></h3>
              <p class="card-label">Active Suppliers</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-archive"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= count($archivedSuppliers) ?></h3>
              <p class="card-label">Archived Suppliers</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= array_sum(array_column($activeSuppliers, 'product_count')) ?></h3>
              <p class="card-label">Total Product Assignments</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-plus"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= count($activeSuppliers) + count($archivedSuppliers) ?></h3>
              <p class="card-label">Total Suppliers</p>
            </div>
          </div>
        </div>
    </div>

    <!-- Alerts -->
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

    <!-- Add Supplier Button -->
    <div class="mb-4 d-flex gap-2">
      <button type="button" class="btn fw-bold px-4" style="background: #b3d9ff; color: #003d82; border-radius: 8px;" data-bs-toggle="modal" data-bs-target="#addSupplierModal">
        <i class="fa fa-plus me-2"></i>Add New Supplier
      </button>
    </div>

    <!-- Active Suppliers Table -->
    <div class="mb-5">
      <div class="table-card">
        <div class="card-header">
          <h4 class="fw-bold text-dark mb-0">
            <i class="fa fa-truck me-2" style="color: var(--bs-primary);"></i>Active Suppliers
          </h4>
        </div>
        <div class="card-body">
        <?php if (empty($activeSuppliers)): ?>
            <div class="text-center py-5">
              <i class="fa fa-truck display-4 text-muted mb-3"></i>
              <h5 class="text-muted">No active suppliers found</h5>
              <p class="text-muted">Add your first supplier using the form above.</p>
          </div>
        <?php else: ?>
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Supplier Name</th>
                    <th class="fw-semibold">Contact Info</th>
                    <th class="fw-semibold">Products</th>
                    <th class="fw-semibold">Notes</th>
                    <th class="fw-semibold text-center">Actions</th>
                  </tr>
                </thead>
            <tbody>
          <?php foreach ($activeSuppliers as $supplier): ?>
                <tr class="supplier-row" style="cursor: pointer;" 
                    onclick="toggleProductsView(<?= $supplier['id'] ?>)"
                        data-id="<?= $supplier['id'] ?>"
                        data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                        data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>">
                  <td>
                    <div class="fw-semibold"><?= htmlspecialchars($supplier['name']) ?></div>
                  </td>
                  <td>
                    <div class="small">
                  <?php if ($supplier['phone']): ?>
                        <div><i class="fa fa-phone me-1 text-muted"></i><?= htmlspecialchars($supplier['phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['email']): ?>
                        <div><i class="fa fa-envelope me-1 text-muted"></i><?= htmlspecialchars($supplier['email']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['address']): ?>
                        <div><i class="fa fa-map-marker me-1 text-muted"></i><?= htmlspecialchars($supplier['address']) ?></div>
                  <?php endif; ?>
                </div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <span class="badge bg-primary me-2"><?= $supplier['product_count'] ?></span>
                      <button class="btn btn-sm" 
                              style="background: #b3d9ff; color: #003d82; border-radius: 8px;"
                              onclick="event.stopPropagation(); openAssignProductsModal(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')"
                              title="Assign Products">
                        <i class="fa fa-plus"></i>
                      </button>
                      <?php 
                      $products = $supplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <button class="btn btn-sm" 
                                style="background: #d1ecf1; color: #0c5460; border-radius: 8px;"
                                onclick="event.stopPropagation(); toggleProductsView(<?= $supplier['id'] ?>)"
                                title="View All Products">
                          <i class="fa fa-eye"></i>
                        </button>
                      <?php endif; ?>
                  </div>
                    <?php if (!empty($products)): ?>
                      <div class="mt-2">
                      <small class="text-muted">
                          <?php 
                          $productNames = array_slice(array_map(function($p) { return $p['product_name']; }, $products), 0, 3);
                          echo htmlspecialchars(implode(', ', $productNames));
                          if (count($products) > 3) echo '...';
                          ?>
                      </small>
                      </div>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($supplier['notes'])): ?>
                      <small class="text-muted"><?= htmlspecialchars(substr($supplier['notes'], 0, 50)) ?><?= strlen($supplier['notes']) > 50 ? '...' : '' ?></small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm" 
                              style="background: #cce5ff; color: #004085; border-radius: 8px;"
                              onclick="event.stopPropagation(); openEditSupplierModal(this)"
                              data-id="<?= $supplier['id'] ?>"
                              data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                              data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                              data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                              data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                              data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>"
                              title="Edit Supplier">
                        <i class="fa fa-edit"></i>
                      </button>
                      <button class="btn btn-sm" 
                         style="background: #fff3cd; color: #856404; border-radius: 8px;"
                         onclick="confirmArchive(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')"
                         title="Archive Supplier">
                        <i class="fa fa-archive"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- Expandable Products Row -->
                <tr id="products-row-<?= $supplier['id'] ?>" class="products-detail-row" style="display: none;">
                  <td colspan="5">
                    <div class="p-3 bg-light rounded">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">
                          <i class="fa fa-box me-2 text-primary"></i>Products Supplied by <?= htmlspecialchars($supplier['name']) ?>
                        </h6>
                        <button class="btn btn-success btn-sm" 
                                onclick="event.stopPropagation(); openCreatePOModal(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')">
                          <i class="fa fa-file-invoice me-1"></i>Create Purchase Order
                        </button>
                      </div>
                      <?php 
                      $products = $supplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <div class="row g-3">
                          <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4">
                              <div class="product-item d-flex align-items-center gap-3 p-3 bg-white rounded border">
                                <img src="<?= !empty($product['image1']) ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                     class="product-image"
                                     onerror="this.src='uploads/default.png'">
                                <div class="product-details">
                                  <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                  <div class="product-meta">
                                    <span class="product-price">₱<?= number_format($product['price'], 2) ?></span>
                                    <span class="mx-2">•</span>
                                    <span class="product-stock <?= $product['stock'] > 10 ? 'stock-available' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out') ?>">
                                      Stock: <?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                    </span>
                                  </div>
                                  <div class="product-meta">
                                    <small><?= htmlspecialchars($product['category_name'] ?? '') ?></small>
                                    <span class="mx-1">•</span>
                                    <small><?= !empty($product['brand_name']) ? htmlspecialchars($product['brand_name']) : 'No Brand' ?></small>
                                  </div>
                                </div>
                              </div>
                            </div>
                          <?php endforeach; ?>
                    </div>
                  <?php else: ?>
                        <div class="text-center py-4">
                          <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                          <h6 class="text-muted">No products assigned</h6>
                          <p class="text-muted">This supplier doesn't have any products assigned yet.</p>
                    </div>
                  <?php endif; ?>
                </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
              </table>
                  </div>
                <?php endif; ?>
              </div>
      </div>
    </div>

    <!-- Archived Suppliers Table -->
    <?php if (!empty($archivedSuppliers)): ?>
      <div class="mb-5">
        <div class="table-card">
          <div class="card-header">
            <h4 class="fw-bold text-dark mb-0">
              <i class="fa fa-archive me-2" style="color: var(--bs-danger);"></i>Archived Suppliers
            </h4>
          </div>
          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Supplier Name</th>
                    <th class="fw-semibold">Contact Info</th>
                    <th class="fw-semibold">Products</th>
                    <th class="fw-semibold">Notes</th>
                    <th class="fw-semibold text-center">Actions</th>
                  </tr>
                </thead>
            <tbody>
          <?php foreach ($archivedSuppliers as $supplier): ?>
                <tr class="supplier-row" style="cursor: pointer; opacity: 0.7;" 
                    onclick="toggleProductsView(<?= $supplier['id'] ?>)"
                        data-id="<?= $supplier['id'] ?>"
                        data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                        data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                        data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                        data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                        data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>">
                  <td>
                    <div class="fw-semibold text-danger"><?= htmlspecialchars($supplier['name']) ?></div>
                    <small class="badge bg-danger">Archived</small>
                  </td>
                  <td>
                    <div class="small">
                  <?php if ($supplier['phone']): ?>
                        <div><i class="fa fa-phone me-1 text-muted"></i><?= htmlspecialchars($supplier['phone']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['email']): ?>
                        <div><i class="fa fa-envelope me-1 text-muted"></i><?= htmlspecialchars($supplier['email']) ?></div>
                  <?php endif; ?>
                  <?php if ($supplier['address']): ?>
                        <div><i class="fa fa-map-marker me-1 text-muted"></i><?= htmlspecialchars($supplier['address']) ?></div>
                  <?php endif; ?>
                </div>
                  </td>
                  <td>
                    <div class="d-flex align-items-center">
                      <span class="badge bg-secondary me-2"><?= $supplier['product_count'] ?></span>
                      <?php 
                      $products = $archivedSupplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <button class="btn btn-sm" 
                                style="background: #d1ecf1; color: #0c5460; border-radius: 8px;"
                                onclick="event.stopPropagation(); toggleProductsView(<?= $supplier['id'] ?>)"
                                title="View All Products">
                          <i class="fa fa-eye"></i>
                        </button>
                      <?php endif; ?>
                  </div>
                    <?php if (!empty($products)): ?>
                      <div class="mt-2">
                      <small class="text-muted">
                          <?php 
                          $productNames = array_slice(array_map(function($p) { return $p['product_name']; }, $products), 0, 3);
                          echo htmlspecialchars(implode(', ', $productNames));
                          if (count($products) > 3) echo '...';
                          ?>
                      </small>
                    </div>
                  <?php endif; ?>
                  </td>
                  <td>
                    <?php if (!empty($supplier['notes'])): ?>
                      <small class="text-muted"><?= htmlspecialchars(substr($supplier['notes'], 0, 50)) ?><?= strlen($supplier['notes']) > 50 ? '...' : '' ?></small>
                    <?php else: ?>
                      <span class="text-muted">-</span>
                    <?php endif; ?>
                  </td>
                  <td class="text-center">
                    <div class="btn-group" role="group">
                      <button class="btn btn-sm" 
                              style="background: #cce5ff; color: #004085; border-radius: 8px;"
                              onclick="event.stopPropagation(); openEditSupplierModal(this)"
                              data-id="<?= $supplier['id'] ?>"
                              data-name="<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>"
                              data-phone="<?= htmlspecialchars($supplier['phone'] ?? '', ENT_QUOTES) ?>"
                              data-email="<?= htmlspecialchars($supplier['email'] ?? '', ENT_QUOTES) ?>"
                              data-address="<?= htmlspecialchars($supplier['address'] ?? '', ENT_QUOTES) ?>"
                              data-notes="<?= htmlspecialchars($supplier['notes'] ?? '', ENT_QUOTES) ?>"
                              title="Edit Supplier">
                        <i class="fa fa-edit"></i>
                      </button>
                      <button class="btn btn-sm" 
                         style="background: #d4edda; color: #155724; border-radius: 8px;"
                         onclick="confirmUnarchive(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')"
                         title="Unarchive Supplier">
                        <i class="fa fa-undo"></i>
                      </button>
                      <button class="btn btn-sm" 
                         style="background: #f5c6cb; color: #721c24; border-radius: 8px;"
                         onclick="confirmDelete(<?= $supplier['id'] ?>, '<?= htmlspecialchars($supplier['name'], ENT_QUOTES) ?>')"
                         title="Delete Supplier">
                        <i class="fa fa-trash"></i>
                      </button>
                    </div>
                  </td>
                </tr>
                <!-- Expandable Products Row for Archived Suppliers -->
                <tr id="products-row-<?= $supplier['id'] ?>" class="products-detail-row" style="display: none;">
                  <td colspan="5">
                    <div class="p-3 bg-light rounded">
                      <div class="d-flex justify-content-between align-items-center mb-3">
                        <h6 class="fw-semibold mb-0">
                          <i class="fa fa-box me-2 text-primary"></i>Products Supplied by <?= htmlspecialchars($supplier['name']) ?>
                        </h6>
                      </div>
                      <?php 
                      $products = $archivedSupplierProducts[$supplier['id']] ?? [];
                      if (!empty($products)): ?>
                        <div class="row g-3">
                          <?php foreach ($products as $product): ?>
                            <div class="col-md-6 col-lg-4">
                              <div class="product-item d-flex align-items-center gap-3 p-3 bg-white rounded border">
                                <img src="<?= !empty($product['image1']) ? htmlspecialchars($product['image1']) : 'uploads/default.png' ?>" 
                                     alt="<?= htmlspecialchars($product['product_name']) ?>" 
                                     class="product-image"
                                     onerror="this.src='uploads/default.png'">
                                <div class="product-details">
                                  <div class="product-name"><?= htmlspecialchars($product['product_name']) ?></div>
                                  <div class="product-meta">
                                    <span class="product-price">₱<?= number_format($product['price'], 2) ?></span>
                                    <span class="mx-2">•</span>
                                    <span class="product-stock <?= $product['stock'] > 10 ? 'stock-available' : ($product['stock'] > 0 ? 'stock-low' : 'stock-out') ?>">
                                      Stock: <?= $product['stock'] ?> <?= htmlspecialchars($product['uom_name'] ?? '') ?>
                                    </span>
                  </div>
                                  <div class="product-meta">
                                    <small><?= htmlspecialchars($product['category_name'] ?? '') ?></small>
                                    <span class="mx-1">•</span>
                                    <small><?= !empty($product['brand_name']) ? htmlspecialchars($product['brand_name']) : 'No Brand' ?></small>
                                  </div>
                                </div>
              </div>
            </div>
          <?php endforeach; ?>
                        </div>
                      <?php else: ?>
                        <div class="text-center py-4">
                          <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                          <h6 class="text-muted">No products assigned</h6>
                          <p class="text-muted">This supplier doesn't have any products assigned yet.</p>
                        </div>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
              </table>
            </div>
          </div>
        </div>
      </div>
    <?php endif; ?>
    </div>
  </main>

  <!-- Add Supplier Modal -->
  <div class="modal fade" id="addSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-plus me-2" style="color: #7F1734;"></i>Add New Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="add">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier Name</label>
                <input type="text" name="name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Person</label>
                <input type="text" name="contact_info" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="tel" name="phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn fw-bold" style="background: #b3d9ff; color: #003d82; border-radius: 8px;">
              <i class="fa fa-plus me-2"></i>Add Supplier
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Edit Supplier Modal (same design as Add) -->
  <div class="modal fade" id="editSupplierModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-edit me-2" style="color: #7F1734;"></i>Edit Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="edit">
            <input type="hidden" name="supplier_id" id="edit_supplier_id">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label fw-semibold">Supplier Name</label>
                <input type="text" name="name" id="edit_name" class="form-control" required>
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Contact Person</label>
                <input type="text" name="contact_info" id="edit_contact_info" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Phone</label>
                <input type="tel" name="phone" id="edit_phone" class="form-control">
              </div>
              <div class="col-md-6">
                <label class="form-label fw-semibold">Email</label>
                <input type="email" name="email" id="edit_email" class="form-control">
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Address</label>
                <textarea name="address" id="edit_address" class="form-control" rows="3"></textarea>
              </div>
              <div class="col-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="notes" id="edit_notes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn fw-bold" style="background: #b3d9ff; color: #003d82; border-radius: 8px;">
              <i class="fa fa-save me-2"></i>Save Changes
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Assign Products Modal -->
  <div class="modal fade" id="assignProductsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-plus me-2" style="color: #7F1734;"></i>Assign Products to Supplier
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST">
          <div class="modal-body">
            <input type="hidden" name="action" value="assign_product">
            <input type="hidden" name="supplier_id" id="assign_supplier_id">
            
            <div class="mb-3">
              <label class="form-label fw-semibold">Supplier</label>
              <input type="text" id="assign_supplier_name" class="form-control" readonly>
            </div>
            
            <div class="mb-3">
              <label class="form-label fw-semibold">Select Product</label>
              <select name="product_id" id="assign_product_id" class="form-select" required>
                <option value="">Choose a product...</option>
                <?php
                // Fetch all active products that are not already assigned to this supplier
                $allProducts = $pdo->query("
                  SELECT p.product_id, p.product_name, c.category_name
                  FROM products p
                  LEFT JOIN categories c ON p.category_id = c.category_id
                  WHERE p.is_archive = 0
                  ORDER BY p.product_name
                ")->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($allProducts as $product): ?>
                  <option value="<?= $product['product_id'] ?>" data-category="<?= htmlspecialchars($product['category_name'] ?? '') ?>">
                    <?= htmlspecialchars($product['product_name']) ?> 
                    <?php if (!empty($product['category_name'])): ?>
                      (<?= htmlspecialchars($product['category_name']) ?>)
                    <?php endif; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <div class="form-text">
                <i class="fa fa-info-circle me-1"></i>
                Search and select a product to assign to this supplier
              </div>
            </div>
            
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn fw-bold" style="background: #b3d9ff; color: #003d82; border-radius: 8px;">
              <i class="fa fa-plus me-2"></i>Assign Product
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Create Purchase Order Modal -->
  <div class="modal fade" id="createPOModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title fw-bold">
            <i class="fa fa-file-invoice me-2" style="color: #7F1734;"></i>Create Purchase Order
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form method="POST" id="createPOForm">
          <div class="modal-body">
            <input type="hidden" name="action" value="create_po">
            <input type="hidden" name="supplier_id" id="po_supplier_id">
            
            <div class="row g-3 mb-4">
              <div class="col-md-12">
                <label class="form-label fw-semibold">Supplier</label>
                <input type="text" id="po_supplier_name" class="form-control" readonly>
              </div>
            </div>
            
            <div class="alert alert-info">
              <i class="fa fa-info-circle me-2"></i>
              <strong>Low Stock Products:</strong> The products below have stock at or below their reorder point. Select items to include in the purchase order.
            </div>
            
            <div id="po_products_container" style="max-height: 400px; overflow-y: auto;">
              <table class="table table-hover" id="po_products_table">
                <thead class="table-light sticky-top">
                  <tr>
                    <th style="width: 50px;">
                      <input type="checkbox" id="select_all_po" class="form-check-input" checked>
                    </th>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Current Stock</th>
                    <th>Reorder Point</th>
                    <th style="width: 100px;">Quantity <span class="text-danger">*</span></th>
                    <th style="width: 120px;">Subtotal</th>
                  </tr>
                </thead>
                <tbody id="po_products_body">
                  <tr>
                    <td colspan="6" class="text-center py-4">
                      <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Loading...</span>
                      </div>
                      <p class="mt-2 mb-0">Loading low stock products...</p>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>
            
            <div class="row g-3 mt-3">
              <div class="col-md-12">
                <label class="form-label fw-semibold">Notes</label>
                <textarea name="po_notes" class="form-control" rows="2" placeholder="Additional notes for this purchase order..."></textarea>
              </div>
            </div>
            
            <div class="alert alert-light mt-3 mb-0">
              <div class="d-flex justify-content-between align-items-center">
                <span class="fw-bold fs-5">Total Items:</span>
                <span class="fw-bold fs-4 text-primary"><span id="po_total">0</span> kilos</span>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-success fw-bold" id="submit_po_btn">
              <i class="fa fa-check me-2"></i>Create Purchase Order
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    function openEditSupplierModal(btn) {
      const id = btn.getAttribute('data-id');
      const name = btn.getAttribute('data-name') || '';
      const phone = btn.getAttribute('data-phone') || '';
      const email = btn.getAttribute('data-email') || '';
      const address = btn.getAttribute('data-address') || '';
      const notes = btn.getAttribute('data-notes') || '';
      // Optional: contact_info not in schema; keep for UI consistency
      const contact = '';

      document.getElementById('edit_supplier_id').value = id;
      document.getElementById('edit_name').value = name;
      document.getElementById('edit_phone').value = phone;
      document.getElementById('edit_email').value = email;
      document.getElementById('edit_address').value = address;
      document.getElementById('edit_notes').value = notes;
      const ci = document.getElementById('edit_contact_info');
      if (ci) ci.value = contact;

      const modal = new bootstrap.Modal(document.getElementById('editSupplierModal'));
      modal.show();
    }

    function openAssignProductsModal(supplierId, supplierName) {
      document.getElementById('assign_supplier_id').value = supplierId;
      document.getElementById('assign_supplier_name').value = supplierName;
      
      // Reset form
      document.getElementById('assign_product_id').value = '';
      
      const modal = new bootstrap.Modal(document.getElementById('assignProductsModal'));
      modal.show();
      
      // Initialize Select2 when modal is shown
      $('#assignProductsModal').on('shown.bs.modal', function () {
        const productSelect = document.querySelector('#assignProductsModal select[name="product_id"]');
        if (productSelect && !$(productSelect).hasClass('select2-hidden-accessible')) {
          try {
            $(productSelect).select2({
              theme: 'bootstrap-5',
              placeholder: 'Search and select a product...',
              allowClear: true,
              width: '100%',
              dropdownParent: $('#assignProductsModal')
            });
            
            console.log('Assign Product Select2 initialized successfully');
          } catch (error) {
            console.error('Error initializing Assign Product Select2:', error);
          }
        }
      });
      
      // Clean up Select2 when modal is hidden
      $('#assignProductsModal').on('hidden.bs.modal', function () {
        const productSelect = document.querySelector('#assignProductsModal select[name="product_id"]');
        if (productSelect && $(productSelect).hasClass('select2-hidden-accessible')) {
          $(productSelect).select2('destroy');
        }
      });
    }

    function toggleProductsView(supplierId) {
      const productsRow = document.getElementById('products-row-' + supplierId);
      
      if (productsRow.style.display === 'none' || productsRow.style.display === '') {
        // Show products
        productsRow.style.display = 'table-row';
      } else {
        // Hide products
        productsRow.style.display = 'none';
      }
    }

    // Purchase Order Functions
    function openCreatePOModal(supplierId, supplierName) {
      document.getElementById('po_supplier_id').value = supplierId;
      document.getElementById('po_supplier_name').value = supplierName;
      
      // Reset form
      document.getElementById('createPOForm').reset();
      document.getElementById('po_supplier_id').value = supplierId;
      document.getElementById('po_supplier_name').value = supplierName;
      
      // Show loading state
      document.getElementById('po_products_body').innerHTML = `
        <tr>
          <td colspan="8" class="text-center py-4">
            <div class="spinner-border text-primary" role="status">
              <span class="visually-hidden">Loading...</span>
            </div>
            <p class="mt-2 mb-0">Loading low stock products...</p>
          </td>
        </tr>
      `;
      
      // Fetch low stock products via AJAX
      fetch(`manage_suppliers.php?action=get_low_stock&supplier_id=${supplierId}`)
        .then(response => response.json())
        .then(data => {
          if (data.length === 0) {
            document.getElementById('po_products_body').innerHTML = `
              <tr>
                <td colspan="6" class="text-center py-4">
                  <i class="fa fa-check-circle display-4 text-success mb-3"></i>
                  <h6 class="text-muted">No Low Stock Products</h6>
                  <p class="text-muted">All products from this supplier have adequate stock levels.</p>
                </td>
              </tr>
            `;
            document.getElementById('submit_po_btn').disabled = true;
          } else {
            populatePOProducts(data);
            calculatePOTotal();
            document.getElementById('submit_po_btn').disabled = false;
          }
          
          const modal = new bootstrap.Modal(document.getElementById('createPOModal'));
          modal.show();
        })
        .catch(error => {
          console.error('Error loading low stock products:', error);
          document.getElementById('po_products_body').innerHTML = `
            <tr>
              <td colspan="6" class="text-center py-4">
                <i class="fa fa-exclamation-triangle display-4 text-danger mb-3"></i>
                <h6 class="text-danger">Error Loading Products</h6>
                <p class="text-muted">Please try again or contact support if the issue persists.</p>
              </td>
            </tr>
          `;
        });
    }

    function populatePOProducts(products) {
      const tbody = document.getElementById('po_products_body');
      tbody.innerHTML = '';
      
      products.forEach(product => {
        const stockClass = product.current_stock <= 0 ? 'text-danger fw-bold' : 'text-warning fw-bold';
        const row = document.createElement('tr');
        row.innerHTML = `
          <td class="text-center">
            <input type="checkbox" name="selected_products[]" value="${product.product_id}_${product.brand_id}" 
                   class="form-check-input po-product-checkbox" checked>
          </td>
          <td>${product.product_name}</td>
          <td><span class="badge bg-secondary">${product.brand_name || 'No Brand'}</span></td>
          <td class="${stockClass}">${product.current_stock} ${product.uom_name || ''}</td>
          <td>${product.reorder_point}</td>
          <td>
            <input type="number" name="quantity_${product.product_id}_${product.brand_id}" 
                   class="form-control form-control-sm qty-input" min="1" step="0.01"
                   value="${Math.ceil(product.suggested_qty)}" required>
          </td>
          <td class="subtotal fw-bold">₱0.00</td>
        `;
        tbody.appendChild(row);
      });
      
      // Add event listeners for auto-calculation
      document.querySelectorAll('.qty-input').forEach(input => {
        input.addEventListener('input', calculatePOTotal);
      });
      
      document.querySelectorAll('.po-product-checkbox').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
          const row = this.closest('tr');
          const inputs = row.querySelectorAll('input[type="number"]');
          inputs.forEach(input => {
            input.disabled = !this.checked;
            if (!this.checked) {
              input.removeAttribute('required');
            } else {
              input.setAttribute('required', 'required');
            }
          });
          calculatePOTotal();
        });
      });
      
      // Select all checkbox
      document.getElementById('select_all_po').addEventListener('change', function() {
        document.querySelectorAll('.po-product-checkbox').forEach(checkbox => {
          checkbox.checked = this.checked;
          checkbox.dispatchEvent(new Event('change'));
        });
      });
    }

    function calculatePOTotal() {
      let totalItems = 0;
      document.querySelectorAll('#po_products_body tr').forEach(row => {
        const checkbox = row.querySelector('.po-product-checkbox');
        if (checkbox && checkbox.checked) {
          const qtyInput = row.querySelector('.qty-input');
          const subtotalCell = row.querySelector('.subtotal');
          
          if (qtyInput) {
            const qty = parseFloat(qtyInput.value) || 0;
            subtotalCell.textContent = `${qty} kilos`;
            totalItems += qty;
          }
        } else {
          const subtotalCell = row.querySelector('.subtotal');
          if (subtotalCell) {
            subtotalCell.textContent = '0 kilos';
          }
        }
      });
      document.getElementById('po_total').textContent = totalItems.toFixed(0);
    }

    // Form validation
    document.getElementById('createPOForm')?.addEventListener('submit', function(e) {
      const checkedProducts = document.querySelectorAll('.po-product-checkbox:checked');
      if (checkedProducts.length === 0) {
        e.preventDefault();
        alert('Please select at least one product for the purchase order.');
        return false;
      }
      
      // No additional validation needed for PO creation
      // Cost and expiration date will be entered during receipt
    });

    // SweetAlert confirmation functions
    function confirmArchive(supplierId, supplierName) {
      Swal.fire({
        title: 'Archive Supplier',
        html: `Are you sure you want to archive <strong>${supplierName}</strong>?<br><br><small class="text-muted">This will move the supplier to the archived section.</small>`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ffc107',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-archive me-1"></i>Archive',
        cancelButtonText: '<i class="fa fa-times me-1"></i>Cancel',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          content: 'swal2-content-custom',
          confirmButton: 'swal2-confirm-custom',
          cancelButton: 'swal2-cancel-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = `manage_suppliers.php?archive=1&id=${supplierId}`;
        }
      });
    }

    function confirmUnarchive(supplierId, supplierName) {
      Swal.fire({
        title: 'Unarchive Supplier',
        html: `Are you sure you want to unarchive <strong>${supplierName}</strong>?<br><br><small class="text-muted">This will move the supplier back to the active section.</small>`,
        icon: 'success',
        showCancelButton: true,
        confirmButtonColor: '#198754',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-undo me-1"></i>Unarchive',
        cancelButtonText: '<i class="fa fa-times me-1"></i>Cancel',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          content: 'swal2-content-custom',
          confirmButton: 'swal2-confirm-custom',
          cancelButton: 'swal2-cancel-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = `manage_suppliers.php?unarchive=1&id=${supplierId}`;
        }
      });
    }

    function confirmDelete(supplierId, supplierName) {
      Swal.fire({
        title: 'Delete Supplier',
        html: `Are you sure you want to permanently delete <strong>${supplierName}</strong>?<br><br><small class="text-danger">This action cannot be undone!</small>`,
        icon: 'error',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fa fa-trash me-1"></i>Delete',
        cancelButtonText: '<i class="fa fa-times me-1"></i>Cancel',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          content: 'swal2-content-custom',
          confirmButton: 'swal2-confirm-custom',
          cancelButton: 'swal2-cancel-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          window.location.href = `manage_suppliers.php?delete=1&id=${supplierId}`;
        }
      });
    }

  </script>
</body>
</html>