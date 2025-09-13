<?php
session_start();
include_once 'includes/db.php';

header('Content-Type: application/json');

// Check if search query is provided
if (!isset($_GET['q']) || empty(trim($_GET['q']))) {
    echo json_encode(['success' => false, 'message' => 'Search query is required']);
    exit;
}

$search_query = trim($_GET['q']);
$search_term = '%' . $search_query . '%';

try {
    // Search for products by name, category, or description
    $sql = "SELECT 
                p.product_id,
                p.product_name,
                p.category,
                p.description,
                COALESCE(ps.current_stock, 0) AS stock,
                COALESCE(pp.selling_price, 0) AS price,
                (SELECT pi.image_url 
                 FROM product_images pi 
                 WHERE pi.product_id = p.product_id 
                 AND pi.is_primary = 1 
                 LIMIT 1) AS image_url
            FROM products p
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            WHERE p.is_archive = 0 
            AND (p.product_name LIKE :search_term 
                 OR p.category LIKE :search_term 
                 OR p.description LIKE :search_term)
            ORDER BY 
                CASE 
                    WHEN p.product_name LIKE :exact_match THEN 1
                    WHEN p.product_name LIKE :starts_with THEN 2
                    ELSE 3
                END,
                p.product_name ASC
            LIMIT 10";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':search_term', $search_term);
    $exact_match = $search_query;
    $starts_with = $search_query . '%';
    $stmt->bindParam(':exact_match', $exact_match);
    $stmt->bindParam(':starts_with', $starts_with);
    $stmt->execute();
    
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for frontend
    $results = [];
    foreach ($products as $product) {
        $results[] = [
            'id' => $product['product_id'],
            'name' => $product['product_name'],
            'category' => $product['category'],
            'price' => number_format($product['price'], 2),
            'stock' => $product['stock'],
            'image' => $product['image_url'] ? 'admin/' . $product['image_url'] : 'images/placeholder.jpg',
            'url' => 'product_detail.php?id=' . $product['product_id']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results,
        'count' => count($results),
        'query' => $search_query
    ]);
    
} catch (Exception $e) {
    error_log("Search error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Search failed. Please try again.'
    ]);
}
?>