<?php
/**
 * Brand Stock Manager
 * Handles brand-specific stock calculations using product_batches as source of truth
 */

class BrandStockManager {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    /**
     * Get total stock for a specific brand
     */
    public function getBrandStock($brand_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(pb.quantity_remaining) as total_stock,
                COUNT(DISTINCT pb.product_id) as product_count,
                COUNT(pb.batch_id) as batch_count
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            WHERE pb.brand_id = ? AND pb.is_active = 1 AND pb.quantity_remaining > 0
        ");
        $stmt->execute([$brand_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get stock for a specific product by brand
     */
    public function getProductStockByBrand($product_id, $brand_id = null) {
        $sql = "
            SELECT 
                b.id as brand_id,
                b.name as brand_name,
                SUM(pb.quantity_remaining) as stock,
                COUNT(pb.batch_id) as batch_count,
                MIN(pb.expiration_date) as earliest_expiration,
                MAX(pb.expiration_date) as latest_expiration,
                AVG(pb.unit_cost) as avg_unit_cost
            FROM product_batches pb
            JOIN brands b ON pb.brand_id = b.id
            WHERE pb.product_id = ? AND pb.is_active = 1 AND pb.quantity_remaining > 0
        ";
        
        $params = [$product_id];
        
        if ($brand_id) {
            $sql .= " AND pb.brand_id = ?";
            $params[] = $brand_id;
        }
        
        $sql .= " GROUP BY pb.brand_id, b.name ORDER BY b.name";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get all brands with their stock levels
     */
    public function getAllBrandsStock() {
        $stmt = $this->pdo->query("
            SELECT 
                b.id as brand_id,
                b.name as brand_name,
                COALESCE(SUM(pb.quantity_remaining), 0) as total_stock,
                COUNT(DISTINCT pb.product_id) as product_count,
                COUNT(pb.batch_id) as batch_count,
                MIN(pb.expiration_date) as earliest_expiration,
                MAX(pb.expiration_date) as latest_expiration
            FROM brands b
            LEFT JOIN product_batches pb ON b.id = pb.brand_id AND pb.is_active = 1 AND pb.quantity_remaining > 0
            LEFT JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            WHERE b.is_archived = 0
            GROUP BY b.id, b.name
            ORDER BY total_stock DESC, b.name
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get brand movement statistics for a period
     */
    public function getBrandMovementStats($brand_id, $days = 30) {
        $stmt = $this->pdo->prepare("
            SELECT 
                COUNT(DISTINCT sm.stockmovement_id) as total_movements,
                SUM(CASE WHEN sm.quantity > 0 THEN sm.quantity ELSE 0 END) as total_in,
                SUM(CASE WHEN sm.quantity < 0 THEN ABS(sm.quantity) ELSE 0 END) as total_out,
                AVG(sm.quantity) as avg_quantity_per_movement,
                MIN(sm.created_at) as first_movement,
                MAX(sm.created_at) as last_movement
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            JOIN stock_movements sm ON p.product_id = sm.product_id 
                AND sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
            WHERE pb.brand_id = ? AND pb.is_active = 1
        ");
        $stmt->execute([$days, $brand_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get low stock brands (brands with stock below threshold)
     */
    public function getLowStockBrands($threshold = 10) {
        $stmt = $this->pdo->prepare("
            SELECT 
                b.id as brand_id,
                b.name as brand_name,
                SUM(pb.quantity_remaining) as total_stock,
                COUNT(DISTINCT pb.product_id) as product_count
            FROM brands b
            JOIN product_batches pb ON b.id = pb.brand_id AND pb.is_active = 1
            JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            WHERE b.is_archived = 0
            GROUP BY b.id, b.name
            HAVING total_stock <= ?
            ORDER BY total_stock ASC
        ");
        $stmt->execute([$threshold]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get expiring batches for a brand
     */
    public function getExpiringBrandBatches($brand_id, $days_ahead = 7) {
        $stmt = $this->pdo->prepare("
            SELECT 
                pb.*,
                p.product_name,
                DATEDIFF(pb.expiration_date, CURDATE()) as days_until_expiry
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            WHERE pb.brand_id = ? 
            AND pb.expiration_date IS NOT NULL 
            AND pb.expiration_date <= DATE_ADD(CURDATE(), INTERVAL ? DAY)
            AND pb.quantity_remaining > 0
            AND pb.is_active = 1
            ORDER BY pb.expiration_date ASC
        ");
        $stmt->execute([$brand_id, $days_ahead]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Calculate brand inventory value
     */
    public function getBrandInventoryValue($brand_id) {
        $stmt = $this->pdo->prepare("
            SELECT 
                SUM(pb.quantity_remaining * COALESCE(pb.unit_cost, 0)) as total_value,
                COUNT(pb.batch_id) as batch_count
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id AND p.is_archive = 0
            WHERE pb.brand_id = ? AND pb.is_active = 1 AND pb.quantity_remaining > 0
        ");
        $stmt->execute([$brand_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>
