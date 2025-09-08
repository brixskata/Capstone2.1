<?php
// Include permissions for module access checks
include_once '../includes/permissions.php';
?>

<style>
.sidebar {
  height: 100vh;
  display: flex;
  flex-direction: column;
  background: #7b1f2a; /* your maroon color */
  color: #fff;
}

/* Scrollable area */
.sidebar .nav {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-bottom: 10px;
}

/* Custom scrollbar */
.sidebar .nav::-webkit-scrollbar {
  width: 6px;
}
.sidebar .nav::-webkit-scrollbar-thumb {
  background-color: #888;
  border-radius: 4px;
}
.sidebar .nav::-webkit-scrollbar-thumb:hover {
  background-color: #555;
}
.sidebar .nav::-webkit-scrollbar-track {
  background: #7b1f2a;
}
.sidebar .nav {
  scrollbar-width: thin;
  scrollbar-color: #888 #7b1f2a;
}

/* Bottom logout section */
.nav-section-bottom {
  flex-shrink: 0;
  background: #6a1924;
  padding: 12px;
  border-top: 1px solid #444;
}
</style>

<!-- Modern Sidebar Navigation -->
<nav class="sidebar" id="sidebar">
  <!-- Scrollable Navigation Menu -->
  <div class="nav flex-column">
    <div class="nav-section">
      <div class="nav-section-title"><span>Main</span></div>
      <a href="admin_dashboard2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard2.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt"></i>
        <span>Dashboard</span>
      </a>
      <?php if (hasModuleAccess($pdo, 'transactions')): ?>
      <a href="transaction_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transaction_logs.php' ? 'active' : ''; ?>">
        <i class="fas fa-shopping-cart"></i>
        <span>Transactions</span>
      </a>
      <?php endif; ?>
      <?php if (hasModuleAccess($pdo, 'inventory')): ?>
      <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
        <i class="fas fa-warehouse"></i>
        <span>Inventory</span>
      </a>
      <?php endif; ?>
    </div>
    
    <!-- Maintenance Section -->
    <div class="nav-section">
      <div class="nav-section-title"><span>Management</span></div>
      <div class="nav-item">
        <a class="nav-link nav-dropdown-toggle" data-bs-toggle="collapse" href="#maintenanceMenu" role="button" aria-expanded="true">
          <i class="fas fa-cogs"></i>
          <span>Maintenance</span>
          <i class="fas fa-chevron-down dropdown-arrow"></i>
        </a>
        <div class="collapse show nav-submenu" id="maintenanceMenu">
          <?php if (hasModuleAccess($pdo, 'users')): ?>
          <a href="manage_users.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>User Accounts</span>
          </a>
          <?php endif; ?>
          <?php if (isSuperAdmin($pdo)): ?>
          <a href="user_permissions.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'user_permissions.php' ? 'active' : ''; ?>">
            <i class="fas fa-user-shield"></i>
            <span>User Permissions</span>
          </a>
          <?php endif; ?>
          <?php if (hasModuleAccess($pdo, 'products')): ?>
          <a href="products.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box"></i>
            <span>Products</span>
          </a>
          <?php endif; ?>
          <?php if (hasModuleAccess($pdo, 'suppliers')): ?>
          <a href="manage_suppliers.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'manage_suppliers.php' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i>
            <span>Suppliers</span>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <div class="nav-section">
      <div class="nav-section-title"><span>Analytics</span></div>
      <?php if (hasModuleAccess($pdo, 'reports')): ?>
      <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line"></i>
        <span>Reports</span>
      </a>
      <?php endif; ?>
      <a href="history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
        <i class="fas fa-history"></i>
        <span>Activity Log</span>
      </a>
    </div>
  </div>

  <!-- Fixed Bottom Logout -->
  <div class="nav-section nav-section-bottom">
    <a href="logout_admin.php" class="nav-link nav-link-danger">
      <i class="fas fa-sign-out-alt"></i>
      <span>Logout</span>
    </a>
  </div>
</nav>
