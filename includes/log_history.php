<?php
function logHistory(PDO $pdo, string $action, string $details, $performed_by = null): void {
    // Compose a readable details message including the action label
    $message = trim($action) !== '' ? ($action . ': ' . $details) : $details;

    // Coerce performed_by to an integer user_id if possible, else 0
    $performedId = 0;
    if (is_int($performed_by)) {
        $performedId = $performed_by;
    } elseif (is_string($performed_by)) {
        // If it's a username, try to get the user_id
        try {
            $stmt = $pdo->prepare("SELECT user_id FROM users WHERE username = ?");
            $stmt->execute([$performed_by]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($user) {
                $performedId = $user['user_id'];
            }
        } catch (Exception $e) {
            // If lookup fails, use 0
            $performedId = 0;
        }
    }

    // Map action names to proper action type IDs (after cleanup)
    $action_type_map = [
        'Stock Adjustment' => 12,  // ID 12: Stock Adjustment
        'Restocking' => 11,        // ID 11: Restock Product
        'Added Brand' => 21,       // ID 21: Added Brand
        'Added UOM' => 22,         // ID 22: Added UOM
        'Added Category' => 23,    // ID 23: Added Category
        'Added Supplier' => 24,    // ID 24: Added Supplier
        'Deleted Brand' => 25,     // ID 25: Deleted Brand
        'Deleted UOM' => 26,       // ID 26: Deleted UOM
        'Deleted Category' => 27,  // ID 27: Deleted Category
        'Deleted Supplier' => 28,  // ID 28: Deleted Supplier
        'Login' => 1,              // ID 1: Login
        'Logout' => 2,             // ID 2: Logout
        'Product Created' => 31,   // ID 31: Product Created
        'Product Updated' => 32,   // ID 32: Product Updated
        'Product Deleted' => 33,   // ID 33: Product Deleted
        'Order Created' => 34,     // ID 34: Order Created
        'Order Updated' => 35,     // ID 35: Order Updated
        'Create User' => 3,        // ID 3: Create User
        'Update User' => 4,        // ID 4: Update User
        'Deactivate User' => 5,    // ID 5: Deactivate User
        'Add Product' => 6,        // ID 6: Add Product
        'Update Product' => 7,     // ID 7: Update Product
        'Archive Product' => 8,    // ID 8: Archive Product
        'Update Category' => 9,    // ID 9: Update Category
        'Update Supplier' => 10,   // ID 10: Update Supplier
        'Approve Return' => 13,    // ID 13: Approve Return
        'Reject Return' => 14,     // ID 14: Reject Return
        'Update Order Status' => 15, // ID 15: Update Order Status
        'Process Payment' => 16,   // ID 16: Process Payment
        'Issue Refund' => 17,      // ID 17: Issue Refund
        'Generate Report' => 18    // ID 18: Generate Report
    ];

    // Get the appropriate action type ID, default to 36 (System Action) if not found
    $action_type_id = $action_type_map[$action] ?? 36;

    // Insert into history_logs using proper action type
    $stmt = $pdo->prepare(
        "INSERT INTO history_logs (history_action_type_id, reference_id, reference_type, details, performed_by, performed_at)
         VALUES (?, NULL, NULL, ?, ?, NOW())"
    );
    $stmt->execute([$action_type_id, $message, $performedId]);
}
?>
