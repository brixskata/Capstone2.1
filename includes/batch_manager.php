<?php
/**
 * Product Batch Manager
 * Handles FIFO inventory management with batch tracking
 */

class BatchManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
 
    public function createBatch($data) {
        // Check if there's already an active transaction
        $has_transaction = $this->pdo->inTransaction();
        
        try {
            // Only start a transaction if there isn't one already
            if (!$has_transaction) {
                $this->pdo->beginTransaction();
            }
            
            // Generate batch number if not provided
            if (empty($data['batch_number'])) {
                $data['batch_number'] = $this->generateBatchNumber($data['product_id']);
            }
            
            $stmt = $this->pdo->prepare("
                INSERT INTO product_batches 
                (product_id, supplier_id, brand_id, batch_number, quantity_received, quantity_remaining, 
                 unit_cost, expiration_date, received_date, created_by, reference_type, 
                 reference_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['product_id'],
                $data['supplier_id'] ?? null,
                $data['brand_id'] ?? null,
                $data['batch_number'],
                $data['quantity_received'],
                $data['quantity_received'], // Initially, remaining = received
                $data['unit_cost'] ?? null,
                $data['expiration_date'] ?? null,
                $data['received_date'] ?? date('Y-m-d'),
                $data['created_by'] ?? null,
                $data['reference_type'] ?? 'restock',
                $data['reference_id'] ?? null,
                $data['notes'] ?? null
            ]);
            
            $batch_id = $this->pdo->lastInsertId();
            
            // Only commit if we started the transaction
            if (!$has_transaction) {
                $this->pdo->commit();
            }
            
            // Trigger automatic reorder point calculation for new batches
            if ($data['brand_id']) {
                $this->triggerAutomaticROPCalculation($data['product_id'], $data['brand_id']);
            }
            
            return $batch_id;
            
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if (!$has_transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw new Exception("Failed to create batch: " . $e->getMessage());
        }
    }
    
    /**
     * Get available batches for a product (FIFO order)
     */
    public function getAvailableBatches($product_id, $quantity_needed = null, $supplier_id = null, $brand_id = null) {
        $sql = "
            SELECT * FROM product_batches 
            WHERE product_id = ? AND quantity_remaining > 0 AND is_active = 1
        ";
        
        $params = [$product_id];
        
        // Filter by supplier if provided
        if ($supplier_id) {
            $sql .= " AND supplier_id = ?";
            $params[] = $supplier_id;
        }
        
        // Filter by brand if provided
        if ($brand_id) {
            $sql .= " AND brand_id = ?";
            $params[] = $brand_id;
        }
        
        $sql .= " ORDER BY expiration_date ASC, received_date ASC, batch_id ASC";
        
        if ($quantity_needed) {
            $sql .= " LIMIT ?";
            $params[] = $quantity_needed;
        }
        
        error_log("getAvailableBatches SQL: $sql");
        error_log("getAvailableBatches params: " . json_encode($params));
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        error_log("getAvailableBatches result: " . json_encode($result));
        return $result;
    }
    
    /**
     * Get combined stock for a product (optionally filtered by brand)
     */
    public function getCombinedStock($product_id, $brand_id = null) {
        $available_batches = $this->getAvailableBatches($product_id, null, null, $brand_id);
        $total_stock = 0;
        foreach ($available_batches as $batch) {
            $total_stock += floatval($batch['quantity_remaining']);
        }
        return $total_stock;
    }
    
    /**
     * Consume stock from batches (FIFO)
     */
    public function consumeStock($product_id, $quantity, $movement_type, $reference_type = null, $reference_id = null, $created_by = null, $notes = null, $supplier_id = null, $brand_id = null) {
        // Check if there's already an active transaction
        $has_transaction = $this->pdo->inTransaction();
        
        error_log("BatchManager::consumeStock called with: product_id=$product_id, quantity=$quantity, brand_id=$brand_id");
        
        try {
            // Only start a transaction if there isn't one already
            if (!$has_transaction) {
                $this->pdo->beginTransaction();
            }
            
            $remaining_quantity = $quantity;
            $batches_used = [];
            
            // Get available batches in FIFO order (filtered by supplier and brand if provided)
            error_log("Getting available batches for product_id=$product_id, supplier_id=$supplier_id, brand_id=$brand_id");
            $batches = $this->getAvailableBatches($product_id, null, $supplier_id, $brand_id);
            error_log("Found " . count($batches) . " available batches");
            
            foreach ($batches as $batch) {
                if ($remaining_quantity <= 0) break;
                
                $consume_from_batch = min($remaining_quantity, $batch['quantity_remaining']);
                
                // Update batch remaining quantity
                $stmt = $this->pdo->prepare("
                    UPDATE product_batches 
                    SET quantity_remaining = quantity_remaining - ? 
                    WHERE batch_id = ?
                ");
                $stmt->execute([$consume_from_batch, $batch['batch_id']]);
                
                // Record batch movement
                $this->recordBatchMovement([
                    'batch_id' => $batch['batch_id'],
                    'product_id' => $product_id,
                    'movement_type' => $movement_type,
                    'quantity' => $consume_from_batch,
                    'reference_type' => $reference_type,
                    'reference_id' => $reference_id,
                    'created_by' => $created_by,
                    'notes' => $notes
                ]);
                
                $batches_used[] = [
                    'batch_id' => $batch['batch_id'],
                    'batch_number' => $batch['batch_number'],
                    'quantity_used' => $consume_from_batch,
                    'expiration_date' => $batch['expiration_date']
                ];
                
                $remaining_quantity -= $consume_from_batch;
            }
            
            if ($remaining_quantity > 0) {
                $supplier_context = $supplier_id ? " from the specified supplier" : "";
                throw new Exception("Insufficient stock{$supplier_context}. Need {$quantity}, but only " . ($quantity - $remaining_quantity) . " available.");
            }
            
            // Only commit if we started the transaction
            if (!$has_transaction) {
                $this->pdo->commit();
            }
            
            // Trigger automatic reorder point calculation for sales
            if ($movement_type === 'sale' && $brand_id) {
                $this->triggerAutomaticROPCalculation($product_id, $brand_id);
            }
            
            return $batches_used;
            
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if (!$has_transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }
    
    /**
     * Trigger automatic reorder point calculation for a specific product-brand combination
     */
    private function triggerAutomaticROPCalculation($product_id, $brand_id) {
        try {
            // Include the reorder point calculator
            require_once __DIR__ . '/reorder_point_calculator.php';
            
            $ropCalculator = new ReorderPointCalculator($this->pdo);
            
            // Calculate ADS for this specific product-brand combination
            $sales_data = $ropCalculator->calculateADS($product_id, $brand_id);
            $ads = $sales_data['ads'];
            
            // Classify movement
            $movement_type = $ropCalculator->classifyMovement($ads);
            
            // Calculate reorder point
            $rop = $ropCalculator->calculateReorderPoint($ads, $movement_type);
            
            // Update the brand-product stock record
            $ropCalculator->updateBrandProductStock($product_id, $brand_id, $ads, $rop, $movement_type);
            
            // Also update product_stock table for backward compatibility
            $stmt = $this->pdo->prepare("
                UPDATE product_stock 
                SET reorder_point = ? 
                WHERE product_id = ?
            ");
            $stmt->execute([$rop, $product_id]);
            
            error_log("Automatic ROP calculation completed for product_id=$product_id, brand_id=$brand_id: ADS=$ads, ROP=$rop, Type=$movement_type");
            
        } catch (Exception $e) {
            // Log error but don't fail the main operation
            error_log("Automatic ROP calculation failed for product_id=$product_id, brand_id=$brand_id: " . $e->getMessage());
        }
    }
    
    /**
     * Record batch movement
     */
    private function recordBatchMovement($data) {
        $stmt = $this->pdo->prepare("
            INSERT INTO batch_movements 
            (batch_id, product_id, movement_type, quantity, reference_type, reference_id, created_by, notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $data['batch_id'],
            $data['product_id'],
            $data['movement_type'],
            $data['quantity'],
            $data['reference_type'],
            $data['reference_id'],
            $data['created_by'],
            $data['notes']
        ]);
    }
    
    /**
     * Get batch details with supplier info
     */
    public function getBatchDetails($batch_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pb.*,
                p.product_name,
                s.name as supplier_name,
                u.name as uom_name
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id
            LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
            LEFT JOIN uom u ON p.uom_id = u.uom_id
            WHERE pb.batch_id = ?
        ");
        
        $stmt->execute([$batch_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all batches for a product
     */
    public function getProductBatches($product_id, $include_inactive = false) {
        $sql = "
            SELECT 
                pb.*,
                s.name as supplier_name,
                u.name as uom_name
            FROM product_batches pb
            LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
            LEFT JOIN uom u ON pb.product_id = u.uom_id
            WHERE pb.product_id = ?
        ";
        
        if (!$include_inactive) {
            $sql .= " AND pb.is_active = 1";
        }
        
        $sql .= " ORDER BY pb.received_date DESC, pb.batch_id DESC";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$product_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get expiring batches (within specified days)
     */
    public function getExpiringBatches($days_ahead = 7) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pb.*,
                p.product_name,
                s.name as supplier_name,
                DATEDIFF(pb.expiration_date, CURDATE()) as days_until_expiry
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id
            LEFT JOIN suppliers s ON pb.supplier_id = s.supplier_id
            WHERE pb.expiration_date IS NOT NULL 
            AND pb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND pb.quantity_remaining > 0
            AND pb.is_active = 1
            ORDER BY pb.expiration_date ASC
        ");
        
        $stmt->execute([$days_ahead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Generate unique batch number
     */
    private function generateBatchNumber($product_id) {
        $date = date('Ymd');
        $stmt = $this->pdo->prepare("
            SELECT COUNT(*) FROM product_batches 
            WHERE product_id = ? AND DATE(created_at) = CURDATE()
        ");
        $stmt->execute([$product_id]);
        $count = $stmt->fetchColumn() + 1;
        
        return "B{$product_id}-{$date}-" . str_pad($count, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Update batch (for adjustments)
     */
    public function updateBatch($batch_id, $data) {
        $fields = [];
        $values = [];
        
        $allowed_fields = ['quantity_remaining', 'expiration_date', 'notes', 'is_active'];
        
        foreach ($data as $field => $value) {
            if (in_array($field, $allowed_fields)) {
                $fields[] = "{$field} = ?";
                $values[] = $value;
            }
        }
        
        if (empty($fields)) {
            return false;
        }
        
        $values[] = $batch_id;
        
        $stmt = $this->pdo->prepare("
            UPDATE product_batches 
            SET " . implode(', ', $fields) . " 
            WHERE batch_id = ?
        ");
        
        return $stmt->execute($values);
    }
    
    /**
     * Restore stock to batches when orders are cancelled
     */
    public function restoreStock($order_id, $created_by = null, $notes = null) {
        // Check if there's already an active transaction
        $has_transaction = $this->pdo->inTransaction();
        
        try {
            // Only start a transaction if there isn't one already
            if (!$has_transaction) {
                $this->pdo->beginTransaction();
            }
            
            // Get order items with batch information
            $stmt = $this->pdo->prepare("
                SELECT 
                    oi.product_id,
                    oi.brand_id,
                    oi.batch_id,
                    oi.quantity,
                    pb.batch_number,
                    pb.expiration_date
                FROM order_items oi
                LEFT JOIN product_batches pb ON oi.batch_id = pb.batch_id
                WHERE oi.order_id = ?
            ");
            $stmt->execute([$order_id]);
            $order_items = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($order_items)) {
                throw new Exception("No order items found for order ID: $order_id");
            }
            
            $restored_items = [];
            
            foreach ($order_items as $item) {
                $product_id = $item['product_id'];
                $brand_id = $item['brand_id'];
                $batch_id = $item['batch_id'];
                $quantity = $item['quantity'];
                
                if ($batch_id) {
                    // Restore to specific batch
                    $stmt = $this->pdo->prepare("
                        UPDATE product_batches 
                        SET quantity_remaining = quantity_remaining + ? 
                        WHERE batch_id = ? AND is_active = 1
                    ");
                    $stmt->execute([$quantity, $batch_id]);
                    
                    if ($stmt->rowCount() == 0) {
                        throw new Exception("Failed to restore stock to batch ID: $batch_id");
                    }
                    
                    // Record batch movement
                    $this->recordBatchMovement([
                        'batch_id' => $batch_id,
                        'product_id' => $product_id,
                        'movement_type' => 'restore',
                        'quantity' => $quantity,
                        'reference_type' => 'order_cancellation',
                        'reference_id' => $order_id,
                        'created_by' => $created_by,
                        'notes' => $notes ?: "Order cancellation - stock restored"
                    ]);
                    
                    $restored_items[] = [
                        'batch_id' => $batch_id,
                        'batch_number' => $item['batch_number'],
                        'quantity_restored' => $quantity,
                        'expiration_date' => $item['expiration_date']
                    ];
                } else {
                    // If no specific batch, restore to the most recent batch for this product-brand combination
                    $stmt = $this->pdo->prepare("
                        SELECT batch_id, batch_number, expiration_date
                        FROM product_batches 
                        WHERE product_id = ? 
                        AND brand_id = ? 
                        AND is_active = 1 
                        AND quantity_remaining > 0
                        ORDER BY received_date DESC, batch_id DESC
                        LIMIT 1
                    ");
                    $stmt->execute([$product_id, $brand_id]);
                    $target_batch = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($target_batch) {
                        $stmt = $this->pdo->prepare("
                            UPDATE product_batches 
                            SET quantity_remaining = quantity_remaining + ? 
                            WHERE batch_id = ?
                        ");
                        $stmt->execute([$quantity, $target_batch['batch_id']]);
                        
                        // Record batch movement
                        $this->recordBatchMovement([
                            'batch_id' => $target_batch['batch_id'],
                            'product_id' => $product_id,
                            'movement_type' => 'restore',
                            'quantity' => $quantity,
                            'reference_type' => 'order_cancellation',
                            'reference_id' => $order_id,
                            'created_by' => $created_by,
                            'notes' => $notes ?: "Order cancellation - stock restored (no specific batch)"
                        ]);
                        
                        $restored_items[] = [
                            'batch_id' => $target_batch['batch_id'],
                            'batch_number' => $target_batch['batch_number'],
                            'quantity_restored' => $quantity,
                            'expiration_date' => $target_batch['expiration_date']
                        ];
                    }
                }
                
                // Update product_stock table
                $stmt = $this->pdo->prepare("
                    UPDATE product_stock 
                    SET current_stock = current_stock + ? 
                    WHERE product_id = ?
                ");
                $stmt->execute([$quantity, $product_id]);
                
                // Record stock movement
                $stmt = $this->pdo->prepare("
                    INSERT INTO stock_movements 
                    (product_id, stockmovementtype_id, quantity, previous_stock, new_stock, reason, reference_id, reference_type, created_by) 
                    VALUES (?, 3, ?, 
                        (SELECT current_stock FROM product_stock WHERE product_id = ?) - ?, 
                        (SELECT current_stock FROM product_stock WHERE product_id = ?), 
                        ?, ?, 'order_cancellation', ?)
                ");
                $stmt->execute([
                    $product_id, 
                    $quantity, 
                    $product_id, 
                    $quantity, 
                    $product_id, 
                    $notes ?: "Order cancellation - stock restored", 
                    $order_id, 
                    $created_by
                ]);
            }
            
            // Only commit if we started the transaction
            if (!$has_transaction) {
                $this->pdo->commit();
            }
            
            return $restored_items;
            
        } catch (Exception $e) {
            // Only rollback if we started the transaction
            if (!$has_transaction && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Get batch movement history
     */
    public function getBatchMovements($batch_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                bm.*,
                u.username as created_by_name
            FROM batch_movements bm
            LEFT JOIN users u ON bm.created_by = u.user_id
            WHERE bm.batch_id = ?
            ORDER BY bm.created_at DESC
        ");
        
        $stmt->execute([$batch_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
?>
