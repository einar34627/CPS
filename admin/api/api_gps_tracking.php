<?php
// api_gps_tracking.php
// GPS Tracking API - For map integration
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

// SIMPLIFIED DATABASE SETUP - No complex schema migrations
function setupDatabaseTables($pdo) {
    try {
        // Drop and recreate all tables to ensure clean state
        $tables = ['gps_history', 'gps_units', 'api_keys', 'users'];
        
        foreach ($tables as $table) {
            try {
                $pdo->exec("DROP TABLE IF EXISTS $table");
            } catch (Exception $e) {
                // Ignore errors if table doesn't exist
            }
        }
        
        // 1. Create users table
        $pdo->exec("CREATE TABLE users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            first_name VARCHAR(100) DEFAULT '',
            last_name VARCHAR(100) DEFAULT '',
            username VARCHAR(50) UNIQUE,
            email VARCHAR(100) UNIQUE,
            role VARCHAR(50) DEFAULT 'USER',
            status ENUM('active', 'inactive') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_role (role)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 2. Create api_keys table (SIMPLIFIED - only needed columns)
        $pdo->exec("CREATE TABLE api_keys (
            id INT AUTO_INCREMENT PRIMARY KEY,
            user_id INT NOT NULL,
            api_key VARCHAR(255) NOT NULL UNIQUE,
            scope VARCHAR(32) DEFAULT 'MAP',
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            expires_at TIMESTAMP NULL DEFAULT NULL,
            INDEX idx_api_key (api_key),
            INDEX idx_user_id (user_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 3. Create gps_units table
        $pdo->exec("CREATE TABLE gps_units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            callsign VARCHAR(50) NOT NULL,
            assignment VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'Stationary',
            latitude DECIMAL(10,6) DEFAULT 14.697000,
            longitude DECIMAL(10,6) DEFAULT 121.088000,
            speed DECIMAL(5,2) DEFAULT 0.00,
            battery INT DEFAULT 100,
            distance_today DECIMAL(8,2) DEFAULT 0.00,
            last_ping TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            is_active TINYINT(1) DEFAULT 1,
            created_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unit_id (unit_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        // 4. Create gps_history table
        $pdo->exec("CREATE TABLE gps_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) NOT NULL,
            latitude DECIMAL(10, 6) NOT NULL,
            longitude DECIMAL(10, 6) NOT NULL,
            speed DECIMAL(5,2) DEFAULT 0.00,
            recorded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unit_time (unit_id, recorded_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
        
        return true;
    } catch (Exception $e) {
        error_log("Database setup error: " . $e->getMessage());
        return false;
    }
}

// API Key validation function (SIMPLIFIED VERSION)
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
    
    // For production, check database
    try {
        $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.role 
                               FROM api_keys ak
                               JOIN users u ON ak.user_id = u.id
                               WHERE ak.api_key = ? 
                               AND ak.is_active = 1
                               AND (ak.expires_at IS NULL OR ak.expires_at > NOW())");
        $stmt->execute([$apiKey]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log("API key validation error: " . $e->getMessage());
        return false;
    }
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
            'example' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?api_key=dev_test_key_123&action=get_units',
            'special_endpoints' => 'For testing without API key: action=test, action=init, action=generate_key'
        ]);
        return;
    }
    
    // Validate API key
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

    // Check if user has permission for GPS tracking
    $allowedRoles = ['CAPTAIN', 'SECRETARY', 'TANOD', 'ADMIN'];
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
    $input = json_decode(file_get_contents('php://input'), true);
    $unitId = $_GET['unit_id'] ?? $input['unit_id'] ?? '';
    
    if (empty($unitId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unit ID required']);
        return;
    }
    
    updateGpsUnit($pdo, $unitId, $input);
}

// Handle DELETE requests
function handleDeleteRequest($pdo, $user) {
    $unitId = $_GET['unit_id'] ?? '';
    
    if (empty($unitId)) {
        http_response_code(400);
        echo json_encode(['error' => 'Unit ID required']);
        return;
    }
    
    deleteGpsUnit($pdo, $unitId);
}

// Initialize database (NO API KEY REQUIRED)
function handleInitDatabase($pdo) {
    if (!DEVELOPMENT_MODE) {
        http_response_code(403);
        echo json_encode(['error' => 'This endpoint is only available in development mode']);
        return;
    }
    
    try {
        // Setup all tables
        setupDatabaseTables($pdo);
        
        // Insert test users
        $testUsers = [
            ['Barangay', 'Captain', 'captain_test', 'captain@example.com', 'CAPTAIN'],
            ['Barangay', 'Secretary', 'secretary_test', 'secretary@example.com', 'SECRETARY'],
            ['Barangay', 'Tanod', 'tanod_test', 'tanod@example.com', 'TANOD']
        ];
        
        foreach ($testUsers as $user) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO users (first_name, last_name, username, email, role) 
                                   VALUES (?, ?, ?, ?, ?)");
            $stmt->execute($user);
        }
        
        // Insert test API keys
        $testApiKeys = [
            [1, 'dev_test_key_123', 'MAP'],
            [2, 'test_map_key', 'MAP'],
            [3, 'test_key_456', 'MAP']
        ];
        
        foreach ($testApiKeys as $key) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO api_keys (user_id, api_key, scope) 
                                   VALUES (?, ?, ?)");
            $stmt->execute($key);
        }
        
        // Create sample GPS units
        $sampleUnits = [
            ['TANOD-001', 'Alpha Unit', 'Patrol Area 1', 'Patrolling', 14.6970, 121.0880, 25.5, 85],
            ['TANOD-002', 'Bravo Unit', 'Patrol Area 2', 'Stationary', 14.6980, 121.0890, 0, 90],
            ['TANOD-003', 'Charlie Unit', 'Barangay Hall', 'Responding', 14.6960, 121.0870, 40.2, 75],
            ['TANOD-004', 'Delta Unit', 'Market Area', 'Patrolling', 14.6950, 121.0900, 30.1, 80]
        ];
        
        foreach ($sampleUnits as $unit) {
            $stmt = $pdo->prepare("INSERT IGNORE INTO gps_units 
                                  (unit_id, callsign, assignment, status, latitude, longitude, speed, battery, last_ping, is_active)
                                  VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), 1)");
            $stmt->execute($unit);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Database initialized successfully',
            'test_api_keys' => [
                'dev_test_key_123' => 'CAPTAIN role',
                'test_map_key' => 'SECRETARY role',
                'test_key_456' => 'TANOD role'
            ],
            'test_endpoints' => [
                'Get all units' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?api_key=dev_test_key_123&action=get_units',
                'Test endpoint' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?action=test',
                'Generate new key' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?action=generate_key&user_id=1'
            ]
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to initialize database', 'message' => $e->getMessage()]);
    }
}

// Handle generate key endpoint (NO API KEY REQUIRED) - SIMPLIFIED VERSION
function handleGenerateKey($pdo) {
    if (!DEVELOPMENT_MODE) {
        http_response_code(403);
        echo json_encode(['error' => 'This endpoint is only available in development mode']);
        return;
    }
    
    $userId = intval($_GET['user_id'] ?? 0);
    $scope = $_GET['scope'] ?? 'MAP';
    
    try {
        // Generate random API key
        $apiKey = 'generated_' . bin2hex(random_bytes(16));
        
        // If no user_id provided or user doesn't exist, create a new user
        if ($userId === 0) {
            // Create a new user
            $timestamp = time();
            $random = bin2hex(random_bytes(4));
            $username = 'auto_user_' . $timestamp . '_' . $random;
            $email = 'auto_' . $timestamp . '_' . $random . '@example.com';
            
            $createUserStmt = $pdo->prepare("INSERT INTO users (first_name, last_name, username, email, role) 
                                            VALUES (?, ?, ?, ?, ?)");
            $createUserStmt->execute(['Auto', 'Generated', $username, $email, 'USER']);
            
            // Get the newly created user's ID
            $userId = $pdo->lastInsertId();
        } else {
            // Check if user exists
            $userStmt = $pdo->prepare("SELECT id FROM users WHERE id = ?");
            $userStmt->execute([$userId]);
            $userExists = $userStmt->fetch();
            
            if (!$userExists) {
                // Create a user with the specified ID if it doesn't exist
                $timestamp = time();
                $random = bin2hex(random_bytes(4));
                $username = 'user_' . $userId . '_' . $random;
                $email = 'user_' . $userId . '_' . $timestamp . '@example.com';
                
                $createUserStmt = $pdo->prepare("INSERT INTO users (id, first_name, last_name, username, email, role) 
                                                VALUES (?, ?, ?, ?, ?, ?)");
                $createUserStmt->execute([$userId, 'Manual', 'User ' . $userId, $username, $email, 'USER']);
            }
        }
        
        // Insert into api_keys table
        $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key, scope) 
                               VALUES (?, ?, ?)");
        $result = $stmt->execute([$userId, $apiKey, $scope]);
        
        if ($result) {
            echo json_encode([
                'success' => true,
                'api_key' => $apiKey,
                'user_id' => $userId,
                'scope' => $scope,
                'warning' => 'Store this key securely!',
                'usage_example' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?api_key=' . urlencode($apiKey) . '&action=get_units',
                'note' => 'This key works with the hardcoded development keys: dev_test_key_123, test_map_key, test_key_456'
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to generate API key', 'details' => $stmt->errorInfo()]);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to generate API key', 'message' => $e->getMessage()]);
    }
}

// Test endpoint (NO API KEY REQUIRED)
function handleTestEndpoint($pdo) {
    try {
        // Test database connection
        $pdo->query("SELECT 1");
        $dbConnected = true;
    } catch (Exception $e) {
        $dbConnected = false;
        $dbError = $e->getMessage();
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'GPS Tracking API Test',
        'timestamp' => date('Y-m-d H:i:s'),
        'development_mode' => DEVELOPMENT_MODE,
        'database_connected' => $dbConnected,
        'database_error' => $dbError ?? null,
        'endpoints' => [
            'Initialize Database (WILL DELETE ALL DATA)' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?action=init',
            'Generate API Key (no user_id = auto create)' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?action=generate_key',
            'Generate API Key for user_id=1' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?action=generate_key&user_id=1',
            'Test GPS tracking' => 'http://localhost/CPSA/admin/api/api_gps_tracking.php?api_key=dev_test_key_123&action=get_units'
        ],
        'predefined_test_keys' => [
            'dev_test_key_123' => 'CAPTAIN role - Full access',
            'test_map_key' => 'SECRETARY role - Map access',
            'test_key_456' => 'TANOD role - Basic access'
        ]
    ]);
}

// Get all GPS units
function getGpsUnits($pdo) {
    try {
        $stmt = $pdo->prepare("SELECT 
            unit_id, callsign, assignment, status, 
            latitude, longitude, speed, battery, 
            distance_today, last_ping, is_active
            FROM gps_units 
            ORDER BY last_ping DESC");
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format the data
        foreach ($units as &$unit) {
            $unit['last_ping'] = date('Y-m-d H:i:s', strtotime($unit['last_ping']));
            $unit['battery'] = intval($unit['battery']);
            $unit['speed'] = floatval($unit['speed']);
            $unit['distance_today'] = floatval($unit['distance_today']);
        }
        
        echo json_encode([
            'success' => true,
            'units' => $units,
            'timestamp' => date('Y-m-d H:i:s'),
            'count' => count($units),
            'active_count' => count(array_filter($units, function($unit) {
                return $unit['is_active'] == 1;
            }))
        ]);
        
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error', 'message' => $e->getMessage()]);
    }
}

// Get single unit
function getGpsUnit($pdo, $unitId) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM gps_units WHERE unit_id = ?");
        $stmt->execute([$unitId]);
        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($unit) {
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
        $stmt = $pdo->query("SELECT COUNT(*) as total_units FROM gps_units");
        $stats['total_units'] = $stmt->fetchColumn();
        
        // Active units
        $stmt = $pdo->query("SELECT COUNT(*) as active_units FROM gps_units WHERE is_active = 1");
        $stats['active_units'] = $stmt->fetchColumn();
        
        // Units by status
        $stmt = $pdo->query("SELECT status, COUNT(*) as count FROM gps_units WHERE is_active = 1 GROUP BY status");
        $stats['by_status'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to fetch statistics', 'message' => $e->getMessage()]);
    }
}

// Update unit location
function updateUnitLocation($pdo, $data, $userId) {
    try {
        $unitId = $data['unit_id'] ?? '';
        $lat = floatval($data['latitude'] ?? 0);
        $lng = floatval($data['longitude'] ?? 0);
        $speed = isset($data['speed']) ? floatval($data['speed']) : 0;
        $status = $data['status'] ?? 'Stationary';
        
        if (empty($unitId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unit ID required']);
            return;
        }
        
        $stmt = $pdo->prepare("UPDATE gps_units 
                              SET latitude = ?, longitude = ?, speed = ?, status = ?, last_ping = NOW()
                              WHERE unit_id = ?");
        $stmt->execute([$lat, $lng, $speed, $status, $unitId]);
        
        $stmt = $pdo->prepare("INSERT INTO gps_history (unit_id, latitude, longitude, speed) 
                              VALUES (?, ?, ?, ?)");
        $stmt->execute([$unitId, $lat, $lng, $speed]);
        
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

// Create new GPS unit
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
        
        $stmt = $pdo->prepare("INSERT INTO gps_units 
                              (unit_id, callsign, assignment, status, latitude, longitude, last_ping, is_active)
                              VALUES (?, ?, ?, ?, ?, ?, NOW(), 1)
                              ON DUPLICATE KEY UPDATE
                              callsign = VALUES(callsign),
                              assignment = VALUES(assignment),
                              status = VALUES(status)");
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

// Update GPS unit (PUT method)
function updateGpsUnit($pdo, $unitId, $data) {
    try {
        $fields = [];
        $params = [];
        
        $allowedFields = ['callsign', 'assignment', 'status', 'latitude', 'longitude', 'speed', 'battery', 'distance_today', 'is_active'];
        
        foreach ($allowedFields as $field) {
            if (isset($data[$field])) {
                $fields[] = "$field = ?";
                $params[] = $data[$field];
            }
        }
        
        if (empty($fields)) {
            http_response_code(400);
            echo json_encode(['error' => 'No fields to update']);
            return;
        }
        
        $params[] = $unitId;
        
        $query = "UPDATE gps_units SET " . implode(', ', $fields) . " WHERE unit_id = ?";
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        
        echo json_encode([
            'success' => true,
            'message' => 'Unit updated successfully',
            'unit_id' => $unitId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to update unit', 'message' => $e->getMessage()]);
    }
}

// Delete GPS unit
function deleteGpsUnit($pdo, $unitId) {
    try {
        $stmt = $pdo->prepare("UPDATE gps_units SET is_active = 0 WHERE unit_id = ?");
        $stmt->execute([$unitId]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Unit deactivated',
            'unit_id' => $unitId
        ]);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Failed to delete unit', 'message' => $e->getMessage()]);
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
        'tip' => 'Check database connection in db_connection.php'
    ]);
}
?>