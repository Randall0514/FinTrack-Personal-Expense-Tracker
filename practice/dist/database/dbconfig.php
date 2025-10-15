<?php
$host = "localhost";
$user = "root"; // your DB username
$pass = "";     // your DB password
$db   = "fintrack"; // your database name

$conn = new mysqli($host, $user, $pass, $db);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}
?>
