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
     * Get stock status based on current stock vs reorder point
     */
    public function getStockStatus($current_stock, $reorder_point) {
        if ($current_stock <= 0) {
            return 'Out of Stock';
        } elseif ($current_stock <= $reorder_point) {
            return 'Low Stock';
        } else {
            return 'In Stock';
        }
    }
}
?>
