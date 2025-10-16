<?php
    session_start();
    session_destroy();
    header("Location:\FinTrack-Personal-Expense-Tracker\practice\login.php");
    exit();
?>