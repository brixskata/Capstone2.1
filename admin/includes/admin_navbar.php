<!-- Top Navbar -->
<nav class="top-navbar shadow-sm">
  <div class="d-flex align-items-center">
    <!-- Sidebar Toggle -->
    <button class="sidebar-toggle-nav me-3" id="sidebarToggleNav">
      <i class="fas fa-bars"></i>
    </button>
    <!-- Brand -->
    <a href="admin_dashboard2.php" class="navbar-brand">
      <i class="fas fa-store me-2"></i><span class="d-none d-md-inline">MikeMadz</span>
    </a>
  </div>

  <!-- Center Navigation Quick Actions (Desktop) -->
  <div class="navbar-center d-none d-lg-flex">
    <a href="admin_dashboard2.php" class="nav-quick-link" title="Dashboard">
      <i class="fas fa-tachometer-alt"></i>
    </a>
    <a href="transaction_logs.php" class="nav-quick-link" title="Transactions">
      <i class="fas fa-shopping-cart"></i>
    </a>
    <a href="inventory.php" class="nav-quick-link" title="Inventory">
      <i class="fas fa-warehouse"></i>
    </a>
    <a href="products.php" class="nav-quick-link" title="Products">
      <i class="fas fa-box"></i>
    </a>
  </div>

  <!-- Right Side Items -->
  <div class="d-flex align-items-center gap-3">
    
    <!-- Notifications -->
    <div class="dropdown">
      <button class="nav-icon-btn position-relative" type="button" data-bs-toggle="dropdown">
        <i class="fas fa-bell"></i>
        <span class="notification-badge">3</span>
      </button>
      <div class="dropdown-menu dropdown-menu-end notification-dropdown p-0">
        <div class="dropdown-header fw-semibold bg-light">Notifications</div>
        <div class="list-group list-group-flush" style="max-height:300px; overflow-y:auto;">
          <a href="#" class="list-group-item list-group-item-action small">
            <i class="fas fa-exclamation-triangle text-warning me-2"></i> Low stock alert: 5 products
          </a>
          <a href="#" class="list-group-item list-group-item-action small">
            <i class="fas fa-shopping-cart text-info me-2"></i> 3 new orders pending
          </a>
          <a href="#" class="list-group-item list-group-item-action small">
            <i class="fas fa-user text-success me-2"></i> New user registered
          </a>
        </div>
      </div>
    </div>

    <!-- Settings -->
    <button class="nav-icon-btn" title="Settings">
      <i class="fas fa-cog"></i>
    </button>

    <!-- Admin Profile -->
    <div class="dropdown">
      <div class="admin-profile" data-bs-toggle="dropdown" role="button">
        <img src="uploads/admin-avatar.png" alt="Admin" class="admin-avatar"
             onerror="this.src='data:image/svg+xml;base64,...'">
        <div class="d-none d-md-block">
          <div class="fw-semibold">Admin</div>
          <small class="opacity-75"><?php echo htmlspecialchars($_SESSION['username']); ?></small>
        </div>
        <i class="fas fa-chevron-down ms-2 opacity-75"></i>
      </div>
      <div class="dropdown-menu dropdown-menu-end shadow-sm">
        <div class="dropdown-item-text d-flex align-items-center">
          <img src="uploads/admin-avatar.png" alt="Admin" class="admin-avatar me-2" style="width:32px;height:32px;"
               onerror="this.src='data:image/svg+xml;base64,...'">
          <div>
            <div class="fw-semibold"><?php echo htmlspecialchars($_SESSION['username']); ?></div>
            <small class="text-muted">Administrator</small>
          </div>
        </div>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item" href="#"><i class="fas fa-user me-2"></i> Profile Settings</a>
        <a class="dropdown-item" href="#"><i class="fas fa-cog me-2"></i> Account Settings</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-danger" href="logout_admin.php"><i class="fas fa-sign-out-alt me-2"></i> Logout</a>
      </div>
    </div>

    <!-- Dark Mode Toggle -->
    <button class="dark-mode-toggle" id="darkModeToggle" title="Toggle Dark Mode">
      <i class="fas fa-sun theme-icon sun"></i>
      <i class="fas fa-moon theme-icon moon"></i>
    </button>
  </div>
</nav>
