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

// Fetch user's budgets
$dailyBudget = 500;
$weeklyBudget = 3000;
$monthlyBudget = 10000;

try {
    $budget_query = $conn->prepare("SELECT daily_budget, weekly_budget, monthly_budget FROM users WHERE id = ?");
    if ($budget_query) {
        $budget_query->bind_param("i", $user_id);
        $budget_query->execute();
        $budget_result = $budget_query->get_result();
        
        if ($budget_result->num_rows > 0) {
            $budget_data = $budget_result->fetch_assoc();
            $dailyBudget = $budget_data['daily_budget'] ?? 500;
            $weeklyBudget = $budget_data['weekly_budget'] ?? 3000;
            $monthlyBudget = $budget_data['monthly_budget'] ?? 10000;
        }
        $budget_query->close();
    }
} catch (Exception $e) {
    $dailyBudget = 500;
    $weeklyBudget = 3000;
    $monthlyBudget = 10000;
}

// Fetch expenses
$expenses = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND archived = 0 ORDER BY date DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $expenses[] = $row;
        }
    }
    $stmt->close();
}

// Calculate spending
$dailySpending = 0;
$weeklySpending = 0;
$monthlySpending = 0;
$today = date('Y-m-d');
$currentMonth = date('Y-m');
$weekStart = date('Y-m-d', strtotime('monday this week'));
$weekEnd = date('Y-m-d', strtotime('sunday this week'));

foreach ($expenses as $expense) {
    $expenseDate = $expense['date'];
    $amount = floatval($expense['amount']);
    
    if ($expenseDate == $today) {
        $dailySpending += $amount;
    }
    if ($expenseDate >= $weekStart && $expenseDate <= $weekEnd) {
        $weeklySpending += $amount;
    }
    if (substr($expenseDate, 0, 7) == $currentMonth) {
        $monthlySpending += $amount;
    }
}

// Calculate percentages
$dailyPercentage = min(100, ($dailyBudget > 0 ? ($dailySpending / $dailyBudget) * 100 : 0));
$weeklyPercentage = min(100, ($weeklyBudget > 0 ? ($weeklySpending / $weeklyBudget) * 100 : 0));
$monthlyPercentage = min(100, ($monthlyBudget > 0 ? ($monthlySpending / $monthlyBudget) * 100 : 0));

// Category breakdown
$categoryTotals = [];
foreach ($expenses as $expense) {
    $cat = $expense['category'];
    $amt = floatval($expense['amount']);
    if (!isset($categoryTotals[$cat])) {
        $categoryTotals[$cat] = 0;
    }
    $categoryTotals[$cat] += $amt;
}

// Recent expenses
$recentExpenses = array_slice($expenses, 0, 5);

// Enhanced alerts with multiple thresholds
$alerts = [];

// Daily alerts
if ($dailySpending > $dailyBudget) {
    $alerts[] = [
        'type' => 'danger', 
        'message' => '🚨 Daily budget exceeded by ₱' . number_format($dailySpending - $dailyBudget, 2) . '!',
        'icon' => 'alert-circle'
    ];
} elseif ($dailyPercentage >= 80) {
    $alerts[] = [
        'type' => 'warning', 
        'message' => '⚠️ Daily budget warning: ' . number_format($dailyPercentage, 1) . '% used (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
        'icon' => 'alert-triangle'
    ];
} elseif ($dailyPercentage >= 60) {
    $alerts[] = [
        'type' => 'info', 
        'message' => 'ℹ️ Daily budget update: ' . number_format($dailyPercentage, 1) . '% used (₱' . number_format($dailySpending, 2) . ' of ₱' . number_format($dailyBudget, 2) . ')',
        'icon' => 'info'
    ];
}

// Weekly alerts
if ($weeklySpending > $weeklyBudget) {
    $alerts[] = [
        'type' => 'danger', 
        'message' => '🚨 Weekly budget exceeded by ₱' . number_format($weeklySpending - $weeklyBudget, 2) . '!',
        'icon' => 'alert-circle'
    ];
} elseif ($weeklyPercentage >= 80) {
    $alerts[] = [
        'type' => 'warning', 
        'message' => '⚠️ Weekly budget warning: ' . number_format($weeklyPercentage, 1) . '% used (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
        'icon' => 'alert-triangle'
    ];
} elseif ($weeklyPercentage >= 60) {
    $alerts[] = [
        'type' => 'info', 
        'message' => 'ℹ️ Weekly budget update: ' . number_format($weeklyPercentage, 1) . '% used (₱' . number_format($weeklySpending, 2) . ' of ₱' . number_format($weeklyBudget, 2) . ')',
        'icon' => 'info'
    ];
}

// Monthly alerts
if ($monthlySpending > $monthlyBudget) {
    $alerts[] = [
        'type' => 'danger', 
        'message' => '🚨 Monthly budget exceeded by ₱' . number_format($monthlySpending - $monthlyBudget, 2) . '!',
        'icon' => 'alert-circle'
    ];
} elseif ($monthlyPercentage >= 80) {
    $alerts[] = [
        'type' => 'warning', 
        'message' => '⚠️ Monthly budget warning: ' . number_format($monthlyPercentage, 1) . '% used (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
        'icon' => 'alert-triangle'
    ];
} elseif ($monthlyPercentage >= 60) {
    $alerts[] = [
        'type' => 'info', 
        'message' => 'ℹ️ Monthly budget update: ' . number_format($monthlyPercentage, 1) . '% used (₱' . number_format($monthlySpending, 2) . ' of ₱' . number_format($monthlyBudget, 2) . ')',
        'icon' => 'info'
    ];
}

// Quick add expense
if (isset($_POST['quick_add'])) {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $payment_method = $_POST['payment_method'];

    $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, date, description, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdsss", $user_id, $category, $amount, $date, $description, $payment_method);
    
    if ($stmt->execute()) {
        header("Location: dashboard.php?success=1");
        exit;
    }
    $stmt->close();
}

if (isset($_GET['success'])) {
    $alerts[] = [
        'type' => 'success',
        'message' => '✅ Expense added successfully!',
        'icon' => 'check-circle'
    ];
}

$categories = [
    'Food & Dining',
    'Transportation',
    'Shopping',
    'Bills & Utilities',
    'Entertainment',
    'Health & Fitness',
    'Education',
    'Travel',
    'Others'
];
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
<head>
  <title>FinTrack - Dashboard</title>
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
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  
  <style>
    body {
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
      min-height: 100vh;
      font-weight: 600;
    }

    .pc-container { background: transparent !important; }
    .pc-content { padding: 20px; }

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

    .user-info {
      color: rgba(255, 255, 255, 0.9);
      font-size: 0.95rem;
      margin-top: 8px;
    }

    .breadcrumb { background: transparent !important; }
    .breadcrumb-item, .breadcrumb-item a {
      color: rgba(255, 255, 255, 0.9) !important;
      font-weight: 600;
    }

    .card {
      background: rgba(255, 255, 255, 0.95) !important;
      backdrop-filter: blur(10px);
      border-radius: 15px !important;
      border: none !important;
      box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
      transition: transform 0.3s, box-shadow 0.3s;
      margin-bottom: 20px;
    }

    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 15px 40px rgba(0, 0, 0, 0.3) !important;
    }

    .card-header {
      background: transparent !important;
      border-bottom: 2px solid #f0f0f0 !important;
      padding: 20px !important;
    }

    .card-header h5 {
      color: #667eea !important;
      font-weight: 700;
      font-size: 1.2rem;
    }

    .card-body { padding: 25px !important; }

    /* Alert Container - Fixed Position */
    .alert-container {
      position: fixed;
      top: 80px;
      right: 20px;
      z-index: 9999;
      max-width: 450px;
      width: 100%;
    }

    .alert {
      padding: 18px 24px;
      border-radius: 12px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 15px;
      animation: slideInRight 0.5s ease-out;
      box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
      backdrop-filter: blur(10px);
      border: 2px solid transparent;
      position: relative;
      overflow: hidden;
    }

    .alert i {
      font-size: 1.8rem;
      flex-shrink: 0;
    }

    .alert-message {
      flex: 1;
      font-weight: 600;
      font-size: 0.95rem;
      line-height: 1.5;
    }

    .alert-close {
      background: none;
      border: none;
      color: inherit;
      cursor: pointer;
      padding: 4px;
      opacity: 0.7;
      transition: opacity 0.3s;
      flex-shrink: 0;
    }

    .alert-close:hover { opacity: 1; }
    .alert-close i { font-size: 1.2rem; }

    .alert-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
      border-color: #fca5a5;
    }

    .alert-danger::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: linear-gradient(90deg, #fca5a5, #ef4444, #dc2626);
      animation: pulse 2s ease-in-out infinite;
    }

    .alert-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
      border-color: #fcd34d;
    }

    .alert-warning::before {
      content: '';
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 3px;
      background: linear-gradient(90deg, #fcd34d, #f59e0b, #d97706);
      animation: pulse 2s ease-in-out infinite;
    }

    .alert-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
      border-color: #6ee7b7;
    }

    .alert-info {
      background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
      color: white;
      border-color: #93c5fd;
    }

    .alert-progress {
      position: absolute;
      bottom: 0;
      left: 0;
      height: 3px;
      background: rgba(255, 255, 255, 0.5);
      width: 100%;
      transform-origin: left;
      animation: shrink 5s linear forwards;
    }

    @keyframes slideInRight {
      from { opacity: 0; transform: translateX(100px); }
      to { opacity: 1; transform: translateX(0); }
    }

    @keyframes pulse {
      0%, 100% { opacity: 1; }
      50% { opacity: 0.5; }
    }

    @keyframes shrink {
      from { transform: scaleX(1); }
      to { transform: scaleX(0); }
    }

    @keyframes fadeOut {
      from { opacity: 1; transform: translateX(0); }
      to { opacity: 0; transform: translateX(100px); }
    }

    /* Budget Cards */
    .budget-card {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 20px;
      border-radius: 15px;
      margin-bottom: 20px;
    }

    .budget-card h6 {
      color: rgba(255, 255, 255, 0.9);
      font-size: 0.9rem;
      margin-bottom: 10px;
    }

    .budget-amounts {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 12px;
      gap: 15px;
    }

    .budget-amount-item {
      flex: 1;
      background: rgba(255, 255, 255, 0.15);
      padding: 12px;
      border-radius: 10px;
      backdrop-filter: blur(5px);
      border: 1px solid rgba(255, 255, 255, 0.2);
    }

    .budget-label {
      display: block;
      font-size: 0.75rem;
      color: rgba(255, 255, 255, 0.8);
      margin-bottom: 5px;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      font-weight: 600;
    }

    .budget-value {
      display: block;
      font-size: 1.1rem;
      color: white;
      font-weight: 700;
    }
    

    .progress-bar-wrapper {
      background: rgba(255, 255, 255, 0.2);
      height: 10px;
      border-radius: 10px;
      overflow: hidden;
      margin-top: 10px;
    }

    .progress-bar-fill {
      height: 100%;
      background: white;
      border-radius: 10px;
      transition: width 0.5s ease;
    }

    .budget-summary {
      margin-top: 12px;
      padding-top: 12px;
      border-top: 1px solid rgba(255, 255, 255, 0.2);
      display: flex;
      justify-content: space-between;
      align-items: center;
    }

    .budget-summary-text {
      font-size: 0.85rem;
      color: rgba(255, 255, 255, 0.95);
    }

    .budget-summary-text strong {
      font-weight: 700;
      color: white;
    }

    /* Quick Add Form */
    .quick-add-form input,
    .quick-add-form select,
    .quick-add-form textarea {
      width: 100%;
      padding: 10px;
      margin-bottom: 10px;
      border: 2px solid #e5e7eb;
      border-radius: 8px;
      outline: none;
    }

    .quick-add-form input:focus,
    .quick-add-form select:focus,
    .quick-add-form textarea:focus {
      border-color: #667eea;
    }

    .quick-add-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 12px 24px;
      border: none;
      border-radius: 8px;
      font-weight: 600;
      cursor: pointer;
      width: 100%;
      transition: transform 0.3s;
    }

    .quick-add-btn:hover { transform: translateY(-2px); }

    /* Activity Feed */
    .activity-item {
      display: flex;
      align-items: center;
      gap: 15px;
      padding: 15px;
      background: white;
      border-radius: 10px;
      margin-bottom: 10px;
      transition: all 0.3s;
    }

    .activity-item:hover {
      transform: translateX(5px);
      box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
    }

    .activity-icon {
      width: 50px;
      height: 50px;
      border-radius: 50%;
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      display: flex;
      align-items: center;
      justify-content: center;
      color: white;
      font-weight: 700;
    }

    .activity-details h6 {
      margin: 0;
      font-weight: 700;
      color: #333;
    }

    .activity-details p {
      margin: 0;
      font-size: 0.85rem;
      color: #666;
    }

    .activity-amount {
      margin-left: auto;
      font-weight: 700;
      font-size: 1.1rem;
      color: #764ba2;
    }

    .chart-container {
      position: relative;
      height: 300px;
      margin-top: 20px;
    }

    .dashboard-grid {
      display: grid;
      grid-template-columns: repeat(12, 1fr);
      gap: 20px;
    }

    .col-span-12 { grid-column: span 12; }
    .col-span-6 { grid-column: span 6; }
    .col-span-4 { grid-column: span 4; }
    .col-span-8 { grid-column: span 8; }

    @media (max-width: 768px) {
      .col-span-6, .col-span-4, .col-span-8 { grid-column: span 12; }
      .budget-amounts { flex-direction: column; gap: 10px; }
      .alert-container { top: 10px; right: 10px; left: 10px; max-width: none; }
      .alert { padding: 15px 18px; }
      .alert-message { font-size: 0.875rem; }
    }
  </style>
</head>

<body>
  <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
    <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
      <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
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
            <h5 class="mb-0 font-medium">📊 FinTrack Dashboard</h5>
            <div class="user-info">
              Welcome back, <strong><?= htmlspecialchars($user['fullname']); ?></strong>! 
              (<?= htmlspecialchars($user['email']); ?>)
            </div>
          </div>
          <ul class="breadcrumb">
            <li class="breadcrumb-item"><a href="dashboard.php">Home</a></li>
            <li class="breadcrumb-item" aria-current="page">Dashboard</li>
            <li class="breadcrumb-item"><a href="logout.php">Logout</a></li>
          </ul>
        </div>
      </div>

      <!-- Alert Container -->
      <div class="alert-container">
        <?php if (!empty($alerts)): ?>
          <?php foreach ($alerts as $index => $alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?>" id="alert-<?php echo $index; ?>">
              <i class="feather icon-<?php echo $alert['icon']; ?>"></i>
              <span class="alert-message"><?php echo $alert['message']; ?></span>
              <button class="alert-close" onclick="dismissAlert(<?php echo $index; ?>)">
                <i class="feather icon-x"></i>
              </button>
              <div class="alert-progress"></div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

      <div class="dashboard-grid">
        <!-- Monthly Budget Card -->
        <div class="col-span-4">
          <div class="budget-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            <h6>📆 Monthly Budget</h6>
            <div class="budget-amounts">
              <div class="budget-amount-item">
                <span class="budget-label">Expenses</span>
                <span class="budget-value">₱ <?php echo number_format($monthlySpending, 2); ?></span>
              </div>
              <div class="budget-amount-item">
                <span class="budget-label">Budget Limit</span>
                <span class="budget-value">₱ <?php echo number_format($monthlyBudget, 2); ?></span>
              </div>
            </div>
            <div class="progress-bar-wrapper">
              <div class="progress-bar-fill" style="width: <?php echo $monthlyPercentage; ?>%"></div>
            </div>
            <div class="budget-summary">
              <span class="budget-summary-text"><strong><?php echo number_format($monthlyPercentage, 1); ?>%</strong> used</span>
              <span class="budget-summary-text"><strong>₱ <?php echo number_format(max(0, $monthlyBudget - $monthlySpending), 2); ?></strong> left</span>
            </div>
          </div>
        </div>

        <!-- Weekly Budget Card -->
        <div class="col-span-4">
          <div class="budget-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <h6>📅 Weekly Budget</h6>
            <div class="budget-amounts">
              <div class="budget-amount-item">
                <span class="budget-label">Expenses</span>
                <span class="budget-value">₱ <?php echo number_format($weeklySpending, 2); ?></span>
              </div>
              <div class="budget-amount-item">
                <span class="budget-label">Budget Limit</span>
                <span class="budget-value">₱ <?php echo number_format($weeklyBudget, 2); ?></span>
              </div>
            </div>
            <div class="progress-bar-wrapper">
              <div class="progress-bar-fill" style="width: <?php echo $weeklyPercentage; ?>%"></div>
            </div>
            <div class="budget-summary">
              <span class="budget-summary-text"><strong><?php echo number_format($weeklyPercentage, 1); ?>%</strong> used</span>
              <span class="budget-summary-text"><strong>₱ <?php echo number_format(max(0, $weeklyBudget - $weeklySpending), 2); ?></strong> left</span>
            </div>
          </div>
        </div>

        <!-- Daily Budget Card -->
        <div class="col-span-4">
          <div class="budget-card">
            <h6>💰 Daily Budget</h6>
            <div class="budget-amounts">
              <div class="budget-amount-item">
                <span class="budget-label">Expenses</span>
                <span class="budget-value">₱ <?php echo number_format($dailySpending, 2); ?></span>
              </div>
              <div class="budget-amount-item">
                <span class="budget-label">Budget Limit</span>
                <span class="budget-value">₱ <?php echo number_format($dailyBudget, 2); ?></span>
              </div>
            </div>
            <div class="progress-bar-wrapper">
              <div class="progress-bar-fill" style="width: <?php echo $dailyPercentage; ?>%"></div>
            </div>
            <div class="budget-summary">
              <span class="budget-summary-text"><strong><?php echo number_format($dailyPercentage, 1); ?>%</strong> used</span>
              <span class="budget-summary-text"><strong>₱ <?php echo number_format(max(0, $dailyBudget - $dailySpending), 2); ?></strong> left</span>
            </div>
          </div>
        </div>

        <!-- Category Chart -->
        <div class="col-span-8">
          <div class="card">
            <div class="card-header">
              <h5>📊 Spending by Category</h5>
            </div>
            <div class="card-body">
              <?php if (!empty($categoryTotals)): ?>
                <div class="chart-container">
                  <canvas id="categoryChart"></canvas>
                </div>
              <?php else: ?>
                <p style="text-align: center; color: #666; padding: 40px 0;">No expenses yet. Start tracking your spending!</p>
              <?php endif; ?>
            </div>
          </div>
        </div>

        <!-- Quick Add Expense -->
        <div class="col-span-4">
          <div class="card">
            <div class="card-header">
              <h5>⚡ Quick Add Expense</h5>
            </div>
            <div class="card-body">
              <form method="POST" class="quick-add-form">
                <select name="category" required>
                  <option value="" disabled selected>Category</option>
                  <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                  <?php endforeach; ?>
                </select>
                <input type="number" name="amount" step="0.01" placeholder="Amount (₱)" required>
                <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
                <textarea name="description" placeholder="Description" rows="2"></textarea>
                <select name="payment_method" required>
                  <option value="" disabled selected>Payment Method</option>
                  <option value="Cash">Cash</option>
                  <option value="Credit Card">Credit Card</option>
                  <option value="Debit Card">Debit Card</option>
                  <option value="E-Wallet">E-Wallet</option>
                </select>
                <button type="submit" name="quick_add" class="quick-add-btn">
                  <i class="feather icon-plus"></i> Add Expense
                </button>
              </form>
            </div>
          </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="col-span-12">
          <div class="card">
            <div class="card-header">
              <h5>📝 Recent Activity Feed</h5>
            </div>
            <div class="card-body">
              <?php if (!empty($recentExpenses)): ?>
                <?php foreach ($recentExpenses as $expense): ?>
                  <div class="activity-item">
                    <div class="activity-icon">
                      <?php echo strtoupper(substr($expense['category'], 0, 1)); ?>
                    </div>
                    <div class="activity-details">
                      <h6><?php echo htmlspecialchars($expense['category']); ?></h6>
                      <p><?php echo htmlspecialchars($expense['description']); ?> • <?php echo date('M d, Y', strtotime($expense['date'])); ?></p>
                    </div>
                    <div class="activity-amount">
                      ₱ <?php echo number_format($expense['amount'], 2); ?>
                    </div>
                  </div>
                <?php endforeach; ?>
              <?php else: ?>
                <p style="text-align: center; color: #666;">No recent expenses. Add your first expense using the form above!</p>
              <?php endif; ?>
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
    // Auto-dismiss alerts after 5 seconds
    document.addEventListener('DOMContentLoaded', function() {
      <?php if (!empty($alerts)): ?>
        <?php foreach ($alerts as $index => $alert): ?>
          setTimeout(function() {
            dismissAlert(<?php echo $index; ?>);
          }, 5000 + (<?php echo $index; ?> * 500));
        <?php endforeach; ?>
      <?php endif; ?>
    });

    function dismissAlert(index) {
      const alert = document.getElementById('alert-' + index);
      if (alert) {
        alert.style.animation = 'fadeOut 0.4s ease-out forwards';
        setTimeout(function() {
          alert.remove();
        }, 400);
      }
    }

    // Category Chart
    <?php if (!empty($categoryTotals)): ?>
    const categoryData = <?php echo json_encode($categoryTotals); ?>;
    const labels = Object.keys(categoryData);
    const data = Object.values(categoryData);

    const ctx = document.getElementById('categoryChart').getContext('2d');
    new Chart(ctx, {
      type: 'doughnut',
      data: {
        labels: labels,
        datasets: [{
          data: data,
          backgroundColor: [
            '#667eea', '#764ba2', '#3b82f6', '#ef4444', '#10b981',
            '#f59e0b', '#8b5cf6', '#ec4899', '#14b8a6'
          ],
          borderWidth: 0
        }]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
          legend: {
            position: 'bottom',
            labels: { padding: 15, font: { size: 12, weight: '600' } }
          }
        }
      }
    });
    <?php endif; ?>

    // Initialize Feather icons
    if (typeof feather !== 'undefined') {
      feather.replace();
    }

    layout_change('false');
    layout_theme_sidebar_change('dark');
    change_box_container('false');
    layout_caption_change('true');
    layout_rtl_change('false');
    preset_change('preset-1');
    main_layout_change('vertical');
  </script>
</body>
</html>