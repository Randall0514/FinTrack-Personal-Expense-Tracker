<?php
session_start();
require '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Check if token exists
if (!isset($_COOKIE['jwt_token'])) {
    header("Location: ../login.php");
    exit;
}

// Verify token
$secret_key = "your_secret_key_here_change_this_in_production";
try {
    $decoded = JWT::decode($_COOKIE['jwt_token'], new Key($secret_key, 'HS256'));
    
    // Check if user is admin
    if (!isset($decoded->data->is_admin) || $decoded->data->is_admin !== true) {
        header("Location: ../dist/admin/dashboard.php");
        exit;
    }
    
    $user_id = $decoded->data->id;
    $fullname = $decoded->data->fullname;
    $email = $decoded->data->email;
} catch (Exception $e) {
    // Invalid token
    setcookie("jwt_token", "", time() - 3600, "/", "localhost", false, true);
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
        username VARCHAR(255) NOT NULL,
        ip_address VARCHAR(45) NOT NULL,
        success BOOLEAN DEFAULT FALSE,
        attempt_time TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_attempt_time (attempt_time),
        INDEX idx_success (success)
    )";
    $conn->query($createTable);
}

// Get login attempts
$login_attempts = [];
$result = $conn->query("SELECT * FROM login_attempts ORDER BY attempt_time DESC LIMIT 50");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $login_attempts[] = $row;
    }
}

// Calculate stats
$result = $conn->query("SELECT COUNT(*) as total FROM users");
$total_users = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$failed_attempts = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 1 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$successful_logins = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE attempt_time > DATE_SUB(NOW(), INTERVAL 1 HOUR)");
$active_sessions = $result ? $result->fetch_assoc()['total'] : 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Control - FinTrack Admin</title>
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

    .trend-up {
        color: #48bb78;
    }

    .trend-down {
        color: #f56565;
    }

    .trend-neutral {
        color: #4299e1;
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

    .btn-primary i {
        font-size: 16px;
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
    }

    th:first-child {
        border-radius: 10px 0 0 10px;
    }

    th:last-child {
        border-radius: 0 10px 10px 0;
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
                        <div><div class="stat-value"><?php echo $total_users; ?></div><div class="stat-label">Total Users</div></div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"><i class="fas fa-users"></i></div>
                    </div>
                    <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> System protected</div>
                </div>
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-card-header">
                        <div><div class="stat-value"><?php echo $failed_attempts; ?></div><div class="stat-label">Failed Logins (24h)</div></div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);"><i class="fas fa-exclamation-triangle"></i></div>
                    </div>
                    <div class="stat-trend trend-down"><i class="fas fa-shield-alt"></i> Monitored</div>
                </div>
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-card-header">
                        <div><div class="stat-value"><?php echo $successful_logins; ?></div><div class="stat-label">Successful Logins (24h)</div></div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);"><i class="fas fa-check-circle"></i></div>
                    </div>
                    <div class="stat-trend trend-up"><i class="fas fa-arrow-up"></i> Active users</div>
                </div>
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-card-header">
                        <div><div class="stat-value"><?php echo $active_sessions; ?></div><div class="stat-label">Active Sessions (1h)</div></div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);"><i class="fas fa-clock"></i></div>
                    </div>
                    <div class="stat-trend trend-neutral"><i class="fas fa-clock"></i> Real-time data</div>
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
                <div class="table-responsive">
                    <table>
                        <thead><tr><th>Username</th><th>IP Address</th><th>Status</th><th>Time</th></tr></thead>
                        <tbody>
                            <?php if (empty($login_attempts)): ?>
                                <tr><td colspan="4"><div class="empty-state"><i class="fas fa-clipboard-list"></i><div class="empty-state-text">No login attempts recorded yet</div></div></td></tr>
                            <?php else: ?>
                                <?php foreach ($login_attempts as $attempt): ?>
                                    <tr>
                                        <td style="font-weight: 600;"><i class="fas fa-user" style="color: #667eea; margin-right: 8px;"></i><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td style="color: #718096;"><i class="fas fa-network-wired" style="margin-right: 8px;"></i><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                        <td><span class="status-badge <?php echo $attempt['success'] ? 'status-success' : 'status-failed'; ?>"><i class="fas <?php echo $attempt['success'] ? 'fa-check-circle' : 'fa-times-circle'; ?>"></i><?php echo $attempt['success'] ? 'Success' : 'Failed'; ?></span></td>
                                        <td style="color: #718096;"><i class="fas fa-clock" style="margin-right: 8px;"></i><?php echo date('M d, Y H:i:s', strtotime($attempt['attempt_time'])); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>