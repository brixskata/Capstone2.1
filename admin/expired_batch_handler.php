<?php


include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/batch_manager.php';

// Set timezone
date_default_timezone_set('Asia/Manila');

class ExpiredBatchHandler {
    private $pdo;
    private $batchManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->batchManager = new BatchManager($pdo);
    }
    
    /**
     * Process all expired batches
     */
    public function processExpiredBatches() {
        $expiredBatches = $this->getExpiredBatches();
        $processedCount = 0;
        $errors = [];
        
        foreach ($expiredBatches as $batch) {
            try {
                $this->processExpiredBatch($batch);
                $processedCount++;
            } catch (Exception $e) {
                $errors[] = "Batch {$batch['batch_number']}: " . $e->getMessage();
            }
        }
        
        return [
            'processed' => $processedCount,
            'total' => count($expiredBatches),
            'errors' => $errors
        ];
    }
    
    /**
     * Get all expired batches that haven't been processed yet
     */
    private function getExpiredBatches() {
        $stmt = $this->pdo->prepare("
            SELECT 
                pb.batch_id,
                pb.product_id,
                pb.batch_number,
                pb.quantity_remaining,
                pb.expiration_date,
                pb.supplier_id,
                pb.brand_id,
                pb.unit_cost,
                p.product_name,
                s.name as supplier_name,
                b.name as brand_name
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id
            LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
            LEFT JOIN brands b ON pb.brand_id = b.id
            WHERE pb.expiration_date IS NOT NULL 
            AND pb.expiration_date < CURDATE()
            AND pb.quantity_remaining > 0
            AND pb.is_active = 1
            AND pb.is_processed_expired = 0
            ORDER BY pb.expiration_date ASC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Process a single expired batch
     */
    private function processExpiredBatch($batch) {
        $this->pdo->beginTransaction();
        
        try {
            // 1. Mark batch as processed to prevent duplicate processing
            $stmt = $this->pdo->prepare("
                UPDATE product_batches 
                SET is_processed_expired = 1, 
                    processed_expired_at = NOW()
                WHERE batch_id = ?
            ");
            $stmt->execute([$batch['batch_id']]);
            
            // 2. Update batch remaining quantity to 0
            $stmt = $this->pdo->prepare("
                UPDATE product_batches 
                SET quantity_remaining = 0
                WHERE batch_id = ?
            ");
            $stmt->execute([$batch['batch_id']]);
            
            // 3. Update product stock
            $stmt = $this->pdo->prepare("
                UPDATE product_stock 
                SET current_stock = current_stock - ?
                WHERE product_id = ?
            ");
            $stmt->execute([$batch['quantity_remaining'], $batch['product_id']]);
            
            // 4. Create stock adjustment record for pull-out report
            // Try with brand_id first, fallback if column doesn't exist
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO stock_adjustment 
                    (product_id, adjustment_type_id, quantity, previous_stock, new_stock, 
                     reason, notes, supplier_id, brand_id, expiration_date, created_by, created_at)
                    VALUES (?, 2, ?, ?, ?, 'Expired', ?, ?, ?, ?, 1, NOW())
                ");
                
                // Get current stock for previous_stock calculation
                $stmt2 = $this->pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
                $stmt2->execute([$batch['product_id']]);
                $current_stock = $stmt2->fetchColumn();
                
                $previous_stock = $current_stock + $batch['quantity_remaining'];
                $new_stock = $current_stock;
                
                $notes = "Automatic expiration processing - Batch: {$batch['batch_number']}, Expired: {$batch['expiration_date']}";
                
                $stmt->execute([
                    $batch['product_id'],
                    $batch['quantity_remaining'],
                    $previous_stock,
                    $new_stock,
                    $notes,
                    $batch['supplier_id'],
                    $batch['brand_id'],
                    $batch['expiration_date']
                ]);
            } catch (PDOException $e) {
                // Fallback without brand_id if column doesn't exist
                $stmt = $this->pdo->prepare("
                    INSERT INTO stock_adjustment 
                    (product_id, adjustment_type_id, quantity, previous_stock, new_stock, 
                     reason, notes, supplier_id, expiration_date, created_by, created_at)
                    VALUES (?, 2, ?, ?, ?, 'Expired', ?, ?, ?, 1, NOW())
                ");
                
                $stmt->execute([
                    $batch['product_id'],
                    $batch['quantity_remaining'],
                    $previous_stock,
                    $new_stock,
                    $notes,
                    $batch['supplier_id'],
                    $batch['expiration_date']
                ]);
            }
            
            $adjustment_id = $this->pdo->lastInsertId();
            
            // 5. Record stock movement
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_movements 
                (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, 
                 reason, reference_id, reference_type, created_by, created_at)
                VALUES (?, 2, ?, ?, ?, 'Automatic Expiration', ?, 'stock_adjustment', 1, NOW())
            ");
            $stmt->execute([
                $batch['product_id'],
                $batch['quantity_remaining'],
                $previous_stock,
                $new_stock,
                $adjustment_id
            ]);
            
            // 6. Log history
            logHistory($this->pdo, 'Automatic Expiration', 
                "Product: {$batch['product_name']}, Batch: {$batch['batch_number']}, " .
                "Quantity: {$batch['quantity_remaining']}, Expired: {$batch['expiration_date']}", 
                'system'
            );
            
            $this->pdo->commit();
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
    
    /**
     * Get batches expiring within specified days
     */
    public function getExpiringBatches($days_ahead = 7) {
        $stmt = $this->pdo->prepare("
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
                b.name as brand_name,
                DATEDIFF(pb.expiration_date, CURDATE()) as days_until_expiry
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id
            LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
            LEFT JOIN brands b ON pb.brand_id = b.id
            WHERE pb.expiration_date IS NOT NULL 
            AND pb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND pb.expiration_date > CURDATE()
            AND pb.quantity_remaining > 0
            AND pb.is_active = 1
            ORDER BY pb.expiration_date ASC
        ");
        
        $stmt->execute([$days_ahead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Manual pull out of a specific batch
     */
    public function manualPullOut($batch_id, $reason = 'Manual Pull Out', $notes = '') {
        $stmt = $this->pdo->prepare("
            SELECT 
                pb.batch_id,
                pb.product_id,
                pb.batch_number,
                pb.quantity_remaining,
                pb.expiration_date,
                pb.supplier_id,
                pb.brand_id,
                pb.unit_cost,
                p.product_name,
                s.name as supplier_name,
                b.name as brand_name
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id
            LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
            LEFT JOIN brands b ON pb.brand_id = b.id
            WHERE pb.batch_id = ? AND pb.quantity_remaining > 0 AND pb.is_active = 1
        ");
        
        $stmt->execute([$batch_id]);
        $batch = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$batch) {
            throw new Exception("Batch not found or already processed");
        }
        
        $this->pdo->beginTransaction();
        
        try {
            // Mark batch as processed
            $stmt = $this->pdo->prepare("
                UPDATE product_batches 
                SET is_processed_expired = 1, 
                    processed_expired_at = NOW(),
                    quantity_remaining = 0
                WHERE batch_id = ?
            ");
            $stmt->execute([$batch_id]);
            
            // Update product stock
            $stmt = $this->pdo->prepare("
                UPDATE product_stock 
                SET current_stock = current_stock - ?
                WHERE product_id = ?
            ");
            $stmt->execute([$batch['quantity_remaining'], $batch['product_id']]);
            
            // Create stock adjustment record
            // Try with brand_id first, fallback if column doesn't exist
            try {
                $stmt = $this->pdo->prepare("
                    INSERT INTO stock_adjustment 
                    (product_id, adjustment_type_id, quantity, previous_stock, new_stock, 
                     reason, notes, supplier_id, brand_id, expiration_date, created_by, created_at)
                    VALUES (?, 2, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $current_stock = $this->pdo->prepare("SELECT current_stock FROM product_stock WHERE product_id = ?");
                $current_stock->execute([$batch['product_id']]);
                $stock = $current_stock->fetchColumn();
                
                $previous_stock = $stock + $batch['quantity_remaining'];
                $new_stock = $stock;
                
                $full_notes = "Manual pull out - Batch: {$batch['batch_number']}";
                if ($notes) {
                    $full_notes .= " - {$notes}";
                }
                
                $stmt->execute([
                    $batch['product_id'],
                    $batch['quantity_remaining'],
                    $previous_stock,
                    $new_stock,
                    $reason,
                    $full_notes,
                    $batch['supplier_id'],
                    $batch['brand_id'],
                    $batch['expiration_date'],
                    $_SESSION['user_id'] ?? 1
                ]);
            } catch (PDOException $e) {
                // Fallback without brand_id if column doesn't exist
                $stmt = $this->pdo->prepare("
                    INSERT INTO stock_adjustment 
                    (product_id, adjustment_type_id, quantity, previous_stock, new_stock, 
                     reason, notes, supplier_id, expiration_date, created_by, created_at)
                    VALUES (?, 2, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                
                $stmt->execute([
                    $batch['product_id'],
                    $batch['quantity_remaining'],
                    $previous_stock,
                    $new_stock,
                    $reason,
                    $full_notes,
                    $batch['supplier_id'],
                    $batch['expiration_date'],
                    $_SESSION['user_id'] ?? 1
                ]);
            }
            
            $adjustment_id = $this->pdo->lastInsertId();
            
            // Record stock movement
            $stmt = $this->pdo->prepare("
                INSERT INTO stock_movements 
                (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, 
                 reason, reference_id, reference_type, created_by, created_at)
                VALUES (?, 2, ?, ?, ?, ?, ?, 'stock_adjustment', ?, NOW())
            ");
            $stmt->execute([
                $batch['product_id'],
                $batch['quantity_remaining'],
                $previous_stock,
                $new_stock,
                $reason,
                $adjustment_id,
                $_SESSION['user_id'] ?? 1
            ]);
            
            // Log history
            logHistory($this->pdo, 'Manual Pull Out', 
                "Product: {$batch['product_name']}, Batch: {$batch['batch_number']}, " .
                "Quantity: {$batch['quantity_remaining']}, Reason: {$reason}", 
                $_SESSION['username'] ?? 'system'
            );
            
            $this->pdo->commit();
            return true;
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}

// Handle manual execution (for testing or manual runs)
if (isset($_GET['action']) && $_GET['action'] === 'process_expired') {
    session_start();
    
    // Check if user is admin
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $handler = new ExpiredBatchHandler($pdo);
    $result = $handler->processExpiredBatches();
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Handle manual pull out
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'manual_pullout') {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $batch_id = (int)$_POST['batch_id'];
    $reason = $_POST['reason'] ?? 'Manual Pull Out';
    $notes = $_POST['notes'] ?? '';
    
    try {
        $handler = new ExpiredBatchHandler($pdo);
        $handler->manualPullOut($batch_id, $reason, $notes);
        
        echo json_encode(['success' => true, 'message' => 'Batch pulled out successfully']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}

// Handle AJAX request for expiring batches
if (isset($_GET['action']) && $_GET['action'] === 'get_expiring_batches') {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $days_ahead = (int)($_GET['days'] ?? 7);
    $handler = new ExpiredBatchHandler($pdo);
    $batches = $handler->getExpiringBatches($days_ahead);
    
    header('Content-Type: application/json');
    echo json_encode($batches);
    exit;
}
?>
