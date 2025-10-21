<?php
include '../includes/db.php';
include '../includes/permissions.php';
session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    $_SESSION['error'] = "You don't have permission to access this page.";
    header("Location: login_admin.php");
    exit;
}

// Handle order status updates
if (isset($_POST['update_status'])) {
    $order_id = $_POST['order_id'];
    $new_status = $_POST['new_status'];
    $plate_number = $_POST['plate_number'] ?? null;
    $transaction_number = $_POST['transaction_number'] ?? null;
    $application_name = $_POST['application_name'] ?? null;
    $rider_name = $_POST['rider_name'] ?? null;

    try {
        // Validate transaction number format (up to 23 digits)
        if ($transaction_number && !preg_match('/^[0-9]{1,23}$/', $transaction_number)) {
            $_SESSION['error'] = "Transaction number must be up to 23 digits";
            header("Location: transaction_logs.php");
            exit;
        }

        // Check if transaction number is unique (if provided)
        if ($transaction_number) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE transaction_number = ? AND orders_id != ?");
            $checkStmt->execute([$transaction_number, $order_id]);
            $count = $checkStmt->fetchColumn();
            
            if ($count > 0) {
                $_SESSION['error'] = "Transaction number already exists. Please use a different transaction number.";
                header("Location: transaction_logs.php");
                exit;
            }
        }

        $pdo->beginTransaction();

        // Map status name to orderstatus_id and update
        $stmt = $pdo->prepare("UPDATE orders o
                                JOIN order_status os ON os.status_name = :status
                                SET o.orderstatus_id = os.orderstatus_id,
                                    o.plate_number = :plate_number,
                                    o.transaction_number = :transaction_number,
                                    o.application_name = :application_name,
                                    o.rider_name = :rider_name,
                                    o.pickup_ready_at = CASE 
                                        WHEN :status = 'Ready for Pick Up' AND o.pickup_ready_at IS NULL 
                                        THEN NOW() 
                                        ELSE o.pickup_ready_at 
                                    END
                                WHERE o.orders_id = :order_id");
        $stmt->execute([
            'status' => $new_status, 
            'order_id' => $order_id,
            'plate_number' => $plate_number,
            'transaction_number' => $transaction_number,
            'application_name' => $application_name,
            'rider_name' => $rider_name
        ]);

        // If shipping order, notify customer with delivery details
        if ($new_status === 'Out for delivery' && $plate_number && $transaction_number) {
            // Get customer user_id for notification
            $uidStmt = $pdo->prepare("SELECT user_id FROM orders WHERE orders_id = ?");
            $uidStmt->execute([$order_id]);
            $userId = $uidStmt->fetchColumn();
            
            if ($userId) {
                $message = "Your order #{$order_id} is now out for delivery! 
                            Application: {$application_name}
                            Rider: {$rider_name}
                            Vehicle: {$plate_number}
                            Transaction: {$transaction_number}";
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, order_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $notif->execute([$userId, $order_id, $message]);
            }
        }

        $pdo->commit();
        $_SESSION['success'] = "Order status updated successfully!";
    } catch (Exception $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        
        // Check if it's a unique constraint violation
        if (strpos($e->getMessage(), 'uk_orders_transaction_number') !== false) {
            $_SESSION['error'] = "Transaction number already exists. Please use a different transaction number.";
        } else {
            $_SESSION['error'] = "Error updating order status: " . $e->getMessage();
        }
    }

    header("Location: transaction_logs.php");
    exit;
}

// Handle order cancellation with reason (admin action before processing)
if (isset($_POST['cancel_order'])) {
    $order_id = $_POST['order_id'];
    $reason = trim($_POST['cancel_reason'] ?? '');
    if ($order_id && $reason !== '') {
        try {
            // Ensure cancellations table exists
            $pdo->exec("CREATE TABLE IF NOT EXISTS order_cancellations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                order_id INT NOT NULL,
                reason TEXT NOT NULL,
                cancelled_by VARCHAR(255) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                receipt_path VARCHAR(255) NULL,
                receipt_filename VARCHAR(255) NULL,
                receipt_uploaded_at TIMESTAMP NULL,
                INDEX (order_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

            // Add receipt columns if they don't exist (migration)
            try {
                $pdo->exec("ALTER TABLE order_cancellations ADD COLUMN receipt_path VARCHAR(255) NULL");
            } catch (PDOException $e) {
                // Column already exists, ignore error
            }
            try {
                $pdo->exec("ALTER TABLE order_cancellations ADD COLUMN receipt_filename VARCHAR(255) NULL");
            } catch (PDOException $e) {
                // Column already exists, ignore error
            }
            try {
                $pdo->exec("ALTER TABLE order_cancellations ADD COLUMN receipt_uploaded_at TIMESTAMP NULL");
            } catch (PDOException $e) {
                // Column already exists, ignore error
            }

            $pdo->beginTransaction();

            // Update order status to Cancelled via status name mapping
            $stmt = $pdo->prepare("UPDATE orders o
                                    JOIN order_status os ON os.status_name = 'Cancelled'
                                    SET o.orderstatus_id = os.orderstatus_id
                                    WHERE o.orders_id = :order_id");
            $stmt->execute(['order_id' => $order_id]);

            // Log cancellation reason
            $adminName = $_SESSION['username'] ?? 'admin';
            $ins = $pdo->prepare("INSERT INTO order_cancellations (order_id, reason, cancelled_by) VALUES (:order_id, :reason, :by)");
            $ins->execute(['order_id' => $order_id, 'reason' => $reason, 'by' => $adminName]);

            // Check if this is an overdue pickup cancellation that might need refund
            $needsRefund = false;
            if (strpos($reason, 'Customer did not pick up order within 3 hours') !== false) {
                // Check if order was paid (has payment method and proof)
                $paymentStmt = $pdo->prepare("SELECT pay.method, pay.proof FROM payments pay WHERE pay.orders_id = ?");
                $paymentStmt->execute([$order_id]);
                $payment = $paymentStmt->fetch(PDO::FETCH_ASSOC);
                
                if ($payment && $payment['method'] && strtolower($payment['method']) !== 'cash on delivery' && $payment['proof']) {
                    $needsRefund = true;
                    // Update cancellation reason to indicate refund needed
                    $refundReason = $reason . " - REFUND REQUIRED (Payment: " . $payment['method'] . ")";
                    $updateStmt = $pdo->prepare("UPDATE order_cancellations SET reason = ? WHERE order_id = ? ORDER BY created_at DESC LIMIT 1");
                    $updateStmt->execute([$refundReason, $order_id]);
                }
            }

            // Notify customer
            $uidStmt = $pdo->prepare("SELECT user_id FROM orders WHERE orders_id = ?");
            $uidStmt->execute([$order_id]);
            $userId = $uidStmt->fetchColumn();
            if ($userId) {
                $notif = $pdo->prepare("INSERT INTO notifications (user_id, order_id, message, is_read, created_at) VALUES (?, ?, ?, 0, NOW())");
                $notif->execute([$userId, $order_id, 'Your order has been cancelled by admin: ' . $reason]);
            }

            $pdo->commit();
            
            if ($needsRefund) {
                $_SESSION['success'] = "Order cancelled successfully! Please upload the refund receipt for the customer.";
            } else {
                $_SESSION['success'] = "Order cancelled successfully!";
            }
        } catch (Exception $e) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            $_SESSION['error'] = "Error cancelling order: " . $e->getMessage();
        }
    }
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
           ui.phone,
           a.address_line,
           a.address_line2,
           a.city,
           a.state,
           a.postal_code,
           a.country,
           os.status_name AS status,
           o.total_price as total_amount,
           o.delivery_option,
           o.plate_number,
           o.transaction_number,
           o.application_name,
           o.rider_name,
           o.pickup_ready_at,
           o.created_at,
           COALESCE(pay.method, '') as payment_method,
           COALESCE(pay.proof, '') as payment_proof,
           COALESCE(pay.transaction_id, '') as gcash_transaction_id,
           oc.reason AS cancel_reason,
           oc.receipt_path,
           oc.receipt_filename,
           oc.receipt_uploaded_at,
           GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    LEFT JOIN user_info ui ON ui.user_id = u.user_id
    LEFT JOIN addresses a ON a.address_id = o.address_id
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
    LEFT JOIN order_items oi ON o.orders_id = oi.order_id
    LEFT JOIN products p ON oi.product_id = p.product_id
    LEFT JOIN payments pay ON pay.orders_id = o.orders_id
    LEFT JOIN (
        SELECT oc1.order_id, oc1.reason, oc1.receipt_path, oc1.receipt_filename, oc1.receipt_uploaded_at
        FROM order_cancellations oc1
        INNER JOIN (
            SELECT order_id, MAX(id) AS max_id
            FROM order_cancellations
            GROUP BY order_id
        ) latest ON latest.order_id = oc1.order_id AND latest.max_id = oc1.id
    ) oc ON oc.order_id = o.orders_id
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

$query .= " GROUP BY o.orders_id, u.username, ui.email, ui.phone, a.address_line, a.address_line2, a.city, a.state, a.postal_code, a.country, os.status_name, o.total_price, o.delivery_option, o.plate_number, o.transaction_number, o.application_name, o.rider_name, o.pickup_ready_at, o.created_at, pay.method, pay.proof, pay.transaction_id, oc.reason, oc.receipt_path, oc.receipt_filename, oc.receipt_uploaded_at ORDER BY o.created_at ASC";

try {
    $orders = $pdo->query($query)->fetchAll();
} catch (PDOException $e) {
    $orders = [];
}

$stats = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN os.status_name = 'Pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN os.status_name = 'To Ship' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN os.status_name = 'Ready for Pick Up' THEN 1 ELSE 0 END) as pickup_orders,
        SUM(CASE WHEN os.status_name = 'Out for delivery' THEN 1 ELSE 0 END) as shipped_orders,
        SUM(CASE WHEN os.status_name = 'Completed' THEN 1 ELSE 0 END) as completed_orders,
        SUM(CASE WHEN os.status_name = 'Cancelled' THEN 1 ELSE 0 END) as cancelled_orders
    FROM orders o
    JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
")->fetch();

// Get orders grouped by status
$statuses = ['Pending', 'To Ship', 'Ready for Pick Up', 'Out for delivery', 'Completed', 'Cancelled'];
$ordersByStatus = [];

foreach ($statuses as $status) {
    $statusQuery = "
        SELECT o.orders_id AS id,
               u.username,
               ui.email,
               ui.phone,
               a.address_line,
               a.address_line2,
               a.city,
               a.state,
               a.postal_code,
               a.country,
               os.status_name AS status,
               o.total_price as total_amount,
               o.delivery_option,
               o.plate_number,
               o.transaction_number,
               o.application_name,
               o.rider_name,
               o.pickup_ready_at,
               o.created_at,
               COALESCE(pay.method, '') as payment_method,
               COALESCE(pay.proof, '') as payment_proof,
               COALESCE(pay.transaction_id, '') as gcash_transaction_id,
               oc.reason AS cancel_reason,
               oc.receipt_path,
               oc.receipt_filename,
               oc.receipt_uploaded_at,
               GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
        FROM orders o
        INNER JOIN users u ON o.user_id = u.user_id
        LEFT JOIN user_info ui ON ui.user_id = u.user_id
        LEFT JOIN addresses a ON a.address_id = o.address_id
        JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
        LEFT JOIN order_items oi ON o.orders_id = oi.order_id
        LEFT JOIN products p ON oi.product_id = p.product_id
        LEFT JOIN payments pay ON pay.orders_id = o.orders_id
        LEFT JOIN (
            SELECT oc1.order_id, oc1.reason, oc1.receipt_path, oc1.receipt_filename, oc1.receipt_uploaded_at
            FROM order_cancellations oc1
            INNER JOIN (
                SELECT order_id, MAX(id) AS max_id
                FROM order_cancellations
                GROUP BY order_id
            ) latest ON latest.order_id = oc1.order_id AND latest.max_id = oc1.id
        ) oc ON oc.order_id = o.orders_id
        WHERE os.status_name = :status
        GROUP BY o.orders_id, u.username, ui.email, ui.phone, a.address_line, a.address_line2, a.city, a.state, a.postal_code, a.country, os.status_name, o.total_price, o.delivery_option, o.plate_number, o.transaction_number, o.application_name, o.rider_name, o.pickup_ready_at, o.created_at, pay.method, pay.proof, pay.transaction_id, oc.reason, oc.receipt_path, oc.receipt_filename, oc.receipt_uploaded_at 
        ORDER BY o.created_at ASC
    ";
    
    $stmt = $pdo->prepare($statusQuery);
    $stmt->execute(['status' => $status]);
    $ordersByStatus[$status] = $stmt->fetchAll();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <?php include 'includes/admin_head.php'; ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Transaction Logs - Admin Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
     <!-- SweetAlert2 CSS -->
     <link href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css" rel="stylesheet">
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
        
        .stats-container {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            margin-top: 1rem;
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
        
        .stat-badge {
            background: rgba(255,255,255,0.2);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 25px;
            font-weight: 600;
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255,255,255,0.3);
        }
        
        .stat-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            padding: 24px;
            transition: all 0.3s ease;
            color: var(--text-primary) !important;
        }
        
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.15);
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
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
            background: white;
            border-radius: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            padding: 20px;
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
            background: var(--bs-primary);
            color: white;
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
            background: #fff3cd;
            color: #856404;
        }
        
        .btn-process:hover {
            background: #ffeaa7;
            color: #856404;
        }
        
        .btn-ship {
            background: #d1ecf1;
            color: #0c5460;
        }
        
        .btn-ship:hover {
            background: #bee5eb;
            color: #0c5460;
        }
        
        .btn-deliver {
            background: #d4edda;
            color: #155724;
        }
        
        .btn-deliver:hover {
            background: #c3e6cb;
            color: #155724;
        }
        
        .btn-received {
            background: #20c997;
            color: white;
            cursor: pointer;
        }
        
        .btn-received:hover {
            background: #1aa085;
            color: white;
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }
        
        .btn-completed {
            background: #d4edda;
            color: #155724;
            cursor: default;
        }
        
        .btn-completed:hover {
            background: #d4edda;
            color: #155724;
            transform: none;
            box-shadow: none;
        }
        
        .btn-waiting {
            background: #f8f9fa;
            color: #6c757d;
            cursor: default;
            opacity: 0.9;
        }
        
        .btn-waiting:hover {
            background: #f8f9fa;
            color: #6c757d;
            transform: none;
            box-shadow: none;
            opacity: 0.9;
        }
        
        /* Modal Styles */
        .modal-content {
            border-radius: 20px;
            border: none;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
        }
        
        .modal-header {
            border-radius: 20px 20px 0 0;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .modal-footer {
            border-radius: 0 0 20px 20px;
            border-top: 1px solid rgba(0, 0, 0, 0.1);
        }
        
        .modal-body .alert {
            border-radius: 15px;
            border: none;
        }
        
        .modal-body i {
            opacity: 0.8;
        }
        
        .form-control {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }
        
        .form-select {
            border-radius: 10px;
            border: 1px solid #e9ecef;
            padding: 0.75rem;
            transition: all 0.3s ease;
        }
        
        .form-select:focus {
            border-color: var(--bs-primary);
            box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
        }
        
        .btn {
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        
        .btn:active {
            transform: translateY(-1px);
        }
        
        .order-row {
            transition: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 4rem 2rem;
            color: var(--bs-secondary);
        }
        
        .empty-state i {
            font-size: 4rem;
            color: var(--bs-secondary);
            margin-bottom: 1rem;
        }
        
        .empty-state h4 {
            color: var(--bs-dark);
            margin-bottom: 1rem;
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
            
            .stats-container {
                flex-direction: column;
                gap: 0.5rem;
            }
        }
        
         /* SweetAlert2 Custom Styles */
         .swal2-popup-custom {
            border-radius: 20px !important;
             font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif !important;
             border: 1px solid #e9ecef !important;
        }
        
         .swal2-title-custom {
            color: var(--bs-primary) !important;
             font-weight: 700 !important;
             font-size: 1.5rem !important;
        }
        
         .swal2-html-container-custom {
             color: var(--bs-dark) !important;
             font-size: 1rem !important;
        }
        
         .swal2-confirm-button-custom {
             background: linear-gradient(135deg, var(--bs-primary) 0%, #a91d42 100%) !important;
             border: none !important;
            border-radius: 10px !important;
             padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
             font-size: 1rem !important;
             transition: all 0.3s ease !important;
         }

         .swal2-confirm-button-custom:hover {
             transform: translateY(-2px) !important;
             box-shadow: 0 8px 25px rgba(127, 23, 52, 0.3) !important;
             background: linear-gradient(135deg, #6b1429 0%, #8b1a36 100%) !important;
         }

         .swal2-cancel-button-custom {
             border: 2px solid var(--bs-secondary) !important;
             color: var(--bs-secondary) !important;
            border-radius: 10px !important;
             padding: 0.75rem 2rem !important;
            font-weight: 600 !important;
             background: transparent !important;
             font-size: 1rem !important;
             transition: all 0.3s ease !important;
         }

         .swal2-cancel-button-custom:hover {
             background: var(--bs-secondary) !important;
             color: white !important;
             transform: translateY(-2px) !important;
             box-shadow: 0 8px 25px rgba(108, 117, 125, 0.2) !important;
        }

        /* Order Details Modal Styles */
        .modal-lg {
            max-width: 900px;
        }

        .modal-body .card {
            border: none;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: all 0.3s ease;
        }

        .modal-body .card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
        }

        .modal-body .card-header {
            border-radius: 10px 10px 0 0 !important;
            border: none;
            font-weight: 600;
        }

        .modal-body .card-body {
            padding: 1.5rem;
        }

        .modal-body .row {
            margin-bottom: 0.5rem;
        }

        .modal-body strong {
            color: var(--bs-dark);
            font-weight: 600;
        }

        .modal-body .badge {
            font-size: 0.75rem;
            padding: 0.5rem 0.75rem;
        }

        .modal-body .btn-outline-primary {
            border-color: var(--bs-primary);
            color: var(--bs-primary);
            transition: all 0.3s ease;
        }

        .modal-body .btn-outline-primary:hover {
            background-color: var(--bs-primary);
            border-color: var(--bs-primary);
            color: white;
            transform: translateY(-1px);
        }
        
        /* Tab Styles */
        .nav-tabs {
            border-bottom: 2px solid #e9ecef;
            margin-bottom: 0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 10px 10px 0 0;
            margin-right: 5px;
            padding: 12px 20px;
            font-weight: 600;
            color: var(--bs-secondary);
            background: transparent;
            transition: all 0.3s ease;
            position: relative;
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--bs-primary);
            background: rgba(127, 23, 52, 0.1);
            border-color: transparent;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--bs-primary);
            background: white;
            border-color: #e9ecef #e9ecef white;
            border-bottom: 2px solid white;
            font-weight: 700;
        }
        
        .nav-tabs .nav-link.active::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--bs-primary);
        }
        
        .nav-tabs .badge {
            font-size: 0.7rem;
            padding: 0.25rem 0.5rem;
        }
        
        .tab-content {
            background: white;
            border-radius: 0 0 20px 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            border-top: none;
        }
        
        .tab-pane {
            padding: 0;
        }
        
        .tab-pane .table-card {
            border-radius: 0;
            box-shadow: none;
            border: none;
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
        <h2><i class="fas fa-cart-shopping me-2"></i>Transaction Logs</h2>
      </div>
      
      <!-- Analytics Cards -->
      <div class="row g-4 mb-4">
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-shopping-cart"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $stats['total_orders'] ?></h3>
              <p class="card-label">Total Orders</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-clock"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $stats['pending_orders'] ?></h3>
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
              <h3 class="card-number"><?= $stats['processing_orders'] ?></h3>
              <p class="card-label">To Ship</p>
            </div>
          </div>
        </div>
        <div class="col-md-3">
          <div class="analytics-card">
            <div class="card-icon">
              <i class="fas fa-check-circle"></i>
            </div>
            <div class="card-content">
              <h3 class="card-number"><?= $stats['completed_orders'] ?></h3>
              <p class="card-label">Completed</p>
            </div>
          </div>
        </div>
      </div>

      
      <!-- Status Tabs -->
    <div class="filter-card">
      <ul class="nav nav-tabs nav-fill" id="orderStatusTabs" role="tablist">
        <li class="nav-item" role="presentation">
          <button class="nav-link active" id="pending-tab" data-bs-toggle="tab" data-bs-target="#pending" type="button" role="tab" aria-controls="pending" aria-selected="true">
            <i class="fas fa-clock me-2"></i>Pending <span class="badge ms-1" style="background: transparent; color: #856404; border: 1px solid #856404;"><?= $stats['pending_orders'] ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="toship-tab" data-bs-toggle="tab" data-bs-target="#toship" type="button" role="tab" aria-controls="toship" aria-selected="false">
            <i class="fas fa-cog me-2"></i>To Ship <span class="badge ms-1" style="background: transparent; color: #0c5460; border: 1px solid #0c5460;"><?= $stats['processing_orders'] ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="pickup-tab" data-bs-toggle="tab" data-bs-target="#pickup" type="button" role="tab" aria-controls="pickup" aria-selected="false">
            <i class="fas fa-hand-paper me-2"></i>Pick Up <span class="badge ms-1" style="background: transparent; color: #721c24; border: 1px solid #721c24;"><?= $stats['pickup_orders'] ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="delivery-tab" data-bs-toggle="tab" data-bs-target="#delivery" type="button" role="tab" aria-controls="delivery" aria-selected="false">
            <i class="fas fa-truck me-2"></i>Out for Delivery <span class="badge ms-1" style="background: transparent; color: #004085; border: 1px solid #004085;"><?= $stats['shipped_orders'] ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="completed-tab" data-bs-toggle="tab" data-bs-target="#completed" type="button" role="tab" aria-controls="completed" aria-selected="false">
            <i class="fas fa-check-circle me-2"></i>Completed <span class="badge ms-1" style="background: transparent; color: #155724; border: 1px solid #155724;"><?= $stats['completed_orders'] ?></span>
          </button>
        </li>
        <li class="nav-item" role="presentation">
          <button class="nav-link" id="cancelled-tab" data-bs-toggle="tab" data-bs-target="#cancelled" type="button" role="tab" aria-controls="cancelled" aria-selected="false">
            <i class="fas fa-ban me-2"></i>Cancelled <span class="badge ms-1" style="background: transparent; color: #495057; border: 1px solid #495057;"><?= $stats['cancelled_orders'] ?></span>
          </button>
        </li>
      </ul>
    </div>

      <!-- Tab Content -->
    <div class="tab-content" id="orderStatusTabContent">
      <!-- Status-specific tabs -->
                <?php 
      $tabMapping = [
        'Pending' => 'pending',
        'To Ship' => 'toship', 
        'Ready for Pick Up' => 'pickup',
        'Out for delivery' => 'delivery',
        'Completed' => 'completed',
        'Cancelled' => 'cancelled'
      ];
      foreach ($statuses as $status): 
        $tabId = $tabMapping[$status];
        $isActive = ($status === 'Pending') ? 'show active' : '';
      ?>
        <div class="tab-pane fade <?= $isActive ?>" id="<?= $tabId ?>" role="tabpanel" aria-labelledby="<?= $tabId ?>-tab">
          <?php 
            $orders = $ordersByStatus[$status];
            include 'order_table_template.php'; 
          ?>
                  </div>
                  <?php endforeach; ?>
          </div>
  </main>

  <!-- Order Details Modal -->
  <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="orderDetailsModalLabel">
            <i class="fas fa-info-circle me-2"></i>Order Details
          </h5>
                  </div>
        <div class="modal-body">
          <div class="row">
            <!-- Order Information -->
            <div class="col-md-6 mb-4">
              <div class="card h-100">
                <div class="card-header" style="background: #e3f2fd; color: #1976d2;">
                  <h6 class="mb-0"><i class="fas fa-shopping-cart me-2"></i>Order Information</h6>
          </div>
                <div class="card-body">
                  <div class="row mb-2">
                    <div class="col-4"><strong>Order ID:</strong></div>
                    <div class="col-8" id="modalOrderId">-</div>
        </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Status:</strong></div>
                    <div class="col-8" id="modalOrderStatus">-</div>
                      </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Total Amount:</strong></div>
                    <div class="col-8" id="modalTotalAmount">-</div>
          </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Application:</strong></div>
                    <div class="col-8" id="modalApplicationName">-</div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Rider:</strong></div>
                    <div class="col-8" id="modalRiderName">-</div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Vehicle:</strong></div>
                    <div class="col-8" id="modalPlateNumber">-</div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Transaction:</strong></div>
                    <div class="col-8" id="modalTransactionNumber">-</div>
                  </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Order Date:</strong></div>
                    <div class="col-8" id="modalOrderDate">-</div>
                      </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Items:</strong></div>
                    <div class="col-8" id="modalOrderItems">-</div>
                  </div>
                </div>
          </div>
        </div>

            <!-- Customer Information -->
            <div class="col-md-6 mb-4">
              <div class="card h-100">
                <div class="card-header" style="background: #f1f8e9; color: #689f38;">
                  <h6 class="mb-0"><i class="fas fa-user me-2"></i>Customer Information</h6>
                  </div>
                <div class="card-body">
                  <div class="row mb-2">
                    <div class="col-4"><strong>Username:</strong></div>
                    <div class="col-8" id="modalCustomerUsername">-</div>
                                  </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Email:</strong></div>
                    <div class="col-8" id="modalCustomerEmail">-</div>
                                </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Phone:</strong></div>
                    <div class="col-8" id="modalCustomerPhone">-</div>
                            </div>
                  <div class="row mb-2">
                    <div class="col-4"><strong>Address:</strong></div>
                    <div class="col-8" id="modalCustomerAddress">-</div>
                          </div>
                        </div>
              </div>
            </div>

            <!-- Payment Information -->
            <div class="col-12 mb-4">
              <div class="card">
                <div class="card-header" style="background: #fff8e1; color: #f57c00;">
                  <h6 class="mb-0"><i class="fas fa-credit-card me-2"></i>Payment Information</h6>
                          </div>
                <div class="card-body">
                  <div class="row">
                    <div class="col-md-3 mb-2">
                      <strong>Payment Method:</strong>
                      <div id="modalPaymentMethod">-</div>
                    </div>
                    <div class="col-md-3 mb-2">
                      <strong>Reference Number:</strong>
                      <div id="modalTransactionId">-</div>
                    </div>
                    <div class="col-md-6 mb-2">
                      <strong>Payment Proof:</strong>
                      <div id="modalPaymentProof">-</div>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
                        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
            <i class="fas fa-times me-1"></i>Close
          </button>
                      </div>
                          </div>
                        </div>
                      </div>



  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
   <!-- SweetAlert2 JS -->
   <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
  <?php include 'includes/admin_scripts.php'; ?>
  
  <script>
    // Handle modal data population
    document.addEventListener('DOMContentLoaded', function() {
      // Show success/error messages with SweetAlert2
      <?php if (isset($_SESSION['success'])): ?>
        Swal.fire({
          icon: 'success',
          title: 'Success!',
          text: '<?= addslashes($_SESSION['success']) ?>',
          confirmButtonColor: '#7F1734',
          timer: 3000,
          timerProgressBar: true,
          customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            htmlContainer: 'swal2-html-container-custom',
            confirmButton: 'swal2-confirm-button-custom'
          }
        });
        <?php unset($_SESSION['success']); ?>
      <?php endif; ?>

      <?php if (isset($_SESSION['error'])): ?>
      Swal.fire({
          icon: 'error',
          title: 'Error!',
          text: '<?= addslashes($_SESSION['error']) ?>',
          confirmButtonColor: '#7F1734',
          customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            htmlContainer: 'swal2-html-container-custom',
            confirmButton: 'swal2-confirm-button-custom'
          }
        });
        <?php unset($_SESSION['error']); ?>
      <?php endif; ?>

      // Add SweetAlert2 confirmations to form submissions
      const processForm = document.getElementById('processForm');
      if (processForm) {
        processForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const orderId = document.getElementById('processOrderIdInput').value;
          const newStatus = document.getElementById('processNewStatusInput').value;
          
          Swal.fire({
            title: 'Process Order?',
            html: `
              <div class="text-start">
                <p><strong>Order #${orderId}</strong></p>
                <p>This will change the order status to <strong>"${newStatus}"</strong></p>
              </div>
            `,
        icon: 'question',
        showCancelButton: true,
            confirmButtonColor: '#7F1734',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-cog me-2"></i>Yes, Process Order',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom',
              confirmButton: 'swal2-confirm-button-custom',
              cancelButton: 'swal2-cancel-button-custom'
            }
      }).then((result) => {
        if (result.isConfirmed) {
              this.submit();
            }
          });
        });
      }

      const shipForm = document.getElementById('shipForm');
      if (shipForm) {
        shipForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const orderId = document.getElementById('shipOrderIdInput').value;
          
      Swal.fire({
            title: 'Ship Order?',
            html: `
              <div class="text-start">
                <p><strong>Order #${orderId}</strong></p>
                <p>This will change the order status to <strong>"Out for Delivery"</strong></p>
                <p class="text-info">The customer will be notified that their order is on the way.</p>
              </div>
            `,
        icon: 'question',
        showCancelButton: true,
            confirmButtonColor: '#7F1734',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-truck me-2"></i>Yes, Ship Order',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom',
              confirmButton: 'swal2-confirm-button-custom',
              cancelButton: 'swal2-cancel-button-custom'
            }
      }).then((result) => {
        if (result.isConfirmed) {
              this.submit();
            }
          });
        });
      }

      const completePickupForm = document.getElementById('completePickupForm');
      if (completePickupForm) {
        completePickupForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const orderId = document.getElementById('pickupOrderIdInput').value;
          
      Swal.fire({
            title: 'Complete Pickup?',
            html: `
              <div class="text-start">
                <p><strong>Order #${orderId}</strong></p>
                <p>This will change the order status to <strong>"Completed"</strong></p>
                <p class="text-success">Confirm that the customer has picked up their order.</p>
              </div>
            `,
            icon: 'question',
        showCancelButton: true,
            confirmButtonColor: '#7F1734',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Yes, Complete',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom',
              confirmButton: 'swal2-confirm-button-custom',
              cancelButton: 'swal2-cancel-button-custom'
            }
      }).then((result) => {
        if (result.isConfirmed) {
              this.submit();
            }
          });
        });
      }

      const cancelOrderForm = document.getElementById('cancelOrderForm');
      if (cancelOrderForm) {
        cancelOrderForm.addEventListener('submit', function(e) {
          e.preventDefault();
          const orderId = document.getElementById('cancelOrderIdInput').value;
          const reason = document.getElementById('cancelReason').value;
          
          if (!reason.trim()) {
      Swal.fire({
        icon: 'warning',
              title: 'Missing Information',
              text: 'Please provide a cancellation reason.',
              confirmButtonColor: '#7F1734',
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-container-custom',
                confirmButton: 'swal2-confirm-button-custom'
              }
            });
            return;
          }
          
          Swal.fire({
            title: 'Cancel Order?',
            html: `
              <div class="text-start">
                <p><strong>Order #${orderId}</strong></p>
                <p><strong>Reason:</strong> ${reason}</p>
                <p class="text-danger">This will change the order status to <strong>"Cancelled"</strong></p>
                <p class="text-danger">The customer will be notified of the cancellation.</p>
              </div>
            `,
            icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-ban me-2"></i>Yes, Cancel Order',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom',
              confirmButton: 'swal2-confirm-button-custom',
              cancelButton: 'swal2-cancel-button-custom'
            }
      }).then((result) => {
        if (result.isConfirmed) {
              this.submit();
            }
          });
        });
      }

      // Process Modal
      const processModal = document.getElementById('processModal');
      if (processModal) {
        processModal.addEventListener('show.bs.modal', function (event) {
          const button = event.relatedTarget;
          const orderId = button.getAttribute('data-order-id');
          const customer = button.getAttribute('data-customer');
          const newStatus = button.getAttribute('data-new-status') || 'To Ship';
          
          document.getElementById('processOrderId').textContent = 'Order #' + orderId;
          document.getElementById('processCustomer').textContent = 'Customer: ' + customer;
          document.getElementById('processOrderIdInput').value = orderId;
          const statusInput = document.getElementById('processNewStatusInput');
          const statusLabel = document.getElementById('processNewStatusLabel');
          if (statusInput) statusInput.value = newStatus;
          if (statusLabel) statusLabel.textContent = '"' + newStatus + '"';
        });
      }

      // Ship Modal
      const shipModal = document.getElementById('shipModal');
      if (shipModal) {
        shipModal.addEventListener('show.bs.modal', function (event) {
          const button = event.relatedTarget;
          const orderId = button.getAttribute('data-order-id');
          const customer = button.getAttribute('data-customer');
          
          document.getElementById('shipOrderId').textContent = 'Order #' + orderId;
          document.getElementById('shipCustomer').textContent = 'Customer: ' + customer;
          document.getElementById('shipOrderIdInput').value = orderId;
        });
      }

      // Complete Pickup Modal
      const completePickupModal = document.getElementById('completePickupModal');
      if (completePickupModal) {
        completePickupModal.addEventListener('show.bs.modal', function (event) {
          const button = event.relatedTarget;
          const orderId = button.getAttribute('data-order-id');
          const customer = button.getAttribute('data-customer');

          document.getElementById('pickupOrderId').textContent = 'Order #' + orderId;
          document.getElementById('pickupCustomer').textContent = 'Customer: ' + customer;
          document.getElementById('pickupOrderIdInput').value = orderId;
        });
      }

      // Cancel Order Modal
      const cancelOrderModal = document.getElementById('cancelOrderModal');
      if (cancelOrderModal) {
        cancelOrderModal.addEventListener('show.bs.modal', function (event) {
          const button = event.relatedTarget;
          const orderId = button.getAttribute('data-order-id');
          const customer = button.getAttribute('data-customer');
          document.getElementById('cancelOrderId').textContent = 'Order #' + orderId;
          document.getElementById('cancelCustomer').textContent = 'Customer: ' + customer;
          document.getElementById('cancelOrderIdInput').value = orderId;
          document.getElementById('cancelReason').value = '';
        });
      }

    });

    // SweetAlert2 Functions
    function processOrder(orderId, newStatus) {
        Swal.fire({
        title: 'Process Order?',
        html: `
          <div class="text-start">
            <p><strong>Order #${orderId}</strong></p>
            <p>This will change the order status to <strong>"${newStatus}"</strong></p>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7F1734',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-cog me-2"></i>Yes, Process Order',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          confirmButton: 'swal2-confirm-button-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Create and submit form
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="new_status" value="${newStatus}">
            <input type="hidden" name="update_status" value="1">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    function shipOrder(orderId) {
      Swal.fire({
        title: 'Ship Order',
        html: `
          <div class="text-start">
            <p><strong>Order #${orderId}</strong></p>
            <p class="mb-3">Please provide the delivery details:</p>
            
            <div class="mb-3">
              <label for="applicationName" class="form-label">Application *</label>
              <select id="applicationName" class="form-control" required>
                <option value="">Select Application</option>
                <option value="Lalamove">Lalamove</option>
                <option value="Angkas Padala">Angkas Padala</option>
                <option value="GrabExpress">GrabExpress</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label for="riderName" class="form-label">Rider's Name *</label>
              <input type="text" id="riderName" class="form-control" placeholder="Enter rider's name" maxlength="100" required>
            </div>
            
            <div class="mb-3">
              <label for="plateNumber" class="form-label">Plate Number *</label>
              <input type="text" id="plateNumber" class="form-control" placeholder="Enter vehicle plate number" maxlength="20" required>
              <small class="text-muted">e.g., ABC-1234, XYZ-5678</small>
            </div>
            
            <div class="mb-3">
              <label for="transactionNumber" class="form-label">Transaction Number *</label>
              <input type="text" id="transactionNumber" class="form-control" placeholder="Enter transaction number" maxlength="23" pattern="[0-9]{1,23}" required>
              <small class="text-muted">Must be up to 23 digits (e.g., 1234567890123456789)</small>
            </div>
            
            <div class="alert alert-info">
              <i class="fas fa-info-circle me-2"></i>
              <strong>Note:</strong> This will change the order status to "Out for Delivery" and notify the customer with tracking details.
            </div>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7F1734',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-truck me-2"></i>Ship Order',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          confirmButton: 'swal2-confirm-button-custom',
          cancelButton: 'swal2-cancel-button-custom'
        },
        preConfirm: () => {
          const applicationName = document.getElementById('applicationName').value.trim();
          const riderName = document.getElementById('riderName').value.trim();
          const plateNumber = document.getElementById('plateNumber').value.trim();
          const transactionNumber = document.getElementById('transactionNumber').value.trim();
          
          if (!applicationName) {
            Swal.showValidationMessage('Please select an application');
            return false;
          }
          
          if (!riderName) {
            Swal.showValidationMessage('Please enter rider\'s name');
            return false;
          }
          
          if (!plateNumber) {
            Swal.showValidationMessage('Please enter a plate number');
            return false;
          }
          
          if (!transactionNumber) {
            Swal.showValidationMessage('Please enter a transaction number');
            return false;
          }
          
          // Validate transaction number format (up to 23 digits)
          if (!/^[0-9]{1,23}$/.test(transactionNumber)) {
            Swal.showValidationMessage('Transaction number must be up to 23 digits');
            return false;
          }
          
          return {
            applicationName: applicationName,
            riderName: riderName,
            plateNumber: plateNumber,
            transactionNumber: transactionNumber
          };
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Shipping Order...',
            text: 'Please wait while we update the order status.',
            allowOutsideClick: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            },
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom'
            }
          });
          
          // Create and submit form with delivery details
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="new_status" value="Out for Delivery">
            <input type="hidden" name="application_name" value="${result.value.applicationName}">
            <input type="hidden" name="rider_name" value="${result.value.riderName}">
            <input type="hidden" name="plate_number" value="${result.value.plateNumber}">
            <input type="hidden" name="transaction_number" value="${result.value.transactionNumber}">
            <input type="hidden" name="update_status" value="1">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    function completePickup(orderId) {
      Swal.fire({
        title: 'Complete Pickup?',
        html: `
          <div class="text-start">
            <p><strong>Order #${orderId}</strong></p>
            <p>This will change the order status to <strong>"Completed"</strong></p>
            <p class="text-success">Confirm that the customer has picked up their order.</p>
          </div>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#7F1734',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-check me-2"></i>Yes, Complete',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          confirmButton: 'swal2-confirm-button-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Create and submit form
          const form = document.createElement('form');
          form.method = 'POST';
          form.innerHTML = `
            <input type="hidden" name="order_id" value="${orderId}">
            <input type="hidden" name="new_status" value="Completed">
            <input type="hidden" name="update_status" value="1">
          `;
          document.body.appendChild(form);
          form.submit();
        }
      });
    }

    function cancelOverduePickup(orderId, hoursElapsed, paymentMethod = '', paymentProof = '') {
      // Check if order was paid (has payment method and proof)
      const isPaid = paymentMethod && paymentMethod.toLowerCase() !== 'cash on delivery' && paymentProof;
      
      Swal.fire({
        title: 'Cancel Overdue Pickup?',
        html: `
          <div class="text-start">
            <p><strong>Order #${orderId}</strong></p>
            <div class="alert alert-warning">
              <i class="fas fa-exclamation-triangle me-2"></i>
              <strong>Overdue:</strong> Customer hasn't picked up for <strong>${hoursElapsed} hours</strong>
            </div>
            ${isPaid ? `
              <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Payment Status:</strong> Order was paid via <strong>${paymentMethod}</strong>
                <br><small>You will need to upload a refund receipt after cancellation.</small>
              </div>
            ` : `
              <div class="alert alert-secondary">
                <i class="fas fa-info-circle me-2"></i>
                <strong>Payment Status:</strong> Cash on Delivery - No refund needed
              </div>
            `}
            <p class="text-danger">This will:</p>
            <ul class="text-danger">
              <li>Change the order status to <strong>"Cancelled"</strong></li>
              <li>Notify the customer about the cancellation</li>
              <li>Free up inventory for other customers</li>
              ${isPaid ? '<li><strong>Require refund receipt upload</strong></li>' : ''}
            </ul>
            <p class="text-danger"><strong>This action cannot be undone!</strong></p>
          </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: `<i class="fas fa-ban me-2"></i>Yes, Cancel Order${isPaid ? ' & Refund' : ''}`,
        cancelButtonText: '<i class="fas fa-times me-2"></i>Keep Waiting',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          confirmButton: 'swal2-confirm-button-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          if (isPaid) {
            // For paid orders, prompt for refund receipt upload
            promptRefundReceiptUpload(orderId, hoursElapsed);
          } else {
            // For COD orders, proceed directly to cancellation
            proceedWithCancellation(orderId, hoursElapsed);
          }
        }
      });
    }

    function promptRefundReceiptUpload(orderId, hoursElapsed) {
      Swal.fire({
        title: 'Upload Refund Receipt',
        html: `
          <div class="text-start">
            <p><strong>Order #${orderId}</strong></p>
            <p>Since this order was already paid, please upload the refund receipt.</p>
            <p class="text-muted small">Accepted formats: JPG, PNG, PDF (Max 5MB)</p>
          </div>
        `,
        input: 'file',
        inputLabel: 'Refund Receipt File',
        inputAttributes: {
          accept: '.jpg,.jpeg,.png,.pdf',
          'aria-label': 'Upload refund receipt file'
        },
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-upload me-2"></i>Upload & Cancel',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        inputValidator: (value) => {
          if (!value) {
            return 'Please select a refund receipt file!';
          }
          // Check file size (5MB max)
          if (value.size > 5 * 1024 * 1024) {
            return 'File size must be less than 5MB!';
          }
          // Check file type
          const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
          if (!allowedTypes.includes(value.type)) {
            return 'Only JPG, PNG, and PDF files are allowed!';
          }
        },
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          confirmButton: 'swal2-confirm-button-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          // Show loading state
          Swal.fire({
            title: 'Processing Cancellation...',
            text: 'Please wait while we cancel the order and upload the refund receipt',
            allowOutsideClick: false,
            allowEscapeKey: false,
            showConfirmButton: false,
            didOpen: () => {
              Swal.showLoading();
            },
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom'
            }
          });

          // First cancel the order
          proceedWithCancellation(orderId, hoursElapsed).then(() => {
            // Wait a moment for the cancellation to complete
            setTimeout(() => {
              // Now upload the refund receipt
              const formData = new FormData();
              formData.append('receipt', result.value);
              formData.append('order_id', orderId);

              // Upload refund receipt
              fetch('upload_cancellation_receipt.php', {
                method: 'POST',
                body: formData
              })
              .then(response => response.json())
              .then(data => {
                if (data.success) {
                  Swal.fire({
                    icon: 'success',
                    title: 'Success!',
                    text: 'Order cancelled and refund receipt uploaded successfully',
                    confirmButtonColor: '#7F1734',
                    timer: 2000,
                    timerProgressBar: true,
                    customClass: {
                      popup: 'swal2-popup-custom',
                      title: 'swal2-title-custom',
                      htmlContainer: 'swal2-html-container-custom',
                      confirmButton: 'swal2-confirm-button-custom'
                    }
                  }).then(() => {
                    // Refresh the page to show updated order status
                    location.reload();
                  });
                } else {
                  Swal.fire({
                    icon: 'error',
                    title: 'Upload Failed',
                    text: data.message || 'Failed to upload refund receipt',
                    confirmButtonColor: '#7F1734',
                    customClass: {
                      popup: 'swal2-popup-custom',
                      title: 'swal2-title-custom',
                      htmlContainer: 'swal2-html-container-custom',
                      confirmButton: 'swal2-confirm-button-custom'
                    }
                  });
                }
              })
              .catch(error => {
                console.error('Error:', error);
                Swal.fire({
                  icon: 'error',
                  title: 'Error',
                  text: 'Failed to upload refund receipt. Please try again.',
                  confirmButtonColor: '#7F1734',
                  customClass: {
                    popup: 'swal2-popup-custom',
                    title: 'swal2-title-custom',
                    htmlContainer: 'swal2-html-container-custom',
                    confirmButton: 'swal2-confirm-button-custom'
                  }
                });
              });
            }, 1000);
          }).catch(error => {
            console.error('Cancellation error:', error);
            Swal.fire({
              icon: 'error',
              title: 'Cancellation Failed',
              text: 'Failed to cancel the order. Please try again.',
              confirmButtonColor: '#7F1734',
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-container-custom',
                confirmButton: 'swal2-confirm-button-custom'
              }
            });
          });
        }
      });
    }

    function proceedWithCancellation(orderId, hoursElapsed) {
      return new Promise((resolve, reject) => {
        // Create and submit form with cancellation reason
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
          <input type="hidden" name="order_id" value="${orderId}">
          <input type="hidden" name="cancel_reason" value="Customer did not pick up order within 3 hours (${hoursElapsed} hours elapsed)">
          <input type="hidden" name="cancel_order" value="1">
        `;
        document.body.appendChild(form);
        form.submit();
        resolve();
      });
    }

    function cancelOrder(orderId, deliveryOption) {
      // Define cancellation reasons based on delivery type
      const pickupReasons = {
        'customer_unable': 'Customer unable to visit the store',
        'payment_not_received': 'Payment not received',
        'invalid_proof': 'Invalid proof of payment',
        'insufficient_payment': 'Insufficient Payment',
        'other': 'Other'
      };

      const deliveryReasons = {
        'customer_unable': 'Customer unable to visit the store',
        'payment_not_received': 'Payment not received',
        'invalid_proof': 'Invalid proof of payment',
        'insufficient_payment': 'Insufficient Payment',
        'ride_no_show': 'Ride did not show up',
        'other': 'Other'
      };

      // Normalize delivery option to lowercase for comparison
      const normalizedDeliveryOption = (deliveryOption || 'delivery').toLowerCase();
      const reasons = normalizedDeliveryOption === 'pickup' ? pickupReasons : deliveryReasons;

      Swal.fire({
        title: 'Cancel Order?',
        html: `
          <div class="text-start">
            <p><strong>Order #${orderId}</strong></p>
            <p class="text-danger">This will change the order status to <strong>"Cancelled"</strong></p>
            <p class="text-danger">The customer will be notified of the cancellation.</p>
          </div>
        `,
        icon: 'question',
        input: 'select',
        inputOptions: reasons,
        inputLabel: 'Cancellation Reason',
        inputPlaceholder: 'Select a reason...',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
        confirmButtonText: '<i class="fas fa-ban me-2"></i>Yes, Cancel Order',
        cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
        inputValidator: (value) => {
          if (!value) {
            return 'Please select a cancellation reason!';
          }
        },
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          confirmButton: 'swal2-confirm-button-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      }).then((result) => {
        if (result.isConfirmed) {
          let finalReason = reasons[result.value];
          
          // If "Other" is selected, ask for custom reason
          if (result.value === 'other') {
            Swal.fire({
              title: 'Specify Reason',
              input: 'textarea',
              inputLabel: 'Cancellation Reason',
              inputPlaceholder: 'Please specify the cancellation reason...',
              showCancelButton: true,
              confirmButtonColor: '#dc3545',
              cancelButtonColor: '#6c757d',
              confirmButtonText: '<i class="fas fa-check me-2"></i>Confirm',
              cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
              inputValidator: (value) => {
                if (!value.trim()) {
                  return 'Please provide a reason!';
                }
              },
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-container-custom',
                confirmButton: 'swal2-confirm-button-custom',
                cancelButton: 'swal2-cancel-button-custom'
              }
            }).then((customResult) => {
              if (customResult.isConfirmed) {
                finalReason = customResult.value;
                handleCancellationWithReceipt(orderId, finalReason);
              }
            });
          } else {
            handleCancellationWithReceipt(orderId, finalReason);
          }
        }
      });
    }

    function handleCancellationWithReceipt(orderId, reason) {
      // If reason is "Insufficient Payment", ask for receipt upload
      if (reason === 'Insufficient Payment') {
        Swal.fire({
          title: 'Upload Refund Receipt',
          html: `
            <div class="text-start">
              <p><strong>Order #${orderId}</strong></p>
              <p>Please upload the refund receipt for this cancellation.</p>
              <p class="text-muted small">Accepted formats: JPG, PNG, PDF (Max 5MB)</p>
            </div>
          `,
          input: 'file',
          inputLabel: 'Receipt File',
          inputAttributes: {
            accept: '.jpg,.jpeg,.png,.pdf',
            'aria-label': 'Upload receipt file'
          },
          showCancelButton: true,
          confirmButtonColor: '#dc3545',
          cancelButtonColor: '#6c757d',
          confirmButtonText: '<i class="fas fa-upload me-2"></i>Upload & Cancel',
          cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
          inputValidator: (value) => {
            if (!value) {
              return 'Please select a receipt file!';
            }
            // Check file size (5MB max)
            if (value.size > 5 * 1024 * 1024) {
              return 'File size must be less than 5MB!';
            }
            // Check file type
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'application/pdf'];
            if (!allowedTypes.includes(value.type)) {
              return 'Only JPG, PNG, and PDF files are allowed!';
            }
          },
          customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            htmlContainer: 'swal2-html-container-custom',
            confirmButton: 'swal2-confirm-button-custom',
            cancelButton: 'swal2-cancel-button-custom'
          }
        }).then((result) => {
          if (result.isConfirmed) {
            uploadReceiptAndCancel(orderId, reason, result.value);
          }
        });
      } else {
        // For other reasons, proceed directly to cancellation
        submitCancellation(orderId, reason);
      }
    }

    function uploadReceiptAndCancel(orderId, reason, file) {
      // Show loading state
      Swal.fire({
        title: 'Processing Cancellation...',
        text: 'Please wait while we cancel the order and upload the receipt',
        allowOutsideClick: false,
        allowEscapeKey: false,
        showConfirmButton: false,
        didOpen: () => {
          Swal.showLoading();
        }
      });

      // First cancel the order
      submitCancellation(orderId, reason).then(() => {
        // Wait a moment for the cancellation to complete
        setTimeout(() => {
          // Now upload the receipt
          const formData = new FormData();
          formData.append('receipt', file);
          formData.append('order_id', orderId);

          // Upload receipt
          fetch('upload_cancellation_receipt.php', {
            method: 'POST',
            body: formData
          })
          .then(response => response.json())
          .then(data => {
            if (data.success) {
              Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: 'Order cancelled and receipt uploaded successfully',
                confirmButtonColor: '#7F1734',
                timer: 2000,
                timerProgressBar: true,
                customClass: {
                  popup: 'swal2-popup-custom',
                  title: 'swal2-title-custom',
                  htmlContainer: 'swal2-html-container-custom',
                  confirmButton: 'swal2-confirm-button-custom'
                }
              }).then(() => {
                // Refresh the page to show updated order status
                location.reload();
              });
            } else {
              Swal.fire({
                icon: 'error',
                title: 'Upload Failed',
                text: data.message || 'Failed to upload receipt',
                confirmButtonColor: '#7F1734',
                customClass: {
                  popup: 'swal2-popup-custom',
                  title: 'swal2-title-custom',
                  htmlContainer: 'swal2-html-container-custom',
                  confirmButton: 'swal2-confirm-button-custom'
                }
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
              icon: 'error',
              title: 'Upload Error',
              text: 'An error occurred while uploading the receipt',
              confirmButtonColor: '#7F1734',
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-container-custom',
                confirmButton: 'swal2-confirm-button-custom'
              }
            });
          });
        }, 1000); // Wait 1 second for cancellation to complete
      });
    }

    function submitCancellation(orderId, reason) {
      return new Promise((resolve, reject) => {
        const formData = new FormData();
        formData.append('order_id', orderId);
        formData.append('cancel_reason', reason);
        formData.append('cancel_order', '1');

        fetch('transaction_logs.php', {
          method: 'POST',
          body: formData
        })
        .then(response => {
          if (response.ok) {
            // Show success message and refresh the page
            Swal.fire({
              icon: 'success',
              title: 'Order Cancelled!',
              text: 'The order has been successfully cancelled.',
              confirmButtonColor: '#7F1734',
              timer: 2000,
              timerProgressBar: true,
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-container-custom',
                confirmButton: 'swal2-confirm-button-custom'
              }
            }).then(() => {
              // Refresh the page to show updated order status
              location.reload();
            });
            resolve();
          } else {
            reject(new Error('Cancellation failed'));
          }
        })
        .catch(error => {
          reject(error);
        });
      });
    }

    function viewPaymentProof(orderId, paymentProof) {
      console.log('Opening payment proof for order:', orderId, 'proof:', paymentProof);
      
      if (paymentProof && paymentProof.trim() !== '') {
        // Payment proofs are stored directly in uploads/ directory
        // Try multiple possible paths since admin is in subdirectory
      const possiblePaths = [
          '../uploads/' + paymentProof,
          'uploads/' + paymentProof,
          '/capstone2.1/uploads/' + paymentProof
        ];
        
        console.log('Trying possible paths:', possiblePaths);
        
        // Test each path until one works
      let currentPathIndex = 0;
      
      function tryNextPath() {
        if (currentPathIndex >= possiblePaths.length) {
            // All paths failed
          Swal.fire({
              title: 'Payment Proof Not Found',
              html: `
                <div class="text-center">
                  <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                  <p class="text-muted mt-3">Payment proof image not found for Order #${orderId}</p>
                  <p class="text-muted small">File: ${paymentProof}</p>
                  <p class="text-muted small">Tried paths: ${possiblePaths.join(', ')}</p>
                </div>
              `,
              confirmButtonColor: '#7F1734',
              customClass: {
                popup: 'swal2-popup-custom',
                title: 'swal2-title-custom',
                htmlContainer: 'swal2-html-container-custom',
                confirmButton: 'swal2-confirm-button-custom'
              }
          });
          return;
        }
        
          const imagePath = possiblePaths[currentPathIndex];
          console.log('Trying image path:', imagePath);
          
          // Create image element to test if image exists
          const testImage = new Image();
          testImage.onload = function() {
            // Image loaded successfully
            showPaymentProofModal(orderId, imagePath, paymentProof);
          };
          testImage.onerror = function() {
            // This path failed, try the next one
          currentPathIndex++;
          tryNextPath();
        };
          testImage.src = imagePath;
        }
        
        // Start trying paths
      tryNextPath();
      } else {
        // No payment proof filename
        Swal.fire({
          title: 'No Payment Proof',
          html: `
            <div class="text-center">
              <i class="fas fa-image text-muted" style="font-size: 4rem;"></i>
              <p class="text-muted mt-3">No payment proof uploaded for Order #${orderId}</p>
            </div>
          `,
          confirmButtonColor: '#7F1734',
          customClass: {
            popup: 'swal2-popup-custom',
            title: 'swal2-title-custom',
            htmlContainer: 'swal2-html-container-custom',
            confirmButton: 'swal2-confirm-button-custom'
          }
        });
      }
    }

    function showPaymentProofModal(orderId, imagePath, paymentProof) {
      const isSuperAdmin = <?php echo (isset($_SESSION['usertype_id']) && $_SESSION['usertype_id'] == 1) ? 'true' : 'false'; ?>;
      
      Swal.fire({
        title: `Payment Proof - Order #${orderId}`,
        html: `
          <div class="text-center">
            <img src="${imagePath}" alt="Payment Proof" class="img-fluid rounded" style="max-height: 400px; max-width: 100%; border: 2px solid #e9ecef;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
            <div style="display: none; padding: 2rem; background: #f8f9fa; border-radius: 10px; border: 2px dashed #dee2e6;">
              <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
              <p class="text-muted mt-2">Failed to load image</p>
              <p class="text-muted small">Path: ${imagePath}</p>
            </div>
            <div class="mt-3">
              <a href="${imagePath}" download="${paymentProof}" class="btn btn-primary me-2" style="background: #7F1734; border: none;">
                <i class="fas fa-download me-1"></i>Download
              </a>
              ${isSuperAdmin ? `
                <button class="btn btn-danger" onclick="rejectPaymentProof(${orderId}, '${paymentProof.replace(/'/g, "\\'")}')">
                  <i class="fas fa-times-circle me-1"></i>Reject Payment
                </button>
              ` : ''}
            </div>
            <div class="mt-2">
              <small class="text-muted">File: ${paymentProof}</small>
            </div>
          </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        cancelButtonColor: '#6c757d',
        width: '600px',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      });
    }

    function rejectPaymentProof(orderId, paymentProof) {
      Swal.fire({
         title: 'Reject Payment Proof?',
         html: `
           <div class="text-start">
             <p><strong>Order #${orderId}</strong></p>
             <p class="text-danger">This action will:</p>
             <ul class="text-danger">
               <li>Remove the payment proof from the database</li>
               <li>Change the order status to "Cancelled"</li>
               <li>Notify the customer</li>
             </ul>
             <p class="text-danger"><strong>This action cannot be undone!</strong></p>
           </div>
         `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc3545',
        cancelButtonColor: '#6c757d',
         confirmButtonText: '<i class="fas fa-ban me-2"></i>Yes, Reject Payment',
         cancelButtonText: '<i class="fas fa-times me-2"></i>Cancel',
         customClass: {
           popup: 'swal2-popup-custom',
           title: 'swal2-title-custom',
           htmlContainer: 'swal2-html-container-custom',
           confirmButton: 'swal2-confirm-button-custom',
           cancelButton: 'swal2-cancel-button-custom'
         }
      }).then((result) => {
        if (result.isConfirmed) {
           // Show loading state
           Swal.fire({
             title: 'Processing...',
             text: 'Rejecting payment proof',
             icon: 'info',
             allowOutsideClick: false,
             allowEscapeKey: false,
             showConfirmButton: false,
             didOpen: () => {
               Swal.showLoading();
             }
           });
           
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
              Swal.fire({
                 icon: 'success',
                title: 'Success!',
                text: 'Payment proof rejected successfully!',
                 confirmButtonColor: '#7F1734',
                 timer: 2000,
                 timerProgressBar: true,
                 customClass: {
                   popup: 'swal2-popup-custom',
                   title: 'swal2-title-custom',
                   htmlContainer: 'swal2-html-container-custom',
                   confirmButton: 'swal2-confirm-button-custom'
                 }
              }).then(() => {
                // Refresh the page to show updated order status
                location.reload();
              });
            } else {
              Swal.fire({
                icon: 'error',
              title: 'Error',
                 text: data.message || 'Unknown error occurred',
                 confirmButtonColor: '#7F1734'
              });
            }
          })
          .catch(error => {
            console.error('Error:', error);
            Swal.fire({
          icon: 'error',
              title: 'Error',
              text: 'Error rejecting payment proof. Please try again.',
               confirmButtonColor: '#7F1734'
            });
          });
        }
      });
    }

    function viewOrderDetails(orderId, username, email, phone, addressLine, addressLine2, city, state, postalCode, country, items, totalAmount, paymentMethod, paymentProof, transactionId, status, deliveryOption, orderDate, applicationName, riderName, plateNumber, transactionNumber) {
      // Debug: Log the received data
      console.log('Order Details Data:', {
        orderId, username, email, phone, addressLine, addressLine2, city, state, postalCode, country, 
        items, totalAmount, paymentMethod, paymentProof, transactionId, status, deliveryOption, orderDate,
        applicationName, riderName, plateNumber, transactionNumber
      });
      
      // Populate modal with order data
      document.getElementById('modalOrderId').textContent = '#' + orderId;
      document.getElementById('modalCustomerUsername').textContent = username || 'N/A';
      document.getElementById('modalCustomerEmail').textContent = email || 'N/A';
      document.getElementById('modalCustomerPhone').textContent = phone || 'N/A';
      
      // Format address
      let address = '';
      if (addressLine) address += addressLine;
      if (addressLine2) address += ', ' + addressLine2;
      if (city) address += ', ' + city;
      if (state) address += ', ' + state;
      if (postalCode) address += ' ' + postalCode;
      if (country) address += ', ' + country;
      document.getElementById('modalCustomerAddress').textContent = address || 'N/A';
      
      // Format order status with badge
      const statusBadge = `<span class="badge" style="background: ${getStatusColor(status)}; color: ${getStatusTextColor(status)}; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">${status}</span>`;
      document.getElementById('modalOrderStatus').innerHTML = statusBadge;
      
      document.getElementById('modalTotalAmount').innerHTML = `<strong>₱${parseFloat(totalAmount).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</strong>`;
      document.getElementById('modalApplicationName').textContent = applicationName || 'N/A';
      document.getElementById('modalRiderName').textContent = riderName || 'N/A';
      document.getElementById('modalPlateNumber').textContent = plateNumber || 'N/A';
      document.getElementById('modalTransactionNumber').textContent = transactionNumber || 'N/A';
      
      // Format order date
      const date = new Date(orderDate);
      const formattedDate = date.toLocaleDateString('en-US', {
        year: 'numeric',
        month: 'long',
        day: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
      });
      document.getElementById('modalOrderDate').textContent = formattedDate;
      
      // Format items
      document.getElementById('modalOrderItems').innerHTML = items ? items.replace(/,/g, '<br>') : 'N/A';
      
      // Payment information
      document.getElementById('modalPaymentMethod').textContent = paymentMethod || 'Cash On Delivery';
      
      // Transaction ID - show only for GCash payments
      let transactionIdHtml = 'N/A';
      if (transactionId && transactionId.trim() !== '' && paymentMethod && paymentMethod.toLowerCase() === 'gcash') {
        transactionIdHtml = `<span class="badge" style="background: #e8f5e8; color: #2d5a2d; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem; font-family: 'Courier New', monospace;">${transactionId}</span>`;
      }
      document.getElementById('modalTransactionId').innerHTML = transactionIdHtml;
      
      // Payment proof
      let paymentProofHtml = 'N/A';
      if (paymentMethod && paymentMethod.toLowerCase() === 'gcash') {
        if (paymentProof && paymentProof.trim() !== '') {
          paymentProofHtml = `<button class="btn btn-sm" style="background: #e6f3ff; color: #0066cc; border-radius: 15px; padding: 2px 8px; font-size: 0.7rem;" onclick="viewPaymentProof(${orderId}, '${paymentProof.replace(/'/g, "\\'")}')">
            <i class="fas fa-image me-1"></i>View Proof
          </button>`;
        } else {
          paymentProofHtml = '<span class="text-muted">No proof uploaded</span>';
        }
      }
      document.getElementById('modalPaymentProof').innerHTML = paymentProofHtml;
      
      // Show modal
      const modal = new bootstrap.Modal(document.getElementById('orderDetailsModal'));
      modal.show();
    }
    
    function getStatusColor(status) {
      const statusLower = status.toLowerCase();
      if (statusLower === 'pending') return '#fff3cd';
      if (statusLower === 'to ship') return '#d1ecf1';
      if (statusLower === 'ready for pick up') return '#f8d7da';
      if (statusLower === 'out for delivery') return '#cce5ff';
      if (statusLower === 'cancelled') return '#f5c6cb';
      if (statusLower === 'delivered' || statusLower === 'completed') return '#d4edda';
      return '#f8f9fa';
    }

    function getStatusTextColor(status) {
      const statusLower = status.toLowerCase();
      if (statusLower === 'pending') return '#856404';
      if (statusLower === 'to ship') return '#0c5460';
      if (statusLower === 'ready for pick up') return '#721c24';
      if (statusLower === 'out for delivery') return '#004085';
      if (statusLower === 'cancelled') return '#721c24';
      if (statusLower === 'delivered' || statusLower === 'completed') return '#155724';
      return '#6c757d';
    }

    // Search functionality
    function initializeSearch() {
      const searchInput = document.getElementById('orderSearchInput');
      if (!searchInput) return;

      searchInput.addEventListener('keyup', function() {
        const searchTerm = this.value.toLowerCase().trim();
        const tableRows = document.querySelectorAll('.order-row');
        
        tableRows.forEach(row => {
          const orderId = row.cells[0].textContent.toLowerCase();
          const customer = row.cells[1].textContent.toLowerCase();
          const contact = row.cells[2].textContent.toLowerCase();
          const items = row.cells[3].textContent.toLowerCase();
          const total = row.cells[4].textContent.toLowerCase();
          const payment = row.cells[5].textContent.toLowerCase();
          const transactionId = row.cells[6].textContent.toLowerCase();
          const status = row.cells[7].textContent.toLowerCase();
          const delivery = row.cells[8].textContent.toLowerCase();
          const date = row.cells[9].textContent.toLowerCase();
          
          const searchableText = `${orderId} ${customer} ${contact} ${items} ${total} ${payment} ${transactionId} ${status} ${delivery} ${date}`;
          
          if (searchTerm === '' || searchableText.includes(searchTerm)) {
            row.style.display = '';
          } else {
            row.style.display = 'none';
          }
        });
        
        // Show/hide empty state
        const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
        const emptyState = document.querySelector('.empty-state');
        if (emptyState) {
          emptyState.style.display = visibleRows.length === 0 && searchTerm !== '' ? 'block' : 'none';
        }
      });
    }

    // Initialize search when DOM is loaded
    document.addEventListener('DOMContentLoaded', function() {
      initializeSearch();
      initializeAutoRefresh();
      
      // Clear search when switching tabs
      const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
      tabButtons.forEach(button => {
        button.addEventListener('shown.bs.tab', function() {
          // Clear all search inputs
          const searchInputs = document.querySelectorAll('#orderSearchInput');
          searchInputs.forEach(input => {
            input.value = '';
          });
          
          // Show all rows in the newly active tab
          const activeTabPane = document.querySelector('.tab-pane.active');
          if (activeTabPane) {
            const tableRows = activeTabPane.querySelectorAll('.order-row');
            tableRows.forEach(row => {
              row.style.display = '';
            });
            
            // Hide empty state
            const emptyState = activeTabPane.querySelector('.empty-state');
            if (emptyState) {
              emptyState.style.display = 'none';
            }
          }
        });
      });
    });

    // Search functionality for all tabs
    function initializeSearch() {
      const searchInputs = document.querySelectorAll('#orderSearchInput');
      
      searchInputs.forEach(searchInput => {
        // Remove existing event listeners to prevent duplicates
        const newSearchInput = searchInput.cloneNode(true);
        searchInput.parentNode.replaceChild(newSearchInput, searchInput);
        
        newSearchInput.addEventListener('keyup', function() {
          const searchTerm = this.value.toLowerCase().trim();
          const activeTabPane = document.querySelector('.tab-pane.active');
          const tableRows = activeTabPane ? activeTabPane.querySelectorAll('.order-row') : document.querySelectorAll('.order-row');
          
          tableRows.forEach(row => {
            const orderId = row.cells[0].textContent.toLowerCase();
            const customer = row.cells[1].textContent.toLowerCase();
            const contact = row.cells[2].textContent.toLowerCase();
            const items = row.cells[3].textContent.toLowerCase();
            const total = row.cells[4].textContent.toLowerCase();
            const payment = row.cells[5].textContent.toLowerCase();
            const transactionId = row.cells[6].textContent.toLowerCase();
            const status = row.cells[7].textContent.toLowerCase();
            const delivery = row.cells[8].textContent.toLowerCase();
            const date = row.cells[9].textContent.toLowerCase();
            
            const searchableText = `${orderId} ${customer} ${contact} ${items} ${total} ${payment} ${transactionId} ${status} ${delivery} ${date}`;
            
            if (searchTerm === '' || searchableText.includes(searchTerm)) {
              row.style.display = '';
            } else {
              row.style.display = 'none';
            }
          });
          
          // Show/hide empty state
          const visibleRows = Array.from(tableRows).filter(row => row.style.display !== 'none');
          const emptyState = activeTabPane ? activeTabPane.querySelector('.empty-state') : document.querySelector('.empty-state');
          if (emptyState) {
            emptyState.style.display = visibleRows.length === 0 && searchTerm !== '' ? 'block' : 'none';
          }
        });
      });
    }

    // Clear search function
    function clearSearch() {
      const searchInputs = document.querySelectorAll('#orderSearchInput');
      searchInputs.forEach(input => {
        input.value = '';
      });
      
      // Show all rows in the active tab
      const activeTabPane = document.querySelector('.tab-pane.active');
      if (activeTabPane) {
        const tableRows = activeTabPane.querySelectorAll('.order-row');
        tableRows.forEach(row => {
          row.style.display = '';
        });
        
        // Hide empty state
        const emptyState = activeTabPane.querySelector('.empty-state');
        if (emptyState) {
          emptyState.style.display = 'none';
        }
      }
    }

    // AJAX Auto-Refresh Functionality
    let autoRefreshInterval;
    let isModalOpen = false;
    let isUserInteracting = false;
    let lastOrderCount = 0;
    let lastOrderIds = new Set();
    let refreshPaused = false;

    function initializeAutoRefresh() {
      console.log('Initializing auto-refresh...');
      
      // Initialize with current order count
      lastOrderCount = document.querySelectorAll('.order-row').length;
      console.log('Initial order count:', lastOrderCount);
      
      document.querySelectorAll('.order-row').forEach(row => {
        const orderId = row.cells[0].textContent.replace('#', '').trim();
        lastOrderIds.add(orderId);
      });
      
      console.log('Initial order IDs:', Array.from(lastOrderIds));

      // Start auto-refresh
      startAutoRefresh();

      // Pause when modals are opened
      document.addEventListener('show.bs.modal', function() {
        isModalOpen = true;
        pauseAutoRefresh();
      });

      // Resume when modals are closed
      document.addEventListener('hidden.bs.modal', function() {
        isModalOpen = false;
        if (!isUserInteracting) {
          setTimeout(() => {
            if (!isModalOpen && !isUserInteracting) {
              resumeAutoRefresh();
            }
          }, 2000);
        }
      });

      // Pause when user interacts with table
      const tableContainer = document.querySelector('.table-responsive');
      if (tableContainer) {
        tableContainer.addEventListener('mouseenter', function() {
          isUserInteracting = true;
          pauseAutoRefresh();
        });

        tableContainer.addEventListener('mouseleave', function() {
          isUserInteracting = false;
          setTimeout(() => {
            if (!isModalOpen && !isUserInteracting) {
              resumeAutoRefresh();
            }
          }, 2000);
        });
      }
    }

    function startAutoRefresh() {
      console.log('Starting auto-refresh...');
      if (autoRefreshInterval) clearInterval(autoRefreshInterval);
      autoRefreshInterval = setInterval(fetchOrders, 10000); // 10 seconds
      console.log('Auto-refresh interval set to 10 seconds');
      showRefreshIndicator(true);
    }

    function pauseAutoRefresh() {
      if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
        autoRefreshInterval = null;
      }
      showRefreshIndicator(false);
    }

    function resumeAutoRefresh() {
      if (!autoRefreshInterval && !refreshPaused) {
        startAutoRefresh();
      }
    }

    function showRefreshIndicator(active) {
      let indicator = document.getElementById('refreshIndicator');
      if (!indicator) {
        indicator = document.createElement('div');
        indicator.id = 'refreshIndicator';
        indicator.innerHTML = '<i class="fas fa-sync-alt"></i>';
        indicator.style.cssText = `
          position: fixed;
          bottom: 20px;
          right: 20px;
          width: 40px;
          height: 40px;
          background: var(--bs-primary);
          color: white;
          border-radius: 50%;
          display: flex;
          align-items: center;
          justify-content: center;
          z-index: 9999;
          opacity: 0.8;
          transition: all 0.3s ease;
        `;
        document.body.appendChild(indicator);
      }
      
      if (active) {
        indicator.style.display = 'flex';
        indicator.style.animation = 'pulse 2s infinite';
      } else {
        indicator.style.display = 'none';
        indicator.style.animation = 'none';
      }
    }

    async function fetchOrders() {
      try {
        console.log('Fetching orders...');
        const activeTab = document.querySelector('.nav-link.active');
        let status = 'pending'; // Default to pending tab
        
        if (activeTab) {
          const target = activeTab.getAttribute('data-bs-target');
          if (target) {
            status = target.replace('#', '');
          }
        }
        
        console.log('Active tab status:', status);
        const response = await fetch(`fetch_orders.php?status=${status}`);
        console.log('Response status:', response.status);
        
        const data = await response.json();
        console.log('Response data:', data);

        if (data.success) {
          console.log('Updating table with', data.orders.length, 'orders');
          updateOrderTable(data);
          updateStatistics(data.stats);
          updateTabBadges(data.stats);
        } else {
          console.error('API returned error:', data.error);
        }
      } catch (error) {
        console.error('Error fetching orders:', error);
      }
    }

    function updateOrderTable(data) {
      const tbody = document.querySelector('tbody');
      if (!tbody) return;

      const currentOrderIds = new Set();
      document.querySelectorAll('.order-row').forEach(row => {
        const orderId = row.cells[0].textContent.replace('#', '').trim();
        currentOrderIds.add(orderId);
      });

      // Check for new orders
      const newOrders = data.orders.filter(order => !currentOrderIds.has(order.id.toString()));
      
      if (newOrders.length > 0) {
        showNewOrderNotification(newOrders.length);
      }

      // Update table content
      tbody.innerHTML = generateTableRows(data.orders, data.is_super_admin);
      
      // Highlight new orders
      newOrders.forEach(order => {
        const newRow = document.querySelector(`tr[data-order-id="${order.id}"]`);
        if (newRow) {
          newRow.style.animation = 'highlightNew 2s ease-out';
        }
      });

      // Update last known data
      lastOrderCount = data.orders.length;
      lastOrderIds.clear();
      data.orders.forEach(order => lastOrderIds.add(order.id.toString()));
      
      // Reinitialize search for new table content
      setTimeout(() => {
        initializeSearch();
      }, 100);
    }

    function generateTableRows(orders, isSuperAdmin) {
      if (orders.length === 0) {
        return `
          <tr>
            <td colspan="11" class="text-center py-5">
              <div class="empty-state">
                <i class="fas fa-search text-muted mb-3" style="font-size: 4rem;"></i>
                <h4 class="text-muted">No Orders Found</h4>
                <p class="text-muted">There are currently no orders to display.</p>
              </div>
            </td>
          </tr>
        `;
      }

      return orders.map((order, index) => {
        try {
        const status = (order.status || 'unknown').toLowerCase();
        let badgeStyle = 'background: #f8f9fa;';
        if (status === 'pending') {
          badgeStyle = 'background: #fff3cd; color: #856404;';
        } else if (status === 'to ship') {
          badgeStyle = 'background: #e3f2fd; color: #1976d2;';
        } else if (status === 'ready for pick up') {
          badgeStyle = 'background: #fce4ec; color: #c2185b;';
        } else if (status === 'out for delivery') {
          badgeStyle = 'background: #e8f5e8; color: #388e3c;';
        } else if (status === 'cancelled') {
          badgeStyle = 'background: #ffebee; color: #d32f2f;';
        } else if (status === 'delivered' || status === 'completed') {
          badgeStyle = 'background: #f1f8e9; color: #689f38;';
        }

        // Add pickup timing information for Ready for Pick Up orders
        let pickupTimingHtml = '';
        if (order.status === 'Ready for Pick Up' && order.pickup_ready_at) {
          const pickupTime = new Date(order.pickup_ready_at);
          const now = new Date();
          const timeDiff = now - pickupTime;
          
          // Only show timing if pickup time is in the past
          if (timeDiff > 0) {
            const hoursElapsed = timeDiff / (1000 * 60 * 60);
            const isOverdue = hoursElapsed > 3;
            
             if (isOverdue) {
               pickupTimingHtml = `
                 <div class="mt-1">
                   <span class="badge" style="background: #dc3545; color: white; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                     <i class="fas fa-exclamation-triangle me-1"></i>OVERDUE: ${Math.round(hoursElapsed * 10) / 10}h
                   </span>
                 </div>
               `;
             } else {
               const minutesElapsed = Math.round(hoursElapsed * 60);
               pickupTimingHtml = `
                 <div class="mt-1">
                   <span class="badge" style="background: #ffc107; color: #212529; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                     <i class="fas fa-clock me-1"></i>Ready: ${minutesElapsed}m
                   </span>
                 </div>
               `;
             }
           } else {
             // Just processed, show 0 minutes
             pickupTimingHtml = `
               <div class="mt-1">
                 <span class="badge" style="background: #ffc107; color: #212529; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                   <i class="fas fa-clock me-1"></i>Ready: 0m
                 </span>
               </div>
             `;
           }
        }

        return `
          <tr class="order-row" data-order-id="${order.id || 'unknown'}" style="transition: all 0.2s ease;">
            <td class="fw-semibold text-dark">#${order.id || 'N/A'}</td>
            <td>
              <div class="d-flex align-items-center">
                <div class="user-avatar me-2" style="width: 32px; height: 32px; border-radius: 50%; background: #6c757d; display: flex; align-items: center; justify-content: center; color: white; font-weight: 700; font-size: 0.8rem;">
                  ${(order.username || 'U').charAt(0).toUpperCase()}
                </div>
                <span class="fw-semibold text-dark">${escapeHtml(order.username || 'Unknown User')}</span>
              </div>
            </td>
            <td>
              <div class="contact-info">
                <small class="text-muted">${escapeHtml(order.email || '')}</small>
              </div>
            </td>
            <td>
              <div class="items-preview">
                <small class="text-dark">${escapeHtml(order.items && order.items.length > 30 ? order.items.substring(0, 30) + '...' : (order.items || 'No items'))}</small>
              </div>
            </td>
            <td class="fw-bold text-dark">₱${parseFloat(order.total_amount || 0).toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
            <td>
              <div class="d-flex flex-column gap-1">
                ${order.payment_method ? `
                  <span class="badge" style="background: #f0f8ff; color: #4a90e2; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                    ${escapeHtml(order.payment_method)}
                  </span>
                  ${order.payment_method.toLowerCase() === 'gcash' && order.payment_proof ? `
                    <button class="btn btn-sm" style="background: #e6f3ff; color: #0066cc; border-radius: 15px; padding: 2px 8px; font-size: 0.7rem;" onclick="event.stopPropagation(); viewPaymentProof(${order.id}, '${order.payment_proof.replace(/'/g, "\\'")}')">
                      View Proof
                    </button>
                  ` : ''}
                ` : `
                  <span class="badge" style="background: #f5f5f5; color: #8b8b8b; border: 1px solid #e0e0e0; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                    Cash On Delivery
                  </span>
                `}
              </div>
            </td>
            <td>
              ${order.payment_method && order.payment_method.toLowerCase() === 'gcash' && order.gcash_transaction_id ? `
                <span style="color: #2d5a2d; font-size: 0.7rem; font-family: 'Courier New', monospace; font-weight: bold;">
                  ${escapeHtml(order.gcash_transaction_id)}
                </span>
              ` : `
                <span class="text-muted" style="font-size: 0.7rem;">-</span>
              `}
            </td>
            <td>
              <span class="badge" style="${badgeStyle} border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                ${escapeHtml(order.status || 'Unknown')}
              </span>
              ${pickupTimingHtml}
            </td>
            <td>
              ${order.delivery_option ? `
                <span class="badge" style="background: #f0f4f8; color: #5a6c7d; border: 1px solid #d1d9e0; border-radius: 15px; padding: 4px 8px; font-size: 0.7rem;">
                  ${escapeHtml(order.delivery_option.charAt(0).toUpperCase() + order.delivery_option.slice(1))}
                </span>
              ` : `
                <span class="text-muted">N/A</span>
              `}
            </td>
            <td class="text-muted">
              ${order.created_at ? new Date(order.created_at).toLocaleDateString('en-US', {month: 'short', day: 'numeric', year: 'numeric'}) : 'N/A'}
              <br><small>${order.created_at ? new Date(order.created_at).toLocaleTimeString('en-US', {hour: '2-digit', minute: '2-digit'}) : 'N/A'}</small>
            </td>
            <td>
              <div class="d-flex gap-1 flex-wrap">
                <button type="button" class="action-btn" style="background: #6c757d; color: white;" onclick="event.stopPropagation(); viewOrderDetails(${order.id || 0}, '${(order.username || '').replace(/'/g, "\\'")}', '${(order.email || '').replace(/'/g, "\\'")}', '${(order.phone || '').replace(/'/g, "\\'")}', '${(order.address_line || '').replace(/'/g, "\\'")}', '${(order.address_line2 || '').replace(/'/g, "\\'")}', '${(order.city || '').replace(/'/g, "\\'")}', '${(order.state || '').replace(/'/g, "\\'")}', '${(order.postal_code || '').replace(/'/g, "\\'")}', '${(order.country || '').replace(/'/g, "\\'")}', '${(order.items || '').replace(/'/g, "\\'")}', '${order.total_amount || 0}', '${(order.payment_method || '').replace(/'/g, "\\'")}', '${(order.payment_proof || '').replace(/'/g, "\\'")}', '${(order.gcash_transaction_id || '').replace(/'/g, "\\'")}', '${(order.status || '').replace(/'/g, "\\'")}', '${(order.delivery_option || '').replace(/'/g, "\\'")}', '${order.created_at || ''}', '${(order.application_name || '').replace(/'/g, "\\'")}', '${(order.rider_name || '').replace(/'/g, "\\'")}', '${(order.plate_number || '').replace(/'/g, "\\'")}', '${(order.transaction_number || '').replace(/'/g, "\\'")}')">
                  <i class="fas fa-eye me-1"></i>View Details
                </button>
                ${generateActionButtons(order, isSuperAdmin)}
              </div>
            </td>
          </tr>
        `;
        } catch (error) {
          console.error(`Error processing order ${index}:`, error, order);
          return `<tr><td colspan="11" class="text-center text-danger">Error loading order ${order.id || 'unknown'}</td></tr>`;
        }
      }).join('');
    }

    function generateActionButtons(order, isSuperAdmin) {
      let buttons = '';
      
      if (order.status === 'Pending') {
        if (order.delivery_option && order.delivery_option.toLowerCase() === 'pickup') {
          buttons += `<button type="button" class="action-btn btn-process" onclick="event.stopPropagation(); processOrder(${order.id}, 'Ready for Pick Up')">
            <i class="fas fa-cog me-1"></i>Process
          </button>`;
        } else {
          buttons += `<button type="button" class="action-btn btn-process" onclick="event.stopPropagation(); processOrder(${order.id}, 'To Ship')">
            <i class="fas fa-cog me-1"></i>Process
          </button>`;
        }
        buttons += `<button type="button" class="action-btn" style="background: #f5c6cb; color: #721c24;" onclick="event.stopPropagation(); cancelOrder(${order.id}, '${(order.delivery_option || 'delivery').toLowerCase()}')">
          <i class="fas fa-ban me-1"></i>Cancel
        </button>`;
      } else if (order.status === 'To Ship') {
        buttons += `<button type="button" class="action-btn btn-ship" onclick="event.stopPropagation(); shipOrder(${order.id})">
          <i class="fas fa-truck me-1"></i>Ship
        </button>`;
      } else if (order.status === 'Ready for Pick Up') {
        // Check if order is overdue (more than 3 hours)
        const pickupTime = new Date(order.pickup_ready_at);
        const now = new Date();
        const timeDiff = now - pickupTime;
        const isOverdue = timeDiff > 0 && (timeDiff / (1000 * 60 * 60)) > 3;
        
        if (isSuperAdmin) {
          buttons += `<button type="button" class="action-btn btn-deliver" onclick="event.stopPropagation(); completePickup(${order.id})">
            <i class="fas fa-check me-1"></i>Complete
          </button>`;
        } else {
          buttons += `<span class="action-btn btn-waiting" title="Waiting for Super Admin confirmation">
            <i class="fas fa-clock me-1"></i>Waiting
          </span>`;
        }
        
             // Add cancel button for overdue orders
             if (isOverdue) {
               const hoursElapsed = timeDiff / (1000 * 60 * 60);
               buttons += `<button type="button" class="action-btn" style="background: #dc3545; color: white;" onclick="event.stopPropagation(); cancelOverduePickup(${order.id}, ${Math.round(hoursElapsed * 10) / 10}, '${order.payment_method || ''}', '${order.payment_proof || ''}')">
                 <i class="fas fa-exclamation-triangle me-1"></i>Cancel Order
               </button>`;
             }
      } else if (order.status === 'Out for delivery') {
        buttons += `<span class="action-btn btn-waiting" title="Waiting for customer to confirm receipt">
          <i class="fas fa-clock me-1"></i>Waiting
        </span>`;
      } else if (order.status === 'Completed') {
        buttons += `<span class="action-btn btn-completed">
          <i class="fas fa-check-circle me-1"></i>Done
        </span>`;
      }
      
      return buttons;
    }

    function updateStatistics(stats) {
      document.querySelector('.card-number').textContent = stats.total_orders;
      document.querySelectorAll('.card-number')[1].textContent = stats.pending_orders;
      document.querySelectorAll('.card-number')[2].textContent = stats.processing_orders;
      document.querySelectorAll('.card-number')[3].textContent = stats.completed_orders;
    }

    function updateTabBadges(stats) {
      const badges = document.querySelectorAll('.nav-link .badge');
      if (badges.length >= 7) {
        badges[0].textContent = stats.total_orders;
        badges[1].textContent = stats.pending_orders;
        badges[2].textContent = stats.processing_orders;
        badges[3].textContent = stats.pickup_orders;
        badges[4].textContent = stats.shipped_orders;
        badges[5].textContent = stats.completed_orders;
        badges[6].textContent = stats.cancelled_orders;
      }
    }

    function showNewOrderNotification(count) {
      const notification = document.createElement('div');
      notification.innerHTML = `
        <div style="position: fixed; top: 80px; right: 20px; background: #28a745; color: white; padding: 12px 20px; border-radius: 10px; box-shadow: 0 4px 12px rgba(0,0,0,0.15); z-index: 10000; animation: slideInRight 0.3s ease-out;">
          <i class="fas fa-shopping-cart me-2"></i>
          ${count} new order${count > 1 ? 's' : ''} received!
        </div>
      `;
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.style.animation = 'slideOutRight 0.3s ease-in';
        setTimeout(() => notification.remove(), 300);
      }, 3000);
    }

    function escapeHtml(text) {
      const div = document.createElement('div');
      div.textContent = text;
      return div.innerHTML;
    }

    // Function to view refund receipt (admin side)
    function viewRefundReceipt(orderId, receiptPath, receiptFilename) {
      // Try multiple possible paths for the receipt
      const possiblePaths = [
        receiptPath,
        '../' + receiptPath,
        'uploads/cancellation_receipts/' + receiptFilename
      ];
      
      // Test each path until one works
      let currentPathIndex = 0;
      
      function tryNextPath() {
        if (currentPathIndex >= possiblePaths.length) {
          // All paths failed
          Swal.fire({
            title: 'Receipt Not Found',
            html: `
              <div class="text-center">
                <i class="fas fa-exclamation-triangle text-warning" style="font-size: 4rem;"></i>
                <p class="text-muted mt-3">Receipt file not found for Order #${orderId}</p>
                <p class="text-muted small">File: ${receiptFilename}</p>
              </div>
            `,
            confirmButtonColor: '#7F1734',
            confirmButtonText: 'OK',
            customClass: {
              popup: 'swal2-popup-custom',
              title: 'swal2-title-custom',
              htmlContainer: 'swal2-html-container-custom',
              confirmButton: 'swal2-confirm-button-custom'
            }
          });
          return;
        }
        
        const imagePath = possiblePaths[currentPathIndex];
        
        // Create image element to test if file exists
        const testImage = new Image();
        testImage.onload = function() {
          // File loaded successfully
          showAdminReceiptModal(orderId, imagePath, receiptFilename);
        };
        testImage.onerror = function() {
          // This path failed, try the next one
          currentPathIndex++;
          tryNextPath();
        };
        testImage.src = imagePath;
      }
      
      // Start trying paths
      tryNextPath();
    }

    function showAdminReceiptModal(orderId, imagePath, receiptFilename) {
      Swal.fire({
        title: `Receipt - Order #${orderId}`,
        html: `
          <div class="text-center">
            <img src="${imagePath}" alt="Receipt" class="img-fluid rounded" style="max-height: 400px; max-width: 100%; border: 2px solid #e9ecef;" onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
            <div style="display: none; padding: 2rem; background: #f8f9fa; border-radius: 10px; border: 2px dashed #dee2e6;">
              <i class="fas fa-exclamation-triangle text-warning" style="font-size: 3rem;"></i>
              <p class="text-muted mt-2">Failed to load receipt</p>
              <p class="text-muted small">Path: ${imagePath}</p>
            </div>
            <div class="mt-3">
              <a href="${imagePath}" download="${receiptFilename}" class="btn btn-primary me-2" style="background: #7F1734; border: none;">
                <i class="fas fa-download me-1"></i>Download Receipt
              </a>
            </div>
            <div class="mt-2">
              <small class="text-muted">File: ${receiptFilename}</small>
            </div>
          </div>
        `,
        showConfirmButton: false,
        showCancelButton: true,
        cancelButtonText: 'Close',
        cancelButtonColor: '#6c757d',
        width: '600px',
        customClass: {
          popup: 'swal2-popup-custom',
          title: 'swal2-title-custom',
          htmlContainer: 'swal2-html-container-custom',
          cancelButton: 'swal2-cancel-button-custom'
        }
      });
    }

    // Add CSS animations
    const style = document.createElement('style');
    style.textContent = `
      @keyframes highlightNew {
        0% { background-color: rgba(40, 167, 69, 0.3); }
        100% { background-color: transparent; }
      }
      
      @keyframes pulse {
        0% { transform: scale(1); opacity: 0.8; }
        50% { transform: scale(1.1); opacity: 1; }
        100% { transform: scale(1); opacity: 0.8; }
      }
      
      @keyframes slideInRight {
        from { transform: translateX(100%); opacity: 0; }
        to { transform: translateX(0); opacity: 1; }
      }
      
      @keyframes slideOutRight {
        from { transform: translateX(0); opacity: 1; }
        to { transform: translateX(100%); opacity: 0; }
      }
      
      /* Search input styling */
      #orderSearchInput {
        padding-right: 30px;
      }
      
      #orderSearchInput:focus {
        border-color: var(--bs-primary);
        box-shadow: 0 0 0 0.2rem rgba(127, 23, 52, 0.25);
      }
      
      .search-clear-btn {
        transition: all 0.2s ease;
      }
      
      .search-clear-btn:hover {
        color: var(--bs-danger) !important;
        transform: scale(1.1);
      }
    `;
    document.head.appendChild(style);

  </script>
</body>
</html>