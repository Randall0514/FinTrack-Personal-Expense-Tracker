<?php
session_start();
include 'dist/database/config/db.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// For PHPMailer
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Check if PHPMailer is available
if (file_exists('vendor/autoload.php')) {
    require 'vendor/autoload.php';
} else {
    die('PHPMailer not installed. Run: composer require phpmailer/phpmailer');
}

$message = '';
$messageType = '';
$debugInfo = [];

if(isset($_POST['submit'])) {
    $email = trim($_POST['email']);
    
    if(empty($email)) {
        $message = 'Please enter your email address.';
        $messageType = 'error';
    } elseif(!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $message = 'Please enter a valid email address.';
        $messageType = 'error';
    } else {
        // Check if email exists
        $stmt = $conn->prepare("SELECT id, fullname FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if($result->num_rows > 0) {
            $user = $result->fetch_assoc();
            $debugInfo[] = "User found: " . $user['fullname'];
            
            // Generate unique token
            $token = bin2hex(random_bytes(32));
            $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
            $debugInfo[] = "Token generated: " . substr($token, 0, 10) . "...";
            
            // Store token in database
            $updateStmt = $conn->prepare("UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE email = ?");
            $updateStmt->bind_param("sss", $token, $expiry, $email);
            
            if($updateStmt->execute()) {
                $debugInfo[] = "Token saved to database";
                
                // Create reset link
                $resetLink = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/reset_password.php?token=" . $token;
                $debugInfo[] = "Reset link: " . $resetLink;
                
                try {
                    $mail = new PHPMailer(true);
                    
                    // CONFIGURE THESE SETTINGS
                    $smtpHost = 'smtp.gmail.com';           // Your SMTP host
                    $smtpUsername = 'vrcursedfaker@gmail.com'; // Your email
                    $smtpPassword = 'hvdijrgacmmfhlsd';    // Your app password (16 characters)
                    $smtpPort = 587;
                    $fromEmail = 'vrcursedfaker@gmail.com';
                    $fromName = 'FinTrack';
                    
                    // Server settings
                    $mail->SMTPDebug = 2; // Enable verbose debug output
                    $mail->isSMTP();
                    $mail->Host = $smtpHost;
                    $mail->SMTPAuth = true;
                    $mail->Username = $smtpUsername;
                    $mail->Password = $smtpPassword;
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = $smtpPort;
                    
                    // Recipients
                    $mail->setFrom($fromEmail, $fromName);
                    $mail->addAddress($email, $user['fullname']);
                    
                    // Content
                    $mail->isHTML(true);
                    $mail->Subject = 'Password Reset Request - FinTrack';
                    $mail->Body = "
                        <html>
                        <body style='font-family: Arial, sans-serif; padding: 20px;'>
                            <div style='max-width: 600px; margin: 0 auto; background: #f9f9f9; padding: 30px; border-radius: 10px;'>
                                <h2 style='color: #667eea;'>Password Reset Request</h2>
                                <p>Hello <strong>{$user['fullname']}</strong>,</p>
                                <p>We received a request to reset your password for your FinTrack account.</p>
                                <p style='margin: 30px 0;'>
                                    <a href='{$resetLink}' style='background: #667eea; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px; display: inline-block;'>Reset Password</a>
                                </p>
                                <p>Or copy and paste this link into your browser:</p>
                                <p style='background: #fff; padding: 10px; border: 1px solid #ddd; word-break: break-all;'>{$resetLink}</p>
                                <p style='color: #666; font-size: 14px;'><strong>This link will expire in 1 hour.</strong></p>
                                <p style='color: #666; font-size: 14px;'>If you didn't request this password reset, please ignore this email. Your password will remain unchanged.</p>
                                <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                                <p style='color: #999; font-size: 12px;'>Best regards,<br>FinTrack Team</p>
                            </div>
                        </body>
                        </html>
                    ";
                    
                    $mail->AltBody = "Password Reset Request\n\nHello {$user['fullname']},\n\nClick this link to reset your password:\n{$resetLink}\n\nThis link will expire in 1 hour.\n\nBest regards,\nFinTrack Team";
                    
                    $debugInfo[] = "Attempting to send email...";
                    $mail->send();
                    $debugInfo[] = "Email sent successfully!";
                    
                    $message = 'Password reset link has been sent to your email address. Please check your inbox and spam folder.';
                    $messageType = 'success';
                    
                } catch (Exception $e) {
                    $debugInfo[] = "Email Error: " . $mail->ErrorInfo;
                    $message = "Failed to send email. Error: {$mail->ErrorInfo}";
                    $messageType = 'error';
                }
            } else {
                $debugInfo[] = "Database update failed";
                $message = 'Database error. Please try again.';
                $messageType = 'error';
            }
            
            $updateStmt->close();
        } else {
            // For security, show same message even if email doesn't exist
            $debugInfo[] = "Email not found in database";
            $message = 'If that email address exists in our system, we have sent a password reset link to it.';
            $messageType = 'success';
        }
        
        $stmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>FinTrack - Forgot Password</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow-x: hidden;
            font-weight: 700;
            padding: 20px;
        }

        .forgot-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
        }

        body::before {
            content: '';
            position: absolute;
            width: 500px;
            height: 500px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            top: -200px;
            right: -200px;
            animation: float 6s ease-in-out infinite;
        }

        body::after {
            content: '';
            position: absolute;
            width: 300px;
            height: 300px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 50%;
            bottom: -100px;
            left: -100px;
            animation: float 8s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-20px); }
        }

        .forgot-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.5s ease-out;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }

        .logo {
            font-size: 2.5rem;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 10px;
        }

        .logo-subtitle {
            color: #666;
            font-size: 1rem;
            font-weight: 600;
        }

        .icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            margin: 0 auto 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 30px;
        }

        .welcome-text h2 {
            font-size: 1.8rem;
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .welcome-text p {
            color: #666;
            font-size: 0.95rem;
            font-weight: 600;
            line-height: 1.6;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-weight: 600;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 2px solid #10b981;
        }

        .alert-error {
            background: #fee2e2;
            color: #991b1b;
            border: 2px solid #ef4444;
        }

        .debug-info {
            background: #f3f4f6;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            max-height: 300px;
            overflow-y: auto;
        }

        .debug-info h4 {
            color: #374151;
            margin-bottom: 10px;
        }

        .debug-info ul {
            list-style: none;
            padding: 0;
        }

        .debug-info li {
            padding: 5px 0;
            color: #6b7280;
            border-bottom: 1px solid #e5e7eb;
        }

        .debug-info li:last-child {
            border-bottom: none;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #333;
            font-weight: 700;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }

        input[type="email"] {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e0e0e0;
            border-radius: 10px;
            font-size: 1rem;
            font-family: 'Inter', sans-serif;
            font-weight: 600;
            transition: all 0.3s;
            background: white;
        }

        input[type="email"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .btn-submit {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.3s;
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
            font-family: 'Inter', sans-serif;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-submit:active {
            transform: translateY(0);
        }

        .back-links {
            text-align: center;
            margin-top: 25px;
        }

        .back-links a {
            color: #667eea;
            text-decoration: none;
            font-weight: 700;
            font-size: 0.95rem;
            transition: color 0.3s;
        }

        .back-links a:hover {
            color: #764ba2;
        }
    </style>
</head>
<body>
    <div class="forgot-container">
        <div class="forgot-card">
            <div class="logo-container">
                <div class="logo">FinTrack</div>
                <p class="logo-subtitle">Personal Expense Tracker</p>
            </div>

            <div class="icon">🔑</div>

            <div class="welcome-text">
                <h2>Forgot Password?</h2>
                <p>Enter your email address and we'll send you a link to reset your password.</p>
            </div>

            <?php if(!empty($debugInfo)): ?>
                <div class="debug-info">
                    <h4>🐛 Debug Information:</h4>
                    <ul>
                        <?php foreach($debugInfo as $info): ?>
                            <li><?php echo htmlspecialchars($info); ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Email Address</label>
                    <input type="email" id="email" name="email" placeholder="Enter your email" required />
                </div>

                <button type="submit" name="submit" class="btn-submit">
                    Send Reset Link
                </button>
            </form>

            <div class="back-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>