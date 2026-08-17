<?php
/**
 * Send email notification to customer
 * @param string $toEmail Customer email address
 * @param string $subject Email subject
 * @param string $htmlBody HTML email body
 * @return bool True if sent successfully, false otherwise
 */
function sendCustomerEmail($toEmail, $subject, $htmlBody) {
    if (empty($toEmail)) {
        return false;
    }
    
    $emailSent = false;
    $autoloadPath = __DIR__ . '/../vendor/autoload.php';
    
    if (file_exists($autoloadPath)) {
        require_once $autoloadPath;
        if (class_exists('PHPMailer\PHPMailer\PHPMailer')) {
            try {
                $mailer = new PHPMailer\PHPMailer\PHPMailer(true);
                $mailer->isSMTP();
                $mailer->Host       = 'smtp.gmail.com';
                $mailer->SMTPAuth   = true;
                $mailer->Username   = getenv('SMTP_USERNAME') ?: 'mikemadzstore021@gmail.com';
            $mailer->Password   = getenv('SMTP_PASSWORD') ?: 'yxjb kkzb jhzr odyy';
                $mailer->SMTPSecure = 'tls';
                $mailer->Port       = 587;

                $mailer->setFrom('mikemadzstore021@gmail.com', 'MikeMadz');
                $mailer->addAddress($toEmail);
                $mailer->isHTML(true);
                $mailer->Subject = $subject;
                $mailer->Body    = $htmlBody;

                $mailer->send();
                $emailSent = true;
            } catch (Exception $e) {
                error_log("Email sending failed: " . $e->getMessage());
                $emailSent = false;
            }
        }
    }

    // Fallback to native mail() if PHPMailer fails
    if (!$emailSent) {
        $headers = "From: MikeMadz <mikemadzstore021@gmail.com>\r\n";
        $headers .= "Reply-To: mikemadzstore021@gmail.com\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
        $headers .= "X-Mailer: PHP/" . phpversion() . "\r\n";
        @mail($toEmail, $subject, $htmlBody, $headers);
    }
    
    return $emailSent;
}
