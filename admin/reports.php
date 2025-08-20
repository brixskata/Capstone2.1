<?php
session_start();
include 'db.php';

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
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

// Get report type from query string
$reportType = $_GET['type'] ?? 'sales';
$period = $_GET['period'] ?? 'daily';

// Get basic stats for overview
$totalSales = $pdo->query("SELECT COALESCE(SUM(total_price), 0) FROM delivered_orders")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM orders")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'user'")->fetchColumn();
$totalProducts = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archived = 0")->fetchColumn();

// Fetch data for reports
$salesData = [];
$inventoryData = [];
$returnData = [];
$ordersData = [];

if ($reportType === 'sales') {
    list($start, $end) = getDateRange($period);
    $stmt = $pdo->prepare("SELECT id, customer, total_price, delivered_at FROM delivered_orders WHERE delivered_at BETWEEN ? AND ? ORDER BY delivered_at DESC");
    $stmt->execute([$start, $end]);
    $salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($reportType === 'inventory') {
    $stmt = $pdo->query("SELECT id, name, stock, price, is_archived FROM products ORDER BY name ASC");
    $inventoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($reportType === 'returns') {
    // Example: Assume you have a returns table
    $stmt = $pdo->query("SELECT * FROM returns ORDER BY created_at DESC");
    $returnData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} elseif ($reportType === 'orders') {
    list($start, $end) = getDateRange($period);
    $stmt = $pdo->prepare("
        SELECT o.id, u.username as customer, o.total_price, o.status, o.created_at
        FROM orders o
        INNER JOIN users u ON o.user_id = u.id
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Reports - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <?php include 'includes/admin_styles.php'; ?>
  <style>
    .report-card {
      background: white;
      border-radius: 12px;
      box-shadow: 0 2px 10px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
    }

    .metric-item {
      padding: 24px;
      text-align: center;
      transition: transform 0.2s ease;
    }

    .metric-item:hover {
      transform: translateY(-2px);
    }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="d-flex justify-content-between align-items-center mb-4">
      <div>
        <h1 class="h3 fw-bold text-dark mb-2">
          <i class="fa fa-chart-line text-primary me-3"></i>Business Reports
        </h1>
      </div>
      <a href="generate_report_pdf.php" class="btn text-white fw-bold" style="background-color: var(--bs-secondary);">
        <i class="fa fa-download me-2"></i>Export PDF
      </a>
    </div>

    <!-- Sales Overview -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="report-card h-100">
            <div class="metric-item">
              <div class="text-primary mb-2">
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
              <div class="text-success mb-2">
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
              <div class="text-info mb-2">
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
              <div class="text-warning mb-2">
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
                    <td class="fw-bold text-success">₱<?= number_format($row['total_price'],2) ?></td>
                    <td>
                      <span class="badge
                        <?= $row['status'] === 'Pending' ? 'bg-warning text-dark' : '' ?>
                        <?= $row['status'] === 'Processing' ? 'bg-info' : '' ?>
                        <?= $row['status'] === 'Shipped' ? 'bg-secondary' : '' ?>
                        <?= $row['status'] === 'Delivered' ? 'bg-success' : '' ?>
                        <?= $row['status'] === 'Return' ? 'bg-danger' : '' ?>">
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
                <?php if (empty($salesData)): // Assuming salesData can represent top products for now ?>
                  <tr>
                    <td colspan="3" class="text-center py-5">
                      <i class="fa fa-box-open display-4 text-muted mb-3"></i>
                      <div class="text-muted">No top selling products found.</div>
                    </td>
                  </tr>
                <?php else: ?>
                  <?php
                    // For demonstration, let's assume we have top product data similar to salesData structure
                    // In a real scenario, you'd fetch this specifically.
                    // Example: $topProducts = fetchTopProducts();
                    // For now, we'll just show a few from salesData if available
                    $displayProducts = array_slice($salesData, 0, 5); // Displaying first 5 as example
                    foreach ($displayProducts as $row):
                  ?>
                  <tr>
                    <td><?= htmlspecialchars($row['customer']) ?></td> <?php // Using customer name as product name placeholder ?>
                    <td>120</td> <?php // Placeholder sales count ?>
                    <td class="fw-bold text-success">₱<?= number_format($row['total_price'], 2) ?></td>
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
                <i class="fa fa-chart-line text-warning me-2"></i>
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
                  <div class="display-6 fw-bold text-warning"><?= $orderCount ?></div>
                  <div class="text-muted">Total Orders</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold text-success">₱<?= number_format($total, 2) ?></div>
                  <div class="text-muted">Total Revenue</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold text-info">₱<?= number_format($avgOrder, 2) ?></div>
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
                      <td class="fw-bold text-success">₱<?= number_format($row['total_price'],2) ?></td>
                      <td class="text-muted"><?= date('M d, Y H:i', strtotime($row['delivered_at'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                  <?php endif; ?>
                </tbody>
                <?php if (!empty($salesData)): ?>
                <tfoot class="table-light">
                  <tr class="fw-bold">
                    <td colspan="2" class="text-end">Total Sales:</td>
                    <td class="text-success">₱<?= number_format($total,2) ?></td>
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
                <i class="fa fa-shopping-bag text-warning me-2"></i>
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
                  <div class="display-6 fw-bold text-info"><?= $orderCount ?></div>
                  <div class="text-muted">Total Orders</div>
                </div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold text-success">₱<?= number_format($totalRevenue, 2) ?></div>
                  <div class="text-muted">Total Revenue</div>
                </div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold text-warning"><?= $statusCounts['Pending'] ?? 0 ?></div>
                  <div class="text-muted">Pending Orders</div>
                </div>
              </div>
              <div class="col-lg-3 col-md-6">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: var(--bs-secondary);"><?= $statusCounts['Delivered'] ?? 0 ?></div>
                  <div class="text-muted">Delivered Orders</div>
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
                      <td class="fw-bold text-success">₱<?= number_format($row['total_price'],2) ?></td>
                      <td>
                        <span class="badge
                          <?= $row['status'] === 'Pending' ? 'bg-warning text-dark' : '' ?>
                          <?= $row['status'] === 'Processing' ? 'bg-info' : '' ?>
                          <?= $row['status'] === 'Shipped' ? 'bg-secondary' : '' ?>
                          <?= $row['status'] === 'Delivered' ? 'bg-success' : '' ?>
                          <?= $row['status'] === 'Return' ? 'bg-danger' : '' ?>">
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
                <i class="fa fa-boxes text-warning me-2"></i>
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
                  <div class="display-6 fw-bold text-info"><?= $totalProducts ?></div>
                  <div class="text-muted">Total Products</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold text-success"><?= $activeProducts ?></div>
                  <div class="text-muted">Active Products</div>
                </div>
              </div>
              <div class="col-md-4">
                <div class="stat-card text-center">
                  <div class="display-6 fw-bold" style="color: var(--bs-secondary);">₱<?= number_format($totalValue, 2) ?></div>
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
                      <td class="fw-bold text-success">₱<?= number_format($row['price'],2) ?></td>
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
                <i class="fa fa-undo text-warning me-2"></i>
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
                  <div class="display-6 fw-bold text-danger"><?= count($returnData) ?></div>
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
                      <td class="text-warning"><?= htmlspecialchars($row['reason']) ?></td>
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