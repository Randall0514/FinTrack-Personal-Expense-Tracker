<?php
session_start();
require __DIR__ . '/../../vendor/autoload.php';

// Database connection
include '../database/config/db.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// JWT Authentication
$secret_key = "your_secret_key_here_change_this_in_production";

header('Content-Type: application/json');

if (!isset($_COOKIE['jwt_token'])) {
    echo json_encode(['success' => false, 'message' => 'Authentication required']);
    exit;
}

$jwt = $_COOKIE['jwt_token'];

try {
    $decoded = JWT::decode($jwt, new Key($secret_key, 'HS256'));
    $user = (array) $decoded->data;
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Invalid token']);
    exit;
}

$user_id = $user['id'];

// Delete all chat history for this user
try {
    $delete_stmt = $conn->prepare("DELETE FROM chat_history WHERE user_id = ?");
    
    if ($delete_stmt) {
        $delete_stmt->bind_param("i", $user_id);
        
        if ($delete_stmt->execute()) {
            $affected_rows = $delete_stmt->affected_rows;
            $delete_stmt->close();
            
            echo json_encode([
                'success' => true,
                'message' => 'Chat history cleared successfully',
                'deleted_count' => $affected_rows
            ]);
        } else {
            echo json_encode([
                'success' => false,
                'message' => 'Failed to delete chat history'
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Database error: Could not prepare statement'
        ]);
    }
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>