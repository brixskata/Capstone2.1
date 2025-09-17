<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle FIFO sale
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['process_fifo_sale'])) {
    try {
        $product_id = (int)$_POST['product_id'];
        $quantity_sold = (int)$_POST['quantity_sold'];
        
        if ($quantity_sold <= 0) {
            throw new Exception("Quantity must be greater than 0.");
        }
        
        // Get batches ordered by expiration date (FIFO)
        $stmt = $pdo->prepare("
            SELECT batch_id, remaining_quantity, expiration_date, batch_number
            FROM product_batches 
            WHERE product_id = ? AND remaining_quantity > 0 
            ORDER BY expiration_date ASC, created_at ASC
        ");
        $stmt->execute([$product_id]);
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $total_available = array_sum(array_column($batches, 'remaining_quantity'));
        if ($total_available < $quantity_sold) {
            throw new Exception("Insufficient stock. Available: $total_available, Requested: $quantity_sold");
        }
        
        $remaining_to_sell = $quantity_sold;
        $used_batches = [];
        
        foreach ($batches as $batch) {
            if ($remaining_to_sell <= 0) break;
            
            $quantity_from_batch = min($remaining_to_sell, $batch['remaining_quantity']);
            $new_remaining = $batch['remaining_quantity'] - $quantity_from_batch;
            
            // Update batch remaining quantity
            $stmt = $pdo->prepare("UPDATE product_batches SET remaining_quantity = ? WHERE batch_id = ?");
            $stmt->execute([$new_remaining, $batch['batch_id']]);
            
            $used_batches[] = [
                'batch_id' => $batch['batch_id'],
                'batch_number' => $batch['batch_number'],
                'quantity' => $quantity_from_batch,
                'expiration_date' => $batch['expiration_date']
            ];
            
            $remaining_to_sell -= $quantity_from_batch;
        }
        
        // Update product stock
        $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = current_stock - ? WHERE product_id = ?");
        $stmt->execute([$quantity_sold, $product_id]);
        
        // Record stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_type, created_by) 
            VALUES (?, 2, ?, (SELECT current_stock FROM product_stock WHERE product_id = ?) + ?, (SELECT current_stock FROM product_stock WHERE product_id = ?), 'FIFO Sale', 'sale', ?)
        ");
        $stmt->execute([$product_id, $quantity_sold, $product_id, $quantity_sold, $product_id, $_SESSION['user_id']]);
        
        $batch_info = implode(', ', array_map(fn($b) => "Batch {$b['batch_number']} ({$b['quantity']} units, expires {$b['expiration_date']})", $used_batches));
        logHistory($pdo, 'FIFO Sale', "Product ID: $product_id, Quantity: $quantity_sold, Batches: $batch_info", $_SESSION['username']);
        
        $_SESSION['success'] = "FIFO sale processed successfully! Used batches: " . $batch_info;
    } catch (Exception $e) {
        $_SESSION['error'] = "Error processing FIFO sale: " . $e->getMessage();
    }
    header("Location: fifo_management.php");
    exit;
}

// Get products with batch information
$stmt = $pdo->query("
    SELECT 
        p.product_id,
        p.product_name,
        c.category_name,
        ps.current_stock,
        ps.expiration_date,
        p.is_perishable,
        p.shelf_life_days,
        p.storage_requirements,
        COALESCE(SUM(pb.remaining_quantity), 0) as total_batch_quantity,
        COUNT(pb.batch_id) as batch_count
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.category_id
    LEFT JOIN product_stock ps ON ps.product_id = p.product_id
    LEFT JOIN product_batches pb ON pb.product_id = p.product_id AND pb.remaining_quantity > 0
    WHERE p.is_archive = 0
    GROUP BY p.product_id, p.product_name, c.category_name, ps.current_stock, ps.expiration_date, p.is_perishable, p.shelf_life_days, p.storage_requirements
    ORDER BY p.is_perishable DESC, ps.expiration_date ASC
");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get batch details for each product
foreach ($products as &$product) {
    $stmt = $pdo->prepare("
        SELECT batch_id, batch_number, remaining_quantity, expiration_date, created_at
        FROM product_batches 
        WHERE product_id = ? AND remaining_quantity > 0 
        ORDER BY expiration_date ASC, created_at ASC
    ");
    $stmt->execute([$product['product_id']]);
    $product['batches'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FIFO Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .batch-item {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 12px;
            margin-bottom: 8px;
        }
        .batch-expired { background-color: #f8d7da; border-color: #f5c6cb; }
        .batch-critical { background-color: #fff3cd; border-color: #ffeaa7; }
        .batch-warning { background-color: #d1ecf1; border-color: #bee5eb; }
        .batch-good { background-color: #d4edda; border-color: #c3e6cb; }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

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
                    <i class="fa fa-sort-amount-asc me-3" style="color: #7F1734;"></i>FIFO Management
                </h1>
                <p class="text-muted">First In, First Out inventory management for perishable products</p>
            </div>
        </div>

        <!-- Products with Batches -->
        <div class="row">
            <?php foreach ($products as $product): ?>
            <div class="col-lg-6 mb-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><?php echo htmlspecialchars($product['product_name']); ?></h5>
                        <div>
                            <?php if ($product['is_perishable']): ?>
                                <span class="badge bg-warning">Perishable</span>
                            <?php endif; ?>
                            <span class="badge bg-primary"><?php echo $product['current_stock']; ?> units</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-6">
                                <small class="text-muted">Category</small>
                                <div><?php echo htmlspecialchars($product['category_name']); ?></div>
                            </div>
                            <div class="col-6">
                                <small class="text-muted">Storage</small>
                                <div><?php echo ucfirst(str_replace('_', ' ', $product['storage_requirements'])); ?></div>
                            </div>
                        </div>
                        
                        <?php if (!empty($product['batches'])): ?>
                            <h6>Batches (FIFO Order):</h6>
                            <?php foreach ($product['batches'] as $batch): ?>
                                <?php
                                $days_until_expiry = (strtotime($batch['expiration_date']) - time()) / (60 * 60 * 24);
                                $batch_class = '';
                                if ($days_until_expiry < 0) $batch_class = 'batch-expired';
                                elseif ($days_until_expiry <= 3) $batch_class = 'batch-critical';
                                elseif ($days_until_expiry <= 7) $batch_class = 'batch-warning';
                                else $batch_class = 'batch-good';
                                ?>
                                <div class="batch-item <?php echo $batch_class; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <strong>Batch <?php echo htmlspecialchars($batch['batch_number']); ?></strong>
                                            <br>
                                            <small>Qty: <?php echo $batch['remaining_quantity']; ?> | Expires: <?php echo date('M d, Y', strtotime($batch['expiration_date'])); ?></small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($days_until_expiry < 0): ?>
                                                <span class="badge bg-danger">EXPIRED</span>
                                            <?php elseif ($days_until_expiry <= 3): ?>
                                                <span class="badge bg-warning"><?php echo ceil($days_until_expiry); ?> days</span>
                                            <?php elseif ($days_until_expiry <= 7): ?>
                                                <span class="badge bg-info"><?php echo ceil($days_until_expiry); ?> days</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?php echo ceil($days_until_expiry); ?> days</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                            
                            <!-- FIFO Sale Form -->
                            <form method="POST" class="mt-3">
                                <input type="hidden" name="product_id" value="<?php echo $product['product_id']; ?>">
                                <div class="input-group">
                                    <input type="number" name="quantity_sold" class="form-control" placeholder="Quantity to sell" min="1" max="<?php echo $product['total_batch_quantity']; ?>" required>
                                    <button type="submit" name="process_fifo_sale" class="btn btn-success">
                                        <i class="fa fa-shopping-cart me-1"></i> Process FIFO Sale
                                    </button>
                                </div>
                            </form>
                        <?php else: ?>
                            <div class="text-muted text-center py-3">
                                <i class="fa fa-info-circle me-2"></i>No batches available
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </main>
</body>
</html>
