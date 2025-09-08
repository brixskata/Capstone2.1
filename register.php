<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = htmlspecialchars(trim($_POST['username']));
    $email = htmlspecialchars(trim($_POST['email']));
    $password = trim($_POST['password']);
    $confirm_password = trim($_POST['confirm_password']);
    $agreed_terms = isset($_POST['agree_terms']) ? true : false;

    // Validate fields
    if (!$agreed_terms) {
        $_SESSION['error'] = "Please agree to the Terms and Conditions";
    } else if (empty($username) || empty($email) || empty($password) || empty($confirm_password)) {
        $_SESSION['error'] = "All fields are required.";
    } else if ($password !== $confirm_password) {
        $_SESSION['error'] = "Passwords do not match.";
    } else if (strlen($password) < 6) {
        $_SESSION['error'] = "Password must be at least 6 characters long.";
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
                $stmt = $pdo->prepare("INSERT INTO users (username, password, is_verified, is_active, usertype_id) VALUES (:username, :password, 0, 1, NULL)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->execute();
                
                $newUserId = $pdo->lastInsertId();
                
                // Insert user info into user_info table (with email)
                $stmt = $pdo->prepare("INSERT INTO user_info (user_id, email) VALUES (:user_id, :email)");
                $stmt->bindParam(':user_id', $newUserId);
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
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

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            min-height: 100vh;
        }

        .auth-container {
            min-height: calc(100vh - 200px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem 0;
        }

        .auth-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            overflow: hidden;
            max-width: 900px;
            width: 100%;
            margin: 0 1rem;
        }

        .auth-left {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            color: white;
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            min-height: 700px;
        }

        .auth-right {
            padding: 3rem;
            display: flex;
            flex-direction: column;
            justify-content: center;
            min-height: 700px;
        }

        .brand-logo {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: white;
        }

        .brand-subtitle {
            font-size: 1.1rem;
            opacity: 0.9;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        .auth-form {
            max-width: 400px;
            margin: 0 auto;
            width: 100%;
        }

        .form-title {
            color: var(--bs-secondary);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: #6c757d;
            text-align: center;
            margin-bottom: 2rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-control {
            padding: 0.75rem 1rem 0.75rem 3rem;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: #f8f9fa;
        }

        .form-control:focus {
            border-color: var(--bs-secondary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
            background-color: white;
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
            font-size: 1.1rem;
        }

        .btn-register {
            background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
            border: none;
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1rem;
        }

        .btn-register:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(127, 23, 52, 0.3);
            background: linear-gradient(135deg, #a91d42 0%, var(--bs-secondary) 100%);
        }

        .btn-login {
            background: transparent;
            border: 2px solid var(--bs-secondary);
            color: var(--bs-secondary);
            padding: 0.75rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            font-size: 1rem;
            transition: all 0.3s ease;
            width: 100%;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }

        .btn-login:hover {
            background: var(--bs-secondary);
            color: white;
            transform: translateY(-2px);
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

        .terms-container {
            display: flex;
            align-items: flex-start;
            margin-bottom: 1.5rem;
        }

        .terms-container input[type="checkbox"] {
            margin-right: 0.5rem;
            margin-top: 0.25rem;
        }

        .terms-container label {
            font-size: 0.9rem;
            color: #6c757d;
            line-height: 1.4;
        }

        .terms-container a {
            color: var(--bs-secondary);
            text-decoration: none;
        }

        .terms-container a:hover {
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
            <div class="row g-0">
                <!-- Left Side - Branding -->
                <div class="col-lg-6">
                    <div class="auth-left">
                        <div>
                            <div class="brand-logo">
                                <i class="fas fa-store me-2"></i>MikeMadz
                            </div>
                            <p class="brand-subtitle">
                                Join our community of satisfied customers and enjoy quality products with excellent service.
                            </p>
                        </div>
                        
                        <div class="w-100">
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-user-plus"></i>
                                </div>
                                <div>
                                    <strong>Easy Registration</strong><br>
                                    <small>Quick and simple sign-up process</small>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div>
                                    <strong>Secure Account</strong><br>
                                    <small>Your information is protected</small>
                                </div>
                            </div>
                            <div class="feature-item">
                                <div class="feature-icon">
                                    <i class="fas fa-gift"></i>
                                </div>
                                <div>
                                    <strong>Exclusive Offers</strong><br>
                                    <small>Special deals for members</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Side - Register Form -->
                <div class="col-lg-6">
                    <div class="auth-right">
                        <div class="auth-form">
                            <h2 class="form-title">Create Account</h2>
                            <p class="form-subtitle">Join MikeMadz and start shopping today</p>

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
                                <div class="form-group">
                                    <i class="fas fa-user form-icon"></i>
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

                                <div class="terms-container">
                                    <input type="checkbox" name="agree_terms" id="agree_terms" required>
                                    <label for="agree_terms">
                                        I agree to the <a href="#" data-bs-toggle="modal" data-bs-target="#termsModal">Terms & Conditions</a>
                                    </label>
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
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
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