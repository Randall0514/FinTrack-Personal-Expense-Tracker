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

// Set default profile picture if not exists
if (empty($current_user['profile_picture']) || $current_user['profile_picture'] == '../assets/images/default-avatar.png') {
    $current_user['profile_picture'] = '../assets/images/default-avatar.png';
    $current_user['has_custom_picture'] = false;
} else {
    $current_user['has_custom_picture'] = true;
}

// Handle Profile Picture Upload
if (isset($_POST['upload_picture'])) {
    $target_dir = "upload/images/";
    
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    if (isset($_FILES["profile_picture"]) && $_FILES["profile_picture"]["error"] == 0) {
        $file_extension = strtolower(pathinfo($_FILES["profile_picture"]["name"], PATHINFO_EXTENSION));
        $new_filename = "profile_" . $user_id . "_" . time() . "." . $file_extension;
        $target_file = $target_dir . $new_filename;
        
        $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
        $max_size = 5 * 1024 * 1024; // 5MB
        
        if (!in_array($file_extension, $allowed_types)) {
            $message = "Only JPG, JPEG, PNG & GIF files are allowed!";
            $message_type = "warning";
        } elseif ($_FILES["profile_picture"]["size"] > $max_size) {
            $message = "File size must be less than 5MB!";
            $message_type = "warning";
        } elseif (move_uploaded_file($_FILES["profile_picture"]["tmp_name"], $target_file)) {
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
            $message = "Error uploading file.";
            $message_type = "danger";
        }
    } else {
        $message = "No file selected or upload error occurred.";
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

// Handle Password Change
if (isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if ($new_password === $confirm_password) {
        if (password_verify($current_password, $current_user['password'])) {
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
        } else {
            $message = "Current password is incorrect.";
            $message_type = "danger";
        }
    } else {
        $message = "New passwords do not match.";
        $message_type = "warning";
    }
}
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
  <title>FinTrack - Settings</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />

  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      min-height: 100vh;
      font-weight: 600;
    }

    .pc-container { background: transparent !important; }
    .pc-content { padding: 20px; max-width: 1400px; margin: 0 auto; }

    .page-header {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 25px 30px;
      margin-bottom: 30px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .page-header-title h5 {
      color: white !important;
      font-weight: 700;
      font-size: 2rem;
      margin-bottom: 5px;
    }

    .page-subtitle {
      color: rgba(255, 255, 255, 0.85);
      font-size: 0.95rem;
      margin-top: 5px;
    }

    .breadcrumb { background: transparent !important; margin-top: 10px; }
    .breadcrumb-item, .breadcrumb-item a {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 600;
    }

    .card {
      background: rgba(255, 255, 255, 0.98) !important;
      backdrop-filter: blur(20px);
      border-radius: 20px !important;
      border: none !important;
      box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15) !important;
      margin-bottom: 25px;
      overflow: hidden;
      transition: all 0.3s ease;
    }

    .card:hover {
      transform: translateY(-3px);
      box-shadow: 0 15px 50px rgba(0, 0, 0, 0.25) !important;
    }

    .card-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      border: none !important;
      padding: 20px 30px !important;
      display: flex;
      align-items: center;
      gap: 12px;
    }

    .card-header h6 {
      color: white !important;
      font-weight: 700;
      font-size: 1.15rem;
      margin: 0;
      letter-spacing: 0.3px;
    }

    .card-header i {
      font-size: 1.4rem;
      color: white;
    }

    .card-body { 
      padding: 35px 30px !important;
    }

    .alert {
      padding: 18px 25px;
      border-radius: 12px;
      margin-bottom: 25px;
      display: flex;
      align-items: center;
      gap: 15px;
      animation: slideInDown 0.5s ease-out;
      font-weight: 600;
      box-shadow: 0 5px 20px rgba(0, 0, 0, 0.1);
    }

    .alert-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    .alert-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
    }

    .alert-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
    }

    .alert i {
      font-size: 1.6rem;
    }

    .settings-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 25px;
    }

    .col-span-12 { grid-column: span 12; }
    .col-span-6 { grid-column: span 6; }
    .col-span-4 { grid-column: span 4; }
    .col-span-8 { grid-column: span 8; }

    @media (max-width: 1024px) {
      .col-span-8 { grid-column: span 12; }
      .col-span-4 { grid-column: span 12; }
    }

    @media (max-width: 768px) {
      .col-span-6 { grid-column: span 12; }
    }

    /* Profile Picture Section */
    .profile-section {
      display: flex;
      flex-direction: column;
      align-items: center;
      gap: 25px;
      width: 100%;
    }

    .profile-picture-wrapper {
      position: relative;
      display: flex;
      justify-content: center;
      align-items: center;
      margin-bottom: 10px;
    }

    .profile-preview {
      width: 180px;
      height: 180px;
      border-radius: 50%;
      object-fit: cover;
      border: 6px solid #667eea;
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
      transition: all 0.3s ease;
    }

    .profile-preview:hover {
      transform: scale(1.05);
      box-shadow: 0 20px 50px rgba(102, 126, 234, 0.5);
    }

    .no-picture {
      width: 180px;
      height: 180px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-size: 4rem;
      font-weight: 700;
      border: 6px solid white;
      box-shadow: 0 15px 40px rgba(102, 126, 234, 0.4);
      transition: all 0.3s ease;
    }

    .no-picture:hover {
      transform: scale(1.05);
      box-shadow: 0 20px 50px rgba(102, 126, 234, 0.5);
    }

    .upload-btn-wrapper {
      position: relative;
      overflow: hidden;
      width: 100%;
    }

    .upload-btn-wrapper input[type=file] {
      position: absolute;
      left: -9999px;
    }

    .upload-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 14px 25px;
      border-radius: 12px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      width: 100%;
      font-size: 0.95rem;
    }

    .upload-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .upload-btn i {
      font-size: 1.2rem;
    }

    /* Form Styles */
    .form-group {
      margin-bottom: 25px;
    }

    .form-group label {
      display: block;
      color: #667eea;
      font-weight: 700;
      margin-bottom: 10px;
      font-size: 0.95rem;
      letter-spacing: 0.3px;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      padding: 14px 18px;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      outline: none;
      transition: all 0.3s;
      font-family: 'Inter', sans-serif;
      font-size: 0.95rem;
      background: white;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: #667eea;
      box-shadow: 0 0 0 4px rgba(102, 126, 234, 0.1);
    }

    /* Buttons */
    .btn {
      padding: 14px 32px;
      border-radius: 12px;
      border: none;
      font-weight: 700;
      cursor: pointer;
      transition: all 0.3s;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      font-size: 0.95rem;
      letter-spacing: 0.3px;
    }

    .btn i {
      font-size: 1.1rem;
    }

    .btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      box-shadow: 0 5px 15px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(102, 126, 234, 0.4);
    }

    .btn-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
    }

    .btn-danger:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(239, 68, 68, 0.4);
    }

    .btn-secondary {
      background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
      color: white;
      box-shadow: 0 5px 15px rgba(107, 114, 128, 0.3);
    }

    .btn-secondary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 25px rgba(107, 114, 128, 0.4);
    }

    .btn-full {
      width: 100%;
      justify-content: center;
    }

    /* Info Box */
    .info-box {
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
      padding: 18px 20px;
      border-radius: 12px;
      margin-top: 15px;
      color: #666;
      font-size: 0.9rem;
      border-left: 4px solid #667eea;
      display: flex;
      align-items: flex-start;
      gap: 12px;
    }

    .info-box i {
      color: #667eea;
      font-size: 1.3rem;
      margin-top: 2px;
    }

    /* Budget Stats */
    .budget-stats {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 15px;
      margin-bottom: 30px;
    }

    .stat-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      padding: 20px;
      border-radius: 15px;
      text-align: center;
      color: white;
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
      transition: all 0.3s;
    }

    .stat-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 12px 30px rgba(102, 126, 234, 0.4);
    }

    .stat-card:nth-child(2) {
      background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
    }

    .stat-card:nth-child(3) {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    }

    .stat-card h4 {
      margin: 0 0 8px 0;
      font-size: 1.8rem;
      font-weight: 700;
    }

    .stat-card p {
      margin: 0;
      font-size: 0.9rem;
      opacity: 0.95;
      font-weight: 600;
    }

    @media (max-width: 768px) {
      .budget-stats {
        grid-template-columns: 1fr;
      }
    }

    /* Account Actions */
    .action-buttons {
      display: flex;
      gap: 15px;
      flex-wrap: wrap;
    }

    .action-card {
      flex: 1;
      min-width: 200px;
      padding: 25px;
      border-radius: 15px;
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
      border: 2px solid rgba(102, 126, 234, 0.2);
      transition: all 0.3s;
      text-align: center;
    }

    .action-card:hover {
      border-color: #667eea;
      background: linear-gradient(135deg, rgba(102, 126, 234, 0.15) 0%, rgba(118, 75, 162, 0.15) 100%);
      transform: translateY(-3px);
    }

    .action-card i {
      font-size: 2.5rem;
      color: #667eea;
      margin-bottom: 15px;
    }

    .action-card h6 {
      color: #667eea;
      font-weight: 700;
      margin-bottom: 15px;
    }

    @keyframes slideInDown {
      from {
        opacity: 0;
        transform: translateY(-30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .form-row {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 20px;
    }

    @media (max-width: 768px) {
      .form-row {
        grid-template-columns: 1fr;
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
                  <div class="upload-btn-wrapper">
                    <label for="profile_picture" class="upload-btn">
                      <i class="feather icon-upload"></i>
                      <span>Choose New Picture</span>
                    </label>
                    <input type="file" 
                           name="profile_picture" 
                           id="profile_picture" 
                           accept="image/jpeg,image/jpg,image/png,image/gif"
                           onchange="previewFile()">
                  </div>
                  <button type="submit" name="upload_picture" class="btn btn-primary btn-full" style="margin-top: 15px;">
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
              <form method="POST">
                <div class="form-row">
                  <div class="form-group">
                    <label><i class="feather icon-key" style="margin-right: 5px;"></i> Current Password</label>
                    <input type="password" 
                           name="current_password" 
                           placeholder="Enter current password"
                           required>
                  </div>
                  
                  <div class="form-group">
                    <label><i class="feather icon-lock" style="margin-right: 5px;"></i> New Password</label>
                    <input type="password" 
                           name="new_password" 
                           placeholder="Enter new password"
                           minlength="8"
                           required>
                  </div>
                </div>
                
                <div class="form-group">
                  <label><i class="feather icon-check-circle" style="margin-right: 5px;"></i> Confirm New Password</label>
                  <input type="password" 
                         name="confirm_password" 
                         placeholder="Re-enter new password"
                         minlength="8"
                         required>
                </div>

                <div class="info-box">
                  <i class="feather icon-shield"></i>
                  <div>
                    <strong>Password Requirements:</strong><br>
                    • Minimum 8 characters<br>
                    • Include numbers and special characters<br>
                    • Avoid common words or patterns
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
    function previewFile() {
      const file = document.getElementById('profile_picture').files[0];
      const preview = document.getElementById('profilePreview');
      const reader = new FileReader();

      if (file) {
        // Validate file size
        if (file.size > 5242880) {
          alert('File size too large. Maximum 5MB allowed.');
          document.getElementById('profile_picture').value = '';
          return;
        }

        // Validate file type
        const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif'];
        if (!allowedTypes.includes(file.type)) {
          alert('Invalid file type. Only JPG, PNG, and GIF allowed.');
          document.getElementById('profile_picture').value = '';
          return;
        }

        reader.onload = function(e) {
          if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
          } else {
            preview.outerHTML = '<img src="' + e.target.result + '" alt="Profile Preview" class="profile-preview" id="profilePreview">';
          }
        };
        reader.readAsDataURL(file);
      }
    }

    // Auto-hide alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
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
        // Daily changed: calculate weekly (7 days) and monthly (30 days)
        document.getElementById('weekly_budget').value = (daily * 7).toFixed(2);
        document.getElementById('monthly_budget').value = (daily * 30).toFixed(2);
      } else if (changedField === 'weekly' && weekly > 0) {
        // Weekly changed: calculate daily (weekly/7) and monthly (weekly*4.33)
        document.getElementById('daily_budget').value = (weekly / 7).toFixed(2);
        document.getElementById('monthly_budget').value = (weekly * 4.33).toFixed(2);
      } else if (changedField === 'monthly' && monthly > 0) {
        // Monthly changed: calculate daily (monthly/30) and weekly (monthly/4.33)
        document.getElementById('daily_budget').value = (monthly / 30).toFixed(2);
        document.getElementById('weekly_budget').value = (monthly / 4.33).toFixed(2);
      }
    }

    // Password confirmation validation
    const newPasswordInput = document.querySelector('input[name="new_password"]');
    const confirmPasswordInput = document.querySelector('input[name="confirm_password"]');

    if (confirmPasswordInput) {
      confirmPasswordInput.addEventListener('input', function() {
        if (newPasswordInput.value !== confirmPasswordInput.value) {
          confirmPasswordInput.setCustomValidity('Passwords do not match');
        } else {
          confirmPasswordInput.setCustomValidity('');
        }
      });
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