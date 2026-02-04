<?php
session_start();
require_once '../../config/db_connection.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');

// Handle preflight requests for CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ============================================
// API KEY AUTHENTICATION SYSTEM
// ============================================

// Get API key from multiple possible sources
$api_key = '';

// 1. Check Authorization header (Bearer token)
if (isset($_SERVER['HTTP_AUTHORIZATION'])) {
    $auth_header = $_SERVER['HTTP_AUTHORIZATION'];
    if (strpos($auth_header, 'Bearer ') === 0) {
        $api_key = substr($auth_header, 7);
    } else {
        $api_key = $auth_header;
    }
}
// 2. Check X-API-Key header
elseif (isset($_SERVER['HTTP_X_API_KEY'])) {
    $api_key = $_SERVER['HTTP_X_API_KEY'];
}
// 3. Check query parameter
elseif (isset($_GET['api_key'])) {
    $api_key = $_GET['api_key'];
}
// 4. Check POST parameter (if using POST)
elseif (isset($_POST['api_key'])) {
    $api_key = $_POST['api_key'];
}

// Define valid API keys (CHANGE THESE FOR PRODUCTION!)
$valid_api_keys = [
    'GPS_SYSTEM_2024_KEY_ABC123XYZ' => [
        'name' => 'Main Command System',
        'rate_limit' => 1000,
        'enabled' => true
    ],
    'MOBILE_APP_KEY_DEF456UVW' => [
        'name' => 'Mobile Application',
        'rate_limit' => 500,
        'enabled' => true
    ],
    'EXTERNAL_DASHBOARD_KEY_GHI789RST' => [
        'name' => 'External Dashboard',
        'rate_limit' => 200,
        'enabled' => true
    ],
    'TEST_KEY_123' => [  // For testing only
        'name' => 'Test System',
        'rate_limit' => 100,
        'enabled' => true
    ]
];

// Function to validate API key
function validateApiKey($key, $valid_keys) {
    if (empty($key)) {
        return false;
    }
    
    // Check if key exists and is enabled
    if (isset($valid_keys[$key]) && $valid_keys[$key]['enabled']) {
        return $valid_keys[$key];
    }
    
    return false;
}

// Validate the API key
$key_info = validateApiKey($api_key, $valid_api_keys);

// If API key is valid, skip session check
if ($key_info) {
    // API key is valid - allow access
    $client_name = $key_info['name'];
    
    // Simple rate limiting (in-memory, for production use database)
    $rate_limit_key = 'api_rate_' . md5($api_key);
    if (!isset($_SESSION[$rate_limit_key])) {
        $_SESSION[$rate_limit_key] = [
            'count' => 1,
            'timestamp' => time()
        ];
    } else {
        $rate_data = $_SESSION[$rate_limit_key];
        
        // Reset counter if more than an hour has passed
        if (time() - $rate_data['timestamp'] > 3600) {
            $_SESSION[$rate_limit_key] = [
                'count' => 1,
                'timestamp' => time()
            ];
        } 
        // Check if rate limit exceeded
        elseif ($rate_data['count'] >= $key_info['rate_limit']) {
            http_response_code(429);
            echo json_encode([
                'success' => false,
                'error' => 'Rate limit exceeded. Maximum ' . $key_info['rate_limit'] . ' requests per hour.'
            ]);
            exit();
        } 
        // Increment counter
        else {
            $_SESSION[$rate_limit_key]['count']++;
        }
    }
}
// If no valid API key, check for session authentication
else {
    session_start();
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'error' => 'Unauthorized. Provide a valid API key or login.',
            'api_key_help' => 'Add ?api_key=TEST_KEY_123 to URL or use Authorization header'
        ]);
        exit();
    }
}

// ============================================
// MAIN DATA FETCHING LOGIC (UPDATED FOR YOUR SCHEMA)
// ============================================

date_default_timezone_set('Asia/Manila');

try {
    // UPDATED QUERY - Using correct column names from your gps_units table
    $stmt = $pdo->query("
        SELECT 
            unit_id as id,
            callsign,
            assignment,
            latitude as lat,
            longitude as lng,
            status,
            -- Removed: speed (column doesn't exist)
            -- Removed: battery (column doesn't exist)
            -- Removed: distance_today (column doesn't exist)
            last_ping,
            TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping,
            assignment_area,
            unit_type,
            duration
        FROM gps_units 
        WHERE is_active = 1
        ORDER BY callsign
    ");
    $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format last_ping as ISO string
    foreach ($units as &$unit) {
        if (!empty($unit['last_ping'])) {
            $unit['last_ping'] = date(DATE_ATOM, strtotime($unit['last_ping']));
        } else {
            $unit['last_ping'] = null;
        }
        
        // Add placeholder values for missing columns (for backward compatibility)
        $unit['speed'] = 0; // Default value since column doesn't exist
        $unit['battery'] = 100; // Default value since column doesn't exist
        $unit['distance_today'] = 0; // Default value since column doesn't exist
    }
    
    // Additional statistics based on status
    $onPatrol = count(array_filter($units, fn($u) => $u['status'] === 'On Patrol'));
    $responding = count(array_filter($units, fn($u) => $u['status'] === 'Responding'));
    $stationary = count(array_filter($units, fn($u) => $u['status'] === 'Stationary'));
    $alerts = count(array_filter($units, fn($u) => $u['status'] === 'Needs Assistance'));
    
    // Total and offline devices
    $totalDevices = 0;
    $offlineDevices = 0;
    try {
        $totalStmt = $pdo->query("SELECT COUNT(*) AS total FROM gps_units");
        $totalRow = $totalStmt->fetch(PDO::FETCH_ASSOC);
        $totalDevices = intval($totalRow['total'] ?? 0);
        
        $offlineStmt = $pdo->query("SELECT COUNT(*) AS offline FROM gps_units WHERE is_active = 0");
        $offlineRow = $offlineStmt->fetch(PDO::FETCH_ASSOC);
        $offlineDevices = intval($offlineRow['offline'] ?? 0);
    } catch (\Throwable $e) {
        $totalDevices = count($units);
        $offlineDevices = 0;
    }
    
    // Calculate statistics
    $stats = [
        'total_devices' => $totalDevices,
        'active' => $onPatrol + $responding,
        'active_devices' => $onPatrol + $responding,
        'offline_devices' => $offlineDevices,
        'on_patrol' => $onPatrol,
        'responding' => $responding,
        'stationary' => $stationary,
        'alerts' => $alerts,
        'timestamp' => date(DATE_ATOM),
        'timezone' => 'Asia/Manila',
        'units_online' => count($units)
    ];
    
    // Add API info if accessed via API key
    $response = [
        'success' => true,
        'units' => $units,
        'stats' => $stats,
        'schema_info' => [
            'note' => 'speed, battery, distance_today columns are placeholders (not in database)',
            'actual_columns' => ['unit_id', 'callsign', 'assignment', 'latitude', 'longitude', 'status', 'last_ping', 'assignment_area', 'unit_type', 'duration']
        ]
    ];
    
    if (isset($client_name)) {
        $response['api_client'] = $client_name;
        if (isset($_SESSION[$rate_limit_key])) {
            $response['rate_limit_remaining'] = $key_info['rate_limit'] - $_SESSION[$rate_limit_key]['count'];
        }
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error',
        'message' => $e->getMessage(),
        'sql_state' => $e->getCode()
    ], JSON_UNESCAPED_UNICODE);
}
?>