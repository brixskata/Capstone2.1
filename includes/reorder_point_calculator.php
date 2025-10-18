<?php
/**
 * Reorder Point Calculator
 * Handles automatic reorder point calculations based on 7-day sales velocity
 */

class ReorderPointCalculator {
    private $pdo;
    private $lead_time_days = 7; // Fixed 7-day lead time
    private $safety_stock_percentage = 0.20; // 20% of ADS
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Calculate 7-day Average Daily Sales (ADS) for a specific brand-product combination
     */
    public function calculateADS($product_id, $brand_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COALESCE(SUM(bm.quantity), 0) as total_sales_7d,
                ROUND(COALESCE(SUM(bm.quantity), 0) / 7, 2) as ads
            FROM product_batches pb
            LEFT JOIN batch_movements bm ON pb.batch_id = bm.batch_id 
                AND bm.movement_type = 'sale'
                AND bm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            WHERE pb.product_id = ? 
                AND pb.brand_id = ? 
                AND pb.is_active = 1
        ");
        $stmt->execute([$product_id, $brand_id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return [
            'total_sales_7d' => (float)$result['total_sales_7d'],
            'ads' => (float)$result['ads']
        ];
    }
    
    /**
     * Classify movement type based on ADS
     */
    public function classifyMovement($ads) {
        if ($ads > 10) {
            return 'Fast-Moving';
        } elseif ($ads >= 1) {
            return 'Slow-Moving';
        } else {
            return 'Non-Moving';
        }
    }
    
    /**
     * Calculate reorder point based on ADS and movement type
     */
    public function calculateReorderPoint($ads, $movement_type) {
        $safety_stock = $ads * $this->safety_stock_percentage;
        
        switch ($movement_type) {
            case 'Fast-Moving':
                // (ADS × Lead Time) + (Safety Stock × 1.5)
                return ($ads * $this->lead_time_days) + ($safety_stock * 1.5);
                
            case 'Slow-Moving':
                // (ADS × Lead Time) + Safety Stock
                return ($ads * $this->lead_time_days) + $safety_stock;
                
            case 'Non-Moving':
            default:
                // Safety Stock only
                return max($safety_stock, 1); // Minimum 1 unit
        }
    }
    
    /**
     * Update or insert brand-product stock record
     */
    public function updateBrandProductStock($product_id, $brand_id, $ads, $rop, $movement_type) {
        $stmt = $this->pdo->prepare("
            INSERT INTO brand_product_stock 
            (product_id, brand_id, reorder_point, average_daily_sales, movement_type, last_calculated)
            VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                reorder_point = VALUES(reorder_point),
                average_daily_sales = VALUES(average_daily_sales),
                movement_type = VALUES(movement_type),
                last_calculated = VALUES(last_calculated),
                updated_at = NOW()
        ");
        
        return $stmt->execute([$product_id, $brand_id, $rop, $ads, $movement_type]);
    }
    
    /**
     * Calculate reorder points for all active brand-product combinations
     */
    public function calculateAllReorderPoints() {
        try {
            $this->pdo->beginTransaction();
            
            // Get all active brand-product combinations
            $stmt = $this->pdo->query("
                SELECT DISTINCT 
                    pb.product_id,
                    pb.brand_id,
                    p.product_name,
                    b.name as brand_name
                FROM product_batches pb
                JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
                JOIN brands b ON pb.brand_id = b.id AND b.is_archived = 0
                WHERE pb.is_active = 1
                ORDER BY b.name, p.product_name
            ");
            $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $processed_count = 0;
            $results = [];
            
            foreach ($combinations as $combo) {
                $product_id = $combo['product_id'];
                $brand_id = $combo['brand_id'];
                
                // Calculate ADS
                $sales_data = $this->calculateADS($product_id, $brand_id);
                $ads = $sales_data['ads'];
                
                // Classify movement
                $movement_type = $this->classifyMovement($ads);
                
                // Calculate reorder point
                $rop = $this->calculateReorderPoint($ads, $movement_type);
                
                // Update database
                $success = $this->updateBrandProductStock($product_id, $brand_id, $ads, $rop, $movement_type);
                
                if ($success) {
                    $processed_count++;
                    $results[] = [
                        'product_id' => $product_id,
                        'brand_id' => $brand_id,
                        'product_name' => $combo['product_name'],
                        'brand_name' => $combo['brand_name'],
                        'ads' => $ads,
                        'movement_type' => $movement_type,
                        'reorder_point' => $rop
                    ];
                }
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'count' => $processed_count,
                'results' => $results
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'count' => 0
            ];
        }
    }
    
    /**
     * Get brand stock data grouped by brand (same as stock_levels.php)
     */
    public function getBrandStockData() {
        $stmt = $this->pdo->query("
            SELECT 
                b.id as brand_id,
                b.name as brand_name,
                COUNT(DISTINCT pb.product_id) as product_count,
                COALESCE(SUM(pb.quantity_remaining), 0) as total_stock,
                COALESCE(AVG(bps.reorder_point), 0) as avg_reorder_point,
                COALESCE(AVG(bps.average_daily_sales), 0) as avg_ads,
                COALESCE(SUM((SELECT COUNT(*) FROM batch_movements bm WHERE bm.batch_id = pb.batch_id AND bm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))), 0) as total_sales_7d,
                COUNT(CASE WHEN bps.movement_type = 'Fast-Moving' THEN 1 END) as fast_moving_products,
                COUNT(CASE WHEN bps.movement_type = 'Slow-Moving' THEN 1 END) as slow_moving_products,
                COUNT(CASE WHEN bps.movement_type = 'Non-Moving' THEN 1 END) as non_moving_products,
                CASE 
                    WHEN AVG(bps.average_daily_sales) > 10 THEN 'Fast-Moving'
                    WHEN AVG(bps.average_daily_sales) >= 1 THEN 'Slow-Moving'
                    ELSE 'Non-Moving'
                END as movement_type,
                MAX(bps.last_calculated) as last_calculated
            FROM brands b
            LEFT JOIN product_batches pb ON b.id = pb.brand_id AND pb.is_active = 1
            LEFT JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            LEFT JOIN brand_product_stock bps ON bps.product_id = pb.product_id AND bps.brand_id = pb.brand_id
            WHERE b.is_archived = 0
            GROUP BY b.id, b.name
            ORDER BY total_stock ASC, b.name
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get brand-product stock data for display
     */
    public function getBrandProductStockData() {
        $stmt = $this->pdo->query("
            SELECT 
                b.id as brand_id,
                b.name as brand_name,
                p.product_id,
                p.product_name,
                COALESCE(sales_stats.total_sales_7d, 0) as total_sales_7d,
                COALESCE(sales_stats.ads, 0) as ads,
                COALESCE(stock_stats.current_stock, 0) as current_stock,
                COALESCE(bps.reorder_point, 0) as reorder_point,
                COALESCE(bps.movement_type, 'Non-Moving') as movement_type,
                bps.last_calculated
            FROM brands b
            CROSS JOIN products p
            LEFT JOIN (
                SELECT 
                    pb.brand_id,
                    pb.product_id,
                    SUM(bm.quantity) as total_sales_7d,
                    ROUND(SUM(bm.quantity) / 7, 2) as ads
                FROM product_batches pb
                LEFT JOIN batch_movements bm ON pb.batch_id = bm.batch_id 
                    AND bm.movement_type = 'sale'
                    AND bm.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                WHERE pb.is_active = 1
                GROUP BY pb.brand_id, pb.product_id
            ) sales_stats ON b.id = sales_stats.brand_id AND p.product_id = sales_stats.product_id
            LEFT JOIN (
                SELECT 
                    pb.brand_id,
                    pb.product_id,
                    SUM(pb.quantity_remaining) as current_stock
                FROM product_batches pb
                WHERE pb.is_active = 1
                GROUP BY pb.brand_id, pb.product_id
            ) stock_stats ON b.id = stock_stats.brand_id AND p.product_id = stock_stats.product_id
            LEFT JOIN brand_product_stock bps ON bps.product_id = p.product_id AND bps.brand_id = b.id
            WHERE b.is_archived = 0 AND p.is_archive = 0
            ORDER BY sales_stats.ads DESC, b.name, p.product_name
        ");
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Validate and sync reorder point data between tables
     */
    public function validateAndSyncReorderPoints() {
        try {
            $this->pdo->beginTransaction();
            
            // Get all brand-product combinations
            $stmt = $this->pdo->query("
                SELECT DISTINCT pb.product_id, pb.brand_id
                FROM product_batches pb
                WHERE pb.is_active = 1
            ");
            $combinations = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $synced_count = 0;
            
            foreach ($combinations as $combo) {
                $product_id = $combo['product_id'];
                $brand_id = $combo['brand_id'];
                
                // Get current reorder point from brand_product_stock
                $stmt = $this->pdo->prepare("
                    SELECT reorder_point FROM brand_product_stock 
                    WHERE product_id = ? AND brand_id = ?
                ");
                $stmt->execute([$product_id, $brand_id]);
                $brand_rop = $stmt->fetchColumn();
                
                // Get current reorder point from product_stock
                $stmt = $this->pdo->prepare("
                    SELECT reorder_point FROM product_stock 
                    WHERE product_id = ?
                ");
                $stmt->execute([$product_id]);
                $product_rop = $stmt->fetchColumn();
                
                // If brand_product_stock has a value but product_stock doesn't, sync it
                if ($brand_rop !== false && $product_rop === false) {
                    $stmt = $this->pdo->prepare("
                        INSERT INTO product_stock (product_id, reorder_point, current_stock, last_restock_date)
                        VALUES (?, ?, 0, NOW())
                        ON DUPLICATE KEY UPDATE reorder_point = VALUES(reorder_point)
                    ");
                    $stmt->execute([$product_id, $brand_rop]);
                    $synced_count++;
                }
                // If both exist but are different, prioritize brand_product_stock
                elseif ($brand_rop !== false && $product_rop !== false && $brand_rop != $product_rop) {
                    $stmt = $this->pdo->prepare("
                        UPDATE product_stock SET reorder_point = ? WHERE product_id = ?
                    ");
                    $stmt->execute([$brand_rop, $product_id]);
                    $synced_count++;
                }
            }
            
            $this->pdo->commit();
            
            return [
                'success' => true,
                'message' => "Successfully synced $synced_count reorder points",
                'count' => $synced_count
            ];
            
        } catch (Exception $e) {
            $this->pdo->rollBack();
            return [
                'success' => false,
                'message' => $e->getMessage(),
                'count' => 0
            ];
        }
    }
    
    /**
     * Get stock status based on current stock vs reorder point
     */
    public function getStockStatus($current_stock, $reorder_point) {
        if ($current_stock <= 0) {
            return 'Out of Stock';
        } elseif ($current_stock <= $reorder_point) {
            return 'Low Stock';
        } else {
            return 'Sufficient';
        }
    }
}
?>
