
<style>
  :root {
    --primary-color: #7F1734;
    --secondary-color: #a91d42;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #0dcaf0;
    --light-bg: #f8f9fa;
    --dark-text: #212529;
    --sidebar-width: 280px;
    --sidebar-collapsed: 70px;
    --navbar-height: 70px;
    
    /* Light mode colors */
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #e9ecef;
    --card-bg: #ffffff;
    --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
  }
  
  [data-theme="dark"] {
    --bg-primary: #1a1d23;
    --bg-secondary: #242831;
    --text-primary: #ffffff;
    --text-secondary: #a8b2c7;
    --border-color: #343a46;
    --card-bg: #2d3340;
    --card-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
    --light-bg: #1a1d23;
    --dark-text: #ffffff;
  }
  
  body {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: var(--bg-primary);
    color: var(--text-primary);
    line-height: 1.6;
    padding-top: var(--navbar-height);
    transition: all 0.3s ease;
  }
  
  .top-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--navbar-height);
    background: #7F1734;
    z-index: 1001;
    box-shadow: 0 2px 10px rgba(127, 23, 52, 0.15);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 24px;
    transition: all 0.3s ease;
  }
  
  .navbar-brand {
    color: white;
    font-weight: 700;
    font-size: 24px;
    text-decoration: none;
  }
  
  .sidebar-toggle-nav {
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
  }
  
  .sidebar-toggle-nav:hover {
    background-color: rgba(255, 255, 255, 0.1);
  }
  
  .navbar-center {
    display: flex;
    align-items: center;
    gap: 8px;
  }
  
  .nav-quick-link {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 40px;
    height: 40px;
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
    text-decoration: none;
    border-radius: 8px;
    transition: all 0.3s ease;
  }
  
  .nav-quick-link:hover {
    background-color: rgba(255, 255, 255, 0.2);
    color: white;
    transform: translateY(-1px);
  }
  
  .nav-icon-btn {
    position: relative;
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
    cursor: pointer;
  }
  
  .nav-icon-btn:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
  }
  
  .dark-mode-toggle {
    position: relative;
    background: none;
    border: none;
    color: white;
    font-size: 18px;
    padding: 8px;
    border-radius: 6px;
    transition: all 0.3s ease;
    cursor: pointer;
  }
  
  .dark-mode-toggle:hover {
    background-color: rgba(255, 255, 255, 0.1);
    color: white;
  }
  
  .theme-icon {
    transition: all 0.3s ease;
  }
  
  [data-theme="dark"] .theme-icon.sun {
    opacity: 1;
    transform: rotate(0deg);
  }
  
  [data-theme="dark"] .theme-icon.moon {
    opacity: 0;
    transform: rotate(180deg);
  }
  
  .theme-icon.sun {
    opacity: 0;
    transform: rotate(-180deg);
  }
  
  .theme-icon.moon {
    opacity: 1;
    transform: rotate(0deg);
  }
  
  .notification-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background-color: #dc3545;
    color: white;
    font-size: 10px;
    font-weight: bold;
    padding: 2px 5px;
    border-radius: 10px;
    min-width: 18px;
    text-align: center;
  }
  
  .notification-dropdown {
    width: 280px;
    max-height: 400px;
    overflow-y: auto;
  }
  
  .admin-profile {
    display: flex;
    align-items: center;
    gap: 12px;
    color: white;
    cursor: pointer;
    padding: 8px 12px;
    border-radius: 8px;
    transition: all 0.3s ease;
  }
  
  .admin-profile:hover {
    background-color: rgba(255, 255, 255, 0.1);
  }
  
  .admin-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
  }
  
  .sidebar {
    background: #7F1734;
    min-height: calc(100vh - var(--navbar-height));
    position: fixed;
    top: var(--navbar-height);
    left: 0;
    width: var(--sidebar-width);
    z-index: 1000;
    box-shadow: 2px 0 10px rgba(127, 23, 52, 0.1);
    transition: all 0.3s ease;
    overflow-x: hidden;
    overflow-y: auto;
  }
  
  .sidebar.collapsed {
    width: var(--sidebar-collapsed);
  }
  
  .sidebar-toggle {
    position: absolute;
    top: 20px;
    right: -15px;
    background: #7F1734;
    color: white;
    border: none;
    border-radius: 50%;
    width: 30px;
    height: 30px;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
    transition: all 0.3s ease;
    z-index: 1002;
  }
  
  .sidebar-toggle:hover {
    background: var(--secondary-color);
    transform: scale(1.1);
  }
  
  .sidebar-header {
    padding: 24px 20px;
    text-align: center;
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    margin-bottom: 20px;
  }
  
  .sidebar-brand {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    color: white;
    font-size: 24px;
    font-weight: 700;
    margin-bottom: 8px;
  }
  
  .sidebar-brand i {
    font-size: 28px;
  }
  
  .sidebar-subtitle {
    color: rgba(255, 255, 255, 0.7);
    font-size: 12px;
    text-transform: uppercase;
    letter-spacing: 1px;
  }
  
  .sidebar.collapsed .sidebar-header {
    padding: 20px 10px;
  }
  
  .sidebar.collapsed .brand-text,
  .sidebar.collapsed .sidebar-subtitle {
    display: none;
  }
  
  .nav-section {
    margin-bottom: 32px;
  }
  
  .nav-section-title {
    color: rgba(255, 255, 255, 0.6);
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    padding: 0 24px 8px;
    margin-bottom: 8px;
  }
  
  .sidebar.collapsed .nav-section-title {
    display: none;
  }
  
  .nav-section-bottom {
    margin-top: auto;
    padding-top: 20px;
    border-top: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .sidebar .nav-link {
    position: relative;
    color: rgba(255, 255, 255, 0.85);
    padding: 12px 24px;
    margin: 2px 16px;
    border-radius: 10px;
    transition: all 0.3s ease;
    font-weight: 500;
    display: flex;
    align-items: center;
    text-decoration: none;
    white-space: nowrap;
  }
  
  .nav-indicator {
    position: absolute;
    left: -16px;
    top: 50%;
    transform: translateY(-50%);
    width: 4px;
    height: 0;
    background: white;
    border-radius: 0 2px 2px 0;
    transition: height 0.3s ease;
  }
  
  .sidebar .nav-link.active .nav-indicator {
    height: 24px;
  }
  
  .sidebar .nav-link i {
    min-width: 20px;
    margin-right: 12px;
    text-align: center;
    font-size: 16px;
  }
  
  .sidebar .nav-link:hover,
  .sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.15);
    color: white;
    transform: translateX(2px);
  }
  
  .nav-sublink {
    margin-left: 32px !important;
    padding-left: 16px !important;
    font-size: 14px;
  }
  
  .nav-sublink i {
    font-size: 14px;
  }
  
  .nav-dropdown-toggle .dropdown-arrow {
    margin-left: auto;
    transition: transform 0.3s ease;
  }
  
  .nav-dropdown-toggle[aria-expanded="true"] .dropdown-arrow {
    transform: rotate(180deg);
  }
  
  .nav-submenu {
    margin-top: 8px;
    padding-left: 0;
  }
  
  .nav-link-danger:hover {
    background-color: rgba(220, 53, 69, 0.2) !important;
    color: #ff6b6b !important;
  }
  
  .sidebar.collapsed .nav-link span {
    opacity: 0;
    width: 0;
  }
  
  .sidebar.collapsed .nav-link {
    padding: 12px;
    margin: 2px 8px;
    justify-content: center;
  }
  
  .sidebar.collapsed .nav-link i {
    margin-right: 0;
  }
  
  .sidebar.collapsed .nav-indicator {
    display: none;
  }
  
  .sidebar.collapsed .dropdown-arrow {
    display: none;
  }
  
  .sidebar.collapsed .nav-item .collapse {
    display: none !important;
  }
  
  .main-content {
    margin-left: var(--sidebar-width);
    padding: 32px;
    min-height: calc(100vh - var(--navbar-height));
    transition: all 0.3s ease;
  }
  
  .main-content.sidebar-collapsed {
    margin-left: var(--sidebar-collapsed);
  }
  
  /* Dark mode card and component styles */
  .stat-card,
  .metric-card,
  .table-card,
  .filter-card,
  .chart-container {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
    box-shadow: var(--card-shadow) !important;
    color: var(--text-primary) !important;
  }
  
  .table {
    background-color: var(--card-bg);
    color: var(--text-primary);
  }
  
  .table th {
    background-color: var(--bg-primary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
  }
  
  .table td {
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
  }
  
  .form-control,
  .form-select {
    background-color: var(--card-bg) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
  }
  
  .form-control:focus,
  .form-select:focus {
    background-color: var(--card-bg) !important;
    border-color: #7F1734 !important;
    color: var(--text-primary) !important;
    box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25) !important;
  }
  
  .text-muted {
    color: var(--text-secondary) !important;
  }
  
  .badge {
    border: 1px solid var(--border-color);
  }
  
  /* Chart containers */
  .chart-header {
    background: var(--bg-primary) !important;
    border-color: var(--border-color) !important;
    color: var(--text-primary) !important;
  }
</style>
