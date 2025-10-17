<?php
session_start();
// Clear the session
session_destroy();

// Clear the JWT cookie
setcookie("jwt_token", "", time() - 3600, "/", "localhost", false, true);

// Redirect to login page
header("Location: login.php");
exit;
?>