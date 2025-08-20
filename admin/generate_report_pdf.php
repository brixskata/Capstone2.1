<?php
require '../vendor/autoload.php';
require 'db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();
if (!isset($_SESSION['username']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
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
            background-color: #181f2a;
        }
        .header {
            text-align: center;
            margin-bottom: 30px;
            border-bottom: 3px solid #36c6f0;
            padding-bottom: 20px;
        }
        .company-name {
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(90deg, #36c6f0, #7f5af0, #ff5fd2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
            margin: 5px 0;
        }
        .report-title {
            font-size: 20px;
            color: #fff;
            margin: 5px 0;
        }
        .report-period {
            font-size: 14px;
            color: #b5eaff;
            margin: 5px 0;
        }
        .generated-date {
            font-size: 12px;
            color: #7f8fa6;
            margin: 5px 0;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
            background-color: #232946;
        }
        th {
            background: linear-gradient(90deg, #36c6f0, #7f5af0, #ff5fd2);
            color: white;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 14px;
        }
        td {
            padding: 10px 8px;
            border-bottom: 1px solid #2d334a;
            font-size: 13px;
            color: #e0e6ed;
        }
        tr:nth-child(even) {
            background-color: #20263a;
        }
        tr:hover {
            background-color: #232946;
        }
        .total-row {
            background: linear-gradient(90deg, #36c6f0, #7f5af0, #ff5fd2) !important;
            color: white;
            font-weight: bold;
        }
        .status-badge {
            padding: 4px 8px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: bold;
        }
        .status-pending { background-color: #ffe066; color: #7c6f00; }
        .status-processing { background-color: #36c6f0; color: #00334e; }
        .status-shipped { background-color: #7f5af0; color: #fff; }
        .status-delivered { background-color: #43e97b; color: #0a3d1a; }
        .status-return { background-color: #ff5fd2; color: #6d004e; }
        .status-active { background-color: #43e97b; color: #0a3d1a; }
        .status-archived { background-color: #ff5fd2; color: #6d004e; }
        .summary-section {
            margin: 20px 0;
            padding: 15px;
            background: linear-gradient(90deg, #232946 80%, #36c6f0 100%);
            border-radius: 8px;
            border-left: 4px solid #36c6f0;
        }
        .summary-title {
            font-size: 16px;
            font-weight: bold;
            color: #fff;
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
            background: linear-gradient(90deg, #232946 80%, #7f5af0 100%);
            border-radius: 6px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .summary-number {
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(90deg, #36c6f0, #7f5af0, #ff5fd2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            text-fill-color: transparent;
        }
        .summary-label {
            font-size: 12px;
            color: #b5eaff;
            margin-top: 5px;
        }
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 12px;
            color: #7f8fa6;
            border-top: 1px solid #2d334a;
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
            SELECT o.id, u.username as customer, o.total_price, o.created_at, o.status
            FROM orders o
            INNER JOIN users u ON o.user_id = u.id
            WHERE o.created_at BETWEEN ? AND ? AND o.status = 'Delivered'
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
        // Get inventory data
        $stmt = $pdo->query("
            SELECT p.id, p.name, p.stock, p.price, p.is_archived, c.name AS category
            FROM products p
            LEFT JOIN categories c ON p.category_id = c.id
            ORDER BY c.name, p.name
        ");
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate totals
        $totalProducts = count($products);
        $activeProducts = count(array_filter($products, fn($p) => !$p['is_archived']));
        $totalValue = array_sum(array_map(fn($p) => $p['stock'] * $p['price'], $products));
        
        // Summary section
        echo '<div class="summary-section">';
        echo '<div class="summary-title">Inventory Summary</div>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . $totalProducts . '</div>';
        echo '<div class="summary-label">Total Products</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . $activeProducts . '</div>';
        echo '<div class="summary-label">Active Products</div>';
        echo '</div>';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . formatCurrency($totalValue) . '</div>';
        echo '<div class="summary-label">Total Inventory Value</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Inventory table
        echo '<table>';
        echo '<thead><tr><th>ID</th><th>Product Name</th><th>Category</th><th>Stock</th><th>Price</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($products)) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">No products found.</td></tr>';
        } else {
            foreach ($products as $row) {
                $status = $row['is_archived'] ? 'Archived' : 'Active';
                $statusClass = $row['is_archived'] ? 'status-archived' : 'status-active';
                
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['name']) . '</td>';
                echo '<td>' . htmlspecialchars($row['category']) . '</td>';
                echo '<td>' . $row['stock'] . '</td>';
                echo '<td>' . formatCurrency($row['price']) . '</td>';
                echo '<td><span class="status-badge ' . $statusClass . '">' . $status . '</span></td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        
    } elseif ($type === 'orders') {
        list($start, $end) = getDateRange($period);
        
        // Get orders data
        $stmt = $pdo->prepare("
            SELECT o.id, u.username as customer, o.total_price, o.status, o.created_at,
                   GROUP_CONCAT(CONCAT(p.name, ' (', oi.quantity, ')') SEPARATOR ', ') as items
            FROM orders o
            INNER JOIN users u ON o.user_id = u.id
            LEFT JOIN order_items oi ON o.id = oi.order_id
            LEFT JOIN products p ON oi.product_id = p.id
            WHERE o.created_at BETWEEN ? AND ?
            GROUP BY o.id
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
        // Get returns data
        $stmt = $pdo->query("
            SELECT r.id, r.order_id, r.product, r.reason, r.created_at, u.username as customer
            FROM returns r
            LEFT JOIN orders o ON r.order_id = o.id
            LEFT JOIN users u ON o.user_id = u.id
            ORDER BY r.created_at DESC
        ");
        $returns = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Summary section
        echo '<div class="summary-section">';
        echo '<div class="summary-title">Returns Summary</div>';
        echo '<div class="summary-grid">';
        echo '<div class="summary-item">';
        echo '<div class="summary-number">' . count($returns) . '</div>';
        echo '<div class="summary-label">Total Returns</div>';
        echo '</div>';
        echo '</div>';
        echo '</div>';
        
        // Returns table
        echo '<table>';
        echo '<thead><tr><th>Return ID</th><th>Order ID</th><th>Customer</th><th>Product</th><th>Reason</th><th>Date</th></tr></thead>';
        echo '<tbody>';
        
        if (empty($returns)) {
            echo '<tr><td colspan="6" style="text-align: center; padding: 20px; color: #666;">No returns found.</td></tr>';
        } else {
            foreach ($returns as $row) {
                echo '<tr>';
                echo '<td>#' . $row['id'] . '</td>';
                echo '<td>#' . $row['order_id'] . '</td>';
                echo '<td>' . htmlspecialchars($row['customer']) . '</td>';
                echo '<td>' . htmlspecialchars($row['product']) . '</td>';
                echo '<td>' . htmlspecialchars($row['reason']) . '</td>';
                echo '<td>' . date('M d, Y H:i', strtotime($row['created_at'])) . '</td>';
                echo '</tr>';
            }
        }
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
