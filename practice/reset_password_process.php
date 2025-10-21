<?php
session_start();
require __DIR__ . "/config/dbconfig_password.php";

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token = $_POST['token'] ?? '';
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate inputs
    if (empty($token) || empty($password) || empty($confirm_password)) {
        echo "<script>alert('All fields are required!'); window.history.back();</script>";
        exit;
    }
    
    // Check if passwords match
    if ($password !== $confirm_password) {
        echo "<script>alert('Passwords do not match!'); window.history.back();</script>";
        exit;
    }
    
    // Validate password strength
    if (strlen($password) < 8) {
        echo "<script>alert('Password must be at least 8 characters long!'); window.history.back();</script>";
        exit;
    }
    
    if (!preg_match('/[A-Z]/', $password)) {
        echo "<script>alert('Password must contain at least one uppercase letter!'); window.history.back();</script>";
        exit;
    }
    
    if (!preg_match('/[a-z]/', $password)) {
        echo "<script>alert('Password must contain at least one lowercase letter!'); window.history.back();</script>";
        exit;
    }
    
    if (!preg_match('/[0-9]/', $password)) {
        echo "<script>alert('Password must contain at least one number!'); window.history.back();</script>";
        exit;
    }
    
    $token_hash = hash('sha256', $token);
    
    // Verify token and get user
    $stmt = $conn->prepare("
        SELECT pr.user_id, pr.expires_at, u.email, u.fullname 
        FROM password_resets pr 
        JOIN users u ON pr.user_id = u.id 
        WHERE pr.token_hash = ? AND pr.expires_at > NOW() AND pr.used = 0
        LIMIT 1
    ");
    $stmt->bind_param("s", $token_hash);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        echo "<script>alert('Invalid or expired reset token!'); window.location.href='forgot_password.php';</script>";
        exit;
    }
    
    $user_data = $result->fetch_assoc();
    $user_id = $user_data['user_id'];
    $stmt->close();
    
    // Hash new password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    
    // Update user password
    $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
    $stmt->bind_param("si", $hashed_password, $user_id);
    
    if ($stmt->execute()) {
        // Mark token as used
        $stmt_update = $conn->prepare("UPDATE password_resets SET used = 1 WHERE token_hash = ?");
        $stmt_update->bind_param("s", $token_hash);
        $stmt_update->execute();
        $stmt_update->close();
        
        // Log the password change
        $ip_address = $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1';
        $stmt_log = $conn->prepare("INSERT INTO password_change_log (user_id, changed_at, ip_address) VALUES (?, NOW(), ?)");
        $stmt_log->bind_param("is", $user_id, $ip_address);
        $stmt_log->execute();
        $stmt_log->close();
        
        echo "<script>
            alert('✅ Password reset successful! You can now login with your new password.');
            window.location.href='login.php';
        </script>";
    } else {
        echo "<script>alert('Failed to update password. Please try again.'); window.history.back();</script>";
    }
    
    $stmt->close();
    $conn->close();
} else {
    header("Location: forgot_password.php");
    exit;
}
?>