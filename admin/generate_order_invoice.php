<?php
require_once __DIR__ . '/../vendor/autoload.php';
use Dompdf\Dompdf;
use Dompdf\Options;

session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    die('Unauthorized');
}

// Get order data from POST
$orderId = $_POST['order_id'] ?? '';
$username = $_POST['username'] ?? '';
$email = $_POST['email'] ?? '';
$phone = $_POST['phone'] ?? '';
$address = $_POST['address'] ?? '';
$items = $_POST['items'] ?? '';
$totalAmount = floatval($_POST['total_amount'] ?? 0);
$paymentMethod = $_POST['payment_method'] ?? 'Cash on Delivery';
$gcashTransactionId = $_POST['gcash_transaction_id'] ?? '';
$deliveryOption = $_POST['delivery_option'] ?? '';
$orderDate = $_POST['order_date'] ?? '';
$status = $_POST['status'] ?? '';
$applicationName = $_POST['application_name'] ?? '';
$riderName = $_POST['rider_name'] ?? '';
$plateNumber = $_POST['plate_number'] ?? '';
$transactionNumber = $_POST['transaction_number'] ?? '';

if (empty($orderId)) {
    http_response_code(400);
    die('Missing order ID');
}

// Configure Dompdf options
$options = new Options();
$options->set('defaultFont', 'DejaVu Sans');
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

// Create HTML content
$html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: DejaVu Sans, Arial, sans-serif; padding: 20px; margin: 0; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #000; padding-bottom: 15px; }
        .invoice-title { font-size: 28px; font-weight: bold; margin: 10px 0; }
        .invoice-subtitle { font-size: 12px; color: #666; }
        .section { margin: 20px 0; }
        .section-title { font-size: 14px; font-weight: bold; border-bottom: 1px solid #ccc; padding-bottom: 5px; margin-bottom: 10px; }
        .info-row { margin: 5px 0; font-size: 11px; }
        .info-label { font-weight: bold; display: inline-block; width: 120px; }
        .items-table { width: 100%; border-collapse: collapse; margin: 10px 0; font-size: 11px; }
        .items-table th, .items-table td { border: 1px solid #000; padding: 8px; text-align: left; }
        .items-table th { background-color: #f0f0f0; font-weight: bold; }
        .total-row { font-weight: bold; font-size: 13px; text-align: right; margin-top: 10px; }
        .footer { margin-top: 40px; padding-top: 20px; border-top: 2px solid #000; text-align: center; font-size: 10px; color: #666; }
    </style>
</head>
<body>
    <div class="header">
        <div class="invoice-title">INVOICE</div>
        <div class="invoice-subtitle">Order #' . htmlspecialchars($orderId) . '</div>
        <div class="info-row">Date: ' . date('F d, Y h:i A', strtotime($orderDate)) . '</div>
    </div>

    <div class="section">
        <div class="section-title">ORDER INFORMATION</div>
        <div class="info-row"><span class="info-label">Order ID:</span> #' . htmlspecialchars($orderId) . '</div>
        <div class="info-row"><span class="info-label">Status:</span> ' . htmlspecialchars($status) . '</div>
        <div class="info-row"><span class="info-label">Delivery:</span> ' . htmlspecialchars(ucfirst($deliveryOption)) . '</div>';

if ($applicationName) {
    $html .= '<div class="info-row"><span class="info-label">Application:</span> ' . htmlspecialchars($applicationName) . '</div>';
    if ($riderName) $html .= '<div class="info-row"><span class="info-label">Rider:</span> ' . htmlspecialchars($riderName) . '</div>';
    if ($_POST['rider_contact_number'] ?? '') $html .= '<div class="info-row"><span class="info-label">Contact:</span> ' . htmlspecialchars($_POST['rider_contact_number']) . '</div>';
    if ($plateNumber) $html .= '<div class="info-row"><span class="info-label">Vehicle:</span> ' . htmlspecialchars($plateNumber) . '</div>';
    if ($transactionNumber) $html .= '<div class="info-row"><span class="info-label">Transaction #:</span> ' . htmlspecialchars($transactionNumber) . '</div>';
}

$html .= '
        <div class="info-row"><span class="info-label">Total Amount:</span> ₱' . number_format($totalAmount, 2) . '</div>
    </div>

    <div class="section">
        <div class="section-title">CUSTOMER INFORMATION</div>
        <div class="info-row"><span class="info-label">Customer:</span> ' . htmlspecialchars($username) . '</div>
        <div class="info-row"><span class="info-label">Email:</span> ' . htmlspecialchars($email) . '</div>';
if ($phone) {
    $html .= '<div class="info-row"><span class="info-label">Phone:</span> ' . htmlspecialchars($phone) . '</div>';
}
$html .= '
        <div class="info-row"><span class="info-label">Address:</span> ' . htmlspecialchars($address) . '</div>
    </div>

    <div class="section">
        <div class="section-title">ORDER ITEMS</div>
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 60%;">Item Description</th>
                    <th style="width: 20%;">Quantity</th>
                </tr>
            </thead>
            <tbody>';

// Parse items (format: "Product Name (quantity)")
$itemsArray = explode(', ', $items);
foreach ($itemsArray as $item) {
    $parts = explode(' (', $item);
    $productName = trim($parts[0]);
    $quantity = isset($parts[1]) ? rtrim(trim($parts[1]), ')') : '1';
    
    $html .= '
                <tr>
                    <td>' . htmlspecialchars($productName) . '</td>
                    <td>' . htmlspecialchars($quantity) . '</td>
                </tr>';
}

$html .= '
            </tbody>
        </table>
        
        <div class="total-row">Total Amount: ₱' . number_format($totalAmount, 2) . '</div>
    </div>

    <div class="footer">
        This is a computer-generated invoice. No signature required.<br>
        Thank you for your purchase!
    </div>
</body>
</html>';

// Load HTML and render PDF
$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// Output PDF
$dompdf->stream('Invoice_Order_' . $orderId . '.pdf', [
    'Attachment' => 1
]);
?>

