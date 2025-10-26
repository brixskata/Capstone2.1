
<?php
session_start();

// Always prevent caching for this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Check if user is logged in and has admin access (Super Admin or Admin)
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// If confirmation is given, proceed with logout
if (isset($_GET['confirm']) && $_GET['confirm'] === 'yes') {
    session_unset();  // Unset all session variables
    session_destroy();  // Destroy the session
    header("Location: login_admin.php");  // Redirect to login page
    exit;
}

// If not confirmed, show confirmation page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Logout - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #7F1734;
            --secondary-color: #a91d42;
            --danger-color: #dc3545;
            --light-bg: #f8f9fa;
        }
        
        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, var(--light-bg) 0%, #e9ecef 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .logout-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(127, 23, 52, 0.1);
            border: 1px solid #e9ecef;
            max-width: 450px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.5s ease-out;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .logout-header {
            background: #7F1734;
            padding: 2rem;
            text-align: center;
            position: relative;
        }
        
        .logout-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><defs><pattern id="grid" width="20" height="20" patternUnits="userSpaceOnUse"><path d="M 20 0 L 0 0 0 20" fill="none" stroke="rgba(255,255,255,0.05)" stroke-width="1"/></pattern></defs><rect width="100%" height="100%" fill="url(%23grid)"/></svg>') repeat;
        }
        
        .logout-icon {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1rem;
            position: relative;
            z-index: 1;
        }
        
        .logout-icon i {
            font-size: 2rem;
            color: white;
        }
        
        .logout-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 1;
        }
        
        .logout-subtitle {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.9rem;
            position: relative;
            z-index: 1;
        }
        
        .logout-body {
            padding: 2rem;
            text-align: center;
        }
        
        .logout-message {
            color: #495057;
            font-size: 1.1rem;
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .logout-warning {
            color: #6c757d;
            font-size: 0.9rem;
            margin-bottom: 2rem;
        }
        
        .admin-info {
            background: var(--light-bg);
            border-radius: 12px;
            padding: 1rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .admin-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            object-fit: cover;
            border: 2px solid #7F1734;
        }
        
        .admin-details h6 {
            margin: 0;
            color: #7F1734;
            font-weight: 600;
        }
        
        .admin-details small {
            color: #6c757d;
            font-size: 0.8rem;
        }
        
        .super-admin {
            color: var(--danger-color) !important;
            font-weight: 700;
        }
        
        .admin-role {
            color: #ffc107 !important;
            font-weight: 600;
        }
        
        .btn-logout {
            background: linear-gradient(135deg, var(--danger-color) 0%, #c82333 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(220, 53, 69, 0.3);
        }
        
        .btn-logout:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(220, 53, 69, 0.4);
            color: white;
        }
        
        .btn-cancel {
            background: linear-gradient(135deg, #6c757d 0%, #5a6268 100%);
            border: none;
            color: white;
            font-weight: 600;
            padding: 0.75rem 2rem;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        
        .btn-cancel:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(108, 117, 125, 0.3);
            color: white;
        }
        
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            flex-wrap: wrap;
        }
        
        .action-buttons .btn {
            min-width: 120px;
        }
    </style>
</head>
<body>
    <div class="logout-card">
        <!-- Header -->
        <div class="logout-header">
            <div class="logout-icon">
                <i class="fas fa-sign-out-alt"></i>
            </div>
            <h2 class="logout-title">Confirm Logout</h2>
            <p class="logout-subtitle"><?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'admin')); ?> Panel Session</p>
        </div>
        
        <!-- Body -->
        <div class="logout-body">
            <p class="logout-message">Are you sure you want to logout?</p>
            <p class="logout-warning">You will need to login again to access admin features.</p>
            
            <!-- Admin Info -->
            <div class="admin-info">
                <img src="uploads/admin-avatar.png" alt="Admin" class="admin-avatar" 
                     onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzZjNzU3ZCIvPgo8cGF0aCBkPSJNOCAzMmMwLTYuNjI3IDUuMzczLTEyIDEyLTEyczEyIDUuMzczIDEyIDEyIiBmaWxsPSIjNmM3NTdkIi8+Cjwvc3ZnPgo='">
                <div class="admin-details">
                    <h6><?php echo htmlspecialchars($_SESSION['username']); ?></h6>
                    <small class="<?php echo ($_SESSION['role'] ?? 'admin') === 'super_admin' ? 'super-admin' : 'admin-role'; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $_SESSION['role'] ?? 'admin')); ?>
                    </small>
                </div>
            </div>
            
            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="admin_dashboard2.php" class="btn btn-cancel">
                    <i class="fas fa-times me-2"></i>Cancel
                </a>
                <a href="logout_admin.php?confirm=yes" class="btn btn-logout">
                    <i class="fas fa-sign-out-alt me-2"></i>Logout
                </a>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
