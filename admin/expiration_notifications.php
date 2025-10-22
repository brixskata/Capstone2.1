<?php
/**
 * Expiration Notifications Helper
 * Checks for expiring batches and creates notifications
 */

include '../includes/db.php';
include_once '../includes/batch_manager.php';

class ExpirationNotifications {
    private $pdo;
    private $batchManager;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
        $this->batchManager = new BatchManager($pdo);
    }
    
    /**
     * Get expiring batches notifications
     */
    public function getExpiringNotifications() {
        $notifications = [];
        
        // Check for batches expiring in 7 days
        $expiringBatches = $this->batchManager->getExpiringBatches(7);
        
        foreach ($expiringBatches as $batch) {
            $daysUntilExpiry = $batch['days_until_expiry'];
            
            if ($daysUntilExpiry <= 7 && $daysUntilExpiry > 0) {
                $severity = 'warning';
                $icon = 'fa-exclamation-triangle';
                
                if ($daysUntilExpiry <= 3) {
                    $severity = 'danger';
                    $icon = 'fa-exclamation-circle';
                }
                
                $notifications[] = [
                    'id' => 'expiring_' . $batch['batch_id'],
                    'type' => 'expiring_batch',
                    'title' => 'Batch Expiring Soon',
                    'message' => "Batch {$batch['batch_number']} ({$batch['product_name']}) expires in {$daysUntilExpiry} days",
                    'severity' => $severity,
                    'icon' => $icon,
                    'batch_id' => $batch['batch_id'],
                    'product_name' => $batch['product_name'],
                    'batch_number' => $batch['batch_number'],
                    'days_until_expiry' => $daysUntilExpiry,
                    'expiration_date' => $batch['expiration_date'],
                    'created_at' => date('Y-m-d H:i:s')
                ];
            }
        }
        
        // Check for expired batches that haven't been processed
        $expiredBatches = $this->getExpiredBatches();
        
        foreach ($expiredBatches as $batch) {
            $daysExpired = abs($batch['days_until_expiry']);
            
            $notifications[] = [
                'id' => 'expired_' . $batch['batch_id'],
                'type' => 'expired_batch',
                'title' => 'Batch Expired',
                'message' => "Batch {$batch['batch_number']} ({$batch['product_name']}) expired {$daysExpired} days ago",
                'severity' => 'danger',
                'icon' => 'fa-times-circle',
                'batch_id' => $batch['batch_id'],
                'product_name' => $batch['product_name'],
                'batch_number' => $batch['batch_number'],
                'days_until_expiry' => $batch['days_until_expiry'],
                'expiration_date' => $batch['expiration_date'],
                'created_at' => date('Y-m-d H:i:s')
            ];
        }
        
        return $notifications;
    }
    
    /**
     * Get expired batches that haven't been processed
     */
    private function getExpiredBatches() {
        $stmt = $this->pdo->prepare("
            SELECT 
                pb.batch_id,
                pb.batch_number,
                pb.quantity_remaining,
                pb.expiration_date,
                p.product_name,
                DATEDIFF(pb.expiration_date, CURDATE()) as days_until_expiry
            FROM product_batches pb
            JOIN products p ON pb.product_id = p.product_id
            WHERE pb.expiration_date IS NOT NULL 
            AND pb.expiration_date < CURDATE()
            AND pb.quantity_remaining > 0
            AND pb.is_active = 1
            AND pb.is_processed_expired = 0
            ORDER BY pb.expiration_date ASC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Get notification count for navbar
     */
    public function getNotificationCount() {
        $notifications = $this->getExpiringNotifications();
        return count($notifications);
    }
    
    /**
     * Get notifications grouped by severity
     */
    public function getNotificationsBySeverity() {
        $notifications = $this->getExpiringNotifications();
        $grouped = [
            'danger' => [],
            'warning' => [],
            'info' => []
        ];
        
        foreach ($notifications as $notification) {
            $grouped[$notification['severity']][] = $notification;
        }
        
        return $grouped;
    }
}

// Handle AJAX request for notifications
if (isset($_GET['action']) && $_GET['action'] === 'get_notifications') {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $notifications = new ExpirationNotifications($pdo);
    $result = $notifications->getNotificationsBySeverity();
    
    header('Content-Type: application/json');
    echo json_encode($result);
    exit;
}

// Handle AJAX request for notification count
if (isset($_GET['action']) && $_GET['action'] === 'get_count') {
    session_start();
    
    if (!isset($_SESSION['user_id'])) {
        http_response_code(403);
        echo json_encode(['error' => 'Unauthorized']);
        exit;
    }
    
    $notifications = new ExpirationNotifications($pdo);
    $count = $notifications->getNotificationCount();
    
    header('Content-Type: application/json');
    echo json_encode(['count' => $count]);
    exit;
}
?>