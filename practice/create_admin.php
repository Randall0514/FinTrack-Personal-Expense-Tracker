<?php
// Connect to database
require_once 'config/dbconfig_password.php';

// Admin credentials
$username = "admin";
$fullname = "System Administrator";
$email = "admin@fintrack.com";
$password = "Admin@123"; // Plain password for display
$hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hashed for storage

// Check if admin already exists
$stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    echo "Admin user already exists!";
} else {
    // Insert admin user
    $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, is_admin, is_approved) VALUES (?, ?, ?, ?, 1, 1)");
    $stmt->bind_param("ssss", $username, $fullname, $email, $hashed_password);
    
    if ($stmt->execute()) {
        echo "<h2>Admin account created successfully!</h2>";
        echo "<p><strong>Username:</strong> $username</p>";
        echo "<p><strong>Password:</strong> $password</p>";
        echo "<p>You can now log in with these credentials.</p>";
    } else {
        echo "Error creating admin account: " . $conn->error;
    }
}

$conn->close();
?>