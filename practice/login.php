<?php
  session_start();
  if(isset($_POST['submit'])){
    // Add your login logic here
  } else {
?>
<!doctype html>
<html lang="en">
  <head>
    <title>FinTrack - Login</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="FinTrack Login" />
    <meta name="keywords" content="fintrack, expense tracker, login" />
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

      .login-container {
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


      .login-card {
        display: flex;
        flex-direction: row; /* Landscape layout */
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

      .login-left {
        flex: 1;
        text-align: center;
        padding: 15px;
        border-right: 1px solid #eee;
      }

      .login-right {
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
      input[type="password"],
      input[type="email"] {
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
      input[type="password"]:focus,
      input[type="email"]:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
      }

      .forgot-password {
        text-align: right;
        margin-bottom: 18px;
      }

      .forgot-password a {
        color: #667eea;
        text-decoration: none;
        font-size: 0.9rem;
        font-weight: 700;
        transition: color 0.3s;
      }

      .forgot-password a:hover {
        color: #764ba2;
      }

      .btn-login {
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

      .btn-login:hover {
        transform: translateY(-2px);
        box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
      }

      .btn-login:active {
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

      .signup-link {
        text-align: center;
        color: #666;
        font-size: 0.95rem;
        font-weight: 600;
      }

      .signup-link a {
        color: #667eea;
        text-decoration: none;
        font-weight: 700;
        transition: color 0.3s;
      }

      .signup-link a:hover {
        color: #764ba2;
      }

      .back-home {
        text-align: center;
        margin-top: 20px;
      }

      .back-home a {
        color: rgba(255, 255, 255, 0.9);
        text-decoration: none;
        font-size: 0.95rem;
        font-weight: 700;
        transition: all 0.3s;
        display: inline-block;
      }

      .back-home a:hover {
        color: white;
        transform: translateX(-5px);
      }

      .checkbox-container {
        display: flex;
        align-items: center;
        margin-bottom: 15px;
      }

      .checkbox-container input[type="checkbox"] {
        width: 18px;
        height: 18px;
        margin-right: 10px;
        cursor: pointer;
      }

      .checkbox-container label {
        margin-bottom: 0;
        font-size: 0.9rem;
        cursor: pointer;
        font-weight: 600;
      }
    </style>
    <script>
      function togglePassword() {
        const passwordInput = document.getElementById('password');
        const eyeIcon = document.getElementById('eye-icon');
        
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
    <div class="login-container">
      <div class="login-card">
        <!-- Left Side -->
        <div class="login-left">
          <div class="logo-container">
            <div class="logo">FinTrack</div>
            <p class="logo-subtitle">Personal Expense Tracker</p>
          </div>
        </div>

        <!-- Right Side -->
        <div class="login-right">
          <div class="welcome-text">
            <h2>Welcome Back!</h2>
            <p>Login to manage your finances</p>
          </div>
          
          <form method="POST" action="login_process.php">
            <div class="form-group">
              <label for="username">Username or Email</label>
              <div class="input-wrapper">
                <input type="text" id="username" name="username" placeholder="Enter your username" required />
              </div>
            </div>

            <div class="form-group">
              <label for="password">Password</label>
              <div class="input-wrapper">
                <input type="password" id="password" name="password" placeholder="Enter your password" required />
                <span class="toggle-password" onclick="togglePassword()">
                  <svg id="eye-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                    <circle cx="12" cy="12" r="3"></circle>
                  </svg>
                </span>
              </div>
            </div>

            <div class="checkbox-container">
              <input type="checkbox" id="remember" name="remember" />
              <label for="remember">Remember me</label>
            </div>

            <div class="forgot-password">
              <a href="forgot_password.php">Forgot Password?</a>
            </div>

            <button type="submit" name="submit" class="btn-login">Login</button>

            <div class="divider">
              <span>OR</span>
            </div>

            <div class="signup-link">
              Don't have an account? <a href="signup.php">Sign up</a>
            </div>
          </form>
        </div>
      </div>
    </div>
  </body>
</html>
<?php } ?>