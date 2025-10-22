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
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
            color: #2d3748;
            line-height: 1.6;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 0;
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
        
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 30px 40px;
        }
        
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
        
        .alert {
            padding: 16px 20px;
            margin-bottom: 25px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
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
            border: 1px solid #9ae6b4;
        }
        
        .alert-success i {
            color: #38a169;
        }
        
        .alert-danger {
            background-color: #fed7d7;
            color: #742a2a;
            border: 1px solid #fc8181;
        }
        
        .alert-danger i {
            color: #e53e3e;
        }
        
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
        
        .card-title-wrapper {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .card-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .card-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
            letter-spacing: -0.3px;
        }
        
        .card-count {
            background: #f7fafc;
            color: #4a5568;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-left: 10px;
        }
        
        .btn {
            padding: 10px 20px;
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
        
        .btn-success {
            background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(67, 233, 123, 0.3);
        }
        
        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(67, 233, 123, 0.4);
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
        
        .btn-sm {
            padding: 8px 16px;
            font-size: 13px;
        }
        
        .user-card {
            display: flex;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid #f0f4f8;
            transition: all 0.2s ease;
        }
        
        .user-card:last-child {
            border-bottom: none;
        }
        
        .user-card:hover {
            background-color: #f8fafc;
            transform: translateX(4px);
        }
        
        .user-avatar-card {
            width: 56px;
            height: 56px;
            border-radius: 14px;
            margin-right: 18px;
            border: 2px solid #e2e8f0;
        }
        
        .user-details {
            flex: 1;
        }
        
        .user-fullname {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 6px;
            color: #1a202c;
        }
        
        .user-email-text {
            color: #718096;
            font-size: 14px;
            margin-bottom: 4px;
        }
        
        .user-meta {
            font-size: 13px;
            color: #a0aec0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .user-meta i {
            font-size: 11px;
        }
        
        .user-actions {
            display: flex;
            gap: 10px;
        }
        
        .user-actions form {
            margin: 0;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 30px;
            color: #a0aec0;
        }
        
        .empty-icon {
            width: 80px;
            height: 80px;
            background: linear-gradient(135deg, #f7fafc 0%, #edf2f7 100%);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
            color: #cbd5e0;
        }
        
        .empty-text {
            font-size: 16px;
            font-weight: 600;
            color: #718096;
            margin-bottom: 8px;
        }
        
        .empty-subtext {
            font-size: 14px;
            color: #a0aec0;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .logo-text, .logo-subtitle, .nav-item span, .nav-section {
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
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .user-card {
                flex-direction: column;
                text-align: center;
                gap: 15px;
            }
            
            .user-avatar-card {
                margin-right: 0;
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
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
        
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
                <div class="alert <?php echo strpos($message, 'Error') !== false ? 'alert-danger' : 'alert-success'; ?>">
                    <i class="fas <?php echo strpos($message, 'Error') !== false ? 'fa-exclamation-circle' : 'fa-check-circle'; ?>"></i>
                    <?php echo $message; ?>
                </div>
            <?php endif; ?>
            
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
                            <div class="user-actions">
                                <form method="POST">
                                    <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                    <button type="submit" name="reject_user" class="btn btn-danger btn-sm">
                                        <i class="fas fa-ban"></i> Revoke
                                    </button>
                                </form>
                            </div>
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
</body>
</html>