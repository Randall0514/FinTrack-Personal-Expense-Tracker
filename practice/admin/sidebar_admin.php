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

// Get current page for sidebar active state
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
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background: #f8fafc;
            color: #1e293b;
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

        .user-avatar {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            border: 2px solid rgba(59, 130, 246, 0.4);
            object-fit: cover;
        }

        .user-info {
            flex: 1;
            min-width: 0;
        }

        .user-name {
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
            margin-left: 280px;
            padding: 40px;
            min-height: 100vh;
        }

        .page-header {
            background: white;
            padding: 32px;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            margin-bottom: 32px;
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
            font-size: 32px;
            font-weight: 800;
            color: #0f172a;
            margin-bottom: 6px;
        }

        .page-subtitle {
            font-size: 15px;
            color: #64748b;
        }

        .header-user {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .header-avatar {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            border: 2px solid #e2e8f0;
        }

        .header-user-info {
            display: flex;
            flex-direction: column;
        }

        .header-user-name {
            font-weight: 600;
            font-size: 15px;
            color: #1e293b;
            display: flex;
            align-items: center;
            gap: 8px;
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

        .header-user-email {
            font-size: 13px;
            color: #64748b;
        }

        /* Dashboard Cards */
        .cards-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .stat-card {
            background: white;
            padding: 28px;
            border-radius: 20px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.04);
            transition: all 0.3s ease;
            border: 1px solid #f1f5f9;
        }

        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0, 0, 0, 0.08);
        }

        .stat-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
            color: white;
        }

        .stat-icon.blue {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
        }

        .stat-icon.purple {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
        }

        .stat-icon.green {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        .stat-icon.orange {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
        }

        .stat-value {
            font-size: 36px;
            font-weight: 800;
            color: #0f172a;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            color: #64748b;
            font-weight: 500;
        }

        .stat-trend {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 13px;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid #f1f5f9;
        }

        .stat-trend.up {
            color: #22c55e;
        }

        .stat-trend.down {
            color: #ef4444;
        }

        /* ==================== RESPONSIVE ==================== */
        @media (max-width: 768px) {
            .sidebar {
                width: 70px;
            }

            .brand-content,
            .nav-section-title,
            .nav-text,
            .nav-badge,
            .nav-arrow,
            .user-info {
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

            .header-content {
                flex-direction: column;
                gap: 20px;
                text-align: center;
            }

            .cards-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
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
                    <a href="dashboard.php" class="nav-link active">
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
                    <a href="user_management.php" class="nav-link">
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
                        $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0 OR is_approved IS NULL");
                        $pending_count = $result->fetch_assoc()['total'];
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
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=3b82f6&color=fff&bold=true" alt="Avatar" class="user-avatar">
                <div class="user-info">
                    <div class="user-name"><?php echo htmlspecialchars($fullname); ?></div>
                    <div class="user-role">
                        <span class="status-dot"></span>
                        Administrator
                    </div>
                </div>
                <i class="fas fa-ellipsis-v user-menu-icon"></i>
            </div>
        </div>
    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-content">
        <div class="page-header">
            <div class="header-content">
                <div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Welcome back! Here's what's happening with your platform.</p>
                </div>
                <div class="header-user">
                    <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($fullname); ?>&background=3b82f6&color=fff&bold=true" alt="User Avatar" class="header-avatar">
                    <div class="header-user-info">
                        <div class="header-user-name">
                            <?php echo htmlspecialchars($fullname); ?>
                            <span class="admin-badge">Admin</span>
                        </div>
                        <div class="header-user-email"><?php echo htmlspecialchars($email); ?></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="cards-grid">
            <div class="stat-card">
                <div class="stat-header">
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
                    <div class="stat-icon blue">
                        <i class="fas fa-users"></i>
                    </div>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>12% from last month</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">
                            <?php
                            $result = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0 OR is_approved IS NULL");
                            $row = $result->fetch_assoc();
                            echo $row['total'];
                            ?>
                        </div>
                        <div class="stat-label">Pending Approvals</div>
                    </div>
                    <div class="stat-icon purple">
                        <i class="fas fa-clock"></i>
                    </div>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-info-circle"></i>
                    <span>Requires attention</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <div>
                        <div class="stat-value">98.5%</div>
                        <div class="stat-label">System Uptime</div>
                    </div>
                    <div class="stat-icon green">
                        <i class="fas fa-server"></i>
                    </div>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-check-circle"></i>
                    <span>All systems operational</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
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
                    <div class="stat-icon orange">
                        <i class="fas fa-user-check"></i>
                    </div>
                </div>
                <div class="stat-trend up">
                    <i class="fas fa-arrow-up"></i>
                    <span>8% increase</span>
                </div>
            </div>
        </div>
    </main>
</body>
</html>