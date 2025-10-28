<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

// Set JSON response header
header('Content-Type: application/json');

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

if (!isset($_COOKIE['jwt_token'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized. Please log in.']);
    exit;
}

$jwt = $_COOKIE['jwt_token'];

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user = (array) $decoded->data;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid or expired token.']);
    exit;
}

$user_id = $user['id'];

// Handle DELETE request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_all') {
    
    try {
        // Create notifications_dismissed table if it doesn't exist
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
        
        $conn->query($create_table);
        
        // Get notification keys from session (set by header.php)
        $notification_keys = isset($_SESSION['active_notification_keys']) 
            ? $_SESSION['active_notification_keys'] 
            : [];
        
        // If session keys are empty, REGENERATE them by recalculating spending
        if (empty($notification_keys)) {
            // Fetch user's budget data
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
                } else {
                    $dailyBudget = 500;
                    $weeklyBudget = 3000;
                    $monthlyBudget = 10000;
                }
                $budget_query->close();
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
            
            // Calculate spending for each period
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
            
            // Generate notification keys based on ACTUAL thresholds (same logic as header.php)
            $notification_keys = [];
            
            // Daily
            if ($dailySpending > $dailyBudget) {
                $notification_keys[] = 'daily_budget_exceeded_' . $today;
            } elseif ($dailyPercentage >= 80) {
                $notification_keys[] = 'daily_budget_warning_' . $today;
            } elseif ($dailyPercentage >= 60) {
                $notification_keys[] = 'daily_budget_info_' . $today;
            }
            
            // Weekly
            if ($weeklySpending > $weeklyBudget) {
                $notification_keys[] = 'weekly_budget_exceeded_' . $weekStart;
            } elseif ($weeklyPercentage >= 80) {
                $notification_keys[] = 'weekly_budget_warning_' . $weekStart;
            } elseif ($weeklyPercentage >= 60) {
                $notification_keys[] = 'weekly_budget_info_' . $weekStart;
            }
            
            // Monthly
            if ($monthlySpending > $monthlyBudget) {
                $notification_keys[] = 'monthly_budget_exceeded_' . $currentMonth;
            } elseif ($monthlyPercentage >= 80) {
                $notification_keys[] = 'monthly_budget_warning_' . $currentMonth;
            } elseif ($monthlyPercentage >= 60) {
                $notification_keys[] = 'monthly_budget_info_' . $currentMonth;
            }
            
            // Add expense notification keys
            $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
            foreach ($expenses as $expense) {
                if ($expense['date'] >= $sevenDaysAgo) {
                    $notification_keys[] = 'expense_' . $expense['id'];
                }
            }
        }
        
        // If still no keys found, return success with 0 count
        if (empty($notification_keys)) {
            echo json_encode([
                'success' => true, 
                'message' => 'No notifications to dismiss.',
                'dismissed_count' => 0
            ]);
            exit;
        }
        
        // Set expiry times for each notification type
        $today = date('Y-m-d');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $currentMonth = date('Y-m');
        
        $daily_expiry = date('Y-m-d 23:59:59');
        $weekly_expiry = date('Y-m-d 23:59:59', strtotime('sunday this week'));
        $monthly_expiry = date('Y-m-t 23:59:59');
        $expense_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
        
        // Insert dismissal records
        $insert_query = $conn->prepare("INSERT INTO notifications_dismissed 
            (user_id, notification_type, notification_key, expires_at) 
            VALUES (?, ?, ?, ?) 
            ON DUPLICATE KEY UPDATE dismissed_at = CURRENT_TIMESTAMP, expires_at = VALUES(expires_at)");
        
        if ($insert_query) {
            $dismissed_count = 0;
            
            foreach ($notification_keys as $key) {
                // Determine notification type and expiry
                $type = 'budget';
                $expiry = null;
                
                if (strpos($key, 'daily') !== false) {
                    $type = 'budget';
                    $expiry = $daily_expiry;
                } elseif (strpos($key, 'weekly') !== false) {
                    $type = 'budget';
                    $expiry = $weekly_expiry;
                } elseif (strpos($key, 'monthly') !== false) {
                    $type = 'budget';
                    $expiry = $monthly_expiry;
                } elseif (strpos($key, 'expense') !== false) {
                    $type = 'expense';
                    $expiry = $expense_expiry;
                }
                
                $insert_query->bind_param("isss", $user_id, $type, $key, $expiry);
                if ($insert_query->execute()) {
                    $dismissed_count++;
                }
            }
            
            $insert_query->close();
            
            echo json_encode([
                'success' => true, 
                'message' => 'All notifications cleared successfully!',
                'dismissed_count' => $dismissed_count
            ]);
        } else {
            echo json_encode([
                'success' => false, 
                'message' => 'Database query preparation failed.'
            ]);
        }
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false, 
            'message' => 'An error occurred: ' . $e->getMessage()
        ]);
    }
    
    $conn->close();
    exit;
}

// If not a valid request
echo json_encode(['success' => false, 'message' => 'Invalid request method or action.']);
?>