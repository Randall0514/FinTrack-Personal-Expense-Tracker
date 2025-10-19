<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

// Database connection
include '../database/config/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

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

$user_id = $user['id'];

// Fetch user profile picture
$userProfilePic = '../assets/images/user/default-avatar.png'; // Default
$profile_stmt = $conn->prepare("SELECT profile_picture FROM users WHERE id = ?");
if ($profile_stmt) {
    $profile_stmt->bind_param("i", $user_id);
    $profile_stmt->execute();
    $profile_result = $profile_stmt->get_result();
    if ($profile_result->num_rows > 0) {
        $profile_data = $profile_result->fetch_assoc();
        if (!empty($profile_data['profile_picture'])) {
            $userProfilePic = $profile_data['profile_picture'];
        }
    }
    $profile_stmt->close();
}

// Fetch user's expense data for AI context
$expenses = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND archived = 0 ORDER BY date DESC LIMIT 50");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    $stmt->close();
}

// Calculate spending statistics
$totalSpending = array_sum(array_column($expenses, 'amount'));
$categoryBreakdown = [];
foreach ($expenses as $expense) {
    $cat = $expense['category'];
    $categoryBreakdown[$cat] = ($categoryBreakdown[$cat] ?? 0) + $expense['amount'];
}

// Fetch recent chat history
$chatHistory = [];
$history_stmt = $conn->prepare("SELECT user_message, ai_response, created_at FROM chat_history WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
if ($history_stmt) {
    $history_stmt->bind_param("i", $user_id);
    $history_stmt->execute();
    $history_result = $history_stmt->get_result();
    while ($row = $history_result->fetch_assoc()) {
        $chatHistory[] = $row;
    }
    $history_stmt->close();
}
$chatHistory = array_reverse($chatHistory); // Oldest first
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
  <title>FinAI - AI Financial Assistant</title>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      min-height: 100vh;
      font-weight: 600;
    }

    .pc-container { background: transparent !important; }
    .pc-content { padding: 20px; height: calc(100vh - 80px); }

    .page-header {
      background: rgba(255, 255, 255, 0.15);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 20px;
      margin-bottom: 25px;
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .page-header-title h5 {
      color: white !important;
      font-weight: 700;
      font-size: 1.8rem;
    }

    .breadcrumb { background: transparent !important; }
    .breadcrumb-item, .breadcrumb-item a {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 600;
    }

    /* Internet Check Modal */
    .internet-modal-overlay {
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background: rgba(0, 0, 0, 0.7);
      backdrop-filter: blur(8px);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10000;
      animation: fadeIn 0.3s ease-out;
    }

    .internet-modal {
      background: white;
      border-radius: 20px;
      padding: 40px;
      max-width: 500px;
      width: 90%;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
      animation: slideUp 0.4s ease-out;
      text-align: center;
    }

    .modal-icon {
      width: 100px;
      height: 100px;
      margin: 0 auto 25px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 3rem;
      animation: pulse 2s infinite;
    }

    .modal-icon.checking {
      animation: spin 1s linear infinite, pulse 2s infinite;
    }

    .modal-icon.success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      animation: scaleIn 0.5s ease-out;
    }

    .modal-icon.error {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      animation: shake 0.5s ease-out;
    }

    .internet-modal h2 {
      color: #1f2937;
      font-weight: 700;
      font-size: 1.8rem;
      margin-bottom: 15px;
    }

    .internet-modal p {
      color: #6b7280;
      font-size: 1rem;
      line-height: 1.6;
      margin-bottom: 25px;
    }

    .connection-status {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 10px;
      padding: 15px;
      background: #f3f4f6;
      border-radius: 10px;
      margin-bottom: 25px;
      font-weight: 600;
    }

    .status-dot {
      width: 12px;
      height: 12px;
      border-radius: 50%;
      animation: pulse 2s infinite;
    }

    .status-dot.checking {
      background: #f59e0b;
    }

    .status-dot.connected {
      background: #10b981;
    }

    .status-dot.disconnected {
      background: #ef4444;
    }

    .modal-buttons {
      display: flex;
      gap: 15px;
      justify-content: center;
    }

    .modal-btn {
      padding: 14px 32px;
      border-radius: 12px;
      font-weight: 600;
      font-size: 1rem;
      cursor: pointer;
      border: none;
      transition: all 0.3s;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .modal-btn-primary {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
    }

    .modal-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .modal-btn-secondary {
      background: #f3f4f6;
      color: #4b5563;
    }

    .modal-btn-secondary:hover {
      background: #e5e7eb;
    }

    .modal-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
      transform: none !important;
    }

    .loading-spinner {
      display: inline-block;
      width: 18px;
      height: 18px;
      border: 3px solid rgba(255, 255, 255, 0.3);
      border-top-color: white;
      border-radius: 50%;
      animation: spin 0.8s linear infinite;
    }

    @keyframes fadeIn {
      from { opacity: 0; }
      to { opacity: 1; }
    }

    @keyframes slideUp {
      from { opacity: 0; transform: translateY(30px); }
      to { opacity: 1; transform: translateY(0); }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.8; transform: scale(0.95); }
    }

    @keyframes spin {
      from { transform: rotate(0deg); }
      to { transform: rotate(360deg); }
    }

    @keyframes scaleIn {
      from { transform: scale(0); }
      to { transform: scale(1); }
    }

    @keyframes shake {
      0%, 100% { transform: translateX(0); }
      25% { transform: translateX(-10px); }
      75% { transform: translateX(10px); }
    }

    .chat-container {
      display: flex;
      gap: 20px;
      height: calc(100vh - 200px);
      opacity: 0;
      pointer-events: none;
      transition: opacity 0.3s;
    }

    .chat-container.active {
      opacity: 1;
      pointer-events: auto;
    }

    .chat-sidebar {
      width: 300px;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      padding: 20px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      overflow-y: auto;
    }

    .chat-sidebar h6 {
      color: #667eea;
      font-weight: 700;
      font-size: 1rem;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .quick-action-btn {
      width: 100%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px 16px;
      border: none;
      border-radius: 10px;
      margin-bottom: 10px;
      cursor: pointer;
      font-weight: 600;
      font-size: 0.9rem;
      display: flex;
      align-items: center;
      gap: 10px;
      transition: all 0.3s;
      text-align: left;
    }

    .quick-action-btn:hover {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .insight-card {
      background: linear-gradient(135deg, #f0f4ff 0%, #e8edff 100%);
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 15px;
      border-left: 4px solid #667eea;
    }

    .insight-card h6 {
      color: #667eea;
      font-size: 0.85rem;
      margin-bottom: 8px;
      font-weight: 700;
    }

    .insight-card p {
      color: #4b5563;
      font-size: 0.9rem;
      margin: 0;
      line-height: 1.5;
    }

    .chat-main {
      flex: 1;
      background: rgba(255, 255, 255, 0.95);
      backdrop-filter: blur(10px);
      border-radius: 15px;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
      display: flex;
      flex-direction: column;
      overflow: hidden;
    }

    .chat-header {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px 25px;
      border-radius: 15px 15px 0 0;
      display: flex;
      align-items: center;
      gap: 15px;
      position: relative;
    }

    .clear-chat-btn {
      position: absolute;
      right: 25px;
      top: 50%;
      transform: translateY(-50%);
      background: rgba(255, 255, 255, 0.2);
      backdrop-filter: blur(10px);
      color: white;
      border: 2px solid rgba(255, 255, 255, 0.3);
      padding: 10px 18px;
      border-radius: 10px;
      font-weight: 600;
      font-size: 0.9rem;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
    }

    .clear-chat-btn:hover {
      background: rgba(255, 255, 255, 0.3);
      border-color: rgba(255, 255, 255, 0.5);
      transform: translateY(-50%) scale(1.05);
    }

    .clear-chat-btn i {
      font-size: 1.1rem;
    }

    .chat-header-avatar {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: white;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 1.5rem;
      box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
    }

    .chat-header-info h5 {
      margin: 0;
      font-weight: 700;
      font-size: 1.2rem;
    }

    .chat-header-info p {
      margin: 0;
      font-size: 0.85rem;
      opacity: 0.9;
    }

    .chat-status {
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .status-dot-header {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #10b981;
      animation: pulse 2s infinite;
    }

    .chat-messages {
      flex: 1;
      overflow-y: auto;
      padding: 25px;
      background: linear-gradient(to bottom, #f9fafb 0%, #ffffff 100%);
    }

    .chat-messages::-webkit-scrollbar {
      width: 8px;
    }

    .chat-messages::-webkit-scrollbar-track {
      background: #f1f1f1;
      border-radius: 10px;
    }

    .chat-messages::-webkit-scrollbar-thumb {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      border-radius: 10px;
    }

    .message {
      display: flex;
      gap: 12px;
      margin-bottom: 20px;
      animation: fadeInUp 0.4s ease-out;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .message-avatar {
      width: 40px;
      height: 40px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      flex-shrink: 0;
      overflow: hidden;
      position: relative;
    }

    .message-avatar img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      border-radius: 50%;
    }

    .message.user {
      flex-direction: row-reverse;
      justify-content: flex-start;
    }

    .message.user .message-avatar {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: 2px solid white;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.15);
    }

    .message.user .message-content {
      display: flex;
      flex-direction: column;
      align-items: flex-end;
      max-width: 70%;
    }

    .message.user .message-header {
      flex-direction: row-reverse;
    }

    .message.user .message-bubble {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-bottom-right-radius: 4px;
    }

    .message.ai {
      flex-direction: row;
    }

    .message.ai .message-avatar {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      font-size: 1.3rem;
    }

    .message-content {
      flex: 1;
      max-width: 70%;
    }

    .message.ai .message-content {
      align-items: flex-start;
    }

    .message-header {
      display: flex;
      align-items: center;
      gap: 8px;
      margin-bottom: 6px;
    }

    .message-sender {
      font-weight: 700;
      font-size: 0.9rem;
      color: #1f2937;
    }

    .message-time {
      font-size: 0.75rem;
      color: #9ca3af;
    }

    .message-bubble {
      padding: 12px 16px;
      border-radius: 12px;
      line-height: 1.6;
      font-size: 0.95rem;
    }

    .message.ai .message-bubble {
      background: white;
      color: #1f2937;
      border: 2px solid #e5e7eb;
      border-bottom-left-radius: 4px;
    }

    .message.ai .message-bubble strong {
      color: #667eea;
    }

    .typing-indicator {
      display: none;
      align-items: center;
      gap: 12px;
      margin-bottom: 20px;
    }

    .typing-indicator.active {
      display: flex;
    }

    .typing-dots {
      display: flex;
      gap: 4px;
      padding: 12px 16px;
      background: white;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
    }

    .typing-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: #667eea;
      animation: typing 1.4s infinite;
    }

    .typing-dot:nth-child(2) { animation-delay: 0.2s; }
    .typing-dot:nth-child(3) { animation-delay: 0.4s; }

    @keyframes typing {
      0%, 60%, 100% { transform: translateY(0); }
      30% { transform: translateY(-10px); }
    }

    .chat-input-container {
      padding: 20px 25px;
      background: white;
      border-top: 2px solid #f3f4f6;
      border-radius: 0 0 15px 15px;
    }

    .chat-input-wrapper {
      display: flex;
      gap: 12px;
      align-items: flex-end;
    }

    .chat-input {
      flex: 1;
      padding: 12px 16px;
      border: 2px solid #e5e7eb;
      border-radius: 12px;
      font-size: 0.95rem;
      resize: none;
      font-family: 'Inter', sans-serif;
      transition: all 0.3s;
      max-height: 120px;
      min-height: 48px;
    }

    .chat-input:focus {
      outline: none;
      border-color: #667eea;
      box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
    }

    .send-btn {
      padding: 12px 24px;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 8px;
      transition: all 0.3s;
      min-width: 100px;
      justify-content: center;
    }

    .send-btn:hover:not(:disabled) {
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
    }

    .send-btn:disabled {
      opacity: 0.6;
      cursor: not-allowed;
    }

    .welcome-message {
      text-align: center;
      padding: 60px 20px;
      color: #6b7280;
    }

    .welcome-message h3 {
      color: #667eea;
      font-weight: 700;
      font-size: 1.8rem;
      margin-bottom: 15px;
    }

    .welcome-message p {
      font-size: 1rem;
      margin-bottom: 30px;
      line-height: 1.6;
    }

    .suggestion-chips {
      display: flex;
      gap: 10px;
      justify-content: center;
      flex-wrap: wrap;
      max-width: 600px;
      margin: 0 auto;
    }

    .suggestion-chip {
      padding: 10px 18px;
      background: white;
      border: 2px solid #e5e7eb;
      border-radius: 20px;
      cursor: pointer;
      transition: all 0.3s;
      font-size: 0.9rem;
      font-weight: 600;
      color: #4b5563;
    }

    .suggestion-chip:hover {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      border-color: #667eea;
      transform: translateY(-2px);
    }

    @media (max-width: 768px) {
      .chat-container {
        flex-direction: column;
        height: auto;
      }

      .chat-sidebar {
        width: 100%;
        height: auto;
        max-height: 300px;
      }

      .chat-main {
        height: 500px;
      }

      .message.user .message-content,
      .message.ai .message-content {
        max-width: 85%;
      }

      .internet-modal {
        padding: 30px 20px;
      }

      .modal-buttons {
        flex-direction: column;
      }

      .modal-btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
    </div>
  </div>

  <!-- Internet Check Modal -->
  <div class="internet-modal-overlay" id="internetModal">
    <div class="internet-modal">
      <div class="modal-icon" id="modalIcon">
        🌐
      </div>
      <h2 id="modalTitle">Internet Connection Required</h2>
      <p id="modalMessage">FinAI requires an active internet connection to provide AI-powered financial insights. Please ensure you're connected to the internet before continuing.</p>
      
      <div class="connection-status" id="connectionStatus">
        <span class="status-dot checking" id="statusDot"></span>
        <span id="statusText">Checking connection...</span>
      </div>

      <div class="modal-buttons" id="modalButtons">
        <button class="modal-btn modal-btn-primary" id="checkConnectionBtn" onclick="checkInternetConnection()">
          <span class="loading-spinner" style="display: none;" id="checkingSpinner"></span>
          <span id="checkBtnText">Check Connection</span>
        </button>
        <button class="modal-btn modal-btn-secondary" onclick="window.location.href='dashboard.php'">
          ← Back to Dashboard
        </button>
      </div>
    </div>
  </div>

  <?php include '../includes/sidebar.php'; ?>
  <?php include '../includes/header.php'; ?>

  <div class="pc-container">
    <div class="pc-content">
      <!-- Page Header -->
      <div class="page-header">
        <div class="page-block">
          <div class="page-header-title">
            <h5 class="mb-0 font-medium">🤖 FinAI - Your AI Financial Assistant</h5>
          </div>
          <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item" aria-current="page">FinAI Chat</li>
          </ul>
        </div>
      </div>

      <!-- Chat Container -->
      <div class="chat-container" id="chatContainer">
        <!-- Sidebar -->
        <div class="chat-sidebar">
          <h6>
            <i data-feather="zap"></i>
            Quick Actions
          </h6>
          
          <button class="quick-action-btn" onclick="sendQuickMessage('Analyze my spending patterns and give me detailed insights')">
            <i data-feather="trending-up"></i>
            <span>Analyze Spending</span>
          </button>
          
          <button class="quick-action-btn" onclick="sendQuickMessage('Give me personalized budget recommendations based on my spending')">
            <i data-feather="target"></i>
            <span>Budget Tips</span>
          </button>
          
          <button class="quick-action-btn" onclick="sendQuickMessage('How can I save more money based on my expenses?')">
            <i data-feather="piggy-bank"></i>
            <span>Saving Advice</span>
          </button>
          
          <button class="quick-action-btn" onclick="sendQuickMessage('What are my biggest expenses and how can I reduce them?')">
            <i data-feather="bar-chart-2"></i>
            <span>Expense Breakdown</span>
          </button>

          <hr style="margin: 20px 0; border-color: #e5e7eb;">

          <h6 style="margin-top: 20px;">
            <i data-feather="lightbulb"></i>
            Quick Insights
          </h6>

          <div class="insight-card">
            <h6>💰 Total Spending</h6>
            <p>₱ <?php echo number_format($totalSpending, 2); ?></p>
          </div>

          <div class="insight-card">
            <h6>📊 Top Category</h6>
            <p><?php 
              if (!empty($categoryBreakdown)) {
                $topCategory = array_keys($categoryBreakdown, max($categoryBreakdown))[0];
                echo $topCategory . ' (₱' . number_format($categoryBreakdown[$topCategory], 2) . ')';
              } else {
                echo 'No expenses yet';
              }
            ?></p>
          </div>

          <div class="insight-card">
            <h6>📝 Total Expenses</h6>
            <p><?php echo count($expenses); ?> transactions</p>
          </div>
        </div>

        <!-- Main Chat Area -->
        <div class="chat-main">
          <!-- Chat Header -->
          <div class="chat-header">
            <div class="chat-header-avatar">
              🤖
            </div>
            <div class="chat-header-info">
              <h5>FinAI Assistant</h5>
              <div class="chat-status">
                <span class="status-dot-header"></span>
                <p>Powered by Google Gemini AI</p>
              </div>
            </div>
            <button class="clear-chat-btn" onclick="clearChatHistory()" title="Clear all chat history">
              <i data-feather="trash-2"></i>
              <span>Clear Chat</span>
            </button>
          </div>

          <!-- Chat Messages -->
          <div class="chat-messages" id="chatMessages">
            <?php if (empty($chatHistory)): ?>
            <div class="welcome-message" id="welcomeMessage">
              <h3>👋 Hello, <?php echo htmlspecialchars($user['fullname']); ?>!</h3>
              <p>I'm FinAI, your AI-powered financial assistant. I can help you analyze your spending, provide personalized budget recommendations, and answer questions about your finances using advanced AI technology.</p>
              <div class="suggestion-chips">
                <div class="suggestion-chip" onclick="sendQuickMessage('Analyze my spending patterns')">
                  Analyze Spending
                </div>
                <div class="suggestion-chip" onclick="sendQuickMessage('Give me budget tips')">
                  Budget Tips
                </div>
                <div class="suggestion-chip" onclick="sendQuickMessage('How can I save money?')">
                  Save Money
                </div>
              </div>
            </div>
            <?php else: ?>
            <!-- Load previous chat history -->
            <?php foreach ($chatHistory as $chat): ?>
              <div class="message user">
                <div class="message-avatar">
                  <?php if (file_exists($userProfilePic)): ?>
                    <img src="<?php echo htmlspecialchars($userProfilePic); ?>" alt="User">
                  <?php else: ?>
                    <?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>
                  <?php endif; ?>
                </div>
                <div class="message-content">
                  <div class="message-header">
                    <span class="message-sender"><?php echo htmlspecialchars($user['fullname']); ?></span>
                    <span class="message-time"><?php echo date('g:i A', strtotime($chat['created_at'])); ?></span>
                  </div>
                  <div class="message-bubble"><?php echo htmlspecialchars($chat['user_message']); ?></div>
                </div>
              </div>
              
              <div class="message ai">
                <div class="message-avatar">🤖</div>
                <div class="message-content">
                  <div class="message-header">
                    <span class="message-sender">FinAI</span>
                    <span class="message-time"><?php echo date('g:i A', strtotime($chat['created_at'])); ?></span>
                  </div>
                  <div class="message-bubble"><?php echo $chat['ai_response']; ?></div>
                </div>
              </div>
            <?php endforeach; ?>
            <?php endif; ?>

            <!-- Typing Indicator -->
            <div class="typing-indicator" id="typingIndicator">
              <div class="message-avatar" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white;">
                🤖
              </div>
              <div class="typing-dots">
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
                <div class="typing-dot"></div>
              </div>
            </div>
          </div>

          <!-- Chat Input -->
          <div class="chat-input-container">
            <div class="chat-input-wrapper">
              <textarea 
                class="chat-input" 
                id="chatInput" 
                placeholder="Ask me anything about your finances..."
                rows="1"
                onkeypress="handleKeyPress(event)"
              ></textarea>
              <button class="send-btn" id="sendBtn" onclick="sendMessage()">
                <i data-feather="send"></i>
                <span>Send</span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>

  <?php include '../includes/footer.php'; ?>

  <script src="../assets/js/plugins/simplebar.min.js"></script>
  <script src="../assets/js/plugins/popper.min.js"></script>
  <script src="../assets/js/icon/custom-icon.js"></script>
  <script src="../assets/js/plugins/feather.min.js"></script>
  <script src="../assets/js/component.js"></script>
  <script src="../assets/js/theme.js"></script>
  <script src="../assets/js/script.js"></script>

  <script>
    // User data
    const userName = '<?php echo htmlspecialchars($user['fullname']); ?>';
    const userInitial = '<?php echo strtoupper(substr($user['fullname'], 0, 1)); ?>';
    const userProfilePic = '<?php echo htmlspecialchars($userProfilePic); ?>';

    let isConnected = false;
    let connectionCheckAttempts = 0;
    const MAX_ATTEMPTS = 3;

    // Check internet connection on page load
    window.addEventListener('load', function() {
      checkInternetConnection();
    });

    // Internet connection check function
    async function checkInternetConnection() {
      const modalIcon = document.getElementById('modalIcon');
      const modalTitle = document.getElementById('modalTitle');
      const modalMessage = document.getElementById('modalMessage');
      const statusDot = document.getElementById('statusDot');
      const statusText = document.getElementById('statusText');
      const checkBtn = document.getElementById('checkConnectionBtn');
      const checkingSpinner = document.getElementById('checkingSpinner');
      const checkBtnText = document.getElementById('checkBtnText');

      // Reset to checking state
      modalIcon.className = 'modal-icon checking';
      modalIcon.textContent = '🌐';
      statusDot.className = 'status-dot checking';
      statusText.textContent = 'Checking connection...';
      checkBtn.disabled = true;
      checkingSpinner.style.display = 'inline-block';
      checkBtnText.textContent = 'Checking...';

      connectionCheckAttempts++;

      try {
        // Test 1: Check if online
        if (!navigator.onLine) {
          throw new Error('No internet connection detected');
        }

        // Test 2: Try to fetch from a reliable endpoint
        const controller = new AbortController();
        const timeoutId = setTimeout(() => controller.abort(), 5000);

        const response = await fetch('https://www.google.com/favicon.ico', {
          method: 'HEAD',
          mode: 'no-cors',
          cache: 'no-cache',
          signal: controller.signal
        });

        clearTimeout(timeoutId);

        // Test 3: Verify we can reach our own API
        const apiTest = await fetch('chat_handler.php', {
          method: 'OPTIONS',
          headers: { 'Content-Type': 'application/json' }
        });

        // Connection successful!
        isConnected = true;
        modalIcon.className = 'modal-icon success';
        modalIcon.textContent = '✅';
        modalTitle.textContent = 'Connection Successful!';
        modalMessage.textContent = 'Your internet connection is active and FinAI is ready to help you with your financial questions.';
        statusDot.className = 'status-dot connected';
        statusText.textContent = 'Connected • Ready to use';
        statusText.style.color = '#10b981';

        checkBtn.disabled = false;
        checkingSpinner.style.display = 'none';
        checkBtnText.textContent = 'Start Using FinAI';
        checkBtn.onclick = closeModalAndStartChat;

        // Auto-close after 2 seconds
        setTimeout(() => {
          closeModalAndStartChat();
        }, 2000);

      } catch (error) {
        console.error('Connection check failed:', error);
        
        isConnected = false;
        modalIcon.className = 'modal-icon error';
        modalIcon.textContent = '❌';
        modalTitle.textContent = 'No Internet Connection';
        
        if (connectionCheckAttempts >= MAX_ATTEMPTS) {
          modalMessage.innerHTML = '<strong>Unable to connect after ' + MAX_ATTEMPTS + ' attempts.</strong><br><br>Please check:<br>• Your WiFi or mobile data is turned on<br>• You have an active internet connection<br>• Try disabling airplane mode<br>• Check your network settings';
        } else {
          modalMessage.innerHTML = '<strong>Connection attempt ' + connectionCheckAttempts + ' of ' + MAX_ATTEMPTS + ' failed.</strong><br><br>Please ensure you have an active internet connection and try again.';
        }
        
        statusDot.className = 'status-dot disconnected';
        statusText.textContent = 'Disconnected • No internet access';
        statusText.style.color = '#ef4444';

        checkBtn.disabled = false;
        checkingSpinner.style.display = 'none';
        
        if (connectionCheckAttempts >= MAX_ATTEMPTS) {
          checkBtnText.textContent = 'Retry Connection';
          connectionCheckAttempts = 0; // Reset for next time
        } else {
          checkBtnText.textContent = 'Try Again (' + (MAX_ATTEMPTS - connectionCheckAttempts) + ' left)';
        }
      }
    }

    // Close modal and enable chat
    function closeModalAndStartChat() {
      if (!isConnected) {
        alert('⚠️ Please establish an internet connection first before using FinAI.');
        return;
      }

      const modal = document.getElementById('internetModal');
      const chatContainer = document.getElementById('chatContainer');
      
      modal.style.animation = 'fadeOut 0.3s ease-out forwards';
      
      setTimeout(() => {
        modal.style.display = 'none';
        chatContainer.classList.add('active');
        
        // Focus on input
        document.getElementById('chatInput').focus();
      }, 300);
    }

    // Monitor connection changes
    window.addEventListener('online', function() {
      console.log('Connection restored');
      if (!isConnected) {
        // Show reconnection notification
        const notification = document.createElement('div');
        notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 10001; animation: slideInRight 0.5s ease-out;';
        notification.innerHTML = '<strong>✅ Connected!</strong><br>Internet connection restored.';
        document.body.appendChild(notification);
        
        setTimeout(() => {
          notification.remove();
        }, 3000);
        
        isConnected = true;
      }
    });

    window.addEventListener('offline', function() {
      console.log('Connection lost');
      isConnected = false;
      
      // Show disconnection notification
      const notification = document.createElement('div');
      notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); color: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 10001; animation: slideInRight 0.5s ease-out;';
      notification.innerHTML = '<strong>⚠️ Disconnected!</strong><br>Internet connection lost.';
      document.body.appendChild(notification);
      
      setTimeout(() => {
        notification.remove();
      }, 5000);
    });

    // Auto-resize textarea
    const chatInput = document.getElementById('chatInput');
    chatInput.addEventListener('input', function() {
      this.style.height = 'auto';
      this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // Handle Enter key
    function handleKeyPress(event) {
      if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
      }
    }

    // Send message function
    async function sendMessage() {
      // Check internet connection before sending
      if (!isConnected || !navigator.onLine) {
        alert('⚠️ No internet connection. Please connect to the internet to use FinAI.');
        return;
      }

      const input = document.getElementById('chatInput');
      const message = input.value.trim();
      
      if (!message) return;
      
      // Hide welcome message
      const welcomeMsg = document.getElementById('welcomeMessage');
      if (welcomeMsg) welcomeMsg.style.display = 'none';
      
      // Disable send button
      const sendBtn = document.getElementById('sendBtn');
      sendBtn.disabled = true;
      
      // Add user message
      addMessage(message, 'user');
      
      // Clear input
      input.value = '';
      input.style.height = 'auto';
      
      // Move typing indicator to bottom and show it
      const typingIndicator = document.getElementById('typingIndicator');
      const messagesContainer = document.getElementById('chatMessages');
      messagesContainer.appendChild(typingIndicator);
      typingIndicator.classList.add('active');
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
      
      try {
        // Call backend API
        const response = await fetch('chat_handler.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({ message: message })
        });
        
        const data = await response.json();
        
        // Hide typing indicator
        typingIndicator.classList.remove('active');
        
        if (data.success) {
          addMessage(data.response, 'ai');
        } else {
          // ENHANCED ERROR MESSAGE - Shows full details
          let errorMsg = '❌ Sorry, I encountered an error: ' + (data.message || 'Unknown error');
          
          if (data.details) {
            errorMsg += '<br><br><strong>Details:</strong> ' + data.details;
          }
          
          if (data.http_code) {
            errorMsg += '<br><strong>HTTP Code:</strong> ' + data.http_code;
          }
          
          addMessage(errorMsg, 'ai');
          
          // Also log to console for debugging
          console.error('Full API Error:', data);
        }
      } catch (error) {
        // Hide typing indicator
        const typingIndicator = document.getElementById('typingIndicator');
        typingIndicator.classList.remove('active');
        
        // Check if it's a network error
        if (!navigator.onLine) {
          addMessage('❌ Connection Lost: Your internet connection was interrupted. Please check your connection and try again.', 'ai');
          isConnected = false;
        } else {
          addMessage('❌ Network Error: Could not connect to the AI service. Please check your internet connection.<br><br><strong>Error:</strong> ' + error.message, 'ai');
        }
        
        console.error('Fetch Error:', error);
      } finally {
        // Re-enable send button
        sendBtn.disabled = false;
      }
    }

    // Quick message function
    function sendQuickMessage(message) {
      if (!isConnected || !navigator.onLine) {
        alert('⚠️ No internet connection. Please connect to the internet to use FinAI.');
        return;
      }
      document.getElementById('chatInput').value = message;
      sendMessage();
    }

    // Add message to chat
    function addMessage(text, sender) {
      const messagesContainer = document.getElementById('chatMessages');
      const time = new Date().toLocaleTimeString('en-US', { hour: '2-digit', minute: '2-digit' });
      
      const messageDiv = document.createElement('div');
      messageDiv.className = `message ${sender}`;
      
      // Create avatar HTML based on sender
      let avatarHTML;
      if (sender === 'user') {
        // Check if profile picture exists
        avatarHTML = `<div class="message-avatar">
          <img src="${userProfilePic}" alt="User" onerror="this.style.display='none'; this.parentElement.innerHTML='${userInitial}'">
        </div>`;
      } else {
        avatarHTML = `<div class="message-avatar">🤖</div>`;
      }
      
      const name = sender === 'user' ? userName : 'FinAI';
      
      messageDiv.innerHTML = `
        ${avatarHTML}
        <div class="message-content">
          <div class="message-header">
            <span class="message-sender">${name}</span>
            <span class="message-time">${time}</span>
          </div>
          <div class="message-bubble">${text}</div>
        </div>
      `;
      
      messagesContainer.appendChild(messageDiv);
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
      
      // Reinitialize feather icons
      if (typeof feather !== 'undefined') {
        feather.replace();
      }
    }

    // Scroll to bottom on page load if there's chat history
    window.addEventListener('load', function() {
      const messagesContainer = document.getElementById('chatMessages');
      messagesContainer.scrollTop = messagesContainer.scrollHeight;
    });

    // Clear chat history function
    async function clearChatHistory() {
      if (!confirm('🗑️ Are you sure you want to clear all chat history? This action cannot be undone.')) {
        return;
      }

      try {
        const response = await fetch('clear_chat.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          }
        });

        const data = await response.json();

        if (data.success) {
          // Clear the chat messages from UI
          const messagesContainer = document.getElementById('chatMessages');
          messagesContainer.innerHTML = `
            <div class="welcome-message" id="welcomeMessage">
              <h3>👋 Hello, ${userName}!</h3>
              <p>I'm FinAI, your AI-powered financial assistant. I can help you analyze your spending, provide personalized budget recommendations, and answer questions about your finances using advanced AI technology.</p>
              <div class="suggestion-chips">
                <div class="suggestion-chip" onclick="sendQuickMessage('Analyze my spending patterns')">
                  Analyze Spending
                </div>
                <div class="suggestion-chip" onclick="sendQuickMessage('Give me budget tips')">
                  Budget Tips
                </div>
                <div class="suggestion-chip" onclick="sendQuickMessage('How can I save money?')">
                  Save Money
                </div>
              </div>
            </div>
          `;

          // Show success notification
          const notification = document.createElement('div');
          notification.style.cssText = 'position: fixed; top: 20px; right: 20px; background: linear-gradient(135deg, #10b981 0%, #059669 100%); color: white; padding: 15px 20px; border-radius: 10px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); z-index: 10001; animation: slideInRight 0.5s ease-out;';
          notification.innerHTML = '<strong>✅ Success!</strong><br>Chat history cleared.';
          document.body.appendChild(notification);

          setTimeout(() => {
            notification.remove();
          }, 3000);

        } else {
          alert('❌ Failed to clear chat history. Please try again.');
        }

      } catch (error) {
        console.error('Clear chat error:', error);
        alert('❌ Error: Could not clear chat history. Please check your connection.');
      }
    }

    // Initialize
    layout_change('false');
    layout_theme_sidebar_change('dark');
    change_box_container('false');
    layout_caption_change('true');
    layout_rtl_change('false');
    preset_change('preset-1');
    main_layout_change('vertical');
  </script>
</body>
</html