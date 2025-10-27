<?php
// JWT Authentication for Admin Sidebar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../config/dbconfig_password.php";

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

// Check JWT via cookie
if (!isset($_COOKIE['admin_jwt_token'])) {
    echo "<script>alert('You must log in as admin first.'); window.location.href='../../login.php';</script>";
    exit;
}

$jwt = $_COOKIE['admin_jwt_token'];

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user = (array) $decoded->data;
    
    // Check if user is admin
    if (!isset($user['is_admin']) || $user['is_admin'] !== true) {
        echo "<script>alert('❌ Access denied. Admin privileges required.'); window.location.href='../../login.php';</script>";
        setcookie("admin_jwt_token", "", time() - 3600, "/");
        exit;
    }
} catch (Exception $e) {
    echo "<script>alert('❌ Invalid or expired token. Please log in again.'); window.location.href='../../login.php';</script>";
    setcookie("admin_jwt_token", "", time() - 3600, "/");
    exit;
}

// Get user ID from JWT
$user_id = $user['id'];

// Fetch actual user data from database
$userData = [];
$userQuery = $conn->prepare("SELECT * FROM users WHERE id = ?");
$userQuery->bind_param("i", $user_id);
$userQuery->execute();
$userResult = $userQuery->get_result();

if ($userResult->num_rows > 0) {
    $userData = $userResult->fetch_assoc();
} else {
    echo "<script>alert('User not found!'); window.location.href='../../login.php';</script>";
    exit;
}

// Set defaults for missing fields
$userData['profile_picture'] = $userData['profile_picture'] ?? 'https://ui-avatars.com/api/?name=' . urlencode($userData['fullname']) . '&background=3b82f6&color=fff&bold=true';
$userData['fullname'] = $userData['fullname'] ?? 'Admin';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);

// Get pending approvals count
$pendingQuery = $conn->query("SELECT COUNT(*) as total FROM users WHERE is_approved = 0 OR is_approved IS NULL");
$pendingCount = $pendingQuery->fetch_assoc()['total'];
?>

<style>
/* Enhanced Admin Sidebar Styling */
.pc-sidebar {
  background: linear-gradient(180deg, #0f172a 0%, #1e293b 100%) !important;
  box-shadow: 4px 0 24px rgba(0, 0, 0, 0.12) !important;
  height: 100vh;
  position: fixed;
  overflow: hidden;
  display: flex;
  flex-direction: column;
}

.navbar-wrapper {
  height: 100%;
  display: flex;
  flex-direction: column;
  overflow: hidden;
}

/* Brand Section with Admin Gradient */
.sidebar-brand {
  background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
  padding: 2rem 1.5rem;
  border-bottom: 1px solid rgba(255, 255, 255, 0.06) !important;
  position: relative;
  overflow: hidden;
  flex-shrink: 0;
}

.sidebar-brand::before {
  content: '';
  position: absolute;
  top: -50%;
  right: -50%;
  width: 200%;
  height: 200%;
  background: radial-gradient(circle, rgba(59, 130, 246, 0.1) 0%, transparent 70%);
  animation: pulse 3s ease-in-out infinite;
}

@keyframes pulse {
  0%, 100% { transform: scale(1); opacity: 0.5; }
  50% { transform: scale(1.1); opacity: 0.8; }
}

.logo-container {
  position: relative;
  z-index: 1;
}

.logo-link {
  display: flex;
  align-items: center;
  gap: 1rem;
  text-decoration: none;
  transition: transform 0.3s ease;
}

.logo-link:hover {
  transform: translateX(5px);
}

.logo-image {
  width: 3.125rem;
  height: 3.125rem;
  border-radius: 16px;
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  display: flex;
  align-items: center;
  justify-content: center;
  box-shadow: 0 8px 20px rgba(59, 130, 246, 0.4);
  border: 2px solid rgba(59, 130, 246, 0.3);
  transition: all 0.3s ease;
  position: relative;
  font-size: 1.5rem;
  color: white;
}

.logo-image::after {
  content: '';
  position: absolute;
  inset: -2px;
  background: linear-gradient(135deg, #3b82f6, #8b5cf6);
  border-radius: 18px;
  z-index: -1;
  opacity: 0.3;
  filter: blur(8px);
}

.logo-link:hover .logo-image {
  transform: rotate(5deg) scale(1.05);
  box-shadow: 0 12px 28px rgba(59, 130, 246, 0.5);
}

.logo-text h1 {
  color: white !important;
  font-size: 1.375rem;
  font-weight: 800;
  margin: 0;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
  letter-spacing: -0.5px;
}

.logo-text p {
  color: #94a3b8 !important;
  font-size: 0.6875rem;
  margin: 0;
  font-weight: 600;
  letter-spacing: 2px;
  text-transform: uppercase;
}

/* User Profile Card */
.user-profile-card {
  margin-top: 1rem;
  padding-top: 1rem;
  border-top: 1px solid rgba(255, 255, 255, 0.06);
  position: relative;
  z-index: 1;
}

.user-profile-wrapper {
  background: rgba(59, 130, 246, 0.08);
  backdrop-filter: blur(10px);
  border-radius: 14px;
  padding: 0.75rem;
  display: flex;
  align-items: center;
  gap: 0.75rem;
  transition: all 0.3s ease;
  border: 1px solid rgba(59, 130, 246, 0.2);
}

.user-profile-wrapper:hover {
  background: rgba(59, 130, 246, 0.15);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
  border-color: rgba(59, 130, 246, 0.4);
}

.user-avatar {
  width: 2.75rem;
  height: 2.75rem;
  border-radius: 12px;
  object-fit: cover;
  border: 2px solid rgba(59, 130, 246, 0.4);
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.user-profile-wrapper:hover .user-avatar {
  transform: scale(1.05);
  border-color: #3b82f6;
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  color: white !important;
  font-size: 0.875rem;
  font-weight: 600;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.admin-badge {
  background: linear-gradient(135deg, #3b82f6 0%, #8b5cf6 100%);
  color: white;
  padding: 0.25rem 0.625rem;
  border-radius: 6px;
  font-size: 0.6875rem;
  font-weight: 600;
  letter-spacing: 0.5px;
  text-transform: uppercase;
}

.user-status {
  display: flex;
  align-items: center;
  gap: 0.375rem;
  margin-top: 0.25rem;
}

.status-indicator {
  width: 6px;
  height: 6px;
  border-radius: 50%;
  background: #22c55e;
  box-shadow: 0 0 8px rgba(34, 197, 94, 0.6);
  animation: blink 2s ease-in-out infinite;
  flex-shrink: 0;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.status-text {
  color: #94a3b8;
  font-size: 0.6875rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Navigation Section - Scrollable */
.nav-section {
  flex: 1;
  padding: 1.5rem 0.75rem;
  overflow-y: auto;
  overflow-x: hidden;
  margin-top: 0;
  min-height: 0;
}

.nav-section::-webkit-scrollbar {
  width: 5px;
}

.nav-section::-webkit-scrollbar-track {
  background: transparent;
}

.nav-section::-webkit-scrollbar-thumb {
  background: rgba(59, 130, 246, 0.3);
  border-radius: 10px;
}

.nav-section::-webkit-scrollbar-thumb:hover {
  background: rgba(59, 130, 246, 0.5);
}

.nav-label {
  color: #64748b !important;
  font-size: 0.625rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1.5px;
  padding: 0 1rem;
  margin: 1.5rem 0 0.75rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nav-label:first-child {
  margin-top: 0;
}

.nav-label::before {
  content: '';
  width: 4px;
  height: 4px;
  background: #3b82f6;
  border-radius: 50%;
  flex-shrink: 0;
}

/* Menu Items */
.pc-navbar {
  list-style: none;
  margin: 0;
  padding: 0;
}

.menu-item {
  width: 100%;
  display: flex;
  align-items: center;
  gap: 0.875rem;
  padding: 0.875rem 1rem;
  border-radius: 12px;
  margin-bottom: 0.25rem;
  transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
  border: 1px solid transparent;
  text-decoration: none;
  font-weight: 500;
  font-size: 0.875rem;
  position: relative;
  overflow: hidden;
  color: #cbd5e1;
}

.menu-item::before {
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

.menu-item:not(.active):hover {
  background: rgba(59, 130, 246, 0.08);
  color: white !important;
  transform: translateX(4px);
}

.menu-item:not(.active):hover::before {
  transform: scaleY(1);
}

.menu-item.active {
  background: linear-gradient(90deg, rgba(59, 130, 246, 0.15) 0%, rgba(139, 92, 246, 0.15) 100%);
  color: white !important;
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.2);
}

.menu-item.active::before {
  transform: scaleY(1);
}

.menu-item.active i {
  color: #3b82f6;
}

.menu-item i {
  width: 22px;
  height: 22px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
  font-size: 1.125rem;
  transition: all 0.3s ease;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.menu-badge {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white;
  padding: 0.1875rem 0.5625rem;
  border-radius: 12px;
  font-size: 0.6875rem;
  font-weight: 700;
  min-width: 22px;
  text-align: center;
}

/* Logout Button Special Style */
.menu-item.logout-btn {
  background: rgba(239, 68, 68, 0.1);
  color: #fca5a5 !important;
  border-color: rgba(239, 68, 68, 0.2);
  margin-top: 1rem;
}

.menu-item.logout-btn:hover {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white !important;
  transform: translateX(5px) scale(1.02);
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

.menu-item.logout-btn i {
  color: #ef4444;
}

.menu-item.logout-btn:hover i {
  color: white;
}

/* Nav Divider */
.nav-divider {
  height: 1px;
  background: rgba(255, 255, 255, 0.06);
  margin: 1.25rem 1.5rem;
}

/* Responsive Design */
@media (max-width: 768px) {
  .pc-sidebar {
    width: 260px;
  }
  
  .sidebar-brand {
    padding: 1.5rem 1rem;
  }
  
  .logo-image {
    width: 2.5rem;
    height: 2.5rem;
  }
  
  .logo-text h1 {
    font-size: 1.25rem;
  }
  
  .user-name {
    font-size: 0.8125rem;
  }
  
  .user-avatar {
    width: 2.5rem;
    height: 2.5rem;
  }
}
</style>

<!-- [ Admin Sidebar Menu ] start -->
<nav class="pc-sidebar">
  <div class="navbar-wrapper h-full flex flex-col">
    <!-- Brand Section -->
    <div class="sidebar-brand">
      <div class="logo-container">
        <a href="../admin/dashboard.php" class="logo-link">
          <div class="logo-image">
            <i class="fas fa-chart-line"></i>
          </div>
          <div class="logo-text">
            <h1>FinTrack</h1>
            <p>Admin Panel</p>
          </div>
        </a>
        
        <!-- User Profile Card -->
        <div class="user-profile-card">
          <div class="user-profile-wrapper">
            <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" alt="Profile" class="user-avatar" />
            <div class="user-info">
              <p class="user-name">
                <?php echo htmlspecialchars($userData['fullname']); ?>
              </p>
              <div class="user-status">
                <span class="status-indicator"></span>
                <span class="status-text">Administrator</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Section -->
    <div class="nav-section">
      <p class="nav-label">Overview</p>

      <ul class="pc-navbar">
        <li>
          <a href="../admin/dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li>
          <a href="../admin/analytics.php" class="menu-item <?php echo ($current_page == 'analytics.php') ? 'active' : ''; ?>">
            <i class="fas fa-chart-bar"></i>
            <span>Analytics</span>
          </a>
        </li>
      </ul>

      <p class="nav-label">Management</p>
      <ul class="pc-navbar">
        <li>
          <a href="../admin/user_management.php" class="menu-item <?php echo ($current_page == 'user_management.php') ? 'active' : ''; ?>">
            <i class="fas fa-users"></i>
            <span>User Management</span>
          </a>
        </li>

        <li>
          <a href="../admin/user_approval.php" class="menu-item <?php echo ($current_page == 'user_approval.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-check"></i>
            <span>User Approval</span>
            <?php if ($pendingCount > 0): ?>
              <span class="menu-badge"><?php echo $pendingCount; ?></span>
            <?php endif; ?>
          </a>
        </li>

        <li>
          <a href="../admin/account_ownership.php" class="menu-item <?php echo ($current_page == 'account_ownership.php') ? 'active' : ''; ?>">
            <i class="fas fa-user-tag"></i>
            <span>Account Ownership</span>
          </a>
        </li>
      </ul>

      <p class="nav-label">Security</p>
      <ul class="pc-navbar">
        <li>
          <a href="../admin/security_control.php" class="menu-item <?php echo ($current_page == 'security_control.php') ? 'active' : ''; ?>">
            <i class="fas fa-shield-halved"></i>
            <span>Security Control</span>
          </a>
        </li>

        <li>
          <a href="../admin/api_keys.php" class="menu-item <?php echo ($current_page == 'api_keys.php') ? 'active' : ''; ?>">
            <i class="fas fa-key"></i>
            <span>API Keys</span>
          </a>
        </li>
      </ul>

      <div class="nav-divider"></div>

      <ul class="pc-navbar">
        <li>
          <a href="../admin/settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i class="fas fa-cog"></i>
            <span>Settings</span>
          </a>
        </li>

        <li>
          <a href="#!" onclick="return alert('FinTrack Admin Panel\n\nVersion: 2.0\nDeveloper: Your Name\nContact: admin@fintrack.com\n\n© 2025 FinTrack. All rights reserved.');" class="menu-item">
            <i class="fas fa-info-circle"></i>
            <span>About</span>
          </a>
        </li>

        <li>
          <a href="../logout.php" class="menu-item logout-btn" onclick="return confirm('Do you really want to Log Out?')">
            <i class="fas fa-sign-out-alt"></i>
            <span>Log Out</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- [ Admin Sidebar Menu ] end -->