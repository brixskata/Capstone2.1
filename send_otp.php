<?php
session_start();
require_once 'includes/db.php';

header('Content-Type: application/json');
// Ensure errors go to log, not output (keeps JSON clean)
@ini_set('display_errors', '0');
@ini_set('log_errors', '1');

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


        // Best-effort dev logging (avoid breaking JSON if file not writable)
        $log_message = "OTP for {$email}: {$otp} (Expires: {$expires_at})\n";
        $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'otp_log.txt';
        // Suppress any warning if the file/dir is not writable in production
        @file_put_contents($logFile, $log_message, FILE_APPEND | LOCK_EX);
        
        // Prepare email content
        $subject = 'Email Verification - OTP Code - MikeMadz';
        $message = "
        <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;'>
            <h2 style='color: #7F1734;'>Email Verification - MikeMadz</h2>
            <p>Your OTP verification code is:</p>
            <div style='background-color: #f5f5f5; padding: 20px; text-align: center; margin: 20px 0;'>
                <h1 style='color: #7F1734; font-size: 32px; letter-spacing: 5px; margin: 0;'>{$otp}</h1>
            </div>
            <p>This code will expire in 10 minutes.</p>
            <p>If you didn't request this code, please ignore this email.</p>
        </div>";

        // Try Gmail SMTP via PHPMailer if available; else fall back to mail()+log
        $emailSent = false;
        $autoloadPath = __DIR__ . '/vendor/autoload.php';
        if (file_exists($autoloadPath)) {
            require_once $autoloadPath;
            if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
                try {
                    $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                    $mailer->isSMTP();
                    $mailer->Host       = 'smtp.gmail.com';
                    $mailer->SMTPAuth   = true;
                    $mailer->Username   = getenv('SMTP_USERNAME') ?: 'mikemadzstore021@gmail.com';
                    $mailer->Password   = getenv('SMTP_PASSWORD') ?: 'agbz ofsx rdfb omdz';
                    // Use string to support environments without the constant
                    $mailer->SMTPSecure = 'tls';
                    $mailer->Port       = 587;

                    $mailer->setFrom('mikemadzstore021@gmail.com', 'MikeMadz');
                    $mailer->addAddress($email);
                    $mailer->isHTML(true);
                    $mailer->Subject = 'Email Verification - OTP Code';
                    $mailer->Body    = $message;

                    $mailer->send();
                    $emailSent = true;
                } catch (Exception $e) {
                    // fall back below
                }
            }
        }

        if (!$emailSent) {
            // Log OTP to file (dev fallback) - suppress warnings and use absolute path
            $log_message = "OTP for {$email}: {$otp} (Expires: {$expires_at})\n";
            $logFile = __DIR__ . DIRECTORY_SEPARATOR . 'otp_log.txt';
            @file_put_contents($logFile, $log_message, FILE_APPEND | LOCK_EX);

            // Attempt native mail() best-effort
            $headers = "From: MikeMadz <mikemadzstore021@gmail.com>\r\n";
            $headers .= "Reply-To: mikemadzstore021@gmail.com\r\n";
            $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
            $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
            @mail($email, $subject, $message, $headers);
        }

        echo json_encode(['success' => true, 'message' => 'OTP sent successfully']);

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Failed to send OTP: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Invalid request method']);
}
?>