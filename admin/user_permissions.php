<?php
include '../includes/db.php';
include '../includes/log_history.php';
include '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access
requireAdmin($pdo);

// Handle role creation
if (isset($_POST['create_role'])) {
    $role_name = trim($_POST['role_name']);
    $admin_password = $_POST['admin_password'];
    
    // Verify admin password
    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }
    
    if ($passwordOk) {
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
                
                $_SESSION['success'] = "Role '$role_name' created successfully";
                logHistory($pdo, 'Role Created', "Created new role: $role_name", $_SESSION['username']);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error creating role: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid admin password";
    }
    
    header("Location: user_permissions.php");
    exit;
}

// Handle role deletion
if (isset($_POST['delete_role'])) {
    $role_id = (int)$_POST['role_id'];
    $admin_password = $_POST['admin_password'];
    
    // Verify admin password
    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }
    
    if ($passwordOk) {
        try {
            // Check if role is in use
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE usertype_id = ?");
            $stmt->execute([$role_id]);
            $userCount = $stmt->fetchColumn();
            
            if ($userCount > 0) {
                $_SESSION['error'] = "Cannot delete role: $userCount user(s) are currently using this role";
            } else {
                // Get role name for logging
                $stmt = $pdo->prepare("SELECT role FROM user_type WHERE usertype_id = ?");
                $stmt->execute([$role_id]);
                $role = $stmt->fetch();
                
                // Delete role
                $stmt = $pdo->prepare("DELETE FROM user_type WHERE usertype_id = ?");
                $stmt->execute([$role_id]);
                
                $_SESSION['success'] = "Role '{$role['role']}' deleted successfully";
                logHistory($pdo, 'Role Deleted', "Deleted role: {$role['role']}", $_SESSION['username']);
            }
        } catch (Exception $e) {
            $_SESSION['error'] = "Error deleting role: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid admin password";
    }
    
    header("Location: user_permissions.php");
    exit;
}

// Handle permission updates
if (isset($_POST['update_permissions'])) {
    $user_id = (int)$_POST['user_id'];
    $selected_permissions = $_POST['permissions'] ?? [];
    $admin_password = $_POST['admin_password'];
    
    // Verify admin password
    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }
    
    if ($passwordOk) {
        try {
            $pdo->beginTransaction();
            
            // Remove all existing permissions for this user
            $stmt = $pdo->prepare("DELETE FROM user_permissions WHERE user_id = ?");
            $stmt->execute([$user_id]);
            
            // Add selected permissions
            if (!empty($selected_permissions)) {
                $stmt = $pdo->prepare("INSERT INTO user_permissions (user_id, permission_id) VALUES (?, ?)");
                foreach ($selected_permissions as $permission_id) {
                    $stmt->execute([$user_id, $permission_id]);
                }
            }
            
            $pdo->commit();
            $_SESSION['success'] = "User permissions updated successfully";
            
            // Log the action
            $stmt = $pdo->prepare("SELECT username FROM users WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $target_user = $stmt->fetch();
            logHistory($pdo, 'User Permissions Updated', "Updated permissions for user: {$target_user['username']}", $_SESSION['username']);
            
        } catch (Exception $e) {
            $pdo->rollBack();
            $_SESSION['error'] = "Error updating permissions: " . $e->getMessage();
        }
    } else {
        $_SESSION['error'] = "Invalid admin password";
    }
    
    header("Location: user_permissions.php");
    exit;
}

// Get all users with their current permissions (excluding customers)
$query = "SELECT u.user_id, u.username, ut.role, 
                 GROUP_CONCAT(p.permission_name) as user_permissions
          FROM users u
          LEFT JOIN user_type ut ON ut.usertype_id = u.usertype_id
          LEFT JOIN user_permissions up ON up.user_id = u.user_id
          LEFT JOIN permissions p ON p.permission_id = up.permission_id
          WHERE u.username != ? AND ut.role != 'customer'
          GROUP BY u.user_id, u.username, ut.role
          ORDER BY u.username";
$stmt = $pdo->prepare($query);
$stmt->execute([$_SESSION['username']]);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get all roles
$roles_query = "SELECT usertype_id, role, 
                       (SELECT COUNT(*) FROM users WHERE usertype_id = ut.usertype_id) as user_count
                FROM user_type ut
                ORDER BY role";
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
                        <h2><i class="fas fa-user-shield me-2"></i>User Permissions & Roles</h2>
                        <p class="mb-0 opacity-75">Manage user roles and individual permissions</p>
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
                            <h3 class="card-number"><?php echo count($users); ?></h3>
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
                            <h3 class="card-number"><?php echo count($permissions_by_module); ?></h3>
                            <p class="card-label">Permission Modules</p>
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
                                        <?php if ($role['user_count'] == 0 && !in_array($role['role'], ['super_admin', 'admin', 'customer'])): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="showDeleteRoleModal(<?php echo $role['usertype_id']; ?>, '<?php echo htmlspecialchars($role['role']); ?>')">
                                                <i class="fa fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                    <small class="text-info">
                                        <i class="fa fa-users me-1"></i>
                                        <?php echo $role['user_count']; ?> user(s) assigned
                                    </small>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Users List -->
            <div class="row g-4">
                <?php foreach ($users as $user): ?>
                    <div class="col-lg-6 col-xl-4">
                        <div class="user-card">
                            <div class="d-flex align-items-center mb-3">
                                <div class="user-avatar me-3" style="width: 50px; height: 50px; border-radius: 50%; background: #7F1734; display: flex; align-items: center; justify-content: center; color: white; font-size: 20px; font-weight: bold;">
                                    <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
                                </div>
                                <div class="flex-grow-1">
                                    <h5 class="mb-1 fw-bold"><?php echo htmlspecialchars($user['username']); ?></h5>
                                    <span class="badge bg-warning text-dark"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <h6 class="text-muted mb-2">Current Permissions:</h6>
                                <?php if (!empty($user['user_permissions'])): ?>
                                    <?php 
                                    $user_perms = explode(',', $user['user_permissions']);
                                    foreach ($user_perms as $perm): 
                                        if (trim($perm)):
                                    ?>
                                        <span class="permission-badge"><?php echo htmlspecialchars(trim($perm)); ?></span>
                                    <?php 
                                        endif;
                                    endforeach; 
                                    ?>
                                <?php else: ?>
                                    <span class="text-muted">No specific permissions assigned</span>
                                <?php endif; ?>
                            </div>
                            
                            <button class="btn btn-sm w-100" data-bs-toggle="modal" data-bs-target="#permissionModal<?php echo $user['user_id']; ?>" style="background-color: #7F1734; color: white; border: none;">
                                <i class="fa fa-edit me-1"></i> Manage Permissions
                            </button>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </main>

    <!-- Permission Modals -->
    <?php foreach ($users as $user): ?>
        <div class="modal fade" id="permissionModal<?php echo $user['user_id']; ?>" tabindex="-1">
            <div class="modal-dialog modal-lg">
                <form action="user_permissions.php" method="POST">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Manage Permissions for <?php echo htmlspecialchars($user['username']); ?></h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <input type="hidden" name="user_id" value="<?php echo $user['user_id']; ?>">
                            
                            <!-- Get current user permissions -->
                            <?php
                            $current_perms_stmt = $pdo->prepare("SELECT permission_id FROM user_permissions WHERE user_id = ?");
                            $current_perms_stmt->execute([$user['user_id']]);
                            $current_permissions = $current_perms_stmt->fetchAll(PDO::FETCH_COLUMN);
                            ?>
                            
                            <?php foreach ($permissions_by_module as $module => $permissions): ?>
                                <div class="permission-card">
                                    <div class="module-header">
                                        <h6 class="mb-0">
                                            <i class="fa fa-<?php echo getModuleIcon($module); ?> me-2"></i>
                                            <?php echo ucfirst($module); ?> Module
                                        </h6>
                                    </div>
                                    
                                    <?php foreach ($permissions as $permission): ?>
                                        <div class="permission-item">
                                            <div class="form-check">
                                                <input class="form-check-input" type="checkbox" 
                                                       name="permissions[]" 
                                                       value="<?php echo $permission['permission_id']; ?>"
                                                       id="perm_<?php echo $user['user_id']; ?>_<?php echo $permission['permission_id']; ?>"
                                                       <?php echo in_array($permission['permission_id'], $current_permissions) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="perm_<?php echo $user['user_id']; ?>_<?php echo $permission['permission_id']; ?>">
                                                    <strong><?php echo htmlspecialchars($permission['permission_name']); ?></strong>
                                                    <?php if (!empty($permission['description'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($permission['description']); ?></small>
                                                    <?php endif; ?>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                            
                            <div class="mb-3">
                                <label class="form-label">Admin Password (to confirm changes)</label>
                                <input type="password" class="form-control" name="admin_password" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="update_permissions" class="btn text-white fw-bold px-4" style="background-color: #7F1734; border-radius: 8px;">Update Permissions</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    <?php endforeach; ?>

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
                        <div class="mb-3">
                            <label class="form-label">Admin Password (to confirm)</label>
                            <input type="password" class="form-control" name="admin_password" required>
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
                        <div class="mb-3">
                            <label class="form-label">Admin Password (to confirm)</label>
                            <input type="password" class="form-control" name="admin_password" required>
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
        function showDeleteRoleModal(roleId, roleName) {
            document.getElementById('deleteRoleId').value = roleId;
            document.getElementById('deleteRoleName').textContent = roleName;
            new bootstrap.Modal(document.getElementById('deleteRoleModal')).show();
        }
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
