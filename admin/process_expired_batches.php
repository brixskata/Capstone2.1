<?php
/**
 * Automatic Expired Batch Processor
 * 
 * This script automatically detects expired batches and records them as pull outs
 * in the stock_adjustment table. It should be run daily via cron job.
 * 
 * Usage: php process_expired_batches.php
 * Cron: 0 1 * * * /usr/bin/php /path/to/admin/process_expired_batches.php
 */

// Set timezone
date_default_timezone_set('Asia/Manila');

// Include database connection
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/batch_manager.php';

// Initialize batch manager
$batchManager = new BatchManager($pdo);

// Log function for this script
function logMessage($message) {
    $timestamp = date('Y-m-d H:i:s');
    echo "[$timestamp] $message\n";
    
    // Also log to file
    $logFile = __DIR__ . '/expired_batches.log';
    file_put_contents($logFile, "[$timestamp] $message\n", FILE_APPEND | LOCK_EX);
}

try {
    logMessage("Starting expired batch processing...");
    
    // Find expired batches that are still active and have remaining quantity
    $stmt = $pdo->prepare("
        SELECT 
            pb.batch_id,
            pb.product_id,
            pb.batch_number,
            pb.quantity_remaining,
            pb.expiration_date,
            pb.supplier_id,
            pb.brand_id,
            p.product_name,
            s.name as supplier_name,
            b.name as brand_name
        FROM product_batches pb
        JOIN products p ON pb.product_id = p.product_id
        LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
        LEFT JOIN brands b ON pb.brand_id = b.id
        WHERE pb.is_active = 1 
        AND pb.quantity_remaining > 0
        AND pb.expiration_date IS NOT NULL
        AND pb.expiration_date < CURDATE()
        ORDER BY pb.expiration_date ASC
    ");
    
    $stmt->execute();
    $expiredBatches = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $processedCount = 0;
    $errorCount = 0;
    
    logMessage("Found " . count($expiredBatches) . " expired batches to process");
    
    foreach ($expiredBatches as $batch) {
        try {
            // Start transaction for each batch
            $pdo->beginTransaction();
            
            $batchId = $batch['batch_id'];
            $productId = $batch['product_id'];
            $quantity = $batch['quantity_remaining'];
            $expirationDate = $batch['expiration_date'];
            $supplierId = $batch['supplier_id'];
            $brandId = $batch['brand_id'];
            
            logMessage("Processing batch: {$batch['batch_number']} - {$batch['product_name']} (Qty: $quantity)");
            
            // Get current stock for this product
            $stmt = $pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
            $stmt->execute([$productId]);
            $currentStock = $stmt->fetchColumn() ?: 0;
            
            // Calculate new stock (subtract expired quantity)
            $newStock = $currentStock - $quantity;
            
            if ($newStock < 0) {
                throw new Exception("Cannot subtract $quantity from current stock of $currentStock. Result would be negative.");
            }
            
            // Update product stock
            $stmt = $pdo->prepare("UPDATE product_stock SET current_stock = ? WHERE product_id = ?");
            $stmt->execute([$newStock, $productId]);
            
            // Set batch quantity remaining to 0 (effectively deactivating it)
            $stmt = $pdo->prepare("UPDATE product_batches SET quantity_remaining = 0, is_active = 0 WHERE batch_id = ?");
            $stmt->execute([$batchId]);
            
            // Record stock adjustment
            $stmt = $pdo->prepare("
                INSERT INTO stock_adjustment 
                (product_id, adjustment_type_id, quantity, previous_stock, new_stock, reason, notes, supplier_id, expiration_date, created_by) 
                VALUES (?, ?, ?, ?, ?, 'Expired', ?, ?, ?, ?)
            ");
            
            $notes = "Automatic expiration processing - Batch: {$batch['batch_number']}, Expired: $expirationDate";
            $stmt->execute([
                $productId, 
                2, // adjustment_type_id = 2 (subtract)
                $quantity, 
                $currentStock, 
                $newStock, 
                $notes,
                $supplierId,
                $expirationDate,
                2 // created_by = 2 (system)
            ]);
            
            // Record stock movement
            $stmt = $pdo->prepare("
                INSERT INTO stock_movements 
                (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_type, created_by) 
                VALUES (?, 4, ?, ?, ?, 'Automatic Expiration: Batch {$batch['batch_number']}', 'expiration', 2)
            ");
            $stmt->execute([$productId, $quantity, $currentStock, $newStock]);
            
            // Log history
            logHistory($pdo, 'Automatic Expiration', 
                "Product: {$batch['product_name']}, Batch: {$batch['batch_number']}, Quantity: $quantity, Expired: $expirationDate", 
                'system'
            );
            
            $pdo->commit();
            $processedCount++;
            
            logMessage("✓ Successfully processed batch: {$batch['batch_number']}");
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $errorCount++;
            logMessage("✗ Error processing batch {$batch['batch_number']}: " . $e->getMessage());
        }
    }
    
    logMessage("Processing completed. Processed: $processedCount, Errors: $errorCount");
    
    // Send summary email if there were errors (optional)
    if ($errorCount > 0) {
        logMessage("Warning: $errorCount batches had errors during processing");
    }
    
} catch (Exception $e) {
    logMessage("Fatal error: " . $e->getMessage());
    exit(1);
}

logMessage("Expired batch processing finished successfully");
?>
