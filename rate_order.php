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

// Validate order belongs to user and is completed only
$stmt = $pdo->prepare("
    SELECT o.orders_id, o.created_at, o.total_price, os.status_name 
    FROM orders o 
    LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id 
    WHERE o.orders_id = :order_id AND o.user_id = :user_id AND os.status_name = 'Completed'
");
$stmt->execute(['order_id' => $order_id, 'user_id' => $user_id]);
$order = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$order) {
    $_SESSION['error_message'] = 'Order not found or not eligible for rating. Only completed orders can be rated.';
    header('Location: orders.php');
    exit();
}

// Check if already rated
$stmt = $pdo->prepare("SELECT rating_id FROM order_ratings WHERE orders_id = :order_id AND user_id = :user_id");
$stmt->execute(['order_id' => $order_id, 'user_id' => $user_id]);
$existingRating = $stmt->fetch();

if ($existingRating) {
    $_SESSION['message'] = 'You have already rated this order.';
    header('Location: orders.php');
    exit();
}

// Handle form submission
if ($_POST) {
    // CSRF token validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_message = 'Invalid security token. Please try again.';
    } else {
        $rating = intval($_POST['rating']);
        $review_text = trim($_POST['review_text']);
        
        if ($rating >= 1 && $rating <= 5) {
        try {
            $stmt = $pdo->prepare("
                INSERT INTO order_ratings (orders_id, user_id, rating, review_text) 
                VALUES (:order_id, :user_id, :rating, :review_text)
            ");
            $stmt->execute([
                'order_id' => $order_id,
                'user_id' => $user_id,
                'rating' => $rating,
                'review_text' => $review_text
            ]);
            
            $_SESSION['success_message'] = 'Thank you for your rating! Your feedback helps us improve our service.';
            header('Location: orders.php');
            exit();
        } catch (PDOException $e) {
            $error_message = 'Unable to save your rating. Please try again.';
        }
        } else {
            $error_message = 'Please select a rating from 1 to 5 stars.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rate Your Order - MikeMadz</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #016bf8;
            --bs-light: #f0f3f2;
            --bs-dark: #001e2b;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background-color: #f8f9fa;
            color: var(--bs-dark);
        }

        .navbar {
            background: rgba(255,255,255,0.95) !important;
            backdrop-filter: blur(10px);
            border-bottom: 1px solid #e9ecef;
        }

        .navbar-brand {
            font-weight: 800;
            font-size: 1.8rem;
            color: var(--bs-secondary) !important;
        }

        .rating-container {
            max-width: 600px;
            margin: 2rem auto;
            background: white;
            border-radius: 1rem;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            padding: 2.5rem;
        }

        .order-info {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 0.75rem;
            margin-bottom: 2rem;
            border-left: 4px solid var(--bs-secondary);
        }

        .star-rating {
            display: flex;
            justify-content: center;
            gap: 0.5rem;
            margin: 2rem 0;
        }

        .star {
            font-size: 2.5rem;
            color: #ddd;
            cursor: pointer;
            transition: all 0.3s ease;
            user-select: none;
        }

        .star:hover,
        .star.active {
            color: #ffc107;
            transform: scale(1.1);
        }

        .star.active {
            text-shadow: 0 0 10px rgba(255, 193, 7, 0.5);
        }

        .rating-text {
            text-align: center;
            font-weight: 600;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
            opacity: 0;
            transition: all 0.3s ease;
        }

        .rating-text.show {
            opacity: 1;
        }

        .review-textarea {
            min-height: 120px;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }

        .review-textarea:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.15);
        }

        .btn-submit {
            background: var(--bs-secondary);
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            color: white;
            transition: all 0.3s ease;
            width: 100%;
        }

        .btn-submit:hover {
            background: #6b1429;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127,23,52,0.3);
        }

        .btn-submit:disabled {
            background: #6c757d;
            cursor: not-allowed;
            transform: none;
        }

        .back-link {
            color: var(--bs-secondary);
            text-decoration: none;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .back-link:hover {
            color: #6b1429;
            gap: 0.75rem;
        }

        .alert {
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--bs-danger);
            border-left: 4px solid var(--bs-danger);
        }

        @media (max-width: 768px) {
            .rating-container {
                margin: 1rem;
                padding: 1.5rem;
            }

            .star {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- Navigation -->
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
                    Rate Your Order
                </h2>
                <p class="text-muted">Help us improve by sharing your experience</p>
            </div>

            <div class="order-info">
                <h6 class="mb-2"><i class="fas fa-receipt me-2"></i>Order #<?php echo $order['orders_id']; ?></h6>
                <div class="row">
                    <div class="col-md-6">
                        <small class="text-muted">Date:</small><br>
                        <span><?php echo date('F j, Y', strtotime($order['created_at'])); ?></span>
                    </div>
                    <div class="col-md-6">
                        <small class="text-muted">Total:</small><br>
                        <span class="fw-bold">₱<?php echo number_format($order['total_price'], 2); ?></span>
                    </div>
                </div>
            </div>

            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <?php echo htmlspecialchars($error_message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="ratingForm">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                <div class="star-rating">
                    <i class="fas fa-star star" data-rating="1"></i>
                    <i class="fas fa-star star" data-rating="2"></i>
                    <i class="fas fa-star star" data-rating="3"></i>
                    <i class="fas fa-star star" data-rating="4"></i>
                    <i class="fas fa-star star" data-rating="5"></i>
                </div>

                <div class="rating-text" id="ratingText">
                    Please select a rating
                </div>

                <input type="hidden" name="rating" id="selectedRating" required>

                <div class="mb-4">
                    <label for="review_text" class="form-label fw-semibold">
                        <i class="fas fa-comment me-2"></i>
                        Share your experience (optional)
                    </label>
                    <textarea 
                        class="form-control review-textarea" 
                        id="review_text" 
                        name="review_text" 
                        placeholder="Tell us about your order experience, food quality, delivery service, etc."
                        maxlength="500"
                    ></textarea>
                    <small class="text-muted">0/500 characters</small>
                </div>

                <button type="submit" class="btn btn-submit" id="submitBtn" disabled>
                    <i class="fas fa-paper-plane me-2"></i>
                    Submit Rating
                </button>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const stars = document.querySelectorAll('.star');
            const ratingText = document.getElementById('ratingText');
            const selectedRating = document.getElementById('selectedRating');
            const submitBtn = document.getElementById('submitBtn');
            const reviewTextarea = document.getElementById('review_text');
            const charCount = document.querySelector('.text-muted');

            const ratingTexts = {
                1: 'Poor - Very unsatisfied',
                2: 'Fair - Below expectations',
                3: 'Good - Met expectations',
                4: 'Very Good - Above expectations',
                5: 'Excellent - Outstanding experience!'
            };

            // Star rating functionality
            stars.forEach((star, index) => {
                star.addEventListener('mouseenter', function() {
                    highlightStars(index + 1);
                });

                star.addEventListener('click', function() {
                    const rating = index + 1;
                    selectedRating.value = rating;
                    ratingText.textContent = ratingTexts[rating];
                    ratingText.classList.add('show');
                    submitBtn.disabled = false;
                    
                    // Add selected class to clicked star and all previous
                    stars.forEach((s, i) => {
                        if (i <= index) {
                            s.classList.add('active');
                        } else {
                            s.classList.remove('active');
                        }
                    });
                });
            });

            document.querySelector('.star-rating').addEventListener('mouseleave', function() {
                const currentRating = selectedRating.value;
                if (currentRating) {
                    highlightStars(parseInt(currentRating));
                } else {
                    highlightStars(0);
                }
            });

            function highlightStars(count) {
                stars.forEach((star, index) => {
                    if (index < count) {
                        star.classList.add('active');
                    } else {
                        star.classList.remove('active');
                    }
                });
            }

            // Character counter for textarea
            reviewTextarea.addEventListener('input', function() {
                const count = this.value.length;
                charCount.textContent = `${count}/500 characters`;
                
                if (count > 450) {
                    charCount.style.color = '#dc3545';
                } else {
                    charCount.style.color = '#6c757d';
                }
            });

            // Form submission with loading state
            document.getElementById('ratingForm').addEventListener('submit', function() {
                submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Submitting...';
                submitBtn.disabled = true;
            });
        });
    </script>
</body>
</html>