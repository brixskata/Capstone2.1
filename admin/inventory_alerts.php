<?php
// Inventory Alerts Helper Functions

function checkInventoryAlerts($pdo) {
    // Check for low stock and out of stock items
    $stmt = $pdo->query("
        SELECT p.*, c.name as category_name, b.name as brand_name, s.name as supplier_name
        FROM products p
        LEFT JOIN categories c ON p.category_id = c.id
        LEFT JOIN brands b ON p.brand_id = b.id
        LEFT JOIN suppliers s ON p.supplier_id = s.id
        WHERE p.is_archived = 0
    ");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $alerts = [];
    
    foreach ($products as $product) {
        $reorder_point = $product['reorder_point'] ?? 10;
        
        // Check for out of stock
        if ($product['stock'] == 0) {
            $alerts[] = [
                'type' => 'out_of_stock',
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'message' => "Product '{$product['name']}' is out of stock. Immediate restocking required.",
                'severity' => 'high'
            ];
        }
        // Check for low stock
        elseif ($product['stock'] <= $reorder_point) {
            $alerts[] = [
                'type' => 'low_stock',
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'message' => "Product '{$product['name']}' is running low on stock ({$product['stock']} remaining). Reorder point: {$reorder_point}",
                'severity' => 'medium'
            ];
        }
        
        // Check for expiring products (if expiration date is set)
        if ($product['expiration_date']) {
            $days_until_expiry = (strtotime($product['expiration_date']) - time()) / (60 * 60 * 24);
            if ($days_until_expiry <= 30 && $days_until_expiry > 0) {
                $alerts[] = [
                    'type' => 'expiring_soon',
                    'product_id' => $product['id'],
                    'product_name' => $product['name'],
                    'message' => "Product '{$product['name']}' expires on " . date('M d, Y', strtotime($product['expiration_date'])) . " ({$days_until_expiry} days remaining)",
                    'severity' => 'medium'
                ];
            }
        }
    }
    
    return $alerts;
}

function createInventoryAlert($pdo, $product_id, $alert_type, $message, $threshold_value = null, $current_value = null) {
    try {
        $stmt = $pdo->prepare("
            INSERT INTO inventory_alerts (product_id, alert_type, threshold_value, current_value, message, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$product_id, $alert_type, $threshold_value, $current_value, $message]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function resolveInventoryAlert($pdo, $alert_id, $resolved_by) {
    try {
        $stmt = $pdo->prepare("
            UPDATE inventory_alerts 
            SET is_resolved = 1, resolved_by = ?, resolved_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$resolved_by, $alert_id]);
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getActiveAlerts($pdo) {
    $stmt = $pdo->query("
        SELECT ia.*, p.name as product_name
        FROM inventory_alerts ia
        JOIN products p ON ia.product_id = p.id
        WHERE ia.is_resolved = 0
        ORDER BY ia.created_at DESC
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getStockMovementStats($pdo, $product_id, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            movement_type,
            COUNT(*) as count,
            SUM(quantity) as total_quantity,
            AVG(quantity) as avg_quantity
        FROM stock_movements 
        WHERE product_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        GROUP BY movement_type
    ");
    $stmt->execute([$product_id, $days]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function calculateStockVelocity($pdo, $product_id, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            SUM(CASE WHEN movement_type = 'out' THEN quantity ELSE 0 END) as total_out,
            SUM(CASE WHEN movement_type = 'in' THEN quantity ELSE 0 END) as total_in,
            COUNT(CASE WHEN movement_type = 'out' THEN 1 END) as out_transactions,
            COUNT(CASE WHEN movement_type = 'in' THEN 1 END) as in_transactions
        FROM stock_movements 
        WHERE product_id = ? 
        AND created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
    ");
    $stmt->execute([$product_id, $days]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($stats['out_transactions'] > 0) {
        $avg_daily_out = $stats['total_out'] / $days;
        $days_of_stock_remaining = $stats['total_out'] > 0 ? ($stats['total_out'] / $days) : 0;
        return [
            'avg_daily_out' => $avg_daily_out,
            'days_of_stock_remaining' => $days_of_stock_remaining,
            'total_out' => $stats['total_out'],
            'total_in' => $stats['total_in'],
            'out_transactions' => $stats['out_transactions'],
            'in_transactions' => $stats['in_transactions']
        ];
    }
    
    return null;
}

function suggestReorderQuantity($pdo, $product_id, $days = 30) {
    $velocity = calculateStockVelocity($pdo, $product_id, $days);
    if (!$velocity) return null;
    
    $stmt = $pdo->prepare("SELECT stock, reorder_point FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $avg_daily_usage = $velocity['avg_daily_out'];
    $safety_stock = $product['reorder_point'] ?? 10;
    $lead_time_days = 7; // Assuming 7 days lead time
    
    $suggested_quantity = ($avg_daily_usage * $lead_time_days) + $safety_stock - $product['stock'];
    
    return max(0, round($suggested_quantity));
}

function updateProductStockLevels($pdo, $product_id, $new_stock, $previous_stock, $reason, $created_by) {
    try {
        // Update product stock
        $stmt = $pdo->prepare("UPDATE products SET stock = ?, last_stock_check = NOW() WHERE id = ?");
        $stmt->execute([$new_stock, $product_id]);
        
        // Record stock movement
        $stmt = $pdo->prepare("
            INSERT INTO stock_movements (product_id, movement_type, quantity, previous_stock, new_stock, reason, created_by)
            VALUES (?, 'adjustment', ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$product_id, abs($new_stock - $previous_stock), $previous_stock, $new_stock, $reason, $created_by]);
        
        return true;
    } catch (Exception $e) {
        return false;
    }
}

function getInventoryValue($pdo) {
    $stmt = $pdo->query("
        SELECT 
            SUM(stock * cost_price) as total_value,
            COUNT(*) as total_products,
            COUNT(CASE WHEN stock = 0 THEN 1 END) as out_of_stock,
            COUNT(CASE WHEN stock <= (reorder_point OR 10) THEN 1 END) as low_stock
        FROM products 
        WHERE is_archived = 0
    ");
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

function getTopMovingProducts($pdo, $limit = 10, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            p.stock as current_stock,
            SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END) as total_out,
            COUNT(CASE WHEN sm.movement_type = 'out' THEN 1 END) as out_transactions
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id 
            AND sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        WHERE p.is_archived = 0
        GROUP BY p.id, p.name, p.stock
        HAVING total_out > 0
        ORDER BY total_out DESC
        LIMIT ?
    ");
    $stmt->execute([$days, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getSlowMovingProducts($pdo, $limit = 10, $days = 30) {
    $stmt = $pdo->prepare("
        SELECT 
            p.name as product_name,
            p.stock as current_stock,
            COALESCE(SUM(CASE WHEN sm.movement_type = 'out' THEN sm.quantity ELSE 0 END), 0) as total_out,
            COALESCE(COUNT(CASE WHEN sm.movement_type = 'out' THEN 1 END), 0) as out_transactions
        FROM products p
        LEFT JOIN stock_movements sm ON p.id = sm.product_id 
            AND sm.created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
        WHERE p.is_archived = 0
        GROUP BY p.id, p.name, p.stock
        HAVING total_out = 0 OR total_out < 5
        ORDER BY total_out ASC, p.stock DESC
        LIMIT ?
    ");
    $stmt->execute([$days, $limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
?> 