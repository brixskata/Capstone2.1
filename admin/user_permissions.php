<?php
include '../includes/db.php';
include '../includes/log_history.php';
include '../includes/permissions.php';
session_start();

// Define sidebar sections and their required permissions (must be defined early)
$sidebar_sections = [
    'overview' => [
        'title' => 'Overview',
        'icon' => 'chart-pie',
        'description' => 'Dashboard, Inventory Overview',
        'permissions' => [11] // inventory_view
    ],
    'sales' => [
        'title' => 'Sales',
        'icon' => 'shopping-cart',
        'description' => 'Transaction Logs',
        'permissions' => [15] // order_view
    ],
    'inventory' => [
        'title' => 'Inventory Management',
        'icon' => 'boxes',
        'description' => 'Restocking, Stock Adjustment, Stock Levels, Stock Movements, Batch Management',
        'permissions' => [11, 12, 13, 88] // inventory_view, inventory_restock, inventory_adjust, inventory_adjustment
    ],
    'products' => [
        'title' => 'Product Management',
        'icon' => 'shopping-bag',
        'description' => 'All Products, Add Product, Categories, Brands, Units, Archived, Discounts, Suppliers',
        'permissions' => [6, 7, 8, 9, 20, 21, 22, 23, 24, 25, 26, 27, 28, 29, 30, 32, 33, 34, 89] // product permissions + category + brand + uom + archive + suppliers
    ],
    'maintenance' => [
        'title' => 'Maintenance',
        'icon' => 'cogs',
        'description' => 'User Accounts, ID Verification, Promo Messages',
        'permissions' => [1, 2, 3, 4, 5] // user permissions only (Promo Messages is always visible)
    ],
    'analytics' => [
        'title' => 'Monitoring',
        'icon' => 'chart-line',
        'description' => 'Reports, Activity Log',
        'permissions' => [35, 36, 37, 42, 43] // reports + history permissions
    ]
];

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Handle role creation
if (isset($_POST['create_role'])) {
    $role_name = trim($_POST['role_name']);
    
    try {
        // Check if role already exists
        $stmt = $pdo->prepare("SELECT 1 FROM user_type WHERE role = ?");
        $stmt->execute([$role_name]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Role '$role_name' already exists";
        } else {
            // Create new role
            $stmt = $pdo->prepare("INSERT INTO user_type (role) VALUES (?)");
            $stmt->execute([$role_name]);
            $new_role_id = $pdo->lastInsertId();
            
            // Store new role ID in session for permission assignment
            $_SESSION['new_role_id'] = $new_role_id;
            $_SESSION['new_role_name'] = $role_name;
            
            $_SESSION['success'] = "Role '$role_name' created successfully. Please assign permissions.";
            logHistory($pdo, 'Role Created', "Created new role: $role_name", $_SESSION['username']);
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error creating role: " . $e->getMessage();
    }
    
    header("Location: user_permissions.php");
    exit;
}

// Handle role permission assignment
if (isset($_POST['assign_role_permissions'])) {
    $role_id = (int)$_POST['role_id'];
    $selected_sections = $_POST['sections'] ?? [];
    
    try {
        $pdo->beginTransaction();
        
        // Remove all existing permissions for this role
        $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE usertype_id = ?");
        $stmt->execute([$role_id]);
        $deleted_count = $stmt->rowCount();
        
        // Add permissions based on selected sections
        $inserted_count = 0;
        if (!empty($selected_sections)) {
            $stmt = $pdo->prepare("INSERT INTO role_permissions (usertype_id, permission_id) VALUES (?, ?)");
            foreach ($selected_sections as $section_key) {
                if (isset($sidebar_sections[$section_key])) {
                    foreach ($sidebar_sections[$section_key]['permissions'] as $permission_id) {
                        $stmt->execute([$role_id, $permission_id]);
                        $inserted_count++;
                    }
                }
            }
        }
        
        $pdo->commit();
        
        // Get role name for success message
        $stmt = $pdo->prepare("SELECT role FROM user_type WHERE usertype_id = ?");
        $stmt->execute([$role_id]);
        $role = $stmt->fetch();
        
        $_SESSION['success'] = "Permissions assigned to role '{$role['role']}' successfully (Added: $inserted_count permissions)";
        
        // Log the action
        $sections_assigned = implode(', ', array_map(function($key) use ($sidebar_sections) {
            return $sidebar_sections[$key]['title'];
        }, $selected_sections));
        logHistory($pdo, 'Role Permissions Assigned', "Assigned permissions to role: {$role['role']} - Sections: {$sections_assigned}", $_SESSION['username']);
        
        // Clear session flags
        unset($_SESSION['new_role_id']);
        unset($_SESSION['new_role_name']);
        
    } catch (Exception $e) {
        $pdo->rollBack();
        $_SESSION['error'] = "Error assigning permissions: " . $e->getMessage();
    }
    
    header("Location: user_permissions.php");
    exit;
}

// Handle clearing new role session variables
if (isset($_POST['clear_new_role_session'])) {
    unset($_SESSION['new_role_id']);
    unset($_SESSION['new_role_name']);
    exit; // Exit to prevent page reload
}

// Handle role deletion
if (isset($_POST['delete_role'])) {
    $role_id = (int)$_POST['role_id'];
    
    try {
        // Check if role is in use (only count active users)
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE usertype_id = ? AND is_active = 1");
        $stmt->execute([$role_id]);
        $userCount = $stmt->fetchColumn();
        
        if ($userCount > 0) {
            $_SESSION['error'] = "Cannot delete role: $userCount active user(s) are currently using this role";
        } else {
            // Get role name for logging
            $stmt = $pdo->prepare("SELECT role FROM user_type WHERE usertype_id = ?");
            $stmt->execute([$role_id]);
            $role = $stmt->fetch();
            
            try {
                $pdo->beginTransaction();
                
                // First, reassign any deactivated users to a default role (customer or admin)
                $default_role_stmt = $pdo->prepare("SELECT usertype_id FROM user_type WHERE role = 'customer' LIMIT 1");
                $default_role_stmt->execute();
                $default_role_id = $default_role_stmt->fetchColumn();
                
                if ($default_role_id) {
                    // Reassign deactivated users to default role
                    $reassign_stmt = $pdo->prepare("UPDATE users SET usertype_id = ? WHERE usertype_id = ? AND is_active = 0");
                    $reassign_stmt->execute([$default_role_id, $role_id]);
                    $reassigned_count = $reassign_stmt->rowCount();
                }
                
                // Delete associated role permissions
                $stmt = $pdo->prepare("DELETE FROM role_permissions WHERE usertype_id = ?");
                $stmt->execute([$role_id]);
                
                // Delete role
                $stmt = $pdo->prepare("DELETE FROM user_type WHERE usertype_id = ?");
                $stmt->execute([$role_id]);
                
                $pdo->commit();
                
                $message = "Role '{$role['role']}' deleted successfully";
                if (isset($reassigned_count) && $reassigned_count > 0) {
                    $message .= " ($reassigned_count deactivated users reassigned to default role)";
                }
                
                $_SESSION['success'] = $message;
                logHistory($pdo, 'Role Deleted', "Deleted role: {$role['role']}", $_SESSION['username']);
                
            } catch (Exception $e) {
                $pdo->rollBack();
                throw $e;
            }
        }
    } catch (Exception $e) {
        $_SESSION['error'] = "Error deleting role: " . $e->getMessage();
    }
    
    header("Location: user_permissions.php");
    exit;
}



// Get all roles with their permissions
$roles_query = "SELECT ut.usertype_id, ut.role, 
                       (SELECT COUNT(*) FROM users WHERE usertype_id = ut.usertype_id AND is_active = 1) as user_count,
                       GROUP_CONCAT(DISTINCT p.permission_name) as role_permissions
                FROM user_type ut
                LEFT JOIN role_permissions rp ON rp.usertype_id = ut.usertype_id
                LEFT JOIN permissions p ON p.permission_id = rp.permission_id
                GROUP BY ut.usertype_id, ut.role
                ORDER BY ut.role";
$roles_stmt = $pdo->query($roles_query);
$all_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all available permissions grouped by module
$permissions_query = "SELECT permission_id, permission_name, description, module 
                      FROM permissions 
                      ORDER BY module, permission_name";
$permissions_stmt = $pdo->query($permissions_query);
$all_permissions = $permissions_stmt->fetchAll(PDO::FETCH_ASSOC);

// Group permissions by module
$permissions_by_module = [];
foreach ($all_permissions as $perm) {
    $permissions_by_module[$perm['module']][] = $perm;
}


// Helper function to get admin password hash
function getAdminHash(PDO $pdo, string $username): ?string {
    $stmt = $pdo->prepare("SELECT u.password
                           FROM users u
                           LEFT JOIN user_type ut ON ut.usertype_id = u.usertype_id
                           WHERE u.username = ? AND (ut.role = 'admin' OR ut.role = 'super_admin' OR u.username IN ('admin','admin1'))");
    $stmt->execute([$username]);
    return $stmt->fetchColumn() ?: null;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>User Permissions - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
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
        
        /* Override admin styles for this page */
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
        
        .page-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 2rem;
        }

        /* Analytics Cards - Light Version */
        .analytics-card {
            background: white;
            color: var(--bs-dark);
            border-radius: 1rem;
            padding: 1.5rem;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s ease;
            height: 100%;
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }

        .analytics-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: var(--bs-primary);
        }

        .analytics-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(0,0,0,0.12);
        }

        .card-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            flex-shrink: 0;
            background: rgba(127, 23, 52, 0.1);
            color: var(--bs-primary);
        }

        .card-content {
            flex: 1;
        }

        .card-number {
            font-size: 2rem;
            font-weight: 700;
            color: var(--bs-primary);
            margin: 0;
            line-height: 1;
        }

        .card-label {
            color: var(--bs-secondary);
            font-size: 0.9rem;
            font-weight: 500;
            margin: 0.5rem 0 0 0;
        }
        
        .table-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            color: var(--text-primary) !important;
        }
        
        .table-card .card-header {
            background: transparent;
            border-bottom: 1px solid #e9ecef;
        }
        
        .table-card .card-body {
            padding: 1.5rem;
        }
        
        .permission-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .module-header {
            background: #7F1734;
            color: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .permission-item {
            display: flex;
            align-items: center;
            padding: 8px 0;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .permission-item:last-child {
            border-bottom: none;
        }
        
        .user-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
            border: 1px solid #e9ecef;
            margin-bottom: 20px;
        }
        
        .permission-badge {
            font-size: 0.75rem;
            padding: 4px 8px;
            margin: 2px;
            background-color: #7F1734;
            color: white;
        }
        
        .btn-group .btn {
            margin-left: 2px;
        }
        
        .btn-group .btn:first-child {
            margin-left: 0;
        }
        
        @media (max-width: 768px) {
            .main-container {
                padding: 1rem;
            }
            
            .page-header {
                padding: 1.5rem;
            }
            
            .page-header h2 {
                font-size: 1.5rem;
            }
        }
        
        /* SweetAlert2 Custom Styling */
        .swal2-popup-rounded {
            border-radius: 20px !important;
        }
        
        .swal2-popup-rounded .swal2-title {
            border-radius: 20px 20px 0 0 !important;
        }
        
        .swal2-popup-rounded .swal2-actions {
            border-radius: 0 0 20px 20px !important;
        }
        
        /* Minimal Permission Item Styles */
        .permission-item-minimal:hover {
            background: rgba(127, 23, 52, 0.05) !important;
            border-color: rgba(127, 23, 52, 0.2) !important;
            transform: translateY(-1px);
        }
        
        .permission-item-minimal input[type="checkbox"]:checked + label .section-icon-minimal {
            background: rgba(127, 23, 52, 0.15) !important;
        }
        
        .permission-item-minimal input[type="checkbox"]:checked + label h6 {
            color: #7F1734 !important;
        }
    </style>
</head>
<body>
    <?php include 'includes/admin_navbar.php'; ?>
    <?php include 'includes/admin_sidebar.php'; ?>

    <!-- Main Content -->
    <main class="main-content" id="mainContent">
        <div class="main-container">
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

            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h2><i class="fas fa-user-shield me-2"></i>Roles</h2>
                        <p class="mb-0 opacity-75">Manage user roles</p>
                    </div>
                    <button class="btn text-white fw-bold px-4" 
                            style="background-color: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3);" 
                            data-bs-toggle="modal" data-bs-target="#createRoleModal">
                        <i class="fa fa-plus me-2"></i>Add Role
                    </button>
                </div>
            </div>

            <!-- Analytics Cards -->
            <div class="row g-4 mb-4">
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-users-cog"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($all_roles); ?></h3>
                            <p class="card-label">Total Roles</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php 
                                // Count total admin users (excluding customers)
                                $admin_count_query = "SELECT COUNT(*) FROM users u 
                                                    JOIN user_type ut ON u.usertype_id = ut.usertype_id 
                                                    WHERE ut.role != 'customer'";
                                $admin_count = $pdo->query($admin_count_query)->fetchColumn();
                                echo $admin_count;
                            ?></h3>
                            <p class="card-label">Admin Users</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-key"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($all_permissions); ?></h3>
                            <p class="card-label">Total Permissions</p>
                        </div>
                    </div>
                </div>
                
                <div class="col-lg-3 col-md-6">
                    <div class="analytics-card">
                        <div class="card-icon">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="card-content">
                            <h3 class="card-number"><?php echo count($sidebar_sections); ?></h3>
                            <p class="card-label">Sidebar Sections</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Roles Management Section -->
            <div class="table-card mb-4">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fa fa-users-cog me-2"></i>System Roles
                    </h5>
                </div>
                <div class="card-body">
                    <div class="row g-3">
                        <?php foreach ($all_roles as $role): ?>
                            <div class="col-lg-4 col-md-6">
                                <div class="role-card" style="background: white; border-radius: 8px; padding: 15px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); border: 1px solid #e9ecef;">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h6 class="mb-0 fw-bold"><?php echo ucfirst(str_replace('_', ' ', $role['role'])); ?></h6>
                                        <div class="btn-group" role="group">
                                            <button class="btn btn-sm btn-outline-primary" onclick="showManageAccessModal(<?php echo $role['usertype_id']; ?>, '<?php echo htmlspecialchars($role['role']); ?>')" title="Manage Access">
                                                <i class="fa fa-cog"></i>
                                            </button>
                                            <?php if ($role['user_count'] == 0 && !in_array($role['role'], ['super_admin', 'admin', 'customer'])): ?>
                                                <button class="btn btn-sm btn-outline-danger" onclick="showDeleteRoleModal(<?php echo $role['usertype_id']; ?>, '<?php echo htmlspecialchars($role['role']); ?>')" title="Delete Role">
                                                    <i class="fa fa-trash"></i>
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <small class="text-info">
                                            <i class="fa fa-users me-1"></i>
                                            <?php echo $role['user_count']; ?> user(s) assigned
                                        </small>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <h6 class="text-muted mb-1">Access:</h6>
                                        <?php
                                        // Get role's current permissions
                                        $role_perms_stmt = $pdo->prepare("SELECT permission_id FROM role_permissions WHERE usertype_id = ?");
                                        $role_perms_stmt->execute([$role['usertype_id']]);
                                        $role_permission_ids = $role_perms_stmt->fetchAll(PDO::FETCH_COLUMN);
                                        
                                        // Check which sections role has access to
                                        $role_sections = [];
                                        foreach ($sidebar_sections as $section_key => $section) {
                                            $has_section = true;
                                            foreach ($section['permissions'] as $required_perm_id) {
                                                if (!in_array($required_perm_id, $role_permission_ids)) {
                                                    $has_section = false;
                                                    break;
                                                }
                                            }
                                            if ($has_section) {
                                                $role_sections[] = $section['title'];
                                            }
                                        }
                                        
                                        if (!empty($role_sections)): ?>
                                            <?php foreach ($role_sections as $section_title): ?>
                                                <span class="permission-badge"><?php echo htmlspecialchars($section_title); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="text-muted">No access assigned</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>
    </main>


    <!-- Create Role Modal -->
    <div class="modal fade" id="createRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="user_permissions.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New Role</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Role Name</label>
                            <input type="text" class="form-control" name="role_name" required 
                                   placeholder="e.g., manager, moderator, staff" 
                                   pattern="[a-zA-Z0-9_]+" 
                                   title="Only letters, numbers, and underscores allowed">
                            <small class="text-muted">Use lowercase with underscores (e.g., store_manager)</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="create_role" class="btn text-white fw-bold px-4" style="background-color: #7F1734; border-radius: 8px;">Create Role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Assign Role Permissions Modal -->
    <div class="modal fade" id="assignRolePermissionsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="user_permissions.php" method="POST">
                <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #7F1734; color: white; border-radius: 16px 16px 0 0; border: none; padding: 1.5rem;">
                        <h5 class="modal-title mb-0 fw-bold">
                            <i class="fas fa-user-shield me-2"></i>
                            Assign Permissions to: <span id="newRoleName" class="text-warning"></span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding: 2rem;">
                        <input type="hidden" name="role_id" id="newRoleId">
                        
                        <div class="row g-3">
                            <?php foreach ($sidebar_sections as $section_key => $section): ?>
                                <div class="col-md-6">
                                    <div class="permission-item-minimal" style="background: #f8f9fa; border-radius: 12px; padding: 1rem; border: 2px solid transparent; transition: all 0.3s ease; cursor: pointer;" onclick="toggleCheckbox('role_section_<?php echo $section_key; ?>')">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="sections[]" 
                                                   value="<?php echo $section_key; ?>"
                                                   id="role_section_<?php echo $section_key; ?>"
                                                   style="transform: scale(1.2); margin-top: 0.1rem;">
                                            <label class="form-check-label w-100 ms-2" for="role_section_<?php echo $section_key; ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="section-icon-minimal me-3" style="width: 40px; height: 40px; background: rgba(127, 23, 52, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fa fa-<?php echo $section['icon']; ?>" style="color: #7F1734; font-size: 1.1rem;"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-semibold" style="color: #2c3e50; font-size: 0.95rem;">
                                                            <?php echo htmlspecialchars($section['title']); ?>
                                                        </h6>
                                                        <small class="text-muted" style="font-size: 0.8rem; line-height: 1.3;">
                                                            <?php echo htmlspecialchars($section['description']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4 p-3" style="background: rgba(127, 23, 52, 0.05); border-radius: 8px; border-left: 4px solid #7F1734;">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1" style="color: #7F1734;"></i>
                                Select the sections this role should have access to. Each section includes multiple related permissions.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f8f9fa; border-radius: 0 0 16px 16px; border: none; padding: 1.5rem;">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="assign_role_permissions" class="btn px-4 fw-bold" style="background-color: #7F1734; color: white; border-radius: 8px; border: none;">
                            <i class="fas fa-check me-2"></i>Assign Permissions
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Manage Access Modal -->
    <div class="modal fade" id="manageAccessModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <form action="user_permissions.php" method="POST">
                <div class="modal-content" style="border-radius: 16px; border: none; box-shadow: 0 10px 40px rgba(0,0,0,0.15);">
                    <div class="modal-header" style="background: #7F1734; color: white; border-radius: 16px 16px 0 0; border: none; padding: 1.5rem;">
                        <h5 class="modal-title mb-0 fw-bold">
                            <i class="fas fa-cog me-2"></i>
                            Manage Access for: <span id="manageRoleName" class="text-warning"></span>
                        </h5>
                        <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body" style="padding: 2rem;">
                        <input type="hidden" name="role_id" id="manageRoleId">
                        
                        <div class="row g-3">
                            <?php foreach ($sidebar_sections as $section_key => $section): ?>
                                <div class="col-md-6">
                                    <div class="permission-item-minimal" style="background: #f8f9fa; border-radius: 12px; padding: 1rem; border: 2px solid transparent; transition: all 0.3s ease; cursor: pointer;" onclick="toggleCheckbox('manage_section_<?php echo $section_key; ?>')">
                                        <div class="form-check mb-0">
                                            <input class="form-check-input" type="checkbox" 
                                                   name="sections[]" 
                                                   value="<?php echo $section_key; ?>"
                                                   id="manage_section_<?php echo $section_key; ?>"
                                                   style="transform: scale(1.2); margin-top: 0.1rem;">
                                            <label class="form-check-label w-100 ms-2" for="manage_section_<?php echo $section_key; ?>">
                                                <div class="d-flex align-items-center">
                                                    <div class="section-icon-minimal me-3" style="width: 40px; height: 40px; background: rgba(127, 23, 52, 0.1); border-radius: 8px; display: flex; align-items: center; justify-content: center;">
                                                        <i class="fa fa-<?php echo $section['icon']; ?>" style="color: #7F1734; font-size: 1.1rem;"></i>
                                                    </div>
                                                    <div>
                                                        <h6 class="mb-1 fw-semibold" style="color: #2c3e50; font-size: 0.95rem;">
                                                            <?php echo htmlspecialchars($section['title']); ?>
                                                        </h6>
                                                        <small class="text-muted" style="font-size: 0.8rem; line-height: 1.3;">
                                                            <?php echo htmlspecialchars($section['description']); ?>
                                                        </small>
                                                    </div>
                                                </div>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        
                        <div class="mt-4 p-3" style="background: rgba(127, 23, 52, 0.05); border-radius: 8px; border-left: 4px solid #7F1734;">
                            <small class="text-muted">
                                <i class="fas fa-info-circle me-1" style="color: #7F1734;"></i>
                                Modify the sections this role should have access to. Changes will be applied immediately.
                            </small>
                        </div>
                    </div>
                    <div class="modal-footer" style="background: #f8f9fa; border-radius: 0 0 16px 16px; border: none; padding: 1.5rem;">
                        <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal" style="border-radius: 8px;">
                            <i class="fas fa-times me-2"></i>Cancel
                        </button>
                        <button type="submit" name="assign_role_permissions" class="btn px-4 fw-bold" style="background-color: #7F1734; color: white; border-radius: 8px; border: none;">
                            <i class="fas fa-save me-2"></i>Update Permissions
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Role Modal -->
    <div class="modal fade" id="deleteRoleModal" tabindex="-1">
        <div class="modal-dialog">
            <form action="user_permissions.php" method="POST">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">Confirm Role Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="role_id" id="deleteRoleId">
                        <input type="hidden" name="delete_role" value="1">
                        <div class="alert alert-warning">
                            <i class="fa fa-exclamation-triangle me-2"></i>
                            Are you sure you want to delete the role "<span id="deleteRoleName"></span>"?
                            <br><strong>This action cannot be undone.</strong>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn text-white fw-bold px-4" style="background-color: #7F1734; border-radius: 8px;">Delete Role</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <?php include 'includes/admin_scripts.php'; ?>
    <script>
        function showManageAccessModal(roleId, roleName) {
            // Set the role details in the modal
            document.getElementById('manageRoleId').value = roleId;
            document.getElementById('manageRoleName').textContent = roleName;
            
            // Get current permissions for this role
            fetch('get_role_permissions.php?role_id=' + roleId)
                .then(response => response.json())
                .then(data => {
                    // Clear all checkboxes first
                    document.querySelectorAll('#manageAccessModal input[type="checkbox"]').forEach(checkbox => {
                        checkbox.checked = false;
                    });
                    
                    // Check the sections that this role has access to
                    if (data.sections) {
                        data.sections.forEach(sectionKey => {
                            const checkbox = document.getElementById('manage_section_' + sectionKey);
                            if (checkbox) {
                                checkbox.checked = true;
                            }
                        });
                    }
                })
                .catch(error => {
                    console.error('Error fetching role permissions:', error);
                });
            
            // Show the modal
            const manageModal = new bootstrap.Modal(document.getElementById('manageAccessModal'));
            manageModal.show();
        }
        
        function showDeleteRoleModal(roleId, roleName) {
            Swal.fire({
                title: 'Confirm Role Deletion',
                html: `Are you sure you want to delete the role "<strong>${roleName}</strong>"?<br><strong>This action cannot be undone.</strong>`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#dc3545',
                cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, delete!',
                cancelButtonText: 'Cancel',
                customClass: {
                    popup: 'swal2-popup-rounded'
                }
            }).then((result) => {
                if (result.isConfirmed) {
                    // Create a form and submit it
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.action = 'user_permissions.php';
                    
                    const roleIdInput = document.createElement('input');
                    roleIdInput.type = 'hidden';
                    roleIdInput.name = 'role_id';
                    roleIdInput.value = roleId;
                    
                    const actionInput = document.createElement('input');
                    actionInput.type = 'hidden';
                    actionInput.name = 'delete_role';
                    actionInput.value = '1';
                    
                    form.appendChild(roleIdInput);
                    form.appendChild(actionInput);
                    document.body.appendChild(form);
                    form.submit();
                }
            });
        }
        
        // Toggle checkbox when clicking on permission item
        function toggleCheckbox(checkboxId) {
            const checkbox = document.getElementById(checkboxId);
            checkbox.checked = !checkbox.checked;
            
            // Trigger change event for any listeners
            checkbox.dispatchEvent(new Event('change'));
        }
        
        // Auto-show permission modal after role creation
        document.addEventListener('DOMContentLoaded', function() {
            <?php if (isset($_SESSION['new_role_id']) && isset($_SESSION['new_role_name'])): ?>
                // Set the role details in the modal
                document.getElementById('newRoleId').value = <?php echo $_SESSION['new_role_id']; ?>;
                document.getElementById('newRoleName').textContent = '<?php echo htmlspecialchars($_SESSION['new_role_name']); ?>';
                
                // Show the permission assignment modal
                const permissionModal = new bootstrap.Modal(document.getElementById('assignRolePermissionsModal'));
                permissionModal.show();
                
                // Clear session variables when modal is hidden (cancelled or closed)
                document.getElementById('assignRolePermissionsModal').addEventListener('hidden.bs.modal', function() {
                    // Send AJAX request to clear session variables
                    fetch('user_permissions.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: 'clear_new_role_session=1'
                    }).catch(error => {
                        console.log('Session cleared');
                    });
                });
            <?php endif; ?>
        });
    </script>
</body>
</html>

<?php
// Helper function to get module icons
function getModuleIcon($module) {
    $icons = [
        'inventory' => 'boxes',
        'products' => 'cube',
        'orders' => 'shopping-cart',
        'reports' => 'chart-bar',
        'users' => 'users',
        'suppliers' => 'truck',
        'transactions' => 'receipt',
        'system' => 'cog'
    ];
    return $icons[$module] ?? 'cog';
}
?>
