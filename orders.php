
<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

include 'includes/db.php';

// Fetch user information
$user_id = $_SESSION['user_id'];
$sql = "SELECT * FROM users WHERE id = :user_id";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);


if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $profileImage = $user['profile_image']; // Default to current image

    // Check if new image is uploaded
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }

        $newImageName = uniqid() . '_' . basename($_FILES['profile_image']['name']);
        $targetPath = $uploadDir . $newImageName;

        if (move_uploaded_file($_FILES['profile_image']['tmp_name'], $targetPath)) {
            $profileImage = $newImageName;
        }
    }

    $sql = "UPDATE users SET
        first_name = :first_name,
        last_name = :last_name,
        email = :email,
        phone = :phone,
        street = :street,
        barangay = :barangay,
        city = :city,
        postal_code = :postal_code,
        profile_image = :profile_image
        WHERE id = :user_id";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':first_name' => $_POST['first_name'],
        ':last_name' => $_POST['last_name'],
        ':email' => $_POST['email'],
        ':phone' => $_POST['phone'],
        ':street' => $_POST['street'],
        ':barangay' => $_POST['barangay'],
        ':city' => $_POST['city'],
        ':postal_code' => $_POST['postal_code'],
        ':profile_image' => $profileImage,
        ':user_id' => $user_id
    ]);

    header('Location: orders.php?profile_updated=1');
    exit;
}

// Fetch current orders (not delivered yet) and group by order ID
$sql = "SELECT orders.*, order_items.quantity, products.name as product_name, products.price 
        FROM orders 
        LEFT JOIN order_items ON orders.id = order_items.order_id 
        LEFT JOIN products ON order_items.product_id = products.id 
        WHERE orders.user_id = :user_id AND orders.status != 'Delivered'
        ORDER BY orders.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$rawCurrentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

$currentOrders = [];
foreach ($rawCurrentOrders as $row) {
    $orderId = $row['id'];
    if (!isset($currentOrders[$orderId])) {
        $currentOrders[$orderId] = [
            'id' => $row['id'],
            'created_at' => $row['created_at'],
            'status' => $row['status'],
            'total_price' => $row['total_price'],
            'items' => []
        ];
    }

    if ($row['product_name']) {
        $currentOrders[$orderId]['items'][] = [
            'product_name' => $row['product_name'],
            'quantity' => $row['quantity'],
            'price' => $row['price']
        ];
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
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
        }
    </style>
</head>
<body>
    <!-- Promo Banner -->
    <div class="promo-banner text-center">
        <div class="container">
            <div class="d-flex flex-wrap justify-content-center align-items-center gap-3">
                <span>₱1,000 OFF on orders ₱10,000+</span>
                <span class="d-none d-md-inline">|</span>
                <span>Free Nationwide Delivery on ₱7,000+</span>
                <span class="d-none d-md-inline">|</span>
                <span>Sign up & get 10% OFF your first order</span>
            </div>
        </div>
    </div>

    <?php include 'includes/user_navbar.php'; ?>

    <div class="profile-container">
        <div class="row g-4">
            <!-- Sidebar -->
            <div class="col-lg-3">
                <div class="profile-sidebar">
                    <div class="text-center">
                        <img src="<?= !empty($user['profile_image']) ? 'uploads/' . htmlspecialchars($user['profile_image']) : 'uploads/default.png'; ?>"
                             alt="Profile Picture"
                             class="profile-avatar" />
                        <div class="profile-name"><?= htmlspecialchars($user['username']); ?></div>
                    </div>

                    <ul class="nav-links">
                        <li class="nav-link-item">
                            <a href="#profile" class="nav-link-btn active">
                                <i class="fas fa-user"></i>
                                Profile
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="#orders" class="nav-link-btn">
                                <i class="fas fa-box"></i>
                                Current Orders
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="#history" class="nav-link-btn">
                                <i class="fas fa-history"></i>
                                Order History
                            </a>
                        </li>
                        <li class="nav-link-item">
                            <a href="logout.php" class="nav-link-btn">
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
                    <!-- Profile Section -->
                    <div id="profile" class="content-section active">
                        <h2 class="section-title">
                            <i class="fas fa-user"></i>
                            Profile Information
                        </h2>

                        <?php if (isset($_GET['profile_updated'])): ?>
                            <div class="success-alert">
                                <i class="fas fa-check-circle"></i>
                                Profile updated successfully!
                            </div>
                        <?php endif; ?>

                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="update_profile" value="1">

                            <!-- Profile Image Upload -->
                            <div class="profile-image-upload">
                                <img src="<?= !empty($user['profile_image']) ? 'uploads/' . htmlspecialchars($user['profile_image']) : 'uploads/default.png'; ?>"
                                     alt="Profile Picture"
                                     class="upload-preview" />
                                <div>
                                    <label class="form-label">Upload Profile Picture</label>
                                    <input type="file" name="profile_image" accept="image/*" class="form-control" />
                                </div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">First Name</label>
                                        <input type="text" name="first_name" required value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Last Name</label>
                                        <input type="text" name="last_name" required value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" class="form-control">
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
                                        <input type="tel" name="phone" required value="<?= htmlspecialchars($user['phone'] ?? '') ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Street Name</label>
                                        <input type="text" name="street" required value="<?= htmlspecialchars($user['street'] ?? '') ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Barangay</label>
                                        <input type="text" name="barangay" required value="<?= htmlspecialchars($user['barangay'] ?? '') ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">City</label>
                                        <input type="text" name="city" required value="<?= htmlspecialchars($user['city'] ?? '') ?>" class="form-control">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-group">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" name="postal_code" required value="<?= htmlspecialchars($user['postal_code'] ?? '') ?>" class="form-control">
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

                    <!-- Current Orders Section -->
                    <div id="orders" class="content-section">
                        <h2 class="section-title">
                            <i class="fas fa-box"></i>
                            Current Orders
                        </h2>

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
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>

                    <!-- Order History Section -->
                    <div id="history" class="content-section">
                        <h2 class="section-title">
                            <i class="fas fa-history"></i>
                            Order History
                        </h2>

                        <div class="empty-state">
                            <i class="fas fa-clock"></i>
                            <h3>No Order History</h3>
                            <p>You don't have any completed orders yet.</p>
                            <a href="product.php">Start Shopping</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/user_footer.php'; ?>

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
    </script>
</body>
</html>
