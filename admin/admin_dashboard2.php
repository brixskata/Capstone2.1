
<?php 
include '../includes/db.php';
session_start();

// Ensure user is logged in and has admin access (Super Admin or Admin)
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
	header("Location: login_admin.php");
	exit; 
}

try {
	// Basic statistics
	$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archive = 0");
	$totalProducts = $stmt->fetchColumn();

	// Get total sales from delivered orders
	$stmt = $pdo->query("SELECT SUM(total_price) as total_sales FROM delivered_orders");
	$deliveredStats = $stmt->fetch(PDO::FETCH_ASSOC);
	$totalCompletedSales = $deliveredStats['total_sales'] ?: 0;

	// Get total pending sales (Pending/To Ship/Shipped)
	$stmt = $pdo->query("SELECT SUM(o.total_price)
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name IN ('Pending','To Ship','Shipped')");
	$totalPendingSales = $stmt->fetchColumn() ?: 0;

	$stmt = $pdo->query("SELECT COUNT(*) FROM users");
	$totalUsers = $stmt->fetchColumn();

	// Sum current stock from product_stock for active products
	$stmt = $pdo->query("SELECT COALESCE(SUM(ps.current_stock),0)
		FROM products p
		LEFT JOIN product_stock ps ON ps.product_id = p.product_id
		WHERE p.is_archive = 0");
	$totalStock = $stmt->fetchColumn();

	// Order status counts
	$stmt = $pdo->query("SELECT COUNT(*)
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name = 'Pending'");
	$pendingOrders = $stmt->fetchColumn();

	$stmt = $pdo->query("SELECT COUNT(*)
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name = 'To Ship'");
	$processingOrders = $stmt->fetchColumn();

	$stmt = $pdo->query("SELECT COUNT(*)
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name = 'Shipped'");
	$shippedOrders = $stmt->fetchColumn();

	// Recent orders
	$stmt = $pdo->query("SELECT o.orders_id AS id, u.username, os.status_name AS status, o.created_at 
						FROM orders o 
						JOIN users u ON o.user_id = u.user_id 
						JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
						ORDER BY o.created_at DESC 
						LIMIT 5");
	$recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Low stock products
	$stmt = $pdo->query("SELECT p.product_name AS name, COALESCE(ps.current_stock,0) AS stock
						FROM products p
						LEFT JOIN product_stock ps ON ps.product_id = p.product_id
						WHERE COALESCE(ps.current_stock,0) < 10 AND p.is_archive = 0
						ORDER BY ps.current_stock ASC 
						LIMIT 5");
	$lowStockProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Sales data for line chart (last 12 months)
	$stmt = $pdo->query("SELECT 
		DATE_FORMAT(delivered_at, '%Y-%m') as month,
		SUM(total_price) as total_sales,
		COUNT(*) as order_count
		FROM delivered_orders 
		WHERE delivered_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
		GROUP BY DATE_FORMAT(delivered_at, '%Y-%m')
		ORDER BY month ASC");
	$salesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Product categories data for pie chart
	$stmt = $pdo->query("SELECT 
		c.category_name as category,
		COUNT(p.product_id) as product_count,
		COALESCE(SUM(ps.current_stock),0) as total_stock
		FROM categories c
		LEFT JOIN products p ON c.category_id = p.category_id AND p.is_archive = 0
		LEFT JOIN product_stock ps ON ps.product_id = p.product_id
		GROUP BY c.category_id, c.category_name
		HAVING product_count > 0
		ORDER BY product_count DESC");
	$categoriesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Order status data for bar chart
	$stmt = $pdo->query("SELECT 
		os.status_name AS status,
		COUNT(*) as order_count,
		SUM(o.total_price) as total_amount
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		GROUP BY os.status_name 
		ORDER BY order_count DESC");
	$orderStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Product movement analysis (last 30 days)
	$stmt = $pdo->query("SELECT 
		p.product_id,
		p.product_name,
		COUNT(sm.stockmovement_id) as total_movements,
		COALESCE(ps.current_stock, 0) as current_stock
		FROM products p
		LEFT JOIN product_stock ps ON p.product_id = ps.product_id
		LEFT JOIN stock_movements sm ON p.product_id = sm.product_id 
			AND sm.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		WHERE p.is_archive = 0
		GROUP BY p.product_id, p.product_name, ps.current_stock
		ORDER BY total_movements DESC
	");
	$movement_analysis = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Classify products by movement - removed medium moving category
	$fast_moving = 0;
	$slow_moving = 0;
	$non_moving = 0;

	foreach ($movement_analysis as $product) {
		$movement_rate = $product['total_movements'] > 0 ? round(($product['total_movements'] / 30) * 100, 1) : 0;
		
		if ($product['total_movements'] == 0) {
			$non_moving++;
		} elseif ($movement_rate > 7 || $product['total_movements'] > 6) {
			$fast_moving++;
		} else {
			$slow_moving++;
		}
	}

	$movement_categories = [
		'Fast Moving' => $fast_moving,
		'Slow Moving' => $slow_moving,
		'Non Moving' => $non_moving
	];

	// Top selling products (last 30 days)
	$stmt = $pdo->query("SELECT 
		p.product_name,
		SUM(oi.quantity) as total_sold,
		SUM(oi.quantity * oi.price) as total_revenue
		FROM order_items oi
		JOIN products p ON oi.product_id = p.product_id
		JOIN orders o ON oi.order_id = o.orders_id
		WHERE o.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		AND p.is_archive = 0
		GROUP BY p.product_id, p.product_name
		ORDER BY total_sold DESC
		LIMIT 10
	");
	$topSellingProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Daily sales data (last 30 days)
	$stmt = $pdo->query("SELECT 
		DATE(created_at) as sale_date,
		COUNT(*) as order_count,
		SUM(total_price) as daily_revenue
		FROM orders
		WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
		GROUP BY DATE(created_at)
		ORDER BY sale_date DESC
	");
	$dailySalesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Customer registration trend (last 12 months)
	$stmt = $pdo->query("SELECT 
		DATE_FORMAT(date_created, '%Y-%m') as month,
		COUNT(*) as new_users
		FROM users
		WHERE date_created >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
		GROUP BY DATE_FORMAT(date_created, '%Y-%m')
		ORDER BY month ASC
	");
	$userRegistrationData = $stmt->fetchAll(PDO::FETCH_ASSOC);

	// Inventory value by category
	$stmt = $pdo->query("SELECT 
		c.category_name,
		SUM(COALESCE(ps.current_stock, 0) * COALESCE(pp.cost_price, 0)) as category_value
		FROM categories c
		LEFT JOIN products p ON c.category_id = p.category_id AND p.is_archive = 0
		LEFT JOIN product_stock ps ON p.product_id = ps.product_id
		LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
		GROUP BY c.category_id, c.category_name
		HAVING category_value > 0
		ORDER BY category_value DESC
	");
	$inventoryValueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
	echo "Error: " . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MikeMadz - Admin Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <?php include 'includes/admin_styles.php'; ?>
    <style>
    .metric-card {
      background: white;
      border-radius: 16px;
      padding: 28px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
      transition: all 0.3s ease;
      height: 100%;
    }
    
    .metric-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
    }
    
    .metric-icon {
      width: 56px;
      height: 56px;
      border-radius: 12px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      color: white;
      margin-bottom: 16px;
    }
    
    .chart-container {
      background: white;
      border-radius: 16px;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
      border: 1px solid #e9ecef;
      overflow: hidden;
    }
    
    .chart-header {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
      padding: 20px 24px;
      border-bottom: 1px solid #e9ecef;
    }
    
    .chart-body {
      padding: 24px;
    }
    
    .status-badge {
      padding: 6px 12px;
      border-radius: 20px;
      font-size: 12px;
      font-weight: 600;
      text-transform: capitalize;
    }
    
    .table-modern {
      background: white;
      border-radius: 16px;
      overflow: hidden;
      box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
    }
    
    .table-modern thead {
      background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
    }
    
    .table-modern th {
      border: none;
      padding: 16px 20px;
      font-weight: 600;
      color: var(--dark-text);
    }
    
    .table-modern td {
      border: none;
      padding: 16px 20px;
      vertical-align: middle;
    }
    
    .page-title {
      color: var(--dark-text);
      font-weight: 700;
      margin-bottom: 8px;
    }
    
    .page-subtitle {
      color: #6c757d;
      font-size: 16px;
      margin-bottom: 32px;
    }
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content Area -->
  <main class="main-content" id="mainContent">
    <!-- Page Header -->
    <div class="mb-5">
      <h1 class="page-title">
        <i class="fas fa-tachometer-alt me-3" style="color: #7F1734;"></i>Dashboard 
      </h1>
     
    </div>

    <!-- Key Metrics Summary -->
    <div class="row g-3 mb-4">
      <div class="col-12">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-tachometer-alt me-2" style="color: #7F1734;"></i>Key Performance Indicators
            </h5>
            <small class="text-muted">Essential business metrics at a glance</small>
          </div>
          <div class="chart-body">
            <div class="row g-3">
              <div class="col-md-2 col-6">
                <div class="text-center">
                  <h4 class="fw-bold text-info mb-1"><?php echo number_format($totalProducts); ?></h4>
                  <small class="text-muted">Active Products</small>
                </div>
              </div>
              <div class="col-md-2 col-6">
                <div class="text-center">
                  <h4 class="fw-bold text-success mb-1">₱<?php echo number_format($totalCompletedSales, 0); ?></h4>
                  <small class="text-muted">Total Revenue</small>
                </div>
              </div>
              <div class="col-md-2 col-6">
                <div class="text-center">
                  <h4 class="fw-bold mb-1" style="color: #7F1734;"><?php echo number_format($totalUsers); ?></h4>
                  <small class="text-muted">Users</small>
                </div>
              </div>
              <div class="col-md-2 col-6">
                <div class="text-center">
                  <h4 class="fw-bold text-warning mb-1"><?php echo number_format($totalStock); ?></h4>
                  <small class="text-muted">In Stock</small>
                </div>
              </div>
              <div class="col-md-2 col-6">
                <div class="text-center">
                  <h4 class="fw-bold text-danger mb-1"><?php echo $lowStockProducts ? count($lowStockProducts) : 0; ?></h4>
                  <small class="text-muted">Low Stock</small>
                </div>
              </div>
              <div class="col-md-2 col-6">
                <div class="text-center">
                  <h4 class="fw-bold text-primary mb-1"><?php echo $movement_categories['Fast Moving']; ?></h4>
                  <small class="text-muted">Fast Moving</small>
                </div>
              </div>
            </div>
            <hr class="my-3">
            <div class="row g-3">
              <div class="col-md-3 col-6">
                <div class="text-center">
                  <h5 class="fw-bold text-warning mb-1"><?php echo $pendingOrders; ?></h5>
                  <small class="text-muted">Pending Orders</small>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="text-center">
                  <h5 class="fw-bold text-info mb-1"><?php echo $processingOrders; ?></h5>
                  <small class="text-muted">Processing</small>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="text-center">
                  <h5 class="fw-bold mb-1" style="color: #7F1734;"><?php echo $shippedOrders; ?></h5>
                  <small class="text-muted">Shipped</small>
                </div>
              </div>
              <div class="col-md-3 col-6">
                <div class="text-center">
                  <h5 class="fw-bold text-secondary mb-1"><?php echo $movement_categories['Non Moving']; ?></h5>
                  <small class="text-muted">Non-Moving</small>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 1: Daily Sales Trend (Full Width) -->
    <div class="row g-4 mb-5">
      <!-- Daily Sales Trend -->
      <div class="col-12">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-line me-2" style="color: #7F1734;"></i>Daily Sales Trend
            </h5>
            <small class="text-muted">Daily revenue and order count (last 30 days)</small>
          </div>
          <div class="chart-body">
            <canvas id="dailySalesChart" height="200"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 2: Product Distribution and User Registration Trend -->
    <div class="row g-4 mb-5">
      <!-- Product Distribution Pie Chart -->
      <div class="col-lg-4">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-pie me-2" style="color: #7F1734;"></i>Product Distribution
            </h5>
            <small class="text-muted">Products by category</small>
          </div>
          <div class="chart-body" style="height: 300px;">
            <canvas id="categoriesChart"></canvas>
          </div>
        </div>
      </div>

      <!-- User Registration Trend -->
      <div class="col-lg-8">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-user-plus me-2" style="color: #7F1734;"></i>User Registration Trend
            </h5>
            <small class="text-muted">New users per month (last 12 months)</small>
          </div>
          <div class="chart-body" style="height: 300px;">
            <canvas id="userRegistrationChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 3: Top Selling Products and Product Movement Analysis -->
    <div class="row g-4 mb-5">
      <!-- Top Selling Products Chart -->
      <div class="col-lg-6">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-trophy me-2" style="color: #7F1734;"></i>Top Selling Products
            </h5>
            <small class="text-muted">Best sellers in last 30 days</small>
          </div>
          <div class="chart-body">
            <canvas id="topSellingChart" height="200"></canvas>
          </div>
        </div>
      </div>

      <!-- Product Movement Analysis Chart -->
      <div class="col-lg-6">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-donut me-2" style="color: #7F1734;"></i>Product Movement Analysis
            </h5>
            <small class="text-muted">Product performance over last 30 days</small>
          </div>
          <div class="chart-body">
            <canvas id="movementAnalysisChart" height="200"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 4: Order Status Bar Chart (Full Width) -->
    <div class="row g-4 mb-5">
      <div class="col-12">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-bar me-2" style="color: #7F1734;"></i>Order Status Overview
            </h5>
            <small class="text-muted">Distribution of orders by current status</small>
          </div>
          <div class="chart-body">
            <canvas id="orderStatusChart" height="100"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Row 5: Inventory Value by Category (Full Width) -->
    <div class="row g-4 mb-5">
      <div class="col-12">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-bar me-2" style="color: #7F1734;"></i>Inventory Value by Category
            </h5>
            <small class="text-muted">Total inventory value distribution across categories</small>
          </div>
          <div class="chart-body">
            <canvas id="inventoryValueChart" height="200"></canvas>
          </div>
        </div>
      </div>
    </div>



    <!-- Data Tables -->
    <div class="row g-4">
      <!-- Recent Orders Table -->
      <div class="col-lg-8">
        <div class="table-modern">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">Latest Orders</h5>
            <small class="text-muted">Most recent customer orders</small>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Customer</th>
                  <th>Status</th>
                  <th>Order Date</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($recentOrders as $order): ?>
                <tr>
                  <td class="fw-semibold">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                  <td><?php echo htmlspecialchars($order['username']); ?></td>
                  <td>
                    <?php
                      $status = strtolower($order['status']);
                      $badgeClass = 'bg-secondary';
                      if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                      elseif ($status === 'to ship') $badgeClass = 'bg-info';
                      elseif ($status === 'shipped') $badgeClass = 'text-white';
                      elseif ($status === 'completed' || $status === 'delivered') $badgeClass = 'bg-success';
                    ?>
                    <span class="status-badge <?php echo $badgeClass; ?>" 
                          <?php if($status === 'shipped') echo 'style="background-color: #7F1734;"'; ?>>
                      <?php echo ucwords($order['status']); ?>
                    </span>
                  </td>
                  <td class="text-muted"><?php echo date('M j, Y g:i A', strtotime($order['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <!-- Low Stock Alert -->
      <div class="col-lg-4">
        <div class="table-modern">
          <div class="chart-header">
            <h5 class="fw-bold mb-0 text-danger">
              <i class="fas fa-exclamation-triangle me-2" style="color: #7F1734;"></i>Stock Alerts
            </h5>
            <small class="text-muted">Products running low</small>
          </div>
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead>
                <tr>
                  <th>Product Name</th>
                  <th class="text-center">Stock</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($lowStockProducts as $product): ?>
                <tr>
                  <td class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                  <td class="text-center">
                    <span class="badge bg-danger fs-6"><?php echo $product['stock']; ?></span>
                  </td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($lowStockProducts)): ?>
                <tr>
                  <td colspan="2" class="text-center text-muted py-4">
                    <i class="fas fa-check-circle text-success me-2"></i>All products have sufficient stock
                  </td>
                </tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  
  <?php include 'includes/admin_scripts.php'; ?>
  
  <script>
    // Chart configuration
    Chart.defaults.font.family = 'Inter, Segoe UI, sans-serif';
    Chart.defaults.color = '#6c757d';
    

    // Product Categories Pie Chart
    const categoriesCtx = document.getElementById('categoriesChart').getContext('2d');
    new Chart(categoriesCtx, {
        type: 'doughnut',
        data: {
            labels: [
                <?php 
                $categoryNames = [];
                $productCounts = [];
                $colors = ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14', '#20c997', '#e83e8c'];
                foreach ($categoriesData as $index => $data) {
                    $categoryNames[] = "'" . htmlspecialchars($data['category']) . "'";
                    $productCounts[] = intval($data['product_count']);
                }
                echo implode(', ', $categoryNames);
                ?>
            ],
            datasets: [{
                data: [<?php echo implode(', ', $productCounts); ?>],
                backgroundColor: [
                    <?php 
                    for ($i = 0; $i < count($productCounts); $i++) {
                        echo "'" . $colors[$i % count($colors)] . "'";
                        if ($i < count($productCounts) - 1) echo ", ";
                    }
                    ?>
                ],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverBorderWidth: 4,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        font: {
                            size: 12
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' products (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Order Status Bar Chart
    const orderStatusCtx = document.getElementById('orderStatusChart').getContext('2d');
    new Chart(orderStatusCtx, {
        type: 'bar',
        data: {
            labels: [
                <?php 
                $statusLabels = [];
                $statusCounts = [];
                $statusAmounts = [];
                foreach ($orderStatusData as $data) {
                    $statusLabels[] = "'" . ucwords($data['status']) . "'";
                    $statusCounts[] = intval($data['order_count']);
                    $statusAmounts[] = floatval($data['total_amount']);
                }
                echo implode(', ', $statusLabels);
                ?>
            ],
            datasets: [{
                label: 'Number of Orders',
                data: [<?php echo implode(', ', $statusCounts); ?>],
                backgroundColor: [
                    'rgba(255, 193, 7, 0.8)',   // Pending
                    'rgba(13, 202, 240, 0.8)',  // To Ship / Processing
                    'rgba(127, 23, 52, 0.8)',   // Shipped
                    'rgba(25, 135, 84, 0.8)',   // Completed/Delivered
                    'rgba(220, 53, 69, 0.8)',   // Cancelled
                ],
                borderColor: [
                    '#ffc107',
                    '#0dcaf0', 
                    '#7f1734',
                    '#198754',
                    '#dc3545',
                ],
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: [
                    'rgba(255, 193, 7, 1)',
                    'rgba(13, 202, 240, 1)',
                    'rgba(127, 23, 52, 1)',
                    'rgba(25, 135, 84, 1)',
                    'rgba(220, 53, 69, 1)',
                ]
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: true,
                    position: 'top',
                    labels: {
                        font: {
                            size: 13,
                            weight: 'bold'
                        },
                        padding: 20
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        afterLabel: function(context) {
                            const amounts = [<?php echo implode(', ', $statusAmounts); ?>];
                            if (amounts[context.dataIndex] > 0) {
                                return 'Total Value: ₱' + new Intl.NumberFormat('en-PH').format(amounts[context.dataIndex]);
                            }
                            return '';
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        stepSize: 1,
                        font: {
                            size: 12
                        },
                        padding: 10
                    }
                },
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    },
                    ticks: {
                        font: {
                            size: 12
                        },
                        padding: 10
                    }
                }
            },
            interaction: {
                intersect: false,
                mode: 'index'
            }
        }
    });

    // Product Movement Analysis Chart (Doughnut Chart)
    const movementAnalysisCtx = document.getElementById('movementAnalysisChart').getContext('2d');
    new Chart(movementAnalysisCtx, {
        type: 'doughnut',
        data: {
            labels: ['Fast Moving', 'Slow Moving', 'Non Moving'],
            datasets: [{
                data: [
                    <?php echo $movement_categories['Fast Moving']; ?>,
                    <?php echo $movement_categories['Slow Moving']; ?>,
                    <?php echo $movement_categories['Non Moving']; ?>
                ],
                backgroundColor: [
                    '#28a745',
                    '#ff5722',
                    '#6c757d'
                ],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverBorderWidth: 4,
                hoverOffset: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 20,
                        font: {
                            size: 12
                        },
                        usePointStyle: true,
                        pointStyle: 'circle'
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            const total = context.dataset.data.reduce((a, b) => a + b, 0);
                            const percentage = ((context.parsed / total) * 100).toFixed(1);
                            return context.label + ': ' + context.parsed + ' products (' + percentage + '%)';
                        }
                    }
                }
            }
        }
    });

    // Top Selling Products Chart (Horizontal Bar Chart)
    const topSellingCtx = document.getElementById('topSellingChart').getContext('2d');
    const topSellingData = <?php echo json_encode($topSellingProducts); ?>;
    const productNames = topSellingData.map(item => 
        item.product_name.length > 20 ? item.product_name.substring(0, 20) + '...' : item.product_name
    );
    const soldQuantities = topSellingData.map(item => parseInt(item.total_sold));

    new Chart(topSellingCtx, {
        type: 'bar',
        data: {
            labels: productNames,
            datasets: [{
                label: 'Units Sold',
                data: soldQuantities,
                backgroundColor: 'rgba(40, 167, 69, 0.8)',
                borderColor: '#28a745',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            indexAxis: 'y',
            scales: {
                x: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                },
                y: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12
                }
            }
        }
    });

    // Daily Sales Trend Chart (Line Chart)
    const dailySalesCtx = document.getElementById('dailySalesChart').getContext('2d');
    const dailySalesData = <?php echo json_encode($dailySalesData); ?>;
    const dates = dailySalesData.map(item => new Date(item.sale_date).toLocaleDateString('en-US', { month: 'short', day: 'numeric' })).reverse();
    const dailyRevenues = dailySalesData.map(item => parseFloat(item.daily_revenue)).reverse();
    const dailyOrderCounts = dailySalesData.map(item => parseInt(item.order_count)).reverse();

    new Chart(dailySalesCtx, {
        type: 'line',
        data: {
            labels: dates,
            datasets: [{
                label: 'Daily Revenue (₱)',
                data: dailyRevenues,
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Order Count',
                data: dailyOrderCounts,
                borderColor: '#28a745',
                backgroundColor: 'rgba(40, 167, 69, 0.1)',
                tension: 0.4,
                fill: false,
                yAxisID: 'y1'
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    type: 'linear',
                    display: true,
                    position: 'left',
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + new Intl.NumberFormat('en-PH').format(value);
                        }
                    }
                },
                y1: {
                    type: 'linear',
                    display: true,
                    position: 'right',
                    beginAtZero: true,
                    grid: {
                        drawOnChartArea: false,
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'top',
                    labels: {
                        padding: 20,
                        usePointStyle: true
                    }
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12
                }
            }
        }
    });

    // User Registration Trend Chart (Bar Chart)
    const userRegistrationCtx = document.getElementById('userRegistrationChart').getContext('2d');
    const userRegistrationData = <?php echo json_encode($userRegistrationData); ?>;
    
    // Ensure we have data for the last 12 months
    const allMonths = [];
    for (let i = 11; i >= 0; i--) {
        const month = new Date();
        month.setMonth(month.getMonth() - i);
        allMonths.push(month.toISOString().slice(0, 7));
    }
    
    const monthLabels = allMonths.map(month => {
        const date = new Date(month + '-01');
        return date.toLocaleDateString('en-US', { month: 'short', year: '2-digit' });
    });
    
    const userCounts = allMonths.map(month => {
        const data = userRegistrationData.find(item => item.month === month);
        return data ? parseInt(data.new_users) : 0;
    });

    new Chart(userRegistrationCtx, {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [{
                label: 'New Users',
                data: userCounts,
                backgroundColor: 'rgba(13, 202, 240, 0.8)',
                borderColor: '#0dcaf0',
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        stepSize: 1
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12
                }
            }
        }
    });

    // Inventory Value by Category Chart (Bar Chart)
    const inventoryValueCtx = document.getElementById('inventoryValueChart').getContext('2d');
    const inventoryValueData = <?php echo json_encode($inventoryValueData); ?>;
    const categoryNames = inventoryValueData.map(item => item.category_name);
    const categoryValues = inventoryValueData.map(item => parseFloat(item.category_value));

    new Chart(inventoryValueCtx, {
        type: 'bar',
        data: {
            labels: categoryNames,
            datasets: [{
                label: 'Inventory Value (₱)',
                data: categoryValues,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.8)',
                    'rgba(54, 162, 235, 0.8)',
                    'rgba(255, 205, 86, 0.8)',
                    'rgba(75, 192, 192, 0.8)',
                    'rgba(153, 102, 255, 0.8)',
                    'rgba(255, 159, 64, 0.8)',
                    'rgba(199, 199, 199, 0.8)',
                    'rgba(83, 102, 255, 0.8)'
                ],
                borderColor: [
                    '#FF6384',
                    '#36A2EB',
                    '#FFCE56',
                    '#4BC0C0',
                    '#9966FF',
                    '#FF9F40',
                    '#C7C7C7',
                    '#5366FF'
                ],
                borderWidth: 2,
                borderRadius: 4
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(0, 0, 0, 0.1)'
                    },
                    ticks: {
                        callback: function(value) {
                            return '₱' + new Intl.NumberFormat('en-PH').format(value);
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: 'rgba(0, 0, 0, 0.8)',
                    titleColor: '#ffffff',
                    bodyColor: '#ffffff',
                    cornerRadius: 8,
                    padding: 12,
                    callbacks: {
                        label: function(context) {
                            return 'Value: ₱' + new Intl.NumberFormat('en-PH').format(context.parsed.y);
                        }
                    }
                }
            }
        }
    });
  </script>
</body>
</html>
