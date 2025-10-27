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
$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - FinTrack</title>
    <link rel="icon" type="image/png" href="../logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/dashboard.css">
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
            <a href="dashboard.php" class="nav-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
                <i class="fas fa-tachometer-alt"></i>
                <span>Dashboard</span>
            </a>
            <a href="user_management.php" class="nav-item <?php echo ($current_page == 'user_management.php') ? 'active' : ''; ?>">
                <i class="fas fa-users"></i>
                <span>User Management</span>
            </a>
            <a href="security_control.php" class="nav-item <?php echo ($current_page == 'security_control.php') ? 'active' : ''; ?>">
                <i class="fas fa-shield-alt"></i>
                <span>Security Control</span>
            </a>
            <a href="user_approval.php" class="nav-item <?php echo ($current_page == 'user_approval.php') ? 'active' : ''; ?>">
                <i class="fas fa-check-circle"></i>
                <span>User Approval</span>
            </a>
            <a href="account_ownership.php" class="nav-item <?php echo ($current_page == 'account_ownership.php') ? 'active' : ''; ?>">
                <i class="fas fa-user-tag"></i>
                <span>Account Ownership</span>
            </a>
            <a href="../logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </aside>

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
                    <button class="btn btn-primary" onclick="window.location.href='user_management.php'">
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
                        <div class="table-title">Recent Login Attempts</div>
                        <div class="page-subtitle">Monitor authentication activity and detect suspicious behavior</div>
                    </div>
                    <button class="btn btn-primary" onclick="window.location.href='security_control.php'">
                        <i class="fas fa-shield-alt"></i>
                        View Security Control
                    </button>
                </div>

                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        // Check if login_attempts table exists
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
                            // Get recent login attempts with user information
                            $result = $conn->query("SELECT la.*, u.fullname, u.id as actual_user_id 
                                                   FROM login_attempts la 
                                                   LEFT JOIN users u ON la.user_id = u.id 
                                                   ORDER BY la.attempt_time DESC 
                                                   LIMIT 10");
                            
                            if ($result && $result->num_rows > 0) {
                                while ($row = $result->fetch_assoc()) {
                                    $statusClass = $row['success'] ? 'status-approved' : 'status-pending';
                                    $statusText = $row['success'] ? 'Success' : 'Failed';
                                    $statusIcon = $row['success'] ? 'fa-check-circle' : 'fa-times-circle';
                                    
                                    echo "<tr>";
                                    echo "<td>
                                            <div style='display: flex; align-items: center; gap: 12px;'>
                                                <img src='https://ui-avatars.com/api/?name=" . urlencode($row['fullname'] ?? $row['username']) . "&background=667eea&color=fff&bold=true' style='width: 36px; height: 36px; border-radius: 10px;' />
                                                <div>
                                                    <div style='font-weight: 600;'>" . htmlspecialchars($row['fullname'] ?? $row['username']) . "</div>
                                                    <div style='font-size: 12px; color: #a0aec0;'>" . (isset($row['actual_user_id']) ? 'ID: ' . $row['actual_user_id'] : 'Unknown User') . "</div>
                                                </div>
                                            </div>
                                          </td>";
                                    echo "<td><i class='fas fa-user' style='color: #667eea; margin-right: 8px;'></i>" . htmlspecialchars($row['username']) . "</td>";
                                    echo "<td style='color: #718096;'><i class='fas fa-envelope' style='margin-right: 8px;'></i>" . htmlspecialchars($row['email']) . "</td>";
                                    echo "<td><span class='status {$statusClass}'><i class='fas {$statusIcon}' style='margin-right: 5px;'></i>{$statusText}</span></td>";
                                    echo "<td style='color: #718096;'><i class='fas fa-clock' style='margin-right: 8px;'></i>" . date('M d, Y H:i:s', strtotime($row['attempt_time'])) . "</td>";
                                    echo "</tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align: center; padding: 40px; color: #718096;'>
                                        <i class='fas fa-clipboard-list' style='font-size: 36px; opacity: 0.3; display: block; margin-bottom: 12px;'></i>
                                        <div style='font-weight: 500;'>No login attempts found</div>
                                      </td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; padding: 40px; color: #718096;'>
                                    <i class='fas fa-database' style='font-size: 36px; opacity: 0.3; display: block; margin-bottom: 12px;'></i>
                                    <div style='font-weight: 500;'>Login attempts tracking not configured</div>
                                  </td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>