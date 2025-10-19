
<style>
  :root {
    --primary-color: #7F1734;
    --primary-light: #a91d42;
    --primary-dark: #5a0f25;
    --secondary-color: #a91d42;
    --success-color: #198754;
    --danger-color: #dc3545;
    --warning-color: #ffc107;
    --info-color: #0dcaf0;
    --light-bg: #f8f9fa;
    --dark-text: #212529;
    --sidebar-width: 260px;
    --sidebar-collapsed: 110px;
    --navbar-height: 64px;
    
    /* Light mode colors */
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #e9ecef;
    --card-bg: #ffffff;
    --card-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
    
    /* Minimalist spacing */
    --spacing-xs: 4px;
    --spacing-sm: 8px;
    --spacing-md: 16px;
    --spacing-lg: 24px;
    --spacing-xl: 32px;
    
    /* Border radius */
    --radius-sm: 6px;
    --radius-md: 8px;
    --radius-lg: 12px;
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
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
    background-color: var(--bg-primary);
    color: var(--text-primary);
    line-height: 1.5;
    padding-top: var(--navbar-height);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    font-size: 14px;
  }
  
  .top-navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: var(--navbar-height);
    background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
    z-index: 1001;
    box-shadow: 0 4px 20px rgba(127, 23, 52, 0.3);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--spacing-lg);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    backdrop-filter: blur(10px);
    border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  }
  
  .navbar-left {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
  }
  
  .navbar-center {
    flex: 1;
    max-width: 500px;
    margin: 0 var(--spacing-xl);
  }
  
  .navbar-right {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
  }
  
  .navbar-brand {
    color: white;
    font-weight: 600;
    font-size: 20px;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 12px;
    letter-spacing: -0.5px;
  }
  
  .navbar-logo {
    width: 65px;
    height: 65px;
    object-fit: contain;
    border-radius: var(--radius-md);
    background: rgba(255, 255, 255, 0.15);
    padding: 10px;
    border: 2px solid rgba(255, 255, 255, 0.2);
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s ease;
    filter: brightness(0) invert(1);
  }
  
  .navbar-logo:hover {
    transform: scale(1.05);
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.3);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
  
  .navbar-brand .brand-text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
  }
  
  .navbar-brand .brand-name {
    color: white;
    font-weight: 800;
    font-size: 22px;
    letter-spacing: 1px;
    text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
  }
  
  .navbar-brand .brand-subtitle {
    color: rgba(255, 255, 255, 0.8);
    font-size: 12px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1px;
    text-shadow: 0 1px 2px rgba(0, 0, 0, 0.1);
  }
  
  .sidebar-toggle-nav {
    background: none;
    border: none;
    color: white;
    font-size: 16px;
    padding: var(--spacing-sm);
    border-radius: var(--radius-sm);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    cursor: pointer;
  }
  
  .sidebar-toggle-nav:hover {
    background-color: rgba(255, 255, 255, 0.1);
    transform: scale(1.05);
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
    background: linear-gradient(180deg, var(--primary-color) 0%, var(--primary-dark) 100%);
    height: calc(100vh - var(--navbar-height));
    position: fixed;
    top: var(--navbar-height);
    left: 0;
    width: var(--sidebar-width);
    z-index: 1000;
    box-shadow: 2px 0 8px rgba(0, 0, 0, 0.1);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    overflow-x: hidden;
    overflow-y: auto;
    border-right: 1px solid rgba(255, 255, 255, 0.1);
    scrollbar-width: thin;
    scrollbar-color: rgba(255, 255, 255, 0.3) transparent;
  }
  
  .sidebar.collapsed {
    width: var(--sidebar-collapsed);
  }
  
  .sidebar.collapsed .nav {
    padding: 0 16px;
  }
  
  .sidebar.collapsed .nav-section {
    margin-bottom: 20px;
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
    margin-bottom: var(--spacing-xl);
  }
  
  .nav-section:first-child {
    margin-top: var(--spacing-md);
  }
  
  .nav-section-title {
    color: rgba(255, 255, 255, 0.5);
    font-size: 10px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    padding: var(--spacing-sm) var(--spacing-lg);
    margin: var(--spacing-sm) var(--spacing-md) var(--spacing-sm);
    display: flex;
    align-items: center;
    justify-content: space-between;
    cursor: pointer;
    transition: all 0.2s ease;
    border-radius: var(--radius-sm);
  }
  
  .nav-section-title:hover {
    color: rgba(255, 255, 255, 0.7);
    background-color: rgba(255, 255, 255, 0.05);
  }
  
  .dropdown-arrow {
    font-size: 8px;
    transition: transform 0.2s ease;
  }
  
  .nav-section-title[aria-expanded="false"] .dropdown-arrow {
    transform: rotate(-90deg);
  }
  
  .nav-dropdown {
    margin-left: var(--spacing-md);
    border-left: 1px solid rgba(255, 255, 255, 0.1);
    padding-left: var(--spacing-sm);
  }
  
  .nav-dropdown .nav-link {
    margin-left: 0;
    padding-left: var(--spacing-md);
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
    color: rgba(255, 255, 255, 0.8);
    padding: var(--spacing-sm) var(--spacing-lg);
    margin: 1px var(--spacing-md);
    border-radius: var(--radius-md);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    font-weight: 500;
    display: flex;
    align-items: center;
    text-decoration: none;
    white-space: nowrap;
    font-size: 14px;
  }
  
  .nav-indicator {
    position: absolute;
    left: calc(-var(--spacing-md) - 4px);
    top: 50%;
    transform: translateY(-50%);
    width: 3px;
    height: 0;
    background: white;
    border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
    transition: height 0.2s cubic-bezier(0.4, 0, 0.2, 1);
    display: none;
  }
  
  .sidebar .nav-link.active .nav-indicator {
    height: 0;
  }
  
  .sidebar .nav-link i {
    min-width: 18px;
    margin-right: var(--spacing-sm);
    text-align: center;
    font-size: 14px;
  }
  
  .sidebar .nav-link:hover,
  .sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.12);
    color: white;
    transform: translateX(1px);
  }
   
  .sidebar .nav-link.active {
    background-color: rgba(255, 255, 255, 0.15);
    font-weight: 600;
  }
  
  .nav-sublink {
    margin-left: var(--spacing-xl) !important;
    padding-left: var(--spacing-md) !important;
    font-size: 13px;
    opacity: 0.9;
  }
  
  .nav-sublink i {
    font-size: 13px;
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
  
  .nav-subsection {
    margin-bottom: 20px;
  }
  
  .nav-subsection-title {
    font-size: 9px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.3px;
    color: rgba(255, 255, 255, 0.4);
    margin: 0 0 var(--spacing-sm) var(--spacing-lg);
    padding: var(--spacing-sm) 0;
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
    padding: 18px 12px;
    margin: 6px 16px;
    justify-content: center;
    min-height: 56px;
    display: flex;
    align-items: center;
    border-radius: 10px;
  }
  
  .sidebar.collapsed .nav-link i {
    margin-right: 0;
    font-size: 20px;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
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
    padding: var(--spacing-xl);
    min-height: calc(100vh - var(--navbar-height));
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    background-color: var(--bg-primary);
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
  
  /* Minimalist enhancements */
  .admin-profile {
    padding: var(--spacing-sm) var(--spacing-sm);
    border-radius: var(--radius-md);
    transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
  }
  
  .admin-profile:hover {
    background-color: rgba(255, 255, 255, 0.1);
  }
  
  .admin-avatar {
    width: 32px;
    height: 32px;
    border: 1px solid rgba(255, 255, 255, 0.2);
  }
  
  
  .dropdown-menu {
    border: none;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    border-radius: var(--radius-md);
    padding: var(--spacing-sm);
  }
  
  .dropdown-item {
    border-radius: var(--radius-sm);
    padding: var(--spacing-sm) var(--spacing-md);
    transition: all 0.2s ease;
  }
  
  .dropdown-item:hover {
    background-color: var(--bg-primary);
  }
  
  /* Enhanced Scrollbar styling */
  .sidebar::-webkit-scrollbar {
    width: 6px;
  }
  
  .sidebar::-webkit-scrollbar-track {
    background: transparent;
  }
  
  .sidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.3);
    border-radius: 3px;
    transition: background 0.2s ease;
  }
  
  .sidebar::-webkit-scrollbar-thumb:hover {
    background: rgba(255, 255, 255, 0.5);
  }
  
  .sidebar::-webkit-scrollbar-thumb:active {
    background: rgba(255, 255, 255, 0.7);
  }

  /* Enhanced Navbar Styles */
  .brand-logo {
    position: relative;
    display: flex;
    align-items: center;
  }
  
  .logo-badge {
    position: absolute;
    top: 2px;
    right: 2px;
    background: linear-gradient(135deg, #ffc107 0%, #ff8c00 100%);
    color: #7F1734;
    border-radius: 50%;
    width: 16px;
    height: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 8px;
    font-weight: bold;
    border: 2px solid white;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
    animation: pulse-glow 2s infinite;
  }
  
  @keyframes pulse-glow {
    0% { 
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
      transform: scale(1);
    }
    50% { 
      box-shadow: 0 4px 12px rgba(255, 193, 7, 0.4);
      transform: scale(1.1);
    }
    100% { 
      box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
      transform: scale(1);
    }
  }
  
  .search-container {
    position: relative;
    width: 100%;
  }
  
  .search-input {
    width: 100%;
    padding: 8px 16px 8px 40px;
    border: 1px solid rgba(255, 255, 255, 0.2);
    border-radius: 20px;
    background: rgba(255, 255, 255, 0.1);
    color: white;
    font-size: 14px;
    transition: all 0.3s ease;
  }
  
  .search-input::placeholder {
    color: rgba(255, 255, 255, 0.7);
  }
  
  .search-input:focus {
    outline: none;
    background: rgba(255, 255, 255, 0.2);
    border-color: rgba(255, 255, 255, 0.4);
    box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.1);
  }
  
  .search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: rgba(255, 255, 255, 0.7);
    font-size: 14px;
  }
  
  .search-shortcut {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: rgba(255, 255, 255, 0.1);
    color: rgba(255, 255, 255, 0.7);
    padding: 2px 6px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 500;
  }
  
  .quick-actions {
    display: flex;
    align-items: center;
    gap: var(--spacing-sm);
  }
  
  .quick-action-btn {
    position: relative;
    background: none;
    border: none;
    color: rgba(255, 255, 255, 0.8);
    padding: 8px;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 16px;
  }
  
  .quick-action-btn:hover {
    background: rgba(255, 255, 255, 0.1);
    color: white;
  }
  
  .notification-badge {
    position: absolute;
    top: -2px;
    right: -2px;
    background: #dc3545;
    color: white;
    border-radius: 50%;
    width: 18px;
    height: 18px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 10px;
    font-weight: bold;
  }
  
  .profile-avatar {
    position: relative;
  }
  
  .status-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    border: 2px solid white;
  }
  
  .status-indicator.online {
    background: #28a745;
  }
  
  .profile-info {
    display: flex;
    flex-direction: column;
    margin-left: var(--spacing-sm);
  }
  
  .profile-name {
    font-weight: 600;
    font-size: 14px;
    color: white;
  }
  
  .profile-role {
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
  }
  
  .dropdown-arrow {
    margin-left: var(--spacing-sm);
    color: rgba(255, 255, 255, 0.7);
    font-size: 12px;
    transition: transform 0.2s ease;
  }
  
  .admin-profile-dropdown .dropdown-menu.show .dropdown-arrow {
    transform: rotate(180deg);
  }
  
  .profile-dropdown {
    min-width: 280px;
    padding: 0;
    border: none;
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
    border-radius: 12px;
    overflow: hidden;
  }
  
  .dropdown-header {
    background: linear-gradient(135deg, #7F1734 0%, #a91d42 100%);
    padding: var(--spacing-lg);
    color: white;
  }
  
  .profile-summary {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
  }
  
  .profile-avatar-large {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    object-fit: cover;
    border: 2px solid rgba(255, 255, 255, 0.3);
  }
  
  .profile-details {
    flex: 1;
  }
  
  .profile-name-large {
    font-weight: 600;
    font-size: 16px;
    margin-bottom: 2px;
  }
  
  .profile-email {
    font-size: 14px;
    color: rgba(255, 255, 255, 0.8);
    margin-bottom: 4px;
  }
  
  .profile-status {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.7);
  }
  
  .status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
  }
  
  .status-dot.online {
    background: #28a745;
  }
  
  .dropdown-section {
    padding: var(--spacing-sm) 0;
  }
  
  .dropdown-section-title {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #6c757d;
    padding: 0 var(--spacing-md) var(--spacing-sm);
    margin-bottom: var(--spacing-sm);
  }
  
  .dropdown-item {
    display: flex;
    align-items: center;
    gap: var(--spacing-md);
    padding: var(--spacing-sm) var(--spacing-md);
    color: #495057;
    text-decoration: none;
    transition: all 0.2s ease;
    font-size: 14px;
  }
  
  .dropdown-item:hover {
    background: #f8f9fa;
    color: #7F1734;
  }
  
  .dropdown-item i {
    width: 16px;
    text-align: center;
    color: #6c757d;
  }
  
  .dropdown-item:hover i {
    color: #7F1734;
  }
  
  .logout-item {
    color: #dc3545 !important;
  }
  
  .logout-item:hover {
    background: #f8d7da !important;
    color: #721c24 !important;
  }
  
  .logout-item i {
    color: #dc3545 !important;
  }

  /* Enhanced Sidebar Styles */
  
  .section-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 12px;
    color: rgba(255, 255, 255, 0.6);
  }
  
  .nav-icon {
    width: 20px;
    height: 20px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
    margin-right: var(--spacing-sm);
  }
  
  .nav-badge {
    background: rgba(255, 255, 255, 0.2);
    color: white;
    padding: 2px 6px;
    border-radius: 10px;
    font-size: 10px;
    font-weight: 600;
    margin-left: auto;
  }
  
  .nav-badge.urgent {
    background: #dc3545;
    animation: pulse 2s infinite;
  }
  
  @keyframes pulse {
    0% { opacity: 1; }
    50% { opacity: 0.7; }
    100% { opacity: 1; }
  }
  
  .sidebar.collapsed .brand-text {
    display: none;
  }
  
  .sidebar.collapsed .section-icon {
    display: none;
  }
  
  .sidebar.collapsed .nav-icon {
    margin-right: 0;
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 20px;
  }
  
  .sidebar.collapsed .nav-badge {
    display: none;
  }
</style>
