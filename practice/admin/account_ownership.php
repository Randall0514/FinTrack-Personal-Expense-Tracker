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

// Process ownership actions
$message = '';

// Transfer ownership
if (isset($_POST['transfer_ownership'])) {
    $from_user_id = $_POST['from_user_id'];
    $to_user_id = $_POST['to_user_id'];
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Get all expenses from the source user
        $stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ?");
        $stmt->bind_param("i", $from_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $expenses = $result->fetch_all(MYSQLI_ASSOC);
        
        // Transfer each expense
        foreach ($expenses as $expense) {
            $stmt = $conn->prepare("UPDATE expenses SET user_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $to_user_id, $expense['id']);
            $stmt->execute();
        }
        
        // Get all income from the source user
        $stmt = $conn->prepare("SELECT * FROM income WHERE user_id = ?");
        $stmt->bind_param("i", $from_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $incomes = $result->fetch_all(MYSQLI_ASSOC);
        
        // Transfer each income
        foreach ($incomes as $income) {
            $stmt = $conn->prepare("UPDATE income SET user_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $to_user_id, $income['id']);
            $stmt->execute();
        }
        
        // Get all budgets from the source user
        $stmt = $conn->prepare("SELECT * FROM budgets WHERE user_id = ?");
        $stmt->bind_param("i", $from_user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $budgets = $result->fetch_all(MYSQLI_ASSOC);
        
        // Transfer each budget
        foreach ($budgets as $budget) {
            $stmt = $conn->prepare("UPDATE budgets SET user_id = ? WHERE id = ?");
            $stmt->bind_param("ii", $to_user_id, $budget['id']);
            $stmt->execute();
        }
        
        // Commit transaction
        $conn->commit();
        
        $message = "Account data transferred successfully!";
    } catch (Exception $e) {
        // Rollback transaction on error
        $conn->rollback();
        $message = "Error transferring account data: " . $e->getMessage();
    }
}

// Get all users
$result = $conn->query("SELECT * FROM users ORDER BY fullname ASC");
$users = $result->fetch_all(MYSQLI_ASSOC);

// Get user statistics
$user_stats = [];
foreach ($users as $user) {
    $user_id = $user['id'];
    
    // Count expenses
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM expenses WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $expense_data = $result->fetch_assoc();
    
    // Count incomes
    $stmt = $conn->prepare("SELECT COUNT(*) as count, SUM(amount) as total FROM income WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $income_data = $result->fetch_assoc();
    
    // Count budgets
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM budgets WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $budget_data = $result->fetch_assoc();
    
    $user_stats[$user_id] = [
        'expenses_count' => $expense_data['count'] ?? 0,
        'expenses_total' => $expense_data['total'] ?? 0,
        'income_count' => $income_data['count'] ?? 0,
        'income_total' => $income_data['total'] ?? 0,
        'budgets_count' => $budget_data['count'] ?? 0
    ];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Ownership - FinTrack Admin</title>
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
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        .form-control {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
        }
        
        .btn {
            padding: 10px 15px;
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
        
        .table-responsive {
            overflow-x: auto;
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
        
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 12px;
            font-weight: 500;
        }
        
        .badge-admin {
            background-color: #e6f0ff;
            color: #3755e3;
        }
        
        .badge-user {
            background-color: #f0f0f0;
            color: #555;
        }
        
        .badge-approved {
            background-color: #e6f7ee;
            color: #0bab64;
        }
        
        .badge-pending {
            background-color: #fff8e6;
            color: #f7b500;
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
            width: 500px;
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
            <a href="account_ownership.php" class="nav-item active">
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
                <div class="page-title">Account Ownership</div>
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
                    <div class="card-title">Transfer Account Ownership</div>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="from_user_id">Transfer From User</label>
                        <select id="from_user_id" name="from_user_id" class="form-control" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="to_user_id">Transfer To User</label>
                        <select id="to_user_id" name="to_user_id" class="form-control" required>
                            <option value="">Select User</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-primary" onclick="openConfirmModal()">
                            <i class="fas fa-exchange-alt"></i> Transfer Ownership
                        </button>
                    </div>
                    
                    <!-- Confirm Modal -->
                    <div id="confirmModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title">Confirm Transfer</div>
                                <span class="close" onclick="closeConfirmModal()">&times;</span>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to transfer all data from <strong id="fromUserName"></strong> to <strong id="toUserName"></strong>?</p>
                                <p>This action will move all expenses, income, and budgets from one user to another.</p>
                                <p style="color: #ff5252; margin-top: 10px;"><strong>Warning:</strong> This action cannot be undone!</p>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                                <button type="submit" name="transfer_ownership" class="btn btn-danger">Transfer</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="data-card">
                <div class="card-header">
                    <div class="card-title">User Account Data</div>
                </div>
                
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Status</th>
                                <th>Expenses</th>
                                <th>Income</th>
                                <th>Budgets</th>
                                <th>Net Worth</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <?php 
                                $user_id = $user['id'];
                                $stats = $user_stats[$user_id];
                                $net_worth = ($stats['income_total'] ?? 0) - ($stats['expenses_total'] ?? 0);
                                $isAdmin = isset($user['is_admin']) && $user['is_admin'] == 1;
                                $isApproved = isset($user['is_approved']) && $user['is_approved'] == 1;
                                ?>
                                <tr>
                                    <td>
                                        <div style="display: flex; align-items: center;">
                                            <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=764ba2&color=fff" alt="User Avatar" style="width: 30px; height: 30px; border-radius: 50%; margin-right: 10px;">
                                            <div>
                                                <div style="font-weight: 600;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                                <div style="font-size: 12px; color: #666;"><?php echo htmlspecialchars($user['username']); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge <?php echo $isAdmin ? 'badge-admin' : 'badge-user'; ?>">
                                            <?php echo $isAdmin ? 'Admin' : 'User'; ?>
                                        </span>
                                        <span class="badge <?php echo $isApproved ? 'badge-approved' : 'badge-pending'; ?>">
                                            <?php echo $isApproved ? 'Approved' : 'Pending'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div><?php echo $stats['expenses_count']; ?> items</div>
                                        <div style="font-size: 12px; color: #ff5252;">$<?php echo number_format($stats['expenses_total'], 2); ?></div>
                                    </td>
                                    <td>
                                        <div><?php echo $stats['income_count']; ?> items</div>
                                        <div style="font-size: 12px; color: #0bab64;">$<?php echo number_format($stats['income_total'], 2); ?></div>
                                    </td>
                                    <td><?php echo $stats['budgets_count']; ?> budgets</td>
                                    <td style="font-weight: 600; color: <?php echo $net_worth >= 0 ? '#0bab64' : '#ff5252'; ?>">
                                        $<?php echo number_format($net_worth, 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Get user names for confirmation modal
        function openConfirmModal() {
            const fromSelect = document.getElementById('from_user_id');
            const toSelect = document.getElementById('to_user_id');
            
            if (fromSelect.value === '' || toSelect.value === '') {
                alert('Please select both users for the transfer.');
                return;
            }
            
            if (fromSelect.value === toSelect.value) {
                alert('You cannot transfer data to the same user.');
                return;
            }
            
            const fromUserName = fromSelect.options[fromSelect.selectedIndex].text;
            const toUserName = toSelect.options[toSelect.selectedIndex].text;
            
            document.getElementById('fromUserName').textContent = fromUserName;
            document.getElementById('toUserName').textContent = toUserName;
            
            document.getElementById('confirmModal').style.display = 'block';
        }
        
        function closeConfirmModal() {
            document.getElementById('confirmModal').style.display = 'none';
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeConfirmModal();
            }
        }
    </script>
</body>
</html>