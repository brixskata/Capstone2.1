<?php
session_start();
include '../includes/db.php';

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Helper function to get date ranges
date_default_timezone_set('Asia/Manila');
function getDateRange($type) {
    $today = date('Y-m-d');
    switch ($type) {
        case 'daily':
            return [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
        case 'weekly':
            $start = date('Y-m-d 00:00:00', strtotime('monday this week'));
            $end = date('Y-m-d 23:59:59', strtotime('sunday this week'));
            return [$start, $end];
        case 'monthly':
            $start = date('Y-m-01 00:00:00');
            $end = date('Y-m-t 23:59:59');
            return [$start, $end];
        case 'yearly':
            $start = date('Y-01-01 00:00:00');
            $end = date('Y-12-31 23:59:59');
            return [$start, $end];
        default:
            return [null, null];
    }
}

// Get report type from query string with validation
$reportType = $_GET['type'] ?? 'sales';
$period = $_GET['period'] ?? 'daily';

// Validate report type
$validTypes = ['sales', 'inventory', 'orders', 'returns'];
if (!in_array($reportType, $validTypes)) {
    $reportType = 'sales';
}

// Validate period
$validPeriods = ['daily', 'weekly', 'monthly', 'yearly'];
if (!in_array($period, $validPeriods)) {
    $period = 'daily';
}

// Get basic stats for overview with error handling
try {
    $totalSales = $pdo->query("
        SELECT COALESCE(SUM(o.total_price), 0) 
        FROM orders o
        INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
        WHERE os.status_name = 'Completed'
    ")->fetchColumn();
    
    $totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
    
    $totalCustomers = $pdo->query("
        SELECT COUNT(*) 
        FROM users u
        LEFT JOIN user_type ut ON u.usertype_id = ut.usertype_id
        WHERE (ut.role IS NULL OR ut.role != 'admin') 
        AND u.username NOT IN ('admin', 'admin1')
    ")->fetchColumn();
    
    $totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archive = 0")->fetchColumn();
} catch (Exception $e) {
    $totalSales = 0;
    $totalOrders = 0;
    $totalCustomers = 0;
    $totalProducts = 0;
    $error_message = "Error loading statistics: " . $e->getMessage();
}

// Fetch data for reports
$salesData = [];
$inventoryData = [];
$returnData = [];
$ordersData = [];

if ($reportType === 'sales') {
    list($start, $end) = getDateRange($period);
    $stmt = $pdo->prepare("
        SELECT o.orders_id as id, u.username as customer, o.total_price, o.created_at as delivered_at
        FROM orders o
        INNER JOIN users u ON o.user_id = u.user_id
        INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
        WHERE os.status_name = 'Completed' AND o.created_at BETWEEN ? AND ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$start, $end]);
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($reportType === 'inventory') {
    $stmt = $pdo->query("
        SELECT 
            p.product_id as id,
            p.product_name as name,
            COALESCE(ps.current_stock, 0) as stock,
            COALESCE(pp.selling_price, 0) as price,
            p.is_archive as is_archived
        FROM products p
        LEFT JOIN product_stock ps ON p.product_id = ps.product_id
        LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
        WHERE p.is_archive = 0
        ORDER BY p.product_name ASC
    ");
    $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($reportType === 'returns') {
    // Returns functionality - create empty array since returns table doesn't exist yet
    $returnData = [];
} elseif ($reportType === 'orders') {
    list($start, $end) = getDateRange($period);
    $stmt = $pdo->prepare("
        SELECT o.orders_id as id, u.username as customer, o.total_price, os.status_name as status, o.created_at
        FROM orders o
        INNER JOIN users u ON o.user_id = u.user_id
        INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
        WHERE o.created_at BETWEEN ? AND ?
        ORDER BY o.created_at DESC
    ");
    $stmt->execute([$start, $end]);
    $ordersData = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <?php include 'includes/admin_styles.php'; ?>
  <style>
    :root {
      --bs-primary: #7F1734;
      --bs-secondary: #6c757d;
      --bs-success: #198754;
      --bs-danger: #dc3545;
      --bs-warning: #ffc107;
      --bs-info: #0dcaf0;
      --bs-light: #f8f9fa;
      --bs-dark: #212529;
    }
    
    /* Override admin styles for this page */
    .main-content {
      background-color: var(--bg-primary) !important;
    }
    
    .main-container {
      background: var(--card-bg);
      border-radius: 20px;
      box-shadow: var(--card-shadow);
      padding: 2rem;
      border: 1px solid var(--border-color);
    }
    
    .page-header {
      background: var(--bs-primary);
      color: white;
      padding: 2rem;
      border-radius: 15px;
      margin-bottom: 2rem;
      box-shadow: 0 5px 15px rgba(127, 23, 52, 0.3);
    }
    
    .page-header h2 {
      margin: 0;
      font-weight: 700;
      font-size: 2rem;
    }

    /* Analytics Cards - Light Version */
    .analytics-card {
      background: white;
      color: var(--bs-dark);
      border-radius: 1rem;
      padding: 1.5rem;
      box-shadow: 0 4px 20px rgba(0,0,0,0.08);
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
      height: 100%;
      display: flex;
      align-items: center;
      gap: 1rem;
      position: relative;
      overflow: hidden;
    }

    .analytics-card::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: var(--bs-primary);
    }

    .analytics-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 30px rgba(0,0,0,0.12);
    }

    .card-icon {
      width: 60px;
      height: 60px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      flex-shrink: 0;
      background: rgba(127, 23, 52, 0.1);
      color: var(--bs-primary);
    }

    .card-content {
      flex: 1;
    }

    .card-number {
      font-size: 2rem;
      font-weight: 700;
      color: var(--bs-primary);
      margin: 0;
      line-height: 1;
    }

    .card-label {
      color: var(--bs-secondary);
      font-size: 0.9rem;
      font-weight: 500;
      margin: 0.5rem 0 0 0;
    }
    
    .table-card {
      background: white;
      border-radius: 20px;
      box-shadow: 0 8px 25px rgba(0,0,0,0.08);
      border: 1px solid #e9ecef;
      color: var(--text-primary) !important;
    }
    
    .table-card .card-header {
      background: transparent;
      border-bottom: 1px solid #e9ecef;
    }
    
    .table-card .card-body {
      padding: 1.5rem;
    }
    
    .table-card .table {
      margin-bottom: 0;
    }
    
    .table-card .table th {
      border: none;
      padding: 1rem 1.25rem;
      font-weight: 600;
      color: var(--bs-dark);
    }
    
    .table-card .table td {
      border: none;
      padding: 1rem 1.25rem;
      vertical-align: middle;
    }
    
    .table-card .table-light {
      background: #f8f9fa;
    }

    .report-nav .nav-link {
      color: #6c757d;
      font-weight: 500;
      transition: all 0.3s ease;
      border-radius: 8px;
      margin-right: 0.5rem;
    }

    .report-nav .nav-link:hover {
      color: #7F1734;
      background-color: rgba(127, 23, 52, 0.1);
    }

    .report-nav .nav-link.active {
      color: white;
      background-color: #7F1734;
    }

    .table-hover tbody tr:hover {
      background-color: rgba(127, 23, 52, 0.05);
    }

    .badge {
      font-size: 0.75rem;
      padding: 0.5rem 0.75rem;
    }

    .btn {
      border-radius: 8px;
      font-weight: 500;
      transition: all 0.3s ease;
    }

    .btn:hover {
      transform: translateY(-1px);
    }

    .display-6 {
      font-weight: 700;
    }

    .stat-card {
      background: linear-gradient(135deg, #f8f9fa 0%, #ffffff 100%);
      border-radius: 8px;
      padding: 1.5rem;
      border-left: 4px solid #7F1734;
      transition: all 0.3s ease;
    }

    .stat-card:hover {
      transform: translateY(-2px);
      box-shadow: 0 4px 15px rgba(127, 23, 52, 0.1);
    }
    
    @media (max-width: 768px) {
      .main-container {
        padding: 1rem;
      }
      
      .page-header {
        padding: 1.5rem;
      }
      
      .page-header h2 {
        font-size: 1.5rem;
      }
    }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="main-container">
      <?php if (isset($error_message)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
          <i class="fa fa-exclamation-triangle me-2"></i>
          <?= htmlspecialchars($error_message) ?>
          <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
      <?php endif; ?>
    
    <!-- Page Header -->
    <div class="page-header">
      <div class="d-flex justify-content-between align-items-center">
        <div>
          <h2>
            <i class="fa fa-chart-line me-3"></i>Business Reports
          </h2>
          <p class="mb-0 opacity-75">Comprehensive analytics and insights for your business</p>
        </div>
      </div>
    </div>

      <!-- Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fa fa-chart-line"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">₱<?php echo number_format($totalSales, 2); ?></h3>
              <p class="card-label">Total Sales</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fa fa-shopping-cart"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $totalOrders; ?></h3>
              <p class="card-label">Total Orders</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fa fa-users"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $totalCustomers; ?></h3>
              <p class="card-label">Total Customers</p>
            </div>
          </div>
        </div>
        
        <div class="col-lg-3 col-md-6">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fa fa-box"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $totalProducts; ?></h3>
              <p class="card-label">Total Products</p>
            </div>
          </div>
        </div>
      </div>


      <!-- Report Navigation -->
      <div class="mb-4">
        <nav class="nav report-nav">
          <a href="?type=sales&period=daily" class="nav-link <?= $reportType==='sales'&&$period==='daily'?'active':'' ?>">
            <i class="fa fa-calendar-day me-2"></i>Daily Sales
          </a>
          <a href="?type=sales&period=weekly" class="nav-link <?= $reportType==='sales'&&$period==='weekly'?'active':'' ?>">
            <i class="fa fa-calendar-week me-2"></i>Weekly Sales
          </a>
          <a href="?type=sales&period=monthly" class="nav-link <?= $reportType==='sales'&&$period==='monthly'?'active':'' ?>">
            <i class="fa fa-calendar-alt me-2"></i>Monthly Sales
          </a>
          <a href="?type=sales&period=yearly" class="nav-link <?= $reportType==='sales'&&$period==='yearly'?'active':'' ?>">
            <i class="fa fa-calendar me-2"></i>Yearly Sales
          </a>
          <a href="?type=orders&period=daily" class="nav-link <?= $reportType==='orders'?'active':'' ?>">
            <i class="fa fa-shopping-bag me-2"></i>Orders
          </a>
          <a href="?type=inventory" class="nav-link <?= $reportType==='inventory'?'active':'' ?>">
            <i class="fa fa-boxes me-2"></i>Inventory
          </a>
          <a href="?type=returns" class="nav-link <?= $reportType==='returns'?'active':'' ?>">
            <i class="fa fa-undo me-2"></i>Returns
          </a>
        </nav>
      </div>

      <!-- Filter and Sort Controls -->
      <div class="row mb-4">
        <div class="col-md-12">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-3">
              <div class="row align-items-center">
                <div class="col-md-4">
                  <h6 class="fw-bold mb-0 text-dark">
                    <i class="fa fa-filter me-2"></i>Filter & Sort Options
                  </h6>
                </div>
                <div class="col-md-8">
                  <div class="row g-2">
                    <?php if (in_array($reportType, ['sales', 'orders', 'returns'])): ?>
                    <div class="col-md-3">
                      <input type="date" id="dateFrom" class="form-control form-control-sm" placeholder="From Date">
                    </div>
                    <div class="col-md-3">
                      <input type="date" id="dateTo" class="form-control form-control-sm" placeholder="To Date">
                    </div>
                    <?php endif; ?>
                    <div class="col-md-3">
                      <select id="sortBy" class="form-select form-select-sm">
                        <option value="">Sort by...</option>
                        <?php if ($reportType === 'sales'): ?>
                          <option value="date_desc">Date (Newest First)</option>
                          <option value="date_asc">Date (Oldest First)</option>
                          <option value="amount_desc">Amount (Highest First)</option>
                          <option value="amount_asc">Amount (Lowest First)</option>
                          <option value="customer_asc">Customer (A-Z)</option>
                          <option value="customer_desc">Customer (Z-A)</option>
                        <?php elseif ($reportType === 'orders'): ?>
                          <option value="date_desc">Date (Newest First)</option>
                          <option value="date_asc">Date (Oldest First)</option>
                          <option value="amount_desc">Amount (Highest First)</option>
                          <option value="amount_asc">Amount (Lowest First)</option>
                          <option value="status_asc">Status (A-Z)</option>
                          <option value="status_desc">Status (Z-A)</option>
                          <option value="customer_asc">Customer (A-Z)</option>
                          <option value="customer_desc">Customer (Z-A)</option>
                        <?php elseif ($reportType === 'inventory'): ?>
                          <option value="name_asc">Product Name (A-Z)</option>
                          <option value="name_desc">Product Name (Z-A)</option>
                          <option value="stock_desc">Stock (Highest First)</option>
                          <option value="stock_asc">Stock (Lowest First)</option>
                          <option value="price_desc">Price (Highest First)</option>
                          <option value="price_asc">Price (Lowest First)</option>
                          <option value="value_desc">Total Value (Highest First)</option>
                          <option value="value_asc">Total Value (Lowest First)</option>
                        <?php elseif ($reportType === 'returns'): ?>
                          <option value="date_desc">Date (Newest First)</option>
                          <option value="date_asc">Date (Oldest First)</option>
                          <option value="reason_asc">Reason (A-Z)</option>
                          <option value="reason_desc">Reason (Z-A)</option>
                        <?php endif; ?>
                      </select>
                    </div>
                    <div class="col-md-3">
                      <input type="text" id="searchFilter" class="form-control form-control-sm" placeholder="Search...">
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Sales Report -->
      <?php if ($reportType === 'sales'): ?>
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fa fa-chart-line me-2"></i>
                Sales Report (<?= ucfirst($period) ?>)
              </h5>
              <a href="generate_report_pdf.php?type=sales&period=<?= $period ?>" class="btn btn-danger">
                <i class="fa fa-file-pdf me-1"></i>Generate PDF
              </a>
            </div>
          </div>

          <!-- Summary Cards -->
          <?php
          $total = array_sum(array_column($salesData, 'total_price'));
          $orderCount = count($salesData);
          $avgOrder = $orderCount > 0 ? $total / $orderCount : 0;
          ?>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #ffc107;"><?= $orderCount ?></div>
                  <div class="text-muted">Total Orders</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #198754;">₱<?= number_format($total, 2) ?></div>
                  <div class="text-muted">Total Revenue</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #0dcaf0;">₱<?= number_format($avgOrder, 2) ?></div>
                  <div class="text-muted">Average Order Value</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Order ID</th>
                    <th class="fw-semibold">Customer</th>
                    <th class="fw-semibold">Total Price</th>
                    <th class="fw-semibold">Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($salesData)): ?>
                    <tr>
                      <td colspan="4" class="text-center py-5">
                        <i class="fa fa-inbox display-4 text-muted mb-3"></i>
                        <div class="text-muted">No sales data found for this period.</div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($salesData as $row): ?>
                    <tr>
                      <td class="fw-semibold">#<?= $row['id'] ?></td>
                      <td><?= htmlspecialchars($row['customer']) ?></td>
                      <td class="fw-bold" style="color: #198754;">₱<?= number_format($row['total_price'],2) ?></td>
                      <td class="text-muted"><?= date('M d, Y H:i', strtotime($row['delivered_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
                <?php if (!empty($salesData)): ?>
                <tfoot class="table-light">
                  <tr class="fw-bold">
                    <td colspan="2" class="text-end">Total Sales:</td>
                    <td style="color: #198754;">₱<?= number_format($total,2) ?></td>
                    <td></td>
                  </tr>
                </tfoot>
                <?php endif; ?>
              </table>
            </div>
          </div>
        </div>

      <!-- Orders Report -->
      <?php elseif ($reportType === 'orders'): ?>
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fa fa-shopping-bag me-2"></i>
                Orders Report (<?= ucfirst($period) ?>)
              </h5>
              <a href="generate_report_pdf.php?type=orders&period=<?= $period ?>" class="btn btn-danger">
                <i class="fa fa-file-pdf me-1"></i>Generate PDF
              </a>
            </div>
          </div>

          <!-- Summary Cards -->
          <?php
          $totalRevenue = array_sum(array_column($ordersData, 'total_price'));
          $orderCount = count($ordersData);
          $statusCounts = array_count_values(array_column($ordersData, 'status'));
          ?>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #0dcaf0;"><?= $orderCount ?></div>
                  <div class="text-muted">Total Orders</div>
                </div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #198754;">₱<?= number_format($totalRevenue, 2) ?></div>
                  <div class="text-muted">Total Revenue</div>
                </div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #ffc107;"><?= $statusCounts['Pending'] ?? 0 ?></div>
                  <div class="text-muted">Pending Orders</div>
                </div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #7F1734;"><?= $statusCounts['Completed'] ?? 0 ?></div>
                  <div class="text-muted">Completed Orders</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Order ID</th>
                    <th class="fw-semibold">Customer</th>
                    <th class="fw-semibold">Total Price</th>
                    <th class="fw-semibold">Status</th>
                    <th class="fw-semibold">Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($ordersData)): ?>
                    <tr>
                      <td colspan="5" class="text-center py-5">
                        <i class="fa fa-inbox display-4 text-muted mb-3"></i>
                        <div class="text-muted">No orders found for this period.</div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($ordersData as $row): ?>
                    <tr>
                      <td class="fw-semibold">#<?= $row['id'] ?></td>
                      <td><?= htmlspecialchars($row['customer']) ?></td>
                      <td class="fw-bold" style="color: #198754;">₱<?= number_format($row['total_price'],2) ?></td>
                      <td>
                        <span class="badge
                          <?= $row['status'] === 'Pending' ? 'bg-warning text-dark' : '' ?>
                          <?= $row['status'] === 'To Ship' ? 'bg-info' : '' ?>
                          <?= $row['status'] === 'Out for delivery' ? 'bg-secondary' : '' ?>
                          <?= $row['status'] === 'Completed' ? 'bg-success' : '' ?>
                          <?= $row['status'] === 'Cancelled' ? 'bg-danger' : '' ?>">
                          <?= htmlspecialchars($row['status']) ?>
                        </span>
                      </td>
                      <td class="text-muted"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      <!-- Inventory Report -->
      <?php elseif ($reportType === 'inventory'): ?>
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fa fa-boxes me-2"></i>
                Inventory Report
              </h5>
              <a href="generate_report_pdf.php?type=inventory" class="btn btn-danger">
                <i class="fa fa-file-pdf me-1"></i>Generate PDF
              </a>
            </div>
          </div>

          <!-- Summary Cards -->
          <?php
          $totalProducts = count($inventoryData);
          $totalValue = array_sum(array_map(fn($p) => $p['stock'] * $p['price'], $inventoryData));
          $avgPrice = $totalProducts > 0 ? $totalValue / $totalProducts : 0;
          ?>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #0dcaf0;"><?= $totalProducts ?></div>
                  <div class="text-muted">Active Products</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #7F1734;">₱<?= number_format($totalValue, 2) ?></div>
                  <div class="text-muted">Total Inventory Value</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #198754;">₱<?= number_format($avgPrice, 2) ?></div>
                  <div class="text-muted">Average Product Value</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Product ID</th>
                    <th class="fw-semibold">Name</th>
                    <th class="fw-semibold">Stock</th>
                    <th class="fw-semibold">Price</th>
                    <th class="fw-semibold">Total Value</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($inventoryData)): ?>
                    <tr>
                      <td colspan="5" class="text-center py-5">
                        <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                        <div class="text-muted">No active inventory data found.</div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($inventoryData as $row): ?>
                    <tr>
                      <td class="fw-semibold">#<?= $row['id'] ?></td>
                      <td><?= htmlspecialchars($row['name']) ?></td>
                      <td><?= $row['stock'] ?></td>
                      <td class="fw-bold" style="color: #198754;">₱<?= number_format($row['price'],2) ?></td>
                      <td class="fw-bold" style="color: #7F1734;">₱<?= number_format($row['stock'] * $row['price'], 2) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

      <!-- Returns Report -->
      <?php elseif ($reportType === 'returns'): ?>
        <div class="table-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fa fa-undo me-2"></i>
                Return Reports
              </h5>
              <a href="generate_report_pdf.php?type=returns" class="btn btn-danger">
                <i class="fa fa-file-pdf me-1"></i>Generate PDF
              </a>
            </div>
          </div>

          <!-- Summary Cards -->
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-12">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #dc3545;"><?= count($returnData) ?></div>
                  <div class="text-muted">Total Returns</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Return ID</th>
                    <th class="fw-semibold">Order ID</th>
                    <th class="fw-semibold">Product</th>
                    <th class="fw-semibold">Reason</th>
                    <th class="fw-semibold">Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($returnData)): ?>
                    <tr>
                      <td colspan="5" class="text-center py-5">
                        <i class="fa fa-undo display-4 text-muted mb-3"></i>
                        <div class="text-muted">No returns data found.</div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($returnData as $row): ?>
                    <tr>
                      <td class="fw-semibold">#<?= $row['id'] ?></td>
                      <td>#<?= $row['order_id'] ?></td>
                      <td><?= htmlspecialchars($row['product']) ?></td>
                      <td style="color: #ffc107;"><?= htmlspecialchars($row['reason']) ?></td>
                      <td class="text-muted"><?= date('M d, Y H:i', strtotime($row['created_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  </main>
  <?php include 'includes/admin_scripts.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const sortSelect = document.getElementById('sortBy');
      const searchInput = document.getElementById('searchFilter');
      const dateFromInput = document.getElementById('dateFrom');
      const dateToInput = document.getElementById('dateTo');
      const reportType = '<?= $reportType ?>';
      
      // Get all table rows (excluding header and footer)
      function getTableRows() {
        const table = document.querySelector('.table-card table tbody');
        return table ? Array.from(table.querySelectorAll('tr')) : [];
      }
      
      // Parse date from the format used in reports (e.g., "Dec 15, 2023 14:30")
      function parseDate(dateString) {
        if (!dateString || dateString.trim() === '') return new Date(0);
        
        // Handle different date formats
        // Format: "Dec 15, 2023 14:30" or "Dec 15, 2023"
        const cleanDate = dateString.trim();
        
        // Try to parse the date
        let date = new Date(cleanDate);
        
        // If parsing failed, try alternative formats
        if (isNaN(date.getTime())) {
          // Try format: "M d, Y H:i" -> "M d, Y"
          const dateOnly = cleanDate.split(' ').slice(0, 3).join(' ');
          date = new Date(dateOnly);
        }
        
        // If still failed, return epoch time
        if (isNaN(date.getTime())) {
          console.warn('Could not parse date:', cleanDate);
          return new Date(0);
        }
        
        return date;
      }
      
      // Sort function
      function sortTable(sortBy) {
        const rows = getTableRows();
        if (rows.length === 0) return;
        
        rows.sort((a, b) => {
          let aVal, bVal;
          
          switch (sortBy) {
            case 'date_desc':
              aVal = parseDate(a.cells[<?= $reportType === 'sales' ? '3' : ($reportType === 'orders' ? '4' : '4') ?>].textContent);
              bVal = parseDate(b.cells[<?= $reportType === 'sales' ? '3' : ($reportType === 'orders' ? '4' : '4') ?>].textContent);
              return bVal - aVal;
            case 'date_asc':
              aVal = parseDate(a.cells[<?= $reportType === 'sales' ? '3' : ($reportType === 'orders' ? '4' : '4') ?>].textContent);
              bVal = parseDate(b.cells[<?= $reportType === 'sales' ? '3' : ($reportType === 'orders' ? '4' : '4') ?>].textContent);
              return aVal - bVal;
            case 'amount_desc':
              aVal = parseFloat(a.cells[2].textContent.replace(/[₱,]/g, ''));
              bVal = parseFloat(b.cells[2].textContent.replace(/[₱,]/g, ''));
              return bVal - aVal;
            case 'amount_asc':
              aVal = parseFloat(a.cells[2].textContent.replace(/[₱,]/g, ''));
              bVal = parseFloat(b.cells[2].textContent.replace(/[₱,]/g, ''));
              return aVal - bVal;
            case 'customer_asc':
              aVal = a.cells[1].textContent.toLowerCase();
              bVal = b.cells[1].textContent.toLowerCase();
              return aVal.localeCompare(bVal);
            case 'customer_desc':
              aVal = a.cells[1].textContent.toLowerCase();
              bVal = b.cells[1].textContent.toLowerCase();
              return bVal.localeCompare(aVal);
            case 'status_asc':
              aVal = a.cells[3].textContent.toLowerCase();
              bVal = b.cells[3].textContent.toLowerCase();
              return aVal.localeCompare(bVal);
            case 'status_desc':
              aVal = a.cells[3].textContent.toLowerCase();
              bVal = b.cells[3].textContent.toLowerCase();
              return bVal.localeCompare(aVal);
            case 'name_asc':
              aVal = a.cells[1].textContent.toLowerCase();
              bVal = b.cells[1].textContent.toLowerCase();
              return aVal.localeCompare(bVal);
            case 'name_desc':
              aVal = a.cells[1].textContent.toLowerCase();
              bVal = b.cells[1].textContent.toLowerCase();
              return bVal.localeCompare(aVal);
            case 'stock_desc':
              aVal = parseInt(a.cells[2].textContent);
              bVal = parseInt(b.cells[2].textContent);
              return bVal - aVal;
            case 'stock_asc':
              aVal = parseInt(a.cells[2].textContent);
              bVal = parseInt(b.cells[2].textContent);
              return aVal - bVal;
            case 'price_desc':
              aVal = parseFloat(a.cells[3].textContent.replace(/[₱,]/g, ''));
              bVal = parseFloat(b.cells[3].textContent.replace(/[₱,]/g, ''));
              return bVal - aVal;
            case 'price_asc':
              aVal = parseFloat(a.cells[3].textContent.replace(/[₱,]/g, ''));
              bVal = parseFloat(b.cells[3].textContent.replace(/[₱,]/g, ''));
              return aVal - bVal;
            case 'value_desc':
              aVal = parseFloat(a.cells[4].textContent.replace(/[₱,]/g, ''));
              bVal = parseFloat(b.cells[4].textContent.replace(/[₱,]/g, ''));
              return bVal - aVal;
            case 'value_asc':
              aVal = parseFloat(a.cells[4].textContent.replace(/[₱,]/g, ''));
              bVal = parseFloat(b.cells[4].textContent.replace(/[₱,]/g, ''));
              return aVal - bVal;
            case 'reason_asc':
              aVal = a.cells[3].textContent.toLowerCase();
              bVal = b.cells[3].textContent.toLowerCase();
              return aVal.localeCompare(bVal);
            case 'reason_desc':
              aVal = a.cells[3].textContent.toLowerCase();
              bVal = b.cells[3].textContent.toLowerCase();
              return bVal.localeCompare(aVal);
            default:
              return 0;
          }
        });
        
        // Re-append sorted rows
        const tbody = document.querySelector('.table-card table tbody');
        if (tbody) {
          rows.forEach(row => tbody.appendChild(row));
        }
      }
      
      // Filter function
      function filterTable(searchTerm, dateFrom, dateTo) {
        const rows = getTableRows();
        const term = searchTerm ? searchTerm.toLowerCase() : '';
        
        rows.forEach(row => {
          let shouldShow = true;
          
          // Text search filter
          if (term) {
            const text = row.textContent.toLowerCase();
            shouldShow = shouldShow && text.includes(term);
          }
          
          // Date range filter (only for reports with dates)
          if (shouldShow && (dateFrom || dateTo) && in_array(reportType, ['sales', 'orders', 'returns'])) {
            const dateColumnIndex = reportType === 'sales' ? 3 : (reportType === 'orders' ? 4 : 4);
            const dateCell = row.cells[dateColumnIndex];
            
            if (dateCell) {
              const rowDate = parseDate(dateCell.textContent);
              
              if (dateFrom) {
                const fromDate = new Date(dateFrom);
                shouldShow = shouldShow && rowDate >= fromDate;
              }
              
              if (dateTo) {
                const toDate = new Date(dateTo);
                toDate.setHours(23, 59, 59, 999); // End of day
                shouldShow = shouldShow && rowDate <= toDate;
              }
            }
          }
          
          row.style.display = shouldShow ? '' : 'none';
        });
      }
      
      // Helper function to check if value is in array (JavaScript equivalent of PHP in_array)
      function in_array(needle, haystack) {
        return haystack.indexOf(needle) !== -1;
      }
      
      // Event listeners
      sortSelect.addEventListener('change', function() {
        if (this.value) {
          sortTable(this.value);
        }
      });
      
      searchInput.addEventListener('input', function() {
        const dateFrom = dateFromInput ? dateFromInput.value : '';
        const dateTo = dateToInput ? dateToInput.value : '';
        filterTable(this.value, dateFrom, dateTo);
      });
      
      if (dateFromInput) {
        dateFromInput.addEventListener('change', function() {
          const searchTerm = searchInput ? searchInput.value : '';
          const dateTo = dateToInput ? dateToInput.value : '';
          filterTable(searchTerm, this.value, dateTo);
        });
      }
      
      if (dateToInput) {
        dateToInput.addEventListener('change', function() {
          const searchTerm = searchInput ? searchInput.value : '';
          const dateFrom = dateFromInput ? dateFromInput.value : '';
          filterTable(searchTerm, dateFrom, this.value);
        });
      }
    });
  </script>
</body>
</html>