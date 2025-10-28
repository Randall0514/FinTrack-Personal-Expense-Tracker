<?php
session_start();
require '../vendor/autoload.php';
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

$secret_key = "your_secret_key_here_change_this_in_production";

if (!isset($_COOKIE['admin_jwt_token'])) {
    if (isset($_COOKIE['jwt_token'])) {
        header("Location: ../dist/admin/dashboard.php");
        exit;
    }
    header("Location: ../login.php");
    exit;
}

$jwt = $_COOKIE['admin_jwt_token'];
try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    
    if (!isset($decoded->data->is_admin) || $decoded->data->is_admin !== true) {
        setcookie("admin_jwt_token", "", time() - 3600, "/", "", false, true);
        header("Location: ../dist/admin/dashboard.php");
        exit;
    }
    
    $user_id = $decoded->data->id;
    $fullname = $decoded->data->fullname;
    $email = $decoded->data->email;
} catch (Exception $e) {
    setcookie("admin_jwt_token", "", time() - 3600, "/", "", false, true);
    header("Location: ../login.php");
    exit;
}

require_once '../config/dbconfig_password.php';

$message = '';
$message_type = '';

// Create system_settings table if it doesn't exist
$conn->query("CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(100) UNIQUE,
    setting_value VARCHAR(255),
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
)");

// Toggle automatic approval
if (isset($_POST['toggle_auto_approval'])) {
    $new_status = $_POST['auto_approval_status'];
    
    $stmt = $conn->prepare("INSERT INTO system_settings (setting_key, setting_value) VALUES ('auto_approval', ?) 
                           ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->bind_param("ss", $new_status, $new_status);
    
    if ($stmt->execute()) {
        if ($new_status === '1') {
            // Auto-approve all pending users when turning on automatic approval
            $conn->query("UPDATE users SET is_approved = 1 WHERE is_approved = 0 OR is_approved IS NULL");
            $message = "Automatic approval enabled! All pending users have been approved.";
        } else {
            $message = "Automatic approval disabled. New users will require manual approval.";
        }
        $message_type = "success";
    } else {
        $message = "Error updating approval setting: " . $conn->error;
        $message_type = "error";
    }
}

// Get current auto-approval setting
$auto_approval_enabled = false;
$auto_approval_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_approval'");
if ($auto_approval_result && $auto_approval_result->num_rows > 0) {
    $setting = $auto_approval_result->fetch_assoc();
    $auto_approval_enabled = ($setting['setting_value'] === '1');
}

// Approve single user
if (isset($_POST['approve_user'])) {
    $target_user_id = $_POST['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $message = "User approved successfully!";
        $message_type = "success";
    } else {
        $message = "Error approving user: " . $conn->error;
        $message_type = "error";
    }
}

// Reject user
if (isset($_POST['reject_user'])) {
    $target_user_id = $_POST['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET is_approved = 0 WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $message = "User approval rejected!";
        $message_type = "success";
    } else {
        $message = "Error rejecting user: " . $conn->error;
        $message_type = "error";
    }
}

// Approve all pending users
if (isset($_POST['approve_all'])) {
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE is_approved = 0 OR is_approved IS NULL");
    
    if ($stmt->execute()) {
        $message = "All pending users approved successfully!";
        $message_type = "success";
    } else {
        $message = "Error approving all users: " . $conn->error;
        $message_type = "error";
    }
}

// Get pending users
$result = $conn->query("SELECT * FROM users WHERE is_approved = 0 OR is_approved IS NULL ORDER BY id DESC");
$pending_users = $result->fetch_all(MYSQLI_ASSOC);

// Get approved users
$result = $conn->query("SELECT * FROM users WHERE is_approved = 1 ORDER BY id DESC LIMIT 10");
$approved_users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approval - FinTrack Admin</title>
    <link rel="icon" type="image/png" href="../logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/user_approval.css">
</head>
<body>
    <div class="admin-container">
        <aside class="sidebar">
            <div class="logo">
                <div class="logo-text">
                    <div class="logo-icon"><i class="fas fa-chart-line"></i></div>
                    <div>
                        FinTrack
                        <div class="logo-subtitle">Admin Panel</div>
                    </div>
                </div>
            </div>

            <div class="nav-section">Main Menu</div>
            <a href="dashboard.php" class="nav-item">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="user_management.php" class="nav-item">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="security_control.php" class="nav-item">
                <i class="fas fa-shield-alt"></i>
                <span>Security Control</span>
            </a>
            <a href="user_approval.php" class="nav-item active">
                <i class="fas fa-check-circle"></i>
                <span>User Approval</span>
            </a>
            <a href="account_ownership.php" class="nav-item">
                <i class="fas fa-user-tag"></i>
                <span>Account Ownership</span>
            </a>
            <a href="#" onclick="confirmLogout(event)" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </aside>
        
        <div class="main-content">
            <div class="header">
                <div>
                    <div class="page-title">User Approval</div>
                    <div class="page-subtitle">Review and approve pending user registrations</div>
                </div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=667eea&color=fff&bold=true" alt="User Avatar" class="user-avatar">
                    <div class="user-details">
                        <div class="user-name">
                            <?php echo htmlspecialchars($fullname); ?>
                            <span class="admin-badge">Admin</span>
                        </div>
                        <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
            </div>
            
            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Auto-Approval Toggle Card -->
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white;">
                            <i class="fas fa-cog"></i>
                        </div>
                        <div>
                            <div class="card-title">Approval Settings</div>
                            <div class="page-subtitle">Configure automatic or manual user approval</div>
                        </div>
                    </div>
                </div>
                
                <div class="approval-setting-container">
                    <div class="approval-setting-info">
                        <div class="approval-mode-title">
                            <i class="fas fa-<?php echo $auto_approval_enabled ? 'bolt' : 'hand-paper'; ?>"></i>
                            <?php echo $auto_approval_enabled ? 'Automatic Approval' : 'Manual Approval'; ?>
                        </div>
                        <div class="approval-mode-description">
                            <?php if ($auto_approval_enabled): ?>
                                New users are automatically approved upon registration. They can access the system immediately.
                            <?php else: ?>
                                New users must wait for admin approval before accessing the system. Review pending users below.
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <form method="POST" class="toggle-form">
                        <input type="hidden" name="auto_approval_status" value="<?php echo $auto_approval_enabled ? '0' : '1'; ?>">
                        <label class="toggle-switch">
                            <input type="checkbox" <?php echo $auto_approval_enabled ? 'checked' : ''; ?> onchange="this.form.submit()">
                            <span class="toggle-slider"></span>
                        </label>
                        <input type="hidden" name="toggle_auto_approval" value="1">
                    </form>
                </div>
                
                <div class="approval-status-badge <?php echo $auto_approval_enabled ? 'status-active' : 'status-inactive'; ?>">
                    <i class="fas fa-circle"></i>
                    <?php echo $auto_approval_enabled ? 'Auto-Approval Active' : 'Manual Approval Active'; ?>
                </div>
            </div>
            
            <!-- Pending Approval Card - Only show when manual approval is enabled -->
            <?php if (!$auto_approval_enabled): ?>
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                            <i class="fas fa-user-clock"></i>
                        </div>
                        <div>
                            <div class="card-title">
                                Pending Approval
                                <span class="card-count"><?php echo count($pending_users); ?></span>
                            </div>
                            <div class="page-subtitle">Users waiting for approval</div>
                        </div>
                    </div>
                    <?php if (count($pending_users) > 0): ?>
                        <form method="POST">
                            <button type="submit" name="approve_all" class="btn btn-success">
                                <i class="fas fa-check-double"></i> Approve All
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if (count($pending_users) > 0): ?>
                    <?php foreach ($pending_users as $user): ?>
                        <div class="user-card">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=f093fb&color=fff&bold=true" alt="User Avatar" class="user-avatar-card">
                            <div class="user-details">
                                <div class="user-fullname"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                <div class="user-email-text"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="user-meta">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </div>
                            <div class="user-actions">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="approve_user" class="btn btn-success btn-sm">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger btn-sm">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="empty-text">All Caught Up!</div>
                        <div class="empty-subtext">No pending users to approve at the moment</div>
                    </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title-wrapper">
                        <div class="card-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); color: white;">
                            <i class="fas fa-user-check"></i>
                        </div>
                        <div>
                            <div class="card-title">Recently Approved Users</div>
                            <div class="page-subtitle">Latest approved user accounts</div>
                        </div>
                    </div>
                </div>
                
                <?php if (count($approved_users) > 0): ?>
                    <?php foreach ($approved_users as $user): ?>
                        <div class="user-card">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=43e97b&color=fff&bold=true" alt="User Avatar" class="user-avatar-card">
                            <div class="user-details">
                                <div class="user-fullname"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                <div class="user-email-text"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div class="user-meta">
                                    <i class="fas fa-user"></i>
                                    <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </div>
                            <?php if (!$auto_approval_enabled): ?>
                            <div class="user-actions">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger btn-sm">
                                        <i class="fas fa-ban"></i> Revoke
                                    </button>
                                </form>
                            </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <div class="empty-icon">
                            <i class="fas fa-users"></i>
                        </div>
                        <div class="empty-text">No Approved Users Yet</div>
                        <div class="empty-subtext">Approved users will appear here</div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
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