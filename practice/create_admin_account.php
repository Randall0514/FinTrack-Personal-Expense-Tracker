<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Admin Account</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 0 auto;
            padding: 20px;
        }
        .container {
            background-color: #f5f5f5;
            border-radius: 5px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .success {
            background-color: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .error {
            background-color: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .credentials {
            background-color: #fff;
            padding: 15px;
            border-radius: 5px;
            border: 1px solid #ddd;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Create Admin Account</h1>
        
        <?php
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Connect to database
            require_once 'config/dbconfig_password.php';
            
            // Admin credentials
            $username = "admin";
            $fullname = "System Administrator";
            $email = "admin@fintrack.com";
            $password = "Admin@123"; // Plain password for display
            $hashed_password = password_hash($password, PASSWORD_DEFAULT); // Hashed for storage
            
            // Check if admin already exists
            $stmt = $conn->prepare("SELECT * FROM users WHERE username = ? OR email = ?");
            $stmt->bind_param("ss", $username, $email);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                echo '<div class="error">Admin user already exists!</div>';
            } else {
                // Check if the users table has is_admin and is_approved columns
                $result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_admin'");
                if ($result->num_rows == 0) {
                    // Add is_admin column if it doesn't exist
                    $conn->query("ALTER TABLE users ADD COLUMN is_admin TINYINT(1) DEFAULT 0");
                }
                
                $result = $conn->query("SHOW COLUMNS FROM users LIKE 'is_approved'");
                if ($result->num_rows == 0) {
                    // Add is_approved column if it doesn't exist
                    $conn->query("ALTER TABLE users ADD COLUMN is_approved TINYINT(1) DEFAULT 0");
                }
                
                // Insert admin user
                $stmt = $conn->prepare("INSERT INTO users (username, fullname, email, password, is_admin, is_approved) VALUES (?, ?, ?, ?, 1, 1)");
                $stmt->bind_param("ssss", $username, $fullname, $email, $hashed_password);
                
                if ($stmt->execute()) {
                    echo '<div class="success">Admin account created successfully!</div>';
                    echo '<div class="credentials">';
                    echo '<h3>Admin Credentials</h3>';
                    echo '<p><strong>Username:</strong> ' . $username . '</p>';
                    echo '<p><strong>Password:</strong> ' . $password . '</p>';
                    echo '<p>You can now log in with these credentials at <a href="login.php">login page</a>.</p>';
                    echo '</div>';
                } else {
                    echo '<div class="error">Error creating admin account: ' . $conn->error . '</div>';
                }
            }
            
            $conn->close();
        }
        ?>
        
        <form method="post" action="">
            <p>Click the button below to create an admin account with the following credentials:</p>
            <ul>
                <li><strong>Username:</strong> admin</li>
                <li><strong>Password:</strong> Admin@123</li>
            </ul>
            <button type="submit" style="background-color: #4CAF50; color: white; padding: 10px 15px; border: none; border-radius: 4px; cursor: pointer;">Create Admin Account</button>
        </form>
    </div>
</body>
</html>