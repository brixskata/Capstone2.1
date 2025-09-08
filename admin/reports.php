<?php
session_start();
include 'db.php';

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
$topProducts = [];

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

// Get top selling products for all report types
$topProductsStmt = $pdo->query("
    SELECT 
        p.product_name as name,
        SUM(oi.quantity) as total_sold,
        SUM(oi.quantity * oi.price) as total_revenue
    FROM products p
    INNER JOIN order_items oi ON p.product_id = oi.product_id
    INNER JOIN orders o ON oi.order_id = o.orders_id
    INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
    WHERE os.status_name = 'Completed'
    GROUP BY p.product_id, p.product_name
    ORDER BY total_sold DESC
    LIMIT 5
");
$topProducts = $topProductsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <?php include 'includes/admin_styles.php'; ?>
  <style>
    :root {
      --primary-color: #7F1734;
      --secondary-color: #a91d42;
      --success-color: #198754;
      --info-color: #0dcaf0;
      --warning-color: #ffc107;
      --danger-color: #dc3545;
    }

    .report-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
    }

    .report-card:hover {
      box-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
      transform: translateY(-2px);
    }

    .metric-item {
      padding: 24px;
      text-align: center;
      transition: transform 0.2s ease;
      position: relative;
      overflow: hidden;
    }

    .metric-item::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      height: 4px;
      background: #7F1734;
    }

    .metric-item:hover {
      transform: translateY(-2px);
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

    .text-primary {
      color: #7F1734 !important;
    }

    .text-success {
      color: var(--success-color) !important;
    }

    .text-info {
      color: var(--info-color) !important;
    }

    .text-warning {
      color: var(--warning-color) !important;
    }

    .text-danger {
      color: var(--danger-color) !important;
    }

    .bg-primary {
      background-color: #7F1734 !important;
    }

    .bg-success {
      background-color: var(--success-color) !important;
    }

    .bg-info {
      background-color: var(--info-color) !important;
    }

    .bg-warning {
      background-color: var(--warning-color) !important;
    }

    .bg-danger {
      background-color: var(--danger-color) !important;
    }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <?php if (isset($error_message)): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fa fa-exclamation-triangle me-2"></i>
        <?= htmlspecialchars($error_message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>
    
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 fw-bold text-dark mb-2">
          <i class="fa fa-chart-line me-3" style="color: #7F1734;"></i>Business Reports
        </h1>
      </div>
      <a href="generate_report_pdf.php?type=<?= $reportType ?>&period=<?= $period ?>" class="btn text-white fw-bold" style="background-color: #7F1734;">
        <i class="fa fa-download me-2"></i>Export PDF
      </a>
    </div>

    <!-- Sales Overview -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="report-card h-100">
            <div class="metric-item">
              <div class="mb-2" style="color: #7F1734;">
                <i class="fa fa-chart-line fa-2x"></i>
              </div>
              <h4 class="fw-bold">₱<?php echo number_format($totalSales, 2); ?></h4>
              <p class="text-muted mb-0">Total Sales</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="report-card h-100">
            <div class="metric-item">
              <div class="mb-2" style="color: #198754;">
                <i class="fa fa-shopping-cart fa-2x"></i>
              </div>
              <h4 class="fw-bold"><?php echo $totalOrders; ?></h4>
              <p class="text-muted mb-0">Total Orders</p>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="report-card h-100">
            <div class="metric-item">
              <div class="mb-2" style="color: #0dcaf0;">
                <i class="fa fa-users fa-2x"></i>
              </div>
              <h4 class="fw-bold"><?php echo $totalCustomers; ?></h4>
              <p class="text-muted mb-0">Total Customers</p>
            </div>
          </div>
        </div>

        <div class="col-md-3">
          <div class="report-card h-100">
            <div class="metric-item">
              <div class="mb-2" style="color: #ffc107;">
                <i class="fa fa-box fa-2x"></i>
              </div>
              <h4 class="fw-bold"><?php echo $totalProducts; ?></h4>
              <p class="text-muted mb-0">Total Products</p>
            </div>
          </div>
        </div>
      </div>

    <!-- Recent Orders & Top Selling Products -->
      <div class="row g-4 mb-4">
        <div class="col-md-6">
          <div class="report-card">
            <div class="card-header bg-transparent border-0 py-4">
              <h5 class="fw-bold mb-0">Recent Orders</h5>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Order ID</th>
                    <th class="fw-semibold">Customer</th>
                    <th class="fw-semibold">Amount</th>
                    <th class="fw-semibold">Status</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (empty($ordersData)): ?>
                  <tr>
                    <td colspan="4" class="text-center py-5">
                      <i class="fa fa-inbox display-4 text-muted mb-3"></i>
                      <div class="text-muted">No recent orders found.</div>
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
                        <?= $row['status'] === 'Shipped' ? 'bg-secondary' : '' ?>
                        <?= $row['status'] === 'Completed' ? 'bg-success' : '' ?>
                        <?= $row['status'] === 'Cancelled' ? 'bg-danger' : '' ?>">
                        <?= htmlspecialchars($row['status']) ?>
                      </span>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
            </div>
          </div>
        </div>

        <div class="col-md-6">
          <div class="report-card">
            <div class="card-header bg-transparent border-0 py-4">
              <h5 class="fw-bold mb-0">Top Selling Products</h5>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold">Product</th>
                    <th class="fw-semibold">Sales</th>
                    <th class="fw-semibold">Revenue</th>
                  </tr>
                </thead>
                <tbody>
                <?php if (empty($topProducts)): ?>
                  <tr>
                    <td colspan="3" class="text-center py-5">
                      <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                      <div class="text-muted">No top selling products found.</div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($topProducts as $product): ?>
                  <tr>
                    <td><?= htmlspecialchars($product['name']) ?></td>
                    <td><?= $product['total_sold'] ?></td>
                    <td class="fw-bold" style="color: #198754;">₱<?= number_format($product['total_revenue'], 2) ?></td>
                  </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
              </table>
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

      <!-- Sales Report -->
      <?php if ($reportType === 'sales'): ?>
        <div class="report-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0">
                <i class="fa fa-chart-line me-2" style="color: #ffc107;"></i>
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
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total Price</th>
                    <th>Date</th>
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
        <div class="report-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0">
                <i class="fa fa-shopping-bag me-2" style="color: #ffc107;"></i>
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
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Total Price</th>
                    <th>Status</th>
                    <th>Date</th>
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
                          <?= $row['status'] === 'Shipped' ? 'bg-secondary' : '' ?>
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
        <div class="report-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0">
                <i class="fa fa-boxes me-2" style="color: #ffc107;"></i>
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
          $activeProducts = count(array_filter($inventoryData, fn($p) => !$p['is_archived']));
          $totalValue = array_sum(array_map(fn($p) => $p['stock'] * $p['price'], $inventoryData));
          ?>
          <div class="card-body">
            <div class="row g-3 mb-4">
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #0dcaf0;"><?= $totalProducts ?></div>
                  <div class="text-muted">Total Products</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #198754;"><?= $activeProducts ?></div>
                  <div class="text-muted">Active Products</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: #7F1734;">₱<?= number_format($totalValue, 2) ?></div>
                  <div class="text-muted">Total Inventory Value</div>
                </div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Product ID</th>
                    <th>Name</th>
                    <th>Stock</th>
                    <th>Price</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php if (empty($inventoryData)): ?>
                    <tr>
                      <td colspan="5" class="text-center py-5">
                        <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                        <div class="text-muted">No inventory data found.</div>
                      </td>
                    </tr>
                  <?php else: ?>
                    <?php foreach ($inventoryData as $row): ?>
                    <tr>
                      <td class="fw-semibold">#<?= $row['id'] ?></td>
                      <td><?= htmlspecialchars($row['name']) ?></td>
                      <td><?= $row['stock'] ?></td>
                      <td class="fw-bold" style="color: #198754;">₱<?= number_format($row['price'],2) ?></td>
                      <td>
                        <?php if ($row['is_archived']): ?>
                          <span class="badge bg-danger">Archived</span>
                        <?php else: ?>
                          <span class="badge bg-success">Active</span>
                        <?php endif; ?>
                      </td>
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
        <div class="report-card">
          <div class="card-header bg-transparent border-0 p-4">
            <div class="d-flex justify-content-between align-items-center">
              <h5 class="fw-bold mb-0">
                <i class="fa fa-undo me-2" style="color: #ffc107;"></i>
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
              <table class="table table-hover">
                <thead class="table-light">
                  <tr>
                    <th>Return ID</th>
                    <th>Order ID</th>
                    <th>Product</th>
                    <th>Reason</th>
                    <th>Date</th>
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
  </main>
  <?php include 'includes/admin_scripts.php'; ?>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>