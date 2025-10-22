<?php
  session_start();
  if(isset($_POST['submit'])){
    // Add your registration logic here
    // Example:
    // $fullname = $_POST['fullname'];
    // $username = $_POST['username'];
    // $email = $_POST['email'];
    // $password = $_POST['password'];
    // $confirm_password = $_POST['confirm_password'];
    
    // Validate and insert into database
  } else {
?>
<!doctype html>
<html lang="en">
  <head>
    <title>FinTrack - Sign Up</title>
    <meta charset="utf-8" />
    <link rel="icon" type="image/png" href="logo.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="FinTrack Sign Up" />
    <meta name="keywords" content="fintrack, expense tracker, signup, register" />
    <meta name="author" content="FBSB10-Group2" />
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

      /* Background animation */
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

      .signup-container {
        position: relative;
        z-index: 1;
        width: 100%;
        max-width: 750px;
        padding: 20px;
      }

      .signup-card {
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

      @keyframes slideUp {
        from { opacity: 0; transform: translateY(30px); }
        to { opacity: 1; transform: translateY(0); }
      }

      .signup-left {
        flex: 1;
        text-align: center;
        padding: 15px;
        border-right: 1px solid #eee;
      }

      .signup-right {
        flex: 1.2;
        padding: 15px;
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

      .welcome-text h2 {
        font-size: 1.3rem;
        color: #333;
        margin-bottom: 4px;
        font-weight: 700;
      }

      .welcome-text p {
        color: #666;
        font-size: 0.9rem;
        font-weight: 600;
        margin-bottom: 12px;
      }

      .form-group { 
        margin-bottom: 6px; 
      }

      label {
        display: block;
        color: #333;
        font-weight: 700;
        margin-bottom: 3px;
        font-size: 0.8rem;
      }

      .input-wrapper {
        position: relative;
      }

      .toggle-password {
        position: absolute;
        right: 10px;
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
      input[type="password"],
      input[type="email"] {
        width: 100%;
        padding: 7px 35px 7px 10px;
        border: 2px solid #e0e0e0;
        border-radius: 8px;
        font-size: 0.85rem;
        font-family: 'Inter', sans-serif;
        font-weight: 600;
        transition: all 0.3s;
        background: white;
      }

      input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
      }

      .checkbox-container {
        display: flex;
        align-items: flex-start;
        margin-bottom: 8px;
      }

      .checkbox-container input[type="checkbox"] {
        width: 14px;
        height: 14px;
        margin-right: 6px;
        margin-top: 2px;
        cursor: pointer;
      }

      .checkbox-container label {
        margin-bottom: 0;
        font-size: 0.75rem;
        font-weight: 600;
        line-height: 1.2;
        cursor: pointer;
      }

      .checkbox-container label a {
        color: #667eea;
        text-decoration: none;
        font-weight: 700;
        transition: color 0.3s;
      }

      .checkbox-container label a:hover {
        color: #764ba2;
      }

      .btn-signup {
        width: 100%;
        padding: 8px;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
        border-radius: 8px;
        font-size: 0.95rem;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        font-family: 'Inter', sans-serif;
      }

      .btn-signup:hover { 
        transform: translateY(-2px); 
        box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
      }

      .btn-signup:active { 
        transform: translateY(0); 
      }

      .divider {
        display: flex;
        align-items: center;
        text-align: center;
        margin: 10px 0;
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
        padding: 0 12px; 
        font-size: 0.8rem;
      }

      .login-link {
        text-align: center;
        font-size: 0.85rem;
        color: #666;
        font-weight: 600;
      }

      .login-link a { 
        color: #667eea; 
        text-decoration: none;
        font-weight: 700;
        transition: color 0.3s;
      }

      .login-link a:hover {
        color: #764ba2;
      }

      .password-strength {
        margin-top: 3px;
        font-size: 0.65rem;
        font-weight: 600;
      }

      .strength-bar {
        height: 2px;
        background: #e0e0e0;
        border-radius: 1px;
        margin-top: 2px;
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

      .password-requirements {
        background: #f0f7ff;
        border: 1px solid #bedaff;
        border-radius: 6px;
        padding: 8px;
        margin-top: 5px;
        display: none;
        animation: slideDown 0.3s ease-out;
      }

      @keyframes slideDown {
        from {
          opacity: 0;
          transform: translateY(-10px);
        }
        to {
          opacity: 1;
          transform: translateY(0);
        }
      }

      .password-requirements.show {
        display: block;
      }

      .password-requirements h4 {
        color: #0066cc;
        font-size: 0.7rem;
        margin-bottom: 5px;
      }

      .password-requirements ul {
        list-style: none;
        padding: 0;
        margin: 0;
      }

      .password-requirements li {
        color: #333;
        font-size: 0.65rem;
        font-weight: 600;
        padding: 2px 0;
        padding-left: 15px;
        position: relative;
      }

      .password-requirements li::before {
        content: '✓';
        position: absolute;
        left: 0;
        color: #ccc;
        font-weight: bold;
        font-size: 0.75rem;
        transition: color 0.3s;
      }

      .password-requirements li.met {
        color: #00C851;
      }

      .password-requirements li.met::before {
        color: #00C851;
      }
    </style>
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
    </script>
  </head>
  <body>
    <div class="signup-container">
      <div class="signup-card">
        <!-- Left Side -->
        <div class="signup-left">
          <div class="logo-container">
            <div class="logo">FinTrack</div>
            <p class="logo-subtitle">Personal Expense Tracker</p>
          </div>
        </div>

        <!-- Right Side -->
        <div class="signup-right">
          <div class="welcome-text">
            <h2>Create Account</h2>
            <p>Start tracking your expenses today</p>
          </div>

          <form method="POST" action="signup_process.php" id="signupForm">
            <div class="form-group">
              <label for="fullname">Full Name</label>
              <input type="text" id="fullname" name="fullname" placeholder="Enter your full name" required />
            </div>

            <div class="form-group">
              <label for="username">Username</label>
              <input type="text" id="username" name="username" placeholder="Choose a username" required />
            </div>

            <div class="form-group">
              <label for="email">Email Address</label>
              <input type="email" id="email" name="email" placeholder="Enter your email" required />
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrapper">
                <input type="password" id="password" name="password" placeholder="Create a password" required />
                <span class="toggle-password" onclick="togglePassword('password', 'eye-icon-1')">
                  <svg id="eye-icon-1" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
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
              <div class="password-requirements" id="passwordRequirements">
                <h4>Password Requirements:</h4>
                <ul>
                  <li id="req-length">At least 8 characters long</li>
                  <li id="req-case">Contains uppercase and lowercase letters</li>
                  <li id="req-number">Includes at least one number</li>
                  <li id="req-special">Has at least one special character</li>
                </ul>
              </div>
            </div>

            <div class="form-group">
              <label for="confirm_password">Confirm Password</label>
              <div class="input-wrapper">
                <input type="password" id="confirm_password" name="confirm_password" placeholder="Confirm your password" required />
                <span class="toggle-password" onclick="togglePassword('confirm_password', 'eye-icon-2')">
                  <svg id="eye-icon-2" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </span>
              </div>
            </div>

            <div class="checkbox-container">
              <input type="checkbox" id="terms" name="terms" required />
              <label for="terms">I agree to the <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a></label>
            </div>

            <button type="submit" name="submit" class="btn-signup">Create Account</button>

            <div class="divider"><span>OR</span></div>

            <div class="login-link">
              Already have an account? <a href="login.php">Login</a>
            </div>
          </form>
        </div>
      </div>
    </div>

    <script>
      const passwordInput = document.getElementById('password');
      const strengthBar = document.getElementById('strengthBar');
      const passwordRequirements = document.getElementById('passwordRequirements');

      passwordInput.addEventListener('input', function() {
        const password = this.value;
        
        // Show/hide requirements box based on input
        if (password.length > 0) {
          passwordRequirements.classList.add('show');
        } else {
          passwordRequirements.classList.remove('show');
        }

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

      document.getElementById('signupForm').addEventListener('submit', function(e) {
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
    </script>
  </body>
</html>
<?php } ?>