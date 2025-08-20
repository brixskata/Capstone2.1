
<?php 
include 'db.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit; 
}

try {
    // Basic statistics
    $stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE is_archived = 0");
    $totalProducts = $stmt->fetchColumn();

    // Get total sales from delivered orders
    $stmt = $pdo->query("SELECT SUM(total_price) as total_sales FROM delivered_orders");
    $deliveredStats = $stmt->fetch(PDO::FETCH_ASSOC);
    $totalCompletedSales = $deliveredStats['total_sales'] ?: 0;

    // Get total pending sales
    $stmt = $pdo->query("SELECT SUM(total_price) FROM orders WHERE status IN ('Pending', 'Processing', 'Shipped')");
    $totalPendingSales = $stmt->fetchColumn() ?: 0;

    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $totalUsers = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT SUM(stock) FROM products WHERE is_archived = 0");
    $totalStock = $stmt->fetchColumn();

    // Order status counts
    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'");
    $pendingOrders = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'processing'");
    $processingOrders = $stmt->fetchColumn();

    $stmt = $pdo->query("SELECT COUNT(*) FROM orders WHERE status = 'shipped'");
    $shippedOrders = $stmt->fetchColumn();

    // Recent orders
    $stmt = $pdo->query("SELECT o.id, u.username, o.status, o.created_at 
                        FROM orders o 
                        JOIN users u ON o.user_id = u.id 
                        ORDER BY o.created_at DESC 
                        LIMIT 5");
    $recentOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Low stock products
    $stmt = $pdo->query("SELECT name, stock FROM products 
                        WHERE stock < 10 AND is_archived = 0 
                        ORDER BY stock ASC 
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
        c.name as category,
        COUNT(p.id) as product_count,
        SUM(p.stock) as total_stock
        FROM categories c
        LEFT JOIN products p ON c.id = p.category_id AND p.is_archived = 0
        GROUP BY c.id, c.name
        HAVING product_count > 0
        ORDER BY product_count DESC");
    $categoriesData = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Order status data for bar chart
    $stmt = $pdo->query("SELECT 
        status,
        COUNT(*) as order_count,
        SUM(total_price) as total_amount
        FROM orders 
        GROUP BY status 
        ORDER BY order_count DESC");
    $orderStatusData = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
        <i class="fas fa-tachometer-alt text-primary me-3"></i>Dashboard Analytics
      </h1>
     
    </div>

    <!-- Key Metrics Cards -->
    <div class="row g-4 mb-5">
      <div class="col-xl-3 col-md-6">
        <div class="metric-card">
          <div class="metric-icon bg-info">
            <i class="fas fa-box"></i>
          </div>
          <h3 class="fw-bold mb-1"><?php echo number_format($totalProducts); ?></h3>
          <p class="text-muted mb-0">Active Products</p>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="metric-card">
          <div class="metric-icon bg-success">
            <i class="fas fa-peso-sign"></i>
          </div>
          <h3 class="fw-bold mb-1">₱<?php echo number_format($totalCompletedSales, 2); ?></h3>
          <p class="text-muted mb-0">Total Revenue</p>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="metric-card">
          <div class="metric-icon" style="background-color: var(--primary-color);">
            <i class="fas fa-users"></i>
          </div>
          <h3 class="fw-bold mb-1"><?php echo number_format($totalUsers); ?></h3>
          <p class="text-muted mb-0">Registered Users</p>
        </div>
      </div>
      
      <div class="col-xl-3 col-md-6">
        <div class="metric-card">
          <div class="metric-icon bg-warning">
            <i class="fas fa-cubes"></i>
          </div>
          <h3 class="fw-bold mb-1"><?php echo number_format($totalStock); ?></h3>
          <p class="text-muted mb-0">Items in Stock</p>
        </div>
      </div>
    </div>

    <!-- Analytics Charts - Moved to Top -->
    <div class="row g-4 mb-5">
      <!-- Sales Trend Line Chart -->
      <div class="col-lg-8">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-line text-success me-2"></i>Sales Trend Analysis
            </h5>
            <small class="text-muted">Monthly revenue over the last 12 months</small>
          </div>
          <div class="chart-body">
            <canvas id="salesTrendChart" height="120"></canvas>
          </div>
        </div>
      </div>

      <!-- Product Categories Pie Chart -->
      <div class="col-lg-4">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-pie text-warning me-2"></i>Product Distribution
            </h5>
            <small class="text-muted">Products by category</small>
          </div>
          <div class="chart-body">
            <canvas id="categoriesChart"></canvas>
          </div>
        </div>
      </div>
    </div>

    <!-- Order Status Overview -->
    <div class="row g-4 mb-5">
      <div class="col-md-4">
        <div class="metric-card text-center">
          <div class="metric-icon bg-warning mx-auto">
            <i class="fas fa-clock"></i>
          </div>
          <h4 class="fw-bold"><?php echo $pendingOrders; ?></h4>
          <p class="text-muted">Pending Orders</p>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="metric-card text-center">
          <div class="metric-icon bg-info mx-auto">
            <i class="fas fa-spinner"></i>
          </div>
          <h4 class="fw-bold"><?php echo $processingOrders; ?></h4>
          <p class="text-muted">Processing Orders</p>
        </div>
      </div>
      
      <div class="col-md-4">
        <div class="metric-card text-center">
          <div class="metric-icon mx-auto" style="background-color: var(--primary-color);">
            <i class="fas fa-shipping-fast"></i>
          </div>
          <h4 class="fw-bold"><?php echo $shippedOrders; ?></h4>
          <p class="text-muted">Shipped Orders</p>
        </div>
      </div>
    </div>

    <!-- Order Status Bar Chart -->
    <div class="row g-4 mb-5">
      <div class="col-12">
        <div class="chart-container">
          <div class="chart-header">
            <h5 class="fw-bold mb-0">
              <i class="fas fa-chart-bar text-info me-2"></i>Order Status Overview
            </h5>
            <small class="text-muted">Distribution of orders by current status</small>
          </div>
          <div class="chart-body">
            <canvas id="orderStatusChart" height="100"></canvas>
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
                      elseif ($status === 'processing') $badgeClass = 'bg-info';
                      elseif ($status === 'shipped') $badgeClass = 'text-white';
                      elseif ($status === 'delivered') $badgeClass = 'bg-success';
                    ?>
                    <span class="status-badge <?php echo $badgeClass; ?>" 
                          <?php if($status === 'shipped') echo 'style="background-color: var(--primary-color);"'; ?>>
                      <?php echo ucfirst($order['status']); ?>
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
              <i class="fas fa-exclamation-triangle me-2"></i>Stock Alerts
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
    
    // Sales Trend Line Chart
    const salesCtx = document.getElementById('salesTrendChart').getContext('2d');
    new Chart(salesCtx, {
        type: 'line',
        data: {
            labels: [
                <?php 
                $months = [];
                $sales = [];
                // Ensure we have data for the last 12 months, filling gaps with 0
                $allMonths = [];
                for ($i = 11; $i >= 0; $i--) {
                    $month = date('Y-m', strtotime("-$i months"));
                    $allMonths[$month] = 0;
                }
                
                foreach ($salesData as $data) {
                    $allMonths[$data['month']] = floatval($data['total_sales']);
                }
                
                foreach ($allMonths as $month => $sale) {
                    $months[] = "'" . date('M Y', strtotime($month . '-01')) . "'";
                    $sales[] = $sale;
                }
                echo implode(', ', $months);
                ?>
            ],
            datasets: [{
                label: 'Monthly Revenue (₱)',
                data: [<?php echo implode(', ', $sales); ?>],
                borderColor: '#198754',
                backgroundColor: 'rgba(25, 135, 84, 0.1)',
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: '#198754',
                pointBorderColor: '#ffffff',
                pointBorderWidth: 3,
                pointRadius: 6,
                pointHoverRadius: 8,
                pointHoverBackgroundColor: '#198754',
                pointHoverBorderColor: '#ffffff',
                pointHoverBorderWidth: 3
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
                    padding: 12
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
                        callback: function(value) {
                            return '₱' + new Intl.NumberFormat('en-PH').format(value);
                        },
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
            maintainAspectRatio: true,
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
                    $statusLabels[] = "'" . ucfirst($data['status']) . "'";
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
                    'rgba(255, 193, 7, 0.8)',   // Warning - Pending
                    'rgba(13, 202, 240, 0.8)',  // Info - Processing  
                    'rgba(127, 23, 52, 0.8)',   // Primary - Shipped
                    'rgba(25, 135, 84, 0.8)',   // Success - Delivered
                    'rgba(220, 53, 69, 0.8)',   // Danger - Cancelled
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
  </script>
</body>
</html>
