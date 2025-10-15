<?php
/**
 * Check if a notification has been dismissed by the user
 * Add this function to a shared helper file or include it in header.php and notifications.php
 */
function isNotificationDismissed($conn, $user_id, $notification_key) {
    // First, clean up expired dismissals
    $cleanup = $conn->prepare("DELETE FROM notifications_dismissed 
        WHERE expires_at IS NOT NULL AND expires_at < NOW()");
    if ($cleanup) {
        $cleanup->execute();
        $cleanup->close();
    }
    
    // Check if this specific notification is dismissed
    $check = $conn->prepare("SELECT id FROM notifications_dismissed 
        WHERE user_id = ? AND notification_key = ? 
        AND (expires_at IS NULL OR expires_at > NOW())");
    
    if ($check) {
        $check->bind_param("is", $user_id, $notification_key);
        $check->execute();
        $result = $check->get_result();
        $is_dismissed = $result->num_rows > 0;
        $check->close();
        return $is_dismissed;
    }
    
    return false;
}

/**
 * Generate notifications with dismissal check
 * Replace the notification generation code in header.php with this
 */
function generateNotifications($conn, $user_id, $expenses, $dailyBudget, $weeklyBudget, $monthlyBudget) {
    $notifications = [];
    
    // Calculate spending
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

    // Calculate percentages
    $dailyPercentage = $dailyBudget > 0 ? ($dailySpending / $dailyBudget) * 100 : 0;
    $weeklyPercentage = $weeklyBudget > 0 ? ($weeklySpending / $weeklyBudget) * 100 : 0;
    $monthlyPercentage = $monthlyBudget > 0 ? ($monthlySpending / $monthlyBudget) * 100 : 0;

    // Daily budget notifications
    if ($dailySpending > $dailyBudget) {
        $key = 'daily_budget_exceeded_' . $today;
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notifications[] = [
                'icon' => 'alert-circle',
                'color' => 'red',
                'title' => 'Daily Budget Exceeded!',
                'message' => 'You have exceeded your daily budget by ₱' . number_format($dailySpending - $dailyBudget, 2),
                'time' => 'Just now',
                'type' => 'danger',
                'key' => $key
            ];
        }
    } elseif ($dailyPercentage >= 90) {
        $key = 'daily_budget_warning_' . $today;
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notifications[] = [
                'icon' => 'alert-triangle',
                'color' => 'orange',
                'title' => 'Daily Budget Warning',
                'message' => 'You have used ' . number_format($dailyPercentage, 1) . '% of your daily budget',
                'time' => 'Just now',
                'type' => 'warning',
                'key' => $key
            ];
        }
    }

    // Weekly budget notifications
    if ($weeklySpending > $weeklyBudget) {
        $key = 'weekly_budget_exceeded_' . $weekStart;
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notifications[] = [
                'icon' => 'alert-circle',
                'color' => 'red',
                'title' => 'Weekly Budget Exceeded!',
                'message' => 'You have exceeded your weekly budget by ₱' . number_format($weeklySpending - $weeklyBudget, 2),
                'time' => 'Today',
                'type' => 'danger',
                'key' => $key
            ];
        }
    } elseif ($weeklyPercentage >= 80) {
        $key = 'weekly_budget_warning_' . $weekStart;
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notifications[] = [
                'icon' => 'alert-triangle',
                'color' => 'orange',
                'title' => 'Weekly Budget Alert',
                'message' => 'You have used ' . number_format($weeklyPercentage, 1) . '% of your weekly budget',
                'time' => 'Today',
                'type' => 'warning',
                'key' => $key
            ];
        }
    }

    // Monthly budget notifications
    if ($monthlySpending > $monthlyBudget) {
        $key = 'monthly_budget_exceeded_' . $currentMonth;
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notifications[] = [
                'icon' => 'alert-circle',
                'color' => 'red',
                'title' => 'Monthly Budget Exceeded!',
                'message' => 'You have exceeded your monthly budget by ₱' . number_format($monthlySpending - $monthlyBudget, 2),
                'time' => date('M d'),
                'type' => 'danger',
                'key' => $key
            ];
        }
    } elseif ($monthlyPercentage >= 75) {
        $key = 'monthly_budget_info_' . $currentMonth;
        if (!isNotificationDismissed($conn, $user_id, $key)) {
            $notifications[] = [
                'icon' => 'info',
                'color' => 'blue',
                'title' => 'Monthly Budget Info',
                'message' => 'You have used ' . number_format($monthlyPercentage, 1) . '% of your monthly budget',
                'time' => date('M d'),
                'type' => 'info',
                'key' => $key
            ];
        }
    }

    // Recent expense notification (last 24 hours)
    if (!empty($expenses)) {
        $latestExpense = $expenses[0];
        $expenseTime = strtotime($latestExpense['date']);
        $currentTime = time();
        $timeDiff = $currentTime - $expenseTime;
        
        if ($timeDiff < 86400) { // Less than 24 hours
            $key = 'expense_' . $latestExpense['id'];
            if (!isNotificationDismissed($conn, $user_id, $key)) {
                $hours = floor($timeDiff / 3600);
                $timeAgo = $hours > 0 ? $hours . ' hours ago' : 'Just now';
                
                $notifications[] = [
                    'icon' => 'check-circle',
                    'color' => 'green',
                    'title' => 'Expense Added',
                    'message' => '₱' . number_format($latestExpense['amount'], 2) . ' - ' . $latestExpense['category'],
                    'time' => $timeAgo,
                    'type' => 'success',
                    'key' => $key
                ];
            }
        }
    }

    return $notifications;
}
?>