<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to submit a rating']);
    exit;
}

// Check if request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
    exit;
}

// Get and validate input data
$order_id = intval($_POST['order_id'] ?? 0);
$rating = intval($_POST['rating'] ?? 0);
$review = trim($_POST['review'] ?? '');

// Validate order ID
if ($order_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid order ID']);
    exit;
}

// Validate rating (1-5 stars)
if ($rating < 1 || $rating > 5) {
    echo json_encode(['success' => false, 'message' => 'Rating must be between 1 and 5 stars']);
    exit;
}

// Validate review length (optional, max 500 characters)
if (strlen($review) > 500) {
    echo json_encode(['success' => false, 'message' => 'Review must be 500 characters or less']);
    exit;
}

try {
    // Check if order exists and belongs to the user
    $stmt = $pdo->prepare("
        SELECT o.orders_id, o.user_id, os.status_name 
        FROM orders o 
        LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id 
        WHERE o.orders_id = ? AND o.user_id = ?
    ");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$order) {
        echo json_encode(['success' => false, 'message' => 'Order not found or does not belong to you']);
        exit;
    }

    // Check if order is completed/delivered (you may need to adjust this based on your order status system)
    $completed_statuses = ['Delivered', 'Completed', 'Finished'];
    if (!in_array($order['status_name'], $completed_statuses)) {
        echo json_encode(['success' => false, 'message' => 'You can only rate completed orders']);
        exit;
    }

    // Check if user has already rated this order
    $stmt = $pdo->prepare("SELECT rating_id FROM order_ratings WHERE order_id = ? AND user_id = ?");
    $stmt->execute([$order_id, $_SESSION['user_id']]);
    $existing_rating = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($existing_rating) {
        // Update existing rating
        $stmt = $pdo->prepare("
            UPDATE order_ratings 
            SET rating = ?, review = ?, updated_at = CURRENT_TIMESTAMP 
            WHERE order_id = ? AND user_id = ?
        ");
        $stmt->execute([$rating, $review, $order_id, $_SESSION['user_id']]);
        $message = 'Rating updated successfully!';
    } else {
        // Insert new rating
        $stmt = $pdo->prepare("
            INSERT INTO order_ratings (order_id, user_id, rating, review) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $_SESSION['user_id'], $rating, $review]);
        $message = 'Rating submitted successfully!';
    }

    echo json_encode([
        'success' => true, 
        'message' => $message,
        'rating' => $rating,
        'review' => $review
    ]);

} catch (PDOException $e) {
    error_log("Rating submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
?>
