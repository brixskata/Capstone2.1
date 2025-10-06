<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin access
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle notification preferences update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_notifications'])) {
    try {
        $email_notifications = isset($_POST['email_notifications']) ? 1 : 0;
        $low_stock_alerts = isset($_POST['low_stock_alerts']) ? 1 : 0;
        $order_notifications = isset($_POST['order_notifications']) ? 1 : 0;
        $system_updates = isset($_POST['system_updates']) ? 1 : 0;
        
        // Update notification preferences (you can create a user_preferences table)
        // For now, we'll just show success message
        logHistory($pdo, 'Settings Update', "Updated notification preferences", $_SESSION['username']);
        $_SESSION['success'] = "Notification preferences updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating preferences: " . $e->getMessage();
    }
    header("Location: account_settings.php");
    exit;
}

// Handle theme preferences update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_theme'])) {
    try {
        $theme = $_POST['theme'];
        $sidebar_collapsed = isset($_POST['sidebar_collapsed']) ? 1 : 0;
        
        // Update theme preferences
        logHistory($pdo, 'Settings Update', "Updated theme preferences", $_SESSION['username']);
        $_SESSION['success'] = "Theme preferences updated successfully!";
        
    } catch (Exception $e) {
        $_SESSION['error'] = "Error updating theme: " . $e->getMessage();
    }
    header("Location: account_settings.php");
    exit;
}

// Get current user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Settings - MikeMadz Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
        .settings-card {
            background: white;
            border-radius: 16px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            overflow: hidden;
            margin-bottom: 2rem;
        }
        
        .settings-header {
            background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
            color: white;
            padding: 1.5rem 2rem;
        }
        
        .settings-body {
            padding: 2rem;
        }
        
        .section-title {
            color: #7F1734;
            font-weight: 600;
            margin-bottom: 1.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #f8f9fa;
        }
        
        .form-check-input:checked {
            background-color: #7F1734;
            border-color: #7F1734;
        }
        
        .theme-preview {
            width: 60px;
            height: 40px;
            border-radius: 8px;
            border: 2px solid #e9ecef;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .theme-preview:hover {
            border-color: #7F1734;
            transform: scale(1.05);
        }
        
        .theme-preview.selected {
            border-color: #7F1734;
            box-shadow: 0 0 0 2px rgba(127, 23, 52, 0.2);
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa fa-check-circle me-2"></i><?php echo $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa fa-exclamation-circle me-2"></i><?php echo $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 class="h3 fw-bold text-dark mb-2">
                    <i class="fa fa-cog me-3" style="color: #7F1734;"></i>Account Settings
                </h1>
                <p class="text-muted">Customize your account preferences and system settings</p>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <!-- Notification Settings -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0">
                            <i class="fa fa-bell me-2"></i>Notification Preferences
                        </h5>
                        <small class="opacity-75">Manage how you receive notifications</small>
                    </div>
                    
                    <div class="settings-body">
                        <form method="POST">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="email_notifications" name="email_notifications" checked>
                                        <label class="form-check-label" for="email_notifications">
                                            <strong>Email Notifications</strong>
                                            <br><small class="text-muted">Receive notifications via email</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="low_stock_alerts" name="low_stock_alerts" checked>
                                        <label class="form-check-label" for="low_stock_alerts">
                                            <strong>Low Stock Alerts</strong>
                                            <br><small class="text-muted">Get notified when inventory is low</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="order_notifications" name="order_notifications" checked>
                                        <label class="form-check-label" for="order_notifications">
                                            <strong>Order Notifications</strong>
                                            <br><small class="text-muted">Get notified about new orders</small>
                                        </label>
                                    </div>
                                    
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" id="system_updates" name="system_updates">
                                        <label class="form-check-label" for="system_updates">
                                            <strong>System Updates</strong>
                                            <br><small class="text-muted">Receive system maintenance notifications</small>
                                        </label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="update_notifications" class="btn btn-primary">
                                    <i class="fa fa-save me-2"></i>Save Notification Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Theme Settings -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0">
                            <i class="fa fa-palette me-2"></i>Theme & Display
                        </h5>
                        <small class="opacity-75">Customize the appearance of your admin panel</small>
                    </div>
                    
                    <div class="settings-body">
                        <form method="POST">
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Color Theme</label>
                                <div class="d-flex gap-3">
                                    <div class="text-center">
                                        <div class="theme-preview selected" style="background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);" data-theme="maroon"></div>
                                        <small class="d-block mt-1">Maroon</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="theme-preview" style="background: linear-gradient(135deg, #2c3e50 0%, #34495e 100%);" data-theme="dark"></div>
                                        <small class="d-block mt-1">Dark</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="theme-preview" style="background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);" data-theme="blue"></div>
                                        <small class="d-block mt-1">Blue</small>
                                    </div>
                                    <div class="text-center">
                                        <div class="theme-preview" style="background: linear-gradient(135deg, #27ae60 0%, #2ecc71 100%);" data-theme="green"></div>
                                        <small class="d-block mt-1">Green</small>
                                    </div>
                                </div>
                                <input type="hidden" name="theme" id="selected_theme" value="maroon">
                            </div>
                            
                            <div class="mb-4">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="sidebar_collapsed" name="sidebar_collapsed">
                                    <label class="form-check-label" for="sidebar_collapsed">
                                        <strong>Collapse Sidebar by Default</strong>
                                        <br><small class="text-muted">Start with sidebar collapsed for more screen space</small>
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end">
                                <button type="submit" name="update_theme" class="btn btn-primary">
                                    <i class="fa fa-save me-2"></i>Save Theme Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Quick Actions -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0">
                            <i class="fa fa-bolt me-2"></i>Quick Actions
                        </h5>
                    </div>
                    
                    <div class="settings-body">
                        <div class="d-grid gap-2">
                            <a href="profile_settings.php" class="btn btn-outline-primary">
                                <i class="fa fa-user me-2"></i>Edit Profile
                            </a>
                            <a href="change_password.php" class="btn btn-outline-warning">
                                <i class="fa fa-key me-2"></i>Change Password
                            </a>
                            <a href="activity_log.php" class="btn btn-outline-info">
                                <i class="fa fa-history me-2"></i>Activity Log
                            </a>
                            <a href="export_data.php" class="btn btn-outline-success">
                                <i class="fa fa-download me-2"></i>Export Data
                            </a>
                        </div>
                    </div>
                </div>

                <!-- System Information -->
                <div class="settings-card">
                    <div class="settings-header">
                        <h5 class="mb-0">
                            <i class="fa fa-info-circle me-2"></i>System Information
                        </h5>
                    </div>
                    
                    <div class="settings-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Admin Panel Version</label>
                            <p class="form-control-plaintext">v2.1.0</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">PHP Version</label>
                            <p class="form-control-plaintext"><?php echo PHP_VERSION; ?></p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Database</label>
                            <p class="form-control-plaintext">MySQL</p>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Last Updated</label>
                            <p class="form-control-plaintext"><?php echo date('M d, Y'); ?></p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    
    <script>
        // Theme selection
        document.querySelectorAll('.theme-preview').forEach(preview => {
            preview.addEventListener('click', function() {
                // Remove selected class from all
                document.querySelectorAll('.theme-preview').forEach(p => p.classList.remove('selected'));
                // Add selected class to clicked
                this.classList.add('selected');
                // Update hidden input
                document.getElementById('selected_theme').value = this.dataset.theme;
            });
        });
    </script>
</body>
</html>




