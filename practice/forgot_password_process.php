<?php
session_start();
require __DIR__ . "/config/dbconfig_password.php";

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'C:/xampp/htdocs/FinTrack-Personal-Expense-Tracker/vendor/autoload.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email'] ?? '');
    
    // Validate email
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header("Location: forgot_password.php?error=invalid");
        exit;
    }
    
    // Check if email exists
    $stmt = $conn->prepare("SELECT id, fullname, email FROM users WHERE email = ? LIMIT 1");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        // Don't reveal if email exists or not (security practice)
        header("Location: forgot_password.php?success=1");
        exit;
    }
    
    $user = $result->fetch_assoc();
    $user_id = $user['id'];
    $fullname = $user['fullname'];
    
    // Generate unique reset token
    $token = bin2hex(random_bytes(32));
    $token_hash = hash('sha256', $token);
    $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));
    
    // Store token in database
    $stmt = $conn->prepare("INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE token_hash = ?, expires_at = ?");
    $stmt->bind_param("issss", $user_id, $token_hash, $expires_at, $token_hash, $expires_at);
    $stmt->execute();
    
    // Send email with PHPMailer
    $mail = new PHPMailer(true);
    
    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com'; // Change to your SMTP server
        $mail->SMTPAuth = true;
        $mail->Username = 'randallbenedict14@gmail.com'; // Change to your email
        $mail->Password = 'afphkhjweylquzce'; // Use app-specific password
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;
        
        // Recipients
        $mail->setFrom('randallbenedict14@gmail.com', 'FinTrack');
        $mail->addAddress($email, $fullname);
        
        // Content
        $reset_link = "http://localhost/FinTrack-Personal-Expense-Tracker/practice/reset_password.php?token=" . $token;
        
        $mail->isHTML(true);
        $mail->Subject = 'Password Reset Request - FinTrack';
        $mail->Body = "
        <!DOCTYPE html>
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 10px 10px 0 0; }
                .content { background: #f9f9f9; padding: 30px; border-radius: 0 0 10px 10px; }
                .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: bold; }
                .footer { text-align: center; margin-top: 20px; color: #666; font-size: 0.9em; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>🔐 Password Reset Request</h1>
                </div>
                <div class='content'>
                    <p>Hi <strong>{$fullname}</strong>,</p>
                    <p>We received a request to reset your password for your FinTrack account. Click the button below to reset your password:</p>
                    <div style='text-align: center;'>
                        <a href='{$reset_link}' class='button'>Reset Password</a>
                    </div>
                    <p>Or copy and paste this link into your browser:</p>
                    <p style='background: #fff; padding: 10px; border-radius: 5px; word-break: break-all;'>{$reset_link}</p>
                    <p><strong>⏰ This link will expire in 1 hour.</strong></p>
                    <p>If you didn't request this password reset, please ignore this email or contact support if you have concerns.</p>
                </div>
                <div class='footer'>
                    <p>© 2025 FinTrack - Personal Expense Tracker</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $mail->AltBody = "Hi {$fullname},\n\nWe received a request to reset your password. Click this link to reset: {$reset_link}\n\nThis link expires in 1 hour.\n\nIf you didn't request this, please ignore this email.";
        
        $mail->send();
        header("Location: forgot_password.php?success=1");
    } catch (Exception $e) {
        error_log("Email sending failed: " . $mail->ErrorInfo);
        header("Location: forgot_password.php?error=email_failed");
    }
    
    $stmt->close();
    $conn->close();
} else {
    header("Location: forgot_password.php");
    exit;
}
?>