<?php
// Database migration script to add transaction_id column to payments table
include 'includes/db.php';

try {
    echo "<h2>Database Migration: Adding Transaction ID Column</h2>";
    
    // Check if column already exists
    $checkColumn = $pdo->query("SHOW COLUMNS FROM payments LIKE 'transaction_id'");
    if ($checkColumn->rowCount() > 0) {
        echo "<p style='color: orange;'>⚠️ Column 'transaction_id' already exists in payments table.</p>";
    } else {
        // Add the transaction_id column
        $sql = "ALTER TABLE payments ADD COLUMN transaction_id VARCHAR(50) NULL AFTER proof";
        $pdo->exec($sql);
        echo "<p style='color: green;'>✅ Successfully added 'transaction_id' column to payments table.</p>";
        
        // Add index for better performance
        $indexSql = "CREATE INDEX idx_payments_transaction_id ON payments(transaction_id)";
        $pdo->exec($indexSql);
        echo "<p style='color: green;'>✅ Successfully added index for transaction_id column.</p>";
        
        // Add comment to document the column
        $commentSql = "ALTER TABLE payments MODIFY COLUMN transaction_id VARCHAR(50) NULL COMMENT 'GCash transaction ID or reference number'";
        $pdo->exec($commentSql);
        echo "<p style='color: green;'>✅ Successfully added comment to transaction_id column.</p>";
    }
    
    echo "<hr>";
    echo "<h3>Migration Summary</h3>";
    echo "<p><strong>Status:</strong> <span style='color: green;'>Completed Successfully</span></p>";
    echo "<p><strong>Changes Made:</strong></p>";
    echo "<ul>";
    echo "<li>Added 'transaction_id' column to payments table</li>";
    echo "<li>Added index for better query performance</li>";
    echo "<li>Added documentation comment</li>";
    echo "</ul>";
    
    echo "<p><strong>Next Steps:</strong></p>";
    echo "<ul>";
    echo "<li>GCash transaction IDs will now be stored in the database</li>";
    echo "<li>Admin can view transaction IDs in the transaction logs</li>";
    echo "<li>Payment verification is now more robust</li>";
    echo "</ul>";
    
} catch (PDOException $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
    echo "<p>Please check your database connection and try again.</p>";
}
?>









