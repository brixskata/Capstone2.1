<?php
include 'db.php';
session_start();

// Ensure user is logged in and has admin access (Super Admin or Admin)
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

// Handle order status updates
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];

    // Map status name to orderstatus_id and update
    $stmt = $pdo->prepare("UPDATE orders o
                            JOIN order_status os ON os.status_name = :status
                            SET o.orderstatus_id = os.orderstatus_id
                            WHERE o.orders_id = :order_id");
    $stmt->execute(['status' => $new_status, 'order_id' => $order_id]);

    header("Location: transaction_logs.php");
    exit;
}

// Fetch orders with filtering
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';
$search = isset($_GET['search']) ? $_GET['search'] : '';

$query = "
    SELECT o.orders_id AS id,
           u.username,
           ui.email,
           os.status_name AS status,
           o.total_price as total_amount,
           o.created_at,
           pay.method as payment_method,
           pay.proof as payment_proof,
           GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    LEFT JOIN user_info ui ON ui.user_id = u.user_id
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
    LEFT JOIN order_items oi ON o.orders_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN payments pay ON pay.orders_id = o.orders_id
    WHERE os.status_name IS NOT NULL
";

if ($status_filter) {
    $query .= " AND os.status_name = '" . str_replace("'", "''", $status_filter) . "'";
}
if ($date_filter) {
    $query .= " AND DATE(o.created_at) = '" . str_replace("'", "''", $date_filter) . "'";
}
if ($search) {
    $s = str_replace("'", "''", $search);
    $query .= " AND (u.username LIKE '%$s%' OR o.orders_id LIKE '%$s%')";
}

$query .= " GROUP BY o.orders_id, u.username, ui.email, os.status_name, o.total_price, o.created_at, pay.method, pay.proof ORDER BY o.created_at DESC";

// Debug: Check the query
echo "<!-- Debug Query: " . htmlspecialchars($query) . " -->\n";

try {
    $orders = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    echo "<!-- SQL Error: " . htmlspecialchars($e->getMessage()) . " -->\n";
    $orders = [];
}

// Debug: Check what payment data we're getting
echo "<!-- Debug: Payment data -->\n";
foreach ($orders as $order) {
    echo "<!-- Order {$order['id']}: method='" . ($order['payment_method'] ?? 'NULL') . "', proof='" . ($order['payment_proof'] ?? 'NULL') . "' -->\n";
}

$totalDelivered = $pdo->query("SELECT COUNT(*) FROM delivered_orders")->fetchColumn();
$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN os.status_name = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN os.status_name = 'To Ship' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN os.status_name = 'Shipped' THEN 1 ELSE 0 END) as shipped_orders
    FROM orders o
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
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
      
      .action-btn {
        font-size: 0.75rem;
        padding: 0.375rem 0.75rem;
        border-radius: 6px;
        font-weight: 500;
        transition: all 0.2s ease;
        border: none;
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        text-decoration: none;
      }
      
      .action-btn:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        color: inherit;
      }
      
      .action-btn:active {
        transform: translateY(0);
      }
      
      .btn-process {
        background-color: #0d6efd;
        color: white;
      }
      
      .btn-process:hover {
        background-color: #0b5ed7;
        color: white;
      }
      
      .btn-ship {
        background-color: #ffc107;
        color: #000;
      }
      
      .btn-ship:hover {
        background-color: #ffca2c;
        color: #000;
      }
      
      .btn-deliver {
        background-color: #198754;
        color: white;
      }
      
      .btn-deliver:hover {
        background-color: #157347;
        color: white;
      }
      
      .btn-completed {
        background-color: #6c757d;
        color: white;
        cursor: default;
      }
      
      .btn-completed:hover {
        background-color: #6c757d;
        color: white;
        transform: none;
        box-shadow: none;
      }
    </style>
</head>
<body>
  <?php include 'includes/admin_navbar.php'; ?>
  <?php include 'includes/admin_sidebar.php'; ?>

  <!-- Main Content -->
  <main class="main-content" id="mainContent">
    <div class="mb-4">
      <h1 class="h3 fw-bold text-dark mb-2">
        <i class="fa fa-cart-shopping me-2" style="color: #7F1734;"></i>Transaction Logs
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
            <option value="To Ship" <?= $status_filter == 'To Ship' ? 'selected' : '' ?>>To Ship</option>
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
          <button type="submit" class="btn w-100" style="background-color: #7F1734; color: white;">
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
              <th class="fw-semibold">Payment</th>
              <th class="fw-semibold">Status</th>
              <th class="fw-semibold">Date</th>
              <th class="fw-semibold">Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($orders)): ?>
              <tr>
                <td colspan="9" class="text-center py-5">
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
                  <small class="text-muted"><?= htmlspecialchars($order['email'] ?? '') ?></small>
                </td>
                <td>
                  <small><?= htmlspecialchars($order['items']) ?></small>
                </td>
                <td class="fw-bold">₱<?= number_format($order['total_amount'], 2) ?></td>
                <td>
                  <div class="d-flex flex-column gap-1">
                    <?php if ($order['payment_method']): ?>
                      <span class="badge bg-primary"><?= htmlspecialchars($order['payment_method']) ?></span>
                      <?php if ($order['payment_method'] == 'Gcash' && $order['payment_proof']): ?>
                        <button class="btn btn-sm btn-outline-info" onclick="viewPaymentProof(<?= $order['id'] ?>, '<?= htmlspecialchars($order['payment_proof']) ?>')">
                          <i class="fas fa-image me-1"></i>View Proof
                        </button>
                      <?php endif; ?>
                    <?php else: ?>
                      <span class="badge bg-secondary">Cash On Delivery</span>
                      
                    <?php endif; ?>
                  </div>
                </td>
                <td>
                  <?php
                    $status = strtolower($order['status']);
                    $badgeClass = 'bg-secondary';
                    if ($status === 'pending') $badgeClass = 'bg-warning text-dark';
                    elseif ($status === 'to ship') $badgeClass = 'bg-info';
                    elseif ($status === 'shipped') $badgeClass = 'text-white';
                    elseif ($status === 'delivered' || $status === 'completed') $badgeClass = 'bg-success';
                  ?>
                  <span class="badge-status <?= $badgeClass ?>" <?php if($status === 'shipped') echo 'style="background-color: #7F1734;"'; ?>>
                    <?= htmlspecialchars($order['status']) ?>
                  </span>
                </td>
                <td class="text-muted"><?= date('M d, Y H:i', strtotime($order['created_at'])) ?></td>
                <td>
                  <div class="d-flex gap-2">
                    <?php if ($order['status'] == 'Pending'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="new_status" value="To Ship">
                        <button type="submit" name="update_status" class="action-btn btn-process">
                          <i class="fas fa-cog me-1"></i>Process
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'To Ship'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="new_status" value="Shipped">
                        <button type="submit" name="update_status" class="action-btn btn-ship">
                          <i class="fas fa-truck me-1"></i>Ship
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'Shipped'): ?>
                      <form method="POST" class="d-inline">
                        <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                        <input type="hidden" name="new_status" value="Completed">
                        <button type="submit" name="update_status" class="action-btn btn-deliver">
                          <i class="fas fa-check me-1"></i>Deliver
                        </button>
                      </form>
                    <?php endif; ?>
                    <?php if ($order['status'] == 'Completed'): ?>
                      <span class="action-btn btn-completed">
                        <i class="fas fa-check-circle me-1"></i>Completed
                      </span>
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

  <!-- Payment Proof Modal -->
  <div class="modal fade" id="paymentProofModal" tabindex="-1" aria-labelledby="paymentProofModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="paymentProofModalLabel">
            <i class="fas fa-credit-card me-2"></i>Payment Proof - Order #<span id="modalOrderId"></span>
            <?php if (isset($_SESSION['usertype_id']) && $_SESSION['usertype_id'] == 1): ?>
            <span class="badge bg-danger ms-2">Super Admin Action</span>
            <?php endif; ?>
          </h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center">
          <div id="paymentProofContainer">
            <img id="paymentProofImage" src="" alt="Payment Proof" class="img-fluid rounded" style="max-height: 500px;">
          </div>
          <div id="noPaymentProof" class="d-none">
            <i class="fas fa-image text-muted" style="font-size: 4rem;"></i>
            <p class="text-muted mt-3">No payment proof available for this order.</p>
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
          <button type="button" class="btn btn-danger" id="rejectProofBtn" style="display: none;" onclick="rejectPaymentProof()" 
                  title="Super Admin Only - Reject payment proof and cancel order">
            <i class="fas fa-times-circle me-1"></i>Reject Payment
          </button>
          <a id="downloadProofBtn" href="" download class="btn btn-primary" style="display: none;">
            <i class="fas fa-download me-1"></i>Download
          </a>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  
  <script>
    function viewPaymentProof(orderId, paymentProof) {
      console.log('Opening payment proof modal for order:', orderId, 'proof:', paymentProof);
      const modal = new bootstrap.Modal(document.getElementById('paymentProofModal'));
      const modalOrderId = document.getElementById('modalOrderId');
      const paymentProofImage = document.getElementById('paymentProofImage');
      const paymentProofContainer = document.getElementById('paymentProofContainer');
      const noPaymentProof = document.getElementById('noPaymentProof');
      const downloadBtn = document.getElementById('downloadProofBtn');
      const rejectBtn = document.getElementById('rejectProofBtn');
      
      // Set order ID
      modalOrderId.textContent = orderId;
      
      // Store order ID and payment proof for reject function
      window.currentOrderId = orderId;
      window.currentPaymentProof = paymentProof;
      
      if (paymentProof && paymentProof.trim() !== '') {
        // Try both possible paths
        const uploadsPath = 'uploads/' + paymentProof;
        const paymentProofsPath = 'payment_proofs/' + paymentProof;
        
        console.log('Trying uploads path:', uploadsPath);
        console.log('Trying payment_proofs path:', paymentProofsPath);
        
        // First try uploads directory
        paymentProofImage.src = uploadsPath;
        paymentProofContainer.classList.remove('d-none');
        noPaymentProof.classList.add('d-none');
        
        // Set download link
        downloadBtn.href = uploadsPath;
        downloadBtn.style.display = 'inline-block';
        
        // Show reject button only for super admin
        <?php if (isset($_SESSION['usertype_id']) && $_SESSION['usertype_id'] == 1): ?>
        rejectBtn.style.display = 'inline-block';
        <?php else: ?>
        rejectBtn.style.display = 'none';
        <?php endif; ?>
        
        // Handle image load error - try payment_proofs directory
        paymentProofImage.onerror = function() {
          console.log('Image failed to load from uploads, trying payment_proofs:', paymentProofsPath);
          paymentProofImage.src = paymentProofsPath;
          downloadBtn.href = paymentProofsPath;
          
          // If both fail, show no proof message
          paymentProofImage.onerror = function() {
            console.log('Image failed to load from both directories');
            paymentProofContainer.classList.add('d-none');
            noPaymentProof.classList.remove('d-none');
            downloadBtn.style.display = 'none';
            rejectBtn.style.display = 'none';
          };
        };
        
        // Handle image load success
        paymentProofImage.onload = function() {
          console.log('Image loaded successfully from uploads');
        };
      } else {
        // No payment proof
        paymentProofContainer.classList.add('d-none');
        noPaymentProof.classList.remove('d-none');
        downloadBtn.style.display = 'none';
        rejectBtn.style.display = 'none';
      }
      
      modal.show();
    }
    
    function rejectPaymentProof() {
      if (!window.currentOrderId || !window.currentPaymentProof) {
        alert('Error: Order information not found');
        return;
      }
      
      const orderId = window.currentOrderId;
      const paymentProof = window.currentPaymentProof;
      
      if (confirm(`Are you sure you want to reject the payment proof for Order #${orderId}?\n\nThis will:\n- Remove the payment proof from the database\n- Change the order status to "Cancelled"\n- Notify the customer\n\nThis action cannot be undone.`)) {
        // Show loading state
        const rejectBtn = document.getElementById('rejectProofBtn');
        const originalText = rejectBtn.innerHTML;
        rejectBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-1"></i>Rejecting...';
        rejectBtn.disabled = true;
        
        // Send AJAX request to reject payment
        fetch('reject_payment_proof.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
          },
          body: `order_id=${orderId}&payment_proof=${encodeURIComponent(paymentProof)}`
        })
        .then(response => response.json())
        .then(data => {
          if (data.success) {
            alert('Payment proof rejected successfully!');
            // Close modal and reload page
            bootstrap.Modal.getInstance(document.getElementById('paymentProofModal')).hide();
            location.reload();
          } else {
            alert('Error rejecting payment proof: ' + (data.message || 'Unknown error'));
            // Reset button
            rejectBtn.innerHTML = originalText;
            rejectBtn.disabled = false;
          }
        })
        .catch(error => {
          console.error('Error:', error);
          alert('Error rejecting payment proof. Please try again.');
          // Reset button
          rejectBtn.innerHTML = originalText;
          rejectBtn.disabled = false;
        });
      }
    }
  </script>
</body>
</html>