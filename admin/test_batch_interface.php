<?php
/**
 * Simple Test Interface for Batch System
 * Quick way to test batch functionality through the web interface
 */

include '../includes/db.php';
include_once '../includes/batch_manager.php';
session_start();

// Initialize batch manager
$batchManager = new BatchManager($pdo);

// Handle test actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    try {
        switch ($_POST['action']) {
            case 'create_test_batch':
                $product_id = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                $supplier_id = (int)$_POST['supplier_id'];
                
                // Get a valid user ID
                $stmt = $pdo->query("SELECT user_id FROM users LIMIT 1");
                $user = $stmt->fetch();
                $user_id = $user ? $user['user_id'] : null;
                
                $batch_data = [
                    'product_id' => $product_id,
                    'supplier_id' => $supplier_id,
                    'quantity_received' => $quantity,
                    'unit_cost' => 25.00,
                    'expiration_date' => date('Y-m-d', strtotime('+30 days')),
                    'received_date' => date('Y-m-d'),
                    'created_by' => $user_id,
                    'reference_type' => 'manual',
                    'notes' => 'Test batch from interface'
                ];
                
                $batch_id = $batchManager->createBatch($batch_data);
                $message = "✅ Test batch created successfully! Batch ID: {$batch_id}";
                break;
                
            case 'consume_test_stock':
                $product_id = (int)$_POST['product_id'];
                $quantity = (int)$_POST['quantity'];
                
                // Get a valid user ID
                $stmt = $pdo->query("SELECT user_id FROM users LIMIT 1");
                $user = $stmt->fetch();
                $user_id = $user ? $user['user_id'] : null;
                
                $batches_used = $batchManager->consumeStock(
                    $product_id,
                    $quantity,
                    'sale',
                    'test',
                    999,
                    $user_id,
                    'Test consumption from interface'
                );
                
                $message = "✅ Consumed {$quantity} units from " . count($batches_used) . " batch(es)";
                break;
        }
    } catch (Exception $e) {
        $message = "❌ Error: " . $e->getMessage();
    }
}

// Get products and suppliers for the form
$stmt = $pdo->query("SELECT product_id, product_name FROM products WHERE is_archive = 0 ORDER BY product_name LIMIT 10");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);

$stmt = $pdo->query("SELECT supplier_id, name FROM suppliers WHERE is_archive = 0 ORDER BY name LIMIT 10");
$suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Batch System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fa fa-flask me-2"></i>Batch System Test Interface
                        </h4>
                    </div>
                    <div class="card-body">
                        <?php if (isset($message)): ?>
                            <div class="alert alert-info">
                                <?= $message ?>
                            </div>
                        <?php endif; ?>
                        
                        <div class="row">
                            <!-- Create Test Batch -->
                            <div class="col-md-6">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0">Create Test Batch</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="create_test_batch">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Product</label>
                                                <select name="product_id" class="form-select" required>
                                                    <option value="">Select Product</option>
                                                    <?php foreach ($products as $product): ?>
                                                        <option value="<?= $product['product_id'] ?>">
                                                            <?= htmlspecialchars($product['product_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Supplier</label>
                                                <select name="supplier_id" class="form-select" required>
                                                    <option value="">Select Supplier</option>
                                                    <?php foreach ($suppliers as $supplier): ?>
                                                        <option value="<?= $supplier['supplier_id'] ?>">
                                                            <?= htmlspecialchars($supplier['name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Quantity</label>
                                                <input type="number" name="quantity" class="form-control" value="50" min="1" required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-success w-100">
                                                <i class="fa fa-plus me-2"></i>Create Test Batch
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Consume Test Stock -->
                            <div class="col-md-6">
                                <div class="card border-warning">
                                    <div class="card-header bg-warning text-dark">
                                        <h5 class="mb-0">Test FIFO Consumption</h5>
                                    </div>
                                    <div class="card-body">
                                        <form method="POST">
                                            <input type="hidden" name="action" value="consume_test_stock">
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Product</label>
                                                <select name="product_id" class="form-select" required>
                                                    <option value="">Select Product</option>
                                                    <?php foreach ($products as $product): ?>
                                                        <option value="<?= $product['product_id'] ?>">
                                                            <?= htmlspecialchars($product['product_name']) ?>
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </div>
                                            
                                            <div class="mb-3">
                                                <label class="form-label">Quantity to Consume</label>
                                                <input type="number" name="quantity" class="form-control" value="10" min="1" required>
                                            </div>
                                            
                                            <button type="submit" class="btn btn-warning w-100">
                                                <i class="fa fa-minus me-2"></i>Test FIFO Consumption
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <h5>Quick Links</h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <a href="batch_management.php" class="btn btn-outline-primary">
                                    <i class="fa fa-boxes me-1"></i>Batch Management
                                </a>
                                <a href="restocking.php" class="btn btn-outline-success">
                                    <i class="fa fa-plus me-1"></i>Restocking
                                </a>
                                <a href="stock_adjustment.php" class="btn btn-outline-info">
                                    <i class="fa fa-edit me-1"></i>Stock Adjustment
                                </a>
                                <a href="test_batch_system.php" class="btn btn-outline-secondary">
                                    <i class="fa fa-flask me-1"></i>Full Test Script
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
