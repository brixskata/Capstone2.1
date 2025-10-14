<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in']);
    exit;
}

// Check if order_id is provided
$order_id = intval($_GET['order_id'] ?? 0);
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

try {
    // Get rating for this order and user
    $stmt = $pdo->prepare("SELECT rating, review, image FROM order_ratings WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $rating = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($rating) {
        echo json_encode([
            'success' => true,
            'rating' => $rating['rating'],
            'review' => $rating['review'],
            'image' => $rating['image']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Rating not found']);
    }

} catch (PDOException $e) {
    error_log("Get rating error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred']);
}
?>
