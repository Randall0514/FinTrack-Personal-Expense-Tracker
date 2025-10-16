<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

include "../database/config/db.php";

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

// Check JWT via cookie
if (!isset($_COOKIE['jwt_token'])) {
    echo "<script>alert('You must log in first.'); window.location.href='../login.php';</script>";
    exit;
}

try {
    $jwt = $_COOKIE['jwt_token'];
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user_id = $decoded->data->id;
    $user = (array) $decoded->data;
} catch (Exception $e) {
    echo "<script>alert('Invalid session. Please log in again.'); window.location.href='../login.php';</script>";
    exit;
}

// ✅ ARCHIVE RESTORE
if (isset($_GET['restore'])) {
    $id = $_GET['restore'];

    $stmt = $conn->prepare("UPDATE expenses SET archived = 0 WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute()) {
        header("Location: view_archived.php?restored=1");
        exit;
    }
    $stmt->close();
}

// ❌ PERMANENT DELETE
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];

    $stmt = $conn->prepare("DELETE FROM expenses WHERE id=? AND user_id=? AND archived=1");
    $stmt->bind_param("ii", $id, $user_id);
    if ($stmt->execute()) {
        header("Location: view_archived.php?deleted=1");
        exit;
    }
    $stmt->close();
}

// 🧾 FETCH ARCHIVED EXPENSES
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND archived = 1 ORDER BY date DESC");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$archivedExpenses = [];
while ($row = $result->fetch_assoc()) {
    $archivedExpenses[] = $row;
}

$archivedCount = count($archivedExpenses);
$totalArchivedAmount = array_sum(array_column($archivedExpenses, 'amount'));
?>

<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
    <title>FinTrack - Archived Expenses</title>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="View your archived expenses" />
    <meta name="keywords" content="expense, tracker, finance, budget, archive" />
    <meta name="author" content="FinTrack Team" />

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="../assets/fonts/feather.css" />
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
    <link rel="stylesheet" href="../assets/fonts/material.css" />
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />

    <style>
/* Custom Styles - Purple Theme to Match Dashboard */

body {
    font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif !important;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
    min-height: 100vh;
    font-weight: 600;
}

.pc-container {
    background: transparent !important;
}

.pc-content {
    padding: 20px;
}

/* Page Header */
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

.breadcrumb {
    background: transparent !important;
}

.breadcrumb-item,
.breadcrumb-item a {
    color: rgba(255, 255, 255, 0.9) !important;
    font-weight: 600;
}

.breadcrumb-item a:hover {
    color: white !important;
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

.alert-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.alert-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.alert-info {
    background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
    color: white;
}

/* Cards */
.card {
    background: rgba(255, 255, 255, 0.95) !important;
    backdrop-filter: blur(10px);
    border-radius: 15px !important;
    border: none !important;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2) !important;
    transition: transform 0.3s, box-shadow 0.3s;
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
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
}

.card-header h5 {
    color: white !important;
    font-weight: 700;
    font-size: 1.3rem;
    margin: 0;
}

/* Table */
.table-wrapper {
    overflow-x: auto;
    padding: 25px;
}

.table {
    width: 100%;
    margin-bottom: 1rem;
    border-collapse: separate;
    border-spacing: 0;
}

.table thead th {
    background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
    color: white;
    font-weight: 700;
    padding: 15px;
    text-align: center;
    border: none;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.table thead th:first-child {
    border-radius: 10px 0 0 0;
}

.table thead th:last-child {
    border-radius: 0 10px 0 0;
}

.table tbody tr {
    background: white;
    transition: all 0.3s;
    border-bottom: 2px solid #f3f4f6;
}

.table tbody tr:hover {
    background: #f9fafb;
    transform: scale(1.01);
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
}

.table tbody td {
    padding: 18px 15px;
    text-align: center;
    border: none;
    color: #374151;
    font-weight: 500;
}

.category-badge {
    display: inline-block;
    padding: 6px 14px;
    border-radius: 20px;
    font-size: 0.85rem;
    font-weight: 600;
    background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
    color: white;
}

.amount-cell {
    font-weight: 700;
    font-size: 1.05rem;
    color: #764ba2;
}

/* Buttons */
.btn {
    padding: 8px 16px;
    border-radius: 8px;
    font-weight: 600;
    font-size: 0.85rem;
    border: none;
    cursor: pointer;
    transition: all 0.3s;
    display: inline-flex;
    align-items: center;
    gap: 5px;
    text-decoration: none;
}

.btn-secondary {
    background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
    color: white;
    box-shadow: 0 5px 15px rgba(107, 114, 128, 0.3);
}

.btn-secondary:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 20px rgba(107, 114, 128, 0.4);
    color: white;
}

.btn-success {
    background: linear-gradient(135deg, #10b981 0%, #059669 100%);
    color: white;
}

.btn-success:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(16, 185, 129, 0.4);
}

.btn-danger {
    background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
    color: white;
}

.btn-danger:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(239, 68, 68, 0.4);
}

.btn-sm {
    padding: 6px 12px;
    font-size: 0.8rem;
}

.action-buttons {
    display: flex;
    gap: 8px;
    justify-content: center;
    flex-wrap: wrap;
}

/* Summary Cards */
.summary-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 15px;
    margin-bottom: 25px;
}

.summary-card {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 20px;
    border-radius: 12px;
    color: white;
    text-align: center;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
    transition: transform 0.3s;
}

.summary-card:hover {
    transform: translateY(-5px);
}

.summary-card h6 {
    font-size: 0.9rem;
    margin-bottom: 10px;
    opacity: 0.9;
    color: white;
}

.summary-card h3 {
    font-size: 1.8rem;
    font-weight: 700;
    margin: 0;
    color: white;
}

.search-filter-section {
    display: flex;
    gap: 10px;
    align-items: center;
    flex-wrap: wrap;
}

.search-input {
    padding: 8px 15px;
    border: 2px solid rgba(255, 255, 255, 0.3);
    border-radius: 8px;
    font-size: 0.9rem;
    outline: none;
    transition: all 0.3s;
    background: rgba(255, 255, 255, 0.9);
}

.search-input:focus {
    border-color: white;
    box-shadow: 0 0 0 3px rgba(255, 255, 255, 0.2);
}

/* Loader */
.loader-bg {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%) !important;
}

.loader-fill {
    background: white !important;
}

/* Animations */
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

.card {
    animation: fadeInUp 0.5s ease-out;
}

.summary-card:nth-child(1) {
    animation: fadeInUp 0.5s ease-out 0.1s both;
}

.summary-card:nth-child(2) {
    animation: fadeInUp 0.5s ease-out 0.2s both;
}

.back-button-wrapper {
    margin-bottom: 20px;
}
</style>
</head>

<body>
    <!-- Pre-loader -->
    <div class="loader-bg fixed inset-0 bg-white dark:bg-themedark-cardbg z-[1034]">
        <div class="loader-track h-[5px] w-full inline-block absolute overflow-hidden top-0">
            <div class="loader-fill w-[300px] h-[5px] bg-primary-500 absolute top-0 left-0 animate-[hitZak_0.6s_ease-in-out_infinite_alternate]"></div>
        </div>
    </div>

    <!-- Sidebar -->
    <?php include '../includes/sidebar.php'; ?>

    <!-- Header -->
    <?php include '../includes/header.php'; ?>

    <!-- Main Content -->
    <div class="pc-container">
        <div class="pc-content">

            <!-- Breadcrumb -->
            <div class="page-header">
                <div class="page-block">
                    <div class="page-header-title">
                        <h5 class="mb-0 font-medium">📦 Archived Expenses</h5>
                        <div class="user-info">
                            Viewing archived expenses for <strong><?= htmlspecialchars($user['fullname']); ?></strong>
                        </div>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin/dashboard.php">Home</a></li>
                        <li class="breadcrumb-item"><a href="manage_expenses.php">Manage Expenses</a></li>
                        <li class="breadcrumb-item" aria-current="page">Archived</li>
                    </ul>
                </div>
            </div>

            <!-- Success/Delete Messages -->
            <?php if (isset($_GET['restored'])): ?>
                <div class="alert alert-success">
                    <i class="feather icon-check-circle" style="font-size: 1.5rem;"></i>
                    <strong>Expense restored successfully!</strong>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['deleted'])): ?>
                <div class="alert alert-danger">
                    <i class="feather icon-trash-2" style="font-size: 1.5rem;"></i>
                    <strong>Expense permanently deleted!</strong>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h6>Total Archived</h6>
                    <h3><?php echo $archivedCount; ?></h3>
                </div>
                <div class="summary-card" style="background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);">
                    <h6>Total Archived Amount</h6>
                    <h3>₱ <?php echo number_format($totalArchivedAmount, 2); ?></h3>
                </div>
            </div>

            <!-- Back Button -->
            <div class="back-button-wrapper">
                <a href="manage_expenses.php" class="btn btn-secondary">
                    <i class="feather icon-arrow-left"></i> Back to Active Expenses
                </a>
            </div>

            <!-- Archived Expense Table -->
            <div class="grid grid-cols-12 gap-x-6">
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>📝 Archived Expense Records</h5>
                            <div class="search-filter-section">
                                <input type="text" class="search-input" placeholder="Search archived expenses..." id="searchInput">
                            </div>
                        </div>

                        <div class="card-body">
                            <?php if ($archivedCount > 0): ?>
                                <div class="table-wrapper">
                                    <table class="table table-striped">
                                        <thead>
                                            <tr>
                                                <th>No</th>
                                                <th>Date</th>
                                                <th>Category</th>
                                                <th>Description</th>
                                                <th>Amount (₱)</th>
                                                <th>Payment Method</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="expenseTableBody">
                                            <?php foreach ($archivedExpenses as $index => $expense): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                                <td><span class="category-badge"><?php echo htmlspecialchars($expense['category']); ?></span></td>
                                                <td style="text-align: left;"><?php echo htmlspecialchars($expense['description']); ?></td>
                                                <td class="amount-cell">₱ <?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo htmlspecialchars($expense['payment_method']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <a href="?restore=<?php echo $expense['id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Restore this expense?');">
                                                            <i class="feather icon-rotate-ccw"></i> Restore
                                                        </a>
                                                        <a href="?delete=<?php echo $expense['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('⚠️ Permanently delete this expense? This action cannot be undone!');">
                                                            <i class="feather icon-trash-2"></i> Delete
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php else: ?>
                                <div class="alert alert-info">
                                    <i class="feather icon-info" style="font-size: 1.5rem;"></i>
                                    <strong>No archived expenses found.</strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Footer -->
    <?php include '../includes/footer.php'; ?>

    <!-- JS Scripts -->
    <script src="../assets/js/plugins/simplebar.min.js"></script>
    <script src="../assets/js/plugins/popper.min.js"></script>
    <script src="../assets/js/icon/custom-icon.js"></script>
    <script src="../assets/js/plugins/feather.min.js"></script>
    <script src="../assets/js/component.js"></script>
    <script src="../assets/js/theme.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        // Initialize Feather icons
        feather.replace();

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#expenseTableBody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // Layout setup
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