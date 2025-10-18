<?php
require '../vendor/autoload.php';
require '../includes/db.php';
include_once '../includes/permissions.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// Ensure user is logged in and not a customer
if (!isset($_SESSION['user_id'])) {
    header("Location: login_admin.php");
    exit;
}

// Check if user is not a customer
if (isCustomer($pdo)) {
    header("Location: login_admin.php");
    exit;
}

date_default_timezone_set('Asia/Manila');

// Get report type and period
$type = $_GET['type'] ?? 'sales';
$period = $_GET['period'] ?? 'daily';

// Check for custom date range
$customFrom = $_GET['custom_from'] ?? null;
$customTo = $_GET['custom_to'] ?? null;
$isCustomRange = $customFrom && $customTo;

function getDateRange($period) {
    switch ($period) {
        case 'daily':
            return [date('Y-m-d 00:00:00'), date('Y-m-d 23:59:59')];
        case 'weekly':
            return [date('Y-m-d 00:00:00', strtotime('monday this week')), date('Y-m-d 23:59:59', strtotime('sunday this week'))];
        case 'monthly':
            return [date('Y-m-01 00:00:00'), date('Y-m-t 23:59:59')];
        case 'yearly':
            return [date('Y-01-01 00:00:00'), date('Y-12-31 23:59:59')];
        default:
            return [null, null];
    }
}

function formatCurrency($amount) {
    return '₱ ' . number_format($amount, 2);
}

function getPeriodText($period, $customFrom = null, $customTo = null) {
    global $isCustomRange;
    
    if ($isCustomRange && $customFrom && $customTo) {
        return 'Custom Range Report - ' . date('M d, Y', strtotime($customFrom)) . ' to ' . date('M d, Y', strtotime($customTo));
    }
    
    switch ($period) {
        case 'daily':
            return 'Daily Report - ' . date('F d, Y');
        case 'weekly':
            $start = date('M d', strtotime('monday this week'));
            $end = date('M d, Y', strtotime('sunday this week'));
            return 'Weekly Report - ' . $start . ' to ' . $end;
        case 'monthly':
            return 'Monthly Report - ' . date('F Y');
        case 'yearly':
            return 'Yearly Report - ' . date('Y');
        default:
            return 'Report';
    }
}

// Start output buffering
ob_start();

// Generate HTML content
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title><?= ucfirst($type) ?> Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 0;
            padding: 10px;
            font-size: 10px;
        }
        .header {
            text-align: center;
            margin-bottom: 15px;
            border-bottom: 1px solid #000;
            padding-bottom: 10px;
        }
        .company-name {
            font-size: 14px;
            font-weight: bold;
            margin: 2px 0;
        }
        .report-title {
            font-size: 12px;
            margin: 2px 0;
        }
        .report-period {
            font-size: 10px;
            margin: 2px 0;
        }
        .generated-date {
            font-size: 8px;
            margin: 2px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 10px 0;
            border: 1px solid #000;
        }
        th {
            background: #f0f0f0;
            border: 1px solid #000;
            padding: 6px 4px;
            text-align: left;
            font-weight: bold;
            font-size: 9px;
        }
        td {
            border: 1px solid #000;
            padding: 4px;
            font-size: 9px;
            vertical-align: top;
        }
        .total-row {
            background: #f0f0f0 !important;
            font-weight: bold;
        }
        .footer {
            margin-top: 15px;
            text-align: center;
            font-size: 8px;
            border-top: 1px solid #000;
            padding-top: 10px;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">MikeMadz</div>
        <div class="report-title"><?= ucfirst($type) ?> Report</div>
        <div class="report-period"><?= getPeriodText($period, $customFrom, $customTo) ?></div>
        <div class="generated-date">Generated on: <?= date('F d, Y \a\t g:i A') ?></div>
    </div>

<?php
try {
    if ($type === 'sales') {
        if ($isCustomRange) {
            $start = $customFrom . ' 00:00:00';
            $end = $customTo . ' 23:59:59';
        } else {
            list($start, $end) = getDateRange($period);
        }
        
        // Get sales data
        $stmt = $pdo->prepare("
            SELECT 
                COALESCE(b.name, 'No Brand') as brand_name,
                p.product_name,
                SUM(oi.quantity) as total_quantity,
                SUM(oi.quantity * oi.price) as total_amount,
                o.created_at as order_date
            FROM orders o
            INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
            INNER JOIN order_items oi ON o.orders_id = oi.order_id
            INNER JOIN products p ON oi.product_id = p.product_id
            LEFT JOIN brands b ON oi.brand_id = b.id
            WHERE os.status_name = 'Completed' AND o.created_at BETWEEN ? AND ?
            GROUP BY b.name, p.product_name, o.created_at
            ORDER BY o.created_at DESC, p.product_name ASC
        ");
        $stmt->execute([$start, $end]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Sales table
        echo '<table>';
        echo '<thead><tr><th>Brand</th><th>Product</th><th>Quantity</th><th>Total</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($sales)) {
            echo '<tr><td colspan="4">No sales data found for this period.</td></tr>';
        } else {
            $totalQuantity = 0;
            $totalAmount = 0;
            
            foreach ($sales as $row) {
                echo '<tr>';
                echo '<td>' . htmlspecialchars($row['brand_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                echo '<td>' . number_format($row['total_quantity'], 1) . '</td>';
                echo '<td>' . formatCurrency($row['total_amount']) . '</td>';
                echo '</tr>';
                
                $totalQuantity += $row['total_quantity'];
                $totalAmount += $row['total_amount'];
            }
            
            // Add total row
            echo '<tr class="total-row">';
            echo '<td colspan="2"><strong>TOTAL</strong></td>';
            echo '<td><strong>' . number_format($totalQuantity, 1) . '</strong></td>';
            echo '<td><strong>' . formatCurrency($totalAmount) . '</strong></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'inventory') {
        // Get inventory data (only active products)
        $stmt = $pdo->query("
            SELECT p.product_id as id, p.product_name as name, 
                   COALESCE(ps.current_stock, 0) as stock, 
                   COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as price, 
                   p.is_archive as is_archived, 
                   c.category_name AS category
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.category_id
            LEFT JOIN product_stock ps ON p.product_id = ps.product_id
            LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
            WHERE p.is_archive = 0
            ORDER BY c.category_name, p.product_name
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Inventory table
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Product Name</th><th>Category</th><th>Stock</th><th>Price</th><th>Total Value</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($products)) {
            echo '<tr><td colspan="6">No active products found.</td></tr>';
        } else {
            foreach ($products as $row) {
                $totalValue = $row['stock'] * $row['price'];
                
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['category']) . '</td>';
                echo '<td>' . $row['stock'] . '</td>';
                echo '<td>' . formatCurrency($row['price']) . '</td>';
                echo '<td>' . formatCurrency($totalValue) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'orders') {
        if ($isCustomRange) {
            $start = $customFrom . ' 00:00:00';
            $end = $customTo . ' 23:59:59';
        } else {
            list($start, $end) = getDateRange($period);
        }
        
        // Get orders data
        $stmt = $pdo->prepare("
            SELECT o.orders_id as id, u.username as customer, o.total_price, os.status_name as status, o.created_at,
                   GROUP_CONCAT(CONCAT(p.product_name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
            FROM orders o
            INNER JOIN users u ON o.user_id = u.user_id
            INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
            LEFT JOIN order_items oi ON o.orders_id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.product_id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY o.orders_id
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$start, $end]);
        $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Orders table
        echo '<table>';
        echo '<thead><tr><th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($orders)) {
            echo '<tr><td colspan="6">No orders found for this period.</td></tr>';
        } else {
            foreach ($orders as $row) {
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['customer']) . '</td>';
                echo '<td>' . htmlspecialchars($row['items']) . '</td>';
                echo '<td>' . formatCurrency($row['total_price']) . '</td>';
                echo '<td>' . htmlspecialchars($row['status']) . '</td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'pullout') {
        if ($isCustomRange) {
            $start = $customFrom . ' 00:00:00';
            $end = $customTo . ' 23:59:59';
        } else {
            list($start, $end) = getDateRange($period);
        }
        
        // Get pullout data
        $stmt = $pdo->prepare("
            SELECT 
                sa.stockadjustment_id as id,
                p.product_name as product_name,
                COALESCE(b.name, 'N/A') as brand_name,
                sa.quantity as quantity,
                sa.reason as reason,
                sa.created_at as created_at
            FROM stock_adjustment sa
            INNER JOIN products p ON sa.product_id = p.product_id
            LEFT JOIN product_batches pb ON pb.product_id = p.product_id AND pb.is_active = 1
            LEFT JOIN brands b ON pb.brand_id = b.id
            WHERE sa.adjustment_type_id = 2 
            AND sa.reason IN ('Damaged Items', 'Theft/Loss')
            AND sa.created_at BETWEEN ? AND ?
            GROUP BY sa.stockadjustment_id, p.product_name, b.name, sa.quantity, sa.reason, sa.created_at
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$start, $end]);
        $pulloutData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Pullout table
        echo '<table>';
        echo '<thead><tr><th>Adjustment ID</th><th>Product</th><th>Brand</th><th>Quantity</th><th>Reason</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($pulloutData)) {
            echo '<tr><td colspan="6">No pull out data found for this period.</td></tr>';
        } else {
            foreach ($pulloutData as $row) {
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['brand_name']) . '</td>';
                echo '<td>' . $row['quantity'] . '</td>';
                echo '<td>' . htmlspecialchars($row['reason']) . '</td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'supplier_returns') {
        if ($isCustomRange) {
            $start = $customFrom . ' 00:00:00';
            $end = $customTo . ' 23:59:59';
        } else {
            list($start, $end) = getDateRange($period);
        }
        
        // Get supplier returns data
        $stmt = $pdo->prepare("
            SELECT 
                sa.stockadjustment_id as id,
                p.product_name as product_name,
                COALESCE(b.name, 'N/A') as brand_name,
                sa.quantity as quantity,
                sa.reason as reason,
                sa.created_at as created_at
            FROM stock_adjustment sa
            INNER JOIN products p ON sa.product_id = p.product_id
            LEFT JOIN product_batches pb ON pb.product_id = p.product_id AND pb.is_active = 1
            LEFT JOIN brands b ON pb.brand_id = b.id
            WHERE sa.adjustment_type_id = 2 
            AND sa.reason = 'Supplier Return'
            AND sa.created_at BETWEEN ? AND ?
            GROUP BY sa.stockadjustment_id, p.product_name, b.name, sa.quantity, sa.reason, sa.created_at
            ORDER BY sa.created_at DESC
        ");
        $stmt->execute([$start, $end]);
        $supplierReturnsData = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Supplier returns table
        echo '<table>';
        echo '<thead><tr><th>Adjustment ID</th><th>Product</th><th>Brand</th><th>Quantity</th><th>Reason</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($supplierReturnsData)) {
            echo '<tr><td colspan="6">No supplier returns found for this period.</td></tr>';
        } else {
            foreach ($supplierReturnsData as $row) {
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['product_name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['brand_name']) . '</td>';
                echo '<td>' . $row['quantity'] . '</td>';
                echo '<td>' . htmlspecialchars($row['reason']) . '</td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'returns') {
        // Returns functionality not implemented yet
        echo '<table>';
        echo '<thead><tr><th>Return ID</th><th>Order ID</th><th>Customer</th><th>Product</th><th>Reason</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td colspan="6">Returns functionality not implemented yet.</td></tr>';
        echo '</tbody></table>';
    }
    
} catch (Exception $e) {
    echo '<div style="color: red; text-align: center; padding: 20px;">Error generating report: ' . htmlspecialchars($e->getMessage()) . '</div>';
}
?>

    <div class="footer">
        <p>This report was generated automatically by MikeMadz Admin System</p>
        <p>For questions or support, please contact the administrator</p>
    </div>
</body>
</html>

<?php
$html = ob_get_clean();

// Configure DOMPDF
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isPhpEnabled', true);
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

// Generate PDF
try {
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Generate filename
    $filename = $type . '_report_' . $period . '_' . date("Y-m-d_H-i-s") . '.pdf';
    
    // Output PDF
    $dompdf->stream($filename, [
        "Attachment" => true,
        "Content-Type" => "application/pdf"
    ]);
    
} catch (Exception $e) {
    // If PDF generation fails, show error
    echo '<div style="color: red; text-align: center; padding: 20px; font-family: Arial, sans-serif;">';
    echo '<h2>PDF Generation Error</h2>';
    echo '<p>Error: ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Please try again or contact the administrator.</p>';
    echo '</div>';
}
?>
