
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

    /* Light mode */
    --bg-primary: #f8f9fa;
    --bg-secondary: #ffffff;
    --text-primary: #212529;
    --text-secondary: #6c757d;
    --border-color: #e9ecef;
    --card-bg: #ffffff;
    --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
  }

  [data-theme="dark"] {
    --bg-primary: #1a1d23;
    --bg-secondary: #242831;
    --text-primary: #ffffff;
    --text-secondary: #a8b2c7;
    --border-color: #343a46;
    --card-bg: #2d3340;
    --card-shadow: 0 4px 16px rgba(0, 0, 0, 0.4);
    --light-bg: #1a1d23;
    --dark-text: #ffffff;
  }

  body {
    font-family: 'Inter', 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    background-color: var(--bg-primary);
    color: var(--text-primary);
    line-height: 1.6;
    padding-top: var(--navbar-height);
    transition: background-color 0.3s ease, color 0.3s ease;
  }

  /* ===== CARDS ===== */
  .stat-card,
  .metric-card,
  .table-card,
  .filter-card,
  .chart-container {
    background-color: var(--card-bg) !important;
    border: 1px solid var(--border-color) !important;
    border-radius: 16px !important;
    box-shadow: var(--card-shadow) !important;
    color: var(--text-primary) !important;
    transition: all 0.3s ease;
    padding: 20px;
  }
  .stat-card:hover,
  .metric-card:hover,
  .table-card:hover,
  .filter-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 18px rgba(0,0,0,0.12);
  }

  /* ===== BUTTONS ===== */
  .btn {
    border-radius: 10px !important;
    padding: 10px 18px;
    font-weight: 600;
    transition: all 0.3s ease;
  }
  .btn-primary {
    background: var(--primary-color) !important;
    border: none !important;
  }
  .btn-primary:hover {
    background: var(--secondary-color) !important;
    transform: translateY(-2px);
  }
  .btn-outline-primary {
    border: 2px solid var(--primary-color) !important;
    color: var(--primary-color) !important;
  }
  .btn-outline-primary:hover {
    background: var(--primary-color) !important;
    color: #fff !important;
  }

  /* ===== TABLES ===== */
  .table {
    background-color: var(--card-bg);
    color: var(--text-primary);
    border-radius: 12px;
    overflow: hidden;
  }
  .table th {
    background-color: var(--bg-secondary) !important;
    color: var(--text-primary);
    font-weight: 600;
    text-transform: uppercase;
    font-size: 13px;
    border-bottom: 2px solid var(--border-color);
    position: sticky;
    top: 0;
    z-index: 2;
  }
  .table tbody tr:nth-child(even) {
    background-color: rgba(0,0,0,0.02);
  }
  .table tbody tr:hover {
    background-color: rgba(127, 23, 52, 0.05);
    transition: background 0.2s;
  }

  /* ===== FORMS ===== */
  .form-control,
  .form-select {
    border-radius: 10px !important;
    border: 1px solid var(--border-color) !important;
    background-color: var(--card-bg) !important;
    color: var(--text-primary) !important;
    transition: all 0.3s ease;
  }
  .form-control:focus,
  .form-select:focus {
    border-color: var(--primary-color) !important;
    box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25) !important;
  }

  /* ===== CHARTS ===== */
  .chart-container {
    padding: 16px;
  }
  .chart-header {
    font-weight: 600;
    padding: 10px 15px;
    border-bottom: 1px solid var(--border-color);
  }

  /* ===== BADGES ===== */
  .badge {
    border-radius: 8px;
    font-weight: 500;
    padding: 6px 10px;
  }

  /* Smooth transitions */
  a, button, .nav-link {
    transition: all 0.3s ease;
  }
</style>
