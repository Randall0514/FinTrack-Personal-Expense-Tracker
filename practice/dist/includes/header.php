<?php
// JWT Authentication for Header
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
$userData['email'] = $userData['email'] ?? 'user@example.com';
?>


<!-- [ Header Topbar ] start -->
<header class="pc-header" style="background: white; border-bottom: 1px solid #e5e7eb; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);">
  <div class="header-wrapper flex max-sm:px-[15px] px-[25px] grow items-center">

    <!-- [Mobile Media Block] start -->
    <div class="me-auto pc-mob-drp">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        <!-- ======= Menu collapse Icon ===== -->
        <li class="pc-h-item pc-sidebar-collapse max-lg:hidden lg:inline-flex">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0 hover:bg-gray-100 rounded-lg transition-all" id="sidebar-hide">
            <i data-feather="menu" class="text-gray-700"></i>
          </a>
        </li>
        <li class="pc-h-item pc-sidebar-popup lg:hidden">
          <a href="#" class="pc-head-link ltr:!ml-0 rtl:!mr-0 hover:bg-gray-100 rounded-lg transition-all" id="mobile-collapse">
            <i data-feather="menu" class="text-gray-700"></i>
          </a>
        </li>
      </ul>
    </div>
    <!-- [Mobile Media Block end] -->

    <div class="ms-auto">
      <ul class="inline-flex *:min-h-header-height *:inline-flex *:items-center">
        
        <!-- User Profile Dropdown -->
        <li class="dropdown pc-h-item header-user-profile">
          <a class="pc-head-link dropdown-toggle arrow-none me-0" data-pc-toggle="dropdown" href="#" role="button"
            aria-haspopup="false" data-pc-auto-close="outside" aria-expanded="false">
            <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" 
                alt="user-image" 
                class="w-8 h-8 rounded-full object-cover border-2 border-purple-300" />
          </a>
          <div class="dropdown-menu dropdown-user-profile dropdown-menu-end pc-h-dropdown p-2 overflow-hidden">
            <div class="dropdown-header flex items-center justify-between py-4 px-5" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
              <div class="flex mb-1 items-center">
                <div class="shrink-0">
                  <img src="<?php echo htmlspecialchars($userData['profile_picture']); ?>" 
                      alt="user-image" 
                      class="w-10 h-10 rounded-full object-cover border-2 border-white shadow-md" />
                </div>
                <div class="grow ms-4">
                  <h4 class="mb-1 text-white font-semibold"><?php echo htmlspecialchars($userData['fullname']); ?></h4>
                  <span class="text-white text-sm opacity-90"><?php echo htmlspecialchars($userData['email']); ?></span>
                </div>
              </div>
            </div>
            <div class="dropdown-body py-4 px-5">
              <div class="profile-notification-scroll position-relative" style="max-height: calc(100vh - 225px)">
                
                <a href="../admin/profile.php" class="dropdown-item-centered">
                  <i data-feather="user" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-gray-700 font-medium">My Profile</span>
                </a>
                
                <a href="../admin/settings.php" class="dropdown-item-centered">
                  <i data-feather="settings" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-gray-700 font-medium">Settings</span>
                </a>
                
                <a href="#" onclick="openHeaderAboutModal(); return false;" class="dropdown-item-centered">
                  <i data-feather="info" class="w-4 h-4 text-purple-600"></i>
                  <span class="text-gray-700 font-medium">About</span>
                </a>
                
                <hr class="my-3 border-gray-200">
                
                <div class="grid">
                  <a href="logout.php" 
                    class="btn flex items-center justify-center gap-2 px-4 py-2.5 rounded-lg text-white font-semibold transition-all"
                    style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);"
                    onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 20px rgba(102, 126, 234, 0.4)';"
                    onmouseout="this.style.transform=''; this.style.boxShadow='';"
                    onclick="return confirm('Do you really want to Log-Out?')">
                    <i data-feather="log-out" class="w-4 h-4"></i>
                    Log-Out
                  </a>
                </div>
              </div>
            </div>
          </div>
        </li>
        <!-- User Profile Dropdown end -->
        
      </ul>
    </div>
  </div>
</header>
<!-- [ Header ] end -->

<!-- About Modal (Header Version) -->
<div id="headerAboutModal" class="about-modal">
  <div class="modal-content">
    <div class="modal-header">
      <button class="close-modal-btn" onclick="closeHeaderAboutModal()">&times;</button>
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


<style>
/* About Modal Styles (Header Version) */

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
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

.modal-content {
    position: relative;
    background: white;
    margin: 8% auto;
    padding: 0;
    border-radius: 14px;
    width: 75%;
    max-width: 340px;
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
    padding: 1rem;
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
    width: 42px;
    height: 42px;
    margin: 0 auto 0.4rem;
    background: white;
    border-radius: 12px;
    padding: 0.5rem;
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
    font-size: 1.2rem;
    font-weight: 800;
    margin: 0 0 0.2rem 0;
    text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.2);
    position: relative;
    z-index: 1;
}

.modal-subtitle {
    color: rgba(255, 255, 255, 0.9);
    font-size: 0.75rem;
    margin: 0;
    font-weight: 500;
    position: relative;
    z-index: 1;
}

.modal-body {
    padding: 1rem;
}

.info-section {
    margin-bottom: 1rem;
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
    font-size: 0.9rem;
    font-weight: 600;
    padding: 0.6rem;
    background: #f3f4f6;
    border-radius: 8px;
    border-left: 3px solid #667eea;
    line-height: 1.4;
}

.modal-footer {
    background: #f9fafb;
    padding: 1rem 1.5rem;
    text-align: center;
    border-top: 1px solid #e5e7eb;
}

.copyright-text {
    color: #6b7280;
    font-size: 0.8rem;
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

/* Centered dropdown menu items */

.dropdown-item-centered {
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    gap: 0.75rem !important;
    padding: 0.625rem 1rem !important;
    margin-bottom: 0.5rem !important;
    border-radius: 0.5rem !important;
    transition: all 0.3s ease !important;
    text-decoration: none !important;
    background: transparent !important;
    color: inherit !important;
}

.dropdown-item-centered:hover {
    background: #f9fafb !important;
    color: inherit !important;
    text-decoration: none !important;
}

.dropdown-item-centered i {
    flex-shrink: 0 !important;
    width: 1rem !important;
    height: 1rem !important;
}

.dropdown-item-centered span {
    white-space: nowrap !important;
    font-weight: 500 !important;
    color: #374151 !important;
}

/* Responsive Design */

@media (max-width: 640px) {
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

<script>
// About Modal Functions
function openHeaderAboutModal() {
    document.getElementById('headerAboutModal').style.display = 'block';
    document.body.style.overflow = 'hidden';
    // Re-initialize feather icons for modal content
    if (typeof feather !== 'undefined') {
        feather.replace();
    }
}

function closeHeaderAboutModal() {
    document.getElementById('headerAboutModal').style.display = 'none';
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('headerAboutModal');
    if (event.target == modal) {
        closeHeaderAboutModal();
    }
}

// Close modal with Escape key
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        closeHeaderAboutModal();
    }
});
</script>