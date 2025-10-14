<?php
include 'includes/db.php';

try {
    $sql = "ALTER TABLE order_ratings ADD COLUMN image VARCHAR(255) DEFAULT NULL";
    $pdo->exec($sql);
    echo "Column 'image' added successfully to order_ratings table.";
} catch (PDOException $e) {
    if ($e->getCode() == '42S21') {
        echo "Column 'image' already exists in order_ratings table.";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
?></content>
</xai:function_call">Now run this script to add the image column. Let me run it via the shell. But since it's Windows, I'll use PHP directly. Actually, let me use ExecuteShellCommand with php. 

<xai:function_call name="ExecuteShellCommand">
<parameter name="command">php "c:\xampp\htdocs\Capstone2.1\alter_table.php"