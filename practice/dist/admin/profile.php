<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

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

// Fetch actual user data from database
$userData = [];
$userQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result();

if ($userResult->num_rows > 0) {
    $userData = $userResult->fetch_assoc();
} else {
    echo "<script>alert('User not found!'); window.location.href='../../login.php';</script>";
    exit;
}

// Set defaults for missing fields
$userData['phone'] = $userData['phone'] ?? '';
$userData['address'] = $userData['address'] ?? '';
$userData['monthly_budget'] = $userData['monthly_budget'] ?? 15000.00;
$userData['daily_budget'] = $userData['daily_budget'] ?? 500.00;
$userData['weekly_budget'] = $userData['weekly_budget'] ?? 3000.00;
$userData['profile_picture'] = $userData['profile_picture'] ?? '../assets/images/default-avatar.png';

// Handle Export Data
if (isset($_POST['export_data'])) {
    $export_type = $_POST['export_type'];
    
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="fintrack_data_' . date('Y-m-d') . '.csv"');
    
    $output = fopen('php://output', 'w');
    
    if ($export_type == 'expenses' || $export_type == 'all') {
        fputcsv($output, ['=== EXPENSES ===']);
        fputcsv($output, ['Category', 'Amount', 'Date', 'Description', 'Payment Method']);
        
        $result = $conn->query("SELECT * FROM expenses WHERE user_id = $user_id ORDER BY date DESC");
        while ($row = $result->fetch_assoc()) {
            fputcsv($output, [$row['category'], $row['amount'], $row['date'], $row['description'], $row['payment_method']]);
        }
        fputcsv($output, []);
    }
    
    if ($export_type == 'profile' || $export_type == 'all') {
        fputcsv($output, ['=== PROFILE INFORMATION ===']);
        fputcsv($output, ['Full Name', $userData['fullname']]);
        fputcsv($output, ['Email', $userData['email']]);
        fputcsv($output, ['Phone', $userData['phone']]);
        fputcsv($output, ['Monthly Budget', $userData['monthly_budget']]);
    }
    
    fclose($output);
    exit;
}
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
  <title>FinTrack - My Profile</title>
  <meta charset="utf-8" />
  <link rel="icon" type="image/png" href="../../logo.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  <link rel="stylesheet" href="style/profile.css">
  
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
            <h5 class="mb-0 font-medium">👤 My Profile</h5>
          </div>
          <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item" aria-current="page">Profile</li>
          </ul>
        </div>
      </div>

      <!-- Edit Banner -->
      <div class="edit-banner">
        <div class="edit-banner-content">
          <i class="feather icon-info"></i>
          <div>
            <strong>Want to update your profile?</strong><br>
            <span style="font-size: 0.9rem; opacity: 0.95;">Go to Settings to edit your personal information, budget, and preferences.</span>
          </div>
        </div>
        <a href="settings.php" class="btn btn-primary" style="background: white; color: #667eea; margin: 0;">
          <i class="feather icon-settings"></i> Go to Settings
        </a>
      </div>

      <div class="grid grid-cols-12">
        
        <!-- Profile Overview Card -->
        <div class="col-span-4">
          <div class="card">
            <div class="card-body">
              <div class="profile-avatar-container">
                <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" class="profile-avatar" alt="Profile Picture">
                <h3 class="profile-name"><?php echo htmlspecialchars($userData['fullname']); ?></h3>
                <p class="profile-email"><?php echo htmlspecialchars($userData['email']); ?></p>
              </div>

              <div style="text-align: center; margin-top: 20px; display: flex; flex-direction: column; gap: 10px;">
                <a href="settings.php" class="btn btn-primary btn-full">
                  <i class="feather icon-edit"></i> Edit Profile
                </a>
                <button type="button" class="btn btn-info btn-full" onclick="openModal('exportModal')">
                  <i class="feather icon-download"></i> Export My Data
                </button>
              </div>

              <div class="info-box" style="background: linear-gradient(135deg, rgba(59, 130, 246, 0.1) 0%, rgba(37, 99, 235, 0.05) 100%); border-left: 4px solid #3b82f6;">
                <h6 style="font-weight: 700; color: #3b82f6; margin-bottom: 10px;">
                  <i class="feather icon-dollar-sign"></i> Monthly Budget
                </h6>
                <p style="color: #666; margin: 0; font-size: 1.3rem; font-weight: 700;">₱ <?php echo number_format($userData['monthly_budget'], 2); ?></p>
              </div>
            </div>
          </div>
        </div>

        <!-- Profile Information Display -->
        <div class="col-span-8">
          <div class="card">
            <div class="card-header">
              <h5>Profile Information</h5>
              <a href="settings.php" class="btn btn-primary" style="padding: 8px 20px; font-size: 0.85rem;">
                <i class="feather icon-edit"></i> Edit
              </a>
            </div>
            <div class="card-body">
              <div class="info-group">
                <div class="info-label">
                  <i class="feather icon-user"></i> Full Name
                </div>
                <div class="info-value"><?php echo htmlspecialchars($userData['fullname']); ?></div>
              </div>

              <div class="info-group">
                <div class="info-label">
                  <i class="feather icon-mail"></i> Email Address
                </div>
                <div class="info-value"><?php echo htmlspecialchars($userData['email']); ?></div>
              </div>

              <div class="info-group">
                <div class="info-label">
                  <i class="feather icon-phone"></i> Phone Number
                </div>
                <div class="info-value <?php echo empty($userData['phone']) ? 'empty' : ''; ?>">
                  <?php echo !empty($userData['phone']) ? htmlspecialchars($userData['phone']) : 'Not provided'; ?>
                </div>
              </div>

              <div class="info-group">
                <div class="info-label">
                  <i class="feather icon-calendar"></i> Daily Budget
                </div>
                <div class="info-value">₱ <?php echo number_format($userData['daily_budget'], 2); ?></div>
              </div>

              <div class="info-group">
                <div class="info-label">
                  <i class="feather icon-calendar"></i> Weekly Budget
                </div>
                <div class="info-value">₱ <?php echo number_format($userData['weekly_budget'], 2); ?></div>
              </div>

              <div class="info-group">
                <div class="info-label">
                  <i class="feather icon-dollar-sign"></i> Monthly Budget
                </div>
                <div class="info-value">₱ <?php echo number_format($userData['monthly_budget'], 2); ?></div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- Export Data Modal -->
  <div id="exportModal" class="modal">
    <div class="modal-content">
      <h3><i class="feather icon-download"></i> Export My Data</h3>
      <p style="color: #666; margin-bottom: 20px;">Choose what data you want to export to CSV format:</p>
      <form method="POST">
        <div class="mb-3">
          <label class="form-label">Export Type <span class="required">*</span></label>
          <select name="export_type" class="form-select" required>
            <option value="all">All Data (Expenses & Profile)</option>
            <option value="expenses">Expenses Only</option>
            <option value="profile">Profile Information Only</option>
          </select>
        </div>
        <div style="display: flex; gap: 10px;">
          <button type="submit" name="export_data" class="btn btn-info">
            <i class="feather icon-download"></i> Export Now
          </button>
          <button type="button" class="btn btn-secondary" onclick="closeModal('exportModal')">
            <i class="feather icon-x"></i> Cancel
          </button>
        </div>
      </form>
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
    // Modal functions
    function openModal(modalId) {
      document.getElementById(modalId).style.display = 'flex';
    }

    function closeModal(modalId) {
      document.getElementById(modalId).style.display = 'none';
    }

    // Close modal when clicking outside
    window.onclick = function(event) {
      if (event.target.className === 'modal') {
        event.target.style.display = 'none';
      }
    }

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