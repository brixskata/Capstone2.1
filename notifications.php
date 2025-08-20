<?php
// notifications.php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

// Fetch user notifications
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM notifications WHERE user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Notifications</title>
    <link rel="stylesheet" href="includes/styles.css">
</head>
<body>
    <!-- Profile Navigation Bar -->
    <header>
        <nav>
            <ul>
          
                <li><a href="products.php">Products</a></li>
                <li><a href="cart.php">Cart</a></li>
                <li><a href="checkout.php">Checkout</a></li>
                <li><a href="orders.php">Orders</a></li>
                <li><a href="notifications.php">Notifications</a></li>
            </ul>
        </nav>
    </header>

    <main>
        <h1>Your Notifications</h1>

        <?php if (count($notifications) > 0): ?>
            <ul>
                <?php foreach ($notifications as $notification): ?>
                    <li>
                        <p>
                            <?= htmlspecialchars($notification['message']); ?> - 
                            <strong><?= $notification['is_read'] ? "Read" : "Unread"; ?></strong>
                        </p>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php else: ?>
            <p>You have no notifications.</p>
        <?php endif; ?>
    </main>
</body>
</html>
