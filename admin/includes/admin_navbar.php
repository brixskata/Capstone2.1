<?php
// Include permissions for role checking
include_once '../includes/permissions.php';

// Fetch user profile information
$user_info = null;
if (isset($_SESSION['username'])) {
    try {
        $stmt = $pdo->prepare("
            SELECT u.*, ui.first_name, ui.last_name, ui.profile_picture, ui.email
            FROM users u 
            LEFT JOIN user_info ui ON u.user_id = ui.user_id 
            WHERE u.username = ?
        ");
        $stmt->execute([$_SESSION['username']]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        // Fallback to basic info if query fails
        $user_info = ['username' => $_SESSION['username'], 'role' => $_SESSION['role'] ?? 'admin'];
    }
}

// Check if user is Super Admin
$is_super_admin = isSuperAdmin($pdo);

// Fetch dynamic notification counts
$notification_counts = [
    'notifications' => 0,
    'messages' => 0,
    'pending_verifications' => 0,
    'low_stock' => 0
];

try {
    // Count pending ID verifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM customer_id_verification WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['pending_verifications'] = $result['count'] ?? 0;
    
    // Count low stock items (assuming reorder_point is set)
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM product_stock ps 
                        JOIN products p ON ps.product_id = p.product_id 
                        WHERE ps.current_stock <= ps.reorder_point AND p.is_archived = 0");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['low_stock'] = $result['count'] ?? 0;
    
    // Count recent orders (last 24 hours) as notifications
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['notifications'] = $result['count'] ?? 0;
    
    // Count pending orders as messages
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM orders WHERE status IN ('Pending', 'Processing')");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $notification_counts['messages'] = $result['count'] ?? 0;
    
} catch (Exception $e) {
    // Keep default values if queries fail
    error_log("Error fetching notification counts: " . $e->getMessage());
}

// Set profile picture and display name
$profile_picture = 'uploads/admin-avatar.png';
$display_name = 'Admin';
$role_display = 'Administrator';

if ($user_info) {
    if (!empty($user_info['profile_picture'])) {
        $profile_picture = '../uploads/profile_pictures/' . $user_info['profile_picture'];
    }
    
    if (!empty($user_info['first_name']) && !empty($user_info['last_name'])) {
        $display_name = $user_info['first_name'] . ' ' . $user_info['last_name'];
    } else {
        $display_name = $user_info['username'];
    }
    
    // Set role display based on user role
    $user_role = $user_info['role'] ?? $_SESSION['role'] ?? 'admin';
    if ($is_super_admin) {
        $role_display = 'Super Administrator';
    } elseif ($user_role === 'admin') {
        $role_display = 'Administrator';
    } else {
        $role_display = ucfirst(str_replace('_', ' ', $user_role));
    }
}
?>

<!-- Enhanced Top Navbar -->
<nav class="top-navbar">
  <!-- Left Section -->
  <div class="navbar-left">
    <button class="sidebar-toggle-nav" id="sidebarToggleNav">
      <i class="fas fa-bars"></i>
    </button>
    <a href="<?php echo $is_super_admin ? 'admin_dashboard2.php' : 'basic_dashboard.php'; ?>" class="navbar-brand">
      <div class="brand-logo">
        <img src="../images/logo.png" alt="MikeMadz Logo" class="navbar-logo">
        <?php if ($is_super_admin): ?>
        <div class="logo-badge">
          <i class="fas fa-crown"></i>
        </div>
        <?php endif; ?>
      </div>
      <div class="brand-text">
        <span class="brand-name">MikeMadz</span>
        <span class="brand-subtitle">Frozen Product Store</span>
      </div>
    </a>
  </div>

  <!-- Center Section - Search & Quick Actions -->
  <div class="navbar-center">
    <div class="search-container">
      <i class="fas fa-search search-icon"></i>
      <input type="text" class="search-input" placeholder="Search products, orders, users...">
      <div class="search-shortcut">Ctrl+K</div>
    </div>
  </div>

  <!-- Right Section -->
  <div class="navbar-right">
    <!-- Quick Actions -->
    <div class="quick-actions">
      <button class="quick-action-btn" title="Recent Orders (24h)">
        <i class="fas fa-bell"></i>
        <?php if ($notification_counts['notifications'] > 0): ?>
        <span class="notification-badge"><?= $notification_counts['notifications'] ?></span>
        <?php endif; ?>
      </button>
      <button class="quick-action-btn" title="Pending Orders">
        <i class="fas fa-envelope"></i>
        <?php if ($notification_counts['messages'] > 0): ?>
        <span class="notification-badge"><?= $notification_counts['messages'] ?></span>
        <?php endif; ?>
      </button>
      <button class="quick-action-btn" title="Low Stock Items">
        <i class="fas fa-exclamation-triangle"></i>
        <?php if ($notification_counts['low_stock'] > 0): ?>
        <span class="notification-badge urgent"><?= $notification_counts['low_stock'] ?></span>
        <?php endif; ?>
      </button>
      <button class="quick-action-btn" title="Settings">
        <i class="fas fa-cog"></i>
      </button>
    </div>

    <!-- Admin Profile -->
    <div class="dropdown admin-profile-dropdown">
      <div class="admin-profile" data-bs-toggle="dropdown" role="button">
        <div class="profile-avatar">
          <img src="<?php echo $profile_picture; ?>" alt="Profile" class="admin-avatar" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzZjNzU3ZCIvPgo8cGF0aCBkPSJNOCAzMmMwLTYuNjI3IDUuMzczLTEyIDEyLTEyczEyIDUuMzczIDEyIDEyIiBmaWxsPSIjNmM3NTdkIi8+Cjwvc3ZnPgo='">
          <div class="status-indicator online"></div>
        </div>
        <div class="profile-info">
          <div class="profile-name"><?php echo htmlspecialchars($display_name); ?></div>
          <div class="profile-role"><?php echo $role_display; ?></div>
        </div>
        <i class="fas fa-chevron-down dropdown-arrow"></i>
      </div>
      
      <div class="dropdown-menu dropdown-menu-end profile-dropdown">
        <div class="dropdown-header">
          <div class="profile-summary">
            <img src="<?php echo $profile_picture; ?>" alt="Profile" class="profile-avatar-large" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAiIGhlaWdodD0iNDAiIHZpZXdCb3g9IjAgMCA0MCA0MCIgZmlsbD0ibm9uZSIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KPGNpcmNsZSBjeD0iMjAiIGN5PSIyMCIgcj0iMjAiIGZpbGw9IiNmOGY5ZmEiLz4KPGNpcmNsZSBjeD0iMjAiIGN5PSIxNiIgcj0iNiIgZmlsbD0iIzZjNzU3ZCIvPgo8cGF0aCBkPSJNOCAzMmMwLTYuNjI3IDUuMzczLTEyIDEyLTEyczEyIDUuMzczIDEyIDEyIiBmaWxsPSIjNmM3NTdkIi8+Cjwvc3ZnPgo='">
            <div class="profile-details">
              <div class="profile-name-large"><?php echo htmlspecialchars($display_name); ?></div>
              <div class="profile-email">@<?php echo htmlspecialchars($_SESSION['username']); ?></div>
              <div class="profile-status">
                <span class="status-dot online"></span>
                Online
              </div>
            </div>
          </div>
        </div>
        
        <div class="dropdown-divider"></div>
        
        <div class="dropdown-section">
          <div class="dropdown-section-title">Account</div>
          <a class="dropdown-item" href="profile_settings.php">
            <i class="fas fa-user"></i>
            <span>Profile Settings</span>
          </a>
      
        
       
       
        
        <div class="dropdown-divider"></div>
        
        <a class="dropdown-item logout-item" href="logout_admin.php">
          <i class="fas fa-sign-out-alt"></i>
          <span>Logout</span>
        </a>
      </div>
    </div>
  </div>
</nav>