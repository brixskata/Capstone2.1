<!-- Top Navbar -->
<nav class="top-navbar bg-body-tertiary shadow-sm d-flex align-items-center justify-content-between px-4 py-3 sticky-top">
  <div class="d-flex align-items-center">
    <button class="nav-icon-btn d-lg-none" id="sidebarToggleNav">
      <i class="fas fa-bars"></i>
    </button>
    <a href="admin_dashboard2.php" class="navbar-brand d-flex align-items-center ms-3 ms-lg-0">
      <i class="fas fa-store me-2" style="color: var(--primary-color);"></i>
      <span class="d-none d-md-block logo-text">MikeMadz</span>
    </a>
  </div>

  <!-- Center Navigation Quick Actions -->
  <div class="d-none d-lg-flex align-items-center gap-4">
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
    <!-- Dark Mode Toggle -->
    <button class="nav-icon-btn d-none d-sm-block" id="darkModeToggle" title="Toggle Dark Mode">
        <i class="fas fa-moon"></i>
    </button>

    <!-- Notifications -->
    <div class="dropdown">
      <button class="nav-icon-btn position-relative" type="button" id="notificationDropdown" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
        <i class="fas fa-bell"></i>
        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.6rem; padding: 0.3em 0.5em;">
          3+
        </span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="notificationDropdown">
        <li><a class="dropdown-item" href="#">Notification 1</a></li>
        <li><a class="dropdown-item" href="#">Notification 2</a></li>
        <li><a class="dropdown-item" href="#">Notification 3</a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item text-center text-primary" href="#">View all</a></li>
      </ul>
    </div>
    
    <!-- User Profile Dropdown -->
    <div class="dropdown">
      <button class="btn p-0 border-0 d-flex align-items-center" type="button" id="userDropdown" data-bs-toggle="dropdown" aria-expanded="false">
          <img src="uploads/admin-avatar.png" alt="Admin" class="rounded-circle me-2" style="width: 38px; height: 38px;" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHdpZHRoPSIzOCIgaGVpZ2h0PSIzOCIgdmlld0JveD0iMCAwIDUwIDUwIj48Y2lyY2xlIGN4PSIyNSIgY3k9IjI1IiByPSIyNSIgZmlsbD0iI2Y4ZjlmYSJzdHJva2U9IiNlOWVjZWYiIHN0cm9rZS13aWR0aD0iMiIvPjxwYXRoIGQ9Ik0zMi41IDIzYzAtNC4xNC0zLjM2LTcuNS03LjUtNy41cy03LjUgMy4zNi03LjUgNy41IDcuNSAxNy41IDcuNSAxNy41LTcuNS0xMy4zNi03LjUtMTcuNTVhNy41IDcuNSAwIDEgMSAxNSAuMDV6IiBmaWxsPSIjNmM3NTdkIi8+PHBhdGggZD0iTTM5LjY0NyAzOC4xMDVDMzYuMjMgMzMuMTMgMzEuMjUgMzAgMjUuMTA3IDMwYy02LjExMyAwLTExLjA5IDMuMTMtMTQuMzU3IDguMTA1QzguNzg4IDQyLjEgMTMuNjY1IDQ1IDI1LjEwNyA0NXMxNi4zMTctMi45IDIzLjUyLTYuODk1IiBmaWxsPSIjNmM3NTdkIi8+PC9zdmc+'" loading="lazy">
          <span class="d-none d-md-block text-start user-info">
              <span class="fw-semibold d-block"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
              <small class="text-muted">Administrator</small>
          </span>
      </button>
      <ul class="dropdown-menu dropdown-menu-end shadow-sm" aria-labelledby="userDropdown">
        <li><h6 class="dropdown-header">Logged in as</h6></li>
        <li><a class="dropdown-item fw-semibold" href="#"><?php echo htmlspecialchars($_SESSION['username']); ?></a></li>
        <li><hr class="dropdown-divider"></li>
        <li><a class="dropdown-item" href="profile.php">Profile</a></li>
        <li><a class="dropdown-item" href="logout.php">Log Out</a></li>
      </ul>
    </div>
  </div>
</nav>