<?php
require __DIR__ . '/vendor/autoload.php';

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

// Secret key (keep this safe)
$secretKey = "my_secret_key";

// Create a token payload
$payload = [
    'user_id' => 1,
    'email' => 'test@example.com',
    'iat' => time(),
    'exp' => time() + (60 * 5) // expires in 5 minutes
];

// Encode
$jwt = JWT::encode($payload, $secretKey, 'HS256');
echo "Generated JWT: " . $jwt . "<br><br>";

// Decode
$decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));
echo "Decoded payload:<br>";
print_r($decoded);
?>
