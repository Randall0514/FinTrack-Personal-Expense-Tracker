<?php
// JWT Authentication for Sidebar
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

// Check JWT via cookie
if (!isset($_COOKIE['jwt_token'])) {
    echo "<script>alert('You must log in first.'); window.location.href='../../login.php';</script>";
    exit;
}

$jwt = $_COOKIE['jwt_token'];

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user = (array) $decoded->data;
} catch (Exception $e) {
    echo "<script>alert('❌ Invalid or expired token. Please log in again.'); window.location.href='../../login.php';</script>";
    setcookie("jwt_token", "", time() - 3600, "/");
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
$userData['profile_picture'] = $userData['profile_picture'] ?? '../assets/images/user/avatar-2.jpg';
$userData['fullname'] = $userData['fullname'] ?? 'User';

// Get current page for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>

<style>
/* Enhanced Sidebar Styling - Fixed to fit wrapper */
.pc-sidebar {
  background: linear-gradient(180deg, #ffffff 0%, #f8f9fa 100%) !important;
  box-shadow: 2px 0 20px rgba(0, 0, 0, 0.08) !important;
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

/* Brand Section with Gradient Background */
.sidebar-brand {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 0.75rem;
  border-bottom: none !important;
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
  background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
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
  gap: 0.5rem;
  text-decoration: none;
  transition: transform 0.3s ease;
}

.logo-link:hover {
  transform: translateX(5px);
}

.logo-image {
  width: 2rem;
  height: 2rem;
  border-radius: 10px;
  object-fit: cover;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  border: 2px solid rgba(255, 255, 255, 0.3);
  transition: all 0.3s ease;
}

.logo-link:hover .logo-image {
  transform: rotate(5deg) scale(1.05);
  box-shadow: 0 6px 16px rgba(0, 0, 0, 0.3);
}

.logo-text h1 {
  color: white !important;
  font-size: 1rem;
  font-weight: 800;
  margin: 0;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.1);
  letter-spacing: 0.5px;
}

.logo-text p {
  color: rgba(255, 255, 255, 0.9) !important;
  font-size: 0.65rem;
  margin: 0;
  font-weight: 500;
}

/* User Profile Card */
.user-profile-card {
  margin-top: 0.5rem;
  padding-top: 0.5rem;
  border-top: 2px solid rgba(255, 255, 255, 0.2);
  position: relative;
  z-index: 1;
}

.user-profile-wrapper {
  background: rgba(255, 255, 255, 0.15);
  backdrop-filter: blur(10px);
  border-radius: 10px;
  padding: 0.5rem;
  display: flex;
  align-items: center;
  gap: 0.5rem;
  transition: all 0.3s ease;
  border: 1px solid rgba(255, 255, 255, 0.2);
}

.user-profile-wrapper:hover {
  background: rgba(255, 255, 255, 0.25);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.user-avatar {
  width: 2.25rem;
  height: 2.25rem;
  border-radius: 50%;
  object-fit: cover;
  border: 2px solid white;
  box-shadow: 0 4px 10px rgba(0, 0, 0, 0.2);
  transition: all 0.3s ease;
  flex-shrink: 0;
}

.user-profile-wrapper:hover .user-avatar {
  transform: scale(1.05);
  border-color: rgba(255, 255, 255, 0.9);
}

.user-info {
  flex: 1;
  min-width: 0;
}

.user-name {
  color: white !important;
  font-size: 0.9rem;
  font-weight: 700;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.1);
}

.user-email {
  color: rgba(255, 255, 255, 0.85) !important;
  font-size: 0.7rem;
  margin: 0;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
  font-weight: 500;
}

.user-status {
  display: flex;
  align-items: center;
  gap: 0.25rem;
  margin-top: 0.25rem;
}

.status-indicator {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #10b981;
  box-shadow: 0 0 8px rgba(16, 185, 129, 0.6);
  animation: blink 2s ease-in-out infinite;
  flex-shrink: 0;
}

@keyframes blink {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.5; }
}

.status-text {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.65rem;
  font-weight: 600;
  text-transform: uppercase;
  letter-spacing: 0.5px;
}

/* Navigation Section */
.nav-section {
  flex: 1;
  padding: 0.75rem;
  overflow-y: auto;
  overflow-x: hidden;
  margin-top: 0;
  min-height: 0;
}

.nav-section::-webkit-scrollbar {
  width: 6px;
}

.nav-section::-webkit-scrollbar-track {
  background: transparent;
}

.nav-section::-webkit-scrollbar-thumb {
  background: rgba(102, 126, 234, 0.3);
  border-radius: 10px;
}

.nav-section::-webkit-scrollbar-thumb:hover {
  background: rgba(102, 126, 234, 0.5);
}

.nav-label {
  color: #667eea !important;
  font-size: 0.7rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 0 0.75rem;
  margin: 1.5rem 0 0.5rem 0;
  display: flex;
  align-items: center;
  gap: 0.5rem;
}

.nav-label:first-child {
  margin-top: 0.5rem;
}

.nav-label::before {
  content: '';
  width: 20px;
  height: 2px;
  background: linear-gradient(90deg, #667eea, transparent);
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
  gap: 0.75rem;
  padding: 0.75rem;
  border-radius: 10px;
  margin-bottom: 0.375rem;
  transition: all 0.3s ease;
  border: 2px solid transparent;
  text-decoration: none;
  font-weight: 600;
  font-size: 0.9rem;
  position: relative;
  overflow: hidden;
}

.menu-item::before {
  content: '';
  position: absolute;
  top: 0;
  left: -100%;
  width: 100%;
  height: 100%;
  background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
  transition: left 0.5s ease;
}

.menu-item:hover::before {
  left: 100%;
}

.menu-item:not(.active) {
  color: #4b5563;
  background: white;
  border-color: #e5e7eb;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
}

.menu-item:not(.active):hover {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white !important;
  border-color: #667eea;
  transform: translateX(5px);
  box-shadow: 0 4px 12px rgba(102, 126, 234, 0.3);
}

.menu-item.active {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  color: white !important;
  border-color: #667eea;
  box-shadow: 0 6px 16px rgba(102, 126, 234, 0.4);
  transform: translateX(3px);
}

.menu-item i {
  width: 20px;
  height: 20px;
  flex-shrink: 0;
}

.menu-item span {
  flex: 1;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

/* Logout Button Special Style */
.menu-item.logout-btn {
  background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
  color: white !important;
  border-color: #ef4444;
  margin-top: 0.5rem;
}

.menu-item.logout-btn:hover {
  background: linear-gradient(135deg, #dc2626 0%, #b91c1c 100%);
  transform: translateX(5px) scale(1.02);
  box-shadow: 0 6px 20px rgba(239, 68, 68, 0.4);
}

/* About Modal Styles */
.about-modal {
  display: none;
  position: fixed;
  z-index: 9999;
  left: 0;
  top: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.6);
  backdrop-filter: blur(5px);
  animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
  from { opacity: 0; }
  to { opacity: 1; }
}

.modal-content {
  position: relative;
  background: white;
  margin: 1% auto;
  padding: 0;
  border-radius: 16px;
  width: 85%;
  max-width: 420px;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  animation: slideDown 0.4s ease;
  overflow: hidden;
}

@keyframes slideDown {
  from {
    transform: translateY(-50px);
    opacity: 0;
  }
  to {
    transform: translateY(0);
    opacity: 1;
  }
}

.modal-header {
  background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
  padding: 1.5rem;
  text-align: center;
  position: relative;
}

.modal-header::before {
  content: '';
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: url('data:image/svg+xml,<svg width="100" height="100" xmlns="http://www.w3.org/2000/svg"><circle cx="50" cy="50" r="40" fill="rgba(255,255,255,0.1)"/></svg>');
  opacity: 0.1;
}

.modal-logo {
  width: 60px;
  height: 60px;
  margin: 0 auto 0.75rem;
  background: white;
  border-radius: 15px;
  padding: 0.75rem;
  box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 1;
}

.modal-logo img {
  width: 100%;
  height: 100%;
  object-fit: contain;
}

.modal-title {
  color: white;
  font-size: 1.5rem;
  font-weight: 800;
  margin: 0 0 0.25rem 0;
  text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
  position: relative;
  z-index: 1;
}

.modal-subtitle {
  color: rgba(255, 255, 255, 0.9);
  font-size: 0.8rem;
  margin: 0;
  font-weight: 500;
  position: relative;
  z-index: 1;
}

.modal-body {
  padding: 1.5rem;
}

.info-section {
  margin-bottom: 1.5rem;
}

.info-label {
  display: flex;
  align-items: center;
  gap: 0.5rem;
  color: #667eea;
  font-size: 0.75rem;
  font-weight: 700;
  text-transform: uppercase;
  letter-spacing: 1px;
  margin-bottom: 0.5rem;
}

.info-value {
  color: #1f2937;
  font-size: 1rem;
  font-weight: 600;
  padding: 0.75rem;
  background: #f3f4f6;
  border-radius: 10px;
  border-left: 4px solid #667eea;
}

.modal-footer {
  background: #f9fafb;
  padding: 1.5rem 2rem;
  text-align: center;
  border-top: 1px solid #e5e7eb;
}

.copyright-text {
  color: #6b7280;
  font-size: 0.85rem;
  margin: 0;
  font-weight: 500;
}

.close-modal-btn {
  position: absolute;
  top: 1rem;
  right: 1rem;
  background: rgba(255, 255, 255, 0.2);
  border: none;
  color: white;
  font-size: 1.5rem;
  width: 36px;
  height: 36px;
  border-radius: 50%;
  cursor: pointer;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: all 0.3s ease;
  z-index: 2;
}

.close-modal-btn:hover {
  background: rgba(255, 255, 255, 0.3);
  transform: rotate(90deg);
}

/* Responsive Design */
@media (max-width: 768px) {
  .pc-sidebar {
    width: 260px;
  }
  
  .modal-content {
    width: 95%;
    margin: 10% auto;
  }
  
  .modal-header {
    padding: 1.5rem;
  }
  
  .modal-logo {
    width: 60px;
    height: 60px;
  }
  
  .modal-title {
    font-size: 1.5rem;
  }
  
  .modal-body {
    padding: 1.5rem;
  }
}
</style>

<!-- [ Sidebar Menu ] start -->
<nav class="pc-sidebar">
  <div class="navbar-wrapper h-full flex flex-col">
    <!-- Brand Section -->
    <div class="sidebar-brand">
      <div class="logo-container">
        <a href="../admin/dashboard.php" class="logo-link">
          <img src="../assets/images/Logo.png" alt="FinTrack Logo" class="logo-image" />
          <div class="logo-text">
            <h1>FinTrack</h1>
            <p>Expense Tracker</p>
          </div>
        </a>
        
        <!-- User Profile Card -->
        <div class="user-profile-card">
          <div class="user-profile-wrapper">
            <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" alt="Profile" class="user-avatar" />
            <div class="user-info">
              <p class="user-name"><?php echo htmlspecialchars($userData['fullname']); ?></p>
              <div class="user-status">
                <span class="status-indicator"></span>
                <span class="status-text">Online</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Navigation Section -->
    <div class="nav-section">
      <p class="nav-label">📊 Navigation</p>

      <ul class="pc-navbar" style="list-style: none; margin: 0; padding: 0;">
        <li>
          <a href="../admin/dashboard.php" class="menu-item <?php echo ($current_page == 'dashboard.php') ? 'active' : ''; ?>">
            <i data-feather="home" width="20"></i>
            <span>Dashboard</span>
          </a>
        </li>

        <li>
          <a href="../admin/manage_expenses.php" class="menu-item <?php echo ($current_page == 'manage_expenses.php') ? 'active' : ''; ?>">
            <i data-feather="credit-card" width="20"></i>
            <span>Manage Expenses</span>
          </a>
        </li>

        <li>
          <a href="../admin/summary_report.php" class="menu-item <?php echo ($current_page == 'summary_report.php') ? 'active' : ''; ?>">
            <i data-feather="pie-chart" width="20"></i>
            <span>Reports & Summary</span>
          </a>
        </li>

        <li>
          <a href="../admin/FinAI.php" class="menu-item <?php echo ($current_page == 'FinAI.php') ? 'active' : ''; ?>">
            <i data-feather="cpu" width="20"></i>
            <span>FinAI</span>
          </a>
        </li>
      </ul>

      <p class="nav-label">⚙️ Settings</p>
      <ul style="list-style: none; margin: 0; padding: 0;">
        <li>
          <a href="../admin/settings.php" class="menu-item <?php echo ($current_page == 'settings.php') ? 'active' : ''; ?>">
            <i data-feather="settings" width="20"></i>
            <span>Settings</span>
          </a>
        </li>

        <li>
          <a href="#!" onclick="openAboutModal(); return false;" class="menu-item">
            <i data-feather="info" width="20"></i>
            <span>About</span>
          </a>
        </li>

        <li>
          <a href="logout.php" class="menu-item logout-btn" onclick="return confirm('Do you really want to Log-Out?')">
            <i data-feather="log-out" width="20"></i>
            <span>Log Out</span>
          </a>
        </li>
      </ul>
    </div>
  </div>
</nav>
<!-- [ Sidebar Menu ] end -->

<!-- About Modal -->
<div id="aboutModal" class="about-modal">
  <div class="modal-content">
    <div class="modal-header">
      <button class="close-modal-btn" onclick="closeAboutModal()">&times;</button>
      <div class="modal-logo">
        <img src="../assets/images/Logo.png" alt="FinTrack Logo" />
      </div>
      <h2 class="modal-title">FinTrack</h2>
      <p class="modal-subtitle">Expense Tracker System</p>
    </div>
    
    <div class="modal-body">
      <div class="info-section">
        <div class="info-label">
          <i data-feather="users" width="16"></i>
          Group Members
        </div>
        <div class="info-value">
          Wrench Joseph Colorada<br>
          Cliven James Macaranas<br>
          Danilo Orozco<br>
          Daphnie Roanne Salinas<br>
          Randall Benedict Salvador
        </div>
      </div>
      
      <div class="info-section">
        <div class="info-label">
          <i data-feather="phone" width="16"></i>
          Contact Number
        </div>
        <div class="info-value">(Contact No.)</div>
      </div>
      
      <div class="info-section">
        <div class="info-label">
          <i data-feather="mail" width="16"></i>
          Email Address
        </div>
        <div class="info-value">daaq.salinas.up@phinmaed.com</div>
      </div>
    </div>
    
    <div class="modal-footer">
      <p class="copyright-text">© 2025 Software Solutions. All rights reserved.</p>
    </div>
  </div>
</div>

<script>
// Modal Functions
function openAboutModal() {
  document.getElementById('aboutModal').style.display = 'block';
  document.body.style.overflow = 'hidden';
  // Re-initialize feather icons for modal content
  if (typeof feather !== 'undefined') {
    feather.replace();
  }
}

function closeAboutModal() {
  document.getElementById('aboutModal').style.display = 'none';
  document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
  const modal = document.getElementById('aboutModal');
  if (event.target == modal) {
    closeAboutModal();
  }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
  if (event.key === 'Escape') {
    closeAboutModal();
  }
});
</script>