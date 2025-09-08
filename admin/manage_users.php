
<?php
include 'db.php';
include '../includes/log_history.php';
include '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin access (Super Admin or Admin)
requireAdmin($pdo);

// Helper: fetch admin password hash for current admin
function getAdminHash(PDO $pdo, string $username): ?string {
    $stmt = $pdo->prepare("SELECT u.password
                           FROM users u
                           LEFT JOIN user_type ut ON ut.usertype_id = u.usertype_id
                           WHERE u.username = ? AND (ut.role IN ('admin', 'super_admin') OR u.username IN ('admin','admin1'))");
    $stmt->execute([$username]);
    return $stmt->fetchColumn() ?: null;
}

// Map role string to usertype_id
function getUsertypeId(PDO $pdo, string $role): ?int {
    $stmt = $pdo->prepare("SELECT usertype_id FROM user_type WHERE role = ? LIMIT 1");
    $stmt->execute([$role]);
    $id = $stmt->fetchColumn();
    return $id !== false ? (int)$id : null;
}

// Handle role update
if (isset($_POST['update_role'])) {
    // Check if user has permission to update user roles
    if (!hasPermission($pdo, 'user_update')) {
        $_SESSION['error'] = "You don't have permission to update user roles.";
        header("Location: manage_users.php");
        exit;
    }

    $user_id = (int)$_POST['user_id'];
    $new_role = $_POST['role'];
    $admin_password = $_POST['admin_password'];

    // Super Admin can assign any role, Admin can only assign customer role
    if (!isSuperAdmin($pdo) && $new_role === 'super_admin') {
        $_SESSION['error'] = "Only Super Admin can assign Super Admin role.";
        header("Location: manage_users.php");
        exit;
    }

    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }

    if ($passwordOk) {
        try {
            $usertypeId = getUsertypeId($pdo, $new_role);
            if ($usertypeId === null) {
                $_SESSION['error'] = "Invalid role specified.";
            } else {
                $stmt = $pdo->prepare("UPDATE users SET usertype_id = ? WHERE user_id = ? AND username != ?");
                $stmt->execute([$usertypeId, $user_id, $_SESSION['username']]);
                $_SESSION['success'] = "User role updated successfully";
            }
        } catch (PDOException $e) {
            $_SESSION['error'] = "Error updating role";
        }
    } else {
        $_SESSION['error'] = "Invalid admin password";
    }
    header("Location: manage_users.php");
    exit;
}

// Handle user creation
if (isset($_POST['create_user'])) {
    // Check if user has permission to create users
    if (!hasPermission($pdo, 'user_create')) {
        $_SESSION['error'] = "You don't have permission to create users.";
        header("Location: manage_users.php");
        exit;
    }

    $new_username = trim($_POST['new_username']);
    $new_email = trim($_POST['new_email']);
    $new_password = $_POST['new_password'];
    $new_role = $_POST['new_role'];
    $admin_password = $_POST['admin_password_create'];

    // Super Admin can create any role, Admin can only create customer role
    if (!isSuperAdmin($pdo) && in_array($new_role, ['super_admin', 'admin'])) {
        $_SESSION['error'] = "Only Super Admin can create Admin or Super Admin users.";
        header("Location: manage_users.php");
        exit;
    }
    
    // Require Super Admin password for creating admin users
    if (in_array($new_role, ['super_admin', 'admin'])) {
        if (!isSuperAdmin($pdo)) {
            $_SESSION['error'] = "Only Super Admin can create Admin or Super Admin users.";
            header("Location: manage_users.php");
            exit;
        }
    }

    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }

    if ($passwordOk) {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT 1 FROM users WHERE username = ?");
        $stmt->execute([$new_username]);
        $existsUser = (bool)$stmt->fetch();
        $stmt = $pdo->prepare("SELECT 1 FROM user_info WHERE email = ?");
        $stmt->execute([$new_email]);
        $existsEmail = (bool)$stmt->fetch();
        if ($existsUser || $existsEmail) {
            $_SESSION['error'] = "Username or email already exists.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $usertypeId = getUsertypeId($pdo, $new_role);
            if ($usertypeId === null) {
                $_SESSION['error'] = "Invalid role specified.";
            } else {
                $pdo->beginTransaction();
                try {
                    $stmt = $pdo->prepare("INSERT INTO users (username, password, is_active, usertype_id) VALUES (?, ?, 1, ?)");
                    $stmt->execute([$new_username, $hashed_password, $usertypeId]);
                    $newUserId = (int)$pdo->lastInsertId();

                    $stmt = $pdo->prepare("INSERT INTO user_info (user_id, email) VALUES (?, ?)");
                    $stmt->execute([$newUserId, $new_email]);

                    $pdo->commit();
                    $_SESSION['success'] = "User created successfully.";
                    logHistory($pdo, 'User Created', "Username: $new_username, Email: $new_email, Role: $new_role", $_SESSION['username']);
                } catch (Exception $e) {
                    $pdo->rollBack();
                    $_SESSION['error'] = "Error creating user.";
                }
            }
        }
    } else {
        $_SESSION['error'] = "Invalid admin password.";
    }
    header("Location: manage_users.php");
    exit;
}
// Handle user deactivation
if (isset($_POST['deactivate_user'])) {
    // Check if user has permission to deactivate users
    if (!hasPermission($pdo, 'user_deactivate')) {
        $_SESSION['error'] = "You don't have permission to deactivate users.";
        header("Location: manage_users.php");
        exit;
    }

    $user_id = (int)$_POST['user_id'];
    $admin_password = $_POST['admin_password_deactivate'];
    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }
    if ($passwordOk) {
        $pdo->prepare("UPDATE users SET is_active = 0 WHERE user_id = ? AND username != ?")->execute([$user_id, $_SESSION['username']]);
        $_SESSION['success'] = "User deactivated successfully.";
        $stmt = $pdo->prepare("SELECT u.username, ui.email FROM users u LEFT JOIN user_info ui ON ui.user_id = u.user_id WHERE u.user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        logHistory($pdo, 'User Deactivated', "Username: {$user['username']}, Email: {$user['email']}", $_SESSION['username']);
    } else {
        $_SESSION['error'] = "Invalid admin password.";
    }
    header("Location: manage_users.php");
    exit;
}

// Handle user reactivation
if (isset($_POST['reactivate_user'])) {
    // Check if user has permission to reactivate users
    if (!hasPermission($pdo, 'user_deactivate')) {
        $_SESSION['error'] = "You don't have permission to reactivate users.";
        header("Location: manage_users.php");
        exit;
    }

    $user_id = (int)$_POST['user_id'];
    $admin_password = $_POST['admin_password_reactivate'];
    $adminHash = getAdminHash($pdo, $_SESSION['username']);
    $passwordOk = false;
    if ($adminHash) {
        $isHash = preg_match('/^(\$2[aby]\$|\$argon2)/', (string)$adminHash) === 1;
        $passwordOk = $isHash ? password_verify($admin_password, $adminHash) : hash_equals($adminHash, $admin_password);
    }
    if ($passwordOk) {
        $pdo->prepare("UPDATE users SET is_active = 1 WHERE user_id = ? AND username != ?")->execute([$user_id, $_SESSION['username']]);
        $_SESSION['success'] = "User reactivated successfully.";
        $stmt = $pdo->prepare("SELECT u.username, ui.email FROM users u LEFT JOIN user_info ui ON ui.user_id = u.user_id WHERE u.user_id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        logHistory($pdo, 'User Reactivated', "Username: {$user['username']}, Email: {$user['email']}", $_SESSION['username']);
    } else {
        $_SESSION['error'] = "Invalid admin password.";
    }
    header("Location: manage_users.php");
    exit;
}

// Search functionality
$search = isset($_GET['search']) ? $_GET['search'] : '';
$role_filter = isset($_GET['role_filter']) ? $_GET['role_filter'] : '';
$show_deactivated = isset($_GET['show_deactivated']) ? true : false;

// Build the query to list users
$query = "SELECT u.user_id AS id, u.username, ui.email, ut.role, (CASE WHEN u.is_active = 0 THEN 1 ELSE 0 END) AS deactivated
          FROM users u
          LEFT JOIN user_info ui ON ui.user_id = u.user_id
          LEFT JOIN user_type ut ON ut.usertype_id = u.usertype_id
          WHERE u.username != ?";
$params = [$_SESSION['username']];

if ($search) {
    $query .= " AND (u.username LIKE ? OR ui.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $query .= " AND ut.role = ?";
    $params[] = $role_filter;
}

if (!$show_deactivated) {
    $query .= " AND (u.is_active IS NULL OR u.is_active = 1)";
}

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Manage Users - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
      <style>
      .stat-card {
        background: white;
        border-radius: 12px;
        padding: 24px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
        transition: transform 0.2s ease;
      }
      
      .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
      }
      
      .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
      }
      
      .user-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
        border: 1px solid #e9ecef;
        transition: all 0.2s ease;
      }
      
      .user-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
      }
      
      .user-avatar {
        width: 60px;
        height: 60px;
        border-radius: 50%;
        background: #7F1734;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-size: 20px;
        font-weight: bold;
      }
      
      .deactivated {
        opacity: 0.6;
        background-color: #f8f9fa !important;
        border-left: 4px solid #dc3545;
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
          <i class="fa fa-users me-3" style="color: #7F1734;"></i>User Management
        </h1>
      </div>
      <?php if (hasPermission($pdo, 'user_create')): ?>
        <button class="btn" data-bs-toggle="modal" data-bs-target="#createUserModal" style="background-color: #198754; color: white; border: none;">
          <i class="fa fa-user-plus me-1"></i> Add User
        </button>
      <?php endif; ?>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon" style="background-color: #016bf8;">
              <i class="fa fa-users"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count($users); ?></h4>
              <small class="text-muted text-uppercase">Total Users</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon" style="background-color: #7F1734; color: white;">
              <i class="fa fa-user"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count(array_filter($users, function($user) { return ($user['role'] ?? '') === 'customer'; })); ?></h4>
              <small class="text-muted text-uppercase">Customers</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon" style="background-color: #016bf8;">
              <i class="fa fa-user-slash"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count(array_filter($users, function($user) { return !empty($user['deactivated']); })); ?></h4>
              <small class="text-muted text-uppercase">Deactivated</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Search and Filter -->
    <div class="card mb-4">
      <div class="card-body">
        <form method="GET" class="row g-3 align-items-end">
          <div class="col-md-4">
            <label class="form-label">Search Users</label>
            <div class="input-group">
              <span class="input-group-text"><i class="fa fa-search"></i></span>
              <input type="text" class="form-control" placeholder="Search by username or email" name="search" value="<?php echo htmlspecialchars($search); ?>">
            </div>
          </div>
          <div class="col-md-3">
            <label class="form-label">Role Filter</label>
            <select class="form-select" name="role_filter">
              <option value="">All Roles</option>
              <?php
              // Fetch all roles for filter dropdown
              $roles_query = "SELECT usertype_id, role FROM user_type ORDER BY role";
              $roles_stmt = $pdo->query($roles_query);
              $all_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
              
              foreach ($all_roles as $role): 
              ?>
                <option value="<?php echo htmlspecialchars($role['role']); ?>" 
                        <?php echo $role_filter === $role['role'] ? 'selected' : ''; ?>>
                  <?php echo ucfirst(str_replace('_', ' ', $role['role'])); ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-3">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="show_deactivated" value="1" onchange="this.form.submit()" <?php if($show_deactivated) echo 'checked'; ?>>
              <label class="form-check-label">Show Deactivated</label>
            </div>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn w-100" style="background-color: #016bf8; color: white; border: none;">
              <i class="fa fa-filter me-1"></i> Filter
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- Users Grid -->
    <div class="row g-4">
      <?php foreach ($users as $user): ?>
        <div class="col-lg-6 col-xl-4">
          <div class="user-card <?php if (!empty($user['deactivated'])) echo 'deactivated'; ?>">
            <div class="d-flex align-items-center mb-3">
              <div class="user-avatar me-3">
                <?php echo strtoupper(substr($user['username'], 0, 1)); ?>
              </div>
              <div class="flex-grow-1">
                <h5 class="mb-1 fw-bold">
                  <?php echo htmlspecialchars($user['username']); ?>
                  <?php if (!empty($user['deactivated'])): ?>
                    <span class="badge ms-2" style="background-color: #db3030; color: white;">Deactivated</span>
                  <?php endif; ?>
                  <?php if (!empty($user['role'])): ?>
                    <?php
                    $role_badge_style = 'background-color: #6c757d; color: white;';
                    if ($user['role'] === 'super_admin') $role_badge_style = 'background-color: #db3030; color: white;';
                    elseif ($user['role'] === 'admin') $role_badge_style = 'background-color: #ffc107; color: black;';
                    elseif ($user['role'] === 'customer') $role_badge_style = 'background-color: #016bf8; color: white;';
                    ?>
                    <span class="badge ms-2" style="<?php echo $role_badge_style; ?>"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                  <?php endif; ?>
                </h5>
                <small class="text-muted"><?php echo htmlspecialchars($user['email'] ?? ''); ?></small>
              </div>
            </div>
            
            <div class="d-flex flex-wrap gap-2">
              <?php if (($user['role'] ?? '') !== 'super_admin' && empty($user['deactivated'])): ?>
                <select class="form-select form-select-sm" onchange="showPasswordModal(this.value, <?php echo $user['id']; ?>)">
                  <?php
                  // Fetch all roles from database for role assignment
                  $roles_query = "SELECT usertype_id, role FROM user_type ORDER BY role";
                  $roles_stmt = $pdo->query($roles_query);
                  $all_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
                  
                  foreach ($all_roles as $role): 
                    // Super Admin can assign any role, Admin can only assign customer role
                    $canAssign = false;
                    if (isSuperAdmin($pdo)) {
                      $canAssign = true;
                    } elseif (isAdmin($pdo) && $role['role'] === 'customer') {
                      $canAssign = true;
                    }
                    
                    if ($canAssign):
                  ?>
                    <option value="<?php echo htmlspecialchars($role['role']); ?>" 
                            <?php echo ($user['role'] ?? '') === $role['role'] ? 'selected' : ''; ?>>
                      <?php echo ucfirst(str_replace('_', ' ', $role['role'])); ?>
                    </option>
                  <?php 
                    endif;
                  endforeach; 
                  ?>
                </select>
                <?php if (hasPermission($pdo, 'user_deactivate')): ?>
                  <button type="button" onclick="showDeactivateModal(<?php echo $user['id']; ?>)" class="btn btn-sm" style="background-color: #db3030; color: white; border: none;">
                    <i class="fa fa-user-slash me-1"></i> Deactivate
                  </button>
                <?php endif; ?>
              <?php elseif (($user['role'] ?? '') !== 'super_admin' && !empty($user['deactivated'])): ?>
                <?php if (hasPermission($pdo, 'user_deactivate')): ?>
                  <button type="button" onclick="showReactivateModal(<?php echo $user['id']; ?>)" class="btn btn-sm" style="background-color: #198754; color: white; border: none;">
                    <i class="fa fa-user-check me-1"></i> Reactivate
                  </button>
                <?php endif; ?>
              <?php else: ?>
                <span class="badge" style="background-color: #ffc107; color: black;">Protected Super Admin</span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <!-- Modals -->
  <!-- Password Verification Modal -->
  <div class="modal fade" id="passwordModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="manage_users.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Verify Admin Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="user_id" id="modalUserId">
            <input type="hidden" name="role" id="modalRole">
            <input type="hidden" name="update_role" value="1">
            <div class="mb-3">
              <label class="form-label">Enter your admin password to confirm role change</label>
              <input type="password" class="form-control" name="admin_password" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn" style="background-color: #7F1734; color: white; border: none;">Confirm Change</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Create User Modal -->
  <div class="modal fade" id="createUserModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="manage_users.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Create New User</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Username</label>
              <input type="text" class="form-control" name="new_username" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="new_email" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Password</label>
              <input type="password" class="form-control" name="new_password" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Role</label>
              <select class="form-select" name="new_role" required>
                <?php
                // Fetch all roles from database
                $roles_query = "SELECT usertype_id, role FROM user_type ORDER BY role";
                $roles_stmt = $pdo->query($roles_query);
                $all_roles = $roles_stmt->fetchAll(PDO::FETCH_ASSOC);
                
                foreach ($all_roles as $role): 
                  // Super Admin can assign any role, Admin can only assign customer role
                  $canAssign = false;
                  if (isSuperAdmin($pdo)) {
                    $canAssign = true;
                  } elseif (isAdmin($pdo) && $role['role'] === 'customer') {
                    $canAssign = true;
                  }
                  
                  if ($canAssign):
                ?>
                  <option value="<?php echo htmlspecialchars($role['role']); ?>">
                    <?php echo ucfirst(str_replace('_', ' ', $role['role'])); ?>
                  </option>
                <?php 
                  endif;
                endforeach; 
                ?>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label" id="adminPasswordLabel">Admin Password (to confirm)</label>
              <input type="password" class="form-control" name="admin_password_create" required>
              <small class="text-muted" id="adminPasswordHelp">Enter your admin password to confirm user creation</small>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create_user" class="btn" style="background-color: #198754; color: white; border: none;">Create User</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Deactivate User Modal -->
  <div class="modal fade" id="deactivateUserModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="manage_users.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Deactivation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="user_id" id="deactivateUserId">
            <input type="hidden" name="deactivate_user" value="1">
            <div class="mb-3">
              <label class="form-label">Enter your admin password to confirm deactivation</label>
              <input type="password" class="form-control" name="admin_password_deactivate" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn" style="background-color: #db3030; color: white; border: none;">Deactivate</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <!-- Reactivate User Modal -->
  <div class="modal fade" id="reactivateUserModal" tabindex="-1">
    <div class="modal-dialog">
      <form action="manage_users.php" method="POST">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title">Confirm Reactivation</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="user_id" id="reactivateUserId">
            <input type="hidden" name="reactivate_user" value="1">
            <div class="mb-3">
              <label class="form-label">Enter your admin password to confirm reactivation</label>
              <input type="password" class="form-control" name="admin_password_reactivate" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn" style="background-color: #198754; color: white; border: none;">Reactivate</button>
          </div>
        </div>
      </form>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  <script>
    function showPasswordModal(role, userId) {
      document.getElementById('modalUserId').value = userId;
      document.getElementById('modalRole').value = role;
      new bootstrap.Modal(document.getElementById('passwordModal')).show();
    }
    
    function showDeactivateModal(userId) {
      document.getElementById('deactivateUserId').value = userId;
      new bootstrap.Modal(document.getElementById('deactivateUserModal')).show();
    }
    
    function showReactivateModal(userId) {
      document.getElementById('reactivateUserId').value = userId;
      new bootstrap.Modal(document.getElementById('reactivateUserModal')).show();
    }
    
    // Update password label based on role selection
    document.addEventListener('DOMContentLoaded', function() {
      const roleSelect = document.querySelector('select[name="new_role"]');
      const passwordLabel = document.getElementById('adminPasswordLabel');
      const passwordHelp = document.getElementById('adminPasswordHelp');
      
      if (roleSelect) {
        roleSelect.addEventListener('change', function() {
          if (this.value === 'super_admin' || this.value === 'admin') {
            passwordLabel.textContent = 'Super Admin Password (required for admin users)';
            passwordHelp.textContent = 'Enter your Super Admin password to create admin users';
            passwordHelp.className = 'text-warning';
          } else {
            passwordLabel.textContent = 'Admin Password (to confirm)';
            passwordHelp.textContent = 'Enter your admin password to confirm user creation';
            passwordHelp.className = 'text-muted';
          }
        });
      }
    });
  </script>
</body>
</html>
