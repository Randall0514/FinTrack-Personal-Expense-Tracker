<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include("../database/config/db.php");

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

// ✅ Check JWT via cookie
if (!isset($_COOKIE['jwt_token'])) {
    echo "<script>alert('You must log in first.'); window.location.href='../../login.php';</script>";
    exit;
}

$jwt = $_COOKIE['jwt_token'];

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user = (array) $decoded->data;
} catch (Exception $e) {
    echo "<script>alert('❌ Invalid or expired token. Please log in again.'); window.location.href='../../login.php';</script>";
    setcookie("jwt_token", "", time() - 3600, "/");
    exit;
}

// ✅ Get user ID from JWT
$user_id = $user['id'];

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $current_pass = $_POST['current_password'];
    $new_pass = $_POST['new_password'];
    $confirm_pass = $_POST['confirm_password'];

    // Fetch current password from DB
    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();

    if (!$row || !password_verify($current_pass, $row['password'])) {
        $error_message = "Current password is incorrect!";
    } elseif ($new_pass !== $confirm_pass) {
        $error_message = "New passwords do not match!";
    } elseif (strlen($new_pass) < 6) {
        $error_message = "Password must be at least 6 characters!";
    } else {
        // Hash new password
        $hashed_password = password_hash($new_pass, PASSWORD_DEFAULT);

        // Update in DB
        $update = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $update->bind_param("si", $hashed_password, $user_id);

        if ($update->execute()) {
            $success_message = "Password changed successfully!";
        } else {
            $error_message = "Error updating password. Please try again.";
        }
    }
}
?>

<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
<head>
  <title>Change Password - FinTrack</title>
  <meta charset="utf-8" />
  <link rel="icon" type="image/png" href="../../logo.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      min-height: 100vh;
      font-weight: 600;
    }

    .pc-container { background: transparent !important; }
    .pc-content { padding: 20px; }

    .page-header {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 25px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .page-header-title h5 {
      color: white !important;
      font-weight: 700;
      font-size: 1.8rem;
    }

    .breadcrumb { background: transparent !important; }
    .breadcrumb-item, .breadcrumb-item a {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 600;
    }

    .card {
      background: rgba(255, 255, 255, 0.95) !important;
      backdrop-filter: blur(10px);
      border-radius: 15px !important;
      border: none !important;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 20px;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3) !important;
    }

    .card-header {
      background: transparent !important;
      border-bottom: 2px solid #f0f0f0 !important;
      padding: 20px !important;
    }

    .card-header h5 {
      color: #667eea !important;
      font-weight: 700;
      font-size: 1.2rem;
    }

    .card-body { padding: 25px !important; }

    /* Alert Messages */
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 20px;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideInDown 0.5s ease-out;
    }

    .alert-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .alert-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
    }

    .form-label {
      font-weight: 700;
      color: #333;
      margin-bottom: 8px;
      font-size: 0.9rem;
    }

    .required { color: #ef4444; font-weight: 700; }

    .form-control {
      border: 2px solid #e0e0e0;
      border-radius: 10px;
      padding: 12px 15px;
      font-size: 0.95rem;
      transition: all 0.3s;
      font-weight: 500;
      width: 100%;
    }

    .form-control:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
      outline: none;
    }

    .btn {
      padding: 12px 30px;
      border-radius: 10px;
      font-weight: 700;
      font-size: 0.95rem;
      transition: all 0.3s;
      border: none;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      cursor: pointer;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
    }

    .btn-warning:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(245, 158, 11, 0.4);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      color: white;
    }

    .password-requirements {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.05) 100%);
      border-left: 4px solid #667eea;
      padding: 15px;
      border-radius: 10px;
      margin-top: 20px;
    }

    .password-requirements h6 {
      font-weight: 700;
      color: #667eea;
      margin-bottom: 10px;
    }

    .password-requirements ul {
      margin: 0;
      padding-left: 20px;
      color: #666;
    }

    .password-requirements li {
      margin-bottom: 5px;
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-50px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
  </style>
</head>
<body>
  <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
    </div>
  </div>

  <?php include '../includes/sidebar.php'; ?>
  <?php include '../includes/header.php'; ?>

  <div class="pc-container">
    <div class="pc-content">
      <div class="page-header">
        <div class="page-block">
          <div class="page-header-title">
            <h5 class="mb-0 font-medium">🔒 Change Password</h5>
          </div>
          <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item" aria-current="page">Change Password</li>
          </ul>
        </div>
      </div>

      <?php if ($success_message): ?>
        <div class="alert alert-success">
          <i class="feather icon-check-circle" style="font-size: 1.5rem;"></i>
          <strong><?php echo $success_message; ?></strong>
        </div>
      <?php endif; ?>

      <?php if ($error_message): ?>
        <div class="alert alert-danger">
          <i class="feather icon-alert-circle" style="font-size: 1.5rem;"></i>
          <strong><?php echo $error_message; ?></strong>
        </div>
      <?php endif; ?>

      <div class="grid grid-cols-12 gap-x-6">
        <div class="col-span-12">
          <div class="card">
            <div class="card-header">
              <h5>🔐 Change Your Password</h5>
            </div>
            <div class="card-body">
              <form method="POST" action="">
                <div class="mb-3">
                  <label class="form-label">Current Password <span class="required">*</span></label>
                  <input type="password" name="current_password" class="form-control" placeholder="Enter your current password" required />
                </div>
                
                <div class="mb-3">
                  <label class="form-label">New Password <span class="required">*</span></label>
                  <input type="password" name="new_password" id="new_password" class="form-control" placeholder="Enter new password (min. 6 characters)" required minlength="6" />
                </div>
                
                <div class="mb-4">
                  <label class="form-label">Confirm New Password <span class="required">*</span></label>
                  <input type="password" name="confirm_password" id="confirm_password" class="form-control" placeholder="Re-enter new password" required minlength="6" />
                </div>

                <div class="password-requirements">
                  <h6><i class="feather icon-info"></i> Password Requirements:</h6>
                  <ul>
                    <li>Must be at least 6 characters long</li>
                    <li>Use a combination of letters, numbers, and symbols for better security</li>
                    <li>Avoid using personal information</li>
                    <li>Don't reuse passwords from other accounts</li>
                  </ul>
                </div>

                <div class="flex mt-4 justify-between items-center flex-wrap gap-3">
                  <button type="submit" class="btn btn-primary">
                    <i class="feather icon-check"></i> Change Password
                  </button>
                  <a href="dashboard.php" class="btn btn-secondary">
                    <i class="feather icon-arrow-left"></i> Back to Dashboard
                  </a>
                </div>
              </form>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script src="../assets/js/plugins/simplebar.min.js"></script>
  <script src="../assets/js/plugins/popper.min.js"></script>
  <script src="../assets/js/icon/custom-icon.js"></script>
  <script src="../assets/js/plugins/feather.min.js"></script>
  <script src="../assets/js/component.js"></script>
  <script src="../assets/js/theme.js"></script>
  <script src="../assets/js/script.js"></script>

  <script>
    // Password confirmation validation
    document.querySelector('form').addEventListener('submit', function(e) {
      const newPassword = document.getElementById('new_password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      
      if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('New passwords do not match!');
        return false;
      }
      
      if (newPassword.length < 6) {
        e.preventDefault();
        alert('Password must be at least 6 characters long!');
        return false;
      }
    });

    // Layout configuration
    layout_change('false');
    layout_theme_sidebar_change('dark');
    change_box_container('false');
    layout_caption_change('true');
    layout_rtl_change('false');
    preset_change('preset-1');
    main_layout_change('vertical');
  </script>
</body>
</html>