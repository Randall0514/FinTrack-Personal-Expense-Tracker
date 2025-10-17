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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FinTrack</title>
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
            color: #48bb78;
            font-weight: 500;
        }
        
        .stat-trend i {
            margin-right: 4px;
        }
        
        .data-table {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 12px rgba(0, 0, 0, 0.04);
            margin-bottom: 30px;
            border: 1px solid #f0f4f8;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 2px solid #f7fafc;
        }
        
        .table-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
            letter-spacing: -0.3px;
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
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
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
        
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-block;
        }
        
        .status-approved {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background-color: #feebc8;
            color: #7c2d12;
        }
        
        .status-enabled {
            background-color: #bee3f8;
            color: #2c5282;
        }
        
        .status-disabled {
            background-color: #fed7d7;
            color: #742a2a;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 12px;
            border-radius: 8px;
            transition: all 0.2s;
            color: #718096;
        }
        
        .action-btn:hover {
            background-color: #f0f4f8;
            color: #667eea;
        }
        
        .action-btn.delete:hover {
            background-color: #fff5f5;
            color: #e53e3e;
        }
        
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
            
            .dashboard-cards {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
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
            <a href="dashboard.php" class="nav-item active">
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
                <div>
                    <div class="page-title">Dashboard Overview</div>
                    <div class="page-subtitle">Welcome back! Here's what's happening today.</div>
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
            
            <div class="dashboard-cards">
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users");
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                                ?>
                            </div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> 12% from last month
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0");
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                                ?>
                            </div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="stat-trend" style="color: #f5576c;">
                        <i class="fas fa-exclamation-circle"></i> Requires attention
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                                ?>
                            </div>
                            <div class="stat-label">Admin Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-shield-alt"></i> System protected
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 1");
                                $row = $result->fetch_assoc();
                                echo $row['total'];
                                ?>
                            </div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-trend">
                        <i class="fas fa-arrow-up"></i> 8% increase
                    </div>
                </div>
            </div>
            
            <div class="data-table">
                <div class="table-header">
                    <div>
                        <div class="table-title">Recent User Registrations</div>
                        <div class="page-subtitle">Latest user activity and registrations</div>
                    </div>
                    <button class="btn btn-primary">
                        <i class="fas fa-users"></i>
                        View All Users
                    </button>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $result = $conn->query("SELECT * FROM users ORDER BY id DESC LIMIT 5");
                        while ($row = $result->fetch_assoc()) {
                            $status = isset($row['is_approved']) ? (int)$row['is_approved'] : 0;
                            $statusClass = $status === 1 ? 'status-approved' : 'status-pending';
                            $statusText = $status === 1 ? 'Approved' : 'Pending';
                            
                            echo "<tr>";
                            echo "<td>
                                    <div style='display: flex; align-items: center; gap: 12px;'>
                                        <img src='https://ui-avatars.com/api/?name=" . urlencode($row['fullname']) . "&background=667eea&color=fff&bold=true' style='width: 36px; height: 36px; border-radius: 10px;' />
                                        <div style='font-weight: 600;'>" . htmlspecialchars($row['fullname']) . "</div>
                                    </div>
                                  </td>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td style='color: #718096;'>" . htmlspecialchars($row['email']) . "</td>";
                            echo "<td><span class='status {$statusClass}'>{$statusText}</span></td>";
                            echo "<td>
                                    <button class='action-btn'><i class='fas fa-edit'></i></button>
                                    <button class='action-btn delete'><i class='fas fa-trash'></i></button>
                                  </td>";
                            echo "</tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="data-table">
                <div class="table-header">
                    <div>
                        <div class="table-title">Security Control</div>
                        <div class="page-subtitle">System security features and configurations</div>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>Security Feature</th>
                            <th>Status</th>
                            <th>Last Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td style="font-weight: 600;">Two-Factor Authentication</td>
                            <td><span class="status status-enabled">Enabled</span></td>
                            <td style="color: #718096;">June 15, 2023</td>
                            <td><button class="action-btn"><i class="fas fa-cog"></i></button></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Password Policy</td>
                            <td><span class="status status-enabled">Enabled</span></td>
                            <td style="color: #718096;">June 10, 2023</td>
                            <td><button class="action-btn"><i class="fas fa-cog"></i></button></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Login Attempts Limit</td>
                            <td><span class="status status-enabled">Enabled</span></td>
                            <td style="color: #718096;">June 5, 2023</td>
                            <td><button class="action-btn"><i class="fas fa-cog"></i></button></td>
                        </tr>
                        <tr>
                            <td style="font-weight: 600;">Session Timeout</td>
                            <td><span class="status status-disabled">Disabled</span></td>
                            <td style="color: #718096;">May 20, 2023</td>
                            <td><button class="action-btn"><i class="fas fa-cog"></i></button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>