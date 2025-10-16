<?php
// Create this file to check which models are available for your API key
// Save as: check_available_models.php in your project root
// Then visit it in your browser

$geminiApiKey = "AIzaSyC22AURgDm09tvMMxzh1Egct_V_F6LHRlk";

echo "<h2>Checking Available Gemini Models...</h2>";

// Try different API endpoints
$endpoints = [
    'v1' => "https://generativelanguage.googleapis.com/v1/models?key=" . $geminiApiKey,
    'v1beta' => "https://generativelanguage.googleapis.com/v1beta/models?key=" . $geminiApiKey
];

foreach ($endpoints as $version => $url) {
    echo "<h3>API Version: $version</h3>";
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);
    
    if ($curlError) {
        echo "<p style='color: red;'>Error: $curlError</p>";
        continue;
    }
    
    if ($httpCode !== 200) {
        echo "<p style='color: red;'>HTTP Code: $httpCode</p>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
        continue;
    }
    
    $data = json_decode($response, true);
    
    if (isset($data['models'])) {
        echo "<p style='color: green;'>✓ Found " . count($data['models']) . " models</p>";
        echo "<ul>";
        foreach ($data['models'] as $model) {
            $modelName = $model['name'];
            $supportedMethods = isset($model['supportedGenerationMethods']) 
                ? implode(', ', $model['supportedGenerationMethods']) 
                : 'N/A';
            
            // Highlight models that support generateContent
            if (strpos($supportedMethods, 'generateContent') !== false) {
                echo "<li style='color: green; font-weight: bold;'>";
                echo "✓ " . htmlspecialchars($modelName);
                echo " <small>(Methods: $supportedMethods)</small>";
                echo "</li>";
            } else {
                echo "<li style='color: gray;'>";
                echo htmlspecialchars($modelName);
                echo " <small>(Methods: $supportedMethods)</small>";
                echo "</li>";
            }
        }
        echo "</ul>";
    } else {
        echo "<pre>" . htmlspecialchars(print_r($data, true)) . "</pre>";
    }
    
    echo "<hr>";
}

echo "<h3>💡 Next Steps:</h3>";
echo "<p>1. Look for models in <strong style='color: green;'>green</strong> that support 'generateContent'</p>";
echo "<p>2. Copy the full model name (e.g., 'models/gemini-pro')</p>";
echo "<p>3. Use it in your chat_handler.php file</p>";
?>