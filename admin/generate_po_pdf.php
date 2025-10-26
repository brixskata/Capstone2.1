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

// Get PO number from URL
$po_number = $_GET['po_number'] ?? null;

if (!$po_number) {
    die('Purchase Order number is required');
}

try {
    // Query database for PO details
    $stmt = $pdo->prepare("
        SELECT 
            r.*,
            p.product_name,
            b.name as brand_name,
            u.name as uom_name,
            s.name as supplier_name,
            COALESCE(r.cost_per_unit, pp.cost_price, 0) as unit_cost,
            COALESCE(r.total_cost, r.quantity_added * COALESCE(r.cost_per_unit, pp.cost_price, 0), 0) as total_cost,
            CASE 
                WHEN r.status_id = 1 THEN 'Pending'
                WHEN r.status_id = 2 THEN 'Received'
                WHEN r.status_id = 3 THEN 'Cancelled'
                ELSE 'Unknown'
            END as status_name
        FROM restocking r
        LEFT JOIN products p ON r.product_id = p.product_id
        LEFT JOIN brands b ON r.brand_id = b.id
        LEFT JOIN uom u ON p.uom_id = u.uom_id
        LEFT JOIN suppliers s ON r.supplier_id = s.supplier_id
        LEFT JOIN product_pricing pp ON r.product_id = pp.product_id
        WHERE r.po_number = ?
        ORDER BY p.product_name
    ");
    $stmt->execute([$po_number]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($items)) {
        die('Purchase Order not found');
    }
    
    // Get supplier name from first item
    $supplier_name = $items[0]['supplier_name'] ?? 'Unknown Supplier';
    $order_date = $items[0]['restock_date'] ?? date('Y-m-d');
    
    // Generate HTML content
    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <style>
            body {
                font-family: DejaVu Sans, Arial, sans-serif;
                margin: 0;
                padding: 20px;
            }
            .header {
                text-align: center;
                margin-bottom: 30px;
                border-bottom: 2px solid #000;
                padding-bottom: 20px;
            }
            .header h1 {
                font-size: 28px;
                font-weight: bold;
                margin: 0;
                color: #000;
            }
            .header h2 {
                font-size: 18px;
                color: #666;
                margin: 5px 0;
            }
            .info-section {
                margin-bottom: 20px;
            }
            .info-section p {
                margin: 3px 0;
                font-size: 12px;
            }
            .info-label {
                font-weight: bold;
                display: inline-block;
                width: 100px;
            }
            table {
                width: 100%;
                border-collapse: collapse;
                margin: 20px 0;
                font-size: 11px;
            }
            table th {
                background-color: #f0f0f0;
                color: #000;
                padding: 10px 8px;
                text-align: left;
                border: 1px solid #000;
                font-weight: bold;
            }
            table td {
                padding: 8px;
                border: 1px solid #000;
            }
            table tr:nth-child(even) {
                background-color: #f9f9f9;
            }
            .total-row {
                font-weight: bold;
                font-size: 14px;
                background-color: #f0f0f0;
            }
            .grand-total {
                text-align: right;
                font-weight: bold;
                font-size: 16px;
                margin-top: 20px;
                padding: 15px;
                background-color: #f0f0f0;
                border: 1px solid #000;
            }
            .status {
                padding: 4px 8px;
                border-radius: 3px;
                font-size: 10px;
            }
            .status-pending {
                background-color: #ffc107;
                color: #000;
            }
            .status-received {
                background-color: #198754;
                color: #fff;
            }
            .status-cancelled {
                background-color: #dc3545;
                color: #fff;
            }
            .footer {
                margin-top: 40px;
                padding-top: 20px;
                border-top: 1px solid #000;
                text-align: center;
                font-size: 10px;
                color: #666;
            }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>PURCHASE ORDER</h1>
            <h2><?= htmlspecialchars($po_number) ?></h2>
        </div>
        
        <div class="info-section">
            <p><span class="info-label">Supplier:</span> <?= htmlspecialchars($supplier_name) ?></p>
            <p><span class="info-label">Date:</span> <?= date('F d, Y', strtotime($order_date)) ?></p>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Product</th>
                    <th>Brand</th>
                    <th>Quantity</th>
                    <th>Unit Cost</th>
                    <th>Total</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $grand_total = 0;
                foreach ($items as $item): 
                    $grand_total += floatval($item['total_cost']);
                    $status_class = '';
                    switch (strtolower($item['status_name'])) {
                        case 'pending':
                            $status_class = 'status-pending';
                            break;
                        case 'received':
                            $status_class = 'status-received';
                            break;
                        case 'cancelled':
                            $status_class = 'status-cancelled';
                            break;
                    }
                ?>
                <tr>
                    <td><?= htmlspecialchars($item['product_name']) ?></td>
                    <td><?= htmlspecialchars($item['brand_name'] ?? 'No Brand') ?></td>
                    <td><?= number_format($item['quantity_added'], 1) ?> <?= htmlspecialchars($item['uom_name'] ?? '') ?></td>
                    <td>₱<?= number_format($item['unit_cost'], 2) ?></td>
                    <td>₱<?= number_format($item['total_cost'], 2) ?></td>
                    <td><span class="status <?= $status_class ?>"><?= htmlspecialchars($item['status_name']) ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="grand-total">
            <strong>Grand Total: ₱<?= number_format($grand_total, 2) ?></strong>
        </div>
        
        <div class="footer">
            <p>This is a system-generated purchase order document.</p>
            <p>Generated on <?= date('F d, Y \a\t g:i A') ?></p>
        </div>
    </body>
    </html>
    <?php
    
    $html = ob_get_clean();
    
    // Configure DomPDF
    $options = new Options();
    $options->set('isHtml5ParserEnabled', true);
    $options->set('isPhpEnabled', true);
    $options->set('isRemoteEnabled', true);
    $options->set('defaultFont', 'DejaVu Sans');
    $options->set('defaultPaperSize', 'A4');
    $options->set('defaultPaperOrientation', 'portrait');
    
    // Generate PDF
    $dompdf = new Dompdf($options);
    $dompdf->loadHtml($html);
    $dompdf->setPaper('A4', 'portrait');
    $dompdf->render();
    
    // Generate filename
    $filename = 'PO_' . $po_number . '_' . date('Y-m-d') . '.pdf';
    
    // Output PDF
    $dompdf->stream($filename, [
        'Attachment' => true,
        'compress' => true
    ]);
    
} catch (Exception $e) {
    die('Error generating Purchase Order PDF: ' . $e->getMessage());
}
?>

