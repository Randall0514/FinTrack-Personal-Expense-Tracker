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
        'budgets_count' => 0
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
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #667eea 0%, #764ba2 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }

        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.03);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }

        .sidebar::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.2);
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
            padding: 18px 24px;
            margin-bottom: 30px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 15px;
            font-size: 14px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
            animation: slideDown 0.3s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .alert-success {
            background: linear-gradient(135deg, #d4fc79 0%, #96e6a1 100%);
            color: #155724;
            border: 1px solid #96e6a1;
        }
        
        .alert-error {
            background: linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%);
            color: #721c24;
            border: 1px solid #ff9a9e;
        }
        
        .alert-warning {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            color: #856404;
            border: 1px solid #fcb69f;
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
        
        .card-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
            letter-spacing: -0.3px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 10px;
            font-weight: 600;
            font-size: 14px;
            color: #2d3748;
        }
        
        .form-control {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 14px;
            font-family: 'Inter', sans-serif;
            transition: all 0.3s ease;
            background: #f8fafc;
        }
        
        .form-control:focus {
            outline: none;
            border-color: #667eea;
            background: white;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        
        .btn {
            padding: 14px 24px;
            border-radius: 10px;
            border: none;
            font-weight: 600;
            font-size: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
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
        
        .btn-secondary {
            background: #e2e8f0;
            color: #2d3748;
        }
        
        .btn-secondary:hover {
            background: #cbd5e0;
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
        
        .badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 6px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            margin-right: 6px;
        }
        
        .badge-admin {
            background: linear-gradient(135deg, #e0e7ff 0%, #d4d4ff 100%);
            color: #4c51bf;
        }
        
        .badge-user {
            background: #edf2f7;
            color: #4a5568;
        }
        
        .badge-approved {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
        }
        
        .badge-pending {
            background: linear-gradient(135deg, #feebc8 0%, #fbd38d 100%);
            color: #7c2d12;
        }
        
        .modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.6);
            backdrop-filter: blur(4px);
            animation: fadeIn 0.3s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background: white;
            margin: 8% auto;
            padding: 0;
            border-radius: 16px;
            width: 500px;
            max-width: 90%;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 25px 30px;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .modal-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .modal-title i {
            color: #f5576c;
        }
        
        .close {
            font-size: 28px;
            font-weight: 300;
            cursor: pointer;
            color: #a0aec0;
            transition: all 0.2s;
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }
        
        .close:hover {
            background: #f7fafc;
            color: #2d3748;
        }
        
        .modal-body {
            padding: 25px 30px;
            line-height: 1.8;
        }
        
        .modal-body p {
            margin-bottom: 15px;
            color: #4a5568;
        }
        
        .modal-body strong {
            color: #667eea;
            font-weight: 600;
        }
        
        .warning-box {
            background: linear-gradient(135deg, #fff5f5 0%, #fed7d7 100%);
            border-left: 4px solid #f5576c;
            padding: 15px;
            border-radius: 8px;
            margin-top: 15px;
        }
        
        .warning-box strong {
            color: #c53030;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 30px;
            background: #f8fafc;
            border-radius: 0 0 16px 16px;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .logo-text,
            .logo-subtitle,
            .nav-item span,
            .nav-section {
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
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .modal-content {
                width: 95%;
                margin: 20% auto;
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
            <a href="user_approval.php" class="nav-item">
                <i class="fas fa-check-circle"></i>
                <span>User Approval</span>
            </a>
            <a href="account_ownership.php" class="nav-item active">
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
                        <div class="page-subtitle">Overview of all user financial data</div>
                    </div>
                </div>
                
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
                                    <div style="display: flex; align-items: center; gap: 12px;">
                                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['fullname']); ?>&background=667eea&color=fff&bold=true" alt="User Avatar" style="width: 40px; height: 40px; border-radius: 10px;">
                                        <div>
                                            <div style="font-weight: 600; margin-bottom: 2px;"><?php echo htmlspecialchars($user['fullname']); ?></div>
                                            <div style="font-size: 12px; color: #718096;"><?php echo htmlspecialchars($user['username']); ?></div>
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
                                    <div style="font-weight: 600; margin-bottom: 2px;"><?php echo $stats['expenses_count']; ?> items</div>
                                    <div style="font-size: 13px; color: #f5576c; font-weight: 500;">₱<?php echo number_format($stats['expenses_total'], 2); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600; margin-bottom: 2px;"><?php echo $stats['income_count']; ?> items</div>
                                    <div style="font-size: 13px; color: #48bb78; font-weight: 500;">₱<?php echo number_format($stats['income_total'], 2); ?></div>
                                </td>
                                <td>
                                    <div style="font-weight: 600;"><?php echo $stats['budgets_count']; ?> budgets</div>
                                </td>
                                <td>
                                    <div style="font-size: 16px; font-weight: 700; color: <?php echo $net_worth >= 0 ? '#48bb78' : '#f5576c'; ?>;">
                                        ₱<?php echo number_format($net_worth, 2); ?>
                                    </div>
                                    <div style="font-size: 12px; color: #718096; margin-top: 2px;">
                                        <?php echo $net_worth >= 0 ? 'Positive' : 'Negative'; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    
    <script>
        function openConfirmModal() {
            const fromSelect = document.getElementById('from_user_id');
            const toSelect = document.getElementById('to_user_id');
            
            if (fromSelect.value === '' || toSelect.value === '') {
                showAlert('Please select both users for the transfer.', 'warning');
                return;
            }
            
            if (fromSelect.value === toSelect.value) {
                showAlert('You cannot transfer data to the same user.', 'warning');
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
        
        function showAlert(message, type) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type}`;
            alertDiv.innerHTML = `
                <i class="fas fa-${type === 'warning' ? 'exclamation-triangle' : 'info-circle'}"></i>
                <div>${message}</div>
            `;
            
            const mainContent = document.querySelector('.main-content');
            const firstChild = mainContent.firstChild;
            mainContent.insertBefore(alertDiv, firstChild);
            
            setTimeout(() => {
                alertDiv.style.opacity = '0';
                alertDiv.style.transform = 'translateY(-10px)';
                setTimeout(() => alertDiv.remove(), 300);
            }, 3000);
        }
        
        window.onclick = function(event) {
            const modal = document.getElementById('confirmModal');
            if (event.target === modal) {
                closeConfirmModal();
            }
        }
        
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                alert.style.transition = 'all 0.3s ease';
                alert.style.opacity = '0';
                alert.style.transform = 'translateY(-10px)';
                setTimeout(() => alert.remove(), 300);
            });
        }, 5000);
    </script>
</body>
</html>