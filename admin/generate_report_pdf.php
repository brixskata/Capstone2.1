<?php
require '../vendor/autoload.php';
require '../includes/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    header("Location: login_admin.php");
    exit;
}

date_default_timezone_set('Asia/Manila');

// Get report type and period
$type = $_GET['type'] ?? 'sales';
$period = $_GET['period'] ?? 'daily';

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

function getPeriodText($period) {
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
            padding: 20px;
            background-color: #ffffff;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #7F1734;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            color: #7F1734;
            margin: 5px 0;
        }
        .report-title {
            font-size: 20px;
            color: #7F1734;
            margin: 5px 0;
        }
        .report-period {
            font-size: 14px;
            color: #6c757d;
            margin: 5px 0;
        }
        .generated-date {
            font-size: 12px;
            color: #6c757d;
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #ffffff;
            border: 1px solid #dee2e6;
        }
        th {
            background: #7F1734;
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 14px;
        }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #dee2e6;
            font-size: 13px;
            color: #212529;
        }
        tr:nth-child(even) {
            background-color: #f8f9fa;
        }
        tr:hover {
            background-color: #e9ecef;
        }
        .total-row {
            background: #7F1734 !important;
            color: white;
            font-weight: bold;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending { background-color: #ffc107; color: #000; }
        .status-processing { background-color: #0dcaf0; color: #000; }
        .status-shipped { background-color: #7F1734; color: #fff; }
        .status-delivered { background-color: #198754; color: #fff; }
        .status-return { background-color: #dc3545; color: #fff; }
        .status-active { background-color: #198754; color: #fff; }
        .status-archived { background-color: #dc3545; color: #fff; }
        .summary-section {
            margin: 20px 0;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 8px;
            border-left: 4px solid #7F1734;
        }
        .summary-title {
            font-size: 16px;
            font-weight: bold;
            color: #7F1734;
            margin-bottom: 10px;
        }
        .summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .summary-item {
            text-align: center;
            padding: 10px;
            background: #ffffff;
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            border: 1px solid #dee2e6;
        }
        .summary-number {
            font-size: 24px;
            font-weight: bold;
            color: #7F1734;
        }
        .summary-label {
            font-size: 12px;
            color: #6c757d;
            margin-top: 5px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #6c757d;
            border-top: 1px solid #dee2e6;
            padding-top: 20px;
        }
        .page-break {
            page-break-before: always;
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="company-name">MikeMadz</div>
        <div class="report-title"><?= ucfirst($type) ?> Report</div>
        <div class="report-period"><?= getPeriodText($period) ?></div>
        <div class="generated-date">Generated on: <?= date('F d, Y \a\t g:i A') ?></div>
    </div>

<?php
try {
    if ($type === 'sales') {
        list($start, $end) = getDateRange($period);
        
        // Get sales data
        $stmt = $pdo->prepare("
            SELECT o.orders_id as id, u.username as customer, o.total_price, o.created_at, os.status_name as status
            FROM orders o
            INNER JOIN users u ON o.user_id = u.user_id
            INNER JOIN order_status os ON o.orderstatus_id = os.orderstatus_id
            WHERE o.created_at BETWEEN ? AND ? AND os.status_name = 'Completed'
            ORDER BY o.created_at DESC
        ");
        $stmt->execute([$start, $end]);
        $sales = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $totalSales = array_sum(array_column($sales, 'total_price'));
        $totalOrders = count($sales);
        
        // Summary section
        echo '<div class="summary-section">';
        echo '<div class="summary-title">Sales Summary</div>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . $totalOrders . '</div>';
        echo '<div class="summary-label">Total Orders</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . formatCurrency($totalSales) . '</div>';
        echo '<div class="summary-label">Total Revenue</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . formatCurrency($totalOrders > 0 ? $totalSales / $totalOrders : 0) . '</div>';
        echo '<div class="summary-label">Average Order Value</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Sales table
        echo '<table>';
        echo '<thead><tr><th>Order ID</th><th>Customer</th><th>Total Amount</th><th>Order Date</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($sales)) {
            echo '<tr><td colspan="4" style="text-align: center; padding: 20px; color: #666;">No sales data found for this period.</td></tr>';
        } else {
            foreach ($sales as $row) {
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['customer']) . '</td>';
                echo '<td>' . formatCurrency($row['total_price']) . '</td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
            echo '<tr class="total-row">';
            echo '<td colspan="2"><strong>Total Sales</strong></td>';
            echo '<td colspan="2"><strong>' . formatCurrency($totalSales) . '</strong></td>';
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
        
        // Calculate totals
        $totalProducts = count($products);
        $totalValue = array_sum(array_map(fn($p) => $p['stock'] * $p['price'], $products));
        $avgPrice = $totalProducts > 0 ? $totalValue / $totalProducts : 0;
        
        // Summary section
        echo '<div class="summary-section">';
        echo '<div class="summary-title">Inventory Summary</div>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . $totalProducts . '</div>';
        echo '<div class="summary-label">Active Products</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . formatCurrency($totalValue) . '</div>';
        echo '<div class="summary-label">Total Inventory Value</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . formatCurrency($avgPrice) . '</div>';
        echo '<div class="summary-label">Average Product Value</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Inventory table
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Product Name</th><th>Category</th><th>Stock</th><th>Price</th><th>Total Value</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($products)) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">No active products found.</td></tr>';
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
        list($start, $end) = getDateRange($period);
        
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
        
        // Calculate totals
        $totalOrders = count($orders);
        $totalRevenue = array_sum(array_column($orders, 'total_price'));
        $statusCounts = array_count_values(array_column($orders, 'status'));
        
        // Summary section
        echo '<div class="summary-section">';
        echo '<div class="summary-title">Orders Summary</div>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . $totalOrders . '</div>';
        echo '<div class="summary-label">Total Orders</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . formatCurrency($totalRevenue) . '</div>';
        echo '<div class="summary-label">Total Revenue</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . ($statusCounts['Pending'] ?? 0) . '</div>';
        echo '<div class="summary-label">Pending Orders</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . ($statusCounts['Delivered'] ?? 0) . '</div>';
        echo '<div class="summary-label">Delivered Orders</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Orders table
        echo '<table>';
        echo '<thead><tr><th>Order ID</th><th>Customer</th><th>Items</th><th>Total</th><th>Status</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($orders)) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">No orders found for this period.</td></tr>';
        } else {
            foreach ($orders as $row) {
                $statusClass = 'status-' . strtolower($row['status']);
                
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['customer']) . '</td>';
                echo '<td style="max-width: 200px; font-size: 11px;">' . htmlspecialchars($row['items']) . '</td>';
                echo '<td>' . formatCurrency($row['total_price']) . '</td>';
                echo '<td><span class="status-badge ' . $statusClass . '">' . htmlspecialchars($row['status']) . '</span></td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'returns') {
        // Returns functionality not implemented yet
        $returns = [];
        
        // Summary section
        echo '<div class="summary-section">';
        echo '<div class="summary-title">Returns Summary</div>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">0</div>';
        echo '<div class="summary-label">Total Returns</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Returns table
        echo '<table>';
        echo '<thead><tr><th>Return ID</th><th>Order ID</th><th>Customer</th><th>Product</th><th>Reason</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">Returns functionality not implemented yet.</td></tr>';
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
