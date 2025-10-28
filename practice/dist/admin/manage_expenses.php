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

// ---------------------- ADD EXPENSE ----------------------
if (isset($_POST['add_expense'])) {
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $payment_method = $_POST['payment_method'];

    // ✅ Include user_id in the INSERT and set archived to 0 by default
    $stmt = $conn->prepare("INSERT INTO expenses (user_id, category, amount, date, description, payment_method, archived) VALUES (?, ?, ?, ?, ?, ?, 0)");
    $stmt->bind_param("isdsss", $user_id, $category, $amount, $date, $description, $payment_method);
    
    if ($stmt->execute()) {
        header("Location: manage_expenses.php?success=1");
        exit;
    }
    $stmt->close();
}

// ---------------------- EDIT EXPENSE ----------------------
if (isset($_POST['edit_expense'])) {
    $id = $_POST['id'];
    $category = $_POST['category'];
    $amount = $_POST['amount'];
    $date = $_POST['date'];
    $description = $_POST['description'];
    $payment_method = $_POST['payment_method'];

    // ✅ Verify the expense belongs to this user before updating
    $stmt = $conn->prepare("UPDATE expenses SET 
            category=?,
            amount=?,
            date=?,
            description=?,
            payment_method=?
            WHERE id=? AND user_id=? AND archived=0");
    $stmt->bind_param("sdsssii", $category, $amount, $date, $description, $payment_method, $id, $user_id);
    
    if ($stmt->execute()) {
        header("Location: manage_expenses.php?updated=1");
        exit;
    }
    $stmt->close();
}

// ---------------------- ARCHIVE EXPENSE ----------------------
if (isset($_GET['archive'])) {
    $id = intval($_GET['archive']);

    // ✅ Verify the expense belongs to this user before archiving
    $stmt = $conn->prepare("UPDATE expenses SET archived = 1 WHERE id=? AND user_id=? AND archived=0");
    $stmt->bind_param("ii", $id, $user_id);

    if ($stmt->execute() && $stmt->affected_rows > 0) {
        header("Location: manage_expenses.php?archived=1");
        exit;
    } else {
        header("Location: manage_expenses.php?error=archive_failed");
        exit;
    }
    $stmt->close();
}


// ---------------------- FETCH ONLY THIS USER'S ACTIVE (NON-ARCHIVED) EXPENSES ----------------------
$expenses = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND archived = 0 ORDER BY date DESC");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $expenses[] = $row;
            }
        } else {
            // DEBUG: No rows found
            error_log("No expenses found for user_id: " . $user_id);
        }
    } else {
        // DEBUG: Query failed
        error_log("Query execution failed: " . $stmt->error);
    }
    $stmt->close();
} else {
    // DEBUG: Prepare failed
    error_log("Prepare failed: " . $conn->error);
}

// Calculate totals with proper error handling
$totalExpenses = !empty($expenses) ? array_sum(array_column($expenses, 'amount')) : 0;
$expenseCount  = count($expenses);

// Calculate This Month's expenses (only active, non-archived)
$thisMonthExpenses = 0;
$currentMonth = date('Y-m');
foreach ($expenses as $expense) {
    $expenseMonth = substr($expense['date'], 0, 7); // Get YYYY-MM format
    if ($expenseMonth == $currentMonth) {
        $thisMonthExpenses += floatval($expense['amount']);
    }
}

// DEBUG OUTPUT - Add this temporarily to see what's happening
echo "<!-- DEBUG INFO:
User ID: $user_id
Expense Count: $expenseCount
Total Expenses: $totalExpenses
This Month Expenses: $thisMonthExpenses
Expenses Array: " . print_r($expenses, true) . "
-->";
?>

<!doctype html>
<html lang="en" data-pc-preset="preset-1" data-pc-sidebar-caption="true" data-pc-direction="ltr" dir="ltr" data-pc-theme="light">

<head>
    <title>FinTrack - Manage Expenses</title>
    <meta charset="utf-8" />
    <link rel="icon" type="image/png" href="../../logo.png" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=0, minimal-ui" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="description" content="Manage your expenses with FinTrack" />
    <meta name="keywords" content="expense, tracker, finance, budget" />
    <meta name="author" content="FinTrack Team" />

    <!-- Fonts & Styles -->
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700&display=swap" rel="stylesheet" />
    <link rel="stylesheet" href="../assets/fonts/phosphor/duotone/style.css" />
    <link rel="stylesheet" href="../assets/fonts/tabler-icons.min.css" />
    <link rel="stylesheet" href="../assets/fonts/feather.css" />
    <link rel="stylesheet" href="../assets/fonts/fontawesome.css" />
    <link rel="stylesheet" href="../assets/fonts/material.css" />
    <link rel="stylesheet" href="../assets/css/style.css" id="main-style-link" />
    <link rel="stylesheet" href="style/manage_expenses.css">
   
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
                        <h5 class="mb-0 font-medium">💰 Manage Expenses</h5>
                        <div class="user-info">
                            Managing expenses for <strong><?= htmlspecialchars($user['fullname']); ?></strong>
                        </div>
                    </div>
                    <ul class="breadcrumb">
                        <li class="breadcrumb-item"><a href="../admin/dashboard.php">Home</a></li>
                        <li class="breadcrumb-item" aria-current="page">Manage Expenses</li>
                        <li class="breadcrumb-item"><a href="logout.php">Logout</a></li>
                    </ul>
                </div>
            </div>

            <!-- Success/Update Messages -->
            <?php if (isset($_GET['success'])): ?>
                <div class="alert alert-success">
                    <i class="feather icon-check-circle" style="font-size: 1.5rem;"></i>
                    <strong>Expense added successfully!</strong>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['updated'])): ?>
                <div class="alert alert-info">
                    <i class="feather icon-check-circle" style="font-size: 1.5rem;"></i>
                    <strong>Expense updated successfully!</strong>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['archived'])): ?>
                <div class="alert alert-warning">
                    <i class="feather icon-archive" style="font-size: 1.5rem;"></i>
                    <strong>Expense archived successfully!</strong>
                </div>
            <?php endif; ?>

            <?php if (isset($_GET['error']) && $_GET['error'] === 'archive_failed'): ?>
                <div class="alert alert-danger">
                    <i class="feather icon-x-circle" style="font-size: 1.5rem;"></i>
                    <strong>Failed to archive expense. Please try again.</strong>
                </div>
            <?php endif; ?>

            <!-- Summary Cards -->
            <div class="summary-cards">
                <div class="summary-card">
                    <h6>Total Activities</h6>
                    <h3><?php echo $expenseCount; ?></h3>
                </div>
                <div class="summary-card" style="background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);">
                    <h6>Total Expenses Amount</h6>
                    <h3>₱ <?php echo number_format($totalExpenses, 2); ?></h3>
                </div>
                <div class="summary-card" style="background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);">
                    <h6>Total Expenses Amount This Month</h6>
                    <h3>₱ <?php echo number_format($thisMonthExpenses, 2); ?></h3>
                </div>
            </div>
            
            <a href="view_archived.php" class="btn btn-outline-warning mb-3">
                <i class="feather icon-archive"></i> View Archived Expenses
            </a>

            <!-- Expense Table -->
            <div class="grid grid-cols-12 gap-x-6">
                <div class="col-span-12">
                    <div class="card">
                        <div class="card-header">
                            <h5>📝 Active Expense Records</h5>
                            <div class="search-filter-section">
                                <input type="text" class="search-input" placeholder="Search expenses..." id="searchInput">
                                <button type="button" class="btn btn-primary" onclick="openModal('addExpenseModal')">
                                    <i class="feather icon-plus"></i> Add Expense
                                </button>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-wrapper">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>No</th>
                                            <th>Category</th>
                                            <th>Amount</th>
                                            <th>Date</th>
                                            <th>Description</th>
                                            <th>Payment Method</th>
                                            <th>Action</th>
                                        </tr>
                                    </thead>
                                    <tbody id="expenseTableBody">
                                        <?php if (!empty($expenses)): ?>
                                            <?php foreach ($expenses as $index => $expense): ?>
                                            <tr>
                                                <td><?php echo $index + 1; ?></td>
                                                <td><span class="category-badge"><?php echo htmlspecialchars($expense['category']); ?></span></td>
                                                <td class="amount-cell">₱ <?php echo number_format($expense['amount'], 2); ?></td>
                                                <td><?php echo date('M d, Y', strtotime($expense['date'])); ?></td>
                                                <td style="text-align: left;"><?php echo htmlspecialchars($expense['description']); ?></td>
                                                <td><?php echo htmlspecialchars($expense['payment_method']); ?></td>
                                                <td>
                                                    <div class="action-buttons">
                                                        <button type="button" class="btn btn-info btn-sm" onclick="viewExpense(<?php echo $expense['id']; ?>)">
                                                            <i class="feather icon-eye"></i> View
                                                        </button>
                                                        <button type="button" class="btn btn-warning btn-sm"
                                                            onclick="openEditModal('<?php echo $expense['id']; ?>', '<?php echo htmlspecialchars($expense['category']); ?>', '<?php echo $expense['amount']; ?>', '<?php echo $expense['date']; ?>', '<?php echo htmlspecialchars($expense['description']); ?>', '<?php echo htmlspecialchars($expense['payment_method']); ?>')">
                                                            <i class="feather icon-edit"></i> Edit
                                                        </button>
                                                       <a href="?archive=<?php echo $expense['id']; ?>" class="btn btn-warning btn-sm" onclick="return confirm('Archive this expense? You can restore it later from the archived section.');">
                                                            <i class="feather icon-archive"></i> Archive
                                                        </a>
                                                    </div>
                                                </td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr>
                                                <td colspan="7" style="text-align:center; padding:20px;">No active expenses found. Start adding your expenses!</td>
                                            </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Buttons -->
                            <div class="button-group">
                                <button type="button" class="btn btn-primary" onclick="exportToExcel()">
                                    <i class="feather icon-download"></i> Export to Excel
                                </button>
                                <button type="button" class="btn btn-info" onclick="generateReport()">
                                    <i class="feather icon-file-text"></i> Generate Report
                                </button>
                                <button type="button" class="btn btn-warning" onclick="filterByCategoryAndDate()">
                                    <i class="feather icon-filter"></i> Filter by Category & Date
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>

    <!-- Add Expense Modal -->
    <div id="addExpenseModal" style="display:none;" class="modal">
        <form method="POST">
            <h3>Add Expense</h3>
            <label>Category:</label>
            <select name="category" required>
                <option value="" disabled selected>Select category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Amount:</label>
            <input type="number" name="amount" step="0.01" required>
            <label>Date:</label>
            <input type="date" name="date" value="<?php echo date('Y-m-d'); ?>" required>
            <label>Description:</label>
            <textarea name="description"></textarea>
            <label>Payment Method:</label>
            <select name="payment_method" required>
                <option value="" disabled selected>Select Payment Method</option>
                <option value="Cash">Cash</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Debit Card">Debit Card</option>
                <option value="E-Wallet">E-Wallet</option>
                <option value="Online Banking">Online Banking</option>
                <option value="Other">Other</option>
            </select>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="add_expense">Save</button>
                <button type="button" onclick="closeModal('addExpenseModal')">Cancel</button>
            </div>
        </form>
    </div>

    <!-- Edit Expense Modal -->
    <div id="editExpenseModal" style="display:none;" class="modal">
        <form method="POST" id="editForm">
            <h3>Edit Expense</h3>
            <input type="hidden" name="id" id="edit_id">
            <label>Category:</label>
            <select name="category" id="edit_category" required>
                <option value="" disabled>Select category</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?php echo $cat; ?>"><?php echo $cat; ?></option>
                <?php endforeach; ?>
            </select>
            <label>Amount:</label>
            <input type="number" name="amount" id="edit_amount" step="0.01" min="0" placeholder="Enter amount (₱)" required>
            <label>Date:</label>
            <input type="date" name="date" id="edit_date" required>
            <label>Description:</label>
            <textarea name="description" id="edit_description"></textarea>
            <label>Payment Method:</label>
            <select name="payment_method" id="edit_payment_method" required>
                <option value="Cash">Cash</option>
                <option value="Credit Card">Credit Card</option>
                <option value="Debit Card">Debit Card</option>
                <option value="E-Wallet">E-Wallet</option>
                <option value="Online Banking">Online Banking</option>
                <option value="Other">Other</option>
            </select>
            <div style="display: flex; gap: 10px; margin-top: 20px;">
                <button type="submit" name="edit_expense">Update</button>
                <button type="button" onclick="closeModal('editExpenseModal')">Cancel</button>
            </div>
        </form>
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
        // Store all expenses data for filtering and exporting
        const expensesData = <?php echo json_encode($expenses); ?>;

        // DEBUG: Console log to check data
        console.log('User ID:', <?php echo $user_id; ?>);
        console.log('Expense Count:', <?php echo $expenseCount; ?>);
        console.log('Expenses Data:', expensesData);

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchValue = this.value.toLowerCase();
            const tableRows = document.querySelectorAll('#expenseTableBody tr');

            tableRows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchValue) ? '' : 'none';
            });
        });

        // View Expense - Enhanced with modal
        function viewExpense(id) {
            const expense = expensesData.find(e => e.id == id);
            if (!expense) return;

            const modalContent = `
                <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%;">
                    <h3 style="color: #667eea; font-weight: 700; margin-bottom: 20px;">📋 Expense Details</h3>
                    <div style="background: #f9fafb; padding: 20px; border-radius: 10px; margin-bottom: 15px;">
                        <p style="margin: 10px 0;"><strong>Category:</strong> <span class="category-badge">${expense.category}</span></p>
                        <p style="margin: 10px 0;"><strong>Amount:</strong> <span style="color: #764ba2; font-weight: 700; font-size: 1.2rem;">₱ ${parseFloat(expense.amount).toFixed(2)}</span></p>
                        <p style="margin: 10px 0;"><strong>Date:</strong> ${new Date(expense.date).toLocaleDateString('en-US', { year: 'numeric', month: 'long', day: 'numeric' })}</p>
                        <p style="margin: 10px 0;"><strong>Payment Method:</strong> ${expense.payment_method}</p>
                        <p style="margin: 10px 0;"><strong>Description:</strong> ${expense.description || 'No description provided'}</p>
                    </div>
                    <button onclick="closeViewModal()" style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%;">Close</button>
                </div>
            `;

            const modal = document.createElement('div');
            modal.id = 'viewModal';
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = modalContent;
            document.body.appendChild(modal);
        }

        function closeViewModal() {
            const modal = document.getElementById('viewModal');
            if (modal) modal.remove();
        }

        // Export to Excel
        function exportToExcel() {
            let csv = [];
            
            // Add header row
            const headerRow = ['No', 'Date', 'Category', 'Amount', 'Description', 'Payment Method'];
            csv.push(headerRow.join(','));
            
            // Add data rows
            expensesData.forEach((expense, index) => {
                const row = [
                    index + 1,
                    expense.date,
                    `"${expense.category}"`,
                    `"₱ ${parseFloat(expense.amount).toFixed(2)}"`,
                    `"${expense.description.replace(/"/g, '""')}"`,
                    `"${expense.payment_method}"`
                ];
                csv.push(row.join(','));
            });
            
            const csvContent = csv.join('\n');
            
            // ✅ Add UTF-8 BOM (Byte Order Mark) for proper Excel encoding
            const BOM = '\uFEFF';
            const blob = new Blob([BOM + csvContent], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement('a');
            const url = URL.createObjectURL(blob);
            
            link.setAttribute('href', url);
            link.setAttribute('download', 'expenses_' + new Date().toISOString().split('T')[0] + '.csv');
            link.style.visibility = 'hidden';
            
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);

            // Show success message
            showNotification('✅ Expenses exported successfully!', 'success');
        }

        // Generate Report
        function generateReport() {
            const totalExpenses = expensesData.reduce((sum, e) => sum + parseFloat(e.amount), 0);
            const categoryTotals = {};
            const paymentMethodTotals = {};

            expensesData.forEach(expense => {
                // Category totals
                if (!categoryTotals[expense.category]) {
                    categoryTotals[expense.category] = 0;
                }
                categoryTotals[expense.category] += parseFloat(expense.amount);

                // Payment method totals
                if (!paymentMethodTotals[expense.payment_method]) {
                    paymentMethodTotals[expense.payment_method] = 0;
                }
                paymentMethodTotals[expense.payment_method] += parseFloat(expense.amount);
            });

            let reportHTML = `
                <div style="background: white; padding: 30px; border-radius: 15px; max-width: 600px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <h3 style="color: #667eea; font-weight: 700; margin-bottom: 20px;">📊 Expense Report</h3>
                    
                    <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; border-radius: 10px; margin-bottom: 20px; text-align: center;">
                        <h4 style="margin: 0 0 10px 0;">Total Expenses</h4>
                        <h2 style="margin: 0; font-size: 2rem;">₱ ${totalExpenses.toFixed(2)}</h2>
                        <p style="margin: 10px 0 0 0; opacity: 0.9;">${expensesData.length} transactions</p>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #374151; font-weight: 700; margin-bottom: 15px;">By Category</h4>
                        ${Object.entries(categoryTotals).map(([category, amount]) => `
                            <div style="background: #f9fafb; padding: 12px; border-radius: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600;">${category}</span>
                                <span style="color: #764ba2; font-weight: 700;">₱ ${amount.toFixed(2)}</span>
                            </div>
                        `).join('')}
                    </div>

                    <div style="margin-bottom: 20px;">
                        <h4 style="color: #374151; font-weight: 700; margin-bottom: 15px;">By Payment Method</h4>
                        ${Object.entries(paymentMethodTotals).map(([method, amount]) => `
                            <div style="background: #f9fafb; padding: 12px; border-radius: 8px; margin-bottom: 8px; display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 600;">${method}</span>
                                <span style="color: #764ba2; font-weight: 700;">₱ ${amount.toFixed(2)}</span>
                            </div>
                        `).join('')}
                    </div>

                    <button onclick="closeReportModal()" style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px;">Close</button>
                </div>
            `;

            const modal = document.createElement('div');
            modal.id = 'reportModal';
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = reportHTML;
            document.body.appendChild(modal);
        }

        function closeReportModal() {
            const modal = document.getElementById('reportModal');
            if (modal) modal.remove();
        }

        // Enhanced Filter by Category and Date
        function filterByCategoryAndDate() {
            const categories = [...new Set(expensesData.map(e => e.category))].sort();
            
            let filterHTML = `
                <div style="background: white; padding: 30px; border-radius: 15px; max-width: 500px; width: 90%; max-height: 80vh; overflow-y: auto;">
                    <h3 style="color: #667eea; font-weight: 700; margin-bottom: 20px;">🔍 Filter Expenses</h3>
                    
                    <div class="filter-row">
                        <div class="filter-group">
                            <label>From Date:</label>
                            <input type="date" id="filterFromDate" style="width: 100%; padding: 8px; border: 2px solid #e5e7eb; border-radius: 8px;">
                        </div>
                        <div class="filter-group">
                            <label>To Date:</label>
                            <input type="date" id="filterToDate" style="width: 100%; padding: 8px; border: 2px solid #e5e7eb; border-radius: 8px;">
                        </div>
                    </div>

                    <div class="filter-group" style="margin-bottom: 20px;">
                        <label>Category:</label>
                        <select id="filterCategory" style="width: 100%; padding: 10px; border: 2px solid #e5e7eb; border-radius: 8px; font-weight: 600;">
                            <option value="all">All Categories</option>
                            ${categories.map(cat => `<option value="${cat}">${cat}</option>`).join('')}
                        </select>
                    </div>

                    <div style="display: flex; gap: 10px; margin-top: 20px;">
                        <button onclick="applyAdvancedFilter()" style="flex: 1; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            <i class="feather icon-check"></i> Apply Filter
                        </button>
                        <button onclick="clearFilters()" style="flex: 1; background: #f59e0b; color: white; padding: 12px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer;">
                            <i class="feather icon-x"></i> Clear
                        </button>
                    </div>
                    
                    <button onclick="closeAdvancedFilterModal()" style="background: #6b7280; color: white; padding: 10px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; width: 100%; margin-top: 10px;">Close</button>
                </div>
            `;

            const modal = document.createElement('div');
            modal.id = 'advancedFilterModal';
            modal.className = 'modal';
            modal.style.display = 'flex';
            modal.innerHTML = filterHTML;
            document.body.appendChild(modal);
        }

        function applyAdvancedFilter() {
            const fromDate = document.getElementById('filterFromDate').value;
            const toDate = document.getElementById('filterToDate').value;
            const category = document.getElementById('filterCategory').value;
            
            const tableRows = document.querySelectorAll('#expenseTableBody tr');
            let visibleCount = 0;
            
            tableRows.forEach(row => {
                // Skip if it's the "no expenses" row
                if (row.cells.length < 7) {
                    row.style.display = 'none';
                    return;
                }

                const rowCategory = row.cells[1].textContent.trim();
                const rowDateCell = row.cells[3].textContent.trim();
                
                // Find matching expense data by category and approximate date
                const expense = expensesData.find(e => {
                    const expenseDate = new Date(e.date).toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric' });
                    return expenseDate === rowDateCell && e.category === rowCategory;
                });
                
                if (!expense) {
                    row.style.display = 'none';
                    return;
                }
                
                const rowDate = expense.date; // Use the actual date from data (YYYY-MM-DD format)
                
                let showRow = true;
                
                // Filter by category
                if (category !== 'all' && rowCategory !== category) {
                    showRow = false;
                }
                
                // Filter by from date
                if (fromDate && rowDate < fromDate) {
                    showRow = false;
                }
                
                // Filter by to date
                if (toDate && rowDate > toDate) {
                    showRow = false;
                }
                
                row.style.display = showRow ? '' : 'none';
                if (showRow) visibleCount++;
            });

            closeAdvancedFilterModal();
            
            let filterMessage = '🔍 Filters applied';
            if (category !== 'all') filterMessage += `: ${category}`;
            if (fromDate || toDate) {
                filterMessage += ' | Date: ';
                if (fromDate) filterMessage += `From ${new Date(fromDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
                if (toDate) filterMessage += ` To ${new Date(toDate).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' })}`;
            }
            filterMessage += ` (${visibleCount} result${visibleCount !== 1 ? 's' : ''})`;
            
            showNotification(filterMessage, 'info');
        }

        function clearFilters() {
            const tableRows = document.querySelectorAll('#expenseTableBody tr');
            tableRows.forEach(row => {
                row.style.display = '';
            });
            
            closeAdvancedFilterModal();
            showNotification('🔄 All filters cleared - showing all expenses', 'info');
        }

        function closeAdvancedFilterModal() {
            const modal = document.getElementById('advancedFilterModal');
            if (modal) modal.remove();
        }

        // Notification system
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 15px 25px;
                border-radius: 10px;
                color: white;
                font-weight: 600;
                z-index: 10000;
                animation: slideInRight 0.5s ease-out;
                box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            `;
            
            if (type === 'success') {
                notification.style.background = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
            } else if (type === 'info') {
                notification.style.background = 'linear-gradient(135deg, #3b82f6 0%, #2563eb 100%)';
            }
            
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.style.animation = 'fadeOut 0.5s ease-out';
                setTimeout(() => notification.remove(), 500);
            }, 3000);
        }

        // Layout setup
        layout_change('false');
        layout_theme_sidebar_change('dark');
        change_box_container('false');
        layout_caption_change('true');
        layout_rtl_change('false');
        preset_change('preset-1');
        main_layout_change('vertical');

        function openModal(id) {
            document.getElementById(id).style.display = 'flex';
        }
        
        function closeModal(id) {
            document.getElementById(id).style.display = 'none';
        }

        function openEditModal(id, category, amount, date, description, payment_method) {
            document.getElementById('edit_id').value = id;
            document.getElementById('edit_category').value = category;
            document.getElementById('edit_amount').value = amount;
            document.getElementById('edit_date').value = date;
            document.getElementById('edit_description').value = description;
            document.getElementById('edit_payment_method').value = payment_method;
            openModal('editExpenseModal');
        }
    </script>
</body>
</html>