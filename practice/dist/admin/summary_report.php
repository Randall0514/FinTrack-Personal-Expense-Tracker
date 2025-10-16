<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

// Database connection
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

/**
 * generateSummaryReport
 * Returns totals and totals-by-category between optional dates for the current user only.
 */
function generateSummaryReport($conn, $user_id, $startDate = null, $endDate = null) {
    // ✅ Always filter by user_id AND exclude archived expenses
    $where = "WHERE user_id = " . intval($user_id) . " AND archived = 0";
    
    if ($startDate) {
        $start = $conn->real_escape_string($startDate);
        $where .= " AND date >= '{$start}'";
    }
    if ($endDate) {
        $end = $conn->real_escape_string($endDate);
        $where .= " AND date <= '{$end}'";
    }

    // Total amount & count
    $totSql = "SELECT IFNULL(SUM(amount),0) AS total_amount, IFNULL(COUNT(*),0) AS total_count FROM expenses {$where}";
    $totRes = $conn->query($totSql);
    $totRow = $totRes ? $totRes->fetch_assoc() : ['total_amount' => 0, 'total_count' => 0];

    // Totals by category - ordered by total DESC for accurate ranking
    $catSql = "SELECT category, IFNULL(SUM(amount),0) AS total, COUNT(*) AS count 
               FROM expenses {$where} 
               GROUP BY category 
               ORDER BY total DESC";
    $catRes = $conn->query($catSql);
    $byCategory = [];
    if ($catRes && $catRes->num_rows > 0) {
        while ($r = $catRes->fetch_assoc()) {
            $byCategory[$r['category']] = [
                'total' => (float)$r['total'],
                'count' => (int)$r['count']
            ];
        }
    }

    // Daily average - only calculate if there are expenses
    $dailyAverage = 0;
    if ($totRow['total_count'] > 0) {
        $daysSql = "SELECT DATEDIFF(MAX(date), MIN(date)) + 1 AS days FROM expenses {$where}";
        $daysRes = $conn->query($daysSql);
        $daysRow = $daysRes ? $daysRes->fetch_assoc() : ['days' => 1];
        $days = max(1, (int)$daysRow['days']);
        $dailyAverage = $totRow['total_amount'] / $days;
    }

    // Payment method breakdown
    $pmSql = "SELECT payment_method, IFNULL(SUM(amount),0) AS total, COUNT(*) AS count 
              FROM expenses {$where} 
              GROUP BY payment_method 
              ORDER BY total DESC";
    $pmRes = $conn->query($pmSql);
    $byPaymentMethod = [];
    if ($pmRes && $pmRes->num_rows > 0) {
        while ($r = $pmRes->fetch_assoc()) {
            $byPaymentMethod[$r['payment_method']] = [
                'total' => (float)$r['total'],
                'count' => (int)$r['count']
            ];
        }
    }

    // All expenses for detailed table
    $expSql = "SELECT * FROM expenses {$where} ORDER BY date DESC";
    $expRes = $conn->query($expSql);
    $expenses = [];
    if ($expRes && $expRes->num_rows > 0) {
        while ($r = $expRes->fetch_assoc()) {
            $expenses[] = $r;
        }
    }

    return [
        'total_amount' => (float)$totRow['total_amount'],
        'total_count'  => (int)$totRow['total_count'],
        'daily_average' => $dailyAverage,
        'by_category'  => $byCategory,
        'by_payment_method' => $byPaymentMethod,
        'expenses' => $expenses
    ];
}

// Handle form submission
$startDate = null;
$endDate = null;
$filtered = false;

if (isset($_POST['submit'])) {
    $startDate = !empty($_POST['startDate']) ? $_POST['startDate'] : null;
    $endDate = !empty($_POST['endDate']) ? $_POST['endDate'] : null;
    $filtered = true;
}

// ✅ Pass user_id to the report function
$report = generateSummaryReport($conn, $user_id, $startDate, $endDate);

// Calculate percentages for categories
$total = $report['total_amount'];
foreach ($report['by_category'] as $cat => &$data) {
    $data['percentage'] = $total > 0 ? ($data['total'] / $total) * 100 : 0;
}
unset($data); // Break reference
?>
<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">
<head>
    <title>FinTrack - Summary Report</title>
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
            border-bottom: 2px solid rgba(255, 255, 255, 0.2) !important;
            padding: 20px !important;
            border-radius: 15px 15px 0 0 !important;
        }

        .card-header h5 {
            color: white !important;
            font-weight: 700;
            font-size: 1.2rem;
            margin: 0;
        }

        .card-body { padding: 25px !important; }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
            transition: transform 0.3s;
        }

        .stat-card:hover { transform: translateY(-5px); }

        .stat-card h6 {
            font-size: 0.9rem;
            opacity: 0.9;
            margin-bottom: 10px;
        }

        .stat-card h3 {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 0;
        }

        .stat-card p {
            font-size: 0.85rem;
            opacity: 0.8;
            margin-top: 8px;
        }

        .form-control, .form-select {
            padding: 10px 15px;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            outline: none;
            transition: all 0.3s;
        }

        .form-control:focus, .form-select:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .btn {
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            transition: all 0.3s;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }

        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }

        .table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
        }

        .table thead th {
            background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
            color: white;
            font-weight: 700;
            padding: 15px;
            text-align: center;
            font-size: 0.9rem;
            text-transform: uppercase;
        }

        .table thead th:first-child { border-radius: 10px 0 0 0; }
        .table thead th:last-child { border-radius: 0 10px 0 0; }

        .table tbody tr {
            background: white;
            transition: all 0.3s;
            border-bottom: 2px solid #f3f4f6;
        }

        .table tbody tr:hover {
            background: #f9fafb;
            transform: scale(1.01);
        }

        .table tbody td {
            padding: 15px;
            text-align: center;
            color: #374151;
            font-weight: 500;
        }

        .category-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .progress-wrapper {
            background: #e5e7eb;
            height: 10px;
            border-radius: 10px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            transition: width 0.5s ease;
        }

        .chart-container {
            position: relative;
            height: 300px;
            margin-top: 20px;
        }

        .filter-badge {
            display: inline-block;
            padding: 8px 16px;
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            border-radius: 8px;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .empty-state i {
            font-size: 3rem;
            color: #ccc;
            margin-bottom: 15px;
        }

        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .card { animation: fadeInUp 0.5s ease-out; }
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
            <!-- Breadcrumb -->
            <div class="page-header">
                <div class="page-block">
                    <div class="page-header-title">
                        <h5 class="mb-0 font-medium">📊 Summary Report</h5>
                        <div class="user-info">
                            Viewing report for <strong><?= htmlspecialchars($user['fullname']); ?></strong>
                        </div>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin/dashboard.php">Home</a></li>
                        <li class="breadcrumb-item" aria-current="page">Summary Report</li>
                        <li class="breadcrumb-item"><a href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>

            <?php if ($filtered): ?>
                <div class="filter-badge">
                    📅 Filtered: <?php echo $startDate ? date('M d, Y', strtotime($startDate)) : 'All'; ?> 
                    to <?php echo $endDate ? date('M d, Y', strtotime($endDate)) : 'All'; ?>
                </div>
            <?php endif; ?>

            <!-- Summary Statistics -->
            <div class="grid grid-cols-12 gap-4 mb-4">
                <div class="col-span-12 md:col-span-6 lg:col-span-3">
                    <div class="stat-card">
                        <h6>💰 Total Expenses</h6>
                        <h3><?php echo $report['total_count']; ?></h3>
                        <p>Total Records</p>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-6 lg:col-span-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                        <h6>💵 Total Amount</h6>
                        <h3>₱ <?php echo number_format($report['total_amount'], 2); ?></h3>
                        <p>All Expenses</p>
                    </div>
                </div>
                
                <div class="col-span-12 md:col-span-6 lg:col-span-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #10b981 0%, #059669 100%);">
                        <h6>📈 Daily Average</h6>
                        <h3>₱ <?php echo number_format($report['daily_average'], 2); ?></h3>
                        <p>Per Day</p>
                    </div>
                </div>
                <div class="col-span-12 md:col-span-6 lg:col-span-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                        <h6>🏆 Top Category</h6>
                        <h3>
                            <?php 
                            if (!empty($report['by_category'])) {
                                $keys = array_keys($report['by_category']);
                                echo htmlspecialchars(substr($keys[0], 0, 15));
                            } else {
                                echo 'N/A';
                            }
                            ?>
                        </h3>
                        <p>
                            ₱ <?php 
                            if (!empty($report['by_category'])) {
                                $keys = array_keys($report['by_category']);
                                echo number_format($report['by_category'][$keys[0]]['total'], 2);
                            } else {
                                echo '0.00';
                            }
                            ?>
                        </p>
                    </div>
                </div>
            </div>

            <!-- Filter Form -->
            <div class="grid grid-cols-12 gap-x-6">
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>🔍 Filter Report</h5>
                        </div>
                        <div class="card-body">
                            <form method="POST" action="">
                                <div class="grid grid-cols-12 gap-4">
                                    <div class="col-span-12 md:col-span-5">
                                        <label class="form-label">Start Date</label>
                                        <input type="date" class="form-control" name="startDate" value="<?php echo $startDate ?? ''; ?>" />
                                    </div>
                                    <div class="col-span-12 md:col-span-5">
                                        <label class="form-label">End Date</label>
                                        <input type="date" class="form-control" name="endDate" value="<?php echo $endDate ?? ''; ?>" />
                                    </div>
                                    <div class="col-span-12 md:col-span-2" style="display: flex; align-items: flex-end; gap: 10px; flex-wrap: wrap;">
                                        <button type="submit" name="submit" class="btn btn-primary">
                                            <i class="feather icon-search"></i> Generate
                                        </button>
                                        <a href="summary_report.php" class="btn btn-warning">
                                            <i class="feather icon-x"></i> Reset
                                        </a>
                                    </div>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Charts Section -->
            <div class="grid grid-cols-12 gap-x-6">
                <div class="col-span-12 md:col-span-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>📊 Spending by Category</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="categoryChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-span-12 md:col-span-6">
                    <div class="card">
                        <div class="card-header">
                            <h5>💳 Payment Methods</h5>
                        </div>
                        <div class="card-body">
                            <div class="chart-container">
                                <canvas id="paymentChart"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Category Breakdown Table -->
            <div class="grid grid-cols-12 gap-x-6">
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>📋 Category Breakdown</h5>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x: auto;">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Category</th>
                                            <th>Total Amount</th>
                                            <th>Count</th>
                                            <th>Percentage</th>
                                            <th>Progress</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($report['by_category'])): ?>
                                            <?php foreach ($report['by_category'] as $cat => $data): ?>
                                                <tr>
                                                    <td><span class="category-badge"><?php echo htmlspecialchars($cat); ?></span></td>
                                                    <td style="font-weight: 700; color: #764ba2;">₱ <?php echo number_format($data['total'], 2); ?></td>
                                                    <td><?php echo $data['count']; ?></td>
                                                    <td><?php echo number_format($data['percentage'], 1); ?>%</td>
                                                    <td>
                                                        <div class="progress-wrapper">
                                                            <div class="progress-fill" style="width: <?php echo $data['percentage']; ?>%"></div>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="5" class="empty-state">
                                                <i class="feather icon-inbox"></i>
                                                <p>No category data available</p>
                                            </td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Detailed Expenses Table -->
            <div class="grid grid-cols-12 gap-x-6">
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 10px;">
                            <h5>📝 Detailed Expenses</h5>
                            <button class="btn btn-success" onclick="exportToExcel()">
                                <i class="feather icon-download"></i> Export to Excel
                            </button>
                        </div>
                        <div class="card-body">
                            <div style="overflow-x: auto;">
                                <table class="table" id="expensesTable">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Date</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                            <th>Description</th>
                                            <th>Payment Method</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (!empty($report['expenses'])): ?>
                                            <?php foreach ($report['expenses'] as $index => $expense): ?>
                                                <tr>
                                                    <td><?php echo $index + 1; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                                    <td><span class="category-badge"><?php echo htmlspecialchars($expense['category']); ?></span></td>
                                                    <td style="font-weight: 700; color: #764ba2;">₱ <?php echo number_format($expense['amount'], 2); ?></td>
                                                    <td style="text-align: left;"><?php echo htmlspecialchars($expense['description']); ?></td>
                                                    <td><?php echo htmlspecialchars($expense['payment_method']); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="6" class="empty-state">
                                                <i class="feather icon-inbox"></i>
                                                <p>No expenses found for this user</p>
                                            </td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
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
        const categoryData = <?php echo json_encode($report['by_category']); ?>;
        const categoryLabels = Object.keys(categoryData);
        const categoryValues = categoryLabels.map(key => parseFloat(categoryData[key].total));

        if (categoryLabels.length > 0 && categoryValues.some(v => v > 0)) {
            const ctxCategory = document.getElementById('categoryChart').getContext('2d');
            new Chart(ctxCategory, {
                type: 'doughnut',
                data: {
                    labels: categoryLabels,
                    datasets: [{
                        data: categoryValues,
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(59, 130, 246, 0.8)',
                            'rgba(16, 185, 129, 0.8)',
                            'rgba(245, 158, 11, 0.8)',
                            'rgba(239, 68, 68, 0.8)',
                            'rgba(139, 92, 246, 0.8)',
                            'rgba(236, 72, 153, 0.8)',
                            'rgba(14, 165, 233, 0.8)'
                        ],
                        borderWidth: 2,
                        borderColor: '#fff'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            position: 'right',
                            labels: {
                                padding: 15,
                                font: { size: 12, weight: '600' },
                                color: '#374151'
                            }
                        },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return label + ': ₱' + value.toLocaleString('en-US', {minimumFractionDigits: 2}) + ' (' + percentage + '%)';
                                }
                            }
                        }
                    }
                }
            });
        } else {
            document.getElementById('categoryChart').parentElement.innerHTML = 
                '<div class="empty-state"><i class="feather icon-pie-chart"></i><p>No category data available</p></div>';
        }

        // Payment Method Chart
        const paymentData = <?php echo json_encode($report['by_payment_method']); ?>;
        const paymentLabels = Object.keys(paymentData);
        const paymentValues = paymentLabels.map(key => parseFloat(paymentData[key].total));

        if (paymentLabels.length > 0 && paymentValues.some(v => v > 0)) {
            const ctxPayment = document.getElementById('paymentChart').getContext('2d');
            new Chart(ctxPayment, {
                type: 'bar',
                data: {
                    labels: paymentLabels,
                    datasets: [{
                        label: 'Amount (₱)',
                        data: paymentValues,
                        backgroundColor: 'rgba(102, 126, 234, 0.8)',
                        borderColor: '#667eea',
                        borderWidth: 2,
                        borderRadius: 8
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: 'rgba(0, 0, 0, 0.8)',
                            padding: 12,
                            titleFont: { size: 14, weight: '600' },
                            bodyFont: { size: 13 },
                            callbacks: {
                                label: function(context) {
                                    return 'Amount: ₱ ' + context.parsed.y.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2});
                                }
                            }
                        }
                    },
                    scales: {
                        y: { 
                            beginAtZero: true,
                            ticks: {
                                callback: function(value) {
                                    return '₱ ' + value.toLocaleString('en-US');
                                },
                                color: '#374151',
                                font: { weight: '600' }
                            },
                            grid: {
                                color: 'rgba(0, 0, 0, 0.05)'
                            }
                        },
                        x: {
                            ticks: {
                                color: '#374151',
                                font: { weight: '600' }
                            },
                            grid: {
                                display: false
                            }
                        }
                    }
                }
            });
        } else {
            document.getElementById('paymentChart').parentElement.innerHTML = 
                '<div class="empty-state"><i class="feather icon-credit-card"></i><p>No payment method data available</p></div>';
        }

        // Export to Excel function
        function exportToExcel() {
            const table = document.getElementById('expensesTable');
            let csv = [];
            const rows = table.querySelectorAll('tr');
            
            for (let i = 0; i < rows.length; i++) {
                const row = [], cols = rows[i].querySelectorAll('td, th');
                
                for (let j = 0; j < cols.length; j++) {
                    let text = cols[j].innerText.replace(/"/g, '""');
                    row.push('"' + text + '"');
                }
                
                csv.push(row.join(','));
            }
            
            const csvContent = csv.join('\n');
            const blob = new Blob([csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'expenses_report_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        // Initialize layout
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