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

function tableExists($conn, $tableName) {
    $result = $conn->query("SHOW TABLES LIKE '$tableName'");
    return $result && $result->num_rows > 0;
}

$hasExpenses = tableExists($conn, 'expenses');
$hasIncome = tableExists($conn, 'income');
$hasBudgets = tableExists($conn, 'budgets');

$message = '';
$messageType = '';

if (isset($_POST['transfer_ownership'])) {
    $from_user_id = $_POST['from_user_id'];
    $to_user_id = $_POST['to_user_id'];
    
    $conn->begin_transaction();
    
    try {
        if ($hasExpenses) {
            $stmt = $conn->prepare("UPDATE expenses SET user_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $to_user_id, $from_user_id);
            $stmt->execute();
        }
        
        if ($hasIncome) {
            $stmt = $conn->prepare("UPDATE income SET user_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $to_user_id, $from_user_id);
            $stmt->execute();
        }
        
        if ($hasBudgets) {
            $stmt = $conn->prepare("UPDATE budgets SET user_id = ? WHERE user_id = ?");
            $stmt->bind_param("ii", $to_user_id, $from_user_id);
            $stmt->execute();
        }
        
        $conn->commit();
        
        $message = "Account data transferred successfully!";
        $messageType = "success";
    } catch (Exception $e) {
        $conn->rollback();
        $message = "Error transferring account data: " . $e->getMessage();
        $messageType = "error";
    }
}

$result = $conn->query("SELECT * FROM users ORDER BY fullname ASC");
$users = $result->fetch_all(MYSQLI_ASSOC);

$user_stats = [];
foreach ($users as $user) {
    $user_id = $user['id'];
    
    $user_stats[$user_id] = [
        'expenses_count' => 0,
        'expenses_total' => 0,
        'income_count' => 0,
        'income_total' => 0,
        'budgets_count' => 0,
        'budgets_total' => 0,
        'monthly_budget' => $user['monthly_budget'] ?? 0
    ];
    
    if ($hasExpenses) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM expenses WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $expense_data = $result->fetch_assoc();
        $user_stats[$user_id]['expenses_count'] = $expense_data['count'] ?? 0;
        $user_stats[$user_id]['expenses_total'] = $expense_data['total'] ?? 0;
    }
    
    if ($hasIncome) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count, COALESCE(SUM(amount), 0) as total FROM income WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $income_data = $result->fetch_assoc();
        $user_stats[$user_id]['income_count'] = $income_data['count'] ?? 0;
        $user_stats[$user_id]['income_total'] = $income_data['total'] ?? 0;
    }
    
    if ($hasBudgets) {
        $stmt = $conn->prepare("SELECT COUNT(*) as count FROM budgets WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $budget_data = $result->fetch_assoc();
        $user_stats[$user_id]['budgets_count'] = $budget_data['count'] ?? 0;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Account Ownership - FinTrack Admin</title>
    <link rel="icon" type="image/png" href="../logo.png" />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="style/account_ownership.css">
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
            <a href="user_approval.php" class="nav-item">
                <i class="fas fa-check-circle"></i>
                <span>User Approval</span>
            </a>
            <a href="account_ownership.php" class="nav-item active">
                <i class="fas fa-user-tag"></i>
                <span>Account Ownership</span>
            </a>
            <a href="#" onclick="confirmLogout(event)" class="nav-item">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
            </a>
        </div>
        
        <div class="main-content">
            <div class="header">
                <div>
                    <div class="page-title">Account Ownership</div>
                    <div class="page-subtitle">Transfer and manage user account data</div>
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
            
            <?php if (!$hasExpenses && !$hasIncome && !$hasBudgets): ?>
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i>
                    <div>
                        <strong>Warning:</strong> No data tables found. The system cannot track expenses, income, or budgets until the database tables are created.
                    </div>
                </div>
            <?php endif; ?>
            
            <?php if (!empty($message)): ?>
                <div class="alert <?php echo $messageType === 'success' ? 'alert-success' : 'alert-error'; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'times-circle'; ?>"></i>
                    <div><?php echo $message; ?></div>
                </div>
            <?php endif; ?>
            
            <div class="data-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">Transfer Account Ownership</div>
                        <div class="page-subtitle">Move all financial data from one user to another</div>
                    </div>
                </div>
                
                <form method="POST">
                    <div class="form-group">
                        <label for="from_user_id">
                            <i class="fas fa-user-minus" style="margin-right: 8px; color: #f5576c;"></i>
                            Transfer From User
                        </label>
                        <select id="from_user_id" name="from_user_id" class="form-control" required>
                            <option value="">Select source user...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="to_user_id">
                            <i class="fas fa-user-plus" style="margin-right: 8px; color: #48bb78;"></i>
                            Transfer To User
                        </label>
                        <select id="to_user_id" name="to_user_id" class="form-control" required>
                            <option value="">Select destination user...</option>
                            <?php foreach ($users as $user): ?>
                                <option value="<?php echo $user['id']; ?>">
                                    <?php echo htmlspecialchars($user['fullname']); ?> (<?php echo htmlspecialchars($user['username']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <button type="button" class="btn btn-primary" onclick="openConfirmModal()">
                            <i class="fas fa-exchange-alt"></i>
                            Transfer Ownership
                        </button>
                    </div>
                    
                    <div id="confirmModal" class="modal">
                        <div class="modal-content">
                            <div class="modal-header">
                                <div class="modal-title">
                                    <i class="fas fa-exclamation-triangle"></i>
                                    Confirm Transfer
                                </div>
                                <span class="close" onclick="closeConfirmModal()">&times;</span>
                            </div>
                            <div class="modal-body">
                                <p>Are you sure you want to transfer all data from <strong id="fromUserName"></strong> to <strong id="toUserName"></strong>?</p>
                                <p>This action will move all expenses, income, and budgets from one user to another.</p>
                                <div class="warning-box">
                                    <p style="margin: 0;"><strong>⚠️ Warning:</strong> This action cannot be undone!</p>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" onclick="closeConfirmModal()">Cancel</button>
                                <button type="submit" name="transfer_ownership" class="btn btn-danger">
                                    <i class="fas fa-check"></i>
                                    Confirm Transfer
                                </button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
            
            <div class="data-card">
                <div class="card-header">
                    <div>
                        <div class="card-title">User Account Data</div>
                        <div class="page-subtitle">Overview of non-admin user financial data</div>
                    </div>
                </div>
                
                <table>
                    <thead>
                        <tr>
                            <th>User</th>
                            <th>Status</th>
                            <th>Expenses</th>
                            <th>Income</th>
                            <th>Monthly Budget</th>
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
                            
                            // Skip admins
                            if ($isAdmin) continue;
                            ?>
                            <tr>
                                <td>
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=667eea&color=fff&bold=true" alt="User Avatar" style="width: 40px; height: 40px; border-radius: 10px;">
                                        <div>
                                            <div style="font-weight: 600; margin-bottom: 2px;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                            <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($user['username']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-user">User</span>
                                    <span class="badge <?php echo $isApproved ? 'badge-approved' : 'badge-pending'; ?>">
                                        <?php echo $isApproved ? 'Approved' : 'Pending'; ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 2px;"><?php echo $stats['expenses_count']; ?> items</div>
                                    <div style="font-size: 13px; color: #f5576c; font-weight: 500;">₱<?php echo number_format($stats['expenses_total'], 2); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 2px;"><?php echo $stats['income_count']; ?> items</div>
                                    <div style="font-size: 13px; color: #48bb78; font-weight: 500;">₱<?php echo number_format($stats['income_total'], 2); ?></div>
                                </td>
                                <td>
                                    <div style="font-size: 16px; font-weight: 700; color: #667eea;">
                                        ₱<?php echo number_format($stats['monthly_budget'], 2); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #718096; margin-top: 2px;">
                                        Monthly Budget
                                    </div>
                                </td>
                                <td>
                                    <?php 
                                    $monthly_budget = $stats['monthly_budget'] ?? 0;
                                    $expenses_total = $stats['expenses_total'] ?? 0;
                                    $budget_percentage = $monthly_budget > 0 ? ($expenses_total / $monthly_budget) * 100 : 0;
                                    
                                    // Determine color based on budget usage
                                    if ($budget_percentage <= 50) {
                                        $net_worth_color = '#48bb78'; // Green - under 50%
                                        $status_text = 'Under Budget';
                                    } elseif ($budget_percentage <= 100) {
                                        $net_worth_color = '#f59e0b'; // Yellow - 50-100%
                                        $status_text = 'Approaching Limit';
                                    } else {
                                        $net_worth_color = '#f5576c'; // Red - over 100%
                                        $status_text = 'Over Budget';
                                    }
                                    ?>
                                    <div style="font-size: 16px; font-weight: 700; color: <?php echo $net_worth_color; ?>;">
                                        ₱<?php echo number_format($net_worth, 2); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #718096; margin-top: 2px;">
                                        <?php echo $status_text; ?> (<?php echo number_format($budget_percentage, 1); ?>%)
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
 
    <script src="script/account_ownership.js"></script>  
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