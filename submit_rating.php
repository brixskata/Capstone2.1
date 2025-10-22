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

// Handle multiple image uploads (optional, max 3)
$rating_images = [];
$max_images = 3;

for ($i = 1; $i <= $max_images; $i++) {
    $input_name = "rating_image_$i";
    if (isset($_FILES[$input_name]) && $_FILES[$input_name]['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES[$input_name];
        
        // Validate file type
        $allowed_types = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $file_type = $file['type'];
        if (!in_array($file_type, $allowed_types)) {
            echo json_encode(['success' => false, 'message' => "Invalid file type for image $i. Only JPEG, PNG, GIF, and WebP images are allowed."]);
            exit;
        }
        
        // Validate file size (max 5MB per image)
        $max_size = 5 * 1024 * 1024; // 5MB
        if ($file['size'] > $max_size) {
            echo json_encode(['success' => false, 'message' => "Image $i too large. Please upload images smaller than 5MB each."]);
            exit;
        }
        
        // Create upload directory
        $upload_dir = 'uploads/rating_images/';
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }
        
        // Generate unique filename
        $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $unique_filename = 'rating_' . $order_id . '_' . $_SESSION['user_id'] . '_' . $i . '_' . time() . '.' . $file_extension;
        $upload_path = $upload_dir . $unique_filename;
        
        // Move uploaded file
        if (move_uploaded_file($file['tmp_name'], $upload_path)) {
            $rating_images[] = [
                'path' => $upload_path,
                'order' => $i
            ];
        } else {
            echo json_encode(['success' => false, 'message' => "Failed to upload image $i. Please try again."]);
            exit;
        }
    }
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
        
        // Get the rating_id for image operations
        $rating_id = $existing_rating['rating_id'];
        
        // Delete existing images if any
        $stmt = $pdo->prepare("DELETE FROM rating_images WHERE rating_id = ?");
        $stmt->execute([$rating_id]);
        
        $message = 'Rating updated successfully!';
    } else {
        // Insert new rating
        $stmt = $pdo->prepare("
            INSERT INTO order_ratings (order_id, user_id, rating, review) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $_SESSION['user_id'], $rating, $review]);
        $rating_id = $pdo->lastInsertId();
        
        $message = 'Rating submitted successfully!';
    }
    
    // Insert multiple images if any
    if (!empty($rating_images)) {
        foreach ($rating_images as $image) {
            $stmt = $pdo->prepare("
                INSERT INTO rating_images (rating_id, image_path, image_order) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$rating_id, $image['path'], $image['order']]);
        }
    }

    echo json_encode([
        'success' => true, 
        'message' => $message,
        'rating' => $rating,
        'review' => $review,
        'image_count' => count($rating_images),
        'images' => array_column($rating_images, 'path')
    ]);

} catch (PDOException $e) {
    error_log("Rating submission error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred. Please try again.']);
}
?>
