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
                (product_id, supplier_id, batch_number, quantity_received, quantity_remaining, 
                 unit_cost, expiration_date, received_date, created_by, reference_type, 
                 reference_id, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $data['product_id'],
                $data['supplier_id'] ?? null,
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
    public function getAvailableBatches($product_id, $quantity_needed = null, $supplier_id = null) {
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
        
        $sql .= " ORDER BY received_date ASC, batch_id ASC";
        
        if ($quantity_needed) {
            $sql .= " LIMIT ?";
            $params[] = $quantity_needed;
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Consume stock from batches (FIFO)
     */
    public function consumeStock($product_id, $quantity, $movement_type, $reference_type = null, $reference_id = null, $created_by = null, $notes = null, $supplier_id = null) {
        // Check if there's already an active transaction
        $has_transaction = $this->pdo->inTransaction();
        
        try {
            // Only start a transaction if there isn't one already
            if (!$has_transaction) {
                $this->pdo->beginTransaction();
            }
            
            $remaining_quantity = $quantity;
            $batches_used = [];
            
            // Get available batches in FIFO order (filtered by supplier if provided)
            $batches = $this->getAvailableBatches($product_id, null, $supplier_id);
            
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
