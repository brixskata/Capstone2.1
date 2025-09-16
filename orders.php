
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

// Fetch user information using normalized structure
$user_id = $_SESSION['user_id'];
$sql = "SELECT u.user_id, u.username, ui.first_name, ui.last_name, ui.email, ui.phone, ui.user_info_id, ui.profile_picture
FROM users u
INNER JOIN user_info ui ON u.user_id = ui.user_id
WHERE u.user_id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle profile updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $profile_picture = $user['profile_picture'] ?? 'uploads/default.png'; // Keep existing or default
    
    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $upload_dir = 'uploads/';
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        
        if (in_array($file_type, $allowed_types) && $file_size <= $max_size) {
            $file_extension = pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION);
            $new_filename = 'profile_' . $user_id . '_' . time() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $upload_path)) {
                // Delete old profile picture if it's not the default
                $old_picture = $user['profile_picture'] ?? '';
                if ($old_picture && $old_picture !== 'uploads/default.png' && file_exists($old_picture)) {
                    unlink($old_picture);
                }
                $profile_picture = $upload_path;
            }
        }
    }
    
    $sql = "UPDATE user_info SET
        first_name = :first_name,
        last_name = :last_name,
        email = :email,
        phone = :phone,
        profile_picture = :profile_picture
        WHERE user_id = :user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':profile_picture' => $profile_picture,
        ':user_id' => $user_id
    ]);

    header('Location: orders.php?profile_updated=1');
    exit;
}

// Handle address operations
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['address_action'])) {
    $action = $_POST['address_action'];
    
    if ($action === 'add') {
        $sql = "INSERT INTO addresses (user_id, address_line, address_line2, city, state, postal_code, country, is_default) 
                VALUES (:user_id, :address_line, :address_line2, :city, :state, :postal_code, :country, :is_default)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':user_id' => $user_id,
            ':address_line' => $_POST['address_line'],
            ':address_line2' => $_POST['address_line2'] ?? '',
            ':city' => $_POST['city'],
            ':state' => $_POST['state'] ?? '',
            ':postal_code' => $_POST['postal_code'],
            ':country' => $_POST['country'] ?? 'Philippines',
            ':is_default' => isset($_POST['is_default']) ? 1 : 0
        ]);
        
        // If this is set as default, unset other defaults
        if (isset($_POST['is_default'])) {
            $sql = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id AND address_id != :address_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $user_id, ':address_id' => $pdo->lastInsertId()]);
        }
        
        header('Location: orders.php?address_added=1');
        exit;
    }
    
    if ($action === 'edit') {
        $sql = "UPDATE addresses SET 
                address_line = :address_line,
                address_line2 = :address_line2,
                city = :city,
                state = :state,
                postal_code = :postal_code,
                country = :country,
                is_default = :is_default
                WHERE address_id = :address_id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':address_line' => $_POST['address_line'],
            ':address_line2' => $_POST['address_line2'] ?? '',
            ':city' => $_POST['city'],
            ':state' => $_POST['state'] ?? '',
            ':postal_code' => $_POST['postal_code'],
            ':country' => $_POST['country'] ?? 'Philippines',
            ':is_default' => isset($_POST['is_default']) ? 1 : 0,
            ':address_id' => $_POST['address_id'],
            ':user_id' => $user_id
        ]);
        
        // If this is set as default, unset other defaults
        if (isset($_POST['is_default'])) {
            $sql = "UPDATE addresses SET is_default = 0 WHERE user_id = :user_id AND address_id != :address_id";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([':user_id' => $user_id, ':address_id' => $_POST['address_id']]);
        }
        
        header('Location: orders.php?address_updated=1');
        exit;
    }
    
    if ($action === 'delete') {
        $sql = "DELETE FROM addresses WHERE address_id = :address_id AND user_id = :user_id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([':address_id' => $_POST['address_id'], ':user_id' => $user_id]);
        
        header('Location: orders.php?address_deleted=1');
        exit;
    }
}

// Handle rating submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
$sql = "INSERT INTO order_ratings (orders_id, user_id, rating, review_text)
VALUES (:orders_id, :user_id, :rating, :review_text)";
$stmt = $pdo->prepare($sql);
$stmt->execute([
':orders_id' => $_POST['orders_id'],
':user_id' => $user_id,
':rating' => $_POST['rating'],
':review_text' => $_POST['review_text']
]);


header("Location: orders.php?rating_submitted=1");
exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_rating'])) {
    $order_id = $_POST['order_id'];
    $rating = $_POST['rating'];
    $review_text = $_POST['review_text'] ?? '';

    $sql = "INSERT INTO order_ratings (orders_id, user_id, rating, review_text, created_at)
            VALUES (:order_id, :user_id, :rating, :review_text, NOW())
            ON DUPLICATE KEY UPDATE rating = :rating, review_text = :review_text, created_at = NOW()";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':order_id' => $order_id,
        ':user_id' => $user_id,
        ':rating' => $rating,
        ':review_text' => $review_text
    ]);

    header("Location: orders.php?rating_submitted=1");
    exit;
}



// Fetch all orders (current and completed)
$sql = "SELECT o.orders_id, o.created_at, os.status_name as status, o.total_price,
oi.quantity, p.product_name, pp.selling_price as price
FROM orders o
LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
LEFT JOIN order_items oi ON o.orders_id = oi.order_id
LEFT JOIN products p ON oi.product_id = p.product_id
LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
WHERE o.user_id = :user_id
ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$rawOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);


$allOrders = [];
foreach ($rawOrders as $row) {
$orderId = $row['orders_id'];
if (!isset($allOrders[$orderId])) {
$allOrders[$orderId] = [
'id' => $row['orders_id'],
'created_at' => $row['created_at'],
'status' => $row['status'],
'total_price' => $row['total_price'],
'items' => []
];
}


if ($row['product_name']) {
$allOrders[$orderId]['items'][] = [
'product_name' => $row['product_name'],
'quantity' => $row['quantity'],
'price' => $row['price']
];
}
}

// Separate current and completed orders
$currentOrders = array_filter($allOrders, function($order) {
return !in_array($order['status'], ['Completed', 'Cancelled']);
});


$completedOrders = array_filter($allOrders, function($order) {
return in_array($order['status'], ['Completed', 'Cancelled']);
});


// Fetch ratings for completed orders
$orderRatings = [];
if (!empty($completedOrders)) {
$orderIds = array_column($completedOrders, 'id');


if (!empty($orderIds)) {
$placeholders = implode(',', array_fill(0, count($orderIds), '?'));


$ratingsStmt = $pdo->prepare("
SELECT orders_id, rating, review_text, created_at
FROM order_ratings
WHERE orders_id IN ($placeholders) AND user_id = ?
");
$ratingsStmt->execute(array_merge($orderIds, [$user_id]));
$ratings = $ratingsStmt->fetchAll(PDO::FETCH_ASSOC);


foreach ($ratings as $rating) {
$orderRatings[$rating['orders_id']] = $rating;
}
}
}

// Fetch user addresses
$sql = "SELECT * FROM addresses WHERE user_id = :user_id ORDER BY is_default DESC, date_created DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$addresses = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    /* Order card styling */
.order-card {
  background: #fff;
  padding: 15px;
  margin: 15px 0;
  border-radius: 12px;
  box-shadow: 0 2px 6px rgba(0,0,0,0.1);
}

/* Status badge */
.status-badge {
  padding: 4px 10px;
  border-radius: 20px;
  font-size: 0.85em;
  font-weight: bold;
}
.status-completed { background: #d4edda; color: #155724; }
.status-cancelled { background: #f8d7da; color: #721c24; }
.status-pending   { background: #fff3cd; color: #856404; }

/* Ratings */
.rating-section { margin-top: 15px; }
.star-rating {
  direction: rtl; /* so clicking left to right works */
  display: inline-flex;
}
.star-rating input { display: none; }
.star-rating label {
  font-size: 24px;
  color: #ccc;
  cursor: pointer;
  transition: color 0.2s;
}
.star-rating input:checked ~ label i,
.star-rating label:hover ~ label i,
.star-rating label:hover i {
  color: gold;
}
.stars .fa-star {
  color: #ccc;
}
.stars .filled {
  color: gold;
}
.rating-form textarea {
  display: block;
  width: 100%;
  margin: 10px 0;
  padding: 8px;
  border-radius: 6px;
  border: 1px solid #ddd;
}
.rating-form button {
  padding: 6px 12px;
  border: none;
  background: #007bff;
  color: white;
  border-radius: 6px;
  cursor: pointer;
}


    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile & Orders</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">

    <style>
        :root {
            --bs-primary: #ffffff;
            --bs-secondary: #7F1734;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            line-height: 1.6;
            color: var(--bs-dark);
            background: linear-gradient(135deg, var(--bs-light) 0%, #ffffff 100%);
            min-height: 100vh;
        }

        /* Promo Banner */
        .promo-banner {
            background: var(--bs-secondary);
            color: white;
            padding: 0.75rem 0;
            font-weight: 500;
            font-size: 0.9rem;
        }

        /* Profile Container */
        .profile-container {
            max-width: 1200px;
            margin: 2rem auto;
            padding: 0 1rem;
        }

        /* Sidebar */
        .profile-sidebar {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
            height: fit-content;
            position: sticky;
            top: 2rem;
        }

        .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .profile-name {
            font-size: 1.2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 2rem;
            text-align: center;
        }

        .nav-links {
            list-style: none;
            padding: 0;
        }

        .nav-link-item {
            margin-bottom: 0.5rem;
        }

        .nav-link-btn {
            display: flex;
            align-items: center;
            width: 100%;
            padding: 0.75rem 1rem;
            border: none;
            background: transparent;
            color: #6c757d;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .nav-link-btn:hover,
        .nav-link-btn.active {
            background: var(--bs-secondary);
            color: white;
            transform: translateX(5px);
        }

        .nav-link-btn i {
            margin-right: 0.75rem;
            width: 20px;
        }

        /* Content Area */
        .content-area {
            background: white;
            border-radius: 1rem;
            padding: 2rem;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #e9ecef;
        }

        .content-section {
            display: none;
        }

        .content-section.active {
            display: block;
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .section-title i {
            margin-right: 0.75rem;
        }

        /* Success Alert */
        .success-alert {
            background: var(--bs-success);
            color: white;
            padding: 1rem;
            border-radius: 0.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }

        .success-alert i {
            margin-right: 0.75rem;
        }

        /* Form Styles */
        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.5rem;
            display: block;
        }

        .form-control {
            border: 2px solid #e9ecef;
            border-radius: 0.5rem;
            padding: 0.75rem 1rem;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127,23,52,0.25);
        }

        .btn-primary {
            background: var(--bs-secondary);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 0.5rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .btn-primary:hover {
            background: #6b1429;
            transform: translateY(-2px);
        }

        /* Profile Image Upload */
        .profile-image-upload {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 1rem;
            background: var(--bs-light);
            border-radius: 0.5rem;
        }

        .upload-preview {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid var(--bs-secondary);
        }

        /* Order Cards */
        .order-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e9ecef;
        }

        .order-number {
            font-weight: 700;
            color: var(--bs-secondary);
            font-size: 1.1rem;
        }

        .order-date {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .order-status {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .status-processing {
            background: var(--bs-warning);
            color: var(--bs-dark);
        }

        .status-shipped {
            background: var(--bs-info);
            color: white;
        }

        .status-delivered {
            background: var(--bs-success);
            color: white;
        }

        .order-items {
            margin-bottom: 1rem;
        }

        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #f8f9fa;
        }

        .order-item:last-child {
            border-bottom: none;
        }

        .item-name {
            font-weight: 500;
            color: var(--bs-dark);
        }

        .item-price {
            font-weight: 600;
            color: var(--bs-secondary);
        }

        .order-total {
            text-align: right;
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--bs-secondary);
            padding-top: 1rem;
            border-top: 2px solid var(--bs-secondary);
        }

        /* Rating Section Styles */
        .rating-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #e9ecef;
        }

        .existing-rating {
            text-align: center;
        }

        .rating-stars {
            margin-bottom: 0.5rem;
        }

        .star-filled {
            color: #ffc107;
            margin-right: 2px;
        }

        .star-empty {
            color: #dee2e6;
            margin-right: 2px;
        }

        .rating-text {
            margin-left: 0.5rem;
            font-weight: 600;
            color: var(--bs-secondary);
        }

        .rating-review {
            background: #f8f9fa;
            padding: 0.75rem;
            border-radius: 0.5rem;
            margin: 0.5rem 0;
            font-style: italic;
            color: #6c757d;
            border-left: 3px solid var(--bs-secondary);
        }

        .rating-review i {
            color: var(--bs-secondary);
            margin-right: 0.5rem;
        }

        .rating-date {
            font-size: 0.8rem;
        }

        .rate-order {
            text-align: center;
            padding: 0.5rem 0;
        }

        .btn-rate {
            background: var(--bs-secondary);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
        }

        .btn-rate:hover {
            background: #6b1429;
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 4px 15px rgba(127,23,52,0.3);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 2rem;
            color: #6c757d;
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: #dee2e6;
        }

        .empty-state h3 {
            margin-bottom: 1rem;
            color: var(--bs-dark);
        }

        .empty-state a {
            color: var(--bs-secondary);
            text-decoration: none;
            font-weight: 600;
        }

        .empty-state a:hover {
            text-decoration: underline;
        }

        /* Dashboard Styles */
        .stat-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .stat-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
        }

        .stat-content h3 {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-secondary);
            margin: 0;
        }

        .stat-content p {
            color: #6c757d;
            margin: 0;
            font-weight: 500;
        }

        .dashboard-section {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .dashboard-subtitle {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .dashboard-subtitle i {
            margin-right: 0.5rem;
        }

        .recent-orders {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .recent-order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            border-left: 4px solid var(--bs-secondary);
        }

        .order-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .order-number {
            font-weight: 600;
            color: var(--bs-dark);
        }

        .order-date {
            font-size: 0.9rem;
            color: #6c757d;
        }

        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .quick-action-btn {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 1.5rem;
            background: white;
            border: 2px solid #e9ecef;
            border-radius: 0.75rem;
            text-decoration: none;
            color: var(--bs-dark);
            transition: all 0.3s ease;
        }

        .quick-action-btn:hover {
            border-color: var(--bs-secondary);
            color: var(--bs-secondary);
            transform: translateY(-2px);
        }

        .quick-action-btn i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .quick-action-btn span {
            font-weight: 600;
        }

        /* Dashboard Addresses Styles */
        .dashboard-addresses {
            display: flex;
            flex-direction: column;
            gap: 1rem;
        }

        .dashboard-address-item {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            padding: 1rem;
            background: #f8f9fa;
            border-radius: 0.5rem;
            border-left: 4px solid var(--bs-secondary);
            transition: all 0.3s ease;
        }

        .dashboard-address-item:hover {
            background: #e9ecef;
            transform: translateX(5px);
        }

        .address-info {
            flex: 1;
        }

        .address-header {
            margin-bottom: 0.5rem;
        }

        .address-type {
            font-weight: 600;
            color: var(--bs-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-details p {
            margin: 0.25rem 0;
            color: var(--bs-dark);
            font-size: 0.9rem;
        }

        .address-details p:first-child {
            font-weight: 600;
        }

        .address-actions {
            display: flex;
            gap: 0.5rem;
            margin-left: 1rem;
        }

        .view-all-addresses {
            text-align: center;
            margin: 1rem 0;
        }

        .add-address-btn {
            text-align: center;
            margin-top: 1rem;
        }

        /* Order Tabs */
        .order-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid #e9ecef;
        }

        .tab-btn {
            background: none;
            border: none;
            padding: 0.75rem 1.5rem;
            font-weight: 600;
            color: #6c757d;
            border-bottom: 3px solid transparent;
            transition: all 0.3s ease;
        }

        .tab-btn.active {
            color: var(--bs-secondary);
            border-bottom-color: var(--bs-secondary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Address Styles */
        .addresses-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .addresses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 1.5rem;
        }

        .address-card {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
        }

        .address-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }

        .address-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }

        .address-header h5 {
            margin: 0;
            color: var(--bs-secondary);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .default-badge {
            background: var(--bs-success);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 50px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .address-actions {
            display: flex;
            gap: 0.5rem;
        }

        .address-content p {
            margin: 0.25rem 0;
            color: var(--bs-dark);
        }

        .address-content p:first-child {
            font-weight: 600;
        }

        /* Account Styles */
        .account-content {
            background: white;
            border: 1px solid #e9ecef;
            border-radius: 0.75rem;
            padding: 2rem;
        }

        .profile-picture-section {
            text-align: center;
        }

        .profile-picture-section h4 {
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .profile-avatar-large {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid var(--bs-secondary);
            margin-bottom: 1rem;
        }

        .upload-actions {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        /* Modal Styles */
        .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            border-radius: 1rem 1rem 0 0;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Logout Modal Styles */
        .logout-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .logout-title {
            color: var(--bs-secondary);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .logout-message {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 0;
        }

        #logoutModal .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        #logoutModal .modal-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            border-radius: 1rem 1rem 0 0;
            border-bottom: none;
        }

        #logoutModal .modal-body {
            padding: 2rem;
        }

        #logoutModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem 2rem;
        }

        #logoutModal .btn-primary {
            background: var(--bs-secondary);
            border-color: var(--bs-secondary);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #logoutModal .btn-primary:hover {
            background: #6b1429;
            border-color: #6b1429;
            transform: translateY(-1px);
        }

        #logoutModal .btn-secondary {
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #logoutModal .btn-secondary:hover {
            transform: translateY(-1px);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .profile-container {
                margin: 1rem auto;
            }

            .profile-sidebar {
                position: static;
                margin-bottom: 1rem;
            }

            .order-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .section-title {
                font-size: 1.3rem;
            }

            .stat-card {
                flex-direction: column;
                text-align: center;
            }

            .quick-actions {
                grid-template-columns: repeat(2, 1fr);
            }

            .addresses-grid {
                grid-template-columns: 1fr;
            }

            .address-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .address-actions {
                width: 100%;
                justify-content: flex-end;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/promo_banner.php'; ?>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="profile-container">
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="profile-sidebar">
                    <div class="text-center">
                        <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'uploads/default.png') ?>"
                             alt="Profile Picture"
                             class="profile-avatar" />
                        <div class="profile-name"><?= htmlspecialchars($user['username']); ?></div>
                    </div>

                    <ul class="nav-links">
                        <li class="nav-link-item">
                            <a href="#dashboard" class="nav-link-btn active">
                                <i class="fas fa-tachometer-alt"></i>
                                My Dashboard
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="#orders" class="nav-link-btn">
                                <i class="fas fa-box"></i>
                                My Orders
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="#addresses" class="nav-link-btn">
                                <i class="fas fa-map-marker-alt"></i>
                                My Addresses
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="#account" class="nav-link-btn">
                                <i class="fas fa-user-cog"></i>
                                My Account
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="#" class="nav-link-btn" onclick="confirmLogout()">
                                <i class="fas fa-sign-out-alt"></i>
                                Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Content Area -->
            <div class="col-lg-9">
                <div class="content-area">
                    <!-- Dashboard Section -->
                    <div id="dashboard" class="content-section active">
                        <h2 class="section-title">
                            <i class="fas fa-tachometer-alt"></i>
                            My Dashboard
                        </h2>


                        <!-- Recent Orders -->
                        <div class="dashboard-section">
                            <h4 class="dashboard-subtitle">
                                <i class="fas fa-clock"></i>
                                Recent Orders
                            </h4>
                            <?php if (empty($currentOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-bag"></i>
                                    <h3>No Recent Orders</h3>
                                    <p>You don't have any recent orders.</p>
                                    <a href="product.php">Start Shopping</a>
                                </div>
                            <?php else: ?>
                                <div class="recent-orders">
                                    <?php foreach (array_slice($currentOrders, 0, 3) as $order): ?>
                                        <div class="recent-order-item">
                                            <div class="order-info">
                                                <span class="order-number">Order #<?= $order['orders_id'] ?></span>
                                                <span class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></span>
                                            </div>
                                            <div class="order-status">
                                                <span class="status-badge status-<?= strtolower($order['status']) ?>">
                                                    <?= $order['status'] ?>
                                                </span>
                                            </div>
                                            <div class="order-total">
                                                ₱<?= number_format($order['total_price'], 2) ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- My Addresses -->
                        <div class="dashboard-section">
                            <h4 class="dashboard-subtitle">
                                <i class="fas fa-map-marker-alt"></i>
                                My Addresses
                            </h4>
                            <?php if (empty($addresses)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <h3>No Addresses</h3>
                                    <p>You haven't added any addresses yet.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                        Add Your First Address
                                    </button>
                                </div>
                            <?php else: ?>
                                <div class="dashboard-addresses">
                                    <?php foreach (array_slice($addresses, 0, 3) as $address): ?>
                                        <div class="dashboard-address-item">
                                            <div class="address-info">
                                                <div class="address-header">
                                                    <span class="address-type">
                                                        <?= $address['is_default'] ? 'Default Address' : 'Address' ?>
                                                        <?= $address['is_default'] ? '<span class="default-badge">Default</span>' : '' ?>
                                                    </span>
                                                </div>
                                                <div class="address-details">
                                                    <p><strong><?= htmlspecialchars($address['address_line']) ?></strong></p>
                                                    <?php if ($address['address_line2']): ?>
                                                        <p><?= htmlspecialchars($address['address_line2']) ?></p>
                                                    <?php endif; ?>
                                                    <p>
                                                        <?= htmlspecialchars($address['city']) ?>
                                                        <?= $address['state'] ? ', ' . htmlspecialchars($address['state']) : '' ?>
                                                        <?= $address['postal_code'] ? ' ' . htmlspecialchars($address['postal_code']) : '' ?>
                                                    </p>
                                                    <p><?= htmlspecialchars($address['country']) ?></p>
                                                </div>
                                            </div>
                                            <div class="address-actions">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAddress(<?= htmlspecialchars(json_encode($address)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                    
                                    <?php if (count($addresses) > 3): ?>
                                        <div class="view-all-addresses">
                                            <a href="#addresses" class="btn btn-outline-primary">
                                                <i class="fas fa-eye me-2"></i>
                                                View All Addresses (<?= count($addresses) ?>)
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="add-address-btn">
                                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                            <i class="fas fa-plus me-2"></i>
                                            Add New Address
                                        </button>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Orders Section -->
                    <div id="orders" class="content-section">
                        <h2 class="section-title">
                            <i class="fas fa-box"></i>
                            My Orders
                        </h2>

                        <!-- Order Tabs -->
                        <div class="order-tabs">
                            <button class="tab-btn active" data-tab="current">Current Orders</button>
                            <button class="tab-btn" data-tab="completed">Order History</button>
                        </div>

                        <!-- Current Orders Tab -->
                        <div id="current-orders" class="tab-content active">
                            <?php if (empty($currentOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-shopping-bag"></i>
                                    <h3>No Current Orders</h3>
                                    <p>You don't have any current orders.</p>
                                    <a href="product.php">Start Shopping</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($currentOrders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-number">Order #<?= $order['orders_id'] ?></div>
                                                <div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                            <span class="order-status status-<?= strtolower($order['status']) ?>">
                                                <?= $order['status'] ?>
                                            </span>
                                        </div>

                                        <div class="order-items">
                                            <?php foreach ($order['items'] as $item): ?>
                                                <div class="order-item">
                                                    <span class="item-name"><?= htmlspecialchars($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                                                    <span class="item-price">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
                                                </div>
                                            <?php endforeach; ?>
                                        </div>

                                        <div class="order-total">
                                            Total: ₱<?= number_format($order['total_price'], 2) ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Completed Orders Tab -->
                        <div id="completed-orders" class="tab-content">
                            <?php if (empty($completedOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clock"></i>
                                    <h3>No Order History</h3>
                                    <p>You don't have any completed orders yet.</p>
                                    <a href="product.php">Start Shopping</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($completedOrders as $order): ?>
<div class="order-card">
<div class="order-header">
<div>
<div class="order-number">Order #<?= $order['id'] ?></div>
<div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
</div>
<span class="order-status status-<?= strtolower($order['status']) ?>">
<?= $order['status'] ?>
</span>
</div>


<div class="order-items">
<?php foreach ($order['items'] as $item): ?>
<div class="order-item">
<span class="item-name"><?= htmlspecialchars($item['product_name']) ?> × <?= $item['quantity'] ?></span>
<span class="item-price">₱<?= number_format($item['price'] * $item['quantity'], 2) ?></span>
</div>
<?php endforeach; ?>
</div>


<div class="order-total">
Total: ₱<?= number_format($order['total_price'], 2) ?>
</div>


<!-- Rating Section -->
<?php if (in_array($order['status'], ['Completed'])): ?>
    <div class="rating-section">
        <?php if (isset($orderRatings[$order['id']])): ?>
            <p><strong>Your Rating:</strong> 
                <span class="stars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <i class="fas fa-star <?= $i <= $orderRatings[$order['id']]['rating'] ? 'filled' : '' ?>"></i>
                    <?php endfor; ?>
                </span>
            </p>
            <p><em>"<?= htmlspecialchars($orderRatings[$order['id']]['review_text']) ?>"</em></p>
        <?php else: ?>
            <form action="orders.php" method="POST" class="rating-form">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <div class="star-rating" data-order="<?= $order['id'] ?>">
                    <?php for ($i = 5; $i >= 1; $i--): ?>
                        <input type="radio" id="star<?= $i ?>-<?= $order['id'] ?>" name="rating" value="<?= $i ?>">
                        <label for="star<?= $i ?>-<?= $order['id'] ?>"><i class="fas fa-star"></i></label>
                    <?php endfor; ?>
                </div>
                <textarea name="review_text" placeholder="Leave a review..."></textarea>
                <button type="submit" name="submit_rating">Submit Review</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- Show existing rating -->
    <div class="existing-rating">
        <div class="rating-stars">
            <?php
            $rating = $orderRatings[$order['id']]['rating'];
            for ($i = 1; $i <= 5; $i++):
            ?>
                <i class="fas fa-star <?= $i <= $rating ? 'star-filled' : 'star-empty' ?>"></i>
            <?php endfor; ?>
            <span class="rating-text">(<?= $rating ?>/5)</span>
        </div>

        <?php if (!empty($orderRatings[$order['id']]['review_text'])): ?>
            <div class="rating-review">
                <i class="fas fa-quote-left"></i>
                <?= htmlspecialchars($orderRatings[$order['id']]['review_text']) ?>
            </div>
        <?php endif; ?>

        <small class="rating-date text-muted">
            Rated on <?= date('M j, Y', strtotime($orderRatings[$order['id']]['created_at'])) ?>
        </small>
    </div>
<?php else: ?>
    <!-- Show rating form -->
    <form method="POST" action="orders.php" class="rating-form">
        <input type="hidden" name="orders_id" value="<?= $order['id'] ?>">

        <label for="rating">Rate this order:</label>
        <select name="rating" required>
            <option value="">-- Select --</option>
            <option value="1">⭐ 1</option>
            <option value="2">⭐⭐ 2</option>
            <option value="3">⭐⭐⭐ 3</option>
            <option value="4">⭐⭐⭐⭐ 4</option>
            <option value="5">⭐⭐⭐⭐⭐ 5</option>
        </select>

        <label for="review_text">Review:</label>
        <textarea name="review_text" rows="2" placeholder="Write your feedback..."></textarea>

        <button type="submit" name="submit_rating">Submit Rating</button>
    </form>
<?php endif; ?>
</div>
<?php endforeach; ?>
<?php endif; ?>
</div>


                        </div>
                    </div>

                    <!-- Addresses Section -->
                    <div id="addresses" class="content-section">
                        <h2 class="section-title">
                            <i class="fas fa-map-marker-alt"></i>
                            My Addresses
                        </h2>

                        <?php if (isset($_GET['address_added'])): ?>
                            <div class="success-alert">
                                <i class="fas fa-check-circle"></i>
                                Address added successfully!
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['address_updated'])): ?>
                            <div class="success-alert">
                                <i class="fas fa-check-circle"></i>
                                Address updated successfully!
                            </div>
                        <?php endif; ?>

                        <?php if (isset($_GET['address_deleted'])): ?>
                            <div class="success-alert">
                                <i class="fas fa-check-circle"></i>
                                Address deleted successfully!
                            </div>
                        <?php endif; ?>

                        <div class="addresses-header">
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                <i class="fas fa-plus me-2"></i>
                                Add New Address
                            </button>
                        </div>

                        <div class="addresses-grid">
                            <?php if (empty($addresses)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-map-marker-alt"></i>
                                    <h3>No Addresses</h3>
                                    <p>You haven't added any addresses yet.</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
                                        Add Your First Address
                                    </button>
                                </div>
                            <?php else: ?>
                                <?php foreach ($addresses as $address): ?>
                                    <div class="address-card">
                                        <div class="address-header">
                                            <h5>
                                                <?= $address['is_default'] ? 'Default Address' : 'Address' ?>
                                                <?= $address['is_default'] ? '<span class="default-badge">Default</span>' : '' ?>
                                            </h5>
                                            <div class="address-actions">
                                                <button class="btn btn-sm btn-outline-primary" onclick="editAddress(<?= htmlspecialchars(json_encode($address)) ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" onclick="deleteAddress(<?= $address['address_id'] ?>)">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                        <div class="address-content">
                                            <p><strong><?= htmlspecialchars($address['address_line']) ?></strong></p>
                                            <?php if ($address['address_line2']): ?>
                                                <p><?= htmlspecialchars($address['address_line2']) ?></p>
                                            <?php endif; ?>
                                            <p>
                                                <?= htmlspecialchars($address['city']) ?>
                                                <?= $address['state'] ? ', ' . htmlspecialchars($address['state']) : '' ?>
                                                <?= $address['postal_code'] ? ' ' . htmlspecialchars($address['postal_code']) : '' ?>
                                            </p>
                                            <p><?= htmlspecialchars($address['country']) ?></p>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Account Section -->
                    <div id="account" class="content-section">
                        <h2 class="section-title">
                            <i class="fas fa-user-cog"></i>
                            My Account
                        </h2>

                        <?php if (isset($_GET['profile_updated'])): ?>
                            <div class="success-alert">
                                <i class="fas fa-check-circle"></i>
                                Profile updated successfully!
                            </div>
                        <?php endif; ?>

                        <div class="account-content">
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="profile-picture-section">
                                        <h4>Profile Picture</h4>
                                        <div class="profile-picture-upload">
                                            <img src="<?= htmlspecialchars($user['profile_picture'] ?? 'uploads/default.png') ?>" alt="Profile Picture" class="profile-avatar-large" id="profilePreview">
                                            <div class="upload-actions">
                                                <button class="btn btn-outline-primary" onclick="document.getElementById('profileImage').click()">
                                                    <i class="fas fa-camera me-2"></i>
                                                    Change Photo
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <form method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="update_profile" value="1">

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">First Name</label>
                                                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Last Name</label>
                                                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Phone Number</label>
                                                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-control">
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label">Profile Picture</label>
                                                    <input type="file" name="profile_picture" id="profileImage" accept="image/*" class="form-control">
                                                    <small class="form-text text-muted">Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB</small>
                                                </div>
                                            </div>
                                        </div>

                                        <div class="text-end">
                                            <button type="submit" class="btn btn-primary">
                                                <i class="fas fa-save me-2"></i>
                                                Update Profile
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1" aria-labelledby="addAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAddressModalLabel">
                        <i class="fas fa-plus me-2"></i>Add New Address
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="address_action" value="add">
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Address Line 1 *</label>
                                <input type="text" name="address_line" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" name="address_line2" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">State/Province</label>
                                <input type="text" name="state" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code *</label>
                                <input type="text" name="postal_code" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Country</label>
                                <input type="text" name="country" class="form-control" value="Philippines">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" id="isDefault">
                                    <label class="form-check-label" for="isDefault">
                                        Set as default address
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Add Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Address Modal -->
    <div class="modal fade" id="editAddressModal" tabindex="-1" aria-labelledby="editAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel">
                        <i class="fas fa-edit me-2"></i>Edit Address
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="address_action" value="edit">
                        <input type="hidden" name="address_id" id="editAddressId">
                        
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Address Line 1 *</label>
                                <input type="text" name="address_line" id="editAddressLine" class="form-control" required>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Address Line 2</label>
                                <input type="text" name="address_line2" id="editAddressLine2" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">City *</label>
                                <input type="text" name="city" id="editCity" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">State/Province</label>
                                <input type="text" name="state" id="editState" class="form-control">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Postal Code *</label>
                                <input type="text" name="postal_code" id="editPostalCode" class="form-control" required>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Country</label>
                                <input type="text" name="country" id="editCountry" class="form-control">
                            </div>
                            <div class="col-12">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="is_default" id="editIsDefault">
                                    <label class="form-check-label" for="editIsDefault">
                                        Set as default address
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Address Modal -->
    <div class="modal fade" id="deleteAddressModal" tabindex="-1" aria-labelledby="deleteAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteAddressModalLabel">
                        <i class="fas fa-trash me-2"></i>Delete Address
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="address_action" value="delete">
                        <input type="hidden" name="address_id" id="deleteAddressId">
                        <p>Are you sure you want to delete this address? This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div class="modal fade" id="logoutModal" tabindex="-1" aria-labelledby="logoutModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="logoutModalLabel">
                        <i class="fas fa-sign-out-alt me-2"></i>Confirm Logout
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="logout-icon">
                        <i class="fas fa-question-circle"></i>
                    </div>
                    <h4 class="logout-title">Are you sure you want to logout?</h4>
                    <p class="logout-message">You will need to sign in again to access your account.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary me-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <button type="button" class="btn btn-primary" onclick="proceedLogout()">
                        <i class="fas fa-sign-out-alt me-2"></i>Yes, Logout
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Navigation functionality
        document.querySelectorAll('.nav-link-btn').forEach(link => {
            link.addEventListener('click', function (e) {
                if (this.getAttribute('href') === 'logout.php') return;
                e.preventDefault();

                // Update active nav link
                document.querySelectorAll('.nav-link-btn').forEach(l => l.classList.remove('active'));
                this.classList.add('active');

                // Show corresponding content section
                const sectionId = this.getAttribute('href').substring(1);
                document.querySelectorAll('.content-section').forEach(s => s.classList.remove('active'));
                document.getElementById(sectionId).classList.add('active');
            });
        });

        // Order tabs functionality
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                const tab = this.getAttribute('data-tab');
                
                // Update active tab button
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                
                // Show corresponding tab content
                document.querySelectorAll('.tab-content').forEach(content => content.classList.remove('active'));
                document.getElementById(tab + '-orders').classList.add('active');
            });
        });

        // Address management functions
        function editAddress(address) {
            document.getElementById('editAddressId').value = address.address_id;
            document.getElementById('editAddressLine').value = address.address_line;
            document.getElementById('editAddressLine2').value = address.address_line2 || '';
            document.getElementById('editCity').value = address.city;
            document.getElementById('editState').value = address.state || '';
            document.getElementById('editPostalCode').value = address.postal_code;
            document.getElementById('editCountry').value = address.country;
            document.getElementById('editIsDefault').checked = address.is_default == 1;
            
            new bootstrap.Modal(document.getElementById('editAddressModal')).show();
        }

        function deleteAddress(addressId) {
            document.getElementById('deleteAddressId').value = addressId;
            new bootstrap.Modal(document.getElementById('deleteAddressModal')).show();
        }

        // Profile picture upload
        document.getElementById('profileImage').addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.getElementById('profilePreview').src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });

        // Logout confirmation
        function confirmLogout() {
            new bootstrap.Modal(document.getElementById('logoutModal')).show();
        }

        function proceedLogout() {
            window.location.href = 'logout.php';
        }

        // Auto-hide alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.success-alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.transition = 'opacity 0.5s ease-out';
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.remove();
                    }, 500);
                }, 5000);
            });
        });
    </script>
    <script>
document.addEventListener("DOMContentLoaded", function() {
    document.querySelectorAll(".star-rating").forEach(starBlock => {
        const stars = starBlock.querySelectorAll("label i");
        stars.forEach((star, index) => {
            star.addEventListener("click", () => {
                stars.forEach((s, i) => {
                    s.style.color = i >= index ? "gold" : "#ccc";
                });
            });
        });
    });
});
</script>

</body>
</html>
