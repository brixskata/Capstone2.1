<?php
session_start();
require_once 'vendor/autoload.php';
require_once 'includes/db.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var($_POST['email'], FILTER_VALIDATE_EMAIL);

    if (!$email) {
        echo json_encode(['success' => false, 'message' => 'Invalid email address']);
        exit;
    }

    // Generate 6-digit OTP
    $otp = sprintf("%06d", mt_rand(0, 999999));
    $expires_at = date('Y-m-d H:i:s', strtotime('+10 minutes'));

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
        
        // Delete any existing OTP for this user first
        $stmt = $pdo->prepare("DELETE FROM email_verification WHERE user_id = ?");
        $stmt->execute([$user_id]);

        // Store new OTP in database
        $stmt = $pdo->prepare("INSERT INTO email_verification (user_id, otp, expires_at, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $otp, $expires_at]);

        // Send OTP via email
        $mail = new PHPMailer(true);

        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'brixquils16@gmail.com'; // Replace with your actual Gmail address
        $mail->Password   = 'hhcw drjs tdcq uybd';    // Replace with your actual app password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;
        $mail->SMTPDebug  = 0; // Disable debugging for production

        // Recipients
        $mail->setFrom('brixquils16@gmail.com', 'MikeMadz');
        $mail->addAddress($email);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Email Verification - OTP Code';
        $mail->Body    = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #7F1734;'>Email Verification - MikeMadz</h2>
            <p>Your OTP verification code is:</p>
            <div style='background-color: #f5f5f5; padding: 20px; text-align: center; margin: 20px 0;'>
                <h1 style='color: #7F1734; font-size: 32px; letter-spacing: 5px; margin: 0;'>{$otp}</h1>
            </div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
        </div>";

        $mail->send();

        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>