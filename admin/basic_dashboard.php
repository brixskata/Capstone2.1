<?php
include '../includes/db.php';
include '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit; 
}

// Check if user is a customer (deny access)
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Get user's role and permissions
$stmt = $pdo->prepare("SELECT ut.role FROM users u JOIN user_type ut ON u.usertype_id = ut.usertype_id WHERE u.user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user_role = $stmt->fetchColumn();

// Get user's available permissions
$user_permissions = [];
$stmt = $pdo->prepare("
    SELECT DISTINCT p.permission_name 
    FROM permissions p
    WHERE p.permission_id IN (
        SELECT rp.permission_id FROM role_permissions rp 
        WHERE rp.usertype_id = (SELECT usertype_id FROM users WHERE user_id = ?)
        UNION
        SELECT up.permission_id FROM user_permissions up 
        WHERE up.user_id = ?
    )
");
$stmt->execute([$_SESSION['user_id'], $_SESSION['user_id']]);
$user_permissions = $stmt->fetchAll(PDO::FETCH_COLUMN);

// Note: Super Admin users are redirected to admin_dashboard2.php, so this page is for other roles only
?>

<?php
$page_title = 'Dashboard - MikeMadz';
$page_description = 'Dashboard for MikeMadz admin users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <title><?= htmlspecialchars($page_title) ?></title>
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        :root {
            --bs-primary: #7F1734;
            --bs-secondary: #6c757d;
            --bs-success: #198754;
            --bs-danger: #dc3545;
            --bs-warning: #ffc107;
            --bs-info: #0dcaf0;
            --bs-light: #f8f9fa;
            --bs-dark: #212529;
        }
        
        .main-content {
            background-color: var(--bg-primary) !important;
        }
        
        .main-container {
            background: var(--card-bg);
            border-radius: 20px;
            box-shadow: var(--card-shadow);
            padding: 2rem;
            border: 1px solid var(--border-color);
        }
        
        .page-header {
            background: var(--bs-primary);
            color: white;
            padding: 2rem;
            border-radius: 15px;
            margin-bottom: 2rem;
            box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
        }
        
        .welcome-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 2.5rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }
        
        .welcome-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bs-primary);
        }
        
        .permission-badge {
            display: inline-block;
            background: rgba(127, 23, 52, 0.1);
            color: var(--bs-primary);
            padding: 0.4rem 0.8rem;
            border-radius: 25px;
            margin: 0.2rem;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid rgba(127, 23, 52, 0.2);
            transition: all 0.3s ease;
        }
        
        .permission-badge:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(127, 23, 52, 0.2);
            background: rgba(127, 23, 52, 0.15);
        }
        
        .no-permissions {
            text-align: center;
            padding: 2rem;
            color: var(--bs-secondary);
            background: rgba(108, 117, 125, 0.05);
            border-radius: 15px;
            border: 2px dashed rgba(108, 117, 125, 0.2);
        }
        
        .no-permissions i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--bs-secondary);
            opacity: 0.7;
        }
        
        
        .role-badge {
            background: var(--bs-primary);
            color: white;
            padding: 0.5rem 1.2rem;
            border-radius: 25px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-block;
            margin-top: 0.5rem;
            box-shadow: 0 4px 15px rgba(127, 23, 52, 0.3);
        }
        
        
        .info-card {
            background: #ffffff;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 8px 32px rgba(0,0,0,0.1);
            border: 1px solid #e9ecef;
        }
        
        .alert-info {
            background: rgba(13, 202, 240, 0.1);
            border: 1px solid rgba(13, 202, 240, 0.2);
            border-radius: 15px;
            padding: 1.5rem;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="main-container">
            <div class="page-header">
                <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
                <p class="mb-0 opacity-75">Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?></p>
            </div>

            <div class="welcome-card">
                <div class="row">
                    <div class="col-12">
                        <h3 class="text-primary mb-2">
                            <i class="fas fa-user-shield me-2"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?>
                        </h3>
                        <div class="role-badge">
                            <i class="fas fa-id-badge me-1"></i>
                            <?php echo ucfirst(str_replace('_', ' ', $user_role)); ?> Access
                        </div>
                        
                        <?php if (!empty($user_permissions)): ?>
                            <div class="mt-4">
                                <h6 class="text-muted mb-3">Available Permissions</h6>
                                <div class="permissions-list">
                                    <?php foreach ($user_permissions as $permission): ?>
                                        <span class="permission-badge">
                                            <i class="fas fa-check me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $permission)); ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="no-permissions mt-4">
                                <i class="fas fa-lock"></i>
                                <h5>Limited Access</h5>
                                <p class="mb-0">Contact administrator for permissions</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>


            <div class="row">
                <div class="col-12">
                    <div class="info-card">
                        <h5 class="mb-3">
                            <i class="fas fa-compass me-2"></i>
                            Navigation Guide
                        </h5>
                        <p class="text-muted mb-3">
                            Use the sidebar menu to access modules based on your permissions. 
                            Only sections you can access will be visible.
                        </p>
                        <div class="alert alert-info">
                            <i class="fas fa-lightbulb me-2"></i>
                            <strong>Tip:</strong> Each module shows different options based on your role permissions.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
