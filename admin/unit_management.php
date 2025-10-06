<?php
include '../includes/db.php';
include_once '../includes/log_history.php';
include_once '../includes/permissions.php';
session_start();

// Ensure user is logged in and has admin role
if (!isset($_SESSION['username']) || !in_array($_SESSION['role'], ['admin', 'super_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

header('Content-Type: application/json');

try {
    $action = $_REQUEST['action'] ?? '';

    switch ($action) {
        case 'get_conversions':
            $product_id = (int)($_GET['product_id'] ?? 0);
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    puc.conversion_id,
                    puc.product_id,
                    puc.uom_id,
                    puc.conversion_rate,
                    u.name AS from_unit,
                    u2.name AS to_unit
                FROM product_uom_conversions puc
                JOIN uom u ON puc.uom_id = u.uom_id
                JOIN uom u2 ON puc.uom_id = u2.uom_id
                WHERE puc.product_id = ?
                ORDER BY u.name
            ");
            $stmt->execute([$product_id]);
            $conversions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Fix the query - we need to get the base unit and conversion unit
            $stmt = $pdo->prepare("
                SELECT 
                    puc.conversion_id,
                    puc.product_id,
                    puc.uom_id,
                    puc.conversion_rate,
                    u.name AS to_unit,
                    (SELECT u2.name FROM uom u2 JOIN products p ON p.uom_id = u2.uom_id WHERE p.product_id = ?) AS from_unit
                FROM product_uom_conversions puc
                JOIN uom u ON puc.uom_id = u.uom_id
                WHERE puc.product_id = ?
                ORDER BY u.name
            ");
            $stmt->execute([$product_id, $product_id]);
            $conversions = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($conversions);
            break;

        case 'get_boxes':
            $product_id = (int)($_GET['product_id'] ?? 0);
            if ($product_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
                exit;
            }

            $stmt = $pdo->prepare("
                SELECT 
                    box_id,
                    product_id,
                    batch_id,
                    weight,
                    is_sold,
                    created_at
                FROM product_boxes
                WHERE product_id = ?
                ORDER BY weight
            ");
            $stmt->execute([$product_id]);
            $boxes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($boxes);
            break;

        case 'add_conversion':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $from_uom = (int)($_POST['from_uom'] ?? 0);
            $to_uom = (int)($_POST['to_uom'] ?? 0);
            $rate = (float)($_POST['rate'] ?? 0);

            if ($product_id <= 0 || $to_uom <= 0 || $rate <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            // Check if conversion already exists
            $stmt = $pdo->prepare("SELECT conversion_id FROM product_uom_conversions WHERE product_id = ? AND uom_id = ?");
            $stmt->execute([$product_id, $to_uom]);
            if ($stmt->fetch()) {
                echo json_encode(['success' => false, 'message' => 'Conversion already exists for this unit']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO product_uom_conversions (product_id, uom_id, conversion_rate) VALUES (?, ?, ?)");
            $stmt->execute([$product_id, $to_uom, $rate]);

            logHistory($pdo, 'Added Unit Conversion', "Product ID: $product_id, UOM ID: $to_uom, Rate: $rate", $_SESSION['username']);

            echo json_encode(['success' => true, 'message' => 'Conversion added successfully']);
            break;

        case 'add_box':
            $product_id = (int)($_POST['product_id'] ?? 0);
            $batch_id = trim($_POST['batch_id'] ?? '');
            $weight = (float)($_POST['weight'] ?? 0);

            if ($product_id <= 0 || empty($batch_id) || $weight <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
                exit;
            }

            $stmt = $pdo->prepare("INSERT INTO product_boxes (product_id, batch_id, weight, is_sold) VALUES (?, ?, ?, 0)");
            $stmt->execute([$product_id, $batch_id, $weight]);

            logHistory($pdo, 'Added Variable Box', "Product ID: $product_id, Batch: $batch_id, Weight: $weight kg", $_SESSION['username']);

            echo json_encode(['success' => true, 'message' => 'Box added successfully']);
            break;

        case 'delete_conversion':
            $conversion_id = (int)($_POST['conversion_id'] ?? 0);
            if ($conversion_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid conversion ID']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM product_uom_conversions WHERE conversion_id = ?");
            $stmt->execute([$conversion_id]);

            logHistory($pdo, 'Deleted Unit Conversion', "Conversion ID: $conversion_id", $_SESSION['username']);

            echo json_encode(['success' => true, 'message' => 'Conversion deleted successfully']);
            break;

        case 'delete_box':
            $box_id = (int)($_POST['box_id'] ?? 0);
            if ($box_id <= 0) {
                echo json_encode(['success' => false, 'message' => 'Invalid box ID']);
                exit;
            }

            // Check if box is sold
            $stmt = $pdo->prepare("SELECT is_sold FROM product_boxes WHERE box_id = ?");
            $stmt->execute([$box_id]);
            $box = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($box && $box['is_sold']) {
                echo json_encode(['success' => false, 'message' => 'Cannot delete sold box']);
                exit;
            }

            $stmt = $pdo->prepare("DELETE FROM product_boxes WHERE box_id = ?");
            $stmt->execute([$box_id]);

            logHistory($pdo, 'Deleted Variable Box', "Box ID: $box_id", $_SESSION['username']);

            echo json_encode(['success' => true, 'message' => 'Box deleted successfully']);
            break;

        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }

} catch (PDOException $e) {
    error_log("Unit Management Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("Unit Management Error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
}
?>

