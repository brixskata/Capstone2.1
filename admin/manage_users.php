
<?php
include 'db.php';
include '../includes/log_history.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle role update
if (isset($_POST['update_role'])) {
    $user_id = $_POST['user_id'];
    $new_role = $_POST['role'];
    $admin_password = $_POST['admin_password'];

    // First verify admin password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['username']]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($admin_password, $admin['password'])) {
        try {
            $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ? AND username != ?");
            $stmt->execute([$new_role, $user_id, $_SESSION['username']]);
            $_SESSION['success'] = "User role updated successfully";
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
    $new_username = trim($_POST['new_username']);
    $new_email = trim($_POST['new_email']);
    $new_password = $_POST['new_password'];
    $new_role = $_POST['new_role'];
    $admin_password = $_POST['admin_password_create'];

    // Verify admin password
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['username']]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($admin_password, $admin['password'])) {
        // Check if username or email exists
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
        $stmt->execute([$new_username, $new_email]);
        if ($stmt->fetch()) {
            $_SESSION['error'] = "Username or email already exists.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, role, email_verified) VALUES (?, ?, ?, ?, 1)");
            $stmt->execute([$new_username, $new_email, $hashed_password, $new_role]);
            $_SESSION['success'] = "User created successfully.";
            logHistory($pdo, 'User Created', "Username: $new_username, Email: $new_email, Role: $new_role", $_SESSION['username']);
        }
    } else {
        $_SESSION['error'] = "Invalid admin password.";
    }
    header("Location: manage_users.php");
    exit;
}
// Handle user deactivation
if (isset($_POST['deactivate_user'])) {
    $user_id = $_POST['user_id'];
    $admin_password = $_POST['admin_password_deactivate'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['username']]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($admin_password, $admin['password'])) {
       
        $pdo->prepare("UPDATE users SET deactivated = 1 WHERE id = ? AND username != ?")->execute([$user_id, $_SESSION['username']]);
        $_SESSION['success'] = "User deactivated successfully.";
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
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
    $user_id = $_POST['user_id'];
    $admin_password = $_POST['admin_password_reactivate'];
    $stmt = $pdo->prepare("SELECT password FROM users WHERE username = ? AND role = 'admin'");
    $stmt->execute([$_SESSION['username']]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($admin_password, $admin['password'])) {
        $pdo->prepare("UPDATE users SET deactivated = 0 WHERE id = ? AND username != ?")->execute([$user_id, $_SESSION['username']]);
        $_SESSION['success'] = "User reactivated successfully.";
        $stmt = $pdo->prepare("SELECT username, email FROM users WHERE id = ?");
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

// Build the query
$query = "SELECT * FROM users WHERE username != ?";
$params = [$_SESSION['username']];

if ($search) {
    $query .= " AND (username LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}

if (!$show_deactivated) {
    $query .= " AND (deactivated IS NULL OR deactivated = 0)";
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
        background: linear-gradient(135deg, var(--bs-secondary) 0%, #a91d42 100%);
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
          <i class="fa fa-users text-primary me-3"></i>User Management
        </h1>
      </div>
      <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#createUserModal">
        <i class="fa fa-user-plus me-1"></i> Add User
      </button>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-4 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-info">
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
            <div class="stat-icon bg-warning">
              <i class="fa fa-shield-halved"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count(array_filter($users, function($user) { return $user['role'] === 'admin'; })); ?></h4>
              <small class="text-muted text-uppercase">Administrators</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-4 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon" style="background-color: var(--bs-secondary);">
              <i class="fa fa-user"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo count(array_filter($users, function($user) { return $user['role'] === 'customer'; })); ?></h4>
              <small class="text-muted text-uppercase">Customers</small>
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
              <option value="admin" <?php echo $role_filter === 'admin' ? 'selected' : ''; ?>>Admin</option>
              <option value="customer" <?php echo $role_filter === 'customer' ? 'selected' : ''; ?>>Customer</option>
            </select>
          </div>
          <div class="col-md-3">
            <div class="form-check mt-4">
              <input class="form-check-input" type="checkbox" name="show_deactivated" value="1" onchange="this.form.submit()" <?php if($show_deactivated) echo 'checked'; ?>>
              <label class="form-check-label">Show Deactivated</label>
            </div>
          </div>
          <div class="col-md-2">
            <button type="submit" class="btn btn-info w-100">
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
                    <span class="badge bg-danger ms-2">Deactivated</span>
                  <?php endif; ?>
                </h5>
                <small class="text-muted"><?php echo htmlspecialchars($user['email']); ?></small>
              </div>
            </div>
            
            <div class="d-flex flex-wrap gap-2">
              <?php if ($user['role'] !== 'admin' && empty($user['deactivated'])): ?>
                <select class="form-select form-select-sm" onchange="showPasswordModal(this.value, <?php echo $user['id']; ?>)">
                  <option value="customer" <?php echo $user['role'] === 'customer' ? 'selected' : ''; ?>>Customer</option>
                  <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                </select>
                <button type="button" onclick="showDeactivateModal(<?php echo $user['id']; ?>)" class="btn btn-danger btn-sm">
                  <i class="fa fa-user-slash me-1"></i> Deactivate
                </button>
              <?php elseif ($user['role'] !== 'admin' && !empty($user['deactivated'])): ?>
                <button type="button" onclick="showReactivateModal(<?php echo $user['id']; ?>)" class="btn btn-success btn-sm">
                  <i class="fa fa-user-check me-1"></i> Reactivate
                </button>
              <?php else: ?>
                <span class="badge bg-warning text-dark">Protected Admin</span>
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
            <button type="submit" class="btn btn-primary">Confirm Change</button>
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
                <option value="customer">Customer</option>
                <option value="admin">Admin</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Admin Password (to confirm)</label>
              <input type="password" class="form-control" name="admin_password_create" required>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" name="create_user" class="btn btn-success">Create User</button>
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
            <button type="submit" class="btn btn-danger">Deactivate</button>
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
            <button type="submit" class="btn btn-success">Reactivate</button>
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
  </script>
</body>
</html>
