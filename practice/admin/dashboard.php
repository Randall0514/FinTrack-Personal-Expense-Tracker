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

$message = '';
$message_type = '';

// Toggle admin status
if (isset($_POST['toggle_admin'])) {
    $target_user_id = $_POST['user_id'];
    $is_admin = $_POST['is_admin'] ? 0 : 1;
    
    if ($target_user_id == $user_id && $is_admin == 0) {
        $message = "You cannot revoke your own admin privileges!";
        $message_type = "error";
    } else {
        if ($is_admin == 0) {
            $admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE is_admin = 1")->fetch_assoc()['count'];
            if ($admin_count <= 1) {
                $message = "Cannot revoke admin privileges. At least one admin must exist!";
                $message_type = "error";
            } else {
                $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
                $stmt->bind_param("ii", $is_admin, $target_user_id);
                if ($stmt->execute()) {
                    $message = "Admin status updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating admin status!";
                    $message_type = "error";
                }
            }
        } else {
            $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
            $stmt->bind_param("ii", $is_admin, $target_user_id);
            if ($stmt->execute()) {
                $message = "Admin status updated successfully!";
                $message_type = "success";
            } else {
                $message = "Error updating admin status!";
                $message_type = "error";
            }
        }
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
        $message = "Error updating approval status!";
        $message_type = "error";
    }
}

// Delete user
if (isset($_POST['delete_user'])) {
    $target_user_id = $_POST['user_id'];
    
    if ($target_user_id == $user_id) {
        $message = "You cannot delete your own account!";
        $message_type = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
        $stmt->bind_param("i", $target_user_id);
        
        if ($stmt->execute()) {
            $message = "User deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting user!";
            $message_type = "error";
        }
    }
}
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
            <a href="#" onclick="confirmLogout(event)" class="nav-item">
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

            <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?>">
                    <i class="fas fa-<?php echo $message_type === 'success' ? 'check-circle' : 'exclamation-circle'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <div class="dashboard-cards">
                <div class="stat-card" style="--card-color: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </div>
                            <div class="stat-label">Total Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <i class="fas fa-users"></i>
                        </div>
                    </div>
                    <div class="stat-trend"><i class="fas fa-arrow-up"></i> 12% from last month</div>
                </div>

                <div class="stat-card" style="--card-color: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </div>
                            <div class="stat-label">Pending Approvals</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);">
                            <i class="fas fa-user-clock"></i>
                        </div>
                    </div>
                    <div class="stat-trend" style="color: #f5576c;"><i class="fas fa-exclamation-circle"></i> Requires attention</div>
                </div>

                <div class="stat-card" style="--card-color: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_admin = 1");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </div>
                            <div class="stat-label">Admin Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                            <i class="fas fa-user-shield"></i>
                        </div>
                    </div>
                    <div class="stat-trend"><i class="fas fa-shield-alt"></i> System protected</div>
                </div>

                <div class="stat-card" style="--card-color: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                    <div class="stat-card-header">
                        <div>
                            <div class="stat-value">
                                <?php
                                $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 1");
                                echo $result->fetch_assoc()['total'];
                                ?>
                            </div>
                            <div class="stat-label">Active Users</div>
                        </div>
                        <div class="stat-icon" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                            <i class="fas fa-user-check"></i>
                        </div>
                    </div>
                    <div class="stat-trend"><i class="fas fa-arrow-up"></i> 8% increase</div>
                </div>
            </div>

            <div class="data-table">
                <div class="table-header">
                    <div>
                        <div class="table-title">Recent User Registrations</div>
                        <div class="page-subtitle">Latest user activity and registrations</div>
                    </div>
                    <button class="btn btn-primary" onclick="window.location.href='user_management.php'">
                        <i class="fas fa-users"></i> View All Users
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
                            $isAdmin = isset($row['is_admin']) ? (int)$row['is_admin'] : 0;
                            $statusClass = $status === 1 ? 'status-approved' : 'status-pending';
                            $statusText = $status === 1 ? 'Approved' : 'Pending';
                            
                            echo "<tr>
                                <td>
                                    <div style='display: flex; align-items: center; gap: 12px;'>
                                        <img src='https://ui-avatars.com/api/?name=" . urlencode($row['fullname']) . "&background=667eea&color=fff&bold=true' style='width: 36px; height: 36px; border-radius: 10px;' />
                                        <div style='font-weight: 600;'>" . htmlspecialchars($row['fullname']) . "</div>
                                    </div>
                                </td>
                                <td>" . htmlspecialchars($row['username']) . "</td>
                                <td style='color: #718096;'>" . htmlspecialchars($row['email']) . "</td>
                                <td><span class='status {$statusClass}'>{$statusText}</span></td>
                                <td>
                                    <div class='action-buttons'>
                                        <button class='action-btn' onclick='openApprovalModal(" . $row['id'] . ", \"" . htmlspecialchars($row['fullname'], ENT_QUOTES) . "\", " . $status . ")' title='Toggle Approval'>
                                            <i class='fas fa-user-check'></i>
                                        </button>
                                        <button class='action-btn' onclick='openAdminModal(" . $row['id'] . ", \"" . htmlspecialchars($row['fullname'], ENT_QUOTES) . "\", " . $isAdmin . ")' title='Toggle Admin'>
                                            <i class='fas fa-user-shield'></i>
                                        </button>
                                        <button class='action-btn delete' onclick='openDeleteModal(" . $row['id'] . ", \"" . htmlspecialchars($row['fullname'], ENT_QUOTES) . "\")' title='Delete'>
                                            <i class='fas fa-trash'></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>

            <div class="data-table">
                <div class="table-header">
                    <div>
                        <div class="table-title">Recent Login Attempts</div>
                        <div class="page-subtitle">Monitor authentication activity</div>
                    </div>
                    <button class="btn btn-primary" onclick="window.location.href='security_control.php'">
                        <i class="fas fa-shield-alt"></i> View Security Control
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
                        $tableCheck = $conn->query("SHOW TABLES LIKE 'login_attempts'");
                        if ($tableCheck && $tableCheck->num_rows > 0) {
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
                                    
                                    echo "<tr>
                                        <td>
                                            <div style='display: flex; align-items: center; gap: 12px;'>
                                                <img src='https://ui-avatars.com/api/?name=" . urlencode($row['fullname'] ?? $row['username']) . "&background=667eea&color=fff&bold=true' style='width: 36px; height: 36px; border-radius: 10px;' />
                                                <div>
                                                    <div style='font-weight: 600;'>" . htmlspecialchars($row['fullname'] ?? $row['username']) . "</div>
                                                    <div style='font-size: 12px; color: #a0aec0;'>" . (isset($row['actual_user_id']) ? 'ID: ' . $row['actual_user_id'] : 'Unknown User') . "</div>
                                                </div>
                                            </div>
                                        </td>
                                        <td><i class='fas fa-user' style='color: #667eea; margin-right: 8px;'></i>" . htmlspecialchars($row['username']) . "</td>
                                        <td style='color: #718096;'><i class='fas fa-envelope' style='margin-right: 8px;'></i>" . htmlspecialchars($row['email']) . "</td>
                                        <td><span class='status {$statusClass}'><i class='fas {$statusIcon}' style='margin-right: 5px;'></i>{$statusText}</span></td>
                                        <td style='color: #718096;'><i class='fas fa-clock' style='margin-right: 8px;'></i>" . date('M d, Y H:i:s', strtotime($row['attempt_time'])) . "</td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='5' style='text-align: center; padding: 40px;'>No login attempts found</td></tr>";
                            }
                        } else {
                            echo "<tr><td colspan='5' style='text-align: center; padding: 40px;'>Login tracking not configured</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Delete Modal -->
    <div id="deleteModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <div class="modal-icon delete"><i class="fas fa-trash-alt"></i></div>
                    Delete User
                </div>
                <span class="close" onclick="closeModal('deleteModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete this user?</p>
                <div class="user-info-modal">
                    <strong>User Name</strong>
                    <div id="deleteUserName"></div>
                </div>
                <div class="warning-text">
                    <i class="fas fa-exclamation-triangle"></i>
                    This action cannot be undone!
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" id="deleteUserId" name="user_id">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('deleteModal')">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger"><i class="fas fa-trash"></i> Delete</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Approval Modal -->
    <div id="approvalModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <div class="modal-icon approve"><i class="fas fa-user-check"></i></div>
                    <span id="approvalModalTitle">Toggle Approval</span>
                </div>
                <span class="close" onclick="closeModal('approvalModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p id="approvalModalText"></p>
                <div class="user-info-modal">
                    <strong>User Name</strong>
                    <div id="approvalUserName"></div>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" id="approvalUserId" name="user_id">
                    <input type="hidden" id="approvalCurrentStatus" name="is_approved">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('approvalModal')">Cancel</button>
                    <button type="submit" name="toggle_approval" class="btn btn-success"><i class="fas fa-check"></i> <span id="approvalBtnText">Approve</span></button>
                </form>
            </div>
        </div>
    </div>

    <!-- Admin Modal -->
    <div id="adminModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">
                    <div class="modal-icon admin"><i class="fas fa-user-shield"></i></div>
                    <span id="adminModalTitle">Toggle Admin</span>
                </div>
                <span class="close" onclick="closeModal('adminModal')">&times;</span>
            </div>
            <div class="modal-body">
                <p id="adminModalText"></p>
                <div class="user-info-modal">
                    <strong>User Name</strong>
                    <div id="adminUserName"></div>
                </div>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" id="adminUserId" name="user_id">
                    <input type="hidden" id="adminCurrentStatus" name="is_admin">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('adminModal')">Cancel</button>
                    <button type="submit" name="toggle_admin" class="btn btn-info"><i class="fas fa-shield-alt"></i> <span id="adminBtnText">Grant Admin</span></button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function openDeleteModal(userId, userName) {
            document.getElementById('deleteModal').style.display = 'block';
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUserName').textContent = userName;
        }

        function openApprovalModal(userId, userName, isApproved) {
            document.getElementById('approvalModal').style.display = 'block';
            document.getElementById('approvalUserId').value = userId;
            document.getElementById('approvalCurrentStatus').value = isApproved;
            document.getElementById('approvalUserName').textContent = userName;
            
            if (isApproved === 1) {
                document.getElementById('approvalModalText').textContent = 'Are you sure you want to revoke approval?';
                document.getElementById('approvalBtnText').textContent = 'Revoke';
            } else {
                document.getElementById('approvalModalText').textContent = 'Are you sure you want to approve this user?';
                document.getElementById('approvalBtnText').textContent = 'Approve';
            }
        }

        function openAdminModal(userId, userName, isAdmin) {
            document.getElementById('adminModal').style.display = 'block';
            document.getElementById('adminUserId').value = userId;
            document.getElementById('adminCurrentStatus').value = isAdmin;
            document.getElementById('adminUserName').textContent = userName;
            
            if (isAdmin === 1) {
                document.getElementById('adminModalText').textContent = 'Are you sure you want to revoke admin privileges?';
                document.getElementById('adminBtnText').textContent = 'Revoke Admin';
            } else {
                document.getElementById('adminModalText').textContent = 'Are you sure you want to grant admin privileges?';
                document.getElementById('adminBtnText').textContent = 'Grant Admin';
            }
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function confirmLogout(event) {
            event.preventDefault();
            if (confirm('Are you sure you want to logout?')) {
                window.location.href = '../logout.php';
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                event.target.style.display = 'none';
            }
        }

        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeModal('deleteModal');
                closeModal('approvalModal');
                closeModal('adminModal');
            }
        });
    </script>
</body>
</html>