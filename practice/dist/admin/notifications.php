<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

// Include notification helper functions
include "../includes/notification_helper.php";

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

if (!isset($_COOKIE['jwt_token'])) {
    header("Location: ../../login.php");
    exit;
}

$jwt = $_COOKIE['jwt_token'];

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user = (array) $decoded->data;
} catch (Exception $e) {
    setcookie("jwt_token", "", time() - 3600, "/");
    header("Location: ../../login.php");
    exit;
}

$user_id = $user['id'];

// Fetch user data
$userQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result();
$userData = $userResult->fetch_assoc();

// Fetch budget data
$dailyBudget = $userData['daily_budget'] ?? 500;
$weeklyBudget = $userData['weekly_budget'] ?? 3000;
$monthlyBudget = $userData['monthly_budget'] ?? 10000;

// Fetch all expenses
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC, id DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$expenses = [];
while ($row = $result->fetch_assoc()) {
    $expenses[] = $row;
}
$stmt->close();

// Calculate spending periods
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

// Generate all notifications using the helper function
$allNotifications = [];

// Calculate percentages
$dailyPercentage = $dailyBudget > 0 ? ($dailySpending / $dailyBudget) * 100 : 0;
$weeklyPercentage = $weeklyBudget > 0 ? ($weeklySpending / $weeklyBudget) * 100 : 0;
$monthlyPercentage = $monthlyBudget > 0 ? ($monthlySpending / $monthlyBudget) * 100 : 0;

// ==================== DAILY NOTIFICATIONS ====================
// Check for INFO notification (60-79%)
if ($dailyPercentage >= 60 && $dailyPercentage < 80) {
    $key = 'daily_budget_info_' . $today;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'info',
            'title' => 'Daily Budget Info',
            'message' => 'You have used ' . number_format($dailyPercentage, 1) . '% of your daily budget (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'info',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// Check for WARNING notification (80%+)
if ($dailyPercentage >= 80) {
    $key = 'daily_budget_warning_' . $today;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'alert-triangle',
            'title' => 'Daily Budget Warning',
            'message' => 'You have used ' . number_format($dailyPercentage, 1) . '% of your daily budget (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'warning',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// Check for DANGER notification (exceeded budget)
if ($dailySpending > $dailyBudget) {
    $key = 'daily_budget_exceeded_' . $today;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'alert-circle',
            'title' => 'Daily Budget Exceeded!',
            'message' => 'You have exceeded your daily budget by ₱' . number_format($dailySpending - $dailyBudget, 2) . ' (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'danger',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// ==================== WEEKLY NOTIFICATIONS ====================
// Check for INFO notification (60-79%)
if ($weeklyPercentage >= 60 && $weeklyPercentage < 80) {
    $key = 'weekly_budget_info_' . $weekStart;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'info',
            'title' => 'Weekly Budget Info',
            'message' => 'You have used ' . number_format($weeklyPercentage, 1) . '% of your weekly budget (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'info',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// Check for WARNING notification (80%+)
if ($weeklyPercentage >= 80) {
    $key = 'weekly_budget_warning_' . $weekStart;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'alert-triangle',
            'title' => 'Weekly Budget Alert',
            'message' => 'You have used ' . number_format($weeklyPercentage, 1) . '% of your weekly budget (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'warning',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// Check for DANGER notification (exceeded budget)
if ($weeklySpending > $weeklyBudget) {
    $key = 'weekly_budget_exceeded_' . $weekStart;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'alert-circle',
            'title' => 'Weekly Budget Exceeded!',
            'message' => 'You have exceeded your weekly budget by ₱' . number_format($weeklySpending - $weeklyBudget, 2) . ' (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'danger',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// ==================== MONTHLY NOTIFICATIONS ====================
// Check for INFO notification (60-79%)
if ($monthlyPercentage >= 60 && $monthlyPercentage < 80) {
    $key = 'monthly_budget_info_' . $currentMonth;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'info',
            'title' => 'Monthly Budget Info',
            'message' => 'You have used ' . number_format($monthlyPercentage, 1) . '% of your monthly budget (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'info',
            'category' => 'Budget Info',
            'key' => $key
        ];
    }
}

// Check for WARNING notification (80%+)
if ($monthlyPercentage >= 80) {
    $key = 'monthly_budget_warning_' . $currentMonth;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'alert-triangle',
            'title' => 'Monthly Budget Warning',
            'message' => 'You have used ' . number_format($monthlyPercentage, 1) . '% of your monthly budget (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'warning',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// Check for DANGER notification (exceeded budget)
if ($monthlySpending > $monthlyBudget) {
    $key = 'monthly_budget_exceeded_' . $currentMonth;
    if (!isNotificationDismissed($conn, $user_id, $key)) {
        $allNotifications[] = [
            'icon' => 'alert-circle',
            'title' => 'Monthly Budget Exceeded!',
            'message' => 'You have exceeded your monthly budget by ₱' . number_format($monthlySpending - $monthlyBudget, 2) . ' (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
            'time' => date('h:i A'),
            'date' => date('M d, Y'),
            'type' => 'danger',
            'category' => 'Budget Alert',
            'key' => $key
        ];
    }
}

// Recent expenses (last 7 days)
$sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
foreach ($expenses as $expense) {
    if ($expense['date'] >= $sevenDaysAgo) {
        $key = 'expense_' . $expense['id'];
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $expenseTime = strtotime($expense['date']);
            $currentTime = time();
            $timeDiff = $currentTime - $expenseTime;
            
            $timeAgo = '';
            if ($timeDiff < 3600) {
                $minutes = floor($timeDiff / 60);
                $timeAgo = $minutes <= 1 ? 'Just now' : $minutes . ' minutes ago';
            } elseif ($timeDiff < 86400) {
                $hours = floor($timeDiff / 3600);
                $timeAgo = $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
            } else {
                $days = floor($timeDiff / 86400);
                $timeAgo = $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
            }
            
            $allNotifications[] = [
                'icon' => 'shopping-cart',
                'title' => 'Expense Recorded',
                'message' => '₱' . number_format($expense['amount'], 2) . ' - ' . $expense['category'],
                'time' => date('h:i A', strtotime($expense['date'])),
                'date' => date('M d, Y', strtotime($expense['date'])),
                'type' => 'success',
                'category' => 'Expense',
                'timeAgo' => $timeAgo,
                'key' => $key
            ];
        }
    }
}

$totalNotifications = count($allNotifications);
?>

<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
<head>
    <title>Notifications | FinTrack</title>
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
        .pc-content { padding: 20px; }

        /* Full width view adjustments */
        .pc-container.full-width {
            margin-left: 0 !important;
            width: 100% !important;
        }

        /* Page Header */
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

        .user-info {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.95rem;
            margin-top: 8px;
        }

        .breadcrumb { background: transparent !important; }
        .breadcrumb-item, .breadcrumb-item a {
            color: rgba(255, 255, 255, 0.9) !important;
            font-weight: 600;
        }

        /* Cards */
        .card {
            background: rgba(255, 255, 255, 0.95) !important;
            backdrop-filter: blur(10px);
            border-radius: 15px !important;
            border: none !important;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
            margin-bottom: 20px;
        }

        .card.hoverable {
            transition: transform 0.3s, box-shadow 0.3s;
        }

        .card.hoverable:hover {
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
            margin: 0;
        }

        .card-body { padding: 25px !important; }

        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stat-card.hoverable {
            transition: transform 0.3s;
        }

        .stat-card.hoverable:hover { transform: translateY(-5px); }

        .stat-icon {
            width: 50px;
            height: 50px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .stat-content h6 {
            color: rgba(255, 255, 255, 0.9);
            font-size: 0.85rem;
            margin: 0 0 5px 0;
        }

        .stat-content h3 {
            color: white;
            font-size: 1.8rem;
            margin: 0;
            font-weight: 700;
        }

        /* Filter Section */
        .filter-section {
            background: rgba(255, 255, 255, 0.1);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .filter-section select,
        .filter-section input {
            padding: 8px 12px;
            border-radius: 8px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            background: white;
            outline: none;
        }

        .filter-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 8px 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s;
        }

        .filter-btn:hover { transform: scale(1.05); }

        .filter-btn.secondary {
            background: rgba(255, 255, 255, 0.2);
        }

        .filter-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .filter-btn .icon-loader {
            animation: spin 1s linear infinite;
        }

        /* Notification Item */
        .notification-item {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 15px;
            display: flex;
            align-items: start;
            gap: 15px;
            transition: all 0.3s;
            animation: slideIn 0.4s ease-out;
        }

        .notification-item:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 20px rgba(102, 126, 234, 0.2);
        }

        .notification-icon {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            flex-shrink: 0;
        }

        .notification-icon.danger { background: linear-gradient(135deg, #ef4444, #dc2626); }
        .notification-icon.warning { background: linear-gradient(135deg, #f59e0b, #d97706); }
        .notification-icon.success { background: linear-gradient(135deg, #10b981, #059669); }
        .notification-icon.info { background: linear-gradient(135deg, #3b82f6, #2563eb); }

        .notification-content {
            flex: 1;
        }

        .notification-content h6 {
            color: #333;
            font-weight: 700;
            margin: 0 0 5px 0;
            font-size: 1rem;
        }

        .notification-content p {
            color: #666;
            margin: 0 0 10px 0;
            font-size: 0.9rem;
            line-height: 1.5;
        }

        .notification-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 6px;
            font-size: 0.75rem;
            font-weight: 600;
            margin-right: 8px;
        }

        .notification-badge.danger { background: #fee2e2; color: #dc2626; }
        .notification-badge.warning { background: #fef3c7; color: #d97706; }
        .notification-badge.success { background: #d1fae5; color: #059669; }
        .notification-badge.info { background: #dbeafe; color: #2563eb; }

        .notification-time {
            color: #999;
            font-size: 0.8rem;
            white-space: nowrap;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 80px;
            color: #ccc;
            margin-bottom: 20px;
        }

        .empty-state h4 {
            color: #333;
            margin-bottom: 10px;
            font-weight: 700;
        }

        .empty-state p {
            color: #666;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        @keyframes slideOut {
            from {
                opacity: 1;
                transform: translateX(0);
            }
            to {
                opacity: 0;
                transform: translateX(-100%);
            }
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            
            .notification-item {
                flex-direction: column;
            }
            
            .notification-time {
                text-align: left;
                margin-top: 10px;
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

    <?php 
    // Check if coming from "view all" link
    $hideLayout = isset($_GET['view']) && $_GET['view'] === 'all';
    
    if (!$hideLayout) {
        include '../includes/sidebar.php';
        include '../includes/header.php';
    }
    ?>

    <div class="pc-container" <?php echo $hideLayout ? 'style="margin-left: 0 !important; width: 100% !important;"' : ''; ?>>
        <div class="pc-content" <?php echo $hideLayout ? 'style="padding-top: 20px !important;"' : ''; ?>>
            <!-- Page Header -->
            <div class="page-header">
                <div class="page-block">
                    <div class="page-header-title">
                        <h5 class="mb-0 font-medium">
                            <?php if ($hideLayout): ?>
                            <a href="dashboard.php" style="color: white; text-decoration: none; margin-right: 10px;">
                                <i class="feather icon-arrow-left"></i>
                            </a>
                            <?php endif; ?>
                            🔔 Notifications
                        </h5>
                        <div class="user-info">
                            Stay updated with your financial activity
                        </div>
                    </div>
                    <ul class="breadcrumb">
                        <?php if (!$hideLayout): ?>
                        <li class="breadcrumb-item"><a href="dashboard.php">Dashboard</a></li>
                        <li class="breadcrumb-item" aria-current="page">Notifications</li>
                        <?php else: ?>
                        <li class="breadcrumb-item"><a href="dashboard.php">Back to Dashboard</a></li>
                        <?php endif; ?>
                    </ul>
                </div>
            </div>

            <!-- Stats Overview -->
            <div class="stats-grid">
                <div class="stat-card hoverable">
                    <div class="stat-icon">
                        <i class="feather icon-alert-circle" style="font-size: 24px;"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Critical Alerts</h6>
                        <h3><?php echo count(array_filter($allNotifications, fn($n) => $n['type'] == 'danger')); ?></h3>
                    </div>
                </div>

                <div class="stat-card hoverable" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <div class="stat-icon">
                        <i class="feather icon-alert-triangle" style="font-size: 24px;"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Warnings</h6>
                        <h3><?php echo count(array_filter($allNotifications, fn($n) => $n['type'] == 'warning')); ?></h3>
                    </div>
                </div>

                <div class="stat-card hoverable" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <div class="stat-icon">
                        <i class="feather icon-check-circle" style="font-size: 24px;"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Activities</h6>
                        <h3><?php echo count(array_filter($allNotifications, fn($n) => $n['type'] == 'success')); ?></h3>
                    </div>
                </div>

                <div class="stat-card hoverable" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                    <div class="stat-icon">
                        <i class="feather icon-inbox" style="font-size: 24px;"></i>
                    </div>
                    <div class="stat-content">
                        <h6>Total Notifications</h6>
                        <h3><?php echo $totalNotifications; ?></h3>
                    </div>
                </div>
            </div>

            <!-- Filters -->
            <div class="filter-section">
                <select id="filterType" onchange="filterNotifications()">
                    <option value="all">All Types</option>
                    <option value="danger">Critical</option>
                    <option value="warning">Warnings</option>
                    <option value="success">Activities</option>
                    <option value="info">Information</option>
                </select>

                <select id="filterCategory" onchange="filterNotifications()">
                    <option value="all">All Categories</option>
                    <option value="Budget Alert">Budget Alerts</option>
                    <option value="Expense">Expenses</option>
                </select>

                <input type="text" id="searchNotification" placeholder="Search notifications..." onkeyup="filterNotifications()">

                <button class="filter-btn" onclick="location.reload()">
                    <i class="feather icon-refresh-cw"></i> Refresh
                </button>

                <button class="filter-btn secondary" onclick="resetFilters()">
                    <i class="feather icon-x"></i> Reset
                </button>

                <?php if (!empty($allNotifications)): ?>
                <button class="filter-btn" style="background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);" onclick="deleteAllNotifications()">
                    <i class="feather icon-trash-2"></i> Delete All
                </button>
                <?php endif; ?>
            </div>

            <!-- Notifications List -->
            <div class="card">
                <div class="card-header">
                    <h5>📋 All Notifications</h5>
                </div>
                <div class="card-body" style="padding: 15px !important;">
                    <div id="notificationsList">
                        <?php if (!empty($allNotifications)): ?>
                            <?php foreach ($allNotifications as $notification): ?>
                            <div class="notification-item" 
                                 data-type="<?php echo $notification['type']; ?>"
                                 data-category="<?php echo $notification['category']; ?>"
                                 data-search="<?php echo strtolower($notification['title'] . ' ' . $notification['message']); ?>">
                                
                                <div class="notification-icon <?php echo $notification['type']; ?>">
                                    <i class="feather icon-<?php echo $notification['icon']; ?>" style="font-size: 24px;"></i>
                                </div>
                                
                                <div class="notification-content">
                                    <h6><?php echo htmlspecialchars($notification['title']); ?></h6>
                                    <p><?php echo htmlspecialchars($notification['message']); ?></p>
                                    
                                    <div>
                                        <span class="notification-badge <?php echo $notification['type']; ?>">
                                            <?php echo $notification['category']; ?>
                                        </span>
                                        <?php if (isset($notification['timeAgo'])): ?>
                                        <span class="notification-badge info">
                                            <?php echo $notification['timeAgo']; ?>
                                        </span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="notification-time">
                                    <div><?php echo $notification['date']; ?></div>
                                    <div><?php echo $notification['time']; ?></div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="empty-state">
                                <i class="feather icon-bell-off"></i>
                                <h4>No Notifications</h4>
                                <p>You're all caught up! Check back later for updates.</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- No Results -->
                    <div id="noResults" class="empty-state" style="display: none;">
                        <i class="feather icon-search"></i>
                        <h4>No Results Found</h4>
                        <p>Try adjusting your filters or search terms</p>
                        <button class="filter-btn" onclick="resetFilters()">Reset Filters</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php 
    if (!$hideLayout) {
        include '../includes/footer.php'; 
    }
    ?>

    <script src="../assets/js/plugins/simplebar.min.js"></script>
    <script src="../assets/js/plugins/popper.min.js"></script>
    <script src="../assets/js/icon/custom-icon.js"></script>
    <script src="../assets/js/plugins/feather.min.js"></script>
    <script src="../assets/js/component.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        function filterNotifications() {
            const filterType = document.getElementById('filterType').value;
            const filterCategory = document.getElementById('filterCategory').value;
            const searchTerm = document.getElementById('searchNotification').value.toLowerCase();
            const notifications = document.querySelectorAll('.notification-item');
            let visibleCount = 0;

            notifications.forEach(notification => {
                const type = notification.dataset.type;
                const category = notification.dataset.category;
                const searchContent = notification.dataset.search;

                const typeMatch = filterType === 'all' || type === filterType;
                const categoryMatch = filterCategory === 'all' || category === filterCategory;
                const searchMatch = searchTerm === '' || searchContent.includes(searchTerm);

                if (typeMatch && categoryMatch && searchMatch) {
                    notification.style.display = 'flex';
                    visibleCount++;
                } else {
                    notification.style.display = 'none';
                }
            });

            const emptyState = document.querySelector('.empty-state:not(#noResults)');
            if (emptyState) {
                emptyState.style.display = 'none';
            }
            
            document.getElementById('noResults').style.display = 
                (visibleCount === 0 && (filterType !== 'all' || filterCategory !== 'all' || searchTerm !== '')) 
                ? 'block' : 'none';
        }

        function resetFilters() {
            // Reset all filter inputs
            document.getElementById('filterType').value = 'all';
            document.getElementById('filterCategory').value = 'all';
            document.getElementById('searchNotification').value = '';
            
            // Show all notifications
            const notifications = document.querySelectorAll('.notification-item');
            notifications.forEach(notification => {
                notification.style.display = 'flex';
            });
            
            // Hide no results message
            document.getElementById('noResults').style.display = 'none';
            
            // Show empty state if there are no notifications at all
            const emptyState = document.querySelector('.empty-state:not(#noResults)');
            if (emptyState && notifications.length === 0) {
                emptyState.style.display = 'block';
            }
        }

        function deleteAllNotifications() {
            // Show confirmation dialog
            if (!confirm('Are you sure you want to delete all notifications? This action cannot be undone.')) {
                return;
            }

            // Show loading state
            const deleteBtn = event.target.closest('button');
            const originalHTML = deleteBtn.innerHTML;
            deleteBtn.disabled = true;
            deleteBtn.innerHTML = '<i class="feather icon-loader"></i> Deleting...';

            // Send AJAX request
            fetch('delete_notifications.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=delete_all'
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show success message
                    alert('✅ ' + data.message);
                    
                    // Animate notifications out
                    const notifications = document.querySelectorAll('.notification-item');
                    notifications.forEach((notification, index) => {
                        setTimeout(() => {
                            notification.style.animation = 'slideOut 0.4s ease-out forwards';
                        }, index * 50);
                    });

                    // Reload page after animation
                    setTimeout(() => {
                        location.reload();
                    }, notifications.length * 50 + 500);
                } else {
                    alert('❌ Error: ' + data.message);
                    deleteBtn.disabled = false;
                    deleteBtn.innerHTML = originalHTML;
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('❌ An error occurred while deleting notifications');
                deleteBtn.disabled = false;
                deleteBtn.innerHTML = originalHTML;
            });
        }

        // Initialize
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