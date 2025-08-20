
<?php
include 'db.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit;
}

// Handle order status updates
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];

    $stmt = $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?");
    $stmt->execute([$new_status, $order_id]);

    header("Location: orders_queue.php");
    exit;
}

// Fetch orders with filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "
    SELECT orders.*, users.username, users.email,
           GROUP_CONCAT(CONCAT(products.name, ' (', order_items.quantity, ')') SEPARATOR ', ') as items,
           orders.total_price as total_amount
    FROM orders
    INNER JOIN users ON orders.user_id = users.id
    LEFT JOIN order_items ON orders.id = order_items.order_id
    LEFT JOIN products ON order_items.product_id = products.id
    WHERE orders.status IS NOT NULL
";

if ($status_filter) {
    $query .= " AND orders.status = '$status_filter'";
}
if ($date_filter) {
    $query .= " AND DATE(orders.created_at) = '$date_filter'";
}
if ($search) {
    $query .= " AND (users.username LIKE '%$search%' OR orders.id LIKE '%$search%')";
}

$query .= " GROUP BY orders.id ORDER BY orders.created_at DESC";
$orders = $pdo->query($query)->fetchAll();

$totalDelivered = $pdo->query("SELECT COUNT(*) FROM delivered_orders")->fetchColumn();
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'Processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN status = 'Shipped' THEN 1 ELSE 0 END) as shipped_orders
    FROM orders
")->fetch();
$stats['delivered_orders'] = $totalDelivered;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaction Logs - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php include 'includes/admin_styles.php'; ?>
    <style>
      .stat-card {
        background-color: var(--card-bg) !important;
        border-radius: 12px;
        padding: 24px;
        box-shadow: var(--card-shadow) !important;
        border: 1px solid var(--border-color) !important;
        transition: transform 0.2s ease;
        color: var(--text-primary) !important;
      }
      
      .stat-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 20px rgba(0, 0, 0, 0.12);
      }
      
      .stat-icon {
        width: 48px;
        height: 48px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
      }
      
      .table-card {
        background-color: var(--card-bg) !important;
        border-radius: 12px;
        box-shadow: var(--card-shadow) !important;
        border: 1px solid var(--border-color) !important;
        color: var(--text-primary) !important;
      }
      
      .badge-status {
        padding: 6px 12px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.5px;
      }
      
      .filter-card {
        background-color: var(--card-bg) !important;
        border-radius: 12px;
        padding: 20px;
        box-shadow: var(--card-shadow) !important;
        border: 1px solid var(--border-color) !important;
        margin-bottom: 24px;
        color: var(--text-primary) !important;
      }
    </style>
  </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="mb-4">
      <h1 class="h3 fw-bold text-dark mb-2">
        <i class="fa fa-cart-shopping text-primary me-2"></i>Transaction Logs
      </h1>
    </div>

    <!-- Statistics Cards -->
    <div class="row g-4 mb-4">
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-info">
              <i class="fa fa-shopping-cart"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo $stats['total_orders']; ?></h4>
              <small class="text-muted text-uppercase">Total Orders</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-warning">
              <i class="fa fa-clock"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo $stats['pending_orders']; ?></h4>
              <small class="text-muted text-uppercase">Pending Orders</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-info">
              <i class="fa fa-cog"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo $stats['processing_orders']; ?></h4>
              <small class="text-muted text-uppercase">Processing</small>
            </div>
          </div>
        </div>
      </div>
      
      <div class="col-lg-3 col-md-6">
        <div class="stat-card">
          <div class="d-flex align-items-center">
            <div class="stat-icon bg-success">
              <i class="fa fa-check"></i>
            </div>
            <div class="ms-3">
              <h4 class="fw-bold mb-0"><?php echo $stats['delivered_orders']; ?></h4>
              <small class="text-muted text-uppercase">Completed</small>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Filters -->
    <div class="filter-card">
      <form class="row g-3" method="get">
        <div class="col-md-3">
          <select name="status" class="form-select">
            <option value="">All Statuses</option>
            <option value="Pending" <?= $status_filter == 'Pending' ? 'selected' : '' ?>>Pending</option>
            <option value="Processing" <?= $status_filter == 'Processing' ? 'selected' : '' ?>>Processing</option>
            <option value="Shipped" <?= $status_filter == 'Shipped' ? 'selected' : '' ?>>Shipped</option>
            <option value="Completed" <?= $status_filter == 'Completed' ? 'selected' : '' ?>>Completed</option>
          </select>
        </div>
        <div class="col-md-3">
          <input type="date" name="date" class="form-control" value="<?= $date_filter ?>">
        </div>
        <div class="col-md-4">
          <input type="text" name="search" class="form-control" placeholder="Search orders..." value="<?= $search ?>">
        </div>
        <div class="col-md-2">
          <button type="submit" class="btn w-100" style="background-color: var(--bs-secondary); color: white;">
            <i class="fa fa-search me-1"></i>Filter
          </button>
        </div>
      </form>
    </div>

    <!-- Orders Table -->
    <div class="table-card">
      <div class="card-header bg-transparent border-0 p-4">
        <h5 class="fw-bold mb-0">Order Transactions</h5>
      </div>
      <div class="table-responsive">
        <table class="table table-hover mb-0">
          <thead class="table-light">
            <tr>
              <th class="fw-semibold">Order ID</th>
              <th class="fw-semibold">Customer</th>
              <th class="fw-semibold">Contact</th>
              <th class="fw-semibold">Items</th>
              <th class="fw-semibold">Total</th>
              <th class="fw-semibold">Status</th>
              <th class="fw-semibold">Date</th>
              <th class="fw-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="8" class="text-center py-5">
                  <i class="fa fa-inbox text-muted mb-3" style="font-size: 3rem;"></i>
                  <div class="text-muted">No orders found.</div>
                </td>
              </tr>
            <?php else: ?>
              <?php foreach ($orders as $order): ?>
              <tr>
                <td class="fw-semibold">#<?= $order['id'] ?></td>
                <td><?= htmlspecialchars($order['username']) ?></td>
                <td>
                  <small class="text-muted"><?= htmlspecialchars($order['email']) ?></small>
                </td>
                <td>
                  <small><?= htmlspecialchars($order['items']) ?></small>
                </td>
                <td class="fw-bold">₱<?= number_format($order['total_amount'], 2) ?></td>
                <td>
                  <?php
                    $status = strtolower($order['status']);
                    $badgeClass = 'bg-secondary';
                    if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                    elseif ($status === 'processing') $badgeClass = 'bg-info';
                    elseif ($status === 'shipped') $badgeClass = 'text-white';
                    elseif ($status === 'delivered' || $status === 'completed') $badgeClass = 'bg-success';
                  ?>
                  <span class="badge-status <?= $badgeClass ?>" <?php if($status === 'shipped') echo 'style="background-color: var(--bs-secondary);"'; ?>>
                    <?= htmlspecialchars($order['status']) ?>
                  </span>
                </td>
                <td class="text-muted"><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                <td>
                  <div class="btn-group" role="group">
                    <?php if ($order['status'] == 'Pending'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="new_status" value="Processing">
                        <button type="submit" name="update_status" class="btn btn-sm btn-info">Process</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'Processing'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="new_status" value="Shipped">
                        <button type="submit" name="update_status" class="btn btn-sm btn-warning">Ship</button>
                      </form>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'Shipped'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="new_status" value="Delivered">
                        <button type="submit" name="update_status" class="btn btn-sm btn-success">Deliver</button>
                      </form>
                    <?php endif; ?>
                  </div>
                </td>
              </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
</body>
</html>
