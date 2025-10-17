<?php
include '../includes/db.php';
include '../includes/permissions.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    http_response_code(403);
    echo json_encode(['error' => 'Forbidden']);
    exit;
}

// Get role ID from query string
$role_id = $_GET['role_id'] ?? null;

if (!$role_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Role ID required']);
    exit;
}

try {
    // Get role permissions
    $stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE usertype_id = ?");
    $stmt->execute([$role_id]);
    $permission_ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Define sidebar sections and their required permissions (same as in user_permissions.php)
    $sidebar_sections = [
        'overview' => [
            'title' => 'Overview',
            'icon' => 'chart-pie',
            'description' => 'Dashboard, Inventory Overview',
            'permissions' => [11] // inventory_view
        ],
        'sales' => [
            'title' => 'Sales',
            'icon' => 'shopping-cart',
            'description' => 'Transaction Logs',
            'permissions' => [15] // order_view
        ],
        'inventory' => [
            'title' => 'Inventory Management',
            'icon' => 'boxes',
            'description' => 'Restocking, Stock Adjustment, Stock Levels, Stock Movements, Batch Management',
            'permissions' => [11, 12, 13, 88] // inventory_view, inventory_restock, inventory_adjust, inventory_adjustment
        ],
        'products' => [
            'title' => 'Product Management',
            'icon' => 'shopping-bag',
            'description' => 'All Products, Add Product, Categories, Brands, Units, Archived, Discounts, Suppliers',
            'permissions' => [6, 7, 8, 9, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 32, 33, 34, 89] // product permissions + category + brand + uom + archive + suppliers
        ],
        'maintenance' => [
            'title' => 'Maintenance',
            'icon' => 'cogs',
            'description' => 'User Accounts, ID Verification, Promo Messages',
            'permissions' => [1, 2, 3, 4, 5] // user permissions only (Promo Messages is always visible)
        ],
        'analytics' => [
            'title' => 'Analytics',
            'icon' => 'chart-line',
            'description' => 'Reports, Activity Log',
            'permissions' => [35, 36, 37, 42, 43] // reports + history permissions
        ]
    ];
    
    // Check which sections role has access to
    $role_sections = [];
    foreach ($sidebar_sections as $section_key => $section) {
        $has_section = true;
        foreach ($section['permissions'] as $required_perm_id) {
            if (!in_array($required_perm_id, $permission_ids)) {
                $has_section = false;
                break;
            }
        }
        if ($has_section) {
            $role_sections[] = $section_key;
        }
    }
    
    // Return the sections this role has access to
    echo json_encode([
        'sections' => $role_sections,
        'permissions' => $permission_ids
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
?>
