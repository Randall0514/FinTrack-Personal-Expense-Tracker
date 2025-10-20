<?php
session_start();
require 'config/dbconfig_password.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize inputs
    $username_or_email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';

    // Validate inputs
    if (empty($username_or_email) || empty($password)) {
        echo "<script>alert('Please fill in all fields!'); window.history.back();</script>";
        exit;
    }

    // Check if user exists
    $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ? LIMIT 1");
    $stmt->bind_param("ss", $username_or_email, $username_or_email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $user_id = $user['id'];
        $username = $user['username'];
        $email = $user['email'];

        if (password_verify($password, $user['password'])) {
            // Check if user is approved
            if (!isset($user['is_approved']) || $user['is_approved'] != 1) {
                // Record failed attempt (not approved)
                $stmt_attempt = $conn->prepare("INSERT INTO login_attempts (user_id, username, email, success, ip_address) VALUES (?, ?, ?, 0, ?)");
                $stmt_attempt->bind_param("isss", $user_id, $username, $email, $ip_address);
                $stmt_attempt->execute();
                
                echo "<script>alert('Your account is pending approval. Please contact an administrator.'); window.history.back();</script>";
                exit;
            }
            
            // Record SUCCESSFUL login attempt
            $stmt_attempt = $conn->prepare("INSERT INTO login_attempts (user_id, username, email, success, ip_address) VALUES (?, ?, ?, 1, ?)");
            $stmt_attempt->bind_param("isss", $user_id, $username, $email, $ip_address);
            $stmt_attempt->execute();
            
            // ✅ Correct secret key
            $secret_key = "your_secret_key_here_change_this_in_production";
            $payload = [
                "iss" => "Fintrack",
                "aud" => "FintrackUser",
                "iat" => time(),
                "exp" => time() + (60 * 60), // 1 hour
                "data" => [
                    "id" => $user['id'],
                    "fullname" => $user['fullname'],
                    "email" => $user['email'],
                    "is_admin" => isset($user['is_admin']) ? (bool)$user['is_admin'] : false
                ]
            ];

            $jwt = JWT::encode($payload, $secret_key, 'HS256');

            // ✅ FIXED: Use different cookie names based on user role
            $is_admin = isset($user['is_admin']) && $user['is_admin'] == 1;
            $cookie_name = $is_admin ? "admin_jwt_token" : "jwt_token";
            
            // Clear the other cookie to prevent conflicts
            $other_cookie = $is_admin ? "jwt_token" : "admin_jwt_token";
            setcookie($other_cookie, "", time() - 3600, "/", "", false, true);
            
            // Set the appropriate cookie (removed localhost domain restriction)
            setcookie($cookie_name, $jwt, time() + (60 * 60), "/", "", false, true);
            
            // Debug log
            error_log("Login successful. User: " . $username . ", Is Admin: " . ($is_admin ? 'Yes' : 'No') . ", Cookie: " . $cookie_name);

            // ✅ Redirect based on user role
            if ($is_admin) {
                header("Location: http://localhost/FinTrack-Personal-Expense-Tracker/practice/admin/dashboard.php");
            } else {
                header("Location: http://localhost/FinTrack-Personal-Expense-Tracker/practice/dist/admin/dashboard.php");
            }
            exit;
        } else {
            // Record FAILED login attempt (wrong password)
            $stmt_attempt = $conn->prepare("INSERT INTO login_attempts (user_id, username, email, success, ip_address) VALUES (?, ?, ?, 0, ?)");
            $stmt_attempt->bind_param("isss", $user_id, $username, $email, $ip_address);
            $stmt_attempt->execute();
            
            echo "<script>alert('Invalid password!'); window.history.back();</script>";
            exit;
        }
    } else {
        // User not found - record failed attempt without user_id
        // Try to extract username and email from input
        if (filter_var($username_or_email, FILTER_VALIDATE_EMAIL)) {
            // Input is an email
            $email_attempt = $username_or_email;
            $username_attempt = explode('@', $username_or_email)[0]; // Use part before @ as username
        } else {
            // Input is a username
            $username_attempt = $username_or_email;
            $email_attempt = $username_or_email . '@unknown.com'; // Placeholder email
        }
        
        // Record FAILED login attempt (user not found)
        $stmt_attempt = $conn->prepare("INSERT INTO login_attempts (user_id, username, email, success, ip_address) VALUES (NULL, ?, ?, 0, ?)");
        $stmt_attempt->bind_param("sss", $username_attempt, $email_attempt, $ip_address);
        $stmt_attempt->execute();
        
        echo "<script>alert('No account found with that username/email!'); window.history.back();</script>";
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>