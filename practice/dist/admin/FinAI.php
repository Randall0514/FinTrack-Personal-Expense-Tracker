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
  <link rel="icon" type="image/png" href="../../logo.png" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
  <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
  <link rel="stylesheet" href="../assets/fonts/feather.css" />
  <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
  <link rel="stylesheet" href="../assets/fonts/material.css" />
  <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
  <link rel="stylesheet" href="style/FinAI.css">

  
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