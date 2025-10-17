<?php
session_start();
require 'config/dbconfig_password.php';
require 'vendor/autoload.php';

use Firebase\JWT\JWT;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize inputs
    $username_or_email = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

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

        if (password_verify($password, $user['password'])) {
            // Check if user is approved
            if (!isset($user['is_approved']) || $user['is_approved'] != 1) {
                echo "<script>alert('Your account is pending approval. Please contact an administrator.'); window.history.back();</script>";
                exit;
            }
            
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

            // ✅ Store the token in a cookie (valid for all localhost paths)
            setcookie("jwt_token", $jwt, time() + (60 * 60), "/", "localhost", false, true);

            // ✅ Redirect based on user role
            if (isset($user['is_admin']) && $user['is_admin'] == 1) {
                header("Location: http://localhost/FinTrack-Personal-Expense-Tracker/practice/admin/dashboard.php");
            } else {
                header("Location: http://localhost/FinTrack-Personal-Expense-Tracker/practice/dist/admin/dashboard.php");
            }
            exit;
        } else {
            echo "<script>alert('Invalid password!'); window.history.back();</script>";
            exit;
        }
    } else {
        echo "<script>alert('No account found with that username/email!'); window.history.back();</script>";
        exit;
    }

    $stmt->close();
    $conn->close();
}
?>
