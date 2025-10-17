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

// Process user actions
$message = '';

// Toggle admin status
if (isset($_POST['toggle_admin'])) {
    $target_user_id = $_POST['user_id'];
    $is_admin = $_POST['is_admin'] ? 0 : 1; // Toggle the value
    
    $stmt = $conn->prepare("UPDATE users SET is_admin = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_admin, $target_user_id);
    
    if ($stmt->execute()) {
        $message = "Admin status updated successfully!";
    } else {
        $message = "Error updating admin status: " . $conn->error;
    }
}

// Toggle approval status
if (isset($_POST['toggle_approval'])) {
    $target_user_id = $_POST['user_id'];
    $is_approved = $_POST['is_approved'] ? 0 : 1; // Toggle the value
    
    $stmt = $conn->prepare("UPDATE users SET is_approved = ? WHERE id = ?");
    $stmt->bind_param("ii", $is_approved, $target_user_id);
    
    if ($stmt->execute()) {
        $message = "Approval status updated successfully!";
    } else {
        $message = "Error updating approval status: " . $conn->error;
    }
}

// Delete user
if (isset($_POST['delete_user'])) {
    $target_user_id = $_POST['user_id'];
    
    $stmt = $conn->prepare("DELETE FROM users WHERE id = ?");
    $stmt->bind_param("i", $target_user_id);
    
    if ($stmt->execute()) {
        $message = "User deleted successfully!";
    } else {
        $message = "Error deleting user: " . $conn->error;
    }
}

// Get all users
$result = $conn->query("SELECT * FROM users ORDER BY id DESC");
$users = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - FinTrack Admin</title>
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
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: #f5f7fb;
            border-radius: 5px;
            padding: 8px 15px;
            width: 300px;
        }
        
        .search-box input {
            border: none;
            background: none;
            outline: none;
            width: 100%;
            margin-left: 10px;
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
        
        .status-admin {
            background-color: #e6f0ff;
            color: #3755e3;
        }
        
        .status-user {
            background-color: #f0f0f0;
            color: #555;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            color: #667eea;
            margin-right: 5px;
            transition: all 0.3s;
            font-size: 14px;
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
        
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
        }
        
        .modal-content {
            background-color: white;
            margin: 10% auto;
            padding: 20px;
            border-radius: 10px;
            width: 400px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.2);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .modal-title {
            font-weight: 600;
            font-size: 18px;
        }
        
        .close {
            font-size: 24px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .modal-body {
            margin-bottom: 20px;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
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
        
        .btn-secondary {
            background-color: #f0f0f0;
            color: #333;
            margin-right: 10px;
        }
        
        .btn-secondary:hover {
            background-color: #e0e0e0;
        }
        
        .btn-danger {
            background-color: #ff5252;
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #ff0000;
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
            <a href="../dist/admin/logout.php" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div class="page-title">User Management</div>
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
            
            <div class="data-table">
                <div class="table-header">
                    <div class="table-title">All Users</div>
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="searchInput" placeholder="Search users...">
                    </div>
                </div>
                
                <table id="usersTable">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Full Name</th>
                            <th>Username</th>
                            <th>Email</th>
                            <th>Admin Status</th>
                            <th>Approval Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['id']; ?></td>
                                <td><?php echo htmlspecialchars($user['fullname']); ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php 
                                    $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
                                    $adminStatusClass = $isAdmin ? 'status-admin' : 'status-user';
                                    $adminStatusText = $isAdmin ? 'Admin' : 'User';
                                    ?>
                                    <span class="status <?php echo $adminStatusClass; ?>"><?php echo $adminStatusText; ?></span>
                                </td>
                                <td>
                                    <?php 
                                    $isApproved = isset($user['is_approved']) && $user['is_approved'] == 1;
                                    $approvalStatusClass = $isApproved ? 'status-approved' : 'status-pending';
                                    $approvalStatusText = $isApproved ? 'Approved' : 'Pending';
                                    ?>
                                    <span class="status <?php echo $approvalStatusClass; ?>"><?php echo $approvalStatusText; ?></span>
                                </td>
                                <td>
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
                                    
                                    <button class="action-btn" title="Edit User" onclick="openEditModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['fullname']); ?>', '<?php echo htmlspecialchars($user['username']); ?>', '<?php echo htmlspecialchars($user['email']); ?>')">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    
                                    <button class="action-btn delete" title="Delete User" onclick="openDeleteModal(<?php echo $user['id']; ?>, '<?php echo htmlspecialchars($user['username']); ?>')">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
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
                <p>This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <form method="POST">
                    <input type="hidden" id="deleteUserId" name="user_id">
                    <button type="button" class="btn btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" name="delete_user" class="btn btn-danger">Delete</button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit User Modal -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <div class="modal-title">Edit User</div>
                <span class="close" onclick="closeEditModal()">&times;</span>
            </div>
            <div class="modal-body">
                <form id="editForm" method="POST">
                    <input type="hidden" id="editUserId" name="edit_user_id">
                    <div style="margin-bottom: 15px;">
                        <label for="editFullname" style="display: block; margin-bottom: 5px; font-weight: 500;">Full Name</label>
                        <input type="text" id="editFullname" name="edit_fullname" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="editUsername" style="display: block; margin-bottom: 5px; font-weight: 500;">Username</label>
                        <input type="text" id="editUsername" name="edit_username" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label for="editEmail" style="display: block; margin-bottom: 5px; font-weight: 500;">Email</label>
                        <input type="email" id="editEmail" name="edit_email" style="width: 100%; padding: 8px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="submitEditForm()">Save Changes</button>
            </div>
        </div>
    </div>
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tr');
            
            for (let i = 1; i < rows.length; i++) {
                const fullname = rows[i].getElementsByTagName('td')[1].textContent.toLowerCase();
                const username = rows[i].getElementsByTagName('td')[2].textContent.toLowerCase();
                const email = rows[i].getElementsByTagName('td')[3].textContent.toLowerCase();
                
                if (fullname.includes(searchValue) || username.includes(searchValue) || email.includes(searchValue)) {
                    rows[i].style.display = '';
                } else {
                    rows[i].style.display = 'none';
                }
            }
        });
        
        // Delete Modal
        function openDeleteModal(userId, username) {
            document.getElementById('deleteModal').style.display = 'block';
            document.getElementById('deleteUserId').value = userId;
            document.getElementById('deleteUsername').textContent = username;
        }
        
        function closeDeleteModal() {
            document.getElementById('deleteModal').style.display = 'none';
        }
        
        // Edit Modal
        function openEditModal(userId, fullname, username, email) {
            document.getElementById('editModal').style.display = 'block';
            document.getElementById('editUserId').value = userId;
            document.getElementById('editFullname').value = fullname;
            document.getElementById('editUsername').value = username;
            document.getElementById('editEmail').value = email;
        }
        
        function closeEditModal() {
            document.getElementById('editModal').style.display = 'none';
        }
        
        function submitEditForm() {
            document.getElementById('editForm').submit();
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            const editModal = document.getElementById('editModal');
            
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
            
            if (event.target === editModal) {
                closeEditModal();
            }
        }
    </script>
</body>
</html>