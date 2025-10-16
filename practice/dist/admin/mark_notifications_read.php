<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";
include "../includes/notification_helper.php";

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

// Handle Mark All as Read request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'mark_all_read') {
    
    try {
        // Create notifications_read table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS notifications_read (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_key VARCHAR(255) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            UNIQUE KEY unique_read_notification (user_id, notification_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        $conn->query($create_table);
        
        // Get current date for generating notification keys
        $today = date('Y-m-d');
        $currentMonth = date('Y-m');
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        
        // Mark all current notification types as read
        $notification_keys = [
            'daily_budget_exceeded_' . $today,
            'daily_budget_warning_' . $today,
            'weekly_budget_exceeded_' . $weekStart,
            'weekly_budget_warning_' . $weekStart,
            'monthly_budget_exceeded_' . $currentMonth,
            'monthly_budget_info_' . $currentMonth
        ];
        
        // Set expiry times for each notification type
        $daily_expiry = date('Y-m-d 23:59:59'); // End of today
        $weekly_expiry = date('Y-m-d 23:59:59', strtotime('sunday this week')); // End of this week
        $monthly_expiry = date('Y-m-t 23:59:59'); // End of this month
        
        $expiry_map = [
            'daily' => $daily_expiry,
            'weekly' => $weekly_expiry,
            'monthly' => $monthly_expiry
        ];
        
        // Insert read records
        $insert_query = $conn->prepare("INSERT INTO notifications_read 
            (user_id, notification_key, expires_at) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP, expires_at = VALUES(expires_at)");
        
        if ($insert_query) {
            $marked_count = 0;
            
            foreach ($notification_keys as $key) {
                // Determine expiry based on notification type
                $expiry = null;
                
                if (strpos($key, 'daily') !== false) {
                    $expiry = $expiry_map['daily'];
                } elseif (strpos($key, 'weekly') !== false) {
                    $expiry = $expiry_map['weekly'];
                } elseif (strpos($key, 'monthly') !== false) {
                    $expiry = $expiry_map['monthly'];
                }
                
                $insert_query->bind_param("iss", $user_id, $key, $expiry);
                if ($insert_query->execute()) {
                    $marked_count++;
                }
            }
            
            $insert_query->close();
            
            // Also mark recent expense notifications as read (last 7 days)
            $mark_expenses = $conn->prepare("INSERT INTO notifications_read 
                (user_id, notification_key, expires_at) 
                VALUES (?, ?, ?) 
                ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP");
            
            if ($mark_expenses) {
                // Get recent expenses to mark as read (last 7 days)
                $sevenDaysAgo = date('Y-m-d', strtotime('-7 days'));
                $get_expenses = $conn->prepare("SELECT id FROM expenses 
                    WHERE user_id = ? AND date >= ?");
                $get_expenses->bind_param("is", $user_id, $sevenDaysAgo);
                $get_expenses->execute();
                $expense_result = $get_expenses->get_result();
                
                $expense_expiry = date('Y-m-d H:i:s', strtotime('+7 days'));
                
                while ($expense_row = $expense_result->fetch_assoc()) {
                    $expense_key = 'expense_' . $expense_row['id'];
                    $mark_expenses->bind_param("iss", $user_id, $expense_key, $expense_expiry);
                    if ($mark_expenses->execute()) {
                        $marked_count++;
                    }
                }
                
                $get_expenses->close();
                $mark_expenses->close();
            }
            
            echo json_encode([
                'success' => true, 
                'message' => 'All notifications marked as read successfully!',
                'marked_count' => $marked_count
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