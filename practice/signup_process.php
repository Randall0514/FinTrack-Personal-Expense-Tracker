<?php
session_start();

// Include database config
require __DIR__ . "/config/dbconfig_password.php";

// Check database connection
if (!isset($conn) || $conn->connect_error) {
    die("Database connection failed: " . ($conn->connect_error ?? "Unknown error"));
}

if (isset($_POST['submit'])) {
    // Sanitize and validate input
    $fullname = trim($_POST['fullname']);
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validate required fields
    if (empty($fullname) || empty($username) || empty($email) || empty($password)) {
        echo "<script>alert('All fields are required!'); window.history.back();</script>";
        exit();
    }

    // Validate email format
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "<script>alert('Invalid email format!'); window.history.back();</script>";
        exit();
    }

    // Check if passwords match
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit();
    }

    // Check if username or email already exists
    $check = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $result = $check->get_result();

    if ($result->num_rows > 0) {
        echo "<script>alert('Username or email already exists!'); window.history.back();</script>";
        $check->close();
        $conn->close();
        exit();
    }
    $check->close();

    // Check if auto-approval is enabled
    $is_approved = 0; // Default to not approved (manual approval)
    $auto_approval_result = $conn->query("SELECT setting_value FROM system_settings WHERE setting_key = 'auto_approval'");
    if ($auto_approval_result && $auto_approval_result->num_rows > 0) {
        $setting = $auto_approval_result->fetch_assoc();
        if ($setting['setting_value'] === '1') {
            $is_approved = 1; // Auto-approve the user
        }
    }

    // Hash password
    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

    // Insert new user with approval status
    $stmt = $conn->prepare("INSERT INTO users (fullname, username, email, password, is_approved) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssi", $fullname, $username, $email, $hashedPassword, $is_approved);

    if ($stmt->execute()) {
        if ($is_approved === 1) {
            echo "<script>alert('Registration successful! You can now log in.'); window.location.href='login.php';</script>";
        } else {
            echo "<script>alert('Registration successful! Your account is pending admin approval. You will be able to log in once approved.'); window.location.href='login.php';</script>";
        }
    } else {
        echo "<script>alert('Registration failed. Please try again.'); window.history.back();</script>";
    }

    $stmt->close();
    $conn->close();
} else {
    // Redirect if accessed without POST
    header("Location: signup.php");
    exit();
}
?>