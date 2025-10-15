<?php
    session_start();
    session_destroy();
    header("Location:\practice\login.php");
    exit();
?>