<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to view products']);
    exit;
}

$product_id = isset($_GET['product_id']) ? intval($_GET['product_id']) : 0;

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    // Get alternative brands for the same product type
    $stmt = $pdo->prepare("
        SELECT DISTINCT 
            b.id as brand_id,
            b.name as brand_name,
            pb.batch_id,
            pb.quantity_remaining,
            pb.unit_cost,
            pb.expiration_date,
            pb.received_date,
            COALESCE(pp.cost_price, pb.unit_cost, 0) as cost_price,
            COALESCE(pp.markup_price, 0) as markup_price,
            (COALESCE(pp.cost_price, pb.unit_cost, 0) + COALESCE(pp.markup_price, 0)) as total_price
        FROM product_batches pb
        INNER JOIN brands b ON pb.brand_id = b.id
        LEFT JOIN product_pricing pp ON pb.product_id = pp.product_id
        WHERE pb.product_id = ? 
        AND pb.quantity_remaining > 0 
        AND pb.is_active = 1
        AND b.is_archived = 0
        ORDER BY pb.expiration_date ASC, pb.received_date ASC
    ");
    $stmt->execute([$product_id]);
    $alternative_brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Group by brand and get the best price/stock for each brand
    $brands_grouped = [];
    foreach ($alternative_brands as $brand) {
        $brand_id = $brand['brand_id'];
        if (!isset($brands_grouped[$brand_id])) {
            $brands_grouped[$brand_id] = [
                'brand_id' => $brand['brand_id'],
                'brand_name' => $brand['brand_name'],
                'total_stock' => 0,
                'best_price' => PHP_FLOAT_MAX,
                'best_batch_id' => null,
                'expiration_date' => null,
                'batches' => []
            ];
        }
        
        $brands_grouped[$brand_id]['total_stock'] += $brand['quantity_remaining'];
        $brands_grouped[$brand_id]['batches'][] = $brand;
        
        if ($brand['total_price'] < $brands_grouped[$brand_id]['best_price']) {
            $brands_grouped[$brand_id]['best_price'] = $brand['total_price'];
            $brands_grouped[$brand_id]['best_batch_id'] = $brand['batch_id'];
            $brands_grouped[$brand_id]['expiration_date'] = $brand['expiration_date'];
        }
    }
    
    echo json_encode([
        'success' => true,
        'brands' => array_values($brands_grouped)
    ]);
    
} catch (Exception $e) {
    error_log("Error fetching alternative brands: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error fetching brands']);
}
?>
