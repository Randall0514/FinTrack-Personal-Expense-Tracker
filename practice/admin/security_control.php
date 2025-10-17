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
    // Create security_settings table
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
        // Insert default settings
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
    // Insert default settings if table is empty
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
        
        // Refresh settings
        $result = $conn->query("SELECT * FROM security_settings LIMIT 1");
        if ($result) {
            $settings = $result->fetch_assoc();
        }
    } else {
        $message = "Error updating security settings: " . $conn->error;
    }
}

// Check if login_attempts table exists, if not create it
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Security Control - FinTrack Admin</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fb;
            color: #333;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 250px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
        }
        
        .logo {
            padding: 0 20px;
            margin-bottom: 30px;
            font-size: 24px;
            font-weight: 700;
            text-align: center;
        }
        
        .logo span {
            display: block;
            font-size: 12px;
            opacity: 0.8;
            margin-top: 5px;
        }
        
        .nav-item {
            padding: 12px 20px;
            display: flex;
            align-items: center;
            cursor: pointer;
            transition: all 0.3s;
            margin-bottom: 5px;
            text-decoration: none;
            color: white;
        }
        
        .nav-item:hover, .nav-item.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .nav-item i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }
        
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
        }
        
        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 1px solid #e0e0e0;
        }
        
        .page-title {
            font-size: 24px;
            font-weight: 700;
        }
        
        .user-info {
            display: flex;
            align-items: center;
        }
        
        .user-info img {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            margin-right: 10px;
        }
        
        .user-name {
            font-weight: 600;
        }
        
        .admin-badge {
            background-color: #764ba2;
            color: white;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            margin-left: 10px;
        }
        
        .alert {
            padding: 15px;
            margin-bottom: 20px;
            border-radius: 5px;
        }
        
        .alert-success {
            background-color: #e6f7ee;
            color: #0bab64;
            border: 1px solid #0bab64;
        }
        
        .alert-danger {
            background-color: #ffebee;
            color: #ff5252;
            border: 1px solid #ff5252;
        }
        
        .data-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .card-title {
            font-weight: 600;
            font-size: 18px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .form-check {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        
        .form-check-input {
            margin-right: 10px;
        }
        
        .btn {
            padding: 10px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
            border: none;
        }
        
        .btn-primary {
            background-color: #667eea;
            color: white;
        }
        
        .btn-primary:hover {
            background-color: #5a6ecc;
        }
        
        .table-responsive {
            overflow-x: auto;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th, td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #eee;
        }
        
        th {
            font-weight: 600;
            color: #555;
            background-color: #f9f9f9;
        }
        
        tr:last-child td {
            border-bottom: none;
        }
        
        .status-success {
            color: #0bab64;
        }
        
        .status-failed {
            color: #ff5252;
        }
        
        .grid-container {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            display: flex;
            flex-direction: column;
        }
        
        .stat-title {
            font-size: 14px;
            color: #666;
            margin-bottom: 10px;
        }
        
        .stat-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-icon {
            align-self: flex-end;
            font-size: 24px;
            color: #667eea;
            margin-top: -40px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
                padding: 20px 0;
            }
            
            .logo {
                padding: 0 10px;
                font-size: 18px;
            }
            
            .logo span, .nav-item span {
                display: none;
            }
            
            .nav-item {
                padding: 15px 0;
                justify-content: center;
            }
            
            .nav-item i {
                margin-right: 0;
            }
            
            .main-content {
                margin-left: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="sidebar">
            <div class="logo">
                FinTrack <span>Admin Panel</span>
            </div>
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="user_management.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="security_control.php" class="nav-item active">
                <i class="fas fa-shield-alt"></i>
                <span>Security Control</span>
            </a>
            <a href="user_approval.php" class="nav-item">
                <i class="fas fa-check-circle"></i>
                <span>User Approval</span>
            </a>
            <a href="account_ownership.php" class="nav-item">
                <i class="fas fa-user-tag"></i>
                <span>Account Ownership</span>
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="page-title">Security Control</div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=764ba2&color=fff" alt="User Avatar">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($fullname); ?> <span class="admin-badge">Admin</span></div>
                        <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
            <div class="grid-container">
                <div class="stat-card">
                    <div class="stat-title">Total Users</div>
                    <?php 
                    $result = $conn->query("SELECT COUNT(*) as total FROM users");
                    $total_users = $result ? $result->fetch_assoc()['total'] : 0;
                    ?>
                    <div class="stat-value"><?php echo $total_users; ?></div>
                    <div class="stat-icon"><i class="fas fa-users"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Failed Login Attempts (24h)</div>
                    <?php 
                    $result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $failed_attempts = $result ? $result->fetch_assoc()['total'] : 0;
                    ?>
                    <div class="stat-value"><?php echo $failed_attempts; ?></div>
                    <div class="stat-icon"><i class="fas fa-exclamation-triangle"></i></div>
                </div>
                
                <div class="stat-card">
                    <div class="stat-title">Successful Logins (24h)</div>
                    <?php 
                    $result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 1 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
                    $successful_logins = $result ? $result->fetch_assoc()['total'] : 0;
                    ?>
                    <div class="stat-value"><?php echo $successful_logins; ?></div>
                    <div class="stat-icon"><i class="fas fa-check-circle"></i></div>
                </div>
            </div>
            
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title">Security Settings</div>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="password_min_length">Minimum Password Length</label>
                        <input type="number" id="password_min_length" name="password_min_length" class="form-control" value="<?php echo isset($settings['password_min_length']) ? $settings['password_min_length'] : 8; ?>" min="6" max="20">
                    </div>
                    
                    <div class="form-group">
                        <label>Password Requirements</label>
                        <div class="form-check">
                            <input type="checkbox" id="require_special_chars" name="require_special_chars" class="form-check-input" <?php echo (isset($settings['require_special_chars']) && $settings['require_special_chars']) ? 'checked' : ''; ?>>
                            <label for="require_special_chars">Require Special Characters</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="require_numbers" name="require_numbers" class="form-check-input" <?php echo (isset($settings['require_numbers']) && $settings['require_numbers']) ? 'checked' : ''; ?>>
                            <label for="require_numbers">Require Numbers</label>
                        </div>
                        <div class="form-check">
                            <input type="checkbox" id="require_uppercase" name="require_uppercase" class="form-check-input" <?php echo (isset($settings['require_uppercase']) && $settings['require_uppercase']) ? 'checked' : ''; ?>>
                            <label for="require_uppercase">Require Uppercase Letters</label>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="max_login_attempts">Maximum Login Attempts Before Lockout</label>
                        <input type="number" id="max_login_attempts" name="max_login_attempts" class="form-control" value="<?php echo isset($settings['max_login_attempts']) ? $settings['max_login_attempts'] : 5; ?>" min="1" max="10">
                    </div>
                    
                    <div class="form-group">
                        <label for="lockout_time_minutes">Account Lockout Duration (minutes)</label>
                        <input type="number" id="lockout_time_minutes" name="lockout_time_minutes" class="form-control" value="<?php echo isset($settings['lockout_time_minutes']) ? $settings['lockout_time_minutes'] : 30; ?>" min="5" max="1440">
                    </div>
                    
                    <div class="form-group">
                        <label for="session_timeout_minutes">Session Timeout (minutes)</label>
                        <input type="number" id="session_timeout_minutes" name="session_timeout_minutes" class="form-control" value="<?php echo isset($settings['session_timeout_minutes']) ? $settings['session_timeout_minutes'] : 60; ?>" min="5" max="1440">
                    </div>
                    
                    <button type="submit" name="update_settings" class="btn btn-primary">Update Security Settings</button>
                </form>
            </div>
            
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title">Recent Login Attempts</div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Username</th>
                                <th>IP Address</th>
                                <th>Status</th>
                                <th>Time</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($login_attempts)): ?>
                                <tr>
                                    <td colspan="4" style="text-align: center;">No login attempts recorded</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($login_attempts as $attempt): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($attempt['username']); ?></td>
                                        <td><?php echo htmlspecialchars($attempt['ip_address']); ?></td>
                                        <td class="<?php echo $attempt['success'] ? 'status-success' : 'status-failed'; ?>">
                                            <?php echo $attempt['success'] ? 'Success' : 'Failed'; ?>
                                        </td>
                                        <td><?php echo date('Y-m-d H:i:s', strtotime($attempt['attempt_time'])); ?></td>
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