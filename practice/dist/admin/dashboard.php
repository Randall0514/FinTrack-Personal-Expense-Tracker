<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

// Database connection - adjust this path based on your actual file location
include '../database/config/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

// ✅ Check JWT via cookie
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

// ✅ Get user ID from JWT
$user_id = $user['id'];

// ✅ Try to fetch user's budgets, fall back to defaults if columns don't exist
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
    // Columns don't exist yet, use defaults
    $dailyBudget = 500;
    $weeklyBudget = 3000;
    $monthlyBudget = 10000;
}

// ✅ Fetch only THIS user's expenses
$expenses = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? ORDER BY date DESC");
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

// Calculate statistics
$dailySpending = 0;
$weeklySpending = 0;
$monthlySpending = 0;
$today = date('Y-m-d');
$currentMonth = date('Y-m');

// Calculate week range (Monday to Sunday)
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

// Calculate percentages (based on budget)
$dailyPercentage = min(100, ($dailyBudget > 0 ? ($dailySpending / $dailyBudget) * 100 : 0));
$weeklyPercentage = min(100, ($weeklyBudget > 0 ? ($weeklySpending / $weeklyBudget) * 100 : 0));
$monthlyPercentage = min(100, ($monthlyBudget > 0 ? ($monthlySpending / $monthlyBudget) * 100 : 0));

// Category breakdown for chart
$categoryTotals = [];
foreach ($expenses as $expense) {
    $cat = $expense['category'];
    $amt = floatval($expense['amount']);
    if (!isset($categoryTotals[$cat])) {
        $categoryTotals[$cat] = 0;
    }
    $categoryTotals[$cat] += $amt;
}

// Recent expenses (limit to 5)
$recentExpenses = array_slice($expenses, 0, 5);

// Budget alerts
$alerts = [];
if ($dailyPercentage >= 90) {
    $alerts[] = ['type' => 'danger', 'message' => 'Daily budget almost exceeded!'];
}
if ($weeklyPercentage >= 90) {
    $alerts[] = ['type' => 'danger', 'message' => 'Weekly budget almost exceeded!'];
}
if ($monthlyPercentage >= 80) {
    $alerts[] = ['type' => 'warning', 'message' => 'Monthly budget at 80%!'];
}
if ($dailySpending > $dailyBudget) {
    $alerts[] = ['type' => 'danger', 'message' => 'Daily budget exceeded by ₱' . number_format($dailySpending - $dailyBudget, 2)];
}
if ($weeklySpending > $weeklyBudget) {
    $alerts[] = ['type' => 'danger', 'message' => 'Weekly budget exceeded by ₱' . number_format($weeklySpending - $weeklyBudget, 2)];
}

// ✅ Quick Add Expense Handler with user_id
if (isset($_POST['quick_add'])) {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $payment_method = $_POST['payment_method'];

    // ✅ Include user_id in the INSERT
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, date, description, payment_method) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("isdsss", $user_id, $category, $amount, $date, $description, $payment_method);
    
    if ($stmt->execute()) {
        header("Location: dashboard.php?success=1");
        exit;
    }
    $stmt->close();
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

    .card-body h3 {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
      background-clip: text;
      font-weight: 700;
      font-size: 2rem;
    }

    /* Alert Styles */
    .alert {
      padding: 15px 20px;
      border-radius: 10px;
      margin-bottom: 15px;
      display: flex;
      align-items: center;
      gap: 10px;
      animation: slideInRight 0.5s ease-out;
    }

    .alert-danger {
      background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
      color: white;
    }

    .alert-warning {
      background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
      color: white;
    }

    .alert-success {
      background: linear-gradient(135deg, #10b981 0%, #059669 100%);
      color: white;
    }

    /* Budget Progress */
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

    .budget-card h3 { color: white !important; }

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

    /* Filter Section */
    .filter-section {
      background: rgba(255, 255, 255, 0.1);
      padding: 15px;
      border-radius: 10px;
      margin-bottom: 20px;
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .filter-section select,
    .filter-section input {
      padding: 8px 12px;
      border-radius: 8px;
      border: 2px solid rgba(255, 255, 255, 0.3);
      background: white;
      outline: none;
    }

    .filter-btn {
      background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
      color: white;
      padding: 8px 16px;
      border: none;
      border-radius: 8px;
      cursor: pointer;
      font-weight: 600;
      transition: transform 0.3s;
    }

    .filter-btn:hover { transform: scale(1.05); }

    /* Quick Add Form */
    .quick-add-form {
      background: white;
      padding: 20px;
      border-radius: 15px;
      box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
    }

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

    /* Chart Container */
    .chart-container {
      position: relative;
      height: 300px;
      margin-top: 20px;
    }

    /* Grid Layout */
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
    }

    @keyframes slideInRight {
      from {
        opacity: 0;
        transform: translateX(50px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(30px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
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

  <?php include '../includes/sidebar.php'; ?>
  <?php include '../includes/header.php'; ?>

  <div class="pc-container">
    <div class="pc-content">
      <!-- Breadcrumb with User Info -->
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
            <li class="breadcrumb-item"><a href="logout.php" style="color: rgba(255, 255, 255, 0.95) !important;">Logout</a></li>
          </ul>
        </div>
      </div>

      <!-- Alerts/Notifications -->
      <?php if (!empty($alerts)): ?>
        <div style="animation: fadeInUp 0.5s ease-out;">
          <?php foreach ($alerts as $alert): ?>
            <div class="alert alert-<?php echo $alert['type']; ?>">
              <i class="feather icon-alert-circle" style="font-size: 1.5rem;"></i>
              <strong><?php echo $alert['message']; ?></strong>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success">
          <i class="feather icon-check-circle" style="font-size: 1.5rem;"></i>
          <strong>Expense added successfully!</strong>
        </div>
      <?php endif; ?>

      <div class="dashboard-grid">
        <!-- Budget Tracking Cards -->
        <div class="col-span-4">
          <div class="budget-card">
            <h6>💰 Daily Budget</h6>
            <h3>₱ <?php echo number_format($dailySpending, 2); ?> / ₱ <?php echo number_format($dailyBudget, 2); ?></h3>
            <div class="progress-bar-wrapper">
              <div class="progress-bar-fill" style="width: <?php echo $dailyPercentage; ?>%"></div>
            </div>
            <p style="margin-top: 10px; font-size: 0.9rem;">
              <?php echo number_format($dailyPercentage, 1); ?>% used
              • ₱ <?php echo number_format(max(0, $dailyBudget - $dailySpending), 2); ?> remaining
            </p>
          </div>
        </div>

        <div class="col-span-4">
          <div class="budget-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
            <h6>📅 Weekly Budget</h6>
            <h3>₱ <?php echo number_format($weeklySpending, 2); ?> / ₱ <?php echo number_format($weeklyBudget, 2); ?></h3>
            <div class="progress-bar-wrapper">
              <div class="progress-bar-fill" style="width: <?php echo $weeklyPercentage; ?>%"></div>
            </div>
            <p style="margin-top: 10px; font-size: 0.9rem;">
              <?php echo number_format($weeklyPercentage, 1); ?>% used
              • ₱ <?php echo number_format(max(0, $weeklyBudget - $weeklySpending), 2); ?> remaining
            </p>
          </div>
        </div>

        <div class="col-span-4">
          <div class="budget-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
            <h6>📆 Monthly Budget</h6>
            <h3>₱ <?php echo number_format($monthlySpending, 2); ?> / ₱ <?php echo number_format($monthlyBudget, 2); ?></h3>
            <div class="progress-bar-wrapper">
              <div class="progress-bar-fill" style="width: <?php echo $monthlyPercentage; ?>%"></div>
            </div>
            <p style="margin-top: 10px; font-size: 0.9rem;">
              <?php echo number_format($monthlyPercentage, 1); ?>% used
              • ₱ <?php echo number_format(max(0, $monthlyBudget - $monthlySpending), 2); ?> remaining
            </p>
          </div>
        </div>

        <!-- Filtering Section -->
        <div class="col-span-12">
          <div class="filter-section">
            <select id="categoryFilter">
              <option value="">All Categories</option>
              <?php foreach ($categories as $cat): ?>
                <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
              <?php endforeach; ?>
            </select>
            <input type="date" id="dateFromFilter" placeholder="From Date">
            <input type="date" id="dateToFilter" placeholder="To Date">
            <button class="filter-btn" onclick="applyFilters()">
              <i class="feather icon-filter"></i> Apply Filters
            </button>
            <button class="filter-btn" onclick="resetFilters()">
              <i class="feather icon-x"></i> Reset
            </button>
          </div>
        </div>

        <!-- Data Visualization - Category Breakdown Chart -->
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

    // Filter functionality
    function applyFilters() {
      const category = document.getElementById('categoryFilter').value;
      const dateFrom = document.getElementById('dateFromFilter').value;
      const dateTo = document.getElementById('dateToFilter').value;
      
      let params = [];
      if (category) params.push(`category=${category}`);
      if (dateFrom) params.push(`from=${dateFrom}`);
      if (dateTo) params.push(`to=${dateTo}`);
      
      window.location.href = 'manage_expenses.php' + (params.length ? '?' + params.join('&') : '');
    }

    function resetFilters() {
      document.getElementById('categoryFilter').value = '';
      document.getElementById('dateFromFilter').value = '';
      document.getElementById('dateToFilter').value = '';
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