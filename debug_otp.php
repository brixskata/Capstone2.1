
<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

if (isset($_GET['email'])) {
    $email = filter_var($_GET['email'], FILTER_VALIDATE_EMAIL);
    
    if ($email) {
        try {
            $stmt = $pdo->prepare("SELECT * FROM email_verification WHERE email = ? ORDER BY created_at DESC LIMIT 1");
            $stmt->execute([$email]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($record) {
                $current_time = date('Y-m-d H:i:s');
                echo json_encode([
                    'success' => true,
                    'data' => [
                        'otp' => $record['otp'],
                        'expires_at' => $record['expires_at'],
                        'current_time' => $current_time,
                        'verified' => $record['verified'],
                        'created_at' => $record['created_at'],
                        'is_expired' => (strtotime($record['expires_at']) <= time()) ? 'Yes' : 'No'
                    ]
                ]);
            } else {
                echo json_encode(['success' => false, 'message' => 'No OTP record found']);
            }
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid email']);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Email parameter required']);
}
?>
