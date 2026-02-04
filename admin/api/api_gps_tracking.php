<?php
// api_gps_tracking.php
// GPS Tracking API - For map integration

session_start();
require_once 'config/db_connection.php';

// Enable CORS for API access
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-API-Key');
header('Content-Type: application/json');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit(0);
}

// API Key validation function
function validateApiKey($pdo, $apiKey) {
    try {
        $stmt = $pdo->prepare("SELECT u.id, u.first_name, u.last_name, u.role 
                              FROM api_keys ak 
                              JOIN users u ON ak.user_id = u.id 
                              WHERE ak.api_key_hash = ?");
        $stmt->execute([$apiKey]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $row ? $row : false;
    } catch (Exception $e) {
        return false;
    }
}

// Main API function
function handleApiRequest($pdo) {
    $method = $_SERVER['REQUEST_METHOD'];
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';
    
    if (empty($apiKey)) {
        http_response_code(401);
        echo json_encode(['error' => 'API key required']);
        return;
    }
    
    // Validate API key
    $user = validateApiKey($pdo, $apiKey);
    if (!$user) {
        http_response_code(401);
        echo json_encode(['error' => 'Invalid API key']);
        return;
    }
    
    // Check if user has permission for GPS tracking
    $allowedRoles = ['CAPTAIN', 'SECRETARY', 'TANOD'];
    if (!in_array($user['role'], $allowedRoles)) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        return;
    }
    
    switch ($method) {
        case 'GET':
            handleGetRequest($pdo);
            break;
        case 'POST':
            handlePostRequest($pdo, $user);
            break;
        default:
            http_response_code(405);
            echo json_encode(['error' => 'Method not allowed']);
    }
}

// Handle GET requests
function handleGetRequest($pdo) {
    $action = $_GET['action'] ?? 'get_units';
    
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
        default:
            echo json_encode(['error' => 'Invalid action']);
    }
}

// Handle POST requests
function handlePostRequest($pdo, $user) {
    $input = json_decode(file_get_contents('php://input'), true);
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
            echo json_encode(['error' => 'Invalid action']);
    }
}

// Get all GPS units
function getGpsUnits($pdo) {
    try {
        // Check if gps_units table exists, create if not
        $pdo->exec("CREATE TABLE IF NOT EXISTS gps_units (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) UNIQUE NOT NULL,
            callsign VARCHAR(50) NOT NULL,
            assignment VARCHAR(255) DEFAULT NULL,
            status VARCHAR(50) DEFAULT 'Stationary',
            latitude DECIMAL(10, 6) DEFAULT 14.697000,
            longitude DECIMAL(10, 6) DEFAULT 121.088000,
            speed VARCHAR(20) DEFAULT '0 km/h',
            battery VARCHAR(20) DEFAULT '100%',
            distance_today VARCHAR(20) DEFAULT '0 km',
            last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unit_id (unit_id),
            INDEX idx_status (status)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("SELECT 
            unit_id, callsign, assignment, status, 
            latitude, longitude, speed, battery, 
            distance_today, last_update 
            FROM gps_units 
            WHERE last_update >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            ORDER BY last_update DESC");
        $stmt->execute();
        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add some mock data if table is empty
        if (empty($units)) {
            $units = [
                [
                    'unit_id' => 'UNIT-001',
                    'callsign' => 'Alpha One',
                    'assignment' => 'Zone 1 - Main Road',
                    'status' => 'On Patrol',
                    'latitude' => 14.697000,
                    'longitude' => 121.088000,
                    'speed' => '25 km/h',
                    'battery' => '85%',
                    'distance_today' => '45 km',
                    'last_update' => date('Y-m-d H:i:s')
                ],
                [
                    'unit_id' => 'UNIT-002',
                    'callsign' => 'Bravo Two',
                    'assignment' => 'Zone 2 - Residential Area',
                    'status' => 'Stationary',
                    'latitude' => 14.698500,
                    'longitude' => 121.089000,
                    'speed' => '0 km/h',
                    'battery' => '100%',
                    'distance_today' => '12 km',
                    'last_update' => date('Y-m-d H:i:s', strtotime('-1 hour'))
                ]
            ];
        }
        
        echo json_encode([
            'success' => true,
            'units' => $units,
            'timestamp' => date('Y-m-d H:i:s'),
            'count' => count($units)
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
            echo json_encode(['error' => 'Unit not found']);
        }
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => 'Database error']);
    }
}

// Get location history
function getGpsHistory($pdo, $unitId, $hours = 24) {
    try {
        // Check if location_history table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS location_history (
            id INT AUTO_INCREMENT PRIMARY KEY,
            unit_id VARCHAR(50) NOT NULL,
            latitude DECIMAL(10, 6) NOT NULL,
            longitude DECIMAL(10, 6) NOT NULL,
            speed VARCHAR(20) DEFAULT NULL,
            timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_unit_time (unit_id, timestamp)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        
        $stmt = $pdo->prepare("SELECT latitude, longitude, speed, timestamp 
                              FROM location_history 
                              WHERE unit_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL ? HOUR)
                              ORDER BY timestamp ASC");
        $stmt->execute([$unitId, $hours]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode(['success' => true, 'history' => $history]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to fetch history']);
    }
}

// Update unit location
function updateUnitLocation($pdo, $data, $userId) {
    try {
        $unitId = $data['unit_id'] ?? '';
        $lat = floatval($data['latitude'] ?? 0);
        $lng = floatval($data['longitude'] ?? 0);
        $speed = $data['speed'] ?? '0 km/h';
        $status = $data['status'] ?? 'Stationary';
        
        if (empty($unitId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unit ID required']);
            return;
        }
        
        // Update main units table
        $stmt = $pdo->prepare("UPDATE gps_units 
                              SET latitude = ?, longitude = ?, speed = ?, status = ?, last_update = NOW()
                              WHERE unit_id = ?");
        $stmt->execute([$lat, $lng, $speed, $status, $unitId]);
        
        // Add to history
        $stmt = $pdo->prepare("INSERT INTO location_history (unit_id, latitude, longitude, speed) 
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
        echo json_encode(['error' => 'Failed to update location']);
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
        $createdBy = intval($data['created_by'] ?? 0);
        
        if (empty($unitId) || empty($callsign)) {
            http_response_code(400);
            echo json_encode(['error' => 'Unit ID and callsign required']);
            return;
        }
        
        $stmt = $pdo->prepare("INSERT INTO gps_units 
                              (unit_id, callsign, assignment, status, latitude, longitude, created_by)
                              VALUES (?, ?, ?, ?, ?, ?, ?)
                              ON DUPLICATE KEY UPDATE
                              callsign = VALUES(callsign),
                              assignment = VALUES(assignment),
                              status = VALUES(status)");
        $stmt->execute([$unitId, $callsign, $assignment, $status, $lat, $lng, $createdBy]);
        
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
        
        $stmt = $pdo->prepare("UPDATE gps_units SET status = ?, last_update = NOW() WHERE unit_id = ?");
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

// Run the API
handleApiRequest($pdo);
?>