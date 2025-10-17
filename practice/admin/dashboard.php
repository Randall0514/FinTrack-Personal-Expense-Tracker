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
        
        .dashboard-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .card {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-weight: 600;
            font-size: 16px;
        }
        
        .card-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
        }
        
        .bg-purple {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .bg-blue {
            background: linear-gradient(135deg, #5271ff 0%, #3755e3 100%);
        }
        
        .bg-green {
            background: linear-gradient(135deg, #4cb8c4 0%, #3cd3ad 100%);
        }
        
        .bg-orange {
            background: linear-gradient(135deg, #ff9966 0%, #ff5e62 100%);
        }
        
        .card-value {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .card-label {
            font-size: 14px;
            color: #777;
        }
        
        .data-table {
            background-color: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            margin-bottom: 30px;
        }
        
        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .table-title {
            font-weight: 600;
            font-size: 18px;
        }
        
        .table-actions button {
            background-color: #667eea;
            color: white;
            border: none;
            padding: 8px 15px;
            border-radius: 5px;
            cursor: pointer;
            font-weight: 500;
            transition: all 0.3s;
        }
        
        .table-actions button:hover {
            background-color: #5a6ecc;
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
        
        .status {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .status-approved {
            background-color: #e6f7ee;
            color: #0bab64;
        }
        
        .status-pending {
            background-color: #fff8e6;
            color: #f7b500;
        }
        
        .status-rejected {
            background-color: #ffebee;
            color: #ff5252;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #667eea;
            margin-right: 10px;
            transition: all 0.3s;
        }
        
        .action-btn:hover {
            color: #5a6ecc;
        }
        
        .action-btn.delete {
            color: #ff5252;
        }
        
        .action-btn.delete:hover {
            color: #ff0000;
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
            <a href="dashboard.php" class="nav-item active" style="text-decoration: none; color: white;">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="user_management.php" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="security_control.php" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-shield-alt"></i>
                <span>Security Control</span>
            </a>
            <a href="user_approval.php" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-check-circle"></i>
                <span>User Approval</span>
            </a>
            <a href="account_ownership.php" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-user-tag"></i>
                <span>Account Ownership</span>
            </a>
            <a href="#" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-chart-bar"></i>
                <span>Reports</span>
            </a>
            <a href="#" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-cog"></i>
                <span>Settings</span>
            </a>
            <a href="../logout.php" class="nav-item" style="text-decoration: none; color: white;">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="page-title">Admin Dashboard</div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=764ba2&color=fff" alt="User Avatar">
                    <div>
                        <div class="user-name"><?php echo htmlspecialchars($fullname); ?> <span class="admin-badge">Admin</span></div>
                        <div class="user-email"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
            </div>
            
            <div class="dashboard-cards">
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Total Users</div>
                        <div class="card-icon bg-purple">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="card-value">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM users");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="card-label">Registered users</div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Pending Approvals</div>
                        <div class="card-icon bg-orange">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="card-value">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="card-label">Users awaiting approval</div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Admin Users</div>
                        <div class="card-icon bg-blue">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                    <div class="card-value">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="card-label">Administrator accounts</div>
                </div>
                
                <div class="card">
                    <div class="card-header">
                        <div class="card-title">Active Users</div>
                        <div class="card-icon bg-green">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="card-value">
                        <?php
                        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 1");
                        $row = $result->fetch_assoc();
                        echo $row['total'];
                        ?>
                    </div>
                    <div class="card-label">Approved user accounts</div>
                </div>
            </div>
            
            <div class="data-table">
                <div class="table-header">
                    <div class="table-title">Recent User Registrations</div>
                    <div class="table-actions">
                        <button>View All Users</button>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
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
                            echo "<td>" . $row['id'] . "</td>";
                            echo "<td>" . htmlspecialchars($row['fullname']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['username']) . "</td>";
                            echo "<td>" . htmlspecialchars($row['email']) . "</td>";
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
                    <div class="table-title">Security Control</div>
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
                            <td>Two-Factor Authentication</td>
                            <td><span class="status status-approved">Enabled</span></td>
                            <td>2023-06-15</td>
                            <td>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>Password Policy</td>
                            <td><span class="status status-approved">Enabled</span></td>
                            <td>2023-06-10</td>
                            <td>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>Login Attempts Limit</td>
                            <td><span class="status status-approved">Enabled</span></td>
                            <td>2023-06-05</td>
                            <td>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                        <tr>
                            <td>Session Timeout</td>
                            <td><span class="status status-pending">Disabled</span></td>
                            <td>2023-05-20</td>
                            <td>
                                <button class="action-btn"><i class="fas fa-edit"></i></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        // Simple navigation functionality
        document.querySelectorAll('.nav-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.nav-item').forEach(i => i.classList.remove('active'));
                this.classList.add('active');
            });
        });
    </script>
</body>
</html>