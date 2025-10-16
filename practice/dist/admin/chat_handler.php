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

// Get message from POST
$data = json_decode(file_get_contents('php://input'), true);
$userMessage = $data['message'] ?? '';

if (empty($userMessage)) {
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

// Fetch user's expense data for context
$expenses = [];
$stmt = $conn->prepare("SELECT * FROM expenses WHERE user_id = ? AND archived = 0 ORDER BY date DESC LIMIT 50");
if ($stmt) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $expenses[] = $row;
    }
    $stmt->close();
}

// Calculate statistics
$totalSpending = array_sum(array_column($expenses, 'amount'));
$categoryBreakdown = [];
foreach ($expenses as $expense) {
    $cat = $expense['category'];
    $categoryBreakdown[$cat] = ($categoryBreakdown[$cat] ?? 0) + $expense['amount'];
}

// Get user's budget settings
$dailyBudget = 500;
$weeklyBudget = 3000;
$monthlyBudget = 10000;

try {
    $budget_query = $conn->prepare("SELECT daily_budget, weekly_budget, monthly_budget FROM users WHERE id = ?");
    if ($budget_query) {
        $budget_query->bind_param("i", $user_id);
        $budget_query->execute();
        $budget_result = $budget_query->get_result();
        
        if ($budget_result->num_rows > 0) {
            $budget_data = $budget_result->fetch_assoc();
            $dailyBudget = $budget_data['daily_budget'] ?? 500;
            $weeklyBudget = $budget_data['weekly_budget'] ?? 3000;
            $monthlyBudget = $budget_data['monthly_budget'] ?? 10000;
        }
        $budget_query->close();
    }
} catch (Exception $e) {
    // Use defaults
}

// Build context for Gemini
$contextText = "User Financial Profile:\n";
$contextText .= "- Name: {$user['fullname']}\n";
$contextText .= "- Total Transactions: " . count($expenses) . "\n";
$contextText .= "- Total Spending: ₱" . number_format($totalSpending, 2) . "\n";
$contextText .= "- Daily Budget: ₱" . number_format($dailyBudget, 2) . "\n";
$contextText .= "- Weekly Budget: ₱" . number_format($weeklyBudget, 2) . "\n";
$contextText .= "- Monthly Budget: ₱" . number_format($monthlyBudget, 2) . "\n\n";

if (!empty($categoryBreakdown)) {
    $contextText .= "Spending by Category:\n";
    arsort($categoryBreakdown);
    foreach ($categoryBreakdown as $cat => $amount) {
        $contextText .= "- {$cat}: ₱" . number_format($amount, 2) . "\n";
    }
}

// Call Gemini API with your actual key
$geminiApiKey = "AIzaSyC22AURgDm09tvMMxzh1Egct_V_F6LHRlk";
$geminiUrl = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent?key=" . $geminiApiKey;

$systemPrompt = "You are FinAI, a helpful and friendly financial assistant for a personal expense tracking app called FinTrack. Your role is to help users understand their spending habits, provide budgeting advice, and answer financial questions. Always be encouraging, specific with numbers when relevant, and format responses with HTML formatting (use <br> for line breaks, <strong> for emphasis). Keep responses concise but helpful. Use emojis sparingly for visual appeal.";

$requestData = [
    'contents' => [
        [
            'parts' => [
                [
                    'text' => $systemPrompt . "\n\n" . $contextText . "\n\nUser Question: " . $userMessage
                ]
            ]
        ]
    ],
        'generationConfig' => [
            'temperature' => 0.7,
            'topK' => 40,
            'topP' => 0.95,
            'maxOutputTokens' => 4096,  // ← Doubled for longer responses
        ],
    'safetySettings' => [
        [
            'category' => 'HARM_CATEGORY_HARASSMENT',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ],
        [
            'category' => 'HARM_CATEGORY_HATE_SPEECH',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ],
        [
            'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ],
        [
            'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
            'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
        ]
    ]
];

$ch = curl_init($geminiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

// Enhanced error handling
if ($curlError) {
    echo json_encode([
        'success' => false,
        'message' => 'Connection error: ' . $curlError
    ]);
    exit;
}

if ($httpCode !== 200) {
    $errorData = json_decode($response, true);
    $errorMessage = 'API Error';
    
    if (isset($errorData['error']['message'])) {
        $errorMessage = $errorData['error']['message'];
    }
    
    echo json_encode([
        'success' => false,
        'message' => 'Failed to connect to AI service',
        'details' => $errorMessage,
        'http_code' => $httpCode
    ]);
    exit;
}

$responseData = json_decode($response, true);

if (isset($responseData['candidates'][0]['content']['parts'][0]['text'])) {
    $aiResponse = $responseData['candidates'][0]['content']['parts'][0]['text'];
    
    // Log conversation to database
    $log_stmt = $conn->prepare("INSERT INTO chat_history (user_id, user_message, ai_response, created_at) VALUES (?, ?, ?, NOW())");
    if ($log_stmt) {
        $log_stmt->bind_param("iss", $user_id, $userMessage, $aiResponse);
        $log_stmt->execute();
        $log_stmt->close();
    }
    
    echo json_encode([
        'success' => true,
        'response' => $aiResponse
    ]);
} else {
    // Handle blocked or empty responses
    $finishReason = $responseData['candidates'][0]['finishReason'] ?? 'UNKNOWN';
    
    if ($finishReason === 'SAFETY') {
        $aiResponse = "I apologize, but I cannot provide a response to that query due to safety guidelines. Please ask me about your finances, budgeting, or spending habits instead! 💰";
    } else {
        $aiResponse = "I'm having trouble generating a response right now. Please try rephrasing your question or ask me something about your expenses! 📊";
    }
    
    echo json_encode([
        'success' => true,
        'response' => $aiResponse
    ]);
}
?>