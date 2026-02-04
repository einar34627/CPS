<?php
// generate_api_key.php - Secure this file!
session_start();
require_once '../../config/db_connection.php';

// Only allow admins
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    die("Access denied");
}

// Generate a secure API key
function generateApiKey($length = 32) {
    return bin2hex(random_bytes($length));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $client_name = $_POST['client_name'] ?? '';
    $contact_email = $_POST['contact_email'] ?? '';
    $rate_limit = intval($_POST['rate_limit'] ?? 100);
    
    if (empty($client_name)) {
        die("Client name is required");
    }
    
    // Generate unique API key
    $api_key = 'GPS_' . strtoupper(substr(md5(uniqid() . microtime()), 0, 20)) . '_' . date('Y');
    
    // Save to database (create api_keys table first)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO api_keys (api_key, client_name, contact_email, rate_limit_per_hour) 
            VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([$api_key, $client_name, $contact_email, $rate_limit]);
        
        echo "<h3>API Key Generated Successfully!</h3>";
        echo "<p><strong>API Key:</strong> <code>$api_key</code></p>";
        echo "<p><strong>Client:</strong> $client_name</p>";
        echo "<p><strong>Rate Limit:</strong> $rate_limit requests/hour</p>";
        echo "<p><strong>Warning:</strong> Save this key now. It won't be shown again!</p>";
        
    } catch (PDOException $e) {
        echo "Error: " . $e->getMessage();
    }
    
    exit();
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Generate API Key</title>
    <style>
        body { font-family: Arial; max-width: 500px; margin: 50px auto; }
        input, textarea { width: 100%; padding: 8px; margin: 5px 0 15px; }
        button { background: #007bff; color: white; padding: 10px 20px; border: none; cursor: pointer; }
    </style>
</head>
<body>
    <h2>Generate New API Key</h2>
    <form method="POST">
        <label>Client/System Name:</label>
        <input type="text" name="client_name" required>
        
        <label>Contact Email:</label>
        <input type="email" name="contact_email">
        
        <label>Rate Limit (requests per hour):</label>
        <input type="number" name="rate_limit" value="100" min="1" max="10000">
        
        <button type="submit">Generate API Key</button>
    </form>
</body>
</html>