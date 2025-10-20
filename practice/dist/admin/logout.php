<?php
session_start();

// ✅ Clear only user token
setcookie("jwt_token", "", time() - 3600, "/", "", false, true);

// Destroy session
session_unset();
session_destroy();

// ✅ Redirect to login page
header("Location: http://localhost/FinTrack-Personal-Expense-Tracker/practice/login.php");
exit;
?>
