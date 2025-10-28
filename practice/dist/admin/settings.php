<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

// Database connection
include '../database/config/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

// Check JWT via cookie
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

$user_id = $user['id'];
$message = '';
$message_type = '';

// Fetch current user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$current_user = $result->fetch_assoc();
$stmt->close();

// Fetch security settings
$security_settings = [];
$result = $conn->query("SELECT * FROM security_settings LIMIT 1");
if ($result && $result->num_rows > 0) {
    $security_settings = $result->fetch_assoc();
} else {
    // Default values if table doesn't exist
    $security_settings = [
        'password_min_length' => 8,
        'require_special_chars' => 1,
        'require_numbers' => 1,
        'require_uppercase' => 1
    ];
}

// Set default profile picture if not exists
if (empty($current_user['profile_picture']) || $current_user['profile_picture'] == '../assets/images/default-avatar.png') {
    $current_user['profile_picture'] = '../assets/images/default-avatar.png';
    $current_user['has_custom_picture'] = false;
} else {
    $current_user['has_custom_picture'] = true;
}

// Handle Profile Picture Upload with cropped data
if (isset($_POST['upload_picture'])) {
    $target_dir = "upload/images/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    // Check if cropped image data is provided
    if (isset($_POST['cropped_image_data']) && !empty($_POST['cropped_image_data'])) {
        $imageData = $_POST['cropped_image_data'];
        
        // Remove data URL prefix
        $imageData = str_replace('data:image/png;base64,', '', $imageData);
        $imageData = str_replace(' ', '+', $imageData);
        $decodedImage = base64_decode($imageData);
        
        if ($decodedImage !== false) {
            $new_filename = "profile_" . $user_id . "_" . time() . ".png";
            $target_file = $target_dir . $new_filename;
            
            if (file_put_contents($target_file, $decodedImage)) {
                // Delete old profile picture if it exists and is not default
                if ($current_user['has_custom_picture'] && file_exists($current_user['profile_picture'])) {
                    unlink($current_user['profile_picture']);
                }
                
                $sql = "UPDATE users SET profile_picture = ? WHERE id = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $target_file, $user_id);
                
                if ($stmt->execute()) {
                    $message = "Profile picture updated successfully!";
                    $message_type = "success";
                    $current_user['profile_picture'] = $target_file;
                    $current_user['has_custom_picture'] = true;
                } else {
                    $message = "Error saving profile picture to database.";
                    $message_type = "danger";
                }
                $stmt->close();
            } else {
                $message = "Error saving cropped image.";
                $message_type = "danger";
            }
        } else {
            $message = "Error processing cropped image.";
            $message_type = "danger";
        }
    } else {
        $message = "No cropped image data provided.";
        $message_type = "warning";
    }
}

// Handle Profile Information Update
if (isset($_POST['update_profile'])) {
    $fullname = trim($_POST['fullname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    
    $stmt = $conn->prepare("UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ?");
    $stmt->bind_param("sssi", $fullname, $email, $phone, $user_id);
    
    if ($stmt->execute()) {
        $message = "Profile information updated successfully!";
        $message_type = "success";
        $current_user['fullname'] = $fullname;
        $current_user['email'] = $email;
        $current_user['phone'] = $phone;
    } else {
        $message = "Failed to update profile information.";
        $message_type = "danger";
    }
    $stmt->close();
}

// Handle Budget Settings Update
if (isset($_POST['update_budget'])) {
    $daily_budget = floatval($_POST['daily_budget']);
    $weekly_budget = floatval($_POST['weekly_budget']);
    $monthly_budget = floatval($_POST['monthly_budget']);
    
    $stmt = $conn->prepare("UPDATE users SET daily_budget = ?, weekly_budget = ?, monthly_budget = ? WHERE id = ?");
    $stmt->bind_param("dddi", $daily_budget, $weekly_budget, $monthly_budget, $user_id);
    
    if ($stmt->execute()) {
        $message = "Budget settings updated successfully!";
        $message_type = "success";
        $current_user['daily_budget'] = $daily_budget;
        $current_user['weekly_budget'] = $weekly_budget;
        $current_user['monthly_budget'] = $monthly_budget;
    } else {
        $message = "Failed to update budget settings.";
        $message_type = "danger";
    }
    $stmt->close();
}

// Handle Password Change with security validation
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password against security settings
    $password_errors = [];
    
    // Check minimum length
    if (strlen($new_password) < $security_settings['password_min_length']) {
        $password_errors[] = "Password must be at least " . $security_settings['password_min_length'] . " characters long.";
    }
    
    // Check for special characters
    if ($security_settings['require_special_chars'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $password_errors[] = "Password must contain at least one special character (!@#$%^&*).";
    }
    
    // Check for numbers
    if ($security_settings['require_numbers'] && !preg_match('/[0-9]/', $new_password)) {
        $password_errors[] = "Password must contain at least one number (0-9).";
    }
    
    // Check for uppercase letters
    if ($security_settings['require_uppercase'] && !preg_match('/[A-Z]/', $new_password)) {
        $password_errors[] = "Password must contain at least one uppercase letter (A-Z).";
    }
    
    if (!empty($password_errors)) {
        $message = implode("<br>", $password_errors);
        $message_type = "danger";
    } elseif ($new_password !== $confirm_password) {
        $message = "New passwords do not match.";
        $message_type = "warning";
    } elseif (!password_verify($current_password, $current_user['password'])) {
        $message = "Current password is incorrect.";
        $message_type = "danger";
    } else {
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id);
        
        if ($stmt->execute()) {
            $message = "Password changed successfully!";
            $message_type = "success";
        } else {
            $message = "Failed to change password.";
            $message_type = "danger";
        }
        $stmt->close();
    }
}

// Build password requirements text dynamically
$password_requirements = [];
$password_requirements[] = "Minimum " . $security_settings['password_min_length'] . " characters";
if ($security_settings['require_special_chars']) {
    $password_requirements[] = "Include special characters (!@#$%^&*)";
}
if ($security_settings['require_numbers']) {
    $password_requirements[] = "Include numbers (0-9)";
}
if ($security_settings['require_uppercase']) {
    $password_requirements[] = "Include uppercase letters (A-Z)";
}
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
  <title>FinTrack - Settings</title>
  <meta charset="utf-8" />
  <link rel="icon" type="image/png" href="../../logo.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  <link rel="stylesheet" href="style/settings.css">
  
</head>

<body>
  <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
    </div>
  </div>

  <?php include '../includes/sidebar.php'; ?>
  <?php include '../includes/header.php'; ?>

  <!-- Image Cropper Modal -->
  <div id="cropperModal" class="cropper-modal">
    <div class="cropper-content">
      <div class="cropper-header">
        <h2>Adjust Your Photo</h2>
        <p>Drag to reposition, use controls to zoom and rotate</p>
      </div>

      <div class="cropper-canvas-wrapper" id="cropperWrapper">
        <canvas id="cropperCanvas" width="400" height="400"></canvas>
      </div>

      <div class="cropper-controls">
        <div class="control-group">
          <label>
            <span style="display: flex; align-items: center; gap: 8px;">
              <i class="feather icon-zoom-in"></i>
              Zoom
            </span>
            <span id="zoomValue">100%</span>
          </label>
          <input type="range" id="zoomSlider" min="0.5" max="3" step="0.1" value="1">
        </div>

        <div class="control-group">
          <label>
            <span style="display: flex; align-items: center; gap: 8px;">
              <i class="feather icon-rotate-cw"></i>
              Rotation
            </span>
            <span id="rotationValue">0°</span>
          </label>
          <input type="range" id="rotationSlider" min="0" max="360" step="1" value="0">
        </div>
      </div>

      <div class="cropper-buttons">
        <button type="button" class="btn btn-secondary" onclick="cancelCrop()">
          <i class="feather icon-x"></i>
          <span>Cancel</span>
        </button>
        <button type="button" class="btn btn-primary" onclick="applyCrop()">
          <i class="feather icon-check"></i>
          <span>Apply Crop</span>
        </button>
      </div>
    </div>
  </div>

  <div class="pc-container">
    <div class="pc-content">
      <div class="page-header">
        <div class="page-block">
          <div class="page-header-title">
            <h5 class="mb-0 font-medium">⚙️ Settings & Preferences</h5>
            <p class="page-subtitle">Manage your account settings and customize your experience</p>
          </div>
          <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item" aria-current="page">Settings</li>
          </ul>
        </div>
      </div>

      <?php if ($message): ?>
        <div class="alert alert-<?php echo $message_type; ?>">
          <i class="feather icon-<?php echo $message_type === 'success' ? 'check-circle' : 'alert-circle'; ?>"></i>
          <span><?php echo $message; ?></span>
        </div>
      <?php endif; ?>

      <div class="settings-grid">
        <!-- Profile Picture -->
        <div class="col-span-4">
          <div class="card">
            <div class="card-header">
              <i class="feather icon-camera"></i>
              <h6>Profile Picture</h6>
            </div>
            <div class="card-body">
              <div class="profile-section">
                <div class="profile-picture-wrapper">
                  <?php if ($current_user['has_custom_picture']): ?>
                    <img src="<?php echo htmlspecialchars($current_user['profile_picture']); ?>" 
                         alt="Profile Picture" 
                         class="profile-preview"
                         id="profilePreview">
                  <?php else: ?>
                    <div class="no-picture" id="profilePreview">
                      <?php echo strtoupper(substr($current_user['fullname'], 0, 1)); ?>
                    </div>
                  <?php endif; ?>
                </div>

                <form method="POST" enctype="multipart/form-data" id="uploadForm">
                  <input type="hidden" name="cropped_image_data" id="croppedImageData">
                  
                  <div class="upload-btn-wrapper">
                    <label for="profile_picture" class="upload-btn">
                      <i class="feather icon-upload"></i>
                      <span>Choose New Picture</span>
                    </label>
                    <input type="file" 
                           name="profile_picture" 
                           id="profile_picture" 
                           accept="image/jpeg,image/jpg,image/png,image/gif"
                           onchange="handleFileSelect(event)">
                  </div>
                  
                  <button type="submit" name="upload_picture" class="btn btn-primary btn-full" id="saveBtn" style="margin-top: 15px; display: none;">
                    <i class="feather icon-save"></i>
                    <span>Save Picture</span>
                  </button>
                </form>

                <div class="info-box">
                  <i class="feather icon-info"></i>
                  <div>
                    <strong>Requirements:</strong><br>
                    Max size: 5MB<br>
                    Formats: JPG, PNG, GIF
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Profile Information -->
        <div class="col-span-8">
          <div class="card">
            <div class="card-header">
              <i class="feather icon-user"></i>
              <h6>Personal Information</h6>
            </div>
            <div class="card-body">
              <form method="POST">
                <div class="form-group">
                  <label><i class="feather icon-user" style="margin-right: 5px;"></i> Full Name</label>
                  <input type="text" 
                         name="fullname" 
                         value="<?php echo htmlspecialchars($current_user['fullname']); ?>" 
                         placeholder="Enter your full name"
                         required>
                </div>
                
                <div class="form-row">
                  <div class="form-group">
                    <label><i class="feather icon-mail" style="margin-right: 5px;"></i> Email Address</label>
                    <input type="email" 
                           name="email" 
                           value="<?php echo htmlspecialchars($current_user['email']); ?>" 
                           placeholder="your.email@example.com"
                           required>
                  </div>
                  
                  <div class="form-group">
                    <label><i class="feather icon-phone" style="margin-right: 5px;"></i> Phone Number</label>
                    <input type="tel" 
                           name="phone" 
                           value="<?php echo htmlspecialchars($current_user['phone'] ?? ''); ?>" 
                           placeholder="+63 xxx xxx xxxx">
                  </div>
                </div>

                <div class="info-box">
                  <i class="feather icon-shield"></i>
                  <div>Your personal information is kept secure and will never be shared with third parties.</div>
                </div>

                <button type="submit" name="update_profile" class="btn btn-primary" style="margin-top: 20px;">
                  <i class="feather icon-save"></i>
                  <span>Update Profile</span>
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Budget Settings -->
        <div class="col-span-12">
          <div class="card">
            <div class="card-header">
              <i class="feather icon-dollar-sign"></i>
              <h6>Budget Configuration</h6>
            </div>
            <div class="card-body">
              <div class="budget-stats">
                <div class="stat-card">
                  <h4>₱ <?php echo number_format($current_user['daily_budget'] ?? 500, 2); ?></h4>
                  <p>Daily Budget</p>
                </div>
                <div class="stat-card">
                  <h4>₱ <?php echo number_format($current_user['weekly_budget'] ?? 3000, 2); ?></h4>
                  <p>Weekly Budget</p>
                </div>
                <div class="stat-card">
                  <h4>₱ <?php echo number_format($current_user['monthly_budget'] ?? 10000, 2); ?></h4>
                  <p>Monthly Budget</p>
                </div>
              </div>

              <form method="POST">
                <div class="form-row">
                  <div class="form-group">
                    <label><i class="feather icon-calendar" style="margin-right: 5px;"></i> Daily Budget (₱)</label>
                    <input type="number" 
                           name="daily_budget" 
                           id="daily_budget"
                           step="0.01" 
                           value="<?php echo $current_user['daily_budget'] ?? 500; ?>" 
                           placeholder="500.00"
                           oninput="updateBudgets('daily')"
                           required>
                  </div>
                  
                  <div class="form-group">
                    <label><i class="feather icon-calendar" style="margin-right: 5px;"></i> Weekly Budget (₱)</label>
                    <input type="number" 
                           name="weekly_budget" 
                           id="weekly_budget"
                           step="0.01" 
                           value="<?php echo $current_user['weekly_budget'] ?? 3000; ?>" 
                           placeholder="3000.00"
                           oninput="updateBudgets('weekly')"
                           required>
                  </div>
                </div>

                <div class="form-group">
                  <label><i class="feather icon-calendar" style="margin-right: 5px;"></i> Monthly Budget (₱)</label>
                  <input type="number" 
                         name="monthly_budget" 
                         id="monthly_budget"
                         step="0.01" 
                         value="<?php echo $current_user['monthly_budget'] ?? 10000; ?>" 
                         placeholder="10000.00"
                         oninput="updateBudgets('monthly')"
                         required>
                </div>

                <div class="info-box">
                  <i class="feather icon-trending-up"></i>
                  <div>
                    <strong>Budget Management:</strong> Set realistic spending limits for each period. You'll receive alerts when approaching or exceeding these amounts to help you stay on track with your financial goals.
                  </div>
                </div>

                <button type="submit" name="update_budget" class="btn btn-primary" style="margin-top: 20px;">
                  <i class="feather icon-save"></i>
                  <span>Update Budget Settings</span>
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Security Settings -->
        <div class="col-span-12">
          <div class="card">
            <div class="card-header">
              <i class="feather icon-lock"></i>
              <h6>Security Settings</h6>
            </div>
            <div class="card-body">
              <form method="POST" id="passwordForm">
                <div class="form-row">
                  <div class="form-group">
                    <label><i class="feather icon-key" style="margin-right: 5px;"></i> Current Password</label>
                    <div class="input-wrapper">
                      <input type="password" 
                             name="current_password" 
                             id="current_password"
                             placeholder="Enter current password"
                             required>
                      <span class="toggle-password" onclick="togglePasswordField('current_password', 'eye-icon-current')">
                        <svg id="eye-icon-current" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                      </span>
                    </div>
                  </div>
                  
                  <div class="form-group">
                    <label><i class="feather icon-lock" style="margin-right: 5px;"></i> New Password</label>
                    <div class="input-wrapper">
                      <input type="password" 
                             name="new_password" 
                             id="new_password"
                             placeholder="Enter new password"
                             minlength="<?php echo $security_settings['password_min_length']; ?>"
                             oninput="checkPasswordStrength()"
                             required>
                      <span class="toggle-password" onclick="togglePasswordField('new_password', 'eye-icon-new')">
                        <svg id="eye-icon-new" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                          <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                          <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                      </span>
                    </div>
                    <div class="password-strength">
                      <div class="password-strength-bar" id="strength-bar"></div>
                    </div>
                    <div class="password-strength-text" id="strength-text"></div>
                  </div>
                </div>
                
                <div class="form-group">
                  <label><i class="feather icon-check-circle" style="margin-right: 5px;"></i> Confirm New Password</label>
                  <div class="input-wrapper">
                    <input type="password" 
                           name="confirm_password" 
                           id="confirm_password"
                           placeholder="Re-enter new password"
                           minlength="<?php echo $security_settings['password_min_length']; ?>"
                           required>
                    <span class="toggle-password" onclick="togglePasswordField('confirm_password', 'eye-icon-confirm')">
                      <svg id="eye-icon-confirm" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                        <circle cx="12" cy="12" r="3"></circle>
                      </svg>
                    </span>
                  </div>
                </div>

                <div class="info-box">
                  <i class="feather icon-shield"></i>
                  <div>
                    <strong>Password Requirements:</strong><br>
                    <?php foreach ($password_requirements as $requirement): ?>
                      • <?php echo $requirement; ?><br>
                    <?php endforeach; ?>
                  </div>
                </div>

                <button type="submit" name="change_password" class="btn btn-primary" style="margin-top: 20px;">
                  <i class="feather icon-key"></i>
                  <span>Change Password</span>
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Account Actions -->
        <div class="col-span-12">
          <div class="card">
            <div class="card-header">
              <i class="feather icon-settings"></i>
              <h6>Quick Actions</h6>
            </div>
            <div class="card-body">
              <div class="action-buttons">
                <div class="action-card">
                  <i class="feather icon-home"></i>
                  <h6>Dashboard</h6>
                  <a href="dashboard.php" class="btn btn-primary btn-full">
                    <i class="feather icon-arrow-left"></i>
                    <span>Back to Dashboard</span>
                  </a>
                </div>

                <div class="action-card">
                  <i class="feather icon-list"></i>
                  <h6>Manage Expenses</h6>
                  <a href="manage_expenses.php" class="btn btn-secondary btn-full">
                    <i class="feather icon-file-text"></i>
                    <span>View Expenses</span>
                  </a>
                </div>

                <div class="action-card">
                  <i class="feather icon-log-out"></i>
                  <h6>Sign Out</h6>
                  <a href="logout.php" class="btn btn-danger btn-full">
                    <i class="feather icon-log-out"></i>
                    <span>Logout</span>
                  </a>
                </div>
              </div>
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
    // Image Cropper Variables
    let currentImage = null;
    let canvas = null;
    let ctx = null;
    let scale = 1;
    let rotation = 0;
    let position = { x: 0, y: 0 };
    let isDragging = false;
    let dragStart = { x: 0, y: 0 };

    // Security settings from PHP
    const securitySettings = {
      minLength: <?php echo $security_settings['password_min_length']; ?>,
      requireSpecialChars: <?php echo $security_settings['require_special_chars'] ? 'true' : 'false'; ?>,
      requireNumbers: <?php echo $security_settings['require_numbers'] ? 'true' : 'false'; ?>,
      requireUppercase: <?php echo $security_settings['require_uppercase'] ? 'true' : 'false'; ?>
    };

    // Initialize canvas
    document.addEventListener('DOMContentLoaded', function() {
      canvas = document.getElementById('cropperCanvas');
      ctx = canvas.getContext('2d');

      // Dragging events
      const wrapper = document.getElementById('cropperWrapper');
      
      wrapper.addEventListener('mousedown', startDrag);
      wrapper.addEventListener('mousemove', drag);
      wrapper.addEventListener('mouseup', stopDrag);
      wrapper.addEventListener('mouseleave', stopDrag);

      // Touch events for mobile
      wrapper.addEventListener('touchstart', handleTouchStart);
      wrapper.addEventListener('touchmove', handleTouchMove);
      wrapper.addEventListener('touchend', stopDrag);

      // Zoom slider
      document.getElementById('zoomSlider').addEventListener('input', function(e) {
        scale = parseFloat(e.target.value);
        document.getElementById('zoomValue').textContent = Math.round(scale * 100) + '%';
        drawImage();
      });

      // Rotation slider
      document.getElementById('rotationSlider').addEventListener('input', function(e) {
        rotation = parseInt(e.target.value);
        document.getElementById('rotationValue').textContent = rotation + '°';
        drawImage();
      });
    });

    function handleFileSelect(event) {
      const file = event.target.files[0];
      if (!file) return;

      // Validate file type
      const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
      if (!allowedTypes.includes(file.type)) {
        alert('Invalid file type. Only JPG, PNG, and GIF allowed.');
        event.target.value = '';
        return;
      }

      // Validate file size (5MB)
      if (file.size > 5 * 1024 * 1024) {
        alert('File size too large. Maximum 5MB allowed.');
        event.target.value = '';
        return;
      }

      const reader = new FileReader();
      reader.onload = function(e) {
        currentImage = new Image();
        currentImage.onload = function() {
          // Reset transformations
          scale = 1;
          rotation = 0;
          position = { x: 0, y: 0 };
          
          document.getElementById('zoomSlider').value = 1;
          document.getElementById('zoomValue').textContent = '100%';
          document.getElementById('rotationSlider').value = 0;
          document.getElementById('rotationValue').textContent = '0°';

          // Show modal and draw image
          document.getElementById('cropperModal').classList.add('active');
          drawImage();
        };
        currentImage.src = e.target.result;
      };
      reader.readAsDataURL(file);
    }

    function drawImage() {
      if (!currentImage || !ctx) return;

      const size = canvas.width;
      ctx.clearRect(0, 0, size, size);
      ctx.save();

      // Center and apply transformations
      ctx.translate(size / 2 + position.x, size / 2 + position.y);
      ctx.rotate((rotation * Math.PI) / 180);
      ctx.scale(scale, scale);

      // Calculate dimensions to fit image
      const imgRatio = currentImage.width / currentImage.height;
      let drawWidth = size;
      let drawHeight = size;

      if (imgRatio > 1) {
        drawHeight = size / imgRatio;
      } else {
        drawWidth = size * imgRatio;
      }

      ctx.drawImage(currentImage, -drawWidth / 2, -drawHeight / 2, drawWidth, drawHeight);
      ctx.restore();

      // Apply circular mask
      ctx.globalCompositeOperation = 'destination-in';
      ctx.beginPath();
      ctx.arc(size / 2, size / 2, size / 2, 0, Math.PI * 2);
      ctx.fill();
      ctx.globalCompositeOperation = 'source-over';
    }

    function startDrag(e) {
      isDragging = true;
      dragStart = {
        x: e.clientX - position.x,
        y: e.clientY - position.y
      };
    }

    function drag(e) {
      if (!isDragging) return;
      
      position = {
        x: e.clientX - dragStart.x,
        y: e.clientY - dragStart.y
      };
      drawImage();
    }

    function stopDrag() {
      isDragging = false;
    }

    function handleTouchStart(e) {
      const touch = e.touches[0];
      isDragging = true;
      dragStart = {
        x: touch.clientX - position.x,
        y: touch.clientY - position.y
      };
    }

    function handleTouchMove(e) {
      if (!isDragging) return;
      e.preventDefault();
      const touch = e.touches[0];
      
      position = {
        x: touch.clientX - dragStart.x,
        y: touch.clientY - dragStart.y
      };
      drawImage();
    }

    function applyCrop() {
      if (!canvas) return;

      // Get the cropped image data
      const croppedData = canvas.toDataURL('image/png');
      
      // Store in hidden input
      document.getElementById('croppedImageData').value = croppedData;
      
      // Update preview
      const preview = document.getElementById('profilePreview');
      if (preview.tagName === 'IMG') {
        preview.src = croppedData;
      } else {
        preview.outerHTML = '<img src="' + croppedData + '" alt="Profile Preview" class="profile-preview" id="profilePreview">';
      }
      
      // Show save button
      document.getElementById('saveBtn').style.display = 'flex';
      
      // Hide modal
      document.getElementById('cropperModal').classList.remove('active');
    }

    function cancelCrop() {
      document.getElementById('cropperModal').classList.remove('active');
      document.getElementById('profile_picture').value = '';
    }

    // Toggle password visibility
    function togglePasswordField(inputId, iconId) {
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

    // Check password strength based on security settings
    function checkPasswordStrength() {
      const password = document.getElementById('new_password').value;
      const strengthBar = document.getElementById('strength-bar');
      const strengthText = document.getElementById('strength-text');
      
      let strength = 0;
      let feedback = [];
      
      // Check length
      if (password.length >= securitySettings.minLength) {
        strength += 25;
      } else {
        feedback.push(`At least ${securitySettings.minLength} characters`);
      }
      
      // Check special characters
      if (securitySettings.requireSpecialChars) {
        if (/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
          strength += 25;
        } else {
          feedback.push('Special character (!@#$%^&*)');
        }
      } else {
        strength += 25;
      }
      
      // Check numbers
      if (securitySettings.requireNumbers) {
        if (/[0-9]/.test(password)) {
          strength += 25;
        } else {
          feedback.push('Number (0-9)');
        }
      } else {
        strength += 25;
      }
      
      // Check uppercase
      if (securitySettings.requireUppercase) {
        if (/[A-Z]/.test(password)) {
          strength += 25;
        } else {
          feedback.push('Uppercase letter (A-Z)');
        }
      } else {
        strength += 25;
      }
      
      // Update strength bar
      strengthBar.style.width = strength + '%';
      
      if (strength === 0) {
        strengthBar.style.background = '#e5e7eb';
        strengthText.style.color = '#6b7280';
        strengthText.textContent = '';
      } else if (strength < 50) {
        strengthBar.style.background = '#ef4444';
        strengthText.style.color = '#ef4444';
        strengthText.textContent = 'Weak - Missing: ' + feedback.join(', ');
      } else if (strength < 100) {
        strengthBar.style.background = '#f59e0b';
        strengthText.style.color = '#f59e0b';
        strengthText.textContent = 'Fair - Missing: ' + feedback.join(', ');
      } else {
        strengthBar.style.background = '#10b981';
        strengthText.style.color = '#10b981';
        strengthText.textContent = 'Strong - All requirements met!';
      }
    }

    // Validate password on form submit
    document.addEventListener('DOMContentLoaded', function() {
      const passwordForm = document.getElementById('passwordForm');
      if (passwordForm) {
        passwordForm.addEventListener('submit', function(e) {
          const password = document.getElementById('new_password').value;
          const confirmPassword = document.getElementById('confirm_password').value;
          
          let errors = [];
          
          if (password.length < securitySettings.minLength) {
            errors.push(`Password must be at least ${securitySettings.minLength} characters long.`);
          }
          
          if (securitySettings.requireSpecialChars && !/[!@#$%^&*(),.?":{}|<>]/.test(password)) {
            errors.push('Password must contain at least one special character (!@#$%^&*).');
          }
          
          if (securitySettings.requireNumbers && !/[0-9]/.test(password)) {
            errors.push('Password must contain at least one number (0-9).');
          }
          
          if (securitySettings.requireUppercase && !/[A-Z]/.test(password)) {
            errors.push('Password must contain at least one uppercase letter (A-Z).');
          }
          
          if (password !== confirmPassword) {
            errors.push('Passwords do not match.');
          }
          
          if (errors.length > 0) {
            e.preventDefault();
            alert(errors.join('\n'));
            return false;
          }
        });
      }

      // Password confirmation validation
      const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');
      if (confirmPasswordInput) {
        confirmPasswordInput.addEventListener('input', function() {
          const newPasswordInput = document.querySelector('input[name="new_password"]');
          if (newPasswordInput.value !== confirmPasswordInput.value) {
            confirmPasswordInput.setCustomValidity('Passwords do not match');
          } else {
            confirmPasswordInput.setCustomValidity('');
          }
        });
      }

      // Auto-hide alerts after 5 seconds
      const alerts = document.querySelectorAll('.alert');
      alerts.forEach(function(alert) {
        setTimeout(function() {
          alert.style.transition = 'opacity 0.5s, transform 0.5s';
          alert.style.opacity = '0';
          alert.style.transform = 'translateY(-30px)';
          setTimeout(function() {
            alert.remove();
          }, 500);
        }, 5000);
      });
    });

    // Budget auto-calculation
    function updateBudgets(changedField) {
      const daily = parseFloat(document.getElementById('daily_budget').value) || 0;
      const weekly = parseFloat(document.getElementById('weekly_budget').value) || 0;
      const monthly = parseFloat(document.getElementById('monthly_budget').value) || 0;

      if (changedField === 'daily' && daily > 0) {
        document.getElementById('weekly_budget').value = (daily * 7).toFixed(2);
        document.getElementById('monthly_budget').value = (daily * 30).toFixed(2);
      } else if (changedField === 'weekly' && weekly > 0) {
        document.getElementById('daily_budget').value = (weekly / 7).toFixed(2);
        document.getElementById('monthly_budget').value = (weekly * 4.33).toFixed(2);
      } else if (changedField === 'monthly' && monthly > 0) {
        document.getElementById('daily_budget').value = (monthly / 30).toFixed(2);
        document.getElementById('weekly_budget').value = (monthly / 4.33).toFixed(2);
      }
    }

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