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

// Process approval actions
$message = '';

// Approve user
if (isset($_POST['approve_user'])) {
    $target_user_id = $_POST['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $message = "User approved successfully!";
    } else {
        $message = "Error approving user: " . $conn->error;
    }
}

// Reject user
if (isset($_POST['reject_user'])) {
    $target_user_id = $_POST['user_id'];
    
    $stmt = $conn->prepare("UPDATE users SET is_approved = 0 WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $message = "User approval rejected!";
    } else {
        $message = "Error rejecting user: " . $conn->error;
    }
}

// Approve all pending users
if (isset($_POST['approve_all'])) {
    $stmt = $conn->prepare("UPDATE users SET is_approved = 1 WHERE is_approved = 0 OR is_approved IS NULL");
    
    if ($stmt->execute()) {
        $message = "All pending users approved successfully!";
    } else {
        $message = "Error approving all users: " . $conn->error;
    }
}

// Get pending users
$result = $conn->query("SELECT * FROM users WHERE is_approved = 0 OR is_approved IS NULL ORDER BY id DESC");
$pending_users = $result->fetch_all(MYSQLI_ASSOC);

// Get recently approved users
$result = $conn->query("SELECT * FROM users WHERE is_approved = 1 ORDER BY id DESC LIMIT 10");
$approved_users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Approval - FinTrack Admin</title>
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
        
        .btn {
            padding: 8px 15px;
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
        
        .btn-success {
            background-color: #0bab64;
            color: white;
        }
        
        .btn-success:hover {
            background-color: #099a58;
        }
        
        .btn-danger {
            background-color: #ff5252;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #ff0000;
        }
        
        .user-card {
            display: flex;
            align-items: center;
            padding: 15px;
            border-bottom: 1px solid #eee;
        }
        
        .user-card:last-child {
            border-bottom: none;
        }
        
        .user-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            margin-right: 15px;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-fullname {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .user-email {
            color: #666;
            font-size: 14px;
        }
        
        .user-actions {
            display: flex;
        }
        
        .user-actions form {
            margin-left: 10px;
        }
        
        .empty-state {
            text-align: center;
            padding: 30px;
            color: #666;
        }
        
        .empty-state i {
            font-size: 48px;
            margin-bottom: 15px;
            color: #ddd;
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
            <a href="../dist/admin/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="page-title">User Approval</div>
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
            
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title">Pending Approval (<?php echo count($pending_users); ?>)</div>
                    <?php if (count($pending_users) > 0): ?>
                        <form method="POST">
                            <button type="submit" name="approve_all" class="btn btn-success">
                                <i class="fas fa-check-circle"></i> Approve All
                            </button>
                        </form>
                    <?php endif; ?>
                </div>
                
                <?php if (count($pending_users) > 0): ?>
                    <?php foreach ($pending_users as $user): ?>
                        <div class="user-card">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=764ba2&color=fff" alt="User Avatar" class="user-avatar">
                            <div class="user-details">
                                <div class="user-fullname"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div style="font-size: 12px; color: #888; margin-top: 5px;">
                                    Username: <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </div>
                            <div class="user-actions">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="approve_user" class="btn btn-success">
                                        <i class="fas fa-check"></i> Approve
                                    </button>
                                </form>
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger">
                                        <i class="fas fa-times"></i> Reject
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-check-circle"></i>
                        <p>No pending users to approve</p>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title">Recently Approved Users</div>
                </div>
                
                <?php if (count($approved_users) > 0): ?>
                    <?php foreach ($approved_users as $user): ?>
                        <div class="user-card">
                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=0bab64&color=fff" alt="User Avatar" class="user-avatar">
                            <div class="user-details">
                                <div class="user-fullname"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                <div class="user-email"><?php echo htmlspecialchars($user['email']); ?></div>
                                <div style="font-size: 12px; color: #888; margin-top: 5px;">
                                    Username: <?php echo htmlspecialchars($user['username']); ?>
                                </div>
                            </div>
                            <div class="user-actions">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger">
                                        <i class="fas fa-ban"></i> Revoke
                                    </button>
                                </form>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-users"></i>
                        <p>No approved users found</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>