<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MikeMadz - Admin Dashboard</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
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
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        [data-theme="dark"] {
            --bg-primary: #1a1d23;
            --bg-secondary: #242831;
            --text-primary: #ffffff;
            --text-secondary: #a8b2c7;
            --border-color: #343a46;
            --card-bg: #2d3340;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
            --light-bg: #1a1d23;
            --dark-text: #ffffff;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            padding-top: var(--navbar-height);
            transition: all 0.3s ease;
            overflow-x: hidden;
        }

        .dashboard-container {
            display: flex;
            min-height: calc(100vh - var(--navbar-height));
        }

        /* Top Navbar */
        .top-navbar {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            height: var(--navbar-height);
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            z-index: 1001;
            box-shadow: 0 4px 20px rgba(127, 23, 52, 0.2);
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 0 24px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
        }

        .navbar-brand {
            color: white;
            font-weight: 700;
            font-size: 24px;
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 12px;
            letter-spacing: -0.5px;
        }

        .navbar-brand i {
            font-size: 28px;
            color: rgba(255, 255, 255, 0.9);
        }

        .navbar-center {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .nav-quick-link {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 42px;
            height: 42px;
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: 12px;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-quick-link:hover {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
        }

        .admin-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            color: white;
            cursor: pointer;
            padding: 8px 16px;
            border-radius: 12px;
            transition: all 0.3s ease;
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .admin-profile:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        .admin-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ffffff 0%, #f0f0f0 100%);
            color: var(--primary-color);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 14px;
            border: 2px solid rgba(255, 255, 255, 0.3);
        }

        .dark-mode-toggle {
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            padding: 10px;
            border-radius: 12px;
            transition: all 0.3s ease;
            cursor: pointer;
            background-color: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .dark-mode-toggle:hover {
            background-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-1px);
        }

        /* Sidebar Styles */
        .sidebar {
            background: linear-gradient(180deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: calc(100vh - var(--navbar-height));
            position: fixed;
            top: var(--navbar-height);
            left: 0;
            width: var(--sidebar-width);
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(127, 23, 52, 0.15);
            transition: all 0.3s ease;
            overflow-x: hidden;
            overflow-y: auto;
        }

        .sidebar-header {
            padding: 24px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            margin-bottom: 20px;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
        }

        .sidebar-brand {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            color: white;
            font-size: 22px;
            font-weight: 700;
            margin-bottom: 8px;
            letter-spacing: -0.5px;
        }

        .sidebar-brand i {
            font-size: 26px;
        }

        .sidebar-subtitle {
            color: rgba(255, 255, 255, 0.7);
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 500;
        }

        .nav-section {
            margin-bottom: 28px;
        }

        .nav-section-title {
            color: rgba(255, 255, 255, 0.6);
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            padding: 0 24px 12px;
            margin-bottom: 8px;
        }

        .nav-item {
            margin: 0 16px 4px;
            border-radius: 12px;
            transition: all 0.3s ease;
            position: relative;
        }

        .nav-item.active {
            background: rgba(255, 255, 255, 0.2);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            backdrop-filter: blur(10px);
        }

        .nav-item.active::before {
            content: '';
            position: absolute;
            left: -16px;
            top: 50%;
            transform: translateY(-50%);
            width: 4px;
            height: 24px;
            background: white;
            border-radius: 0 2px 2px 0;
        }

        .nav-item:hover:not(.active) {
            background: rgba(255, 255, 255, 0.1);
            transform: translateX(4px);
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 20px;
            text-decoration: none;
            color: rgba(255, 255, 255, 0.9);
            font-weight: 500;
            font-size: 14px;
            transition: all 0.3s ease;
        }

        .nav-item.active .nav-link {
            color: white;
            font-weight: 600;
        }

        .nav-link i {
            width: 20px;
            margin-right: 12px;
            font-size: 16px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            margin-left: var(--sidebar-width);
            flex: 1;
            padding: 32px;
            background: var(--bg-primary);
            min-height: calc(100vh - var(--navbar-height));
            transition: all 0.3s ease;
        }

        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 32px;
            background: var(--card-bg);
            padding: 24px 32px;
            border-radius: 16px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
        }

        .page-title {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .page-title-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 20px;
        }

        .page-title h1 {
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            letter-spacing: -0.5px;
            margin: 0;
        }

        .breadcrumb-nav {
            color: var(--text-secondary);
            font-size: 14px;
            margin-top: 4px;
        }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-card:nth-child(1) .stat-icon { background: linear-gradient(135deg, var(--info-color), #0077be); }
        .stat-card:nth-child(2) .stat-icon { background: linear-gradient(135deg, var(--success-color), #0f5132); }
        .stat-card:nth-child(3) .stat-icon { background: linear-gradient(135deg, var(--primary-color), var(--secondary-color)); }
        .stat-card:nth-child(4) .stat-icon { background: linear-gradient(135deg, var(--warning-color), #cc9a00); }

        .stat-trend {
            font-size: 12px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(25, 135, 84, 0.1);
            color: var(--success-color);
        }

        .stat-number {
            font-size: 32px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
            letter-spacing: -1px;
            line-height: 1;
        }

        .stat-label {
            font-size: 14px;
            color: var(--text-secondary);
            font-weight: 500;
            margin-bottom: 12px;
        }

        .stat-description {
            font-size: 12px;
            color: var(--text-secondary);
            line-height: 1.4;
        }

        /* Charts Section */
        .charts-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 24px;
        }

        .chart-card {
            background: var(--card-bg);
            border-radius: 16px;
            padding: 28px;
            box-shadow: var(--card-shadow);
            border: 1px solid var(--border-color);
            position: relative;
        }

        .chart-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, var(--primary-color) 0%, var(--secondary-color) 100%);
        }

        .chart-header {
            display: flex;
            align-items: center;
            gap: 16px;
            margin-bottom: 28px;
            padding-bottom: 20px;
            border-bottom: 1px solid var(--border-color);
        }

        .chart-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            font-size: 18px;
        }

        .chart-title {
            font-size: 20px;
            font-weight: 600;
            color: var(--text-primary);
            margin: 0;
            letter-spacing: -0.3px;
        }

        .chart-subtitle {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }

        .chart-placeholder {
            height: 280px;
            background: linear-gradient(135deg, var(--bg-primary) 0%, var(--border-color) 100%);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--text-secondary);
            font-size: 16px;
            border: 2px dashed var(--border-color);
            position: relative;
            overflow: hidden;
        }

        .chart-placeholder::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(127, 23, 52, 0.1), transparent);
            animation: shimmer 2s infinite;
        }

        @keyframes shimmer {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .pie-chart-container {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 24px;
        }

        .pie-chart-placeholder {
            width: 200px;
            height: 200px;
            border-radius: 50%;
            background: conic-gradient(
                var(--primary-color) 0deg 144deg,
                var(--success-color) 144deg 216deg,
                var(--warning-color) 216deg 288deg,
                var(--info-color) 288deg 360deg
            );
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            box-shadow: 0 8px 24px rgba(0, 0, 0, 0.1);
        }

        .pie-chart-center {
            width: 100px;
            height: 100px;
            background: var(--card-bg);
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            border: 4px solid var(--card-bg);
        }

        .pie-center-value {
            font-size: 20px;
            font-weight: 700;
            color: var(--text-primary);
        }

        .pie-center-label {
            font-size: 12px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        .legend {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            width: 100%;
        }

        .legend-item {
            display: flex;
            align-items: center;
            gap: 12px;
            font-size: 13px;
            color: var(--text-primary);
            font-weight: 500;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        /* Theme Toggle Animation */
        .theme-icon {
            transition: all 0.3s ease;
            position: absolute;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            .charts-grid {
                grid-template-columns: 1fr;
            }
            .stats-grid {
                grid-template-columns: 1fr;
            }
            .page-header {
                padding: 20px;
            }
            .navbar-center {
                display: none;
            }
        }
    </style>
</head>
<body>
    <!-- Top Navbar -->
    <div class="top-navbar">
        <a href="#" class="navbar-brand">
            <i class="fas fa-snowflake"></i>
            <span>MikeMadz</span>
        </a>
        
        <div class="navbar-center">
            <a href="#" class="nav-quick-link" title="Orders">
                <i class="fas fa-shopping-cart"></i>
            </a>
            <a href="#" class="nav-quick-link" title="Products">
                <i class="fas fa-boxes"></i>
            </a>
            <a href="#" class="nav-quick-link" title="Reports">
                <i class="fas fa-chart-bar"></i>
            </a>
        </div>
        
        <div style="display: flex; align-items: center; gap: 12px;">
            <button class="dark-mode-toggle" onclick="toggleTheme()" title="Toggle Dark Mode">
                <i class="fas fa-sun theme-icon sun"></i>
                <i class="fas fa-moon theme-icon moon"></i>
            </button>
            <div class="admin-profile">
                <div class="admin-avatar">A</div>
                <div>
                    <div style="font-weight: 600; font-size: 14px;">Admin</div>
                    <div style="font-size: 12px; opacity: 0.8;">superadmin</div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="sidebar-header">
                <div class="sidebar-brand">
                    <i class="fas fa-snowflake"></i>
                    <span class="brand-text">MikeMadz</span>
                </div>
                <div class="sidebar-subtitle">Admin Panel</div>
            </div>
            
            <nav>
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <div class="nav-item active">
                        <a href="#" class="nav-link">
                            <i class="fas fa-chart-pie"></i>
                            <span>Dashboard</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-shopping-cart"></i>
                            <span>Transactions</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-boxes"></i>
                            <span>Inventory</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Management</div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-tools"></i>
                            <span>Maintenance</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-users"></i>
                            <span>User Accounts</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-user-shield"></i>
                            <span>User Permissions</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-drumstick-bite"></i>
                            <span>Products</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-truck"></i>
                            <span>Suppliers</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-section-title">Analytics</div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-chart-bar"></i>
                            <span>Reports</span>
                        </a>
                    </div>
                    <div class="nav-item">
                        <a href="#" class="nav-link">
                            <i class="fas fa-history"></i>
                            <span>Activity Log</span>
                        </a>
                    </div>
                </div>

                <div class="nav-section">
                    <div class="nav-item">
                        <a href="#" class="nav-link nav-link-danger">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </nav>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <div class="page-header">
                <div class="page-title">
                    <div class="page-title-icon">
                        <i class="fas fa-chart-pie"></i>
                    </div>
                    <div>
                        <h1>Dashboard</h1>
                        <div class="breadcrumb-nav">Home / Dashboard</div>
                    </div>
                </div>
            </div>

            <!-- Stats Grid -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="stat-trend">+0%</div>
                    </div>
                    <div class="stat-number">2</div>
                    <div class="stat-label">Active Products</div>
                    <div class="stat-description">Currently available frozen meat products in your inventory</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-peso-sign"></i>
                        </div>
                        <div class="stat-trend">+0%</div>
                    </div>
                    <div class="stat-number">₱0.00</div>
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-description">Total earnings from all completed transactions</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="stat-trend">+0%</div>
                    </div>
                    <div class="stat-number">4</div>
                    <div class="stat-label">Registered Users</div>
                    <div class="stat-description">Total number of customers registered on the platform</div>
                </div>

                <div class="stat-card">
                    <div class="stat-header">
                        <div class="stat-icon">
                            <i class="fas fa-warehouse"></i>
                        </div>
                        <div class="stat-trend">+0%</div>
                    </div>
                    <div class="stat-number">20</div>
                    <div class="stat-label">Items in Stock</div>
                    <div class="stat-description">Total quantity of frozen meat products available</div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="charts-grid">
                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-icon">
                            <i class="fas fa-chart-line"></i>
                        </div>
                        <div>
                            <div class="chart-title">Sales Trend Analysis</div>
                            <div class="chart-subtitle">Monthly revenue over the last 12 months</div>
                        </div>
                    </div>
                    <div class="chart-placeholder">
                        <div style="text-align: center;">
                            <i class="fas fa-chart-line" style="font-size: 48px; opacity: 0.3; margin-bottom: 16px;"></i>
                            <div style="font-weight: 500;">Sales Chart Data</div>
                        </div>
                    </div>
                </div>

                <div class="chart-card">
                    <div class="chart-header">
                        <div class="chart-icon">
                            <i class="fas fa-chart-pie"></i>
                        </div>
                        <div>
                            <div class="chart-title">Product Distribution</div>
                            <div class="chart-subtitle">Products by category</div>
                        </div>
                    </div>
                    <div class="pie-chart-container">
                        <div class="pie-chart-placeholder">
                            <div class="pie-chart-center">
                                <div class="pie-center-value">100%</div>
                                <div class="pie-center-label">Total</div>
                            </div>
                        </div>
                        <div class="legend">
                            <div class="legend-item">
                                <div class="legend-color" style="background: #7F1734;"></div>
                                <span>Beef (40%)</span>
                            </div>
                            <div class="legend-item">
                                <div class="legend-color" style="background: #198754;