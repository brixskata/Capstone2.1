<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = htmlspecialchars(trim($_POST['username']));
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    // Validate fields
    if (empty($username) || empty($first_name) || empty($last_name) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
    } else if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } else if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
    } else if (strlen($first_name) < 2) {
        $_SESSION['error'] = "First name must be at least 2 characters long.";
    } else if (strlen($last_name) < 2) {
        $_SESSION['error'] = "Last name must be at least 2 characters long.";
    } else {
        // Check if username already exists
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $usernameExists = $stmt->fetch();
        
        // Check if email already exists in VERIFIED users only
        $stmt = $pdo->prepare("
            SELECT 1 FROM users u 
            INNER JOIN user_info ui ON u.user_id = ui.user_id 
            WHERE ui.email = :email AND u.email_verified = 1
        ");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $verifiedEmailExists = $stmt->fetch();
        
        if ($usernameExists || $verifiedEmailExists) {
            $_SESSION['error'] = "Username or verified email already exists.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Start transaction to ensure both inserts succeed
            $pdo->beginTransaction();
            try {
                // Insert user into users table with customer usertype_id (2)
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email_verified, is_active, usertype_id) VALUES (:username, :password, 0, 1, 2)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->execute();
                
                $newUserId = $pdo->lastInsertId();
                
                // Insert user info into user_info table (with email, first_name, last_name)
                $stmt = $pdo->prepare("INSERT INTO user_info (user_id, first_name, last_name, email) VALUES (:user_id, :first_name, :last_name, :email)");
                $stmt->bindParam(':user_id', $newUserId);
                $stmt->bindParam(':first_name', $first_name);
                $stmt->bindParam(':last_name', $last_name);
                $stmt->bindParam(':email', $email);
                $stmt->execute();
                
                // Commit transaction
                $pdo->commit();
                
                $_SESSION['success'] = "Registration successful! Verify your email to complete the process.";
                header("Location: email_verification.php?email=" . urlencode($email));
                exit;
            } catch (PDOException $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                
                // Check if it's a duplicate email error
                if ($e->getCode() == 23000 && strpos($e->getMessage(), 'email') !== false) {
                    $_SESSION['error'] = "This email is already registered but not verified. Please use a different email or contact support.";
                } else {
                    $_SESSION['error'] = "Registration failed. Please try again.";
                }
            } catch (Exception $e) {
                // Rollback transaction on error
                $pdo->rollBack();
                $_SESSION['error'] = "Registration failed. Please try again.";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - MikeMadz</title>
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        /* Cache buster: <?= time() ?> */
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

        body {
            font-family: 'Inter', sans-serif;
            background: #ffffff;
            min-height: 100vh;
            margin: 0;
            padding: 0;
        }

        .auth-container {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            position: relative;
        }

        .auth-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px rgba(0, 0, 0, 0.3);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .auth-header {
            text-align: center;
            padding: 3rem 3rem 2rem;
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
        }

        .logo-container {
            margin-bottom: 1.5rem;
        }

        .logo {
            width: 80px;
            height: 80px;
            margin: 0 auto 1rem;
            display: block;
            filter: brightness(0) invert(1);
        }

        .auth-header .brand-name {
            font-size: 2rem;
            font-weight: 700;
            margin: 0;
            letter-spacing: -0.5px;
            color: white !important;
        }

        .auth-header h1.brand-name {
            color: white !important;
        }

        .logo-container .brand-name {
            color: white !important;
        }

        .auth-header .logo-container h1.brand-name {
            color: white !important;
        }

        .brand-name {
            color: white !important;
        }

        * .brand-name {
            color: white !important;
        }

        h1.brand-name {
            color: white !important;
        }

        /* Ultra-specific overrides */
        .auth-card .auth-header .logo-container h1.brand-name {
            color: white !important;
        }

        .auth-card .auth-header h1.brand-name {
            color: white !important;
        }

        .auth-card .auth-header .brand-name {
            color: white !important;
        }

        /* Force white with multiple selectors */
        .auth-header h1[class*="brand"] {
            color: white !important;
        }

        .auth-header [class*="brand-name"] {
            color: white !important;
        }

        .auth-header .brand-tagline {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 0.5rem;
            font-weight: 400;
            color: white !important;
        }

        /* Make the first brand-tagline (MikeMadz) bigger */
        .logo-container .brand-tagline:first-of-type {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        /* Fix Bootstrap conflicts */
        .card-title,
        .form-subtitle {
            color: white !important;
        }

        .auth-form {
            padding: 3rem;
        }

        .form-title {
            color: var(--bs-dark);
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: #6c757d;
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .password-input-container {
            position: relative;
        }

        .form-group .row {
            margin-bottom: 0;
        }

        .form-group .row .form-group {
            margin-bottom: 1.5rem;
        }

        .form-control {
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: rgba(248, 249, 250, 0.8);
            backdrop-filter: blur(10px);
            font-weight: 400;
        }

        .form-control:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 3px rgba(127, 23, 52, 0.1);
            background-color: white;
            outline: none;
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1rem;
            z-index: 2;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .btn-register {
            background: var(--bs-secondary);
            border: none;
            padding: 1rem 2rem;
            border-radius: 16px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1rem;
            color: white;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(127, 23, 52, 0.3);
            background: #6b1429;
            color: white;
        }

        .btn-login {
            background: transparent;
            border: 2px solid #e9ecef;
            color: #6c757d;
            padding: 1rem 2rem;
            border-radius: 16px;
            font-weight: 500;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-login:hover {
            background: #f8f9fa;
            color: var(--bs-secondary);
            border-color: var(--bs-secondary);
            transform: translateY(-1px);
        }

        .alert {
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 1.5rem;
            border: none;
        }

        .alert-danger {
            background: linear-gradient(135deg, #f8d7da 0%, #f5c2c7 100%);
            color: #842029;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1e7dd 0%, #badbcc 100%);
            color: #0f5132;
        }

        .terms-notice {
            text-align: center;
            margin-bottom: 1.5rem;
            padding: 1rem;
            background: rgba(248, 249, 250, 0.6);
            border-radius: 12px;
            border: 1px solid rgba(233, 236, 239, 0.5);
            backdrop-filter: blur(10px);
        }

        .terms-notice a {
            color: var(--bs-secondary);
            text-decoration: none;
            font-weight: 500;
        }

        .terms-notice a:hover {
            text-decoration: underline;
        }

        .password-strength {
            margin-top: 0.5rem;
            font-size: 0.85rem;
            position: relative;
            z-index: 1;
            background: white;
            padding: 0.25rem 0;
            margin-bottom: 0.5rem;
        }

        .strength-weak {
            color: #dc3545;
        }

        .strength-medium {
            color: #ffc107;
        }

        .strength-strong {
            color: #198754;
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            color: #6c757d;
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: #e9ecef;
        }

        .divider span {
            background: white;
            padding: 0 1rem;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        .toggle-password:hover {
            color: var(--bs-secondary);
        }

        /* Terms Modal */
        .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
        }

        .modal-header {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            border-radius: 15px 15px 0 0;
        }

        .btn-close {
            filter: invert(1);
        }

        .terms-text {
            max-height: 300px;
            overflow-y: auto;
        }

        .terms-text p {
            margin-bottom: 0.75rem;
            line-height: 1.6;
        }

        /* Feature highlights */
        .feature-item {
            display: flex;
            align-items: center;
            margin-bottom: 1rem;
            opacity: 0.9;
        }

        .feature-icon {
            width: 40px;
            height: 40px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
        }

        /* Responsive Design */
        @media (max-width: 768px) {
            .auth-left {
                display: none;
            }
            
            .auth-right {
                padding: 2rem 1.5rem;
            }
            
            .auth-card {
                margin: 0 0.5rem;
            }
        }

        @media (max-width: 576px) {
            .form-title {
                font-size: 1.75rem;
            }
            
            .brand-logo {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
     <?php include 'includes/user_promo.php'; ?>
    <?php include 'includes/user_navbar.php'; ?>

    <div class="auth-container">
        <div class="auth-card">
            <!-- Header with Logo -->
            <div class="auth-header">
                <div class="logo-container">
                    <img src="images/logo.png" alt="MikeMadz Logo" class="logo">
                    <p class="brand-tagline">MikeMadz</p>
                    <p class="brand-tagline">Premium Quality Products</p>
                </div>
            </div>

            <!-- Registration Form -->
            <div class="auth-form">
                <h2 class="form-title">Create Account</h2>
                <p class="form-subtitle">Join our community and start shopping</p>

                            <!-- Error/Success Messages -->
                            <?php if (isset($_SESSION['error'])): ?>
                                <div class="alert alert-danger">
                                    <i class="fas fa-exclamation-circle me-2"></i>
                                    <?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                                </div>
                            <?php endif; ?>

                            <?php if (isset($_SESSION['success'])): ?>
                                <div class="alert alert-success">
                                    <i class="fas fa-check-circle me-2"></i>
                                    <?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                                </div>
                            <?php endif; ?>

                            <!-- Registration Form -->
                            <form method="POST" action="register.php">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <i class="fas fa-user form-icon"></i>
                                            <input type="text" name="first_name" class="form-control" placeholder="First Name" required>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="form-group">
                                            <i class="fas fa-user form-icon"></i>
                                            <input type="text" name="last_name" class="form-control" placeholder="Last Name" required>
                                        </div>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <i class="fas fa-at form-icon"></i>
                                    <input type="text" name="username" class="form-control" placeholder="Username" required>
                                </div>

                                <div class="form-group">
                                    <i class="fas fa-envelope form-icon"></i>
                                    <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                                </div>

                                <div class="form-group">
                                    <div class="password-input-container">
                                        <i class="fas fa-lock form-icon"></i>
                                        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                                        <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
                                    </div>
                                    <div class="password-strength" id="passwordStrength"></div>
                                </div>

                                <div class="form-group">
                                    <i class="fas fa-lock form-icon"></i>
                                    <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Confirm Password" required>
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('confirm_password')"></i>
                                </div>

                                <div class="terms-notice">
                                    <small class="text-muted">
                                        By creating an account, you agree to our <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                                    </small>
                                </div>

                                <button type="submit" name="register" class="btn btn-primary btn-register">
                                    <i class="fas fa-user-plus me-2"></i>Create Account
                                </button>
                            </form>

                            <div class="divider">
                                <span>or</span>
                            </div>

                <a href="login.php" class="btn btn-login">
                    <i class="fas fa-sign-in-alt me-2"></i>Already have an account? Sign In
                </a>
            </div>
        </div>
    </div>

    <!-- Terms and Conditions Modal -->
    <div class="modal fade" id="termsModal" tabindex="-1" aria-labelledby="termsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="termsModalLabel">
                        <i class="fas fa-file-contract me-2"></i>Terms and Conditions
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="terms-text">
            
                        <p>Welcome to MikeMadz Frozen Product Store! By using our online meat ordering system ("Service"), you agree to the following terms:</p>
                        
                        <p><strong>1. Ordering & Payments</strong><br>
                        Orders can be placed through our website/app.<br>
                        Cash on Delivery (COD) is only available for orders up to ₱2,000.00. For higher amounts, payments must be made through [Credit/Debit Card, Bank Transfer, or E-Wallet].<br>
                        A minimum order amount of ₱300.00 applies for COD transactions.<br>
                        All prices are in [PHP] and may change without prior notice.</p>
                        
                        <p><strong>2. Delivery & Pick-up</strong><br>
                        <strong>Pickup</strong><br>
                        Customers can select Pickup and collect their orders directly from our store.<br>
                        Customers may also arrange their own delivery by booking a third-party courier (e.g., Grab, Lalamove) to pick up the order on their behalf.<br>
                        Once the order is released to the customer or their chosen courier, responsibility for the product transfers to the customer.<br><br>
                        <strong>3. Delivery</strong><br>
                        We arrange nationwide delivery through trusted third-party courier partners for customer convenience.<br>
                        Delivery fees, timelines, and conditions will follow the policies of the assigned courier.<br>
                        While we coordinate the shipment, we are not responsible for courier delays, damages, or failed deliveries caused by factors beyond our control (e.g., weather, traffic, incomplete addresses).<br>
                        Customers must provide accurate delivery details to avoid delays or additional charges.</p>
                        
                        <p><strong>4. Cancellations & Refunds</strong><br>
                        Customers may cancel their orders only if the order has not yet been shipped or dispatched.<br>
                        Once an order is shipped, it can no longer be canceled.<br>
                        Perishable goods cannot be refunded once delivered, unless proven defective or spoiled upon receipt.<br>
                        The admin reserves the right to cancel or not proceed with an order if the submitted proof of payment (via Bank Transfer or GCash) is not verified or invalid.<br>
                        In case of admin-initiated cancellation, the customer will be notified immediately, and any verified payments already received will be refunded.</p>
                        
                        <p><strong>5. Product Quality</strong><br>
                        We guarantee that our meats are fresh and handled in compliance with food safety standards.<br>
                        All products are properly packed and stored in well-insulated styrofoam containers to ensure freshness during handling and delivery.<br>
                        When properly frozen and stored, our meats can maintain quality for an extended period, even up to years, depending on storage conditions.<br>
                        Customers are advised to refrigerate or freeze products immediately upon receipt to maximize shelf life.<br>
                        We are not responsible for spoilage due to improper storage or delayed receipt after successful delivery.</p>
                        
                        <p><strong>6. Limitation of Liability</strong><br>
                        We are not liable for any indirect, incidental, or consequential damages, including but not limited to product spoilage caused by delayed receipt, courier delays, or improper storage after delivery.<br>
                        Once an order is handed over to a third-party courier, responsibility for the handling, timeliness, and condition of the shipment lies with the courier and the customer.<br>
                        Our maximum liability, in any case, is limited to the total amount paid for the specific order in question.</p>
                        
                        <hr>
                        
                        <h4>🔒 Privacy Policy</h4>
                        <p>Your privacy is important to us. This Privacy Policy explains how we collect, use, and protect your personal information when you use our online ordering system.</p>
                        
                        <p><strong>1. Information We Collect</strong><br>
                        Personal details such as name, delivery address, contact number, and email address.<br>
                        Payment details, processed securely via trusted third-party payment providers (we do not store your full payment information).<br>
                        Order history and preferences to help us improve your experience.</p>
                        
                        <p><strong>2. How We Use Your Information</strong><br>
                        To process and deliver your orders.<br>
                        To contact you regarding your order status or, with your consent, send promotions and updates.<br>
                        To improve our services, website/app features, and customer experience.</p>
                        
                        <p><strong>3. Data Sharing</strong><br>
                        We may share limited personal information (such as name, delivery address, and contact number) with third-party couriers or service providers solely for the purpose of fulfilling your order.<br>
                        These partners are only authorized to use your data to complete delivery and are required to keep it secure.<br>
                        We do not sell, trade, or rent your personal information to unrelated third parties.</p>
                        
                        <p><strong>4. Data Protection</strong><br>
                        We comply with the Philippine Data Privacy Act of 2012 (RA 10173).<br>
                        We use secure servers, encryption, and industry-standard practices to safeguard your information.<br>
                        Access to your personal data is limited to authorized personnel only.</p>
                        
                        <p><strong>5. Your Rights</strong><br>
                        You may access and update or correct your personal information in your account.<br>
                        You may opt out of marketing communications anytime by following the unsubscribe instructions or contacting us directly.<br>
                        Please note: Account deletion is not currently available in our system, but we comply with the Philippine Data Privacy Act of 2012 regarding the proper handling and retention of your data.</p>
                        
                        <hr>
                        
                        <h4>📱 End-User License Agreement (EULA)</h4>
                        <p>This End-User License Agreement ("Agreement") is a legal contract between you ("User") and MikeMadz Frozen Product Store ("Company") regarding the use of our online ordering platform and related services ("Service").</p>
                        
                        <p><strong>1. License Grant</strong><br>
                        We grant you a limited, non-exclusive, non-transferable license to access and use the Service solely for personal, non-commercial purposes (e.g., browsing, placing orders, and managing your account).</p>
                        
                        <p><strong>2. Restrictions</strong><br>
                        You may not copy, modify, distribute, sell, lease, or reverse-engineer any part of the Service.<br>
                        You may not use the Service for any fraudulent, abusive, or unlawful purposes.<br>
                        You must not interfere with or disrupt the Service, servers, or networks connected to it.</p>
                        
                        <p><strong>3. Ownership</strong><br>
                        All content, software, trademarks, logos, and intellectual property within the Service remain the sole property of MikeMadz Frozen Product Store.<br>
                        Use of the Service does not grant you ownership rights in any part of it.</p>
                        
                        <p><strong>4. Payments</strong><br>
                        By placing an order, you agree to provide accurate payment information through the available payment methods (Cash on Delivery, Bank Transfer, GCash).<br>
                        Orders may be canceled by the admin if proof of payment is invalid, unverified, or fraudulent.</p>
                        
                        <p><strong>5. Termination</strong><br>
                        We may suspend or terminate your access immediately if you violate this Agreement or applicable laws.<br>
                        Upon termination, your license to use the Service will end, but you remain responsible for any outstanding payments.</p>
                        
                        <p><strong>6. Disclaimer of Warranties</strong><br>
                        The Service is provided "as is" and "as available," without warranties of any kind.<br>
                        We do not guarantee uninterrupted access, error-free operation, or that the Service will always be secure.</p>
                        
                        <p><strong>7. Limitation of Liability</strong><br>
                        To the maximum extent permitted by law, our liability is limited to the amount you paid for the order in question.<br>
                        We are not responsible for indirect damages (e.g., spoilage due to late receipt, third-party courier delays, or technical failures).</p>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const password = document.getElementById(fieldId);
            const toggleIcon = password.nextElementSibling;
            
            if (password.type === 'password') {
                password.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                password.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // Password strength checker
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            
            if (password.length === 0) {
                strengthDiv.innerHTML = '';
                return;
            }
            
            let strength = 0;
            if (password.length >= 6) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^a-zA-Z0-9]/)) strength++;
            
            switch(strength) {
                case 0:
                case 1:
                case 2:
                    strengthDiv.innerHTML = '<i class="fas fa-times-circle me-1"></i>Weak password';
                    strengthDiv.className = 'password-strength strength-weak';
                    break;
                case 3:
                case 4:
                    strengthDiv.innerHTML = '<i class="fas fa-exclamation-circle me-1"></i>Medium password';
                    strengthDiv.className = 'password-strength strength-medium';
                    break;
                case 5:
                    strengthDiv.innerHTML = '<i class="fas fa-check-circle me-1"></i>Strong password';
                    strengthDiv.className = 'password-strength strength-strong';
                    break;
            }
        });

        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('blur', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0 && password.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#198754';
                } else {
                    this.style.borderColor = '#dc3545';
                    // Show SweetAlert when passwords don't match
                    Swal.fire({
                        icon: 'error',
                        title: 'Password Mismatch',
                        text: 'The passwords you entered do not match. Please try again.',
                        confirmButtonColor: '#7F1734',
                        timer: 3000,
                        timerProgressBar: true,
                        showConfirmButton: true
                    });
                }
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });

        // Real-time password confirmation validation (without alert)
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;
            
            if (confirmPassword.length > 0) {
                if (password === confirmPassword) {
                    this.style.borderColor = '#198754';
                } else {
                    this.style.borderColor = '#dc3545';
                }
            } else {
                this.style.borderColor = '#e9ecef';
            }
        });

        // Form validation feedback
        document.querySelector('form').addEventListener('submit', function(e) {
            const firstName = document.querySelector('input[name="first_name"]').value;
            const lastName = document.querySelector('input[name="last_name"]').value;
            const username = document.querySelector('input[name="username"]').value;
            const email = document.querySelector('input[name="email"]').value;
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            // Validate required fields
            if (!firstName || !lastName || !username || !email || !password || !confirmPassword) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Missing Information',
                    text: 'All fields are required!',
                    confirmButtonColor: '#7F1734',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
                return;
            }
            
            // Validate name lengths
            if (firstName.length < 2) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid First Name',
                    text: 'First name must be at least 2 characters long!',
                    confirmButtonColor: '#7F1734',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
                return;
            }
            
            if (lastName.length < 2) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Invalid Last Name',
                    text: 'Last name must be at least 2 characters long!',
                    confirmButtonColor: '#7F1734',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
                return;
            }
            
            // Validate password match
            if (password !== confirmPassword) {
                e.preventDefault();
                Swal.fire({
                    icon: 'error',
                    title: 'Password Mismatch',
                    text: 'Passwords do not match!',
                    confirmButtonColor: '#7F1734',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
                return;
            }
            
            // Validate password length
            if (password.length < 6) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Weak Password',
                    text: 'Password must be at least 6 characters long!',
                    confirmButtonColor: '#7F1734',
                    timer: 3000,
                    timerProgressBar: true,
                    showConfirmButton: true
                });
                return;
            }
            
            const button = document.querySelector('.btn-register');
            button.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Creating Account...';
            button.disabled = true;
        });

        // Auto-hide alerts after 5 seconds
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
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
</body>
</html>