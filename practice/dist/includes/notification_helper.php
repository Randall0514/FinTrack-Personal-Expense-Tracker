<?php
/**
 * Notification Helper Functions
 * Save this file as: ../includes/notification_helper.php
 */

if (!function_exists('isNotificationRead')) {
    /**
     * Check if a notification has been marked as read
     */
    function isNotificationRead($conn, $user_id, $notification_key) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS notifications_read (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_key VARCHAR(255) NOT NULL,
            read_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            UNIQUE KEY unique_read_notification (user_id, notification_key),
            INDEX idx_user_key (user_id, notification_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        // Use @ to suppress errors if table already exists
        @$conn->query($create_table);
        
        // Clean up expired read notifications
        @$conn->query("DELETE FROM notifications_read WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        
        // Check if notification is marked as read
        $check_query = $conn->prepare("SELECT id FROM notifications_read 
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
}

if (!function_exists('isNotificationDismissed')) {
    /**
     * Check if a notification has been dismissed (deleted from view)
     */
    function isNotificationDismissed($conn, $user_id, $notification_key) {
        // Create table if it doesn't exist
        $create_table = "CREATE TABLE IF NOT EXISTS notifications_dismissed (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            notification_type VARCHAR(50) NOT NULL,
            notification_key VARCHAR(255) NOT NULL,
            dismissed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL,
            UNIQUE KEY unique_notification (user_id, notification_key),
            INDEX idx_user_key_dismissed (user_id, notification_key),
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )";
        
        // Use @ to suppress errors if table already exists
        @$conn->query($create_table);
        
        // Clean up expired dismissed notifications
        @$conn->query("DELETE FROM notifications_dismissed WHERE expires_at IS NOT NULL AND expires_at < NOW()");
        
        // Check if notification is dismissed
        $check_query = $conn->prepare("SELECT id FROM notifications_dismissed 
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
}

if (!function_exists('markNotificationAsRead')) {
    /**
     * Mark a notification as read
     */
    function markNotificationAsRead($conn, $user_id, $notification_key, $expires_at = null) {
        $insert_query = $conn->prepare("INSERT INTO notifications_read 
            (user_id, notification_key, expires_at) 
            VALUES (?, ?, ?) 
            ON DUPLICATE KEY UPDATE read_at = CURRENT_TIMESTAMP, expires_at = VALUES(expires_at)");
        
        if ($insert_query) {
            $insert_query->bind_param("iss", $user_id, $notification_key, $expires_at);
            $success = $insert_query->execute();
            $insert_query->close();
            return $success;
        }
        
        return false;
    }
}

if (!function_exists('countUnreadNotifications')) {
    /**
     * Count unread notifications for a user
     */
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
?>