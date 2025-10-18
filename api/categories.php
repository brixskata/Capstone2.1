<?php
session_start();
include '../includes/db.php';

header('Content-Type: application/json');

try {
    // Load all categories at once for carousel display
    $page = 1;
    $categoriesPerPage = 100; // Load all categories
    $offset = 0;
    
    // Fetch all categories with pagination (including those without products)
    $stmt = $pdo->query("
        SELECT 
            c.category_id,
            c.category_name,
            COUNT(p.product_id) as product_count,
            (SELECT pi.image_url FROM product_images pi 
             JOIN products p2 ON pi.product_id = p2.product_id 
             WHERE p2.category_id = c.category_id AND pi.is_primary = 1 
             ORDER BY p2.created_at DESC LIMIT 1) as category_image
        FROM categories c
        LEFT JOIN products p ON c.category_id = p.category_id AND p.is_archive = 0
        GROUP BY c.category_id, c.category_name
        ORDER BY c.category_name
        LIMIT $categoriesPerPage OFFSET $offset
    ");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get total count of all categories
    $countStmt = $pdo->query("SELECT COUNT(*) as total FROM categories");
    $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
    $totalCategories = $countResult['total'] ?? 0;
    
    // Process categories for display
    $processedCategories = [];
    foreach ($categories as $category) {
        $categoryName = $category['category_name'];
        $productCount = $category['product_count'] ?? 0;
        $categoryImage = $category['category_image'] ?? 'images/placeholder.jpg';
        
        // Determine the correct image path
        $imagePath = 'images/placeholder.jpg';
        if (!empty($categoryImage)) {
            if (strpos($categoryImage, 'admin/') === 0) {
                $imagePath = $categoryImage;
            } elseif (strpos($categoryImage, 'images/') === 0) {
                $imagePath = $categoryImage;
            } else {
                $imagePath = 'admin/' . $categoryImage;
            }
        }
        
        $processedCategories[] = [
            'id' => $category['category_id'],
            'name' => strtoupper(htmlspecialchars($category['category_name'])),
            'description' => $productCount > 0 ? $productCount . ' products available' : 'No products yet',
            'image' => $imagePath,
            'url' => 'product.php?category=' . urlencode(strtolower($category['category_name']))
        ];
    }
    
    $totalPages = ceil($totalCategories / $categoriesPerPage);
    
    echo json_encode([
        'success' => true,
        'categories' => $processedCategories,
        'pagination' => [
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_categories' => $totalCategories,
            'has_next' => $page < $totalPages,
            'has_prev' => $page > 1
        ]
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Error: ' . $e->getMessage()
    ]);
}
?>
