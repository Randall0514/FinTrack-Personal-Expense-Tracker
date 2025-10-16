<?php
/**
 * Notification Configuration
 * Save as: src/includes/notification_config.php
 * 
 * Adjust these settings to control when notifications appear
 */

// ==================== NOTIFICATION THRESHOLDS ====================

// Daily Budget Notifications
define('DAILY_DANGER_THRESHOLD', 100);    // Show DANGER when exceeding budget (100% = exceeded)
define('DAILY_WARNING_THRESHOLD', 70);    // Show WARNING at 70% of budget
define('DAILY_INFO_THRESHOLD', 50);       // Show INFO at 50% of budget

// Weekly Budget Notifications
define('WEEKLY_DANGER_THRESHOLD', 100);   // Show DANGER when exceeding budget
define('WEEKLY_WARNING_THRESHOLD', 70);   // Show WARNING at 70% of budget
define('WEEKLY_INFO_THRESHOLD', 50);      // Show INFO at 50% of budget

// Monthly Budget Notifications
define('MONTHLY_DANGER_THRESHOLD', 100);  // Show DANGER when exceeding budget
define('MONTHLY_WARNING_THRESHOLD', 70);  // Show WARNING at 70% of budget
define('MONTHLY_INFO_THRESHOLD', 50);     // Show INFO at 50% of budget

// ==================== EXPENSE NOTIFICATION SETTINGS ====================

// How many days back to show expense notifications
define('EXPENSE_NOTIFICATION_DAYS', 7);   // Show expenses from last 7 days

// Maximum number of expense notifications to show
define('MAX_EXPENSE_NOTIFICATIONS', 5);   // Show up to 5 recent expenses

// ==================== EXAMPLE CONFIGURATIONS ====================

/*
 * VERY STRICT (Early Warnings):
 * - Daily: Warning at 50%, Info at 30%
 * - Weekly: Warning at 60%, Info at 40%
 * - Monthly: Warning at 65%, Info at 45%
 * 
 * RELAXED (Fewer Notifications):
 * - Daily: Warning at 90%, Info at 75%
 * - Weekly: Warning at 85%, Info at 70%
 * - Monthly: Warning at 80%, Info at 65%
 * 
 * MINIMAL (Only Critical):
 * - Only show DANGER notifications (when budget exceeded)
 * - Set WARNING and INFO thresholds to 999 to disable them
 */

// ==================== TIME DISPLAY SETTINGS ====================

// Format for time display
define('TIME_FORMAT_SHORT', 'g:i A');     // Example: 3:45 PM
define('TIME_FORMAT_LONG', 'F j, Y g:i A'); // Example: October 16, 2024 3:45 PM
define('DATE_FORMAT', 'M d, Y');          // Example: Oct 16, 2024

// ==================== NOTIFICATION ICONS ====================

// You can customize icons here (using Feather Icons)
define('ICON_DANGER', 'alert-circle');
define('ICON_WARNING', 'alert-triangle');
define('ICON_INFO', 'info');
define('ICON_SUCCESS', 'check-circle');
define('ICON_EXPENSE', 'shopping-cart');

// ==================== NOTIFICATION COLORS ====================

define('COLOR_DANGER', 'red');
define('COLOR_WARNING', 'orange');
define('COLOR_INFO', 'blue');
define('COLOR_SUCCESS', 'green');

?>