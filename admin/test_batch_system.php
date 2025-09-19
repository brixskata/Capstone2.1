<?php
/**
 * Test Script for Batch Management System
 * This script helps you test the FIFO batch functionality
 */

include '../includes/db.php';
include_once '../includes/batch_manager.php';
session_start();

// Initialize batch manager
$batchManager = new BatchManager($pdo);

echo "<h2>🧪 Testing Batch Management System</h2>";

// Test 1: Check if tables exist
echo "<h3>1. Database Structure Check</h3>";
try {
    $stmt = $pdo->query("SHOW TABLES LIKE 'product_batches'");
    $batches_table = $stmt->fetch();
    
    $stmt = $pdo->query("SHOW TABLES LIKE 'batch_movements'");
    $movements_table = $stmt->fetch();
    
    if ($batches_table && $movements_table) {
        echo "✅ Both tables exist: product_batches and batch_movements<br>";
    } else {
        echo "❌ Missing tables. Please run the SQL scripts first.<br>";
        exit;
    }
} catch (Exception $e) {
    echo "❌ Database error: " . $e->getMessage() . "<br>";
    exit;
}

// Test 2: Check table structure
echo "<h3>2. Table Structure Check</h3>";
try {
    $stmt = $pdo->query("DESCRIBE product_batches");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $required_columns = ['batch_id', 'product_id', 'supplier_id', 'quantity_received', 'quantity_remaining', 'unit_cost', 'expiration_date', 'received_date', 'reference_type', 'reference_id', 'notes', 'is_active'];
    
    $missing_columns = [];
    foreach ($required_columns as $required) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $required) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $missing_columns[] = $required;
        }
    }
    
    if (empty($missing_columns)) {
        echo "✅ All required columns exist in product_batches table<br>";
    } else {
        echo "❌ Missing columns: " . implode(', ', $missing_columns) . "<br>";
        echo "Please run the ALTER TABLE script to add missing columns.<br>";
    }
} catch (Exception $e) {
    echo "❌ Error checking table structure: " . $e->getMessage() . "<br>";
}

// Test 3: Test batch creation
echo "<h3>3. Test Batch Creation</h3>";
try {
    // Get a sample product
    $stmt = $pdo->query("SELECT product_id FROM products WHERE is_archive = 0 LIMIT 1");
    $product = $stmt->fetch();
    
    if ($product) {
        $product_id = $product['product_id'];
        
        // Get a valid user ID
        $stmt = $pdo->query("SELECT user_id FROM users LIMIT 1");
        $user = $stmt->fetch();
        $user_id = $user ? $user['user_id'] : null;
        
        // Get a valid supplier ID
        $stmt = $pdo->query("SELECT supplier_id FROM suppliers LIMIT 1");
        $supplier = $stmt->fetch();
        $supplier_id = $supplier ? $supplier['supplier_id'] : null;
        
        // Create a test batch
        $batch_data = [
            'product_id' => $product_id,
            'supplier_id' => $supplier_id,
            'quantity_received' => 100,
            'unit_cost' => 25.50,
            'expiration_date' => date('Y-m-d', strtotime('+30 days')),
            'received_date' => date('Y-m-d'),
            'created_by' => $user_id,
            'reference_type' => 'manual',
            'notes' => 'Test batch creation'
        ];
        
        $batch_id = $batchManager->createBatch($batch_data);
        echo "✅ Test batch created successfully with ID: {$batch_id}<br>";
        
        // Test 4: Test batch retrieval
        echo "<h3>4. Test Batch Retrieval</h3>";
        $batches = $batchManager->getAvailableBatches($product_id);
        echo "✅ Found " . count($batches) . " available batches for product {$product_id}<br>";
        
        // Test 5: Test stock consumption (FIFO)
        echo "<h3>5. Test FIFO Stock Consumption</h3>";
        $batches_used = $batchManager->consumeStock(
            $product_id,
            50, // Consume 50 units
            'sale',
            'test',
            999, // Test reference ID
            $user_id, // Use valid user ID
            'Test FIFO consumption'
        );
        
        echo "✅ Successfully consumed 50 units from " . count($batches_used) . " batch(es)<br>";
        foreach ($batches_used as $batch_used) {
            echo "&nbsp;&nbsp;• Batch {$batch_used['batch_number']}: {$batch_used['quantity_used']} units<br>";
        }
        
        // Test 6: Check remaining stock
        echo "<h3>6. Check Remaining Stock</h3>";
        $remaining_batches = $batchManager->getAvailableBatches($product_id);
        $total_remaining = array_sum(array_column($remaining_batches, 'quantity_remaining'));
        echo "✅ Total remaining stock: {$total_remaining} units<br>";
        
        // Clean up test data
        echo "<h3>7. Cleanup Test Data</h3>";
        $stmt = $pdo->prepare("DELETE FROM batch_movements WHERE reference_id = 999");
        $stmt->execute();
        
        $stmt = $pdo->prepare("DELETE FROM product_batches WHERE notes = 'Test batch creation'");
        $stmt->execute();
        
        echo "✅ Test data cleaned up<br>";
        
    } else {
        echo "❌ No products found. Please add some products first.<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Error during testing: " . $e->getMessage() . "<br>";
}

// Test 7: Integration with existing system
echo "<h3>8. Integration Test</h3>";
echo "<p>To test the full integration:</p>";
echo "<ol>";
echo "<li><strong>Test Restocking:</strong> Go to <a href='restocking.php'>Restocking</a> and add some stock. Check if batches are created.</li>";
echo "<li><strong>Test Stock Adjustment:</strong> Go to <a href='stock_adjustment.php'>Stock Adjustment</a> and add/subtract stock. Check if batches are created/consumed.</li>";
echo "<li><strong>Test Batch Management:</strong> Go to <a href='batch_management.php'>Batch Management</a> to view and manage batches.</li>";
echo "</ol>";

echo "<h3>🎉 Testing Complete!</h3>";
echo "<p>If all tests passed, your batch management system is working correctly!</p>";
?>
