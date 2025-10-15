<?php
// JWT Authentication for Sidebar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

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

// Get user ID from JWT
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
$userData['profile_picture'] = $userData['profile_picture'] ?? '../assets/images/user/avatar-2.jpg';
$userData['fullname'] = $userData['fullname'] ?? 'User';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar bg-white text-gray-800 w-64 flex flex-col shadow-lg">
  <div class="navbar-wrapper h-full flex flex-col">
    <!-- Brand -->
    <div class="p-6 border-b border-gray-200">
      <a href="../admin/dashboard.php" class="flex items-center gap-3">
        <!-- Logo Image -->
        <img src="../assets/images/Logo.png" alt="FinTrack Logo" class="w-12 h-12 rounded-lg object-cover shadow-md" />

        <!-- Text Section -->
        <div>
          <h1 class="text-xl font-bold text-gray-800">FinTrack</h1>
          <p class="text-xs text-gray-500">Expense Tracker</p>
        </div>
      </a>
    </div>

    <!-- Navigation -->
    <div class="flex-1 px-3 overflow-y-auto mt-4">
      <p class="text-xs text-gray-500 px-3 mb-2 font-semibold">NAVIGATION</p>

      <ul class="pc-navbar">
        <li>
          <a href="../admin/dashboard.php"
             class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border <?php echo ($current_page == 'dashboard.php') ? 'text-white border-purple-500' : 'border-gray-200 hover:text-white text-gray-700'; ?>"
             style="<?php echo ($current_page == 'dashboard.php') ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' : 'transition: all 0.3s ease;'; ?>"
             <?php if($current_page != 'dashboard.php'): ?>
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';"
             <?php endif; ?>>
            <i data-feather="home" width="20"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li>
          <a href="../admin/manage_expenses.php"
             class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border <?php echo ($current_page == 'manage_expenses.php') ? 'text-white border-purple-500' : 'border-gray-200 hover:text-white text-gray-700'; ?>"
             style="<?php echo ($current_page == 'manage_expenses.php') ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' : 'transition: all 0.3s ease;'; ?>"
             <?php if($current_page != 'manage_expenses.php'): ?>
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';"
             <?php endif; ?>>
            <i data-feather="dollar-sign" width="20"></i>
            <span>Manage Expenses</span>
          </a>
        </li>

        <li>
          <a href="../admin/summary_report.php"
             class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border <?php echo ($current_page == 'summary_report.php') ? 'text-white border-purple-500' : 'border-gray-200 hover:text-white text-gray-700'; ?>"
             style="<?php echo ($current_page == 'summary_report.php') ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' : 'transition: all 0.3s ease;'; ?>"
             <?php if($current_page != 'summary_report.php'): ?>
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';"
             <?php endif; ?>>
            <i data-feather="pie-chart" width="20"></i>
            <span>Reports & Summary</span>
          </a>
        </li>

        <li>
          <a href="../admin/categories.php"
             class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border <?php echo ($current_page == 'categories.php') ? 'text-white border-purple-500' : 'border-gray-200 hover:text-white text-gray-700'; ?>"
             style="<?php echo ($current_page == 'categories.php') ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' : 'transition: all 0.3s ease;'; ?>"
             <?php if($current_page != 'categories.php'): ?>
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';"
             <?php endif; ?>>
            <i data-feather="file-text" width="20"></i>
            <span>Categories</span>
          </a>
        </li>
      </ul>

      <!-- Settings -->
      <p class="text-xs text-gray-500 px-3 mt-6 mb-2 font-semibold">OTHERS</p>
      <ul>
        <li>
          <a href="../admin/settings.php"
             class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border <?php echo ($current_page == 'settings.php') ? 'text-white border-purple-500' : 'border-gray-200 hover:text-white text-gray-700'; ?>"
             style="<?php echo ($current_page == 'settings.php') ? 'background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);' : 'transition: all 0.3s ease;'; ?>"
             <?php if($current_page != 'settings.php'): ?>
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';"
             <?php endif; ?>>
            <i data-feather="settings" width="20"></i>
            <span>Settings</span>
          </a>
        </li>
      </ul>

      <!-- Others -->
      <p class="text-xs text-gray-500 px-3 mt-6 mb-2 font-semibold">SETTINGS</p>
      <ul>
        <li>
          <a href="#!" onclick="return alert('About this System\n\nSystem Name: FinTrack\nDeveloper: (Student Name)\nContact No: (Contact No.)\nEmail: (Email)\n\n© 2025 Software Solutions. All rights reserved.');"
             class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border border-gray-200 hover:text-white text-gray-700"
             style="transition: all 0.3s ease;"
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';">
            <i data-feather="info" width="20"></i>
            <span>About</span>
          </a>
        </li>

        <li>
          <a href="logout.php" class="w-full flex items-center gap-3 px-3 py-2.5 rounded-lg mb-1 transition-all border border-gray-200 hover:text-white text-gray-700"
             style="transition: all 0.3s ease;"
             onmouseover="this.style.background='linear-gradient(135deg, #667eea 0%, #764ba2 100%)'; this.style.borderColor='#667eea';"
             onmouseout="this.style.background=''; this.style.borderColor='#d1d5db';"
             onclick="return confirm('Do you really want to Log-Out?')">
            <i data-feather="log-out" width="20"></i>
            <span>Log-Out</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- [ Sidebar Menu ] end -->