<?php
session_start();
include 'includes/db.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['login'])) {
    $email = htmlspecialchars(trim($_POST['email']));
    $password = trim($_POST['password']);
    
    if (empty($email) || empty($password)) {
        $_SESSION['error'] = "All fields are required.";
    } else {
        try {
            // Join users and user_info tables to get user data with email
            // Get the LATEST VERIFIED user with this email
            $sql = "SELECT u.user_id, u.username, u.password, u.email_verified, u.is_active, u.usertype_id, ui.email 
                    FROM users u 
                    INNER JOIN user_info ui ON u.user_id = ui.user_id 
                    WHERE ui.email = :email AND u.email_verified = 1
                    ORDER BY u.date_created DESC 
                    LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':email', $email);
            $stmt->execute();
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                if ($user['email_verified'] == 0) {
                    $_SESSION['error'] = "Please verify your email before logging in. <a href='email_verification.php' style='color: #7F1734;'>Click here to verify</a>";
                } else if ($user['is_active'] == 0) {
                    $_SESSION['error'] = "Your account has been deactivated. Please contact support.";
                } else {
                    $_SESSION['user_id'] = $user['user_id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['email'] = $user['email'];
                    // Set role based on usertype_id - need to get actual role from user_type table
                    if ($user['usertype_id'] === null) {
                        $_SESSION['role'] = 'customer';
                    } else {
                        // Get the actual role from user_type table
                        $roleStmt = $pdo->prepare("SELECT role FROM user_type WHERE usertype_id = ?");
                        $roleStmt->execute([$user['usertype_id']]);
                        $roleData = $roleStmt->fetch(PDO::FETCH_ASSOC);
                        $_SESSION['role'] = $roleData ? $roleData['role'] : 'customer';
                    }

                    // Load and merge cart from database for customers
                    if ($_SESSION['role'] === 'customer') {
                        include_once 'includes/cart_manager.php';
                        $cartManager = new CartManager($pdo);
                        
                        // Get current session cart (guest cart)
                        $session_cart = $_SESSION['cart'] ?? [];
                        
                        // Load cart from database and merge
                        $merged_cart = $cartManager->mergeCarts($_SESSION['user_id'], $session_cart);
                        
                        // Update session with merged cart
                        $_SESSION['cart'] = $merged_cart;
                        
                        // Save merged cart to database
                        $cartManager->saveCartToDatabase($_SESSION['user_id'], $merged_cart);
                    }

                    // Redirect based on role
                    if (in_array($_SESSION['role'], ['admin', 'super_admin'])) {
                        header('Location: admin/admin_dashboard2.php');
                    } else {
                        header('Location: index.php');
                    }
                    exit;
                }
            } else {
                $_SESSION['error'] = "Invalid credentials.";
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "An error occurred. Please try again.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In - MikeMadz</title>
    <link rel="icon" type="image/png" href="favicon.png">
    <?php include 'includes/user_head.php'; ?>
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: var(--bg-primary);
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
            background: var(--bg-card);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            box-shadow: 0 25px 50px var(--shadow-dark);
            overflow: hidden;
            max-width: 450px;
            width: 100%;
            border: 1px solid var(--border-light);
        }

        .auth-header {
            text-align: center;
            padding: 3rem 3rem 2rem;
            background: var(--brand-gradient);
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

        .auth-header .brand-tagline {
            font-size: 0.95rem;
            opacity: 0.9;
            margin-top: 0.5rem;
            font-weight: 400;
            color: white !important;
        }

        .logo-container .brand-tagline:first-of-type {
            font-size: 2rem;
            font-weight: 700;
            margin-top: 0;
            margin-bottom: 0.5rem;
            letter-spacing: -0.5px;
        }

        .card-title,
        .form-subtitle {
            color: white !important;
        }

        .auth-form {
            padding: 3rem;
        }

        .form-title {
            color: var(--text-primary);
            font-size: 1.75rem;
            font-weight: 600;
            margin-bottom: 0.5rem;
            text-align: center;
        }

        .form-subtitle {
            color: var(--text-secondary);
            text-align: center;
            margin-bottom: 2rem;
            font-size: 0.95rem;
        }

        .form-group {
            position: relative;
            margin-bottom: 1.5rem;
        }

        .form-control {
            padding: 1rem 1rem 1rem 3rem;
            border: 2px solid var(--border-light);
            border-radius: 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background-color: var(--input-bg);
            backdrop-filter: blur(10px);
            font-weight: 400;
            color: var(--text-primary);
        }

        .form-control:focus {
            border-color: var(--brand-primary);
            box-shadow: 0 0 0 3px rgba(127, 23, 52, 0.1);
            background-color: var(--input-bg);
            outline: none;
            color: var(--text-primary);
        }

        .form-control::placeholder {
            color: var(--text-secondary);
        }

        .form-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            font-size: 1rem;
            z-index: 2;
        }

        .btn-login {
            background: var(--brand-primary);
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

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 15px 30px rgba(127, 23, 52, 0.3);
            background: var(--brand-secondary);
            color: white;
        }

        .btn-register {
            background: transparent;
            border: 2px solid var(--border-light);
            color: var(--text-secondary);
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

        .btn-register:hover {
            background: var(--bg-tertiary);
            color: var(--brand-primary);
            border-color: var(--brand-primary);
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
            background: var(--bg-tertiary);
            border-radius: 12px;
            border: 1px solid var(--border-light);
            backdrop-filter: blur(10px);
        }

        .terms-notice a {
            color: var(--brand-primary);
            text-decoration: none;
            font-weight: 500;
        }

        .terms-notice a:hover {
            text-decoration: underline;
        }

        .toggle-password {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            cursor: pointer;
            transition: color 0.3s ease;
            z-index: 2;
        }

        .toggle-password:hover {
            color: var(--brand-primary);
        }

        .divider {
            text-align: center;
            margin: 1.5rem 0;
            color: var(--text-secondary);
            position: relative;
        }

        .divider::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 0;
            right: 0;
            height: 1px;
            background: var(--border-light);
        }

        .divider span {
            background: var(--bg-card);
            padding: 0 1rem;
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

            <!-- Login Form -->
            <div class="auth-form">
                <h2 class="form-title">Welcome Back</h2>
                <p class="form-subtitle">Sign in to your account</p>

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

                <!-- Login Form -->
                <form method="POST" action="login.php">
                    <div class="form-group">
                        <i class="fas fa-envelope form-icon"></i>
                        <input type="email" name="email" class="form-control" placeholder="Email Address" required>
                    </div>

                    <div class="form-group">
                        <i class="fas fa-lock form-icon"></i>
                        <input type="password" name="password" id="password" class="form-control" placeholder="Password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword()"></i>
                    </div>

                

                    <button type="submit" name="login" class="btn btn-primary btn-login">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </button>
                </form>

                <div class="divider">
                    <span>or</span>
                </div>

                <a href="register.php" class="btn btn-register">
                    <i class="fas fa-user-plus me-2"></i>Create New Account
                </a>
            </div>
        </div>
    </div>

    <?php include 'includes/user_footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        function togglePassword() {
            const password = document.getElementById('password');
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
    </script>
</body>
</html>