<?php
require 'vendor/autoload.php';
require 'includes/db.php';

use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// Ensure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Get order ID from URL
$order_id = $_GET['order_id'] ?? null;

if (!$order_id) {
    die('Order ID is required');
}

// Validate order ID is numeric
if (!is_numeric($order_id)) {
    die('Invalid order ID');
}

$user_id = $_SESSION['user_id'];

try {
    // Fetch order details with customer information
    $sql = "SELECT 
                o.orders_id,
                o.created_at,
                o.total_price,
                o.delivery_option,
                o.plate_number,
                o.transaction_number,
                os.status_name as status,
                u.username,
                ui.first_name,
                ui.last_name,
                ui.email,
                ui.phone,
                a.address_line,
                a.address_line2,
                a.city,
                a.state,
                a.postal_code,
                a.country,
                pay.method as payment_method,
                pay.transaction_id as gcash_transaction_id
            FROM orders o
            INNER JOIN users u ON o.user_id = u.user_id
            LEFT JOIN user_info ui ON ui.user_id = u.user_id
            LEFT JOIN addresses a ON a.address_id = o.address_id
            LEFT JOIN order_status os ON os.orderstatus_id = o.orderstatus_id
            LEFT JOIN payments pay ON pay.orders_id = o.orders_id
            WHERE o.orders_id = :order_id AND o.user_id = :user_id";
    
    $stmt = $pdo->prepare($sql);
    $stmt->bindParam(':order_id', $order_id);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        die('Order not found or you do not have permission to view this invoice');
    }
    
    // Fetch order items
    $items_sql = "SELECT 
                    p.product_name,
                    oi.quantity,
                    COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0) as unit_price,
                    (COALESCE(pp.markup_price, 0) + COALESCE(pp.cost_price, 0)) * oi.quantity as total_price
                 FROM order_items oi
                 INNER JOIN products p ON oi.product_id = p.product_id
                 LEFT JOIN product_pricing pp ON p.product_id = pp.product_id
                 WHERE oi.order_id = :order_id";
    
    $items_stmt = $pdo->prepare($items_sql);
    $items_stmt->bindParam(':order_id', $order_id);
    $items_stmt->execute();
    $items = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Set timezone
    date_default_timezone_set('Asia/Manila');
    
    // Configure DomPDF
    $options = new Options();
    $options->set('defaultFont', 'Arial');
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('defaultPaperSize', 'A4');
    $options->set('defaultPaperOrientation', 'portrait');
    $options->set('isRemoteEnabled', true);
    $options->set('isFontSubsettingEnabled', true);
    
    $dompdf = new Dompdf($options);
    
    // Debug: Check if items match order total
    $calculated_total = 0;
    foreach ($items as $item) {
        $calculated_total += $item['total_price'];
    }
    
    // Use the order's total_price if it exists and is different from calculated
    if ($order['total_price'] && abs($order['total_price'] - $calculated_total) > 0.01) {
        // If there's a discrepancy, adjust item prices proportionally
        $adjustment_factor = $order['total_price'] / $calculated_total;
        foreach ($items as &$item) {
            $item['unit_price'] = $item['unit_price'] * $adjustment_factor;
            $item['total_price'] = $item['total_price'] * $adjustment_factor;
        }
        $total_amount = $order['total_price'];
    } else {
        $total_amount = $calculated_total;
    }
    
    // Generate HTML content
    $html = generateInvoiceHTML($order, $items, $total_amount);
    
    // Load HTML into DomPDF
    $dompdf->loadHtml($html, 'UTF-8');
    
    // Set paper size and orientation
    $dompdf->setPaper('A4', 'portrait');
    
    // Render PDF
    $dompdf->render();
    
    // Generate filename
    $filename = 'E-Invoice_' . $order['orders_id'] . '_' . date('Y-m-d') . '.pdf';
    
    // Output PDF
    $dompdf->stream($filename, [
        'Attachment' => true,
        'compress' => true
    ]);
    
} catch (Exception $e) {
    die('Error generating invoice: ' . $e->getMessage());
}

function generateInvoiceHTML($order, $items, $total_amount) {
    $order_date = date('F d, Y', strtotime($order['created_at']));
    $invoice_number = 'INV-' . str_pad($order['orders_id'], 6, '0', STR_PAD_LEFT);
    
    $html = '
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        <title>E-Invoice - Order #' . $order['orders_id'] . '</title>
        <style>
            body {
                font-family: Arial, sans-serif;
                margin: 0;
                padding: 20px;
                color: #000;
                background-color: #fff;
            }
            
            .invoice-container {
                max-width: 800px;
                margin: 0 auto;
                background: white;
                padding: 30px;
                border: 1px solid #000;
            }
            
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            
            .company-name {
                font-size: 28px;
                font-weight: bold;
                color: #000;
                margin-bottom: 5px;
            }
            
            .company-tagline {
                font-size: 14px;
                color: #000;
                margin-bottom: 10px;
            }
            
            .company-address {
                font-size: 12px;
                color: #000;
                line-height: 1.4;
            }
            
            .invoice-title {
                font-size: 24px;
                font-weight: bold;
                color: #000;
                margin-bottom: 20px;
            }
            
            .invoice-details {
                display: table;
                width: 100%;
                margin-bottom: 30px;
            }
            
            .invoice-info, .customer-info {
                display: table-cell;
                width: 50%;
                vertical-align: top;
            }
            
            .info-section h3 {
                font-size: 16px;
                color: #000;
                margin-bottom: 10px;
                border-bottom: 1px solid #000;
                padding-bottom: 5px;
            }
            
            .info-section p {
                margin: 5px 0;
                font-size: 14px;
                color: #000;
            }
            
            .items-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            
            .items-table th {
                background-color: #000;
                color: white;
                padding: 12px;
                text-align: left;
                font-weight: bold;
            }
            
            .items-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #000;
            }
            
            .items-table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            
            .text-right {
                text-align: right;
            }
            
            .text-center {
                text-align: center;
            }
            
            .totals-section {
                margin-top: 20px;
                margin-left: auto;
                width: 300px;
            }
            
            .totals-table {
                width: 100%;
                border-collapse: collapse;
            }
            
            .totals-table td {
                padding: 8px 12px;
                border-bottom: 1px solid #000;
            }
            
            .totals-table .label {
                font-weight: bold;
                background-color: #f5f5f5;
            }
            
            .totals-table .total-row {
                background-color: #000;
                color: white;
                font-weight: bold;
                font-size: 16px;
            }
            
            .footer {
                margin-top: 40px;
                text-align: center;
                font-size: 12px;
                color: #000;
                border-top: 1px solid #000;
                padding-top: 20px;
            }
            
            .status-badge {
                display: inline-block;
                padding: 4px 12px;
                border: 1px solid #000;
                font-size: 12px;
                font-weight: bold;
                text-transform: uppercase;
                background-color: #fff;
                color: #000;
                vertical-align: middle;
                margin-top: 5px;
            }
            
            .delivery-info {
                background-color: #f8f9fa;
                padding: 15px;
                border: 1px solid #000;
                margin: 10px 0;
            }
            
            .delivery-info h4 {
                margin: 0 0 10px 0;
                color: #000;
                font-size: 14px;
            }
            
            .delivery-info p {
                margin: 5px 0;
                font-size: 13px;
                color: #000;
            }
        </style>
    </head>
    <body>
        <div class="invoice-container">
            <!-- Header -->
            <div class="header">
                <div class="company-name">MikeMadz</div>
                <div class="company-tagline">Frozen Products Store</div>
                <div class="company-address">
                    BIR Village Block 9 Lot 5 Franchise St.<br>
                    Brgy. Sauyo, Quezon City<br>
                    Philippines
                </div>
            </div>
            
            <!-- Invoice Title -->
            <div class="invoice-title">E-INVOICE</div>
            
            <!-- Invoice Details -->
            <div class="invoice-details">
                <div class="invoice-info">
                    <div class="info-section">
                        <h3>Invoice Details</h3>
                        <p><strong>Invoice No:</strong> ' . $invoice_number . '</p>
                        <p><strong>Order No:</strong> #' . $order['orders_id'] . '</p>
                        <p><strong>Date:</strong> ' . $order_date . '</p>
                        <p><strong>Status:</strong> <span class="status-badge status-' . strtolower(str_replace(' ', '-', $order['status'])) . '">' . $order['status'] . '</span></p>
                    </div>
                </div>
                
                <div class="customer-info">
                    <div class="info-section">
                        <h3>Bill To</h3>
                        <p><strong>' . htmlspecialchars($order['first_name'] . ' ' . $order['last_name']) . '</strong></p>
                        <p>' . htmlspecialchars($order['username']) . '</p>
                        <p>' . htmlspecialchars($order['email']) . '</p>
                        <p>' . htmlspecialchars($order['phone']) . '</p>
                    </div>
                </div>
            </div>
            
            <!-- Delivery Information -->
            <div class="delivery-info">
                <h4>Delivery Information</h4>
                <p><strong>Method:</strong> ' . ucfirst($order['delivery_option']) . '</p>';
                
    if ($order['delivery_option'] === 'delivery' && !empty($order['address_line'])) {
        $html .= '
                <p><strong>Address:</strong> ' . htmlspecialchars($order['address_line']) . '</p>';
        if (!empty($order['address_line2'])) {
            $html .= '<p>' . htmlspecialchars($order['address_line2']) . '</p>';
        }
        $html .= '
                <p>' . htmlspecialchars($order['city']) . ', ' . htmlspecialchars($order['state']) . ' ' . htmlspecialchars($order['postal_code']) . '</p>
                <p>' . htmlspecialchars($order['country']) . '</p>';
    } else {
        $html .= '
                <p><strong>Pickup Location:</strong> BIR Village Block 9 Lot 5 Franchise St., Brgy. Sauyo, Quezon City</p>';
    }
    
    if (!empty($order['plate_number']) || !empty($order['transaction_number'])) {
        $html .= '
                <p><strong>Tracking Info:</strong></p>';
        if (!empty($order['plate_number'])) {
            $html .= '<p>Vehicle: ' . htmlspecialchars($order['plate_number']) . '</p>';
        }
        if (!empty($order['transaction_number'])) {
            $html .= '<p>Transaction: ' . htmlspecialchars($order['transaction_number']) . '</p>';
        }
    }
    
    $html .= '
            </div>
            
            <!-- Items Table -->
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th class="text-center">Quantity</th>
                        <th class="text-right">Unit Price</th>
                        <th class="text-right">Total</th>
                    </tr>
                </thead>
                <tbody>';
    
    foreach ($items as $item) {
        $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['product_name']) . '</td>
                        <td class="text-center">' . $item['quantity'] . '</td>
                        <td class="text-right">PHP ' . number_format($item['unit_price'], 2) . '</td>
                        <td class="text-right">PHP ' . number_format($item['total_price'], 2) . '</td>
                    </tr>';
    }
    
    $html .= '
                </tbody>
            </table>
            
            <!-- Totals -->
            <div class="totals-section">
                <table class="totals-table">
                    <tr class="total-row">
                        <td>Total Amount:</td>
                        <td class="text-right">PHP ' . number_format($total_amount, 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <!-- Payment Information -->
            <div class="delivery-info">
                <h4>Payment Information</h4>';
    
    if (!empty($order['payment_method'])) {
        $html .= '<p><strong>Method:</strong> ' . htmlspecialchars($order['payment_method']) . '</p>';
    }
    if (!empty($order['gcash_transaction_id'])) {
        $html .= '<p><strong>Transaction ID:</strong> ' . htmlspecialchars($order['gcash_transaction_id']) . '</p>';
    }
    
    $html .= '
            </div>
            
            <!-- Footer -->
            <div class="footer">
                <p>This is an electronic invoice generated on ' . date('F d, Y \a\t g:i A') . '</p>
                <p>For any inquiries, please contact us at your convenience.</p>
            </div>
        </div>
    </body>
    </html>';
    
    return $html;
}
?>
