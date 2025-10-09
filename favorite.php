<?php
session_start();
include 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to use favorites']);
    exit;
}

if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    echo json_encode(['success' => false, 'message' => 'Invalid CSRF token']);
        exit;
    }

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$action = isset($_POST['action']) ? $_POST['action'] : 'toggle';

if ($product_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product ID']);
    exit;
}

try {
    $user_id = $_SESSION['user_id'];
    
    if ($action === 'check') {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $is_favorite = $stmt->fetchColumn() > 0;
        
        echo json_encode(['success' => true, 'is_favorite' => $is_favorite]);
    } else {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM favorites WHERE user_id = ? AND product_id = ?");
        $stmt->execute([$user_id, $product_id]);
        $is_favorite = $stmt->fetchColumn() > 0;
        
        if ($is_favorite) {
            $stmt = $pdo->prepare("DELETE FROM favorites WHERE user_id = ? AND product_id = ?");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['success' => true, 'is_favorite' => false, 'message' => 'Removed from favorites']);
        } else {
            $stmt = $pdo->prepare("INSERT INTO favorites (user_id, product_id) VALUES (?, ?)");
            $stmt->execute([$user_id, $product_id]);
            echo json_encode(['success' => true, 'is_favorite' => true, 'message' => 'Added to favorites']);
        }
    }
} catch (PDOException $e) {
    echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
}
?>