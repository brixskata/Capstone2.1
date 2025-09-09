<style>
  :root {
    --primary-color: #7F1734;
    --secondary-color: #a91d42;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #0dcaf0;
    
    /* Modern Light mode colors */
    --bg-primary: #f3f4f6;
    --bg-secondary: #ffffff;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #e9ecef;
    --card-bg: #ffffff;
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.06);
    --border-radius-base: 12px;
    --transition-speed: 0.3s;
  }
  
  [data-theme="dark"] {
    /* Modern Dark mode colors */
    --bg-primary: #121212;
    --bg-secondary: #1e1e1e;
    --text-primary: #e0e0e0;
    --text-secondary: #a0a0a0;
    --border-color: #333333;
    --card-bg: #1e1e1e;
    --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
  }

  body {
    font-family: 'Inter', sans-serif;
    background-color: var(--bg-primary);
    color: var(--text-primary);
    transition: background-color var(--transition-speed), color var(--transition-speed);
  }

  /* Core layout and animation styles */
  .sidebar {
    width: var(--sidebar-width);
    background: var(--bg-secondary);
    transition: width var(--transition-speed), transform var(--transition-speed), background var(--transition-speed);
    box-shadow: var(--card-shadow);
    z-index: 1000;
  }

  .sidebar.collapsed {
    width: var(--sidebar-collapsed);
  }

  .main-content {
    margin-left: var(--sidebar-width);
    transition: margin-left var(--transition-speed);
  }

  .main-content.sidebar-collapsed {
    margin-left: var(--sidebar-collapsed);
  }
  
  /* Frosted Glass Effect for Sidebar (modern design element) */
  .sidebar {
    background: rgba(255, 255, 255, 0.4);
    backdrop-filter: blur(10px);
    border-right: 1px solid rgba(0, 0, 0, 0.1);
  }

  [data-theme="dark"] .sidebar {
    background: rgba(30, 30, 30, 0.5);
    border-right: 1px solid rgba(255, 255, 255, 0.1);
  }

  /* General component styling */
  .top-navbar,
  .stat-card,
  .metric-card,
  .table-card,
  .filter-card,
  .chart-container {
    background-color: var(--card-bg);
    border-radius: var(--border-radius-base);
    box-shadow: var(--card-shadow);
    color: var(--text-primary);
    transition: background-color var(--transition-speed), color var(--transition-speed), box-shadow var(--transition-speed);
  }
  
  .stat-card, .metric-card {
    border: none;
  }

  /* Table styling */
  .table {
    background-color: transparent; /* No background, so it blends with the card */
    color: var(--text-primary);
  }
  
  .table th {
    background-color: transparent;
    border-color: var(--border-color);
    color: var(--text-secondary);
  }
  
  .table td {
    border-color: var(--border-color);
    color: var(--text-primary);
  }
  
  .form-control,
  .form-select {
    background-color: var(--card-bg);
    border: 1px solid var(--border-color);
    color: var(--text-primary);
    border-radius: var(--border-radius-base);
  }
  
  .form-control:focus,
  .form-select:focus {
    background-color: var(--card-bg);
    border-color: var(--primary-color);
    box-shadow: 0 0 0 0.25rem rgba(127, 23, 52, 0.25);
    color: var(--text-primary);
  }

  /* Modern button styling */
  .btn-primary {
    background-color: var(--primary-color);
    color: #fff;
    border: none;
    border-radius: var(--border-radius-base);
    transition: background-color var(--transition-speed), transform 0.2s;
  }
  
  .btn-primary:hover {
    background-color: var(--secondary-color);
    transform: translateY(-2px);
  }

  /* Modern navigation links */
  .nav-link {
    display: flex;
    align-items: center;
    padding: 12px 16px;
    border-radius: var(--border-radius-base);
    color: var(--text-primary);
    transition: background-color var(--transition-speed), color var(--transition-speed), transform 0.2s;
  }

  .nav-link:hover, .nav-sublink:hover {
    background-color: rgba(127, 23, 52, 0.1);
    color: var(--primary-color);
    transform: translateX(4px);
  }
  
  .nav-link.active, .nav-sublink.active {
    background-color: var(--primary-color);
    color: #fff;
    font-weight: 600;
  }

  .nav-link.active .nav-indicator, .nav-sublink.active .nav-indicator {
    background-color: white;
  }
  
  .nav-indicator {
    width: 4px;
    height: 100%;
    background-color: transparent;
    border-radius: 2px;
    margin-left: auto;
    transition: background-color var(--transition-speed);
  }

  .nav-quick-link {
    font-size: 1.25rem;
    color: var(--text-secondary);
    transition: color var(--transition-speed), transform 0.2s;
  }

  .nav-quick-link:hover {
    color: var(--primary-color);
    transform: translateY(-2px);
  }

  .nav-icon-btn {
    color: var(--text-primary);
    transition: color var(--transition-speed), transform 0.2s;
  }

  .nav-icon-btn:hover {
    color: var(--primary-color);
    transform: translateY(-2px);
  }

  .dropdown-menu {
    border-radius: var(--border-radius-base);
    box-shadow: var(--card-shadow);
  }
  
  /* Additional modern touches */
  .logo-text {
    font-weight: 700;
  }
  
  .user-info {
    font-size: 0.9rem;
  }
  .user-info .fw-semibold {
    font-weight: 600;
  }
</style>