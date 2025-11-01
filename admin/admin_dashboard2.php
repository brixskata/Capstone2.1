
<?php 
include '../includes/db.php';
include '../includes/permissions.php';
session_start();

// Always prevent caching for this page
header("Cache-Control: no-cache, no-store, must-revalidate");
header("Pragma: no-cache");
header("Expires: 0");

// Ensure user is logged in and has admin access
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit; 
}

// Check if user is a customer (deny access)
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Check if user is super admin (only super admin can access dashboard)
if (!isSuperAdmin($pdo)) {
    $_SESSION['error'] = "Only Super Admin can access the dashboard.";
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

  // Fetch brand-based stock levels using the same logic as stock_levels.php
  // Use the ReorderPointCalculator to obtain brand group data and classify each brand
  include_once __DIR__ . '/../includes/reorder_point_calculator.php';
  $ropCalculator = new ReorderPointCalculator($pdo);
  $brandRows = [];
  try {
    $brandRows = $ropCalculator->getBrandStockData();
  } catch (Exception $e) {
    // fallback to empty array if something goes wrong
    $brandRows = [];
  }
  $inStockBrands = 0;
  $lowStockBrands = 0;
  $outOfStockBrands = 0;
  $totalBrands = count($brandRows);
  foreach ($brandRows as $b) {
    $avg_rop = isset($b['avg_reorder_point']) ? (float)$b['avg_reorder_point'] : 0.0;
    $total_stock = isset($b['total_stock']) ? (float)$b['total_stock'] : 0.0;
    $status = $ropCalculator->getStockStatus($total_stock, $avg_rop);
    if ($status === 'Out of Stock') {
      $outOfStockBrands++;
    } elseif ($status === 'Low Stock') {
      $lowStockBrands++;
    } else {
      $inStockBrands++;
    }
  }

  // Order status counts
  $stmt = $pdo->query("SELECT COUNT(*)
    FROM orders o
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
    WHERE os.status_name = 'Pending'");
  $pendingOrders = $stmt->fetchColumn();

  // Pickup orders: match common pickup status names (case-insensitive)
  $stmt = $pdo->query("SELECT COUNT(*)
    FROM orders o
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
    WHERE LOWER(os.status_name) LIKE '%pick%'");
  $pickupOrders = $stmt->fetchColumn();

  $stmt = $pdo->query("SELECT COUNT(*)
    FROM orders o
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
    WHERE os.status_name = 'Out for delivery'");
  $shippedOrders = $stmt->fetchColumn();

  // Total stock (sum of product_stock.current_stock for active products)
  $stmt = $pdo->query("SELECT COALESCE(SUM(ps.current_stock),0) as total_stock
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    WHERE p.is_archive = 0");
  $ts = $stmt->fetch(PDO::FETCH_ASSOC);
  $totalStock = $ts['total_stock'] ?? 0;

  // Low stock count: products where current_stock <= reorder_point (use product_stock.reorder_point or default 10)
  $stmt = $pdo->query("SELECT COUNT(DISTINCT p.product_id) as low_count
    FROM products p
    LEFT JOIN product_stock ps ON p.product_id = ps.product_id
    WHERE p.is_archive = 0
    AND COALESCE(ps.current_stock, 0) <= COALESCE(ps.reorder_point, 10)");
  $lc = $stmt->fetch(PDO::FETCH_ASSOC);
  $lowStockCount = $lc['low_count'] ?? 0;

	// Number of orders today
	$stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()");
	$ordersToday = $stmt->fetchColumn();

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

  // Customer/member analytics
  // Customer/member analytics
  // Active customers: match any user_type role containing 'customer' (case-insensitive), active, and has an email in user_info
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN user_type ut ON u.usertype_id = ut.usertype_id LEFT JOIN user_info ui ON ui.user_id = u.user_id WHERE LOWER(ut.role) LIKE '%customer%' AND (u.is_active IS NULL OR u.is_active = 1) AND COALESCE(ui.email, '') <> ''");
  $stmt->execute();
  $activeCustomers = (int)$stmt->fetchColumn();

  // Active registered customers: any user_type role containing 'customer' (case-insensitive) with email_verified = 1 and active
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN user_type ut ON u.usertype_id = ut.usertype_id WHERE LOWER(ut.role) LIKE '%customer%' AND u.email_verified = 1 AND (u.is_active IS NULL OR u.is_active = 1)");
  $stmt->execute();
  $activeRegisteredCustomers = (int)$stmt->fetchColumn();

  // Active members: count all users whose role is not 'customer' (active)
  $stmt = $pdo->prepare("SELECT COUNT(*) FROM users u JOIN user_type ut ON u.usertype_id = ut.usertype_id WHERE (ut.role IS NULL OR ut.role != 'customer') AND (u.is_active IS NULL OR u.is_active = 1)");
  $stmt->execute();
  $activeMembers = (int)$stmt->fetchColumn();

  // Order status data removed — chart omitted from dashboard

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

  // Customer registration trend removed — chart omitted from dashboard

  // Inventory value data removed — chart omitted from dashboard

  // Get pending ID verifications
  $stmt = $pdo->query("SELECT COUNT(*) FROM customer_id_verification WHERE status = 'pending'");
  $pendingIdVerifications = $stmt->fetchColumn() ?: 0;

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
        
        /* Mobile Responsive Styles */
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
            
            /* Analytics Cards Mobile */
            .analytics-card {
                padding: 1rem;
                gap: 0.75rem;
            }
            
            .card-icon {
                width: 50px;
                height: 50px;
                font-size: 1.25rem;
            }
            
            .card-number {
                font-size: 1.5rem;
            }
            
            .card-label {
                font-size: 0.8rem;
            }
            
            /* Table Cards Mobile */
            .table-card .card-header {
                padding: 1rem;
            }
            
            .table-card .card-body {
                padding: 1rem;
            }
            
            /* Table Responsive */
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table {
                min-width: 600px;
            }
            
            /* Chart Containers Mobile */
            .table-card .card-body[style*="height"] {
                height: 250px !important;
            }
            
            /* Customer Analytics Mobile */
            .analytics-card[style*="padding:12px"] {
                padding: 0.75rem !important;
                gap: 0.5rem !important;
            }
            
            .analytics-card[style*="padding:12px"] .card-icon {
                width: 40px !important;
                height: 40px !important;
                font-size: 1rem !important;
            }
            
            .analytics-card[style*="padding:12px"] .card-number {
                font-size: 1.1rem !important;
            }
            
            .analytics-card[style*="padding:12px"] .card-label {
                font-size: 0.75rem !important;
            }
            
            /* Badge Mobile */
            .badge {
                font-size: 0.65rem !important;
                padding: 3px 6px !important;
            }
            
            /* Chart Height Adjustments */
            canvas {
                max-height: 200px !important;
            }
        }
        
        /* Extra Small Devices */
        @media (max-width: 576px) {
            .main-container {
                padding: 0.75rem;
            }
            
            .page-header {
                padding: 1rem;
            }
            
            .page-header h2 {
                font-size: 1.25rem;
            }
            
            .analytics-card {
                padding: 0.75rem;
                gap: 0.5rem;
            }
            
            .card-icon {
                width: 45px;
                height: 45px;
                font-size: 1.1rem;
            }
            
            .card-number {
                font-size: 1.25rem;
            }
            
            .card-label {
                font-size: 0.75rem;
            }
            
            .table-card .card-header {
                padding: 0.75rem;
            }
            
            .table-card .card-body {
                padding: 0.75rem;
            }
            
            .table-card .card-body[style*="height"] {
                height: 200px !important;
            }
            
            canvas {
                max-height: 150px !important;
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

      <!-- TOP Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <a href="transaction_logs.php?status=pending" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-clock"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo $pendingOrders; ?></h3>
                <p class="card-label">Pendings</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-3">
          <a href="transaction_logs.php?status=pickup" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-cog"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo $pickupOrders; ?></h3>
                <p class="card-label">Pickup</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-3">
          <a href="reports.php?section=orders" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-shopping-cart"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo $ordersToday; ?></h3>
                <p class="card-label">Number of Orders Today</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-3">
          <a href="reports.php?section=yearly_sales" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-peso-sign"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number">₱<?php echo number_format($totalCompletedSales, 0); ?></h3>
                <p class="card-label">Total Sales</p>
              </div>
            </div>
          </a>
        </div>
      </div>

      <!-- BOTTOM Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <a href="products.php" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-box"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo number_format($totalProducts); ?></h3>
                <p class="card-label">Active products</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-3">
          <a href="id_verification_management.php" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-id-card"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo $pendingIdVerifications; ?></h3>
                <p class="card-label">Pending ID Verification</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-3">
          <a href="stock_levels.php" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-exclamation-triangle"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo $lowStockBrands; ?></h3>
                <p class="card-label">Low stock (brands)</p>
              </div>
            </div>
          </a>
        </div>
        <div class="col-md-3">
          <a href="stock_levels.php" class="text-decoration-none">
            <div class="analytics-card">
              <div class="card-icon">
                <i class="fas fa-times-circle"></i>
              </div>
              <div class="card-content">
                <h3 class="card-number"><?php echo $outOfStockBrands; ?></h3>
                <p class="card-label">Out of Stock</p>
              </div>
            </div>
          </a>
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

      <!-- removed duplicate individual customer/member analytics cards -->

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

        <!-- Customer / Member Analytics (side card) -->
        <div class="col-lg-4">
          <div class="table-card">
            <div class="card-header bg-transparent border-0 p-4">
              <h5 class="fw-bold mb-0 text-dark">
                <i class="fas fa-user-friends me-2"></i>Customer / Member Analytics
              </h5>
              <small class="text-muted">Active counts</small>
            </div>
            <div class="card-body" style="height:300px; display:flex; align-items:stretch; justify-content:center;">
              <div class="w-100" style="display:flex; flex-direction:column; gap:12px;">
                <a href="manage_users.php?role=customer" class="text-decoration-none">
                  <div class="analytics-card" style="padding:12px; display:flex; gap:0.75rem; align-items:center; flex:1;">
                    <div class="card-icon" style="width:48px; height:48px; font-size:1.1rem;"><i class="fas fa-users"></i></div>
                    <div class="card-content">
                      <h4 class="card-number" style="font-size:1.25rem; margin:0;"><?php echo $activeCustomers; ?></h4>
                      <p class="card-label" style="margin:0;">Active Customers</p>
                    </div>
                  </div>
                </a>

                <a href="manage_users.php?filter=registered" class="text-decoration-none">
                  <div class="analytics-card" style="padding:12px; display:flex; gap:0.75rem; align-items:center; flex:1;">
                    <div class="card-icon" style="width:48px; height:48px; font-size:1.1rem;"><i class="fas fa-user-check"></i></div>
                    <div class="card-content">
                      <h4 class="card-number" style="font-size:1.25rem; margin:0;"><?php echo $activeRegisteredCustomers; ?></h4>
                      <p class="card-label" style="margin:0;">Active Registered</p>
                    </div>
                  </div>
                </a>

                <a href="manage_users.php?role=member" class="text-decoration-none">
                  <div class="analytics-card" style="padding:12px; display:flex; gap:0.75rem; align-items:center; flex:1;">
                    <div class="card-icon" style="width:48px; height:48px; font-size:1.1rem;"><i class="fas fa-id-badge"></i></div>
                    <div class="card-content">
                      <h4 class="card-number" style="font-size:1.25rem; margin:0;"><?php echo $activeMembers; ?></h4>
                      <p class="card-label" style="margin:0;">Active Members</p>
                    </div>
                  </div>
                </a>
              </div>
            </div>
          </div>
        </div>

        <!-- User Registration Trend removed -->
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

      <!-- Order Status Overview removed -->

      <!-- Inventory Value by Category removed -->



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

  // Order status chart removed

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

  // User registration chart removed

  // Inventory value chart removed
  </script>
</body>
</html>
