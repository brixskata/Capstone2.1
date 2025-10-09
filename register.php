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
        // Check if user already exists (check username in users table and email in user_info table)
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = :username");
        $stmt->bindParam(':username', $username);
        $stmt->execute();
        $usernameExists = $stmt->fetch();
        
        $stmt = $pdo->prepare("SELECT 1 FROM user_info WHERE email = :email");
        $stmt->bindParam(':email', $email);
        $stmt->execute();
        $emailExists = $stmt->fetch();
        
        if ($usernameExists || $emailExists) {
            $_SESSION['error'] = "Username or email already exists.";
        } else {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);

            // Start transaction to ensure both inserts succeed
            $pdo->beginTransaction();
            try {
                // Insert user into users table (without email)
                $stmt = $pdo->prepare("INSERT INTO users (username, password, email_verified, is_active, usertype_id) VALUES (:username, :password, 0, 1, NULL)");
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
                
                $_SESSION['success'] = "Registration successful! Please verify your email to complete the process.";
                header("Location: email_verification.php?email=" . urlencode($email));
                exit;
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
                                    <i class="fas fa-lock form-icon"></i>
                                    <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                                    <i class="fas fa-eye toggle-password" onclick="togglePassword('password')"></i>
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
                        <p><strong>1. Acceptance of Terms</strong><br>
                        By using MikeMadz, you agree to these terms and conditions and our privacy policy.</p>
                        
                        <p><strong>2. User Account</strong><br>
                        You must be at least 18 years old to create an account. You are responsible for maintaining the security of your account credentials.</p>
                        
                        <p><strong>3. Privacy Policy</strong><br>
                        Your personal information will be handled according to our privacy policy. We respect your privacy and protect your data.</p>
                        
                        <p><strong>4. Account Security</strong><br>
                        You are responsible for maintaining the confidentiality of your account and password. Notify us immediately of any unauthorized use.</p>
                        
                        <p><strong>5. Prohibited Activities</strong><br>
                        We reserve the right to suspend accounts that violate our terms, engage in fraudulent activities, or misuse our services.</p>
                        
                        <p><strong>6. Product Information</strong><br>
                        All purchases are subject to availability. Prices and product information are subject to change without notice.</p>
                        
                        <p><strong>7. Order Processing</strong><br>
                        Orders are processed in the order received. We reserve the right to refuse or cancel orders at our discretion.</p>
                        
                        <p><strong>8. Accurate Information</strong><br>
                        You agree to provide accurate and complete information when creating your account and placing orders.</p>
                        
                        <p><strong>9. Changes to Terms</strong><br>
                        We reserve the right to modify these terms at any time. Continued use of our services constitutes acceptance of new terms.</p>
                        
                        <p><strong>10. Contact Information</strong><br>
                        If you have any questions about these terms, please contact our customer support team.</p>
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
                alert('All fields are required!');
                return;
            }
            
            // Validate name lengths
            if (firstName.length < 2) {
                e.preventDefault();
                alert('First name must be at least 2 characters long!');
                return;
            }
            
            if (lastName.length < 2) {
                e.preventDefault();
                alert('Last name must be at least 2 characters long!');
                return;
            }
            
            // Validate password match
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return;
            }
            
            // Validate password length
            if (password.length < 6) {
                e.preventDefault();
                alert('Password must be at least 6 characters long!');
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