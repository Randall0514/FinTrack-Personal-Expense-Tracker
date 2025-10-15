<?php
$host = "localhost";
$username = "root"; // default XAMPP username
$password = "";     // leave empty if no password
$database = "practice_db"; // change this to your database name

try {
    $con = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $username, $password);
    $con->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>