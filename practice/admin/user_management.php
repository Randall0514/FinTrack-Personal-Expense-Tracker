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

// Process user actions
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #2d3748;
            line-height: 1.6;
        }
        
        .admin-container {
            display: flex;
            min-height: 100vh;
        }
        
        /* ==================== SIDEBAR ==================== */
        .sidebar {
            width: 280px;
            height: 100vh;
            background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%);
            position: fixed;
            left: 0;
            top: 0;
            overflow-y: auto;
            box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12);
            z-index: 1000;
            display: flex;
            flex-direction: column;
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

        .sidebar-header {
            padding: 32px 24px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
        }

        .brand {
            display: flex;
            align-items: center;
            gap: 16px;
        }

        .brand-icon {
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
            box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
            position: relative;
        }

        .brand-icon::after {
            content: '';
            position: absolute;
            inset: -2px;
            background: linear-gradient(135deg, #3b82f6, #8b5cf6);
            border-radius: 18px;
            z-index: -1;
            opacity: 0.3;
            filter: blur(8px);
        }

        .brand-content h1 {
            font-size: 22px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.5px;
            margin-bottom: 2px;
        }

        .brand-content p {
            font-size: 11px;
            color: #94a3b8;
            font-weight: 600;
            letter-spacing: 2px;
            text-transform: uppercase;
        }

        .sidebar-nav {
            padding: 24px 0;
            flex: 1;
        }

        .nav-section-title {
            padding: 0 24px 12px;
            font-size: 10px;
            font-weight: 700;
            color: #64748b;
            text-transform: uppercase;
            letter-spacing: 1.5px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .nav-section-title::before {
            content: '';
            width: 4px;
            height: 4px;
            background: #3b82f6;
            border-radius: 50%;
        }

        .nav-menu {
            list-style: none;
            padding: 0 12px;
            margin-bottom: 24px;
        }

        .nav-menu li {
            margin-bottom: 4px;
        }

        .nav-link {
            display: flex;
            align-items: center;
            padding: 14px 16px;
            color: #cbd5e1;
            text-decoration: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            position: relative;
            overflow: hidden;
        }

        .nav-link::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: linear-gradient(180deg, #3b82f6 0%, #8b5cf6 100%);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .nav-link:hover {
            background: rgba(59, 130, 246, 0.08);
            color: white;
            transform: translateX(4px);
        }

        .nav-link:hover::before {
            transform: scaleY(1);
        }

        .nav-link.active {
            background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
        }

        .nav-link.active::before {
            transform: scaleY(1);
        }

        .nav-link.active .nav-icon {
            color: #3b82f6;
        }

        .nav-icon {
            width: 22px;
            height: 22px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 14px;
            font-size: 18px;
            transition: all 0.3s ease;
        }

        .nav-text {
            flex: 1;
        }

        .nav-badge {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 3px 9px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            min-width: 22px;
            text-align: center;
        }

        .nav-arrow {
            font-size: 12px;
            margin-left: auto;
            transition: transform 0.3s ease;
            opacity: 0;
        }

        .nav-link:hover .nav-arrow {
            opacity: 1;
        }

        .nav-divider {
            height: 1px;
            background: rgba(255, 255, 255, 0.06);
            margin: 20px 24px;
        }

        .sidebar-footer {
            padding: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.06);
            background: rgba(0, 0, 0, 0.2);
        }

        .user-profile {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 14px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 1px solid transparent;
        }

        .user-profile:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(59, 130, 246, 0.3);
        }

        .user-avatar-sidebar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 2px solid rgba(59, 130, 246, 0.4);
            object-fit: cover;
        }

        .user-info-sidebar {
            flex: 1;
            min-width: 0;
        }

        .user-name-sidebar {
            font-size: 14px;
            font-weight: 600;
            color: white;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            margin-bottom: 2px;
        }

        .user-role {
            font-size: 11px;
            color: #94a3b8;
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .status-dot {
            width: 6px;
            height: 6px;
            background: #22c55e;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% {
                opacity: 1;
            }
            50% {
                opacity: 0.5;
            }
        }

        .user-menu-icon {
            color: #64748b;
            font-size: 16px;
        }
        
        /* ==================== MAIN CONTENT ==================== */
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
            background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
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
            margin-bottom: 30px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
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
            border-left: 4px solid #38a169;
        }
        
        .alert-error {
            background-color: #fed7d7;
            color: #742a2a;
            border-left: 4px solid #e53e3e;
        }
        
        .alert i {
            font-size: 20px;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
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
        
        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            color: white;
            background: var(--card-color);
        }
        
        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a202c;
            line-height: 1;
            margin-bottom: 6px;
        }
        
        .stat-label {
            font-size: 13px;
            color: #718096;
            font-weight: 500;
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
            flex-wrap: wrap;
            gap: 15px;
        }
        
        .table-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
            letter-spacing: -0.3px;
        }
        
        .search-box {
            display: flex;
            align-items: center;
            background-color: #f7fafc;
            border-radius: 10px;
            padding: 10px 16px;
            width: 320px;
            border: 2px solid transparent;
            transition: all 0.3s;
        }
        
        .search-box:focus-within {
            background-color: white;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }
        
        .search-box i {
            color: #a0aec0;
            margin-right: 10px;
        }
        
        .search-box input {
            border: none;
            background: none;
            outline: none;
            width: 100%;
            font-size: 14px;
            color: #2d3748;
        }
        
        .search-box input::placeholder {
            color: #a0aec0;
        }
        
        .table-wrapper {
            overflow-x: auto;
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
            white-space: nowrap;
        }
        
        th:first-child {
            border-radius: 10px 0 0 0;
        }
        
        th:last-child {
            border-radius: 0 10px 0 0;
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
        
        .user-cell {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .user-cell img {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            border: 2px solid #f0f4f8;
        }
        
        .user-cell-info {
            display: flex;
            flex-direction: column;
        }
        
        .user-cell-name {
            font-weight: 600;
            color: #1a202c;
        }
        
        .user-cell-id {
            font-size: 12px;
            color: #a0aec0;
        }
        
        .status {
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.3px;
            display: inline-block;
            white-space: nowrap;
        }
        
        .status-approved {
            background-color: #c6f6d5;
            color: #22543d;
        }
        
        .status-pending {
            background-color: #feebc8;
            color: #7c2d12;
        }
        
        .status-admin {
            background-color: #bee3f8;
            color: #2c5282;
        }
        
        .status-user {
            background-color: #e2e8f0;
            color: #4a5568;
        }
        
        .actions-cell {
            display: flex;
            gap: 6px;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            padding: 8px 10px;
            border-radius: 8px;
            transition: all 0.2s;
            color: #718096;
            font-size: 14px;
        }
        
        .action-btn:hover {
            background-color: #f0f4f8;
            color: #3b82f6;
        }
        
        .action-btn.delete:hover {
            background-color: #fff5f5;
            color: #e53e3e;
        }
        
        .action-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
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
            animation: fadeIn 0.2s ease;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        .modal-content {
            background-color: white;
            margin: 8% auto;
            padding: 0;
            border-radius: 16px;
            width: 90%;
            max-width: 480px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            animation: slideUp 0.3s ease;
        }
        
        @keyframes slideUp {
            from {
                transform: translateY(30px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 24px 28px;
            border-bottom: 1px solid #f0f4f8;
        }
        
        .modal-title {
            font-weight: 700;
            font-size: 20px;
            color: #1a202c;
        }
        
        .close {
            width: 32px;
            height: 32px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.2s;
            color: #718096;
            font-size: 24px;
            line-height: 1;
        }
        
        .close:hover {
            background-color: #f7fafc;
            color: #2d3748;
        }
        
        .modal-body {
            padding: 24px 28px;
        }
        
        .modal-body p {
            margin-bottom: 12px;
            color: #4a5568;
            line-height: 1.6;
        }
        
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 12px;
            padding: 20px 28px;
            background-color: #f7fafc;
            border-radius: 0 0 16px 16px;
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
        
        .btn-secondary {
            background-color: white;
            color: #4a5568;
            border: 2px solid #e2e8f0;
        }
        
        .btn-secondary:hover {
            background-color: #f7fafc;
            border-color: #cbd5e0;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #fc466b 0%, #3f5efb 100%);
            color: white;
            box-shadow: 0 4px 12px rgba(252, 70, 107, 0.3);
        }
        
        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(252, 70, 107, 0.4);
        }
        
        @media (max-width: 1200px) {
            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }
        }
        
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .brand-content,
            .nav-section-title,
            .nav-text,
            .nav-badge,
            .nav-arrow,
            .user-info-sidebar {
                display: none;
            }

            .sidebar-header {
                padding: 24px 10px;
            }

            .brand {
                justify-content: center;
            }

            .nav-menu {
                padding: 0 8px;
            }

            .nav-link {
                justify-content: center;
                padding: 14px;
            }

            .nav-icon {
                margin-right: 0;
            }

            .nav-link::before {
                display: none;
            }

            .sidebar-footer {
                padding: 16px 10px;
            }

            .user-profile {
                justify-content: center;
                padding: 12px;
            }

            .user-menu-icon {
                display: none;
            }
            
            .main-content {
                margin-left: 70px;
                padding: 20px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .header {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }
            
            .table-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .search-box {
                width: 100%;
            }
            
            .user-cell {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .actions-cell {
                flex-wrap: wrap;
            }
        }
    </style>
</head>
<body>
    <div class="admin-container">
        <!-- SIDEBAR -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="brand">
                    <div class="brand-icon">
                        <i class="fas fa-chart-line"></i>
                    </div>
                    <div class="brand-content">
                        <h1>FinTrack</h1>
                        <p>Admin Panel</p>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <div class="nav-section-title">Overview</div>
                <ul class="nav-menu">
                    <li>
                        <a href="dashboard.php" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-home"></i></span>
                            <span class="nav-text">Dashboard</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-chart-bar"></i></span>
                            <span class="nav-text">Analytics</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                </ul>

                <div class="nav-section-title">Management</div>
                <ul class="nav-menu">
                    <li>
                        <a href="user_management.php" class="nav-link active">
                            <span class="nav-icon"><i class="fas fa-users"></i></span>
                            <span class="nav-text">User Management</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                    <li>
                        <a href="user_approval.php" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-user-check"></i></span>
                            <span class="nav-text">User Approval</span>
                            <?php
                            $pending_result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0 OR is_approved IS NULL");
                            $pending_count = $pending_result->fetch_assoc()['total'];
                            if ($pending_count > 0) {
                                echo '<span class="nav-badge">' . $pending_count . '</span>';
                            }
                            ?>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                    <li>
                        <a href="account_ownership.php" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-exchange-alt"></i></span>
                            <span class="nav-text">Account Ownership</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                </ul>

                <div class="nav-section-title">Security</div>
                <ul class="nav-menu">
                    <li>
                        <a href="security_control.php" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-shield-halved"></i></span>
                            <span class="nav-text">Security Control</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                    <li>
                        <a href="#" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-key"></i></span>
                            <span class="nav-text">API Keys</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                </ul>

                <div class="nav-divider"></div>

                <ul class="nav-menu">
                    <li>
                        <a href="#" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-cog"></i></span>
                            <span class="nav-text">Settings</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                    <li>
                        <a href="../logout.php" class="nav-link">
                            <span class="nav-icon"><i class="fas fa-sign-out-alt"></i></span>
                            <span class="nav-text">Logout</span>
                            <i class="fas fa-chevron-right nav-arrow"></i>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-profile">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=3b82f6&color=fff&bold=true" alt="Avatar" class="user-avatar-sidebar">
                    <div class="user-info-sidebar">
                        <div class="user-name-sidebar"><?php echo htmlspecialchars($fullname); ?></div>
                        <div class="user-role">
                            <span class="status-dot"></span>
                            Administrator
                        </div>
                    </div>
                    <i class="fas fa-ellipsis-v user-menu-icon"></i>
                </div>
            </div>
        </aside>
        
        <div class="main-content">
            <div class="header">
                <div>
                    <div class="page-title">User Management</div>
                    <div class="page-subtitle">Manage all users, permissions, and access control</div>
                </div>
                <div class="user-info">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=3b82f6&color=fff&bold=true" alt="User Avatar" class="user-avatar">
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
    
    <script>
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const table = document.getElementById('usersTable');
            const rows = table.getElementsByTagName('tbody')[0].getElementsByTagName('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const cells = rows[i].getElementsByTagName('td');
                const fullname = cells[0].textContent.toLowerCase();
                const username = cells[1].textContent.toLowerCase();
                const email = cells[2].textContent.toLowerCase();
                
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
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            const deleteModal = document.getElementById('deleteModal');
            
            if (event.target === deleteModal) {
                closeDeleteModal();
            }
        }
        
        // Close modal on ESC key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeDeleteModal();
            }
        });
    </script>
</body>
</html>