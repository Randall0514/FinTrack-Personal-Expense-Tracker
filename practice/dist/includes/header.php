<?php
// JWT Authentication for Header
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
$userData['email'] = $userData['email'] ?? 'user@example.com';

// ===================== NOTIFICATION SYSTEM =====================
$notifications = [];
$unreadCount = 0;

// Fetch user's budget data
try {
    $budget_query = $conn->prepare("SELECT daily_budget, weekly_budget, monthly_budget FROM users WHERE id = ?");
    if ($budget_query) {
        $budget_query->bind_param("i", $user_id);
        $budget_query->execute();
        $budget_result = $budget_query->get_result();
        
        if ($budget_result->num_rows > 0) {
            $budget_data = $budget_result->fetch_assoc();
            $dailyBudget = $budget_data['daily_budget'] ?? 500;
            $weeklyBudget = $budget_data['weekly_budget'] ?? 3000;
            $monthlyBudget = $budget_data['monthly_budget'] ?? 10000;
        }
        $budget_query->close();
    }
} catch (Exception $e) {
    $dailyBudget = 500;
    $weeklyBudget = 3000;
    $monthlyBudget = 10000;
}

// Calculate spending
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $expenses = [];
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expenses[] = $row;
        }
    }
    $stmt->close();
}

$dailySpending = 0;
$weeklySpending = 0;
$monthlySpending = 0;
$today = date('Y-m-d');
$currentMonth = date('Y-m');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

foreach ($expenses as $expense) {
    $expenseDate = $expense['date'];
    $amount = floatval($expense['amount']);
    
    if ($expenseDate == $today) {
        $dailySpending += $amount;
    }
    if ($expenseDate >= $weekStart && $expenseDate <= $weekEnd) {
        $weeklySpending += $amount;
    }
    if (substr($expenseDate, 0, 7) == $currentMonth) {
        $monthlySpending += $amount;
    }
}

// Generate notifications based on spending
$dailyPercentage = $dailyBudget > 0 ? ($dailySpending / $dailyBudget) * 100 : 0;
$weeklyPercentage = $weeklyBudget > 0 ? ($weeklySpending / $weeklyBudget) * 100 : 0;
$monthlyPercentage = $monthlyBudget > 0 ? ($monthlySpending / $monthlyBudget) * 100 : 0;

// Daily budget notifications
if ($dailySpending > $dailyBudget) {
    $notifications[] = [
        'icon' => 'alert-circle',
        'color' => 'red',
        'title' => 'Daily Budget Exceeded!',
        'message' => 'You have exceeded your daily budget by ₱' . number_format($dailySpending - $dailyBudget, 2),
        'time' => 'Just now',
        'type' => 'danger'
    ];
} elseif ($dailyPercentage >= 90) {
    $notifications[] = [
        'icon' => 'alert-triangle',
        'color' => 'orange',
        'title' => 'Daily Budget Warning',
        'message' => 'You have used ' . number_format($dailyPercentage, 1) . '% of your daily budget',
        'time' => 'Just now',
        'type' => 'warning'
    ];
}

// Weekly budget notifications
if ($weeklySpending > $weeklyBudget) {
    $notifications[] = [
        'icon' => 'alert-circle',
        'color' => 'red',
        'title' => 'Weekly Budget Exceeded!',
        'message' => 'You have exceeded your weekly budget by ₱' . number_format($weeklySpending - $weeklyBudget, 2),
        'time' => 'Today',
        'type' => 'danger'
    ];
} elseif ($weeklyPercentage >= 80) {
    $notifications[] = [
        'icon' => 'alert-triangle',
        'color' => 'orange',
        'title' => 'Weekly Budget Alert',
        'message' => 'You have used ' . number_format($weeklyPercentage, 1) . '% of your weekly budget',
        'time' => 'Today',
        'type' => 'warning'
    ];
}

// Monthly budget notifications
if ($monthlySpending > $monthlyBudget) {
    $notifications[] = [
        'icon' => 'alert-circle',
        'color' => 'red',
        'title' => 'Monthly Budget Exceeded!',
        'message' => 'You have exceeded your monthly budget by ₱' . number_format($monthlySpending - $monthlyBudget, 2),
        'time' => date('M d'),
        'type' => 'danger'
    ];
} elseif ($monthlyPercentage >= 75) {
    $notifications[] = [
        'icon' => 'info',
        'color' => 'blue',
        'title' => 'Monthly Budget Info',
        'message' => 'You have used ' . number_format($monthlyPercentage, 1) . '% of your monthly budget',
        'time' => date('M d'),
        'type' => 'info'
    ];
}

// Recent expense notification (last 24 hours)
if (!empty($expenses)) {
    $latestExpense = $expenses[0];
    $expenseTime = strtotime($latestExpense['date']);
    $currentTime = time();
    $timeDiff = $currentTime - $expenseTime;
    
    if ($timeDiff < 86400) { // Less than 24 hours
        $hours = floor($timeDiff / 3600);
        $timeAgo = $hours > 0 ? $hours . ' hours ago' : 'Just now';
        
        $notifications[] = [
            'icon' => 'check-circle',
            'color' => 'green',
            'title' => 'Expense Added',
            'message' => '₱' . number_format($latestExpense['amount'], 2) . ' - ' . $latestExpense['category'],
            'time' => $timeAgo,
            'type' => 'success'
        ];
    }
}

$unreadCount = count($notifications);
?>

<!-- [ Header Topbar ] start -->
<header class="pc-header">
  <div class="header-wrapper flex max-sm:px-[15px] px-[25px] grow">
    <!-- [Mobile Media Block] start -->
    <div class="me-auto pc-mob-drp">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        <!-- ======= Menu collapse Icon ===== -->
        <li class="pc-h-item pc-sidebar-collapse max-lg:hidden lg:inline-flex">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="sidebar-hide">
            <i data-feather="menu"></i>
          </a>
        </li>
        <li class="pc-h-item pc-sidebar-popup lg:hidden">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0" id="mobile-collapse">
            <i data-feather="menu"></i>
          </a>
        </li>
        <li class="dropdown pc-h-item">
          <a class="pc-head-link dropdown-toggle me-0" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" aria-expanded="false">
            <i data-feather="search"></i>
          </a>
          <div class="dropdown-menu pc-h-dropdown drp-search">
            <form class="px-2 py-1">
              <input type="search" class="form-control !border-0 !shadow-none" placeholder="Search here. . ." />
            </form>
          </div>
        </li>
      </ul>
    </div>
    <!-- [Mobile Media Block end] -->

    <div class="ms-auto">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        
        <!-- ================= NOTIFICATION DROPDOWN ================= -->
        <li class="dropdown pc-h-item">
          <a class="pc-head-link dropdown-toggle me-0 relative" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" aria-expanded="false">
            <i data-feather="bell"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="notification-badge">
              <?php echo $unreadCount; ?>
            </span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-menu-end pc-h-dropdown p-0 overflow-hidden shadow-2xl notification-dropdown">
            <!-- Notification Header -->
            <div class="notification-header px-6 py-4 text-white" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
              <div class="flex items-center justify-between mb-3">
                <div class="flex items-center gap-3">
                  <div class="w-10 h-10 bg-white bg-opacity-20 rounded-lg flex items-center justify-center backdrop-blur-sm">
                    <i data-feather="bell" class="w-5 h-5"></i>
                  </div>
                  <div>
                    <h4 class="text-base font-bold mb-0">Notifications</h4>
                    <p class="text-xs opacity-90 mb-0">Stay updated with your spending</p>
                  </div>
                </div>
                <?php if ($unreadCount > 0): ?>
                <span class="bg-white text-purple-600 px-3 py-1.5 rounded-full text-xs font-bold shadow-lg">
                  <?php echo $unreadCount; ?> New
                </span>
                <?php endif; ?>
              </div>
              
              <!-- View All Notifications Button -->
              <a href="../admin/notifications.php?view=all" class="view-all-btn">
                <i data-feather="list" class="w-4 h-4"></i>
                View All Notifications
              </a>
            </div>
            
            <!-- Notification Body -->
            <div class="notification-body max-h-[500px] overflow-y-auto bg-gray-50">
              <?php if (!empty($notifications)): ?>
                <div class="p-3 space-y-2">
                  <?php foreach ($notifications as $index => $notification): ?>
                  <div class="notification-card bg-white rounded-xl p-4 hover:shadow-lg transition-all duration-300 border border-gray-100 hover:border-purple-200">
                    <div class="flex items-start gap-4">
                      <!-- Icon -->
                      <div class="shrink-0">
                        <div class="w-14 h-14 rounded-xl flex items-center justify-center shadow-md notification-icon-<?php echo $notification['type']; ?>" 
                             style="background: linear-gradient(135deg, <?php echo $notification['color'] == 'red' ? '#ef4444, #dc2626' : ($notification['color'] == 'orange' ? '#f59e0b, #d97706' : ($notification['color'] == 'green' ? '#10b981, #059669' : '#3b82f6, #2563eb')); ?>);">
                          <i data-feather="<?php echo $notification['icon']; ?>" class="w-6 h-6 text-white"></i>
                        </div>
                      </div>
                      
                      <!-- Content -->
                      <div class="grow">
                        <div class="flex items-start justify-between mb-2">
                          <h6 class="text-sm font-bold text-gray-800 leading-tight">
                            <?php echo htmlspecialchars($notification['title']); ?>
                          </h6>
                          <span class="text-xs text-gray-400 whitespace-nowrap ml-3 bg-gray-100 px-2 py-1 rounded-md">
                            <i data-feather="clock" class="w-3 h-3 inline mb-0.5"></i>
                            <?php echo htmlspecialchars($notification['time']); ?>
                          </span>
                        </div>
                        <p class="text-sm text-gray-600 leading-relaxed mb-2">
                          <?php echo htmlspecialchars($notification['message']); ?>
                        </p>
                        
                        <!-- Action buttons for specific notification types -->
                        <?php if ($notification['type'] == 'danger' || $notification['type'] == 'warning'): ?>
                        <div class="flex gap-2 mt-3">
                          <a href="../admin/manage_expenses.php" class="text-xs bg-purple-50 text-purple-600 px-3 py-1.5 rounded-lg hover:bg-purple-100 transition-colors font-semibold">
                            <i data-feather="eye" class="w-3 h-3 inline mb-0.5"></i>
                            View Expenses
                          </a>
                          <a href="../admin/settings.php" class="text-xs bg-gray-100 text-gray-600 px-3 py-1.5 rounded-lg hover:bg-gray-200 transition-colors font-semibold">
                            <i data-feather="settings" class="w-3 h-3 inline mb-0.5"></i>
                            Adjust Budget
                          </a>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="px-6 py-12 text-center">
                  <div class="w-20 h-20 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
                    <i data-feather="bell-off" class="w-10 h-10 text-gray-300"></i>
                  </div>
                  <h5 class="text-gray-700 font-semibold mb-2">All Caught Up! 🎉</h5>
                  <p class="text-gray-500 text-sm mb-1">No new notifications</p>
                  <p class="text-gray-400 text-xs">We'll notify you when something important happens</p>
                </div>
              <?php endif; ?>
            </div>
            
            <!-- Notification Footer -->
            <?php if (!empty($notifications)): ?>
            <div class="notification-footer text-center py-4 border-t border-gray-200 bg-white">
              <div class="flex items-center justify-center gap-4">
                <button onclick="markAllAsRead()" class="mark-read-btn">
                  <i data-feather="check-double" class="w-4 h-4"></i>
                  Mark All as Read
                </button>
              </div>
            </div>
            <?php endif; ?>
          </div>
        </li>
        <!-- ================= END NOTIFICATION DROPDOWN ================= -->

        <!-- dark/light mode toggle -->
        <li class="dropdown pc-h-item">
          <a class="pc-head-link dropdown-toggle me-0" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" aria-expanded="false">
            <i data-feather="sun"></i>
          </a>
          <div class="dropdown-menu dropdown-menu-end pc-h-dropdown">
            <a href="#!" class="dropdown-item" onclick="layout_change('dark')">
              <i data-feather="moon"></i>
              <span>Dark</span>
            </a>
            <a href="#!" class="dropdown-item" onclick="layout_change('light')">
              <i data-feather="sun"></i>
              <span>Light</span>
            </a>
            <a href="#!" class="dropdown-item" onclick="layout_change_default()">
              <i data-feather="settings"></i>
              <span>Default</span>
            </a>
          </div>
        </li>

        <!-- User Profile Dropdown -->
        <li class="dropdown pc-h-item header-user-profile">
          <a class="pc-head-link dropdown-toggle arrow-none me-0" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" data-pc-auto-close="outside" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" 
                 alt="user-image" 
                 class="w-8 h-8 rounded-full object-cover border-2 border-purple-300" />
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown p-2 overflow-hidden">
            <div class="dropdown-header flex items-center justify-between py-4 px-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
              <div class="flex mb-1 items-center">
                <div class="shrink-0">
                  <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" 
                       alt="user-image" 
                       class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md" />
                </div>
                <div class="grow ms-4">
                  <h4 class="mb-1 text-white font-semibold"><?php echo htmlspecialchars($userData['fullname']); ?></h4>
                  <span class="text-white text-sm opacity-90"><?php echo htmlspecialchars($userData['email']); ?></span>
                </div>
              </div>
            </div>
            <div class="dropdown-body py-4 px-5">
              <div class="profile-notification-scroll position-relative" style="max-height: calc(100vh - 225px)">
                
                <a href="../admin/profile.php" class="dropdown-item flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-purple-50 transition-all">
                  <i data-feather="user" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-gray-700 font-medium">My Profile</span>
                </a>
                
                <a href="../admin/settings.php" class="dropdown-item flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-purple-50 transition-all">
                  <i data-feather="settings" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-gray-700 font-medium">Settings</span>
                </a>
                
                <a href="#" class="dropdown-item flex items-center gap-3 px-3 py-2 rounded-lg hover:bg-purple-50 transition-all" onclick="return alert('About this System\n\nSystem Name: FinTrack\nDeveloper: (Student Name)\nContact No: (Contact No.)\nEmail: (Email)\n\n© 2025 Software Solutions. All rights reserved.');">
                  <i data-feather="info" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-gray-700 font-medium">About</span>
                </a>
                
                <hr class="my-3 border-gray-200">
                
                <div class="grid">
                  <a href="logout.php" 
                     class="btn flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-white font-semibold transition-all"
                     style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"
                     onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(102, 126, 234, 0.4)';"
                     onmouseout="this.style.transform=''; this.style.boxShadow='';"
                     onclick="return confirm('Do you really want to Log-Out?')">
                    <i data-feather="log-out" class="w-4 h-4"></i>
                    Log-Out
                  </a>
                </div>
              </div>
            </div>
          </div>
        </li>
        <!-- User Profile Dropdown end -->
      </ul>
    </div>
  </div>
</header>
<!-- [ Header ] end -->

<style>
/* Notification Badge - Circle positioned to the side */
.notification-badge {
  position: absolute;
  top: 8px;
  right: -8px;
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  font-size: 11px;
  font-weight: 700;
  min-width: 20px;
  height: 20px;
  border-radius: 50%;
  display: flex;
  align-items: center;
  justify-content: center;
  padding: 0 4px;
  box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4), 0 0 0 3px rgba(255, 255, 255, 0.9);
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
  z-index: 10;
}

/* Notification Dropdown Width */
.notification-dropdown {
  width: 480px !important;
  max-width: 95vw !important;
  border-radius: 16px !important;
  border: none !important;
}

/* Notification Header Styling */
.notification-header {
  border-radius: 16px 16px 0 0 !important;
  box-shadow: 0 4px 6px rgba(102, 126, 234, 0.1);
}

/* View All Notifications Button */
.view-all-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  padding: 10px 16px;
  background: rgba(255, 255, 255, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 10px;
  color: white;
  font-size: 0.875rem;
  font-weight: 600;
  text-decoration: none;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
}

.view-all-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  color: white;
}

.view-all-btn i {
  transition: transform 0.3s ease;
}

.view-all-btn:hover i {
  transform: translateX(3px);
}

/* Mark as Read Button */
.mark-read-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 20px;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white;
  border: none;
  border-radius: 8px;
  font-size: 0.875rem;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.3s ease;
}

.mark-read-btn:hover {
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
}

.mark-read-btn:active {
  transform: translateY(0);
}

.mark-read-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}

.mark-read-btn i {
  transition: transform 0.3s ease;
}

.mark-read-btn:hover i {
  transform: scale(1.1);
}

/* Notification Body Styling */
.notification-body {
  background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
}

.notification-body::-webkit-scrollbar {
  width: 6px;
}

.notification-body::-webkit-scrollbar-track {
  background: #f1f1f1;
  border-radius: 10px;
}

.notification-body::-webkit-scrollbar-thumb {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  border-radius: 10px;
}

.notification-body::-webkit-scrollbar-thumb:hover {
  background: linear-gradient(135deg, #5568d3 0%, #653a8b 100%);
}

/* Notification Card Styling */
.notification-card {
  position: relative;
  overflow: hidden;
  cursor: pointer;
  transform: scale(1);
  animation: fadeInUp 0.4s ease-out;
}

.notification-card::before {
  content: '';
  position: absolute;
  left: 0;
  top: 0;
  width: 4px;
  height: 100%;
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  transform: scaleY(0);
  transition: transform 0.3s ease;
}

.notification-card:hover::before {
  transform: scaleY(1);
}

.notification-card:hover {
  transform: translateX(4px);
  border-left: 4px solid transparent;
}

/* Notification Icon Animation */
.notification-icon-danger {
  animation: shake 0.5s ease-in-out;
}

.notification-icon-warning {
  animation: bounce 0.6s ease-in-out;
}

.notification-icon-success {
  animation: checkmark 0.5s ease-in-out;
}

.notification-icon-info {
  animation: fadeIn 0.5s ease-in-out;
}

/* Notification Footer */
.notification-footer {
  border-radius: 0 0 16px 16px !important;
  box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.05);
}

/* Pulse Animation for Badge */
@keyframes pulse {
  0%, 100% {
    opacity: 1;
    transform: scale(1);
  }
  50% {
    opacity: .9;
    transform: scale(1.15);
  }
}

.animate-pulse {
  animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

/* Smooth dropdown animations */
.dropdown-menu {
  animation: slideDown 0.3s ease-out;
}

@keyframes slideDown {
  from {
    opacity: 0;
    transform: translateY(-20px) scale(0.95);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}

@keyframes fadeInUp {
  from {
    opacity: 0;
    transform: translateY(10px);
  }
  to {
    opacity: 1;
    transform: translateY(0);
  }
}

@keyframes shake {
  0%, 100% { transform: rotate(0deg); }
  25% { transform: rotate(-5deg); }
  75% { transform: rotate(5deg); }
}

@keyframes bounce {
  0%, 100% { transform: translateY(0); }
  50% { transform: translateY(-10px); }
}

@keyframes checkmark {
  0% { transform: scale(0.5); opacity: 0; }
  50% { transform: scale(1.2); }
  100% { transform: scale(1); opacity: 1; }
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

/* Responsive Design */
@media (max-width: 640px) {
  .notification-dropdown {
    width: 95vw !important;
    left: 2.5vw !important;
    right: 2.5vw !important;
  }
  
  .notification-card {
    padding: 12px !important;
  }
  
  .notification-body {
    max-height: 400px !important;
  }
  
  .notification-badge {
    right: -6px;
    min-width: 18px;
    height: 18px;
    font-size: 10px;
  }
}

/* Hover effects for action buttons */
.notification-card a {
  transition: all 0.2s ease;
}

.notification-card a:hover {
  transform: translateY(-1px);
  box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
}
</style>

<script>
// Mark all notifications as read
function markAllAsRead() {
  const btn = event.target.closest('button');
  const originalHTML = btn.innerHTML;
  
  // Show loading state
  btn.disabled = true;
  btn.innerHTML = '<i data-feather="loader" class="w-4 h-4 icon-loader"></i> Processing...';
  
  // Reinitialize feather icons for the loader
  if (typeof feather !== 'undefined') {
    feather.replace();
  }
  
  // Send AJAX request to mark all as read
  fetch('../admin/mark_notifications_read.php', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/x-www-form-urlencoded',
    },
    body: 'action=mark_all_read'
  })
  .then(response => response.json())
  .then(data => {
    if (data.success) {
      // Show success message
      btn.innerHTML = '<i data-feather="check" class="w-4 h-4"></i> Marked as Read!';
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
      
      // Animate notifications out
      const notifications = document.querySelectorAll('.notification-card');
      notifications.forEach((notification, index) => {
        setTimeout(() => {
          notification.style.transition = 'all 0.4s ease-out';
          notification.style.opacity = '0';
          notification.style.transform = 'translateX(-20px)';
        }, index * 50);
      });
      
      // Reload page after animation
      setTimeout(() => {
        location.reload();
      }, notifications.length * 50 + 500);
    } else {
      alert('❌ Error: ' + data.message);
      btn.disabled = false;
      btn.innerHTML = originalHTML;
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    }
  })
  .catch(error => {
    console.error('Error:', error);
    alert('❌ An error occurred while marking notifications as read');
    btn.disabled = false;
    btn.innerHTML = originalHTML;
    if (typeof feather !== 'undefined') {
      feather.replace();
    }
  });
}

// Auto-refresh notification count every 60 seconds
setInterval(function() {
  // Add AJAX call here to check for new notifications
  // Example: fetch('check_notifications.php').then(...)
}, 60000);

// Spinning loader animation
const style = document.createElement('style');
style.textContent = `
  .icon-loader {
    animation: spin 1s linear infinite;
  }
  @keyframes spin {
    to { transform: rotate(360deg); }
  }
`;
document.head.appendChild(style);
</script>