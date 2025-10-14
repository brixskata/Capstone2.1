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

// Handle image upload
$image_path = null;
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $upload_dir = 'uploads/';
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    $max_size = 5 * 1024 * 1024; // 5MB

    $file_type = $_FILES['image']['type'];
    $file_size = $_FILES['image']['size'];
    $file_name = $_FILES['image']['name'];
    $file_tmp = $_FILES['image']['tmp_name'];
    $file_extension = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

    // Enhanced validation
    $errors = [];

    // Check file size
    if ($file_size > $max_size) {
        $errors[] = "File size must be less than 5MB";
    }

    // Check file type by MIME type
    if (!in_array($file_type, $allowed_types)) {
        $errors[] = "Invalid file type. Only JPEG, PNG, GIF, and WebP are allowed";
    }

    // Check file extension
    if (!in_array($file_extension, $allowed_extensions)) {
        $errors[] = "Invalid file extension";
    }

    // Check if file is actually an image
    $image_info = getimagesize($file_tmp);
    if ($image_info === false) {
        $errors[] = "File is not a valid image";
    }

    // Check for malicious file content
    $file_content = file_get_contents($file_tmp);
    if (strpos($file_content, '<?php') !== false || strpos($file_content, '<script') !== false) {
        $errors[] = "File contains potentially malicious content";
    }

    if (empty($errors)) {
        // Create upload directory if it doesn't exist
        if (!is_dir($upload_dir)) {
            mkdir($upload_dir, 0755, true);
        }

        $new_filename = 'rating_' . $order_id . '_' . $_SESSION['user_id'] . '_' . time() . '_' . uniqid() . '.' . $file_extension;
        $upload_path = $upload_dir . $new_filename;

        if (move_uploaded_file($file_tmp, $upload_path)) {
            $image_path = $upload_path;
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to upload image']);
            exit;
        }
    } else {
        echo json_encode(['success' => false, 'message' => implode(', ', $errors)]);
        exit;
    }
}

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
        if ($image_path !== null) {
            // If new image uploaded, delete old one if exists
            $stmt = $pdo->prepare("SELECT image FROM order_ratings WHERE order_id = ? AND user_id = ?");
            $stmt->execute([$order_id, $_SESSION['user_id']]);
            $old_image = $stmt->fetchColumn();
            if ($old_image && file_exists($old_image)) {
                unlink($old_image);
            }
            $stmt = $pdo->prepare("
                UPDATE order_ratings
                SET rating = ?, review = ?, image = ?, updated_at = CURRENT_TIMESTAMP
                WHERE order_id = ? AND user_id = ?
            ");
            $stmt->execute([$rating, $review, $image_path, $order_id, $_SESSION['user_id']]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE order_ratings
                SET rating = ?, review = ?, updated_at = CURRENT_TIMESTAMP
                WHERE order_id = ? AND user_id = ?
            ");
            $stmt->execute([$rating, $review, $order_id, $_SESSION['user_id']]);
        }
        $message = 'Rating updated successfully!';
    } else {
        // Insert new rating
        $stmt = $pdo->prepare("
            INSERT INTO order_ratings (order_id, user_id, rating, review, image)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$order_id, $_SESSION['user_id'], $rating, $review, $image_path]);
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
