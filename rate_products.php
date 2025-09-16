<?php
session_start();
include 'includes/db.php';

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// CSRF token setup
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_id = $_SESSION['user_id'];
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

// Validate order belongs to user and is completed
$stmt = $pdo->prepare("
    SELECT o.orders_id, o.created_at, o.total_price, os.status_name 
    FROM orders o 
    LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id 
    WHERE o.orders_id = :order_id AND o.user_id = :user_id AND os.status_name = 'Completed'
");
$stmt->execute(['order_id' => $order_id, 'user_id' => $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error_message'] = 'Order not found or not eligible for product rating. Only completed orders can be rated.';
    header('Location: orders.php');
    exit();
}

// Get order items with product details
$stmt = $pdo->prepare("
    SELECT 
        oi.product_id,
        oi.quantity,
        p.product_name,
        p.product_description,
        (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.product_id AND pi.is_primary = 1 LIMIT 1) AS image_url
    FROM order_items oi
    LEFT JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = :order_id
");
$stmt->execute(['order_id' => $order_id]);
$orderItems = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get existing product ratings for this order
$existingRatings = [];
if (!empty($orderItems)) {
    $productIds = array_column($orderItems, 'product_id');
    $placeholders = str_repeat('?,', count($productIds) - 1) . '?';
    $stmt = $pdo->prepare("
        SELECT product_id, rating, review_text 
        FROM product_ratings 
        WHERE product_id IN ($placeholders) AND user_id = ? AND orders_id = ?
    ");
    $stmt->execute(array_merge($productIds, [$user_id, $order_id]));
    $ratings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($ratings as $rating) {
        $existingRatings[$rating['product_id']] = $rating;
    }
}

// Handle form submission
if ($_POST) {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $ratingsSubmitted = 0;
        $errors = [];

        foreach ($_POST['ratings'] ?? [] as $product_id => $ratingData) {
            $product_id = intval($product_id);
            $rating = intval($ratingData['rating'] ?? 0);
            $review_text = trim($ratingData['review_text'] ?? '');

            if ($rating >= 1 && $rating <= 5) {
                try {
                    // Check if user purchased this product in this order
                    $stmt = $pdo->prepare("SELECT 1 FROM order_items WHERE order_id = ? AND product_id = ?");
                    $stmt->execute([$order_id, $product_id]);
                    
                    if ($stmt->fetch()) {
                        // Insert or update product rating (PostgreSQL)
                        $stmt = $pdo->prepare("
                            INSERT INTO product_ratings (product_id, user_id, orders_id, rating, review_text) 
                            VALUES (?, ?, ?, ?, ?)
                            ON CONFLICT (product_id, user_id, orders_id) 
                            DO UPDATE SET rating = EXCLUDED.rating, review_text = EXCLUDED.review_text, created_at = CURRENT_TIMESTAMP
                        ");
                        $stmt->execute([$product_id, $user_id, $order_id, $rating, $review_text]);
                        $ratingsSubmitted++;
                    }
                } catch (PDOException $e) {
                    $errors[] = "Error saving rating for product ID $product_id";
                }
            }
        }

        if ($ratingsSubmitted > 0 && empty($errors)) {
            $_SESSION['success_message'] = "Thank you for rating $ratingsSubmitted product(s)! Your feedback helps other customers.";
            header('Location: orders.php');
            exit();
        } elseif (!empty($errors)) {
            $error_message = implode(', ', $errors);
        } else {
            $error_message = 'Please select ratings for at least one product.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Products - MikeMadz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
        }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f8f9fa;
            color: #212529;
        }

        .rating-container {
            max-width: 800px;
            margin: 2rem auto;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2.5rem;
        }

        .product-item {
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            background: #fafafa;
        }

        .product-info {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
        }

        .product-image {
            width: 80px;
            height: 80px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-right: 1rem;
        }

        .product-details h5 {
            margin: 0 0 0.5rem 0;
            color: var(--bs-secondary);
        }

        .star-rating {
            display: flex;
            gap: 0.25rem;
            margin: 1rem 0;
        }

        .star {
            font-size: 1.5rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .star:hover,
        .star.active {
            color: var(--bs-warning);
        }

        .review-textarea {
            min-height: 80px;
            border-radius: 0.5rem;
            border: 1px solid #ced4da;
        }

        .btn-submit {
            background: var(--bs-secondary);
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            color: white;
            width: 100%;
        }

        .btn-submit:hover {
            background: #6b1429;
        }

        .back-link {
            color: var(--bs-secondary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .alert {
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="container py-4">
        <a href="orders.php" class="back-link">
            <i class="fas fa-arrow-left"></i>
            Back to Orders
        </a>

        <div class="rating-container">
            <div class="text-center mb-4">
                <h2 class="mb-3" style="color: var(--bs-secondary);">
                    <i class="fas fa-star me-2"></i>
                    Rate Your Products
                </h2>
                <p class="text-muted">How was your experience with these products?</p>
                
                <div class="alert alert-info">
                    <strong>Order #<?php echo $order['orders_id']; ?></strong> • 
                    <?php echo date('F j, Y', strtotime($order['created_at'])); ?>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">

                <?php foreach ($orderItems as $item): ?>
                    <div class="product-item">
                        <div class="product-info">
                            <img src="<?= !empty($item['image_url']) ? 'admin/' . htmlspecialchars($item['image_url']) : 'images/placeholder.jpg' ?>" 
                                 alt="<?= htmlspecialchars($item['product_name']) ?>" 
                                 class="product-image"
                                 onerror="this.src='images/placeholder.jpg'">
                            <div class="product-details">
                                <h5><?= htmlspecialchars($item['product_name']) ?></h5>
                                <p class="text-muted mb-0">Quantity: <?= $item['quantity'] ?></p>
                            </div>
                        </div>

                        <div class="rating-section">
                            <label class="form-label fw-semibold">Rate this product:</label>
                            <div class="star-rating" data-product-id="<?= $item['product_id'] ?>">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <i class="fas fa-star star" data-rating="<?= $i ?>" 
                                       <?= isset($existingRatings[$item['product_id']]) && $i <= $existingRatings[$item['product_id']]['rating'] ? 'style="color: #ffc107;"' : '' ?>></i>
                                <?php endfor; ?>
                            </div>
                            
                            <input type="hidden" name="ratings[<?= $item['product_id'] ?>][rating]" class="rating-input" 
                                   value="<?= $existingRatings[$item['product_id']]['rating'] ?? '' ?>">
                            
                            <textarea class="form-control review-textarea" 
                                      name="ratings[<?= $item['product_id'] ?>][review_text]" 
                                      placeholder="Share your thoughts about this product (optional)"
                                      maxlength="500"><?= htmlspecialchars($existingRatings[$item['product_id']]['review_text'] ?? '') ?></textarea>
                        </div>
                    </div>
                <?php endforeach; ?>

                <button type="submit" class="btn btn-submit">
                    <i class="fas fa-paper-plane me-2"></i>
                    Submit Product Ratings
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Handle star rating interactions
            document.querySelectorAll('.star-rating').forEach(ratingContainer => {
                const stars = ratingContainer.querySelectorAll('.star');
                const productId = ratingContainer.dataset.productId;
                const ratingInput = document.querySelector(`input[name="ratings[${productId}][rating]"]`);
                
                stars.forEach((star, index) => {
                    star.addEventListener('click', function() {
                        const rating = index + 1;
                        ratingInput.value = rating;
                        
                        // Update star display
                        stars.forEach((s, i) => {
                            if (i < rating) {
                                s.style.color = '#ffc107';
                                s.classList.add('active');
                            } else {
                                s.style.color = '#ddd';
                                s.classList.remove('active');
                            }
                        });
                    });
                    
                    star.addEventListener('mouseover', function() {
                        const rating = index + 1;
                        stars.forEach((s, i) => {
                            s.style.color = i < rating ? '#ffc107' : '#ddd';
                        });
                    });
                });
                
                ratingContainer.addEventListener('mouseleave', function() {
                    const currentRating = parseInt(ratingInput.value) || 0;
                    stars.forEach((s, i) => {
                        s.style.color = i < currentRating ? '#ffc107' : '#ddd';
                    });
                });
            });
        });
    </script>
</body>
</html>