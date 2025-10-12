
<?php 
include '../includes/db.php';
session_start();

// Ensure user is logged in and has admin access (any non-customer role)
if (!isset($_SESSION['username']) || $_SESSION['role'] === 'customer') {
	header("Location: login_admin.php");
	exit; 
}

try {
	// Basic statistics
	$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archive = 0");
	$totalProducts = $stmt->fetchColumn();

	// Get total sales from completed orders
	$stmt = $pdo->query("SELECT SUM(o.total_price) as total_sales 
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name = 'Completed'");
	$completedStats = $stmt->fetch(PDO::FETCH_ASSOC);
	$totalCompletedSales = $completedStats['total_sales'] ?: 0;

	// Get total pending sales (Pending/To Ship/Out for delivery)
	$stmt = $pdo->query("SELECT SUM(o.total_price)
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name IN ('Pending','To Ship','Out for delivery')");
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
		WHERE os.status_name = 'Out for delivery'");
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
		DATE_FORMAT(o.created_at, '%Y-%m') as month,
		SUM(o.total_price) as total_sales,
		COUNT(*) as order_count
		FROM orders o
		JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
		WHERE os.status_name = 'Completed' 
		AND o.created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
		GROUP BY DATE_FORMAT(o.created_at, '%Y-%m')
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

<?php
$page_title = 'MikeMadz - Admin Dashboard';
$page_description = 'Admin dashboard for managing MikeMadz frozen product store';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
  <title><?= htmlspecialchars($page_title) ?></title>
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
      <div class="page-header">
        <h2><i class="fas fa-tachometer-alt me-2"></i>Dashboard</h2>
      </div>

      <!-- Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-box"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo number_format($totalProducts); ?></h3>
              <p class="card-label">Active Products</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-peso-sign"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number">₱<?php echo number_format($totalCompletedSales, 0); ?></h3>
              <p class="card-label">Total Revenue</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-users"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo number_format($totalUsers); ?></h3>
              <p class="card-label">Total Users</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-warehouse"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo number_format($totalStock); ?></h3>
              <p class="card-label">In Stock</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Status Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $pendingOrders; ?></h3>
              <p class="card-label">Pending Orders</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-cog"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $processingOrders; ?></h3>
              <p class="card-label">Processing</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-truck"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $shippedOrders; ?></h3>
              <p class="card-label">Out for delivery</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-exclamation-triangle"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?php echo $lowStockProducts ? count($lowStockProducts) : 0; ?></h3>
              <p class="card-label">Low Stock</p>
            </div>
          </div>
        </div>
      </div>

      <!-- Daily Sales Trend -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-line me-2"></i>Daily Sales Trend
              </h5>
              <small class="text-muted">Daily revenue and order count (last 30 days)</small>
            </div>
            <div class="card-body">
              <canvas id="dailySalesChart" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Product Distribution and User Registration Trend -->
      <div class="row g-4 mb-4">
        <!-- Product Distribution Pie Chart -->
        <div class="col-lg-4">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-pie me-2"></i>Product Distribution
              </h5>
              <small class="text-muted">Products by category</small>
            </div>
            <div class="card-body" style="height: 300px;">
              <canvas id="categoriesChart"></canvas>
            </div>
          </div>
        </div>

        <!-- User Registration Trend -->
        <div class="col-lg-8">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-user-plus me-2"></i>User Registration Trend
              </h5>
              <small class="text-muted">New users per month (last 12 months)</small>
            </div>
            <div class="card-body" style="height: 300px;">
              <canvas id="userRegistrationChart"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Top Selling Products and Product Movement Analysis -->
      <div class="row g-4 mb-4">
        <!-- Top Selling Products Chart -->
        <div class="col-lg-6">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-trophy me-2"></i>Top Selling Products
              </h5>
              <small class="text-muted">Best sellers in last 30 days</small>
            </div>
            <div class="card-body">
              <canvas id="topSellingChart" height="200"></canvas>
            </div>
          </div>
        </div>

        <!-- Product Movement Analysis Chart -->
        <div class="col-lg-6">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-donut me-2"></i>Product Movement Analysis
              </h5>
              <small class="text-muted">Product performance over last 30 days</small>
            </div>
            <div class="card-body">
              <canvas id="movementAnalysisChart" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Order Status Bar Chart -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-bar me-2"></i>Order Status Overview
              </h5>
              <small class="text-muted">Distribution of orders by current status</small>
            </div>
            <div class="card-body">
              <canvas id="orderStatusChart" height="100"></canvas>
            </div>
          </div>
        </div>
      </div>

      <!-- Inventory Value by Category -->
      <div class="row g-4 mb-4">
        <div class="col-12">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-chart-bar me-2"></i>Inventory Value by Category
              </h5>
              <small class="text-muted">Total inventory value distribution across categories</small>
            </div>
            <div class="card-body">
              <canvas id="inventoryValueChart" height="200"></canvas>
            </div>
          </div>
        </div>
      </div>



      <!-- Data Tables -->
      <div class="row g-4">
        <!-- Recent Orders Table -->
        <div class="col-lg-8">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-list-alt me-2"></i>Latest Orders
              </h5>
              <small class="text-muted">Most recent customer orders</small>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold text-dark">Order ID</th>
                    <th class="fw-semibold text-dark">Customer</th>
                    <th class="fw-semibold text-dark">Status</th>
                    <th class="fw-semibold text-dark">Order Date</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($recentOrders as $order): ?>
                  <tr>
                    <td class="fw-semibold text-dark">#<?php echo str_pad($order['id'], 4, '0', STR_PAD_LEFT); ?></td>
                    <td><?php echo htmlspecialchars($order['username']); ?></td>
                    <td>
                      <?php
                        $status = strtolower($order['status']);
                        $badgeStyle = 'background: #f8f9fa; color: #6c757d;';
                        if ($status === 'pending') {
                          $badgeStyle = 'background: #fff3cd; color: #856404;';
                        } elseif ($status === 'to ship') {
                          $badgeStyle = 'background: #d1ecf1; color: #0c5460;';
                        } elseif ($status === 'shipped') {
                          $badgeStyle = 'background: #cce5ff; color: #004085;';
                        } elseif ($status === 'completed' || $status === 'delivered') {
                          $badgeStyle = 'background: #d4edda; color: #155724;';
                        } elseif ($status === 'cancelled') {
                          $badgeStyle = 'background: #f5c6cb; color: #721c24;';
                        }
                      ?>
                      <span class="badge" style="<?= $badgeStyle ?> border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
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
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-exclamation-triangle me-2"></i>Stock Alerts
              </h5>
              <small class="text-muted">Products running low</small>
            </div>
            <div class="table-responsive">
              <table class="table table-hover mb-0">
                <thead class="table-light">
                  <tr>
                    <th class="fw-semibold text-dark">Product Name</th>
                    <th class="fw-semibold text-dark text-center">Stock</th>
                  </tr>
                </thead>
                <tbody>
                  <?php foreach($lowStockProducts as $product): ?>
                  <tr>
                    <td class="fw-medium"><?php echo htmlspecialchars($product['name']); ?></td>
                    <td class="text-center">
                      <span class="badge fs-6" style="background: #f5c6cb; color: #721c24; border-radius: 15px; padding: 4px 8px;"><?php echo $product['stock']; ?></span>
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
                 $colors = ['#d4edda', '#f5c6cb', '#e2e3e5', '#fff3cd', '#d1ecf1', '#cce5ff', '#f8d7da', '#f8f9fa'];
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
                    'rgba(255, 243, 205, 0.8)',   // Pending - Light yellow
                    'rgba(209, 236, 241, 0.8)',   // To Ship / Processing - Light blue
                    'rgba(204, 229, 255, 0.8)',   // Out for delivery - Light blue
                    'rgba(212, 237, 218, 0.8)',   // Completed/Delivered - Light green
                    'rgba(245, 198, 203, 0.8)',   // Cancelled - Light red
                ],
                borderColor: [
                    '#856404',
                    '#0c5460', 
                    '#004085',
                    '#155724',
                    '#721c24',
                ],
                borderWidth: 2,
                borderRadius: 8,
                borderSkipped: false,
                hoverBackgroundColor: [
                    'rgba(255, 234, 167, 1)',   // Pending - Darker yellow
                    'rgba(190, 229, 235, 1)',   // To Ship - Darker blue
                    'rgba(179, 205, 255, 1)',   // Out for delivery - Darker blue
                    'rgba(195, 230, 203, 1)',   // Completed - Darker green
                    'rgba(240, 162, 169, 1)',   // Cancelled - Darker red
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
                    '#d4edda',   // Fast Moving - Light green
                    '#f5c6cb',   // Slow Moving - Light red
                    '#e2e3e5'    // Non Moving - Light gray
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
                backgroundColor: 'rgba(212, 237, 218, 0.8)',
                borderColor: '#155724',
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
                borderColor: '#004085',
                backgroundColor: 'rgba(204, 229, 255, 0.3)',
                tension: 0.4,
                fill: true,
                yAxisID: 'y'
            }, {
                label: 'Order Count',
                data: dailyOrderCounts,
                borderColor: '#155724',
                backgroundColor: 'rgba(212, 237, 218, 0.3)',
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
                backgroundColor: 'rgba(209, 236, 241, 0.8)',
                borderColor: '#0c5460',
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
                     'rgba(212, 237, 218, 0.8)',   // Light green
                     'rgba(245, 198, 203, 0.8)',   // Light red
                     'rgba(226, 227, 229, 0.8)',   // Light gray
                     'rgba(255, 243, 205, 0.8)',   // Light yellow
                     'rgba(209, 236, 241, 0.8)',   // Light blue
                     'rgba(204, 229, 255, 0.8)',   // Light blue
                     'rgba(248, 215, 218, 0.8)',   // Light pink
                     'rgba(248, 249, 250, 0.8)'    // Light gray
                 ],
                 borderColor: [
                     '#155724',   // Dark green
                     '#721c24',   // Dark red
                     '#383d41',   // Dark gray
                     '#856404',   // Dark yellow
                     '#0c5460',   // Dark blue
                     '#004085',   // Dark blue
                     '#721c24',   // Dark pink
                     '#6c757d'    // Dark gray
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
