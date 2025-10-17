<?php
session_start();
include 'database/config/db.php';

$message = '';
$messageType = '';
$validToken = false;
$token = '';

// Check if token is provided
if(isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Verify token
    $stmt = $conn->prepare("SELECT id, email, fullname, reset_token_expiry FROM users WHERE reset_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Check if token has expired
        if(strtotime($user['reset_token_expiry']) > time()) {
            $validToken = true;
        } else {
            $message = 'This reset link has expired. Please request a new one.';
            $messageType = 'error';
        }
    } else {
        $message = 'Invalid reset link.';
        $messageType = 'error';
    }
    $stmt->close();
} else {
    $message = 'No reset token provided.';
    $messageType = 'error';
}

// Handle password reset
if(isset($_POST['submit']) && $validToken) {
    $password = $_POST['password'];
    $confirmPassword = $_POST['confirm_password'];
    
    // Validation
    if(empty($password) || empty($confirmPassword)) {
        $message = 'Please fill in all fields.';
        $messageType = 'error';
    } elseif($password !== $confirmPassword) {
        $message = 'Passwords do not match.';
        $messageType = 'error';
    } elseif(strlen($password) < 8) {
        $message = 'Password must be at least 8 characters long.';
        $messageType = 'error';
    } else {
        // Hash the new password
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
        
        // Update password and clear reset token
        $updateStmt = $conn->prepare("UPDATE users SET password = ?, reset_token = NULL, reset_token_expiry = NULL WHERE reset_token = ?");
        $updateStmt->bind_param("ss", $hashedPassword, $token);
        
        if($updateStmt->execute()) {
            $message = 'Password reset successful! You can now login with your new password.';
            $messageType = 'success';
            $validToken = false; // Prevent form from showing again
        } else {
            $message = 'Something went wrong. Please try again.';
            $messageType = 'error';
        }
        
        $updateStmt->close();
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <title>FinTrack - Reset Password</title>
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
            overflow: hidden;
            font-weight: 700;
        }

        html {
            overflow: hidden;
        }

        .reset-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 500px;
            padding: 20px;
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

        .reset-card {
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

        input[type="password"] {
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

        input[type="password"]:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
        }

        .password-strength {
            margin-top: 8px;
            font-size: 0.85rem;
            font-weight: 600;
        }

        .strength-bar {
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            margin-top: 5px;
            overflow: hidden;
        }

        .strength-fill {
            height: 100%;
            width: 0%;
            transition: all 0.3s;
            border-radius: 2px;
        }

        .strength-weak { width: 33%; background: #ef4444; }
        .strength-medium { width: 66%; background: #f59e0b; }
        .strength-strong { width: 100%; background: #10b981; }

        .password-requirements {
            background: #f9fafb;
            padding: 15px;
            border-radius: 8px;
            margin-top: 10px;
            font-size: 0.85rem;
        }

        .password-requirements ul {
            list-style: none;
            padding: 0;
            margin: 5px 0 0 0;
        }

        .password-requirements li {
            padding: 3px 0;
            color: #6b7280;
            font-weight: 600;
        }

        .password-requirements li.valid {
            color: #10b981;
        }

        .password-requirements li::before {
            content: '○ ';
            margin-right: 5px;
        }

        .password-requirements li.valid::before {
            content: '✓ ';
        }

        .btn-reset {
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

        .btn-reset:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
        }

        .btn-reset:active {
            transform: translateY(0);
        }

        .btn-reset:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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

        .icon {
            display: inline-block;
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
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-card">
            <div class="logo-container">
                <div class="logo">FinTrack</div>
                <p class="logo-subtitle">Personal Expense Tracker</p>
            </div>

            <div class="icon"><?php echo $validToken ? '🔐' : '⚠️'; ?></div>

            <div class="welcome-text">
                <h2><?php echo $validToken ? 'Reset Your Password' : 'Invalid Link'; ?></h2>
                <p><?php echo $validToken ? 'Enter your new password below.' : 'This reset link is invalid or has expired.'; ?></p>
            </div>

            <?php if(!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
            <?php endif; ?>

            <?php if($validToken): ?>
                <form method="POST" action="" id="resetForm">
                    <div class="form-group">
                        <label for="password">New Password</label>
                        <input type="password" id="password" name="password" placeholder="Enter new password" required />
                        <div class="password-strength">
                            <div class="strength-bar">
                                <div class="strength-fill" id="strengthBar"></div>
                            </div>
                        </div>
                        <div class="password-requirements">
                            <strong>Password must contain:</strong>
                            <ul id="requirements">
                                <li id="req-length">At least 8 characters</li>
                                <li id="req-uppercase">One uppercase letter</li>
                                <li id="req-lowercase">One lowercase letter</li>
                                <li id="req-number">One number</li>
                            </ul>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="confirm_password">Confirm Password</label>
                        <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required />
                    </div>

                    <button type="submit" name="submit" class="btn-reset" id="submitBtn">
                        Reset Password
                    </button>
                </form>
            <?php endif; ?>

            <div class="back-links">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>

    <script>
        const passwordInput = document.getElementById('password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const strengthBar = document.getElementById('strengthBar');
        const submitBtn = document.getElementById('submitBtn');
        const resetForm = document.getElementById('resetForm');

        // Password strength checker
        passwordInput.addEventListener('input', function() {
            const password = this.value;
            let strength = 0;

            // Check requirements
            const hasLength = password.length >= 8;
            const hasUppercase = /[A-Z]/.test(password);
            const hasLowercase = /[a-z]/.test(password);
            const hasNumber = /\d/.test(password);

            document.getElementById('req-length').classList.toggle('valid', hasLength);
            document.getElementById('req-uppercase').classList.toggle('valid', hasUppercase);
            document.getElementById('req-lowercase').classList.toggle('valid', hasLowercase);
            document.getElementById('req-number').classList.toggle('valid', hasNumber);

            if (hasLength) strength++;
            if (hasUppercase) strength++;
            if (hasLowercase) strength++;
            if (hasNumber) strength++;
            if (/[^A-Za-z0-9]/.test(password)) strength++;

            strengthBar.className = 'strength-fill';
            if (strength <= 2) {
                strengthBar.classList.add('strength-weak');
            } else if (strength <= 4) {
                strengthBar.classList.add('strength-medium');
            } else {
                strengthBar.classList.add('strength-strong');
            }
        });

        // Form validation
        resetForm.addEventListener('submit', function(e) {
            const password = passwordInput.value;
            const confirmPassword = confirmPasswordInput.value;

            if (password !== confirmPassword) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }

            if (password.length < 8) {
                e.preventDefault();
                alert('Password must be at least 8 characters long!');
                return false;
            }

            if (!/[A-Z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one uppercase letter!');
                return false;
            }

            if (!/[a-z]/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one lowercase letter!');
                return false;
            }

            if (!/\d/.test(password)) {
                e.preventDefault();
                alert('Password must contain at least one number!');
                return false;
            }
        });
    </script>
</body>
</html>