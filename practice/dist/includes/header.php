<?php
// JWT Authentication for Header
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

// Include notification helper if it exists, otherwise define functions inline
$helper_path = __DIR__ . '/notification_helper.php';
if (file_exists($helper_path)) {
    include $helper_path;
} else {
    // Inline helper functions if file doesn't exist
    function isNotificationRead($conn, $user_id, $notification_key) {
        $create_table = "CREATE TABLE IF NOT EXISTS notifications_read (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_key VARCHAR(255) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            UNIQUE KEY unique_read_notification (user_id, notification_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        @$conn->query($create_table);
        
        @$conn->query("DELETE FROM notifications_read WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        
        $check_query = @$conn->prepare("SELECT id FROM notifications_read 
            WHERE user_id = ? AND notification_key = ? AND (expires_at IS NULL OR expires_at > NOW())");
        
        if ($check_query) {
            $check_query->bind_param("is", $user_id, $notification_key);
            $check_query->execute();
            $result = $check_query->get_result();
            $is_read = $result->num_rows > 0;
            $check_query->close();
            return $is_read;
        }
        return false;
    }
    
    function isNotificationDismissed($conn, $user_id, $notification_key) {
        $create_table = "CREATE TABLE IF NOT EXISTS notifications_dismissed (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            notification_key VARCHAR(255) NOT NULL,
            dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            UNIQUE KEY unique_notification (user_id, notification_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        @$conn->query($create_table);
        
        @$conn->query("DELETE FROM notifications_dismissed WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        
        $check_query = @$conn->prepare("SELECT id FROM notifications_dismissed 
            WHERE user_id = ? AND notification_key = ? AND (expires_at IS NULL OR expires_at > NOW())");
        
        if ($check_query) {
            $check_query->bind_param("is", $user_id, $notification_key);
            $check_query->execute();
            $result = $check_query->get_result();
            $is_dismissed = $result->num_rows > 0;
            $check_query->close();
            return $is_dismissed;
        }
        return false;
    }
    
    function countUnreadNotifications($conn, $user_id, $all_notification_keys) {
        $unread_count = 0;
        foreach ($all_notification_keys as $key) {
            if (!isNotificationRead($conn, $user_id, $key)) {
                $unread_count++;
            }
        }
        return $unread_count;
    }
}

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
$notification_keys = [];

// Fetch user's budget data
$dailyBudget = 500;
$weeklyBudget = 3000;
$monthlyBudget = 10000;

try {
    $budget_query = $conn->prepare("SELECT daily_budget, weekly_budget, monthly_budget FROM users WHERE id = ?");
    if ($budget_query) {
        $budget_query->bind_param("i", $user_id);
        $budget_query->execute();
        $budget_result = $budget_query->get_result();
        
        if ($budget_result->num_rows > 0) {
            $budget_data = $budget_result->fetch_assoc();
            $dailyBudget = floatval($budget_data['daily_budget'] ?? 500);
            $weeklyBudget = floatval($budget_data['weekly_budget'] ?? 3000);
            $monthlyBudget = floatval($budget_data['monthly_budget'] ?? 10000);
        }
        $budget_query->close();
    }
} catch (Exception $e) {
    error_log("Budget fetch error: " . $e->getMessage());
}

// Calculate spending
$expenses = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND archived = 0 ORDER BY date DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expenses[] = $row;
        }
    }
    $stmt->close();
}

// Initialize spending variables
$dailySpending = 0;
$weeklySpending = 0;
$monthlySpending = 0;

// Get date ranges
$today = date('Y-m-d');
$currentMonth = date('Y-m');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

// Calculate spending for each period
foreach ($expenses as $expense) {
    $expenseDate = $expense['date'];
    $amount = floatval($expense['amount']);
    
    // Daily spending (today only)
    if ($expenseDate == $today) {
        $dailySpending += $amount;
    }
    
    // Weekly spending (this week)
    if ($expenseDate >= $weekStart && $expenseDate <= $weekEnd) {
        $weeklySpending += $amount;
    }
    
    // Monthly spending (this month)
    if (substr($expenseDate, 0, 7) == $currentMonth) {
        $monthlySpending += $amount;
    }
}

// Calculate percentages
$dailyPercentage = $dailyBudget > 0 ? ($dailySpending / $dailyBudget) * 100 : 0;
$weeklyPercentage = $weeklyBudget > 0 ? ($weeklySpending / $weeklyBudget) * 100 : 0;
$monthlyPercentage = $monthlyBudget > 0 ? ($monthlySpending / $monthlyBudget) * 100 : 0;

// ==================== DAILY BUDGET NOTIFICATIONS ====================
// Only show ONE notification per period based on highest severity
if ($dailySpending > $dailyBudget) {
    // EXCEEDED (over 100%) - Highest priority
    $key = 'daily_budget_exceeded_' . $today;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'alert-circle',
            'color' => 'red',
            'title' => 'Daily Budget Exceeded!',
            'message' => 'You have exceeded your daily budget by ₱' . number_format($dailySpending - $dailyBudget, 2) . ' (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
            'time' => 'Just now',
            'type' => 'danger',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
} elseif ($dailyPercentage >= 80) {
    // WARNING (80-100%)
    $key = 'daily_budget_warning_' . $today;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'alert-triangle',
            'color' => 'orange',
            'title' => 'Daily Budget Warning',
            'message' => 'You have used ' . number_format($dailyPercentage, 1) . '% of your daily budget (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
            'time' => 'Just now',
            'type' => 'warning',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
} elseif ($dailyPercentage >= 60) {
    // INFO (60-79%)
    $key = 'daily_budget_info_' . $today;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'info',
            'color' => 'blue',
            'title' => 'Daily Budget Update',
            'message' => 'You have used ' . number_format($dailyPercentage, 1) . '% of your daily budget (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
            'time' => 'Just now',
            'type' => 'info',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
}

// ==================== WEEKLY BUDGET NOTIFICATIONS ====================
// Only show ONE notification per period based on highest severity
if ($weeklySpending > $weeklyBudget) {
    // EXCEEDED
    $key = 'weekly_budget_exceeded_' . $weekStart;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'alert-circle',
            'color' => 'red',
            'title' => 'Weekly Budget Exceeded!',
            'message' => 'You have exceeded your weekly budget by ₱' . number_format($weeklySpending - $weeklyBudget, 2) . ' (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
            'time' => 'Today',
            'type' => 'danger',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
} elseif ($weeklyPercentage >= 80) {
    // WARNING (80-100%)
    $key = 'weekly_budget_warning_' . $weekStart;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'alert-triangle',
            'color' => 'orange',
            'title' => 'Weekly Budget Alert',
            'message' => 'You have used ' . number_format($weeklyPercentage, 1) . '% of your weekly budget (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
            'time' => 'Today',
            'type' => 'warning',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
} elseif ($weeklyPercentage >= 60) {
    // INFO (60-79%)
    $key = 'weekly_budget_info_' . $weekStart;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'info',
            'color' => 'blue',
            'title' => 'Weekly Budget Update',
            'message' => 'You have used ' . number_format($weeklyPercentage, 1) . '% of your weekly budget (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
            'time' => 'Today',
            'type' => 'info',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
}

// ==================== MONTHLY BUDGET NOTIFICATIONS ====================
// Only show ONE notification per period based on highest severity
if ($monthlySpending > $monthlyBudget) {
    // EXCEEDED
    $key = 'monthly_budget_exceeded_' . $currentMonth;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'alert-circle',
            'color' => 'red',
            'title' => 'Monthly Budget Exceeded!',
            'message' => 'You have exceeded your monthly budget by ₱' . number_format($monthlySpending - $monthlyBudget, 2) . ' (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
            'time' => date('M d'),
            'type' => 'danger',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
} elseif ($monthlyPercentage >= 80) {
    // WARNING (80-100%)
    $key = 'monthly_budget_warning_' . $currentMonth;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'alert-triangle',
            'color' => 'orange',
            'title' => 'Monthly Budget Warning',
            'message' => 'You have used ' . number_format($monthlyPercentage, 1) . '% of your monthly budget (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
            'time' => date('M d'),
            'type' => 'warning',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
} elseif ($monthlyPercentage >= 60) {
    // INFO (60-79%)
    $key = 'monthly_budget_info_' . $currentMonth;
    $isDismissed = isNotificationDismissed($conn, $user_id, $key);
    $isRead = isNotificationRead($conn, $user_id, $key);
    
    if (!$isDismissed) {
        $notification_keys[] = $key;
        $notifications[] = [
            'icon' => 'info',
            'color' => 'blue',
            'title' => 'Monthly Budget Info',
            'message' => 'You have used ' . number_format($monthlyPercentage, 1) . '% of your monthly budget (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
            'time' => date('M d'),
            'type' => 'info',
            'key' => $key,
            'is_read' => $isRead
        ];
    }
}

// ==================== RECENT EXPENSE NOTIFICATIONS (ONLY ONCE) ====================
$sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
$expenseCount = 0;
$maxExpensesToShow = 5;

foreach ($expenses as $expense) {
    if ($expenseCount >= $maxExpensesToShow) break;
    
    $expenseDate = $expense['date'];
    if ($expenseDate >= $sevenDaysAgo) {
        $expenseTime = strtotime($expense['date']);
        $currentTime = time();
        $timeDiff = $currentTime - $expenseTime;
        
        $timeAgo = ''; 
        if ($timeDiff < 3600) {
            $minutes = floor($timeDiff / 60);
            $timeAgo = $minutes <= 1 ? 'Just now' : $minutes . ' min ago';
        } elseif ($timeDiff < 86400) {
            $hours = floor($timeDiff / 3600);
            $timeAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
        } else {
            $days = floor($timeDiff / 86400);
            $timeAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
        }
        
        $key = 'expense_' . $expense['id'];
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notification_keys[] = $key;
            $notifications[] = [
                'icon' => 'shopping-cart',
                'color' => 'green',
                'title' => 'Expense Recorded',
                'message' => '₱' . number_format($expense['amount'], 2) . ' - ' . $expense['category'],
                'time' => $timeAgo,
                'type' => 'success',
                'key' => $key,
                'is_read' => isNotificationRead($conn, $user_id, $key)
            ];
            $expenseCount++;
        }
    }
}

// Count UNREAD notifications
$unreadCount = countUnreadNotifications($conn, $user_id, $notification_keys);

// Format badge display (show "9+" if more than 9)
$badgeDisplay = $unreadCount > 9 ? '9+' : $unreadCount;
?>

<!-- [ Header Topbar ] start -->
<header class="pc-header" style="background: white; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
  <div class="header-wrapper flex max-sm:px-[15px] px-[25px] grow items-center">
    <!-- [Mobile Media Block] start -->
    <div class="me-auto pc-mob-drp">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        <!-- ======= Menu collapse Icon ===== -->
        <li class="pc-h-item pc-sidebar-collapse max-lg:hidden lg:inline-flex">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0 hover:bg-gray-100 rounded-lg transition-all" id="sidebar-hide">
            <i data-feather="menu" class="text-gray-700"></i>
          </a>
        </li>
        <li class="pc-h-item pc-sidebar-popup lg:hidden">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0 hover:bg-gray-100 rounded-lg transition-all" id="mobile-collapse">
            <i data-feather="menu" class="text-gray-700"></i>
          </a>
        </li>
      </ul>
    </div>
    <!-- [Mobile Media Block end] -->

    <div class="ms-auto">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        
        <!-- ================= NOTIFICATION DROPDOWN ================= -->
        <li class="dropdown pc-h-item" id="notification-dropdown">
          <a class="pc-head-link dropdown-toggle me-0 relative" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" aria-expanded="false">
            <i data-feather="bell"></i>
            <?php if ($unreadCount > 0): ?>
            <span class="notification-badge">
              <?php echo $badgeDisplay; ?>
            </span>
            <?php endif; ?>
          </a>
          <div class="dropdown-menu dropdown-menu-end pc-h-dropdown p-0 overflow-hidden shadow-2xl notification-dropdown">
            <!-- Notification Header -->
            <div class="notification-header">
              <div class="notification-header-top">
                <div class="notification-header-title">
                  <div class="notification-header-icon">
                    <i data-feather="bell"></i>
                  </div>
                  <div class="notification-header-text">
                    <h3>Notifications</h3>
                    <p>Stay updated with your activity</p>
                  </div>
                </div>
                <?php if ($unreadCount > 0): ?>
                <span class="notification-count-badge">
                  <?php echo $unreadCount; ?> New
                </span>
                <?php endif; ?>
              </div>
              
              <!-- Action Buttons -->
              <div class="notification-header-actions">
                <a href="../admin/notifications.php?view=all" class="view-all-btn">
                  <i data-feather="list"></i>
                  View All Notifications
                </a>
                <?php if (!empty($notifications) && $unreadCount > 0): ?>
                <button onclick="markAllAsRead(event)" class="mark-read-btn-header">
                  <i data-feather="check-circle"></i>
                  Mark All as Read
                </button>
                <?php endif; ?>
              </div>
            </div>
            
            <!-- Notification Body -->
            <div class="notification-body">
              <?php if (!empty($notifications)): ?>
                <div class="notification-list">
                  <?php foreach ($notifications as $index => $notification): ?>
                  <div class="notification-item <?php echo !$notification['is_read'] ? 'unread' : ''; ?>">
                    <div class="notification-icon <?php echo $notification['type']; ?>">
                      <i data-feather="<?php echo $notification['icon']; ?>"></i>
                    </div>
                    <div class="notification-content">
                      <div class="notification-header-row">
                        <h4 class="notification-title"><?php echo htmlspecialchars($notification['title']); ?></h4>
                        <?php if (!$notification['is_read']): ?>
                        <span class="unread-indicator"></span>
                        <?php endif; ?>
                      </div>
                      <p class="notification-message">
                        <?php echo htmlspecialchars($notification['message']); ?>
                      </p>
                      <div class="notification-footer-row">
                        <span class="notification-time">
                          <i data-feather="clock"></i>
                          <?php echo htmlspecialchars($notification['time']); ?>
                        </span>
                        <?php if ($notification['type'] == 'danger' || $notification['type'] == 'warning'): ?>
                        <div class="notification-actions">
                          <a href="../admin/manage_expenses.php" class="notification-action-btn primary">
                            <i data-feather="eye"></i>
                            View
                          </a>
                          <a href="../admin/settings.php" class="notification-action-btn secondary">
                            <i data-feather="settings"></i>
                            Adjust
                          </a>
                        </div>
                        <?php endif; ?>
                      </div>
                    </div>
                  </div>
                  <?php endforeach; ?>
                </div>
              <?php else: ?>
                <div class="notification-empty">
                  <div class="notification-empty-icon">
                    <i data-feather="bell-off"></i>
                  </div>
                  <h4>All Caught Up! 🎉</h4>
                  <p>No new notifications</p>
                  <p style="font-size: 13px; color: #94a3b8;">We'll notify you when something important happens</p>
                </div>
              <?php endif; ?>
            </div>
          </div>
        </li>
        <!-- ================= END NOTIFICATION DROPDOWN ================= -->

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
/* Notification Badge */

.notification-badge {
    position: absolute;
    top: 8px;
    right: -8px;
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
    font-size: 11px;
    font-weight: 700;
    min-width: 22px;
    height: 22px;
    border-radius: 11px;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0 6px;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.4);
    border: 3px solid white;
    animation: pulse 2s ease-in-out infinite;
    z-index: 10;
}

@keyframes pulse {
    0%,
    100% {
        transform: scale(1);
    }
    50% {
        transform: scale(1.1);
    }
}


/* Notification Dropdown */

.notification-dropdown {
    width: 420px !important;
    max-width: 95vw !important;
    border-radius: 16px !important;
    border: none !important;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3) !important;
    overflow: hidden !important;
}


/* Notification Header */

.notification-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px 24px;
    color: white;
}

.notification-header-top {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.notification-header-title {
    display: flex;
    align-items: center;
    gap: 12px;
}

.notification-header-icon {
    width: 40px;
    height: 40px;
    background: rgba(255, 255, 255, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    backdrop-filter: blur(10px);
}

.notification-header-icon i {
    width: 20px;
    height: 20px;
    color: white;
}

.notification-header-text h3 {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 2px 0;
    color: white;
}

.notification-header-text p {
    font-size: 13px;
    margin: 0;
    opacity: 0.9;
}

.notification-count-badge {
    background: white;
    color: #667eea;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 13px;
    font-weight: 700;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}


/* View All Button and Mark as Read Button Container */

.notification-header-actions {
    display: flex;
    gap: 8px;
    flex-direction: column;
}


/* View All Button */

.view-all-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.15);
    border: 1px solid rgba(255, 255, 255, 0.25);
    border-radius: 10px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    text-decoration: none;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.view-all-btn:hover {
    background: rgba(255, 255, 255, 0.25);
    transform: translateY(-1px);
    color: white;
    text-decoration: none;
}

.view-all-btn i {
    width: 16px;
    height: 16px;
}


/* Mark as Read Button in Header */

.mark-read-btn-header {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 12px 16px;
    background: rgba(255, 255, 255, 0.25);
    border: 1px solid rgba(255, 255, 255, 0.35);
    border-radius: 10px;
    color: white;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.3s ease;
    backdrop-filter: blur(10px);
}

.mark-read-btn-header:hover:not(:disabled) {
    background: rgba(255, 255, 255, 0.35);
    transform: translateY(-1px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.mark-read-btn-header:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

.mark-read-btn-header i {
    width: 16px;
    height: 16px;
}


/* Notification Body */

.notification-body {
    max-height: 480px;
    overflow-y: auto;
    background: #f8f9fa;
}

.notification-body::-webkit-scrollbar {
    width: 6px;
}

.notification-body::-webkit-scrollbar-track {
    background: #f1f1f1;
}

.notification-body::-webkit-scrollbar-thumb {
    background: #cbd5e1;
    border-radius: 3px;
}

.notification-body::-webkit-scrollbar-thumb:hover {
    background: #94a3b8;
}

.notification-list {
    padding: 12px;
}


/* Notification Item */

.notification-item {
    background: white;
    border-radius: 12px;
    padding: 16px;
    margin-bottom: 10px;
    display: flex;
    align-items: flex-start;
    gap: 14px;
    cursor: pointer;
    transition: all 0.2s ease;
    border: 1px solid transparent;
    position: relative;
}

.notification-item:hover {
    transform: translateX(4px);
    border-color: #667eea;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.15);
}

.notification-item.unread {
    background: #f0f4ff;
    border-left: 3px solid #667eea;
}

.notification-item:last-child {
    margin-bottom: 0;
}


/* Notification Icon */

.notification-icon {
    width: 44px;
    height: 44px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}

.notification-icon i {
    width: 20px;
    height: 20px;
    color: white;
}

.notification-icon.danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
}

.notification-icon.warning {
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
}

.notification-icon.success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
}

.notification-icon.info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
}


/* Notification Content */

.notification-content {
    flex: 1;
    min-width: 0;
}

.notification-header-row {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 8px;
    margin-bottom: 6px;
}

.notification-title {
    font-size: 14px;
    font-weight: 700;
    color: #1e293b;
    line-height: 1.4;
    margin: 0;
}

.unread-indicator {
    width: 8px;
    height: 8px;
    background: #667eea;
    border-radius: 50%;
    flex-shrink: 0;
    margin-top: 4px;
    animation: pulse 2s ease-in-out infinite;
}

.notification-message {
    font-size: 13px;
    color: #64748b;
    line-height: 1.5;
    margin: 0 0 8px 0;
}

.notification-footer-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    flex-wrap: wrap;
}

.notification-time {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 12px;
    color: #94a3b8;
}

.notification-time i {
    width: 14px;
    height: 14px;
}

.notification-actions {
    display: flex;
    gap: 6px;
    flex-wrap: wrap;
}

.notification-action-btn {
    padding: 6px 12px;
    border-radius: 6px;
    font-size: 12px;
    font-weight: 600;
    border: none;
    cursor: pointer;
    transition: all 0.2s ease;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    text-decoration: none;
}

.notification-action-btn i {
    width: 14px;
    height: 14px;
}

.notification-action-btn.primary {
    background: #ede9fe;
    color: #667eea;
}

.notification-action-btn.primary:hover {
    background: #ddd6fe;
    transform: translateY(-1px);
    text-decoration: none;
    color: #667eea;
}

.notification-action-btn.secondary {
    background: #f1f5f9;
    color: #64748b;
}

.notification-action-btn.secondary:hover {
    background: #e2e8f0;
    transform: translateY(-1px);
    text-decoration: none;
    color: #64748b;
}


/* Empty State */

.notification-empty {
    padding: 60px 24px;
    text-align: center;
}

.notification-empty-icon {
    width: 80px;
    height: 80px;
    background: #f1f5f9;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 20px;
}

.notification-empty-icon i {
    width: 36px;
    height: 36px;
    color: #cbd5e1;
}

.notification-empty h4 {
    font-size: 16px;
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 8px;
}

.notification-empty p {
    font-size: 14px;
    color: #64748b;
    margin-bottom: 4px;
}


/* Responsive Design */

@media (max-width: 640px) {
    .notification-dropdown {
        width: 95vw !important;
        left: 2.5vw !important;
        right: 2.5vw !important;
    }
    .notification-item {
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
    .notification-action-btn {
        padding: 4px 8px;
        font-size: 11px;
    }
    .notification-header {
        padding: 16px 20px;
    }
    .notification-header-text h3 {
        font-size: 16px;
    }
    .notification-header-text p {
        font-size: 12px;
    }
}


/* Smooth animations */

@keyframes fadeIn {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
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

.dropdown-menu.show {
    animation: slideDown 0.3s cubic-bezier(0.4, 0, 0.2, 1);
}

</style>

<script>
// ==================== NOTIFICATION ====================


window.addEventListener('load', function() {
  setTimeout(function() {
    const allDropdowns = document.querySelectorAll('.dropdown-menu');
    const allToggles = document.querySelectorAll('.dropdown-toggle');
    
    allDropdowns.forEach(dropdown => {
      dropdown.classList.remove('show');
      dropdown.style.display = '';
    });
    
    allToggles.forEach(toggle => {
      toggle.setAttribute('aria-expanded', 'false');
    });
  }, 100);
});

// Prevent any dropdown from opening on page load
document.addEventListener('DOMContentLoaded', function() {
  const dropdowns = document.querySelectorAll('.dropdown-toggle');
  dropdowns.forEach(toggle => {
    toggle.setAttribute('aria-expanded', 'false');
  });
  
  const dropdownMenus = document.querySelectorAll('.dropdown-menu');
  dropdownMenus.forEach(menu => {
    menu.classList.remove('show');
  });

  // Reinitialize feather icons
  if (typeof feather !== 'undefined') {
    feather.replace();
  }
});

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
  const allDropdowns = document.querySelectorAll('.dropdown');
  
  allDropdowns.forEach(dropdownContainer => {
    if (!dropdownContainer.contains(event.target)) {
      const dropdown = dropdownContainer.querySelector('.dropdown-menu');
      const toggle = dropdownContainer.querySelector('.dropdown-toggle');
      
      if (dropdown && dropdown.classList.contains('show')) {
        dropdown.classList.remove('show');
        if (toggle) {
          toggle.setAttribute('aria-expanded', 'false');
        }
      }
    }
  });
});

// Mark all notifications as read
function markAllAsRead(event) {
  if (event) {
    event.preventDefault();
    event.stopPropagation();
  }
  
  const btn = event ? event.target.closest('button') : null;
  if (!btn) {
    console.error('Button not found');
    return;
  }
  
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
      
      // Remove unread indicators
      const unreadDots = document.querySelectorAll('.unread-indicator');
      unreadDots.forEach(dot => {
        dot.style.transition = 'opacity 0.3s ease';
        dot.style.opacity = '0';
        setTimeout(() => dot.remove(), 300);
      });
      
      // Remove unread class from notifications
      const notifications = document.querySelectorAll('.notification-item.unread');
      notifications.forEach(notification => {
        notification.classList.remove('unread');
        notification.style.transition = 'all 0.3s ease';
      });
      
      // Remove the badge
      const badge = document.querySelector('.notification-badge');
      if (badge) {
        badge.style.transition = 'all 0.3s ease';
        badge.style.transform = 'scale(0)';
        badge.style.opacity = '0';
        setTimeout(() => badge.remove(), 300);
      }
      
      // Remove the count badge
      const countBadge = document.querySelector('.notification-count-badge');
      if (countBadge) {
        countBadge.style.transition = 'all 0.3s ease';
        countBadge.style.transform = 'scale(0)';
        countBadge.style.opacity = '0';
        setTimeout(() => countBadge.remove(), 300);
      }
      
      // Reload page after a short delay
      setTimeout(() => {
        location.reload();
      }, 1500);
    } else {
      alert('❌ Error: ' + (data.message || 'Unknown error occurred'));
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

// Auto-refresh notification count every 60 seconds
setInterval(function() {
  // Optional: Add AJAX call to check for new notifications
}, 60000);

// Reinitialize feather icons periodically
setInterval(function() {
  if (typeof feather !== 'undefined') {
    feather.replace();
  }
}, 500);
</script>