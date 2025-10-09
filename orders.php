
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
        $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        $file_type = $_FILES['profile_picture']['type'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_tmp = $_FILES['profile_picture']['tmp_name'];
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
            
            $new_filename = 'profile_' . $user_id . '_' . time() . '_' . uniqid() . '.' . $file_extension;
            $upload_path = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $upload_path)) {
                // Delete old profile picture if it's not the default
                $old_picture = $user['profile_picture'] ?? '';
                if ($old_picture && $old_picture !== 'uploads/default.png' && file_exists($old_picture)) {
                    unlink($old_picture);
                }
                $profile_picture = $upload_path;
                $_SESSION['success'] = "Profile picture updated successfully";
            } else {
                $_SESSION['error'] = "Failed to upload profile picture";
            }
        } else {
            $_SESSION['error'] = implode(', ', $errors);
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


// Fetch all orders (current and completed)
$sql = "SELECT o.orders_id, o.created_at, os.status_name as status, o.total_price,
               oi.quantity, p.product_name, COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as price,
               oc.reason AS cancel_reason,
               a.address_line, a.address_line2, a.city, a.state, a.postal_code, a.country
        FROM orders o
        LEFT JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
        LEFT JOIN order_items oi ON o.orders_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        LEFT JOIN addresses a ON a.address_id = o.address_id
        LEFT JOIN (
            SELECT oc1.order_id, oc1.reason
            FROM order_cancellations oc1
            INNER JOIN (
                SELECT order_id, MAX(id) AS max_id
                FROM order_cancellations
                GROUP BY order_id
            ) latest ON latest.order_id = oc1.order_id AND latest.max_id = oc1.id
        ) oc ON oc.order_id = o.orders_id
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
            'cancel_reason' => $row['cancel_reason'] ?? null,
            'address' => [
                'address_line' => $row['address_line'] ?? '',
                'address_line2' => $row['address_line2'] ?? '',
                'city' => $row['city'] ?? '',
                'state' => $row['state'] ?? '',
                'postal_code' => $row['postal_code'] ?? '',
                'country' => $row['country'] ?? ''
            ],
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

// Separate orders by status
$pendingOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Pending';
});

$toShipOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'To Ship';
});

$outForDeliveryOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Out for delivery';
});

$completedOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Completed';
});

$cancelledOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Cancelled';
});

$readyForPickupOrders = array_filter($allOrders, function($order) {
    return $order['status'] === 'Ready for Pick Up';
});

// For backward compatibility and dashboard
$currentOrders = array_filter($allOrders, function($order) {
    return !in_array($order['status'], ['Completed', 'Cancelled']);
});

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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your Profile & Orders</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    
    <!-- SweetAlert2 CDN -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

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
            background: var(--bs-light);
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
            box-shadow: 0 4px 20px rgba(127, 23, 52, 0.08);
            border: 1px solid rgba(127, 23, 52, 0.1);
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
            box-shadow: 0 4px 20px rgba(127, 23, 52, 0.08);
            border: 1px solid rgba(127, 23, 52, 0.1);
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
            background: rgba(127, 23, 52, 0.05);
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 23, 52, 0.1);
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
            border: 1px solid rgba(127, 23, 52, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(127, 23, 52, 0.05);
        }

        .order-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.12);
            border-color: rgba(127, 23, 52, 0.2);
        }

        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(127, 23, 52, 0.1);
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

        .status-pending {
            background: var(--bs-warning);
            color: var(--bs-dark);
        }

        .status-to-ship {
            background: var(--bs-info);
            color: white;
        }

        .status-out-for-delivery {
            background: #17a2b8;
            color: white;
        }

        .status-ready-pickup {
            background: #6f42c1;
            color: white;
        }

        .status-completed {
            background: var(--bs-success);
            color: white;
        }

        .status-cancelled {
            background: var(--bs-danger);
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
            border-bottom: 1px solid rgba(127, 23, 52, 0.05);
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
            background: rgba(127, 23, 52, 0.05);
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 0.5rem;
        }

        /* Order Actions */
        .order-actions {
            text-align: right;
            padding-top: 1rem;
            border-top: 1px solid rgba(127, 23, 52, 0.1);
        }

        .order-actions .btn {
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .order-actions .btn:hover {
            transform: translateY(-1px);
        }

        .order-actions .badge {
            font-size: 0.9rem;
            padding: 0.5rem 1rem;
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


        /* Order Tabs */
        .order-tabs {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
            border-bottom: 2px solid rgba(127, 23, 52, 0.1);
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
            border: 1px solid rgba(127, 23, 52, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            transition: all 0.3s ease;
            box-shadow: 0 2px 8px rgba(127, 23, 52, 0.05);
        }

        .address-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(127, 23, 52, 0.12);
            border-color: rgba(127, 23, 52, 0.2);
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
            border: 1px solid rgba(127, 23, 52, 0.1);
            border-radius: 0.75rem;
            padding: 2rem;
            box-shadow: 0 2px 8px rgba(127, 23, 52, 0.05);
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

        .profile-avatar-initials {
            border-radius: 50%;
            border: 4px solid var(--bs-secondary);
            margin-bottom: 2rem;
            transition: all 0.3s ease;
        }

        .profile-avatar-initials:hover {
            transform: scale(1.05);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
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
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.15);
        }

        .modal-header {
            background: var(--bs-secondary);
            color: white;
            border-radius: 1rem 1rem 0 0;
        }

        .btn-close {
            filter: invert(1);
        }

        /* Confirm Order Modal Styles */
        .confirm-icon {
            width: 80px;
            height: 80px;
            background: var(--bs-success);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
            color: white;
            font-size: 2rem;
        }

        .confirm-title {
            color: var(--bs-secondary);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .confirm-message {
            color: #6c757d;
            font-size: 1rem;
            margin-bottom: 1rem;
        }


        #confirmOrderModal .modal-content {
            border-radius: 1rem;
            border: none;
            box-shadow: 0 20px 40px rgba(0,0,0,0.15);
        }

        #confirmOrderModal .modal-header {
            background: var(--bs-success);
            color: white;
            border-radius: 1rem 1rem 0 0;
            border-bottom: none;
        }

        #confirmOrderModal .modal-body {
            padding: 2rem;
        }

        #confirmOrderModal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 1.5rem 2rem;
        }

        #confirmOrderModal .btn-success {
            background: var(--bs-success);
            border-color: var(--bs-success);
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #confirmOrderModal .btn-success:hover {
            background: #157347;
            border-color: #157347;
            transform: translateY(-1px);
        }

        #confirmOrderModal .btn-secondary {
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        #confirmOrderModal .btn-secondary:hover {
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

        /* Star Rating Styles */
        .star-rating {
            display: flex;
            flex-direction: row-reverse;
            justify-content: flex-end;
            align-items: center;
            gap: 2px;
        }

        .star-rating input[type="radio"] {
            display: none;
        }

        .star-rating label {
            font-size: 1.2rem;
            color: #ddd;
            cursor: pointer;
            transition: color 0.2s ease;
        }

        .star-rating label:hover,
        .star-rating label:hover ~ label,
        .star-rating input[type="radio"]:checked ~ label {
            color: #ffc107;
        }

        .rating-section {
            border-top: 1px solid #e9ecef;
            padding-top: 1rem;
        }

        .rating-display {
            display: flex;
            align-items: center;
            margin-bottom: 0.5rem;
        }

        .rating-stars {
            font-size: 1.1rem;
        }

        .rating-review {
            background: rgba(127, 23, 52, 0.05);
            padding: 0.75rem;
            border-radius: 0.375rem;
            border-left: 3px solid var(--bs-secondary);
        }

        .rating-form {
            background: rgba(127, 23, 52, 0.05);
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px solid rgba(127, 23, 52, 0.1);
        }

        .rating-form h6 {
            color: #495057;
            font-weight: 600;
        }

        /* LocationIQ Address Autocomplete Styles */
        .address-suggestions {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #e9ecef;
            border-top: none;
            border-radius: 0 0 0.5rem 0.5rem;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            max-height: 300px;
            overflow-y: auto;
            z-index: 1000;
            display: none;
        }

        .address-suggestion {
            padding: 0.75rem 1rem;
            cursor: pointer;
            border-bottom: 1px solid #f8f9fa;
            transition: background-color 0.2s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .address-suggestion:hover,
        .address-suggestion.active {
            background-color: rgba(127, 23, 52, 0.08);
        }

        .address-suggestion.active {
            background-color: rgba(127, 23, 52, 0.1);
            border-left: 3px solid var(--bs-secondary);
        }

        .address-suggestion:last-child {
            border-bottom: none;
        }

        .address-suggestion i {
            color: var(--bs-secondary);
            font-size: 0.9rem;
        }

        .address-suggestion .address-text {
            flex: 1;
        }

        .address-suggestion .address-main {
            font-weight: 600;
            color: var(--bs-dark);
            margin-bottom: 0.25rem;
        }

        .address-suggestion .address-details {
            font-size: 0.85rem;
            color: #6c757d;
        }

        .address-loading {
            padding: 1rem;
            text-align: center;
            color: #6c757d;
        }

        .address-loading i {
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* SweetAlert2 Custom Styles */
        .swal2-popup {
            border-radius: 1rem !important;
            font-family: 'Inter', sans-serif !important;
        }

        .swal2-title {
            font-weight: 600 !important;
        }

        .swal2-confirm {
            border: none !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            order: 1 !important; /* Left side */
        }

        .swal2-cancel {
            border: none !important;
            border-radius: 0.5rem !important;
            font-weight: 600 !important;
            order: 2 !important; /* Right side */
        }

        .swal2-success .swal2-confirm {
            background: #198754 !important; /* Green for success */
        }

        .swal2-danger .swal2-confirm {
            background: #dc3545 !important; /* Red for error */
        }

        .swal2-warning .swal2-confirm {
            background: #ffc107 !important; /* Yellow for warning */
            color: #212529 !important;
        }

        .swal2-info .swal2-confirm {
            background: #0dcaf0 !important; /* Blue for info */
        }

        .swal2-cancel {
            background: #6c757d !important; /* Gray for cancel */
        }
    </style>
</head>
<body>
<?php include 'includes/user_promo.php'; ?>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="profile-container">
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="profile-sidebar">
                    <div class="text-center">
                        <?php 
                        // Check if user has uploaded a profile picture
                        $hasProfilePicture = !empty($user['profile_picture']) && 
                                           $user['profile_picture'] !== 'uploads/default.png' && 
                                           file_exists($user['profile_picture']);
                        
                        if ($hasProfilePicture): ?>
                            <img src="<?= htmlspecialchars($user['profile_picture']) ?>"
                                 alt="Profile Picture"
                                 class="profile-avatar" />
                        <?php else: ?>
                            <!-- Generate initials-based avatar -->
                            <?php 
                            $firstName = $user['first_name'] ?? '';
                            $lastName = $user['last_name'] ?? '';
                            $initials = '';
                            
                            if (!empty($firstName) && !empty($lastName)) {
                                $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                            } elseif (!empty($firstName)) {
                                $initials = strtoupper(substr($firstName, 0, 1));
                            } elseif (!empty($lastName)) {
                                $initials = strtoupper(substr($lastName, 0, 1));
                            } else {
                                $initials = strtoupper(substr($user['username'], 0, 1));
                            }
                            
                            // Generate a consistent color based on user ID
                            $colors = ['#7F1734', '#dc3545', '#198754', '#ffc107', '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997'];
                            $colorIndex = $user['user_id'] % count($colors);
                            $avatarColor = $colors[$colorIndex];
                            ?>
                            <div class="profile-avatar profile-avatar-initials" 
                                 style="background: var(--bs-secondary); 
                                        color: white; 
                                        display: flex; 
                                        align-items: center; 
                                        justify-content: center; 
                                        font-size: 2rem; 
                                        font-weight: 700; 
                                        text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                                        width: 100px;
                                        height: 100px;
                                        margin: 0 auto;
                                        box-shadow: 0 4px 12px rgba(127, 23, 52, 0.2);">
                                <?= htmlspecialchars($initials) ?>
                            </div>
                        <?php endif; ?>
                        <div class="profile-name"><?= htmlspecialchars($user['username']); ?></div>
                    </div>

                    <ul class="nav-links">
                        <li class="nav-link-item">
                            <a href="#orders" class="nav-link-btn active">
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
                    <!-- Orders Section -->
                    <div id="orders" class="content-section active">
                        <h2 class="section-title">
                            <i class="fas fa-box"></i>
                            My Orders
                        </h2>

                        <!-- Order Tabs -->
                        <div class="order-tabs">
                            <button class="tab-btn active" data-tab="pending">
                                <i class="fas fa-clock me-2"></i>Pending (<?= count($pendingOrders) ?>)
                            </button>
                            <button class="tab-btn" data-tab="to-ship">
                                <i class="fas fa-box me-2"></i>To Ship (<?= count($toShipOrders) ?>)
                            </button>
                            <button class="tab-btn" data-tab="out-for-delivery">
                                <i class="fas fa-truck me-2"></i>Out for Delivery (<?= count($outForDeliveryOrders) ?>)
                            </button>
                            <button class="tab-btn" data-tab="ready-pickup">
                                <i class="fas fa-hand-holding me-2"></i>Ready for Pickup (<?= count($readyForPickupOrders) ?>)
                            </button>
                            <button class="tab-btn" data-tab="completed">
                                <i class="fas fa-check-circle me-2"></i>Completed (<?= count($completedOrders) ?>)
                            </button>
                            <button class="tab-btn" data-tab="cancelled">
                                <i class="fas fa-times-circle me-2"></i>Cancelled (<?= count($cancelledOrders) ?>)
                            </button>
                        </div>

                        <!-- Pending Orders Tab -->
                        <div id="pending-orders" class="tab-content active">
                            <?php if (empty($pendingOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-clock"></i>
                                    <h3>No Pending Orders</h3>
                                    <p>You don't have any pending orders.</p>
                                    <a href="product.php">Start Shopping</a>
                                </div>
                            <?php else: ?>
                                <?php foreach ($pendingOrders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-number">Order #<?= $order['id'] ?></div>
                                                <div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                            <span class="order-status status-pending">
                                                <i class="fas fa-clock me-1"></i><?= $order['status'] ?>
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

                                        <?php if (!empty($order['address']['address_line'])): ?>
                                        <div class="order-address mt-3">
                                            <div class="alert alert-light mb-2 py-2">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><strong>Delivery Address:</strong></small>
                                                <div class="mt-1">
                                                    <div><?= htmlspecialchars($order['address']['address_line']) ?></div>
                                                    <?php if (!empty($order['address']['address_line2'])): ?>
                                                        <div><?= htmlspecialchars($order['address']['address_line2']) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <?= htmlspecialchars($order['address']['city']) ?>
                                                        <?= !empty($order['address']['state']) ? ', ' . htmlspecialchars($order['address']['state']) : '' ?>
                                                        <?= !empty($order['address']['postal_code']) ? ' ' . htmlspecialchars($order['address']['postal_code']) : '' ?>
                                                    </div>
                                                    <div><?= htmlspecialchars($order['address']['country']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="order-actions mt-3">
                                            <div class="alert alert-info mb-2 py-2">
                                                <small><i class="fas fa-info-circle me-1"></i>Your order is being processed. We'll notify you when it's ready.</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- To Ship Orders Tab -->
                        <div id="to-ship-orders" class="tab-content">
                            <?php if (empty($toShipOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-box"></i>
                                    <h3>No Orders to Ship</h3>
                                    <p>You don't have any orders ready to ship.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($toShipOrders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-number">Order #<?= $order['id'] ?></div>
                                                <div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                            <span class="order-status status-to-ship">
                                                <i class="fas fa-box me-1"></i><?= $order['status'] ?>
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

                                        <?php if (!empty($order['address']['address_line'])): ?>
                                        <div class="order-address mt-3">
                                            <div class="alert alert-light mb-2 py-2">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><strong>Delivery Address:</strong></small>
                                                <div class="mt-1">
                                                    <div><?= htmlspecialchars($order['address']['address_line']) ?></div>
                                                    <?php if (!empty($order['address']['address_line2'])): ?>
                                                        <div><?= htmlspecialchars($order['address']['address_line2']) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <?= htmlspecialchars($order['address']['city']) ?>
                                                        <?= !empty($order['address']['state']) ? ', ' . htmlspecialchars($order['address']['state']) : '' ?>
                                                        <?= !empty($order['address']['postal_code']) ? ' ' . htmlspecialchars($order['address']['postal_code']) : '' ?>
                                                    </div>
                                                    <div><?= htmlspecialchars($order['address']['country']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="order-actions mt-3">
                                            <div class="alert alert-warning mb-2 py-2">
                                                <small><i class="fas fa-shipping-fast me-1"></i>Your order is being prepared for shipment.</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Out for Delivery Orders Tab -->
                        <div id="out-for-delivery-orders" class="tab-content">
                            <?php if (empty($outForDeliveryOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-truck"></i>
                                    <h3>No Orders Out for Delivery</h3>
                                    <p>You don't have any orders currently out for delivery.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($outForDeliveryOrders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-number">Order #<?= $order['id'] ?></div>
                                                <div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                            <span class="order-status status-out-for-delivery">
                                                <i class="fas fa-truck me-1"></i><?= $order['status'] ?>
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

                                        <?php if (!empty($order['address']['address_line'])): ?>
                                        <div class="order-address mt-3">
                                            <div class="alert alert-light mb-2 py-2">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><strong>Delivery Address:</strong></small>
                                                <div class="mt-1">
                                                    <div><?= htmlspecialchars($order['address']['address_line']) ?></div>
                                                    <?php if (!empty($order['address']['address_line2'])): ?>
                                                        <div><?= htmlspecialchars($order['address']['address_line2']) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <?= htmlspecialchars($order['address']['city']) ?>
                                                        <?= !empty($order['address']['state']) ? ', ' . htmlspecialchars($order['address']['state']) : '' ?>
                                                        <?= !empty($order['address']['postal_code']) ? ' ' . htmlspecialchars($order['address']['postal_code']) : '' ?>
                                                    </div>
                                                    <div><?= htmlspecialchars($order['address']['country']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="order-actions mt-3">
                                            <div class="alert alert-info mb-2 py-2">
                                                <small><i class="fas fa-info-circle me-1"></i>Click below to confirm you have received your order and complete the transaction.</small>
                                            </div>
                                            <button type="button" class="btn btn-success btn-sm confirm-order-btn" 
                                                    data-bs-target="#confirmOrderModal" 
                                                    data-order-id="<?= $order['id'] ?>" 
                                                    data-order-total="₱<?= number_format($order['total_price'], 2) ?>">
                                                <i class="fas fa-check-circle me-2"></i>Confirm Order Received
                                            </button>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Ready for Pickup Orders Tab -->
                        <div id="ready-pickup-orders" class="tab-content">
                            <?php if (empty($readyForPickupOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-hand-holding"></i>
                                    <h3>No Orders Ready for Pickup</h3>
                                    <p>You don't have any orders ready for pickup.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($readyForPickupOrders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-number">Order #<?= $order['id'] ?></div>
                                                <div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                            <span class="order-status status-ready-pickup">
                                                <i class="fas fa-hand-holding me-1"></i><?= $order['status'] ?>
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

                                        <?php if (!empty($order['address']['address_line'])): ?>
                                        <div class="order-address mt-3">
                                            <div class="alert alert-light mb-2 py-2">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><strong>Delivery Address:</strong></small>
                                                <div class="mt-1">
                                                    <div><?= htmlspecialchars($order['address']['address_line']) ?></div>
                                                    <?php if (!empty($order['address']['address_line2'])): ?>
                                                        <div><?= htmlspecialchars($order['address']['address_line2']) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <?= htmlspecialchars($order['address']['city']) ?>
                                                        <?= !empty($order['address']['state']) ? ', ' . htmlspecialchars($order['address']['state']) : '' ?>
                                                        <?= !empty($order['address']['postal_code']) ? ' ' . htmlspecialchars($order['address']['postal_code']) : '' ?>
                                                    </div>
                                                    <div><?= htmlspecialchars($order['address']['country']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="order-actions mt-3">
                                            <div class="alert alert-success mb-2 py-2">
                                                <small><i class="fas fa-check-circle me-1"></i>Your order is ready for pickup! Please visit our store to collect your items.</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Completed Orders Tab -->
                        <div id="completed-orders" class="tab-content">
                            <?php if (empty($completedOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-check-circle"></i>
                                    <h3>No Completed Orders</h3>
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
                                            <span class="order-status status-completed">
                                                <i class="fas fa-check-circle me-1"></i><?= $order['status'] ?>
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

                                        <?php if (!empty($order['address']['address_line'])): ?>
                                        <div class="order-address mt-3">
                                            <div class="alert alert-light mb-2 py-2">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><strong>Delivery Address:</strong></small>
                                                <div class="mt-1">
                                                    <div><?= htmlspecialchars($order['address']['address_line']) ?></div>
                                                    <?php if (!empty($order['address']['address_line2'])): ?>
                                                        <div><?= htmlspecialchars($order['address']['address_line2']) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <?= htmlspecialchars($order['address']['city']) ?>
                                                        <?= !empty($order['address']['state']) ? ', ' . htmlspecialchars($order['address']['state']) : '' ?>
                                                        <?= !empty($order['address']['postal_code']) ? ' ' . htmlspecialchars($order['address']['postal_code']) : '' ?>
                                                    </div>
                                                    <div><?= htmlspecialchars($order['address']['country']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>

                                        <div class="order-actions mt-3">
                                            <!-- Rating Section -->
                                            <div class="rating-section mt-3" id="rating-section-<?= $order['id'] ?>">
                                                <?php
                                                // Check if user has already rated this order
                                                $rating_stmt = $pdo->prepare("SELECT rating, review FROM order_ratings WHERE order_id = ? AND user_id = ?");
                                                $rating_stmt->execute([$order['id'], $user_id]);
                                                $existing_rating = $rating_stmt->fetch(PDO::FETCH_ASSOC);
                                                ?>
                                                
                                                <?php if ($existing_rating): ?>
                                                    <!-- Show existing rating -->
                                                    <div class="existing-rating">
                                                        <div class="rating-display">
                                                            <span class="rating-stars">
                                                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                                                    <i class="fas fa-star <?= $i <= $existing_rating['rating'] ? 'text-warning' : 'text-muted' ?>"></i>
                                                                <?php endfor; ?>
                                                            </span>
                                                            <span class="rating-text ms-2">Your Rating: <?= $existing_rating['rating'] ?>/5</span>
                                                        </div>
                                                        <?php if ($existing_rating['review']): ?>
                                                            <div class="rating-review mt-2">
                                                                <small class="text-muted">Your Review:</small>
                                                                <p class="mb-0"><?= htmlspecialchars($existing_rating['review']) ?></p>
                                                            </div>
                                                        <?php endif; ?>
                                                        <button class="btn btn-sm btn-outline-primary mt-2" onclick="editRating(<?= $order['id'] ?>)">
                                                            <i class="fas fa-edit me-1"></i>Edit Rating
                                                        </button>
                                                    </div>
                                                <?php else: ?>
                                                    <!-- Show rating form -->
                                                    <div class="rating-form">
                                                        <h6 class="mb-2">Rate this order:</h6>
                                                        <form class="rating-form-inline" onsubmit="submitRating(event, <?= $order['id'] ?>)">
                                                            <div class="rating-input mb-2">
                                                                <div class="star-rating">
                                                                    <input type="radio" name="rating-<?= $order['id'] ?>" value="5" id="star5-<?= $order['id'] ?>">
                                                                    <label for="star5-<?= $order['id'] ?>"><i class="fas fa-star"></i></label>
                                                                    <input type="radio" name="rating-<?= $order['id'] ?>" value="4" id="star4-<?= $order['id'] ?>">
                                                                    <label for="star4-<?= $order['id'] ?>"><i class="fas fa-star"></i></label>
                                                                    <input type="radio" name="rating-<?= $order['id'] ?>" value="3" id="star3-<?= $order['id'] ?>">
                                                                    <label for="star3-<?= $order['id'] ?>"><i class="fas fa-star"></i></label>
                                                                    <input type="radio" name="rating-<?= $order['id'] ?>" value="2" id="star2-<?= $order['id'] ?>">
                                                                    <label for="star2-<?= $order['id'] ?>"><i class="fas fa-star"></i></label>
                                                                    <input type="radio" name="rating-<?= $order['id'] ?>" value="1" id="star1-<?= $order['id'] ?>">
                                                                    <label for="star1-<?= $order['id'] ?>"><i class="fas fa-star"></i></label>
                                                                </div>
                                                            </div>
                                                            <div class="mb-2">
                                                                <textarea class="form-control form-control-sm" name="review-<?= $order['id'] ?>" placeholder="Write a review (optional)" rows="2" maxlength="500"></textarea>
                                                            </div>
                                                            <button type="submit" class="btn btn-sm btn-primary">
                                                                <i class="fas fa-star me-1"></i>Submit Rating
                                                            </button>
                                                        </form>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>

                        <!-- Cancelled Orders Tab -->
                        <div id="cancelled-orders" class="tab-content">
                            <?php if (empty($cancelledOrders)): ?>
                                <div class="empty-state">
                                    <i class="fas fa-times-circle"></i>
                                    <h3>No Cancelled Orders</h3>
                                    <p>You don't have any cancelled orders.</p>
                                </div>
                            <?php else: ?>
                                <?php foreach ($cancelledOrders as $order): ?>
                                    <div class="order-card">
                                        <div class="order-header">
                                            <div>
                                                <div class="order-number">Order #<?= $order['id'] ?></div>
                                                <div class="order-date"><?= date('M d, Y', strtotime($order['created_at'])) ?></div>
                                            </div>
                                            <span class="order-status status-cancelled">
                                                <i class="fas fa-times-circle me-1"></i><?= $order['status'] ?>
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

                                        <?php if (!empty($order['address']['address_line'])): ?>
                                        <div class="order-address mt-3">
                                            <div class="alert alert-light mb-2 py-2">
                                                <small><i class="fas fa-map-marker-alt me-1"></i><strong>Delivery Address:</strong></small>
                                                <div class="mt-1">
                                                    <div><?= htmlspecialchars($order['address']['address_line']) ?></div>
                                                    <?php if (!empty($order['address']['address_line2'])): ?>
                                                        <div><?= htmlspecialchars($order['address']['address_line2']) ?></div>
                                                    <?php endif; ?>
                                                    <div>
                                                        <?= htmlspecialchars($order['address']['city']) ?>
                                                        <?= !empty($order['address']['state']) ? ', ' . htmlspecialchars($order['address']['state']) : '' ?>
                                                        <?= !empty($order['address']['postal_code']) ? ' ' . htmlspecialchars($order['address']['postal_code']) : '' ?>
                                                    </div>
                                                    <div><?= htmlspecialchars($order['address']['country']) ?></div>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($order['cancel_reason'])): ?>
                                            <div class="alert alert-danger mt-3 mb-0" role="alert">
                                                <small><i class="fas fa-ban me-2"></i>Cancellation reason: <?= htmlspecialchars($order['cancel_reason']) ?></small>
                                            </div>
                                        <?php endif; ?>

                                        <div class="order-actions mt-3">
                                            <div class="alert alert-danger mb-2 py-2">
                                                <small><i class="fas fa-info-circle me-1"></i>This order has been cancelled.</small>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
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

                        <?php if (isset($_SESSION['address_required'])): ?>
                            <div class="alert alert-warning alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <?= $_SESSION['address_required'] ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                            <?php unset($_SESSION['address_required']); ?>
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
                            <!-- ID Verification Status -->
                            <?php
                            // Check ID verification status
                            $stmt = $pdo->prepare("SELECT id_verified FROM users WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $user_verification = $stmt->fetch(PDO::FETCH_ASSOC);
                            $id_verified = $user_verification && $user_verification['id_verified'];
                            
                            // Get verification details if exists
                            $stmt = $pdo->prepare("SELECT * FROM customer_id_verification WHERE user_id = ?");
                            $stmt->execute([$user_id]);
                            $verification_details = $stmt->fetch(PDO::FETCH_ASSOC);
                            ?>
                            
                            <div class="row mb-4">
                                <div class="col-12">
                                    <div class="card <?= $id_verified ? 'border-success' : 'border-warning' ?>">
                                        <div class="card-header <?= $id_verified ? 'bg-success text-white' : 'bg-warning text-dark' ?>">
                                            <h5 class="mb-0">
                                                <i class="fas fa-id-card me-2"></i>
                                                ID Verification Status
                                            </h5>
                                        </div>
                                        <div class="card-body">
                                            <?php if ($id_verified): ?>
                                                <div class="d-flex align-items-center text-success">
                                                    <i class="fas fa-check-circle me-2" style="font-size: 1.5rem;"></i>
                                                    <div>
                                                        <h6 class="mb-1">Verified</h6>
                                                        <p class="mb-0">Your ID has been verified. You can place orders.</p>
                                                        <?php if ($verification_details): ?>
                                                            <small class="text-muted">
                                                                Verified on: <?= date('M d, Y', strtotime($verification_details['verified_at'])) ?>
                                                            </small>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="d-flex align-items-center text-warning">
                                                    <i class="fas fa-exclamation-triangle me-2" style="font-size: 1.5rem;"></i>
                                                    <div class="flex-grow-1">
                                                        <h6 class="mb-1">Verification Required</h6>
                                                        <p class="mb-2">You need to verify your ID before placing orders.</p>
                                                        <?php if ($verification_details): ?>
                                                            <div class="mb-2">
                                                                <strong>Status:</strong> 
                                                                <span class="badge <?= $verification_details['status'] === 'pending' ? 'bg-warning' : ($verification_details['status'] === 'approved' ? 'bg-success' : 'bg-danger') ?>">
                                                                    <?= ucfirst($verification_details['status']) ?>
                                                                </span>
                                                            </div>
                                                            <?php if ($verification_details['status'] === 'rejected' && $verification_details['rejection_reason']): ?>
                                                                <div class="alert alert-danger mb-2">
                                                                    <strong>Rejection Reason:</strong><br>
                                                                    <?= htmlspecialchars($verification_details['rejection_reason']) ?>
                                                                </div>
                                                            <?php endif; ?>
                                                        <?php endif; ?>
                                                    </div>
                                                    <div>
                                                        <a href="id_verification.php" class="btn <?= $verification_details ? 'btn-outline-primary' : 'btn-primary' ?>">
                                                            <i class="fas fa-upload me-2"></i>
                                                            <?= $verification_details ? 'Update ID' : 'Upload ID' ?>
                                                        </a>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4">
                                    <div class="profile-picture-section">
                                        <h4>Profile Picture</h4>
                                        <div class="profile-picture-upload">
                                            <?php 
                                            // Check if user has uploaded a profile picture
                                            $hasProfilePicture = !empty($user['profile_picture']) && 
                                                               $user['profile_picture'] !== 'uploads/default.png' && 
                                                               file_exists($user['profile_picture']);
                                            
                                            if ($hasProfilePicture): ?>
                                                <img src="<?= htmlspecialchars($user['profile_picture']) ?>" alt="Profile Picture" class="profile-avatar-large" id="profilePreview">
                                            <?php else: ?>
                                                <!-- Generate initials-based avatar -->
                                                <?php 
                                                $firstName = $user['first_name'] ?? '';
                                                $lastName = $user['last_name'] ?? '';
                                                $initials = '';
                                                
                                                if (!empty($firstName) && !empty($lastName)) {
                                                    $initials = strtoupper(substr($firstName, 0, 1) . substr($lastName, 0, 1));
                                                } elseif (!empty($firstName)) {
                                                    $initials = strtoupper(substr($firstName, 0, 1));
                                                } elseif (!empty($lastName)) {
                                                    $initials = strtoupper(substr($lastName, 0, 1));
                                                } else {
                                                    $initials = strtoupper(substr($user['username'], 0, 1));
                                                }
                                                
                                                // Generate a consistent color based on user ID
                                                $colors = ['#7F1734', '#dc3545', '#198754', '#ffc107', '#0dcaf0', '#6f42c1', '#fd7e14', '#20c997'];
                                                $colorIndex = $user['user_id'] % count($colors);
                                                $avatarColor = $colors[$colorIndex];
                                                ?>
                                                <div class="profile-avatar-large profile-avatar-initials" 
                                                     id="profilePreview"
                                                     style="background: var(--bs-secondary); 
                                                            color: white; 
                                                            display: flex; 
                                                            align-items: center; 
                                                            justify-content: center; 
                                                            font-size: 2rem; 
                                                            font-weight: 700; 
                                                            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
                                                            width: 100px;
                                                            height: 100px;
                                                            margin: auto;
                                                            margin-bottom: 2rem;
                                                            box-shadow: 0 4px 12px rgba(127, 23, 52, 0.2);">
                                                    <?= htmlspecialchars($initials) ?>
                                                </div>
                                            <?php endif; ?>
                                            <div class="upload-actions">
                                                <button class="btn btn-outline-primary" onclick="document.getElementById('profileImage').click()">
                                                    <i class="fas fa-camera me-2"></i>
                                                    <?= $hasProfilePicture ? 'Change Photo' : 'Upload Photo' ?>
                                                </button>
                                                <?php if (!$hasProfilePicture): ?>
                                                    <small class="text-muted mt-2">
                                                        <i class="fas fa-info-circle me-1"></i>
                                                        Upload a photo to personalize your profile
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-8">
                                    <form id="profileForm" method="POST" enctype="multipart/form-data">
                                        <input type="hidden" name="update_profile" value="1">

                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">First Name</label>
                                                    <input type="text" name="first_name" value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" class="form-control" required>
                                                    <div data-error="first_name"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Last Name</label>
                                                    <input type="text" name="last_name" value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="form-control" required>
                                                    <div data-error="last_name"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Email</label>
                                                    <input type="email" name="email" required value="<?= htmlspecialchars($user['email'] ?? '') ?>" class="form-control">
                                                    <div data-error="email"></div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="form-group">
                                                    <label class="form-label">Phone Number</label>
                                                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-control">
                                                    <div data-error="phone"></div>
                                                </div>
                                            </div>
                                            <div class="col-12">
                                                <div class="form-group">
                                                    <label class="form-label">Profile Picture</label>
                                                    <input type="file" name="profile_picture" id="profileImage" accept="image/*" class="form-control">
                                                    <small class="form-text text-muted">Supported formats: JPG, PNG, GIF, WebP. Max size: 5MB</small>
                                                    <div data-error="profile_picture"></div>
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
                                <div class="position-relative">
                                    <input type="text" name="address_line" id="add_address_line" class="form-control" placeholder="Start typing your address..." autocomplete="off" required>
                                    <div id="add-address-suggestions" class="address-suggestions"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Landmark</label>
                                <input type="text" name="address_line2" class="form-control" placeholder="Near landmark, building, or reference point">
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
                                <div class="position-relative">
                                    <input type="text" name="address_line" id="editAddressLine" class="form-control" placeholder="Start typing your address..." autocomplete="off" required>
                                    <div id="edit-address-suggestions" class="address-suggestions"></div>
                                </div>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Landmark</label>
                                <input type="text" name="address_line2" id="editAddressLine2" class="form-control" placeholder="Near landmark, building, or reference point">
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

    <!-- Confirm Order Received Modal -->
    <div class="modal fade" id="confirmOrderModal" tabindex="-1" aria-labelledby="confirmOrderModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="confirmOrderModalLabel">
                        <i class="fas fa-check-circle me-2"></i>Confirm Order Received
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center">
                    <div class="confirm-icon">
                        <i class="fas fa-handshake"></i>
                    </div>
                    <h4 class="confirm-title">Have you received your order?</h4>
                    <p class="confirm-message">Please confirm that you have received your order in good condition.</p>
                    <div class="alert alert-info">
                        <strong>Order Details:</strong><br>
                        <span id="modalOrderId"></span><br>
                        <span id="modalOrderTotal"></span>
                    </div>
                    <p class="text-muted small">This action will complete your order and cannot be undone.</p>
                </div>
                <div class="modal-footer justify-content-center">
                    <button type="button" class="btn btn-secondary me-3" data-bs-dismiss="modal">
                        <i class="fas fa-times me-2"></i>Cancel
                    </button>
                    <form method="POST" action="order_received.php" class="d-inline" id="confirmOrderForm">
                        <input type="hidden" name="order_id" id="confirmOrderIdInput">
                        <button type="submit" class="btn btn-success">
                            <i class="fas fa-check-circle me-2"></i>Yes, I Received It
                        </button>
                    </form>
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
                    const preview = document.getElementById('profilePreview');
                    
                    // Check if current preview is an image or initials div
                    if (preview.tagName === 'IMG') {
                        preview.src = e.target.result;
                    } else {
                        // Replace initials div with image
                        const newImg = document.createElement('img');
                        newImg.src = e.target.result;
                        newImg.alt = 'Profile Picture';
                        newImg.className = 'profile-avatar-large';
                        newImg.id = 'profilePreview';
                        preview.parentNode.replaceChild(newImg, preview);
                    }
                };
                reader.readAsDataURL(file);
            }
        });

        // Confirm Order Received with SweetAlert
        document.addEventListener('DOMContentLoaded', function() {
            // Handle order confirmation buttons
            document.querySelectorAll('.confirm-order-btn').forEach(button => {
                button.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    const orderId = this.getAttribute('data-order-id');
                    const orderTotal = this.getAttribute('data-order-total');
                    
                    // Show SweetAlert confirmation
                    Swal.fire({
                        title: 'Confirm Order Received',
                        html: `
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="fas fa-handshake" style="font-size: 3rem; color: #198754;"></i>
                                </div>
                                <h5 class="mb-3">Have you received your order?</h5>
                                <p class="text-muted small">This action will complete your order and cannot be undone.</p>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonColor: '#198754',
                        cancelButtonColor: '#6c757d',
                        confirmButtonText: '<i class="fas fa-check-circle me-2"></i>Yes, I Received It',
                        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                        customClass: {
                            popup: 'swal2-popup',
                            confirmButton: 'swal2-confirm',
                            cancelButton: 'swal2-cancel'
                        },
                        buttonsStyling: true,
                        reverseButtons: true
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Show loading state
                            Swal.fire({
                                title: 'Processing...',
                                text: 'Please wait while we update your order status.',
                                allowOutsideClick: false,
                                showConfirmButton: false,
                                didOpen: () => {
                                    Swal.showLoading();
                                },
                                customClass: {
                                    popup: 'swal2-info'
                                }
                            });
                            
                            // Submit the form
                            const form = document.getElementById('confirmOrderForm');
                            const orderIdInput = document.getElementById('confirmOrderIdInput');
                            orderIdInput.value = orderId;
                            
                            // Create and submit form
                            const submitForm = document.createElement('form');
                            submitForm.method = 'POST';
                            submitForm.action = 'order_received.php';
                            submitForm.style.display = 'none';
                            
                            const orderIdField = document.createElement('input');
                            orderIdField.type = 'hidden';
                            orderIdField.name = 'order_id';
                            orderIdField.value = orderId;
                            
                            submitForm.appendChild(orderIdField);
                            document.body.appendChild(submitForm);
                            submitForm.submit();
                        }
                    });
                });
            });
        });

        // Logout confirmation with SweetAlert2
        function confirmLogout() {
            Swal.fire({
                title: 'Confirm Logout',
                text: 'Are you sure you want to logout? You will need to sign in again to access your account.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#7F1734',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-sign-out-alt me-1"></i>Yes, Logout',
                cancelButtonText: '<i class="fas fa-times me-1"></i>Cancel',
                customClass: {
                    popup: 'swal2-success',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading state
                    Swal.fire({
                        title: 'Logging Out...',
                        text: 'Please wait while we log you out.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        customClass: {
                            popup: 'swal2-info'
                        }
                    });
                    
                    // Redirect to logout page
                    setTimeout(() => {
                        window.location.href = 'logout.php';
                    }, 1000);
                }
            });
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
            
            // Auto-open address modal if redirected from checkout
            <?php if (isset($_SESSION['show_address_modal'])): ?>
                // Show address modal after a short delay to ensure page is loaded
                setTimeout(function() {
                    const addAddressModal = new bootstrap.Modal(document.getElementById('addAddressModal'));
                    addAddressModal.show();
                }, 1000);
                <?php unset($_SESSION['show_address_modal']); ?>
            <?php endif; ?>
            
            // Show success SweetAlert if order was confirmed
            <?php if (isset($_SESSION['order_confirmed'])): ?>
                setTimeout(function() {
                    Swal.fire({
                        title: 'Order Confirmed!',
                        html: `
                            <div class="text-center">
                                <div class="mb-3">
                                    <i class="fas fa-check-circle" style="font-size: 4rem; color: #198754;"></i>
                                </div>
                                <div class="alert alert-success">
                                    <i class="fas fa-star me-2"></i>
                                    You can now rate your order experience below.
                                </div>
                            </div>
                        `,
                        icon: 'success',
                        confirmButtonColor: '#198754',
                        confirmButtonText: '<i class="fas fa-thumbs-up me-2"></i>Great!',
                        customClass: {
                            popup: 'swal2-popup',
                            confirmButton: 'swal2-confirm'
                        },
                        buttonsStyling: true
                    });
                }, 500);
                <?php unset($_SESSION['order_confirmed']); ?>
            <?php endif; ?>
        });

        // Rating functionality
        function submitRating(event, orderId) {
            event.preventDefault();
            
            const form = event.target;
            const ratingInput = form.querySelector(`input[name="rating-${orderId}"]:checked`);
            const reviewTextarea = form.querySelector(`textarea[name="review-${orderId}"]`);
            
            if (!ratingInput) {
                Swal.fire({
                    title: 'Rating Required',
                    text: 'Please select a star rating before submitting.',
                    icon: 'warning',
                    confirmButtonColor: '#7F1734',
                    confirmButtonText: '<i class="fas fa-star me-2"></i>Got it!',
                    customClass: {
                        popup: 'swal2-popup',
                        confirmButton: 'swal2-confirm'
                    },
                    buttonsStyling: true
                });
                return;
            }
            
            const rating = ratingInput.value;
            const review = reviewTextarea ? reviewTextarea.value.trim() : '';
            
            // Show confirmation SweetAlert
            Swal.fire({
                title: 'Submit Rating?',
                html: `
                    <div class="text-center">
                        <div class="mb-3">
                            <div class="d-flex justify-content-center">
                                ${Array.from({length: 5}, (_, i) => 
                                    `<i class="fas fa-star ${i < rating ? 'text-warning' : 'text-muted'} me-1" style="font-size: 1.5rem;"></i>`
                                ).join('')}
                            </div>
                            <p class="mt-2 mb-0"><strong>${rating} Star${rating > 1 ? 's' : ''}</strong></p>
                        </div>
                        ${review ? `<div class="alert alert-light text-start"><strong>Review:</strong><br>${review}</div>` : ''}
                        <p class="text-muted small">This rating will be submitted for Order #${orderId}</p>
                    </div>
                `,
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#198754',
                cancelButtonColor: '#6c757d',
                confirmButtonText: '<i class="fas fa-check me-2"></i>Submit Rating',
                cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
                customClass: {
                    popup: 'swal2-popup',
                    confirmButton: 'swal2-confirm',
                    cancelButton: 'swal2-cancel'
                },
                buttonsStyling: true,
                reverseButtons: true
            }).then((result) => {
                if (result.isConfirmed) {
                    // Show loading SweetAlert
                    Swal.fire({
                        title: 'Submitting Rating...',
                        text: 'Please wait while we process your rating.',
                        allowOutsideClick: false,
                        showConfirmButton: false,
                        didOpen: () => {
                            Swal.showLoading();
                        },
                        customClass: {
                            popup: 'swal2-info'
                        }
                    });
                    
                    // Submit rating
                    const formData = new FormData();
                    formData.append('order_id', orderId);
                    formData.append('rating', rating);
                    formData.append('review', review);
                    
                    fetch('submit_rating.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Show success SweetAlert
                            Swal.fire({
                                title: 'Rating Submitted!',
                                html: `
                                    <div class="text-center">
                                        <div class="mb-3">
                                            <i class="fas fa-check-circle" style="font-size: 4rem; color: #198754;"></i>
                                        </div>
                                        <p class="mb-2">${data.message}</p>
                                        <div class="alert alert-success">
                                            <i class="fas fa-star me-2"></i>
                                            Thank you for your feedback! Your rating helps us improve our service.
                                        </div>
                                    </div>
                                `,
                                icon: 'success',
                                confirmButtonColor: '#198754',
                                confirmButtonText: '<i class="fas fa-thumbs-up me-2"></i>Awesome!',
                                customClass: {
                                    popup: 'swal2-popup',
                                    confirmButton: 'swal2-confirm'
                                },
                                buttonsStyling: true
                            }).then(() => {
                                // Reload the page to show the updated rating
                                window.location.reload();
                            });
                        } else {
                            // Show error SweetAlert
                            Swal.fire({
                                title: 'Submission Failed',
                                text: data.message,
                                icon: 'error',
                                confirmButtonColor: '#dc3545',
                                confirmButtonText: '<i class="fas fa-redo me-2"></i>Try Again',
                                customClass: {
                                    popup: 'swal2-popup',
                                    confirmButton: 'swal2-confirm'
                                },
                                buttonsStyling: true
                            });
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        // Show network error SweetAlert
                        Swal.fire({
                            title: 'Network Error',
                            text: 'Unable to submit rating. Please check your connection and try again.',
                            icon: 'error',
                            confirmButtonColor: '#dc3545',
                            confirmButtonText: '<i class="fas fa-wifi me-2"></i>Retry',
                            customClass: {
                                popup: 'swal2-popup',
                                confirmButton: 'swal2-confirm'
                            },
                            buttonsStyling: true
                        });
                    });
                }
            });
        }
        
        function editRating(orderId) {
            // Hide existing rating and show form
            const ratingSection = document.getElementById(`rating-section-${orderId}`);
            const existingRating = ratingSection.querySelector('.existing-rating');
            const ratingForm = ratingSection.querySelector('.rating-form');
            
            if (existingRating && ratingForm) {
                existingRating.style.display = 'none';
                ratingForm.style.display = 'block';
            }
        }
        
        function showToast(message, type) {
            const toast = document.createElement('div');
            toast.className = 'toast show position-fixed top-0 end-0 m-3';
            toast.style.zIndex = '9999';
            
            const bgClass = type === 'success' ? 'bg-success' : 'bg-danger';
            const icon = type === 'success' ? 'fas fa-check-circle' : 'fas fa-exclamation-circle';
            
            toast.innerHTML = `
                <div class="toast-header ${bgClass} text-white">
                    <i class="${icon} me-2"></i>
                    <strong class="me-auto">${type === 'success' ? 'Success' : 'Error'}</strong>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="toast"></button>
                </div>
                <div class="toast-body">
                    ${message}
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // LocationIQ Address Autocomplete
        const LOCATIONIQ_API_KEY = 'pk.00c9590567d539faf9a471a17f1c5bf3';
        const LOCATIONIQ_BASE_URL = 'https://us1.locationiq.com/v1';

        function initAddressAutocomplete(inputId, suggestionsId) {
            const input = document.getElementById(inputId);
            const suggestions = document.getElementById(suggestionsId);
            let currentSuggestions = [];
            let selectedIndex = -1;
            let debounceTimer;

            if (!input || !suggestions) return;

            input.addEventListener('input', function() {
                const query = this.value.trim();
                
                clearTimeout(debounceTimer);
                
                if (query.length < 3) {
                    hideSuggestions();
                    return;
                }

                debounceTimer = setTimeout(() => {
                    searchAddresses(query);
                }, 300);
            });

            input.addEventListener('keydown', function(e) {
                if (!suggestions.style.display || suggestions.style.display === 'none') return;

                switch(e.key) {
                    case 'ArrowDown':
                        e.preventDefault();
                        selectedIndex = Math.min(selectedIndex + 1, currentSuggestions.length - 1);
                        updateSelection();
                        break;
                    case 'ArrowUp':
                        e.preventDefault();
                        selectedIndex = Math.max(selectedIndex - 1, -1);
                        updateSelection();
                        break;
                    case 'Enter':
                        e.preventDefault();
                        if (selectedIndex >= 0 && currentSuggestions[selectedIndex]) {
                            selectAddress(currentSuggestions[selectedIndex]);
                        }
                        break;
                    case 'Escape':
                        hideSuggestions();
                        break;
                }
            });

            input.addEventListener('blur', function() {
                setTimeout(() => hideSuggestions(), 200);
            });

            function searchAddresses(query) {
                showLoading();
                
                fetch(`${LOCATIONIQ_BASE_URL}/autocomplete?key=${LOCATIONIQ_API_KEY}&q=${encodeURIComponent(query)}&countrycodes=ph&limit=5&addressdetails=1`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && Array.isArray(data)) {
                            currentSuggestions = data;
                            displaySuggestions(data);
                        } else {
                            hideSuggestions();
                        }
                    })
                    .catch(error => {
                        console.error('Address search error:', error);
                        hideSuggestions();
                    });
            }

            function displaySuggestions(addresses) {
                if (addresses.length === 0) {
                    hideSuggestions();
                    return;
                }

                suggestions.innerHTML = addresses.map((address, index) => {
                    const displayName = address.display_name || '';
                    const parts = displayName.split(', ');
                    const mainAddress = parts[0] || '';
                    const details = parts.slice(1, 3).join(', ') || '';
                    
                    return `
                        <div class="address-suggestion" data-index="${index}">
                            <i class="fas fa-map-marker-alt"></i>
                            <div class="address-text">
                                <div class="address-main">${mainAddress}</div>
                                <div class="address-details">${details}</div>
                            </div>
                        </div>
                    `;
                }).join('');

                // Add click event listeners
                suggestions.querySelectorAll('.address-suggestion').forEach((item, index) => {
                    item.addEventListener('click', () => selectAddress(addresses[index]));
                });

                suggestions.style.display = 'block';
                selectedIndex = -1;
            }

            function showLoading() {
                suggestions.innerHTML = `
                    <div class="address-loading">
                        <i class="fas fa-spinner"></i>
                        <span>Searching addresses...</span>
                    </div>
                `;
                suggestions.style.display = 'block';
            }

            function hideSuggestions() {
                suggestions.style.display = 'none';
                currentSuggestions = [];
                selectedIndex = -1;
            }

            function updateSelection() {
                const items = suggestions.querySelectorAll('.address-suggestion');
                items.forEach((item, index) => {
                    item.classList.toggle('active', index === selectedIndex);
                });
            }

            function selectAddress(address) {
                const displayName = address.display_name || '';
                
                // Fill the address input with the full display name
                input.value = displayName;
                
                // Try to auto-fill other fields using the structured address data
                const cityInput = input.closest('form').querySelector('input[name="city"]');
                const stateInput = input.closest('form').querySelector('input[name="state"]');
                const postalInput = input.closest('form').querySelector('input[name="postal_code"]');
                
                // Use structured address data if available
                if (address.address) {
                    // Try different city fields in order of preference
                    if (cityInput) {
                        if (address.address.city) {
                            cityInput.value = address.address.city;
                        } else if (address.address.town) {
                            cityInput.value = address.address.town;
                        } else if (address.address.village) {
                            cityInput.value = address.address.village;
                        } else if (address.address.municipality) {
                            cityInput.value = address.address.municipality;
                        } else if (address.address.county) {
                            cityInput.value = address.address.county;
                        } else {
                            // Fallback: parse from display_name
                            const parts = displayName.split(', ');
                            for (let i = 1; i < parts.length; i++) {
                                const part = parts[i].trim();
                                // Skip common non-city terms
                                if (!part.match(/^(Philippines|Metro Manila|NCR|Region|Province|Quezon City|Manila|Makati|Taguig|Pasig|Mandaluyong|San Juan|Marikina|Parañaque|Las Piñas|Muntinlupa|Caloocan|Malabon|Navotas|Valenzuela|Pateros)$/i)) {
                                    cityInput.value = part;
                                    break;
                                }
                            }
                        }
                    }
                    
                    // Try different state/province fields
                    if (stateInput) {
                        if (address.address.state) {
                            stateInput.value = address.address.state;
                        } else if (address.address.province) {
                            stateInput.value = address.address.province;
                        } else if (address.address.region) {
                            stateInput.value = address.address.region;
                        }
                    }
                    
                    if (postalInput && address.address.postcode) {
                        postalInput.value = address.address.postcode;
                    }
                } else {
                    // Fallback to parsing display name
                    const parts = displayName.split(', ');
                    if (cityInput && parts.length > 1) {
                        // Try to find city from the parts
                        for (let i = 1; i < parts.length; i++) {
                            const part = parts[i].trim();
                            // Skip common non-city terms
                            if (!part.match(/^(Philippines|Metro Manila|NCR|Region|Province|Quezon City|Manila|Makati|Taguig|Pasig|Mandaluyong|San Juan|Marikina|Parañaque|Las Piñas|Muntinlupa|Caloocan|Malabon|Navotas|Valenzuela|Pateros)$/i)) {
                                cityInput.value = part;
                                break;
                            }
                        }
                    }
                }
                
                hideSuggestions();
            }
        }

        // Initialize address autocomplete when modals are shown
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize for add address modal
            const addAddressModal = document.getElementById('addAddressModal');
            if (addAddressModal) {
                addAddressModal.addEventListener('shown.bs.modal', function() {
                    initAddressAutocomplete('add_address_line', 'add-address-suggestions');
                });
            }

            // Initialize for edit address modal
            const editAddressModal = document.getElementById('editAddressModal');
            if (editAddressModal) {
                editAddressModal.addEventListener('shown.bs.modal', function() {
                    initAddressAutocomplete('editAddressLine', 'edit-address-suggestions');
                });
            }
        });
    </script>
    
    <!-- Validation Script -->
    <script src="includes/validation.js"></script>
</body>
</html>
