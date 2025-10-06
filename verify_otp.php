<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);
    $otp = trim($_POST['otp']);
    
    if (!$email || !$otp) {
        echo json_encode(['success' => false, 'message' => 'Email and OTP are required']);
        exit;
    }
    
    try {
        // First, get the user_id from user_info table
        $stmt = $pdo->prepare("SELECT user_id FROM user_info WHERE email = ?");
        $stmt->execute([$email]);
        $user_info = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user_info) {
            echo json_encode(['success' => false, 'message' => 'Email not found in our system']);
            exit;
        }
        
        $user_id = $user_info['user_id'];
        
        // Check if there's any OTP record for this user
        $stmt = $pdo->prepare("SELECT *, NOW() as `current_time` FROM email_verification WHERE user_id = ? ORDER BY created_at DESC LIMIT 1");
        $stmt->execute([$user_id]);
        $record = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$record) {
            echo json_encode(['success' => false, 'message' => 'No OTP found for this email. Please request a new OTP.']);
            exit;
        }
        
        // Check if OTP matches
        if ($record['otp'] !== $otp) {
            echo json_encode(['success' => false, 'message' => 'Invalid OTP code. Please check and try again.']);
            exit;
        }
        
        // Check if OTP has expired
        if (strtotime($record['expires_at']) <= time()) {
            echo json_encode(['success' => false, 'message' => 'OTP has expired. Please request a new one.']);
            exit;
        }
        
        // Check if already verified
        if ($record['verified'] == 1) {
            echo json_encode(['success' => false, 'message' => 'This OTP has already been used. Please request a new one.']);
            exit;
        }
        
        // OTP is valid - mark as verified
        $stmt = $pdo->prepare("UPDATE email_verification SET verified = 1 WHERE user_id = ? AND otp = ?");
        $stmt->execute([$user_id, $otp]);

        // Update the users table to mark email as verified
        $stmt = $pdo->prepare("UPDATE users SET email_verified = 1 WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Store verified email in session
        $_SESSION['verified_email'] = $email;

        echo json_encode(['success' => true, 'message' => 'Email verified successfully']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Verification failed: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>