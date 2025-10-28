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

// Toggle admin status
if (isset($_POST['toggle_admin'])) {
    $target_user_id = $_POST['user_id'];
    $is_admin = $_POST['is_admin'] ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_admin, $target_user_id);
    
    if ($stmt->execute()) {
        $message = "Admin status updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating admin status: " . $conn->error;
        $message_type = "error";
    }
}

// Toggle approval status
if (isset($_POST['toggle_approval'])) {
    $target_user_id = $_POST['user_id'];
    $is_approved = $_POST['is_approved'] ? 0 : 1;
    
    $stmt = $conn->prepare("UPDATE users SET is_approved = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_approved, $target_user_id);
    
    if ($stmt->execute()) {
        $message = "Approval status updated successfully!";
        $message_type = "success";
    } else {
        $message = "Error updating approval status: " . $conn->error;
        $message_type = "error";
    }
}

// Delete user
if (isset($_POST['delete_user'])) {
    $target_user_id = $_POST['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $message = "User deleted successfully!";
        $message_type = "success";
    } else {
        $message = "Error deleting user: " . $conn->error;
        $message_type = "error";
    }
}

// Get all users
$result = $conn->query("SELECT * FROM users ORDER BY id DESC");
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get statistics
$total_users = count($users);
$approved_users = count(array_filter($users, function($u) { return isset($u['is_approved']) && $u['is_approved'] == 1; }));
$pending_users = $total_users - $approved_users;
$admin_users = count(array_filter($users, function($u) { return isset($u['is_admin']) && $u['is_admin'] == 1; }));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - FinTrack Admin</title>
    <link rel="icon" type="image/png" href="../logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" />
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
    <link rel="stylesheet" href="style/user_management.css">
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
            <a href="user_management.php" class="nav-item active">
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
            <a href="#" onclick="confirmLogout(event)" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </aside>
        
        <div class="main-content">
            <div class="header">
                <div>
                    <div class="page-title">User Management</div>
                    <div class="page-subtitle">Manage all users, permissions, and access control</div>
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
            
            <div class="stats-grid">
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="stat-content">
                        <div>
                            <div class="stat-value"><?php echo $total_users; ?></div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-content">
                        <div>
                            <div class="stat-value"><?php echo $approved_users; ?></div>
                            <div class="stat-label">Approved Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-content">
                        <div>
                            <div class="stat-value"><?php echo $pending_users; ?></div>
                            <div class="stat-label">Pending Approval</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-content">
                        <div>
                            <div class="stat-value"><?php echo $admin_users; ?></div>
                            <div class="stat-label">Admin Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="data-table">
                <div class="table-header">
                    <div>
                        <div class="table-title">All Users</div>
                        <div class="page-subtitle">Complete list of registered users</div>
                    </div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search by name, username, or email...">
                    </div>
                </div>
                
                <div class="table-wrapper">
                    <table id="usersTable">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Username</th>
                                <th>Email</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php
                                $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
                                $isApproved = isset($user['is_approved']) && $user['is_approved'] == 1;
                                ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=667eea&color=fff&bold=true" alt="Avatar">
                                            <div class="user-cell-info">
                                                <div class="user-cell-name"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                                <div class="user-cell-id">ID: <?php echo $user['id']; ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($user['username']); ?></td>
                                    <td style="color: #718096;"><?php echo htmlspecialchars($user['email']); ?></td>
                                    <td>
                                        <span class="status <?php echo $isAdmin ? 'status-admin' : 'status-user'; ?>">
                                            <?php echo $isAdmin ? 'Admin' : 'User'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="status <?php echo $isApproved ? 'status-approved' : 'status-pending'; ?>">
                                            <?php echo $isApproved ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="actions-cell">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="is_admin" value="<?php echo $isAdmin ? 1 : 0; ?>">
                                                <button type="submit" name="toggle_admin" class="action-btn" title="<?php echo $isAdmin ? 'Remove Admin' : 'Make Admin'; ?>">
                                                    <i class="fas <?php echo $isAdmin ? 'fa-user-minus' : 'fa-user-plus'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                <input type="hidden" name="is_approved" value="<?php echo $isApproved ? 1 : 0; ?>">
                                                <button type="submit" name="toggle_approval" class="action-btn" title="<?php echo $isApproved ? 'Revoke Approval' : 'Approve User'; ?>">
                                                    <i class="fas <?php echo $isApproved ? 'fa-times-circle' : 'fa-check-circle'; ?>"></i>
                                                </button>
                                            </form>
                                            
                                            <button class="action-btn delete" title="Delete User" onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username'], ENT_QUOTES); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Delete User</div>
                <span class="close" onclick="closeDeleteModal()">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete user <strong id="deleteUsername"></strong>?</p>
                <p style="color: #e53e3e; font-weight: 500;">⚠️ This action cannot be undone and will permanently remove all user data.</p>
            </div>
            <div class="modal-footer">
                <form method="POST" style="display: flex; gap: 12px;">
                    <input type="hidden" id="deleteUserId" name="user_id">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">
                        <i class="fas fa-trash"></i>
                        Delete User
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <script src="script/user_management.js"></script>
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