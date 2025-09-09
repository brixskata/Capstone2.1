<?php
// Include permissions for module access checks
include_once '../includes/permissions.php';
?>

<!-- Modern Sidebar Navigation -->
<nav class="sidebar fixed top-0 left-0 h-full d-flex flex-column p-4 overflow-y-auto" id="sidebar">
  <!-- Sidebar Header with Logo -->
  <div class="d-flex align-items-center mb-4">
    <a href="admin_dashboard2.php" class="text-white text-decoration-none d-flex align-items-center">
      <i class="fas fa-store me-2" style="font-size: 1.5rem; color: var(--primary-color);"></i>
      <span class="logo-text fs-4 d-flex align-items-center">MikeMadz</span>
    </a>
    <button class="btn ms-auto d-none d-lg-block text-white" id="sidebarToggle">
      <i class="fas fa-chevron-left" id="toggleIcon"></i>
    </button>
  </div>
 
  <!-- Navigation Menu -->
  <div class="nav flex-column">
    <div class="nav-section mb-4">
      <div class="nav-section-title text-uppercase fw-semibold mb-2"><span>Main</span></div>
      <a href="admin_dashboard2.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'admin_dashboard2.php' ? 'active' : ''; ?>">
        <i class="fas fa-tachometer-alt me-3"></i>
        <span>Dashboard</span>
        <div class="nav-indicator"></div>
      </a>
      <?php if (hasModuleAccess($pdo, 'transactions')): ?>
      <a href="transaction_logs.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'transaction_logs.php' ? 'active' : ''; ?>">
        <i class="fas fa-shopping-cart me-3"></i>
        <span>Transactions</span>
        <div class="nav-indicator"></div>
      </a>
      <?php endif; ?>
      <?php if (hasModuleAccess($pdo, 'inventory')): ?>
      <a href="inventory.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'inventory.php' ? 'active' : ''; ?>">
        <i class="fas fa-warehouse me-3"></i>
        <span>Inventory</span>
        <div class="nav-indicator"></div>
      </a>
      <?php endif; ?>
      <a href="#productSubmenu" data-bs-toggle="collapse" class="nav-link <?php echo in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'product_archive.php', 'manage_suppliers.php']) ? '' : 'collapsed'; ?>" aria-expanded="<?php echo in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'product_archive.php', 'manage_suppliers.php']) ? 'true' : 'false'; ?>">
        <i class="fas fa-box me-3"></i>
        <span>Products</span>
        <div class="nav-indicator"></div>
      </a>
      <div class="collapse <?php echo in_array(basename($_SERVER['PHP_SELF']), ['products.php', 'product_archive.php', 'manage_suppliers.php']) ? 'show' : ''; ?>" id="productSubmenu">
        <div class="d-flex flex-column gap-2 ps-4 pt-2 pb-2">
          <?php if (hasModuleAccess($pdo, 'products')): ?>
          <a href="products.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'products.php' ? 'active' : ''; ?>">
            <i class="fas fa-box-open"></i>
            <span>All Products</span>
            <div class="nav-indicator"></div>
          </a>
          <?php endif; ?>
          <?php if (hasModuleAccess($pdo, 'products_archive')): ?>
          <a href="product_archive.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'product_archive.php' ? 'active' : ''; ?>">
            <i class="fas fa-archive"></i>
            <span>Product Archive</span>
            <div class="nav-indicator"></div>
          </a>
          <?php endif; ?>
          <?php if (hasModuleAccess($pdo, 'suppliers')): ?>
          <a href="manage_suppliers.php" class="nav-link nav-sublink <?php echo basename($_SERVER['PHP_SELF']) == 'manage_suppliers.php' ? 'active' : ''; ?>">
            <i class="fas fa-truck"></i>
            <span>Suppliers</span>
            <div class="nav-indicator"></div>
          </a>
          <?php endif; ?>
        </div>
      </div>
    </div>
    
    <div class="nav-section mb-4">
      <div class="nav-section-title text-uppercase fw-semibold mb-2"><span>Analytics</span></div>
      <?php if (hasModuleAccess($pdo, 'reports')): ?>
      <a href="reports.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'reports.php' ? 'active' : ''; ?>">
        <i class="fas fa-chart-line me-3"></i>
        <span>Reports</span>
        <div class="nav-indicator"></div>
      </a>
      <?php endif; ?>
      <a href="history.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'history.php' ? 'active' : ''; ?>">
        <i class="fas fa-history me-3"></i>
        <span>Activity Log</span>
        <div class="nav-indicator"></div>
      </a>
    </div>
    
    <!-- Bottom Section -->
    <div class="nav-section mt-auto">
      <div class="nav-section-title text-uppercase fw-semibold mb-2"><span>Settings</span></div>
      <?php if (hasModuleAccess($pdo, 'users')): ?>
      <a href="manage_users.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'manage_users.php' ? 'active' : ''; ?>">
        <i class="fas fa-users-cog me-3"></i>
        <span>User Accounts</span>
        <div class="nav-indicator"></div>
      </a>
      <?php endif; ?>
      <a href="settings.php" class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'settings.php' ? 'active' : ''; ?>">
        <i class="fas fa-cogs me-3"></i>
        <span>Settings</span>
        <div class="nav-indicator"></div>
      </a>
    </div>
  </div>
</nav>
