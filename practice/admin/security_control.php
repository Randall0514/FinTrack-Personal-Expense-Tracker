<?php
session_start();
require '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// ✅ FIXED: Check for admin token first
$secret_key = "your_secret_key_here_change_this_in_production";

// Check if admin token exists
if (!isset($_COOKIE['admin_jwt_token'])) {
    // Check if regular user token exists and redirect accordingly
    if (isset($_COOKIE['jwt_token'])) {
        header("Location: ../dist/admin/dashboard.php");
        exit;
    }
    header("Location: ../login.php");
    exit;
}

// Verify admin token
$jwt = $_COOKIE['admin_jwt_token'];
try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    
    // Check if user is admin
    if (!isset($decoded->data->is_admin) || $decoded->data->is_admin !== true) {
        // Not an admin, clear cookie and redirect
        setcookie("admin_jwt_token", "", time() - 3600, "/", "", false, true);
        header("Location: ../dist/admin/dashboard.php");
        exit;
    }
    
    $user_id = $decoded->data->id;
    $fullname = $decoded->data->fullname;
    $email = $decoded->data->email;
} catch (Exception $e) {
    // Invalid token
    setcookie("admin_jwt_token", "", time() - 3600, "/", "", false, true);
    header("Location: ../login.php");
    exit;
}

// Connect to database
require_once '../config/dbconfig_password.php';

// Process security settings
$message = '';
$settings = [];

// Check if security_settings table exists, if not create it
$tableCheck = $conn->query("SHOW TABLES LIKE 'security_settings'");
if ($tableCheck->num_rows == 0) {
    $createTable = "CREATE TABLE security_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        password_min_length INT DEFAULT 8,
        require_special_chars BOOLEAN DEFAULT TRUE,
        require_numbers BOOLEAN DEFAULT TRUE,
        require_uppercase BOOLEAN DEFAULT TRUE,
        max_login_attempts INT DEFAULT 5,
        lockout_time_minutes INT DEFAULT 30,
        session_timeout_minutes INT DEFAULT 60,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($createTable)) {
        $conn->query("INSERT INTO security_settings 
            (password_min_length, require_special_chars, require_numbers, require_uppercase, max_login_attempts, lockout_time_minutes, session_timeout_minutes) 
            VALUES (8, 1, 1, 1, 5, 30, 60)");
    }
}

// Get current settings
$result = $conn->query("SELECT * FROM security_settings LIMIT 1");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
} else {
    $conn->query("INSERT INTO security_settings 
        (password_min_length, require_special_chars, require_numbers, require_uppercase, max_login_attempts, lockout_time_minutes, session_timeout_minutes) 
        VALUES (8, 1, 1, 1, 5, 30, 60)");
    
    $result = $conn->query("SELECT * FROM security_settings LIMIT 1");
    if ($result) {
        $settings = $result->fetch_assoc();
    }
}

// Update settings
if (isset($_POST['update_settings'])) {
    $password_min_length = $_POST['password_min_length'];
    $require_special_chars = isset($_POST['require_special_chars']) ? 1 : 0;
    $require_numbers = isset($_POST['require_numbers']) ? 1 : 0;
    $require_uppercase = isset($_POST['require_uppercase']) ? 1 : 0;
    $max_login_attempts = $_POST['max_login_attempts'];
    $lockout_time_minutes = $_POST['lockout_time_minutes'];
    $session_timeout_minutes = $_POST['session_timeout_minutes'];
    
    $stmt = $conn->prepare("UPDATE security_settings SET 
        password_min_length = ?, 
        require_special_chars = ?, 
        require_numbers = ?, 
        require_uppercase = ?, 
        max_login_attempts = ?, 
        lockout_time_minutes = ?, 
        session_timeout_minutes = ?");
    
    $stmt->bind_param("iiiiiii", 
        $password_min_length, 
        $require_special_chars, 
        $require_numbers, 
        $require_uppercase, 
        $max_login_attempts, 
        $lockout_time_minutes, 
        $session_timeout_minutes
    );
    
    if ($stmt->execute()) {
        $message = "Security settings updated successfully!";
        $result = $conn->query("SELECT * FROM security_settings LIMIT 1");
        if ($result) {
            $settings = $result->fetch_assoc();
        }
    } else {
        $message = "Error updating security settings: " . $conn->error;
    }
}

// Check if login_attempts table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
if ($tableCheck->num_rows == 0) {
    $createTable = "CREATE TABLE login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NULL,
        username VARCHAR(255) NOT NULL,
        email VARCHAR(255) NOT NULL,
        success BOOLEAN DEFAULT FALSE,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        ip_address VARCHAR(45) NULL,
        INDEX idx_attempt_time (attempt_time),
        INDEX idx_success (success),
        INDEX idx_user_id (user_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    )";
    $conn->query($createTable);
} else {
    // Check if user_id column exists, if not add it
    $columnCheck = $conn->query("SHOW COLUMNS FROM login_attempts LIKE 'user_id'");
    if ($columnCheck->num_rows == 0) {
        $conn->query("ALTER TABLE login_attempts ADD COLUMN user_id INT NULL AFTER id");
        $conn->query("ALTER TABLE login_attempts ADD INDEX idx_user_id (user_id)");
        $conn->query("ALTER TABLE login_attempts ADD FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL");
        
        // Try to match existing records with users
        $conn->query("UPDATE login_attempts la 
                     INNER JOIN users u ON (la.username = u.username OR la.email = u.email) 
                     SET la.user_id = u.id 
                     WHERE la.user_id IS NULL");
    }
    
    // Check if ip_address column exists
    $columnCheck = $conn->query("SHOW COLUMNS FROM login_attempts LIKE 'ip_address'");
    if ($columnCheck->num_rows == 0) {
        $conn->query("ALTER TABLE login_attempts ADD COLUMN ip_address VARCHAR(45) NULL");
    }
}

// Handle delete single attempt
if (isset($_POST['delete_attempt'])) {
    $attempt_id = $_POST['attempt_id'];
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE id = ?");
    $stmt->bind_param("i", $attempt_id);
    if ($stmt->execute()) {
        $message = "Login attempt deleted successfully!";
    } else {
        $message = "Error deleting login attempt: " . $conn->error;
    }
}

// Handle clear all attempts
if (isset($_POST['clear_all_attempts'])) {
    if ($conn->query("DELETE FROM login_attempts")) {
        $message = "All login attempts cleared successfully!";
    } else {
        $message = "Error clearing login attempts: " . $conn->error;
    }
}

// Handle clear failed attempts only
if (isset($_POST['clear_failed_attempts'])) {
    if ($conn->query("DELETE FROM login_attempts WHERE success = 0")) {
        $message = "Failed login attempts cleared successfully!";
    } else {
        $message = "Error clearing failed attempts: " . $conn->error;
    }
}

// Handle clear old attempts (older than 30 days)
if (isset($_POST['clear_old_attempts'])) {
    if ($conn->query("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 30 DAY)")) {
        $message = "Old login attempts cleared successfully!";
    } else {
        $message = "Error clearing old attempts: " . $conn->error;
    }
}

// Get filter and sort parameters
$search = isset($_GET['search']) ? $_GET['search'] : '';
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$sort_by = isset($_GET['sort']) ? $_GET['sort'] : 'attempt_time';
$sort_order = isset($_GET['order']) ? $_GET['order'] : 'DESC';

// Build query with filters and JOIN with users table
$query = "SELECT la.*, u.fullname, u.id as actual_user_id 
          FROM login_attempts la 
          LEFT JOIN users u ON la.user_id = u.id 
          WHERE 1=1";

if (!empty($search)) {
    $search_term = '%' . $conn->real_escape_string($search) . '%';
    $query .= " AND (la.username LIKE '$search_term' OR la.email LIKE '$search_term' OR u.fullname LIKE '$search_term')";
}

if ($status_filter !== '') {
    $query .= " AND la.success = " . ($status_filter === 'success' ? '1' : '0');
}

// Validate sort column
$allowed_sorts = ['username', 'email', 'success', 'attempt_time', 'fullname'];
if (!in_array($sort_by, $allowed_sorts)) {
    $sort_by = 'attempt_time';
}

// Validate sort order
if (!in_array(strtoupper($sort_order), ['ASC', 'DESC'])) {
    $sort_order = 'DESC';
}

// Adjust sort column for JOIN
$sort_column = $sort_by;
if ($sort_by === 'fullname') {
    $sort_column = 'u.fullname';
} else {
    $sort_column = 'la.' . $sort_by;
}

$query .= " ORDER BY $sort_column $sort_order LIMIT 50";

// Get login attempts
$login_attempts = [];
$result = $conn->query($query);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $login_attempts[] = $row;
    }
}

// Calculate stats
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $result ? $result->fetch_assoc()['total'] : 0;

// ✅ REMOVED: The automatic test data insertion code has been completely removed
// Login attempts will now only show real authentication data

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$failed_attempts = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 1 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$successful_logins = $result ? $result->fetch_assoc()['total'] : 0;

// Check if sessions table exists
$tableCheck = $conn->query("SHOW TABLES LIKE 'sessions'");
if ($tableCheck->num_rows == 0) {
    $createTable = "CREATE TABLE sessions (
        id VARCHAR(255) PRIMARY KEY,
        user_id INT NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        user_agent TEXT,
        last_activity TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        INDEX idx_last_activity (last_activity)
    )";
    $conn->query($createTable);
}

// Get active sessions
$result = $conn->query("SELECT COUNT(*) as total FROM sessions WHERE last_activity > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$active_sessions = $result && $result->num_rows > 0 ? $result->fetch_assoc()['total'] : 0;

// Helper function to get sort icon
function getSortIcon($column, $current_sort, $current_order) {
    if ($column === $current_sort) {
        return $current_order === 'ASC' ? '↑' : '↓';
    }
    return '⇅';
}

// Helper function to toggle sort order
function toggleOrder($current_order) {
    return $current_order === 'ASC' ? 'DESC' : 'ASC';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Control - FinTrack Admin</title>
    <link rel="icon" type="image/png" href="../logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
    * {
        margin: 0;
        padding: 0;
        box-sizing: border-box;
    }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
        color: #2d3748;
        line-height: 1.6;
    }

    /* Layout */
    .admin-container {
        display: flex;
        min-height: 100vh;
    }

    .main-content {
        flex: 1;
        margin-left: 280px;
        padding: 30px 40px;
    }

    /* Sidebar */
    .sidebar {
        width: 280px;
        background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
        color: white;
        position: fixed;
        height: 100vh;
        overflow-y: auto;
        box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        z-index: 1000;
    }

    .logo {
        padding: 30px 25px;
        margin-bottom: 20px;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }

    .logo-text {
        font-size: 28px;
        font-weight: 800;
        letter-spacing: -0.5px;
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .logo-icon {
        width: 42px;
        height: 42px;
        background: rgba(255, 255, 255, 0.2);
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
    }

    .logo-subtitle {
        font-size: 12px;
        opacity: 0.75;
        margin-top: 5px;
        font-weight: 500;
        letter-spacing: 1px;
        text-transform: uppercase;
    }

    /* Navigation */
    .nav-section {
        padding: 15px 25px 10px;
        font-size: 11px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        opacity: 0.6;
    }

    .nav-item {
        padding: 14px 25px;
        display: flex;
        align-items: center;
        cursor: pointer;
        transition: all 0.3s ease;
        margin: 2px 12px;
        border-radius: 10px;
        text-decoration: none;
        color: rgba(255, 255, 255, 0.85);
        position: relative;
    }

    .nav-item:hover {
        background-color: rgba(255, 255, 255, 0.15);
        color: white;
        transform: translateX(3px);
    }

    .nav-item.active {
        background-color: rgba(255, 255, 255, 0.2);
        color: white;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .nav-item.active::before {
        content: '';
        position: absolute;
        left: 0;
        top: 50%;
        transform: translateY(-50%);
        width: 4px;
        height: 24px;
        background: white;
        border-radius: 0 4px 4px 0;
    }

    .nav-item i {
        margin-right: 12px;
        width: 20px;
        text-align: center;
        font-size: 16px;
    }

    .nav-item span {
        font-size: 14px;
        font-weight: 500;
    }

    /* Header */
    .header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 40px;
        padding: 25px 30px;
        background: white;
        border-radius: 16px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
    }

    .page-title {
        font-size: 32px;
        font-weight: 700;
        color: #1a202c;
        letter-spacing: -0.5px;
    }

    .page-subtitle {
        font-size: 14px;
        color: #718096;
        margin-top: 5px;
        font-weight: 400;
    }

    /* User Info */
    .user-info {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .user-avatar {
        width: 48px;
        height: 48px;
        border-radius: 12px;
        border: 2px solid #e2e8f0;
    }

    .user-details {
        display: flex;
        flex-direction: column;
    }

    .user-name {
        font-weight: 600;
        font-size: 15px;
        color: #2d3748;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    .user-email {
        font-size: 13px;
        color: #718096;
    }

    .admin-badge {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 4px 10px;
        border-radius: 6px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.5px;
        text-transform: uppercase;
    }

    /* Dashboard Cards */
    .dashboard-cards {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(260px, 1fr));
        gap: 25px;
        margin-bottom: 40px;
    }
    
    .stat-card {
        background: white;
        border-radius: 16px;
        padding: 25px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        transition: all 0.3s ease;
        border: 1px solid #f0f4f8;
        position: relative;
        overflow: hidden;
    }
    
    .stat-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: var(--card-color);
    }
    
    .stat-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    }
    
    .stat-card-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        margin-bottom: 20px;
    }
    
    .stat-icon {
        width: 52px;
        height: 52px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 22px;
        color: white;
        background: var(--card-color);
    }
    
    .stat-value {
        font-size: 36px;
        font-weight: 700;
        color: #1a202c;
        line-height: 1;
        margin-bottom: 8px;
    }
    
    .stat-label {
        font-size: 14px;
        color: #718096;
        font-weight: 500;
    }
    
    .stat-trend {
        font-size: 13px;
        margin-top: 12px;
        padding-top: 12px;
        border-top: 1px solid #f0f4f8;
        font-weight: 500;
    }
    
    .stat-trend i {
        margin-right: 4px;
    }
    
    /* Alerts */
    .alert {
        padding: 18px 24px;
        margin-bottom: 30px;
        border-radius: 12px;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 12px;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        animation: slideIn 0.3s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }

    .alert-success {
        background-color: #c6f6d5;
        color: #22543d;
        border-left: 4px solid #38a169;
    }

    .alert-danger {
        background-color: #fed7d7;
        color: #742a2a;
        border-left: 4px solid #e53e3e;
    }

    .alert i {
        font-size: 20px;
    }

    /* Data Card */
    .data-card {
        background: white;
        border-radius: 16px;
        padding: 30px;
        box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
        margin-bottom: 30px;
        border: 1px solid #f0f4f8;
    }

    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 25px;
        padding-bottom: 20px;
        border-bottom: 2px solid #f7fafc;
    }

    .card-title {
        font-weight: 700;
        font-size: 20px;
        color: #1a202c;
        letter-spacing: -0.3px;
    }

    /* Forms */
    .form-row {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 25px;
        margin-bottom: 25px;
    }

    .form-group {
        margin-bottom: 0;
    }

    .form-group label {
        display: block;
        margin-bottom: 10px;
        font-weight: 600;
        font-size: 14px;
        color: #2d3748;
    }

    .form-control {
        width: 100%;
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
        font-family: 'Inter', sans-serif;
    }

    .form-control:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* Checkbox Group */
    .checkbox-group {
        background: #f7fafc;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
    }

    .checkbox-group-title {
        font-weight: 600;
        font-size: 14px;
        color: #2d3748;
        margin-bottom: 15px;
    }

    .form-check {
        display: flex;
        align-items: center;
        padding: 12px;
        margin-bottom: 8px;
        border-radius: 8px;
        transition: all 0.2s ease;
    }

    .form-check:hover {
        background: white;
    }

    .form-check-input {
        width: 20px;
        height: 20px;
        margin-right: 12px;
        cursor: pointer;
        accent-color: #667eea;
    }

    .form-check label {
        cursor: pointer;
        font-weight: 500;
        font-size: 14px;
        color: #4a5568;
        margin: 0;
    }

    /* Search and Filter Bar */
    .filter-bar {
        display: flex;
        gap: 15px;
        margin-bottom: 25px;
        flex-wrap: wrap;
        align-items: center;
    }

    .search-box {
        flex: 1;
        min-width: 250px;
        position: relative;
    }

    .search-box input {
        width: 100%;
        padding: 12px 16px 12px 45px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        transition: all 0.3s ease;
    }

    .search-box input:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .search-box i {
        position: absolute;
        left: 16px;
        top: 50%;
        transform: translateY(-50%);
        color: #718096;
    }

    .filter-select {
        padding: 12px 16px;
        border: 2px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        cursor: pointer;
        background: white;
        transition: all 0.3s ease;
        min-width: 150px;
    }

    .filter-select:focus {
        outline: none;
        border-color: #667eea;
        box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    /* Buttons */
    .btn {
        padding: 12px 24px;
        border-radius: 10px;
        border: none;
        font-weight: 600;
        font-size: 14px;
        cursor: pointer;
        transition: all 0.3s ease;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }

    .btn-primary {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
    }

    .btn-primary:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
    }

    .btn-danger {
        background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
        color: white;
        box-shadow: 0 4px 12px rgba(245, 87, 108, 0.3);
    }

    .btn-danger:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(245, 87, 108, 0.4);
    }

    .btn-warning {
        background: linear-gradient(135deg, #ffeaa7 0%, #fdcb6e 100%);
        color: #2d3748;
        box-shadow: 0 4px 12px rgba(253, 203, 110, 0.3);
    }

    .btn-warning:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(253, 203, 110, 0.4);
    }

    .btn-secondary {
        background: #e2e8f0;
        color: #2d3748;
    }

    .btn-secondary:hover {
        background: #cbd5e0;
        transform: translateY(-2px);
    }

    .btn-sm {
        padding: 8px 16px;
        font-size: 13px;
    }

    .btn i {
        font-size: 14px;
    }

    .action-buttons {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
    }

    /* Tables */
    .table-responsive {
        overflow-x: auto;
    }

    table {
        width: 100%;
        border-collapse: separate;
        border-spacing: 0;
    }

    th {
        padding: 14px 16px;
        text-align: left;
        font-weight: 600;
        font-size: 13px;
        color: #4a5568;
        background: #f7fafc;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        cursor: pointer;
        user-select: none;
        white-space: nowrap;
    }

    th:hover {
        background: #edf2f7;
    }

    th:first-child {
        border-radius: 10px 0 0 10px;
    }

    th:last-child {
        border-radius: 0 10px 10px 0;
    }

    .sort-icon {
        margin-left: 5px;
        opacity: 0.5;
    }

    td {
        padding: 16px;
        border-bottom: 1px solid #f0f4f8;
        font-size: 14px;
        color: #2d3748;
    }

    tr:last-child td {
        border-bottom: none;
    }

    tr:hover td {
        background-color: #f8fafc;
    }

    /* User Cell */
    .user-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }

    .user-cell img {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        border: 2px solid #f0f4f8;
    }

    .user-cell-info {
        display: flex;
        flex-direction: column;
    }

    .user-cell-name {
        font-weight: 600;
        color: #1a202c;
    }

    .user-cell-email {
        font-size: 12px;
        color: #a0aec0;
    }

    /* Status Badges */
    .status-badge {
        padding: 6px 14px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.3px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
    }

    .status-success {
        background-color: #c6f6d5;
        color: #22543d;
    }

    .status-failed {
        background-color: #fed7d7;
        color: #742a2a;
    }

    /* Empty State */
    .empty-state {
        text-align: center;
        padding: 60px 20px;
        color: #718096;
    }

    .empty-state i {
        font-size: 48px;
        margin-bottom: 16px;
        opacity: 0.3;
    }

    .empty-state-text {
        font-size: 16px;
        font-weight: 500;
    }

    /* Modal */
    .modal {
        display: none;
        position: fixed;
        z-index: 2000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        animation: fadeIn 0.3s ease;
    }

    .modal.show {
        display: flex;
        align-items: center;
        justify-content: center;
    }

    @keyframes fadeIn {
        from { opacity: 0; }
        to { opacity: 1; }
    }

    .modal-content {
        background: white;
        border-radius: 16px;
        padding: 30px;
        max-width: 500px;
        width: 90%;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        animation: slideUp 0.3s ease;
    }

    @keyframes slideUp {
        from {
            transform: translateY(50px);
            opacity: 0;
        }
        to {
            transform: translateY(0);
            opacity: 1;
        }
    }

    .modal-header {
        margin-bottom: 20px;
    }

    .modal-title {
        font-size: 22px;
        font-weight: 700;
        color: #1a202c;
        display: flex;
        align-items: center;
        gap: 10px;
    }

    .modal-body {
        margin-bottom: 25px;
        color: #4a5568;
        line-height: 1.6;
    }

    .modal-footer {
        display: flex;
        gap: 10px;
        justify-content: flex-end;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .dashboard-cards {
            grid-template-columns: repeat(2, 1fr);
        }
    }

    @media (max-width: 768px) {
        .sidebar {
            width: 70px;
        }

        .main-content {
            margin-left: 70px;
            padding: 20px;
        }

        .logo-text,
        .logo-subtitle,
        .nav-item span,
        .nav-section {
            display: none;
        }

        .logo {
            padding: 20px 0;
            text-align: center;
        }

        .nav-item {
            justify-content: center;
            padding: 15px 0;
            margin: 2px 8px;
        }

        .nav-item i {
            margin-right: 0;
        }

        .dashboard-cards {
            grid-template-columns: 1fr;
        }

        .header {
            flex-direction: column;
            gap: 20px;
            text-align: center;
        }

        .form-row {
            grid-template-columns: 1fr;
        }

        .filter-bar {
            flex-direction: column;
        }

        .search-box {
            width: 100%;
        }

        .action-buttons {
            flex-direction: column;
        }

        .action-buttons .btn {
            width: 100%;
            justify-content: center;
        }

        .user-cell {
            flex-direction: column;
            align-items: flex-start;
        }
    }

    @media (max-width: 480px) {
        .main-content {
            padding: 15px;
        }

        .header {
            padding: 20px;
        }

        .page-title {
            font-size: 24px;
        }

        .data-card {
            padding: 20px;
        }

        .stat-card {
            padding: 20px;
        }

        .stat-value {
            font-size: 28px;
        }
    }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <div class="logo">
                <div class="logo-text">
                    <div class="logo-icon"><i class="fas fa-chart-line"></i></div>
                    <div>FinTrack<div class="logo-subtitle">Admin Panel</div></div>
                </div>
            </div>
            <div class="nav-section">Main Menu</div>
            <a href="dashboard.php" class="nav-item"><i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a>
            <a href="user_management.php" class="nav-item"><i class="fas fa-users"></i><span>User Management</span></a>
            <a href="security_control.php" class="nav-item active"><i class="fas fa-shield-alt"></i><span>Security Control</span></a>
            <a href="user_approval.php" class="nav-item"><i class="fas fa-check-circle"></i><span>User Approval</span></a>
            <a href="account_ownership.php" class="nav-item"><i class="fas fa-user-tag"></i><span>Account Ownership</span></a>
            <a href="../logout.php" class="nav-item"><i class="fas fa-sign-out-alt"></i><span>Logout</span></a>
        </div>
        <div class="main-content">
            <div class="header">
                <div>
                    <div class="page-title">Security Control</div>
                    <div class="page-subtitle">Manage system security settings and monitor login activity</div>
                </div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=667eea&color=fff&bold=true" alt="User Avatar" class="user-avatar">
                    <div class="user-details">
                        <div class="user-name"><?php echo htmlspecialchars($fullname); ?><span class="admin-badge">Admin</span></div>
                        <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
            </div>
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                    <i class="fas <?php echo strpos($message, 'Error') !== false ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            <div class="dashboard-cards">
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $total_users; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-shield-alt"></i> System protected
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $failed_attempts; ?></div>
                            <div class="stat-label">Failed Logins (24h)</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                    </div>
                    <div class="stat-trend" style="color: #f5576c;">
                        <i class="fas fa-exclamation-circle"></i> Monitored
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $successful_logins; ?></div>
                            <div class="stat-label">Successful Logins (24h)</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-user-check"></i> Active users
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value"><?php echo $active_sessions; ?></div>
                            <div class="stat-label">Active Sessions (1h)</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-chart-line"></i> Real-time data
                    </div>
                </div>
            </div>
            <div class="data-card">
                <div class="card-header">
                    <div><div class="card-title">Security Settings</div><div class="page-subtitle">Configure password policies and authentication rules</div></div>
                </div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password_min_length"><i class="fas fa-key"></i> Minimum Password Length</label>
                            <input type="number" id="password_min_length" name="password_min_length" class="form-control" value="<?php echo isset($settings['password_min_length']) ? $settings['password_min_length'] : 8; ?>" min="6" max="20">
                        </div>
                        <div class="form-group">
                            <label for="max_login_attempts"><i class="fas fa-lock"></i> Max Login Attempts Before Lockout</label>
                            <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control" value="<?php echo isset($settings['max_login_attempts']) ? $settings['max_login_attempts'] : 5; ?>" min="1" max="10">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label for="lockout_time_minutes"><i class="fas fa-user-lock"></i> Account Lockout Duration (minutes)</label>
                            <input type="number" id="lockout_time_minutes" name="lockout_time_minutes" class="form-control" value="<?php echo isset($settings['lockout_time_minutes']) ? $settings['lockout_time_minutes'] : 30; ?>" min="5" max="1440">
                        </div>
                        <div class="form-group">
                            <label for="session_timeout_minutes"><i class="fas fa-hourglass-half"></i> Session Timeout (minutes)</label>
                            <input type="number" id="session_timeout_minutes" name="session_timeout_minutes" class="form-control" value="<?php echo isset($settings['session_timeout_minutes']) ? $settings['session_timeout_minutes'] : 60; ?>" min="5" max="1440">
                        </div>
                    </div>
                    <div class="checkbox-group">
                        <div class="checkbox-group-title"><i class="fas fa-check-double"></i> Password Requirements</div>
                        <div class="form-check">
                            <input type="checkbox" id="require_special_chars" name="require_special_chars" class="form-check-input" <?php echo (isset($settings['require_special_chars']) && $settings['require_special_chars']) ? 'checked' : ''; ?>>
                            <label for="require_special_chars">Require Special Characters (!@#$%^&*)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="require_numbers" name="require_numbers" class="form-check-input" <?php echo (isset($settings['require_numbers']) && $settings['require_numbers']) ? 'checked' : ''; ?>>
                            <label for="require_numbers">Require Numbers (0-9)</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="require_uppercase" name="require_uppercase" class="form-check-input" <?php echo (isset($settings['require_uppercase']) && $settings['require_uppercase']) ? 'checked' : ''; ?>>
                            <label for="require_uppercase">Require Uppercase Letters (A-Z)</label>
                        </div>
                    </div>
                    <button type="submit" name="update_settings" class="btn btn-primary"><i class="fas fa-save"></i> Update Security Settings</button>
                </form>
            </div>
            <div class="data-card">
                <div class="card-header">
                    <div><div class="card-title">Recent Login Attempts</div><div class="page-subtitle">Monitor authentication activity and detect suspicious behavior</div></div>
                </div>
                
                <form method="GET" class="filter-bar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" name="search" placeholder="Search by name, username or email..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="success" <?php echo $status_filter === 'success' ? 'selected' : ''; ?>>Success Only</option>
                        <option value="failed" <?php echo $status_filter === 'failed' ? 'selected' : ''; ?>>Failed Only</option>
                    </select>
                    <button type="submit" class="btn btn-secondary btn-sm"><i class="fas fa-filter"></i> Filter</button>
                    <?php if (!empty($search) || $status_filter !== ''): ?>
                        <a href="security_control.php" class="btn btn-secondary btn-sm"><i class="fas fa-times"></i> Clear</a>
                    <?php endif; ?>
                </form>

                <div class="action-buttons">
                    <button onclick="showModal('clearFailedModal')" class="btn btn-warning btn-sm">
                        <i class="fas fa-broom"></i> Clear Failed Attempts
                    </button>
                    <button onclick="showModal('clearOldModal')" class="btn btn-warning btn-sm">
                        <i class="fas fa-calendar-times"></i> Clear Old Attempts (30+ days)
                    </button>
                    <button onclick="showModal('clearAllModal')" class="btn btn-danger btn-sm">
                        <i class="fas fa-trash-alt"></i> Clear All Attempts
                    </button>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th onclick="sortTable('fullname')">
                                    User <span class="sort-icon"><?php echo getSortIcon('fullname', $sort_by, $sort_order); ?></span>
                                </th>
                                <th onclick="sortTable('username')">
                                    Username <span class="sort-icon"><?php echo getSortIcon('username', $sort_by, $sort_order); ?></span>
                                </th>
                                <th onclick="sortTable('email')">
                                    Email <span class="sort-icon"><?php echo getSortIcon('email', $sort_by, $sort_order); ?></span>
                                </th>
                                <th onclick="sortTable('success')">
                                    Status <span class="sort-icon"><?php echo getSortIcon('success', $sort_by, $sort_order); ?></span>
                                </th>
                                <th onclick="sortTable('attempt_time')">
                                    Time <span class="sort-icon"><?php echo getSortIcon('attempt_time', $sort_by, $sort_order); ?></span>
                                </th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($login_attempts)): ?>
                                <tr><td colspan="6"><div class="empty-state"><i class="fas fa-clipboard-list"></i><div class="empty-state-text">No login attempts found</div></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($login_attempts as $attempt): ?>
                                    <tr>
                                        <td>
                                            <div class="user-cell">
                                                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($attempt['fullname'] ?? $attempt['username']); ?>&background=667eea&color=fff&bold=true" alt="Avatar">
                                                <div class="user-cell-info">
                                                    <div class="user-cell-name"><?php echo htmlspecialchars($attempt['fullname'] ?? $attempt['username']); ?></div>
                                                    <div class="user-cell-email"><?php echo isset($attempt['actual_user_id']) ? 'ID: ' . $attempt['actual_user_id'] : 'Unknown User'; ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td style="font-weight: 600;"><i class="fas fa-user" style="color: #667eea; margin-right: 8px;"></i><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td style="color: #718096;"><i class="fas fa-envelope" style="margin-right: 8px;"></i><?php echo htmlspecialchars($attempt['email']); ?></td>
                                        <td><span class="status-badge <?php echo $attempt['success'] ? 'status-success' : 'status-failed'; ?>"><i class="fas <?php echo $attempt['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i><?php echo $attempt['success'] ? 'Success' : 'Failed'; ?></span></td>
                                        <td style="color: #718096;"><i class="fas fa-clock" style="margin-right: 8px;"></i><?php echo date('M d, Y H:i:s', strtotime($attempt['attempt_time'])); ?></td>
                                        <td>
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="attempt_id" value="<?php echo $attempt['id']; ?>">
                                                <button type="submit" name="delete_attempt" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this login attempt?')">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Modals -->
    <div id="clearFailedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-exclamation-triangle" style="color: #f5576c;"></i> Clear Failed Attempts</div>
            </div>
            <div class="modal-body">
                Are you sure you want to delete all failed login attempts? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button onclick="hideModal('clearFailedModal')" class="btn btn-secondary">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_failed_attempts" class="btn btn-danger">
                        <i class="fas fa-trash"></i> Clear Failed
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="clearOldModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-calendar-times" style="color: #fdcb6e;"></i> Clear Old Attempts</div>
            </div>
            <div class="modal-body">
                Are you sure you want to delete all login attempts older than 30 days? This action cannot be undone.
            </div>
            <div class="modal-footer">
                <button onclick="hideModal('clearOldModal')" class="btn btn-secondary">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_old_attempts" class="btn btn-warning">
                        <i class="fas fa-calendar-times"></i> Clear Old
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div id="clearAllModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title"><i class="fas fa-exclamation-circle" style="color: #e53e3e;"></i> Clear All Attempts</div>
            </div>
            <div class="modal-body">
                <strong>Warning:</strong> This will permanently delete ALL login attempts from the database. This action cannot be undone. Are you absolutely sure?
            </div>
            <div class="modal-footer">
                <button onclick="hideModal('clearAllModal')" class="btn btn-secondary">Cancel</button>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="clear_all_attempts" class="btn btn-danger">
                        <i class="fas fa-trash-alt"></i> Delete All
                    </button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function showModal(modalId) {
            document.getElementById(modalId).classList.add('show');
        }

        function hideModal(modalId) {
            document.getElementById(modalId).classList.remove('show');
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.classList.remove('show');
            }
        }

        function sortTable(column) {
            const urlParams = new URLSearchParams(window.location.search);
            const currentSort = urlParams.get('sort');
            const currentOrder = urlParams.get('order') || 'DESC';
            
            // If clicking the same column, toggle order
            if (currentSort === column) {
                urlParams.set('order', currentOrder === 'ASC' ? 'DESC' : 'ASC');
            } else {
                // New column, default to DESC
                urlParams.set('sort', column);
                urlParams.set('order', 'DESC');
            }
            
            window.location.search = urlParams.toString();
        }
    </script>
</body>
</html>