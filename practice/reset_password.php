<?php
session_start();
require __DIR__ . "/config/dbconfig_password.php";

// Get token from URL - trim whitespace and decode if needed
$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid_token = false;
$error_message = '';

// Debug: Log token info (remove in production)
error_log("Token received: " . $token);
error_log("Token length: " . strlen($token));

if (!empty($token)) {
    // Make sure token is exactly 64 characters (32 bytes in hex)
    if (strlen($token) !== 64) {
        $error_message = 'Invalid token format. Token length: ' . strlen($token);
        error_log($error_message);
    } else {
        $token_hash = hash('sha256', $token);
        error_log("Token hash: " . $token_hash);
        
        // Verify token with detailed error checking
        $stmt = $conn->prepare("
            SELECT pr.user_id, pr.expires_at, pr.used, u.email, u.fullname 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token_hash = ?
            LIMIT 1
        ");
        $stmt->bind_param("s", $token_hash);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows === 0) {
            $error_message = 'Token not found in database. Please request a new reset link.';
            error_log($error_message);
        } else {
            $user_data = $result->fetch_assoc();
            
            // Check if already used
            if ($user_data['used'] == 1) {
                $error_message = 'This reset link has already been used. Please request a new one.';
                error_log($error_message);
            }
            // Check if expired
            elseif (strtotime($user_data['expires_at']) <= time()) {
                $error_message = 'This reset link has expired. Please request a new one.';
                error_log("Token expired at: " . $user_data['expires_at'] . ", Current time: " . date('Y-m-d H:i:s'));
            }
            else {
                $valid_token = true;
            }
        }
        $stmt->close();
    }
} else {
    $error_message = 'No reset token provided in URL.';
    error_log($error_message);
}
?>
<!doctype html>
<html lang="en">
<head>
  <title>FinTrack - Reset Password</title>
  <meta charset="utf-8" />
  <link rel="icon" type="image/png" href="logo.png" />
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
      max-width: 750px;
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
      display: flex;
      flex-direction: row;
      align-items: center;
      justify-content: space-between;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 20px;
      padding: 25px;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideUp 0.5s ease-out;
      gap: 25px;
    }

    .reset-left {
      flex: 1;
      text-align: center;
      padding: 15px;
      border-right: 1px solid #eee;
    }

    .reset-right {
      flex: 1.2;
      padding: 15px;
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
      margin-bottom: 25px;
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

    .key-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 15px;
    }

    .key-icon svg {
      width: 40px;
      height: 40px;
      stroke: white;
      stroke-width: 2;
      fill: none;
    }

    .welcome-text h2 {
      font-size: 1.5rem;
      color: #333;
      margin-bottom: 8px;
      font-weight: 700;
    }

    .welcome-text p {
      color: #666;
      font-size: 1rem;
      font-weight: 600;
      margin-bottom: 20px;
    }

    .form-group {
      margin-bottom: 18px;
    }

    label {
      display: block;
      color: #333;
      font-weight: 700;
      margin-bottom: 8px;
      font-size: 0.95rem;
    }

    .input-wrapper {
      position: relative;
    }

    .toggle-password {
      position: absolute;
      right: 15px;
      top: 50%;
      transform: translateY(-50%);
      cursor: pointer;
      color: #666;
      transition: color 0.3s;
      display: flex;
      align-items: center;
    }

    .toggle-password:hover {
      color: #667eea;
    }

    input[type="text"],
    input[type="password"] {
      width: 100%;
      padding: 12px 45px 12px 16px;
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      font-size: 1rem;
      font-family: 'Inter', sans-serif;
      font-weight: 600;
      transition: all 0.3s;
      background: white;
    }

    input[type="text"]:focus,
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
    }

    .strength-fill {
      height: 100%;
      width: 0%;
      transition: all 0.3s;
      border-radius: 2px;
    }

    .strength-weak { width: 33%; background: #ff4444; }
    .strength-medium { width: 66%; background: #ffaa00; }
    .strength-strong { width: 100%; background: #00C851; }

    .btn-reset {
      width: 100%;
      padding: 12px;
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

    .divider {
      display: flex;
      align-items: center;
      text-align: center;
      margin: 20px 0;
      color: #999;
      font-weight: 600;
    }

    .divider::before,
    .divider::after {
      content: '';
      flex: 1;
      border-bottom: 1px solid #e0e0e0;
    }

    .divider span {
      padding: 0 15px;
      font-size: 0.9rem;
    }

    .back-login {
      text-align: center;
      color: #666;
      font-size: 0.95rem;
      font-weight: 600;
    }

    .back-login a {
      color: #667eea;
      text-decoration: none;
      font-weight: 700;
      transition: color 0.3s;
    }

    .back-login a:hover {
      color: #764ba2;
    }

    .alert {
      padding: 12px 15px;
      border-radius: 10px;
      margin-bottom: 15px;
      font-size: 0.85rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .alert-error {
      background: #f8d7da;
      color: #721c24;
      border: 1px solid #f5c6cb;
    }

    .alert svg {
      width: 20px;
      height: 20px;
      flex-shrink: 0;
    }

    .password-requirements {
      background: #f0f7ff;
      border: 1px solid #bedaff;
      border-radius: 8px;
      padding: 12px;
      margin-bottom: 15px;
    }

    .password-requirements h4 {
      color: #0066cc;
      font-size: 0.85rem;
      margin-bottom: 8px;
    }

    .password-requirements ul {
      list-style: none;
      padding: 0;
      margin: 0;
    }

    .password-requirements li {
      color: #333;
      font-size: 0.8rem;
      font-weight: 600;
      padding: 3px 0;
      padding-left: 20px;
      position: relative;
    }

    .password-requirements li::before {
      content: '✓';
      position: absolute;
      left: 0;
      color: #ccc;
      font-weight: bold;
      font-size: 0.9rem;
      transition: color 0.3s;
    }

    .password-requirements li.met {
      color: #00C851;
    }

    .password-requirements li.met::before {
      color: #00C851;
    }

    .btn-secondary {
      display: inline-block;
      padding: 12px 30px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      text-decoration: none;
      border-radius: 10px;
      font-weight: 700;
      transition: all 0.3s;
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .error-state {
      text-align: center;
    }
  </style>
</head>
<body>
  <div class="reset-container">
    <div class="reset-card">
      <!-- Left Side -->
      <div class="reset-left">
        <div class="logo-container">
          <div class="logo">FinTrack</div>
          <p class="logo-subtitle">Personal Expense Tracker</p>
        </div>
        <div class="key-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <path d="M21 2l-2 2m-7.61 7.61a5.5 5.5 0 1 1-7.778 7.778 5.5 5.5 0 0 1 7.777-7.777zm0 0L15.5 7.5m0 0l3 3L22 7l-3-3m-3.5 3.5L19 4"></path>
          </svg>
        </div>
      </div>

      <!-- Right Side -->
      <div class="reset-right">
        <?php if (!$valid_token): ?>
          <div class="error-state">
            <div class="welcome-text">
              <h2>Invalid Reset Link</h2>
              <p><?php echo htmlspecialchars($error_message); ?></p>
            </div>
            
            <div class="alert alert-error">
              <svg fill="currentColor" viewBox="0 0 20 20">
                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
              </svg>
              The password reset link is invalid or has expired.
            </div>

            <div style="margin: 20px 0;">
              <a href="forgot_password.php" class="btn-secondary">Request New Link</a>
            </div>

            <div class="divider">
              <span>OR</span>
            </div>

            <div class="back-login">
              <a href="login.php">Back to Login</a>
            </div>
          </div>

        <?php else: ?>
          <div class="welcome-text">
            <h2>Reset Your Password</h2>
            <p>Hi <strong><?php echo htmlspecialchars($user_data['fullname']); ?></strong>, enter your new password below.</p>
          </div>

          <div class="password-requirements">
            <h4>Password Requirements:</h4>
            <ul>
              <li id="req-length">At least 8 characters long</li>
              <li id="req-case">Contains uppercase and lowercase letters</li>
              <li id="req-number">Includes at least one number</li>
              <li id="req-special">Has at least one special character</li>
            </ul>
          </div>

          <form method="POST" action="reset_password_process.php" id="resetForm">
            <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>">
            
            <div class="form-group">
              <label for="password">New Password</label>
              <div class="input-wrapper">
                <input type="password" id="password" name="password" placeholder="Enter new password" required />
                <span class="toggle-password" onclick="togglePassword('password', 'eye-icon-1')">
                  <svg id="eye-icon-1" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </span>
              </div>
              <div class="password-strength">
                <div class="strength-bar">
                  <div class="strength-fill" id="strengthBar"></div>
                </div>
              </div>
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm New Password</label>
              <div class="input-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm new password" required />
                <span class="toggle-password" onclick="togglePassword('confirm_password', 'eye-icon-2')">
                  <svg id="eye-icon-2" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </span>
              </div>
            </div>

            <button type="submit" name="submit" class="btn-reset">
              Reset Password
            </button>

            <div class="divider">
              <span>OR</span>
            </div>

            <div class="back-login">
              <a href="login.php">Back to Login</a>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    function togglePassword(inputId, iconId) {
      const passwordInput = document.getElementById(inputId);
      const eyeIcon = document.getElementById(iconId);
      
      if (passwordInput.type === 'password') {
        passwordInput.type = 'text';
        eyeIcon.innerHTML = '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path><line x1="1" y1="1" x2="23" y2="23"></line>';
      } else {
        passwordInput.type = 'password';
        eyeIcon.innerHTML = '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle>';
      }
    }

    const passwordInput = document.getElementById('password');
    const strengthBar = document.getElementById('strengthBar');

    if (passwordInput && strengthBar) {
      passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;

        // Check requirements
        const hasLength = password.length >= 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);

        // Update requirement indicators
        document.getElementById('req-length').classList.toggle('met', hasLength);
        document.getElementById('req-case').classList.toggle('met', hasUpperCase && hasLowerCase);
        document.getElementById('req-number').classList.toggle('met', hasNumber);
        document.getElementById('req-special').classList.toggle('met', hasSpecial);

        // Calculate strength
        if (password.length >= 8) strength++;
        if (password.length >= 12) strength++;
        if (hasNumber) strength++;
        if (hasUpperCase && hasLowerCase) strength++;
        if (hasSpecial) strength++;

        strengthBar.className = 'strength-fill';
        if (strength <= 2) {
          strengthBar.classList.add('strength-weak');
        } else if (strength <= 4) {
          strengthBar.classList.add('strength-medium');
        } else {
          strengthBar.classList.add('strength-strong');
        }
      });
    }

    const resetForm = document.getElementById('resetForm');
    if (resetForm) {
      resetForm.addEventListener('submit', function(e) {
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        // Check if passwords match
        if (password !== confirmPassword) {
          e.preventDefault();
          alert('Passwords do not match!');
          return false;
        }

        // Validate all requirements
        const hasLength = password.length >= 8;
        const hasUpperCase = /[A-Z]/.test(password);
        const hasLowerCase = /[a-z]/.test(password);
        const hasNumber = /\d/.test(password);
        const hasSpecial = /[^A-Za-z0-9]/.test(password);

        if (!hasLength) {
          e.preventDefault();
          alert('Password must be at least 8 characters long!');
          return false;
        }

        if (!hasUpperCase || !hasLowerCase) {
          e.preventDefault();
          alert('Password must contain both uppercase and lowercase letters!');
          return false;
        }

        if (!hasNumber) {
          e.preventDefault();
          alert('Password must contain at least one number!');
          return false;
        }

        if (!hasSpecial) {
          e.preventDefault();
          alert('Password must contain at least one special character!');
          return false;
        }
      });
    }
  </script>
</body>
</html> 