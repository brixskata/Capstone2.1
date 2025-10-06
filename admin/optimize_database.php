<?php
include '../includes/db.php';

// Database optimization script for transaction logs performance
echo "<h2>Database Optimization for Transaction Logs</h2>";

$indexes = [
    // Orders table indexes
    "CREATE INDEX IF NOT EXISTS idx_orders_created_at ON orders(created_at)",
    "CREATE INDEX IF NOT EXISTS idx_orders_user_id ON orders(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_orders_status ON orders(orderstatus_id)",
    "CREATE INDEX IF NOT EXISTS idx_orders_created_status ON orders(created_at, orderstatus_id)",
    
    // Order items table indexes
    "CREATE INDEX IF NOT EXISTS idx_order_items_order_id ON order_items(order_id)",
    "CREATE INDEX IF NOT EXISTS idx_order_items_product_id ON order_items(product_id)",
    
    // Users table indexes
    "CREATE INDEX IF NOT EXISTS idx_users_username ON users(username)",
    
    // User info table indexes
    "CREATE INDEX IF NOT EXISTS idx_user_info_user_id ON user_info(user_id)",
    
    // Addresses table indexes
    "CREATE INDEX IF NOT EXISTS idx_addresses_user_id ON addresses(user_id)",
    "CREATE INDEX IF NOT EXISTS idx_addresses_default ON addresses(user_id, is_default)",
    
    // Payments table indexes
    "CREATE INDEX IF NOT EXISTS idx_payments_order_id ON payments(orders_id)",
    
    // Order status table indexes
    "CREATE INDEX IF NOT EXISTS idx_order_status_name ON order_status(status_name)",
    
    // Products table indexes
    "CREATE INDEX IF NOT EXISTS idx_products_name ON products(product_name)",
];

$successCount = 0;
$errorCount = 0;

foreach ($indexes as $index) {
    try {
        $pdo->exec($index);
        echo "<p style='color: green;'>✓ " . $index . "</p>";
        $successCount++;
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ " . $index . " - Error: " . $e->getMessage() . "</p>";
        $errorCount++;
    }
}

echo "<hr>";
echo "<h3>Optimization Summary</h3>";
echo "<p><strong>Successful indexes:</strong> $successCount</p>";
echo "<p><strong>Failed indexes:</strong> $errorCount</p>";

if ($errorCount == 0) {
    echo "<p style='color: green; font-weight: bold;'>All indexes created successfully! Database performance should be significantly improved.</p>";
} else {
    echo "<p style='color: orange; font-weight: bold;'>Some indexes failed to create. Check the errors above.</p>";
}

// Show current table sizes
echo "<hr>";
echo "<h3>Table Information</h3>";

$tables = ['orders', 'order_items', 'users', 'user_info', 'addresses', 'payments', 'order_status', 'products'];

foreach ($tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
        $count = $stmt->fetchColumn();
        echo "<p><strong>$table:</strong> $count records</p>";
    } catch (PDOException $e) {
        echo "<p><strong>$table:</strong> Error - " . $e->getMessage() . "</p>";
    }
}

echo "<hr>";
echo "<p><a href='transaction_logs.php'>← Back to Transaction Logs</a></p>";
?>
