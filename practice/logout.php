<?php
session_start();

// ✅ Clear both user and admin JWT cookies
setcookie("jwt_token", "", time() - 3600, "/FinTrack-Personal-Expense-Tracker/", "", false, true);
setcookie("admin_jwt_token", "", time() - 3600, "/FinTrack-Personal-Expense-Tracker/", "", false, true);

// ✅ Clear session data
session_unset();
session_destroy();

// ✅ Redirect to login page
header("Location: http://localhost/FinTrack-Personal-Expense-Tracker/practice/login.php");
exit;
?>
