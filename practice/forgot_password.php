<?php
session_start();
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
      overflow: hidden;
      font-weight: 700;
    }

    html {
      overflow: hidden;
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

    .forgot-container {
      position: relative;
      z-index: 1;
      width: 100%;
      max-width: 500px;
      padding: 20px;
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

    .icon-container {
      text-align: center;
      margin-bottom: 20px;
    }

    .lock-icon {
      width: 80px;
      height: 80px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      margin-bottom: 15px;
    }

    .lock-icon svg {
      width: 40px;
      height: 40px;
      stroke: white;
      stroke-width: 2;
      fill: none;
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

    .back-login {
      text-align: center;
      margin-top: 25px;
    }

    .back-login a {
      color: #667eea;
      text-decoration: none;
      font-size: 0.95rem;
      font-weight: 700;
      transition: color 0.3s;
      display: inline-flex;
      align-items: center;
      gap: 8px;
    }

    .back-login a:hover {
      color: #764ba2;
    }

    .back-login svg {
      width: 18px;
      height: 18px;
    }

    .alert {
      padding: 14px 18px;
      border-radius: 10px;
      margin-bottom: 20px;
      font-size: 0.9rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .alert-success {
      background: #d4edda;
      color: #155724;
      border: 1px solid #c3e6cb;
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
  </style>
</head>
<body>
  <div class="forgot-container">
    <div class="forgot-card">
      <div class="logo-container">
        <div class="logo">FinTrack</div>
        <p class="logo-subtitle">Personal Expense Tracker</p>
      </div>

      <div class="icon-container">
        <div class="lock-icon">
          <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
          </svg>
        </div>
      </div>

      <div class="welcome-text">
        <h2>Forgot Password?</h2>
        <p>No worries! Enter your email address and we'll send you instructions to reset your password.</p>
      </div>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"></path>
          </svg>
          Password reset link sent! Check your email.
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['error'])): ?>
        <div class="alert alert-error">
          <svg fill="currentColor" viewBox="0 0 20 20">
            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"></path>
          </svg>
          <?php 
            if ($_GET['error'] == 'not_found') echo 'Email address not found!';
            elseif ($_GET['error'] == 'email_failed') echo 'Failed to send email. Please try again.';
            else echo 'An error occurred. Please try again.';
          ?>
        </div>
      <?php endif; ?>

      <form method="POST" action="forgot_password_process.php">
        <div class="form-group">
          <label for="email">Email Address</label>
          <input type="email" id="email" name="email" placeholder="Enter your registered email" required />
        </div>

        <button type="submit" name="submit" class="btn-reset">
          Send Reset Link
        </button>
      </form>

      <div class="back-login">
        <a href="login.php">
          <svg fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
          </svg>
          Back to Login
        </a>
      </div>
    </div>
  </div>
</body>
</html>