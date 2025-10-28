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
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    if ($conn->query($createTable)) {
        $conn->query("INSERT INTO security_settings 
            (password_min_length, require_special_chars, require_numbers, require_uppercase) 
            VALUES (8, 1, 1, 1)");
    }
}

// Get current settings
$result = $conn->query("SELECT * FROM security_settings LIMIT 1");
if ($result && $result->num_rows > 0) {
    $settings = $result->fetch_assoc();
} else {
    $conn->query("INSERT INTO security_settings 
        (password_min_length, require_special_chars, require_numbers, require_uppercase) 
        VALUES (8, 1, 1, 1)");
    
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
    
    $stmt = $conn->prepare("UPDATE security_settings SET 
        password_min_length = ?, 
        require_special_chars = ?, 
        require_numbers = ?, 
        require_uppercase = ?");
    
    $stmt->bind_param("iiii", 
        $password_min_length, 
        $require_special_chars, 
        $require_numbers, 
        $require_uppercase
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

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 0 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$failed_attempts = $result ? $result->fetch_assoc()['total'] : 0;

$result = $conn->query("SELECT COUNT(*) as total FROM login_attempts WHERE success = 1 AND attempt_time > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
$successful_logins = $result ? $result->fetch_assoc()['total'] : 0;

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
    <link rel="stylesheet" href="style/security_control.css">

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
            <a href="#" onclick="confirmLogout(event)" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
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
            </div>
            <div class="data-card">
                <div class="card-header">
                    <div><div class="card-title">Password Security Settings</div><div class="page-subtitle">Configure password policies and requirements</div></div>
                </div>
                <form method="POST">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="password_min_length"><i class="fas fa-key"></i> Minimum Password Length</label>
                            <input type="number" id="password_min_length" name="password_min_length" class="form-control" value="<?php echo isset($settings['password_min_length']) ? $settings['password_min_length'] : 8; ?>" min="6" max="20">
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

    <script src="script/security_control.js"></script>
    <script>
        function confirmLogout(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../logout.php';
            }
        }
    </script>
</body>
</html>