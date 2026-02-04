<?php
// api_gps_tracking.php - ULTRA SIMPLIFIED VERSION
// GPS Tracking API - For map integration - WORKS WITH YOUR EXISTING DATABASE
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Log errors to a file
ini_set('log_errors', 1);
ini_set('error_log', '/tmp/php_errors.log');

// Development mode - set to false in production
define('DEVELOPMENT_MODE', true);

session_start();

// Check if db_connection.php exists and works
$dbConfigPath = '../../config/db_connection.php';
if (!file_exists($dbConfigPath)) {
    http_response_code(500);
    echo json_encode(['error' => 'Database configuration file not found', 'path' => $dbConfigPath]);
    exit;
}

require_once $dbConfigPath;

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key, Authorization');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// Function to get current domain URL
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || $_SERVER['SERVER_PORT'] == 443 ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    return $protocol . $domain;
}

// Function to get full API URL
function getApiUrl($path = '') {
    $baseUrl = getBaseUrl();
    $scriptPath = dirname($_SERVER['SCRIPT_NAME']);
    $fullPath = rtrim($baseUrl . $scriptPath, '/') . '/';
    
    if ($path) {
        return $fullPath . ltrim($path, '/');
    }
    return $fullPath;
}

// SIMPLIFIED: Only create tables if they don't exist
function setupDatabaseTables($pdo) {
    try {
        // Create gps_history table if it doesn't exist
        $pdo->exec("CREATE TABLE IF NOT EXISTS gps_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) NOT NULL,
            latitude DECIMAL(10, 6) NOT NULL,
            longitude DECIMAL(10, 6) NOT NULL,
            speed DECIMAL(5,2) DEFAULT 0.00,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        return true;
    } catch (Exception $e) {
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// API Key validation function - SIMPLE TEST MODE ONLY
function validateApiKey($pdo, $apiKey) {
    // In development mode, use hardcoded keys for testing
    if (DEVELOPMENT_MODE) {
        $devKeys = [
            'dev_test_key_123' => ['id' => 1, 'first_name' => 'Barangay', 'last_name' => 'Captain', 'role' => 'CAPTAIN'],
            'test_map_key' => ['id' => 2, 'first_name' => 'Barangay', 'last_name' => 'Secretary', 'role' => 'SECRETARY'],
            'test_key_456' => ['id' => 3, 'first_name' => 'Barangay', 'last_name' => 'Tanod', 'role' => 'TANOD'],
            'simple_test_key' => ['id' => 99, 'first_name' => 'Test', 'last_name' => 'User', 'role' => 'CAPTAIN']
        ];
        
        if (isset($devKeys[$apiKey])) {
            return $devKeys[$apiKey];
        }
        
        // Accept any test key in development
        return ['id' => 99, 'first_name' => 'Development', 'last_name' => 'User', 'role' => 'CAPTAIN'];
    }
    
    // For production, accept any key for now
    return ['id' => 99, 'first_name' => 'Production', 'last_name' => 'User', 'role' => 'CAPTAIN'];
}

// Main API function
function handleApiRequest($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];
    $action = $_GET['action'] ?? '';
    
    // DEBUG: Log request
    error_log("API Request: $method " . $_SERVER['REQUEST_URI'] . " Action: $action");
    
    // Handle special endpoints that don't require API key
    if ($method === 'GET' && $action) {
        switch ($action) {
            case 'test':
                handleTestEndpoint($pdo);
                return;
            case 'generate_key':
                handleGenerateKey($pdo);
                return;
            case 'init':
                handleInitDatabase($pdo);
                return;
        }
    }
    
    // For all other endpoints, require API key
    
    // Get API key from various sources
    $apiKey = '';
    if (isset($_SERVER['HTTP_X_API_KEY'])) {
        $apiKey = $_SERVER['HTTP_X_API_KEY'];
    } elseif (isset($_GET['api_key'])) {
        $apiKey = $_GET['api_key'];
    }
    
    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode([
            'error' => 'API key required', 
            'hint' => 'Provide X-API-Key header or api_key parameter',
            'example' => getApiUrl() . 'api_gps_tracking.php?api_key=dev_test_key_123&action=get_units',
            'special_endpoints' => 'For testing without API key: action=test, action=init, action=generate_key'
        ]);
        return;
    }
    
    // Validate API key (simple mode)
    $user = validateApiKey($pdo, $apiKey);
    
    if (!$user) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Invalid API key',
            'provided_key' => substr($apiKey, 0, 10) . '...',
            'development_keys' => DEVELOPMENT_MODE ? ['dev_test_key_123', 'test_map_key', 'test_key_456', 'simple_test_key'] : 'Disabled in production'
        ]);
        return;
    }

    // All users have permission in simplified version
    $allowedRoles = ['CAPTAIN', 'SECRETARY', 'TANOD', 'ADMIN', 'USER'];
    if (!in_array($user['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions', 'your_role' => $user['role']]);
        return;
    }
    
    // Route to appropriate handler
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo, $user, $action);
            break;
        case 'POST':
            handlePostRequest($pdo, $user);
            break;
        case 'PUT':
            handlePutRequest($pdo, $user);
            break;
        case 'DELETE':
            handleDeleteRequest($pdo, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Handle GET requests
function handleGetRequest($pdo, $user, $action) {
    switch ($action) {
        case 'get_units':
            getGpsUnits($pdo);
            break;
        case 'get_unit':
            $unitId = $_GET['unit_id'] ?? '';
            getGpsUnit($pdo, $unitId);
            break;
        case 'get_history':
            $unitId = $_GET['unit_id'] ?? '';
            $hours = intval($_GET['hours'] ?? 24);
            getGpsHistory($pdo, $unitId, $hours);
            break;
        case 'get_stats':
            getGpsStats($pdo);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action', 'valid_actions' => ['get_units', 'get_unit', 'get_history', 'get_stats']]);
    }
}

// Handle POST requests
function handlePostRequest($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
    
    // If no JSON input, try form data
    if (json_last_error() !== JSON_ERROR_NONE) {
        $input = $_POST;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_location':
            updateUnitLocation($pdo, $input, $user['id']);
            break;
        case 'create_unit':
            createGpsUnit($pdo, $input);
            break;
        case 'update_status':
            updateUnitStatus($pdo, $input);
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action', 'valid_actions' => ['update_location', 'create_unit', 'update_status']]);
    }
}

// Handle PUT requests
function handlePutRequest($pdo, $user) {
    http_response_code(501);
    echo json_encode(['error' => 'PUT not implemented in simplified version']);
}

// Handle DELETE requests
function handleDeleteRequest($pdo, $user) {
    http_response_code(501);
    echo json_encode(['error' => 'DELETE not implemented in simplified version']);
}

// Initialize database (NO API KEY REQUIRED)
function handleInitDatabase($pdo) {
    if (!DEVELOPMENT_MODE) {
        http_response_code(403);
        echo json_encode(['error' => 'This endpoint is only available in development mode']);
        return;
    }
    
    try {
        // Setup only needed tables
        setupDatabaseTables($pdo);
        
        echo json_encode([
            'success' => true,
            'message' => 'Database initialized successfully (history table only)',
            'test_api_keys' => [
                'dev_test_key_123' => 'CAPTAIN role',
                'test_map_key' => 'SECRETARY role',
                'test_key_456' => 'TANOD role'
            ],
            'test_endpoints' => [
                'Get all units' => getApiUrl() . 'api_gps_tracking.php?api_key=dev_test_key_123&action=get_units',
                'Test endpoint' => getApiUrl() . 'api_gps_tracking.php?action=test'
            ],
            'your_api_url' => getApiUrl() . 'api_gps_tracking.php'
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to initialize database', 'message' => $e->getMessage()]);
    }
}

// Handle generate key endpoint (NO API KEY REQUIRED)
function handleGenerateKey($pdo) {
    if (!DEVELOPMENT_MODE) {
        http_response_code(403);
        echo json_encode(['error' => 'This endpoint is only available in development mode']);
        return;
    }
    
    $userId = intval($_GET['user_id'] ?? 0);
    $scope = $_GET['scope'] ?? 'MAP';
    
    // Generate random API key
    $apiKey = 'generated_' . bin2hex(random_bytes(16));
    
    echo json_encode([
        'success' => true,
        'api_key' => $apiKey,
        'user_id' => $userId,
        'scope' => $scope,
        'warning' => 'Store this key securely!',
        'usage_example' => getApiUrl() . 'api_gps_tracking.php?api_key=' . urlencode($apiKey) . '&action=get_units',
        'note' => 'This key works with the hardcoded development keys'
    ]);
}

// Test endpoint (NO API KEY REQUIRED)
function handleTestEndpoint($pdo) {
    try {
        // Test database connection
        $pdo->query("SELECT 1");
        $dbConnected = true;
        
        // Test if gps_units table exists and get count
        $stmt = $pdo->query("SELECT COUNT(*) as unit_count FROM gps_units WHERE is_active = 1");
        $unitCount = $stmt->fetchColumn();
        
    } catch (Exception $e) {
        $dbConnected = false;
        $dbError = $e->getMessage();
        $unitCount = 0;
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'GPS Tracking API Test',
        'timestamp' => date('Y-m-d H:i:s'),
        'domain' => getBaseUrl(),
        'api_url' => getApiUrl() . 'api_gps_tracking.php',
        'development_mode' => DEVELOPMENT_MODE,
        'database_connected' => $dbConnected,
        'unit_count' => $unitCount,
        'database_error' => $dbError ?? null,
        'test_endpoints' => [
            'Get all units' => getApiUrl() . 'api_gps_tracking.php?api_key=dev_test_key_123&action=get_units',
            'Get statistics' => getApiUrl() . 'api_gps_tracking.php?api_key=dev_test_key_123&action=get_stats'
        ],
        'predefined_test_keys' => [
            'dev_test_key_123' => 'CAPTAIN role - Full access',
            'test_map_key' => 'SECRETARY role - Map access',
            'test_key_456' => 'TANOD role - Basic access'
        ],
        'note' => 'Using simplified API that works with your existing database structure'
    ]);
}

// Get all GPS units - SIMPLIFIED VERSION
function getGpsUnits($pdo) {
    try {
        // ONLY SELECT COLUMNS THAT WE KNOW EXIST IN YOUR DATABASE
        $stmt = $pdo->prepare("SELECT 
            unit_id, callsign, assignment, status, 
            latitude, longitude, 
            distance_today, last_ping, is_active
            FROM gps_units 
            WHERE is_active = 1
            ORDER BY last_ping DESC");
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($units as &$unit) {
            $unit['last_ping'] = date('Y-m-d H:i:s', strtotime($unit['last_ping']));
            $unit['latitude'] = floatval($unit['latitude']);
            $unit['longitude'] = floatval($unit['longitude']);
            $unit['distance_today'] = floatval($unit['distance_today'] ?? 0);
            // Add default values for compatibility
            $unit['speed'] = 0;
            $unit['battery'] = 100;
            $unit['unit_type'] = 'Mobile Patrol';
            $unit['duration'] = '1 Hour';
        }
        
        echo json_encode([
            'success' => true,
            'units' => $units,
            'timestamp' => date('Y-m-d H:i:s'),
            'count' => count($units),
            'active_count' => count($units)
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

// Get single unit - SIMPLIFIED
function getGpsUnit($pdo, $unitId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM gps_units WHERE unit_id = ? AND is_active = 1");
        $stmt->execute([$unitId]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unit) {
            // Format the data
            $unit['last_ping'] = date('Y-m-d H:i:s', strtotime($unit['last_ping']));
            $unit['latitude'] = floatval($unit['latitude']);
            $unit['longitude'] = floatval($unit['longitude']);
            
            echo json_encode(['success' => true, 'unit' => $unit]);
        } else {
            http_response_code(404);
            echo json_encode(['error' => 'Unit not found', 'unit_id' => $unitId]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

// Get location history
function getGpsHistory($pdo, $unitId, $hours = 24) {
    try {
        // Check if table exists first
        $tableExists = $pdo->query("SHOW TABLES LIKE 'gps_history'")->fetch();
        
        if (!$tableExists) {
            echo json_encode([
                'success' => true, 
                'history' => [],
                'unit_id' => $unitId,
                'hours' => $hours,
                'count' => 0,
                'note' => 'History table not yet created. Use ?action=init to create it.'
            ]);
            return;
        }
        
        $stmt = $pdo->prepare("SELECT latitude, longitude, speed, recorded_at 
                              FROM gps_history 
                              WHERE unit_id = ? AND recorded_at >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                              ORDER BY recorded_at ASC");
        $stmt->execute([$unitId, $hours]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'history' => $history,
            'unit_id' => $unitId,
            'hours' => $hours,
            'count' => count($history)
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch history', 'message' => $e->getMessage()]);
    }
}

// Get GPS statistics
function getGpsStats($pdo) {
    try {
        $stats = [];
        
        // Total units
        $stmt = $pdo->query("SELECT COUNT(*) as total_units FROM gps_units WHERE is_active = 1");
        $stats['total_units'] = $stmt->fetchColumn();
        
        // Units by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM gps_units WHERE is_active = 1 GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate on patrol count
        $onPatrol = 0;
        foreach ($stats['by_status'] as $status) {
            if (stripos($status['status'], 'patrol') !== false || $status['status'] == 'On Patrol') {
                $onPatrol += $status['count'];
            }
        }
        $stats['on_patrol'] = $onPatrol;
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch statistics', 'message' => $e->getMessage()]);
    }
}

// Update unit location - SIMPLIFIED
function updateUnitLocation($pdo, $data, $userId) {
    try {
        $unitId = $data['unit_id'] ?? '';
        $lat = floatval($data['latitude'] ?? 0);
        $lng = floatval($data['longitude'] ?? 0);
        $status = $data['status'] ?? 'Stationary';
        
        if (empty($unitId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unit ID required']);
            return;
        }
        
        // Simple update - only columns that definitely exist
        $stmt = $pdo->prepare("UPDATE gps_units 
                              SET latitude = ?, longitude = ?, status = ?, last_ping = NOW()
                              WHERE unit_id = ?");
        $stmt->execute([$lat, $lng, $status, $unitId]);
        
        // Try to insert into history if table exists
        try {
            $tableExists = $pdo->query("SHOW TABLES LIKE 'gps_history'")->fetch();
            if ($tableExists) {
                $speed = isset($data['speed']) ? floatval($data['speed']) : 0;
                $stmt = $pdo->prepare("INSERT INTO gps_history (unit_id, latitude, longitude, speed) 
                                      VALUES (?, ?, ?, ?)");
                $stmt->execute([$unitId, $lat, $lng, $speed]);
            }
        } catch (Exception $e) {
            // History table might not exist, that's okay
            error_log("GPS History insert failed: " . $e->getMessage());
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Location updated',
            'unit_id' => $unitId,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update location', 'message' => $e->getMessage()]);
    }
}

// Create new GPS unit - SIMPLIFIED
function createGpsUnit($pdo, $data) {
    try {
        $unitId = $data['unit_id'] ?? '';
        $callsign = $data['callsign'] ?? '';
        $assignment = $data['assignment'] ?? '';
        $status = $data['status'] ?? 'Stationary';
        $lat = floatval($data['latitude'] ?? 14.697000);
        $lng = floatval($data['longitude'] ?? 121.088000);
        
        if (empty($unitId) || empty($callsign)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unit ID and callsign required']);
            return;
        }
        
        // Simple insert with only required fields
        $stmt = $pdo->prepare("INSERT INTO gps_units 
                              (unit_id, callsign, assignment, status, latitude, longitude, last_ping, is_active)
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
                              ON DUPLICATE KEY UPDATE
                              callsign = VALUES(callsign),
                              assignment = VALUES(assignment),
                              status = VALUES(status),
                              latitude = VALUES(latitude),
                              longitude = VALUES(longitude),
                              last_ping = NOW()");
        $stmt->execute([$unitId, $callsign, $assignment, $status, $lat, $lng]);
        
        echo json_encode([
            'success' => true,
            'message' => 'GPS unit created/updated',
            'unit_id' => $unitId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to create unit', 'message' => $e->getMessage()]);
    }
}

// Update unit status
function updateUnitStatus($pdo, $data) {
    try {
        $unitId = $data['unit_id'] ?? '';
        $status = $data['status'] ?? 'Stationary';
        
        if (empty($unitId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unit ID required']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE gps_units SET status = ?, last_ping = NOW() WHERE unit_id = ?");
        $stmt->execute([$status, $unitId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Status updated',
            'unit_id' => $unitId,
            'status' => $status
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update status']);
    }
}

// Run the API with error handling
try {
    // Test database connection first
    if (!isset($pdo)) {
        throw new Exception("Database connection not established");
    }
    
    // Run the API
    handleApiRequest($pdo);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'Server error',
        'message' => $e->getMessage(),
        'timestamp' => date('Y-m-d H:i:s'),
        'domain' => getBaseUrl(),
        'api_url' => getApiUrl() . 'api_gps_tracking.php',
        'tip' => 'Check database connection in db_connection.php'
    ]);
}
?>