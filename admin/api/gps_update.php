<?php
/**
 * GPS Update API Endpoint
 * 
 * This endpoint receives GPS data from tracking devices
 * POST /api/gps_update.php
 * 
 * Required fields:
 * - device_id: Unique device identifier (e.g., "UNIT-001")
 * - latitude: GPS latitude (-90 to 90)
 * - longitude: GPS longitude (-180 to 180)
 * 
 * Optional fields:
 * - speed: Speed in km/h (default: 0)
 * - battery: Battery percentage 0-100 (default: 100)
 * - status: Device status (default: "On Patrol")
 * - distance_today: Distance traveled today in km (default: 0)
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Accept');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Log request method for debugging (remove in production)
error_log('GPS Update API - Request Method: ' . $_SERVER['REQUEST_METHOD']);

require_once '../../config/db_connection.php';

date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
        'received_method' => $_SERVER['REQUEST_METHOD'],
        'allowed_methods' => ['POST', 'OPTIONS']
    ]);
    exit();
}

// Get JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Fallback to form data if JSON is not available
if (!$data) {
    $data = $_POST;
}

// Validate required fields
$deviceId = trim($data['device_id'] ?? $data['unit_id'] ?? '');
$latitude = isset($data['latitude']) ? floatval($data['latitude']) : null;
$longitude = isset($data['longitude']) ? floatval($data['longitude']) : null;

if (empty($deviceId)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'device_id is required'
    ]);
    exit();
}

if ($latitude === null || $longitude === null) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'latitude and longitude are required'
    ]);
    exit();
}

// Validate coordinates
if ($latitude < -90 || $latitude > 90) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid latitude. Must be between -90 and 90.'
    ]);
    exit();
}

if ($longitude < -180 || $longitude > 180) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid longitude. Must be between -180 and 180.'
    ]);
    exit();
}

// Get optional fields with defaults
$speed = isset($data['speed']) ? floatval($data['speed']) : 0;
$battery = isset($data['battery']) ? intval($data['battery']) : 100;
$status = $data['status'] ?? 'On Patrol';
$distanceToday = isset($data['distance_today']) ? floatval($data['distance_today']) : 0;

// Clamp battery between 0-100
$battery = max(0, min(100, $battery));

// Validate status
$validStatuses = ['On Patrol', 'Responding', 'Stationary', 'Needs Assistance'];
if (!in_array($status, $validStatuses)) {
    $status = 'On Patrol';
}

try {
    // Check if device exists
    $checkStmt = $pdo->prepare("SELECT unit_id FROM gps_units WHERE unit_id = ?");
    $checkStmt->execute([$deviceId]);
    $existing = $checkStmt->fetch();

    if ($existing) {
        // Update existing device
        $updateStmt = $pdo->prepare("
            UPDATE gps_units 
            SET 
                latitude = ?,
                longitude = ?,
                speed = ?,
                battery = ?,
                status = ?,
                distance_today = ?,
                last_ping = NOW()
            WHERE unit_id = ?
        ");
        $updateStmt->execute([
            $latitude,
            $longitude,
            $speed,
            $battery,
            $status,
            $distanceToday,
            $deviceId
        ]);

        // Record in history
        $historyStmt = $pdo->prepare("
            INSERT INTO gps_history (unit_id, latitude, longitude, speed)
            VALUES (?, ?, ?, ?)
        ");
        $historyStmt->execute([$deviceId, $latitude, $longitude, $speed]);

        echo json_encode([
            'success' => true,
            'message' => 'GPS data updated successfully',
            'device_id' => $deviceId,
            'timestamp' => date(DATE_ATOM)
        ]);
    } else {
        // Device doesn't exist - create it
        $insertStmt = $pdo->prepare("
            INSERT INTO gps_units 
            (unit_id, callsign, assignment, latitude, longitude, status, speed, battery, distance_today, last_ping)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $insertStmt->execute([
            $deviceId,
            $deviceId, // Use device_id as callsign if not provided
            null,
            $latitude,
            $longitude,
            $status,
            $speed,
            $battery,
            $distanceToday
        ]);

        // Record in history
        $historyStmt = $pdo->prepare("
            INSERT INTO gps_history (unit_id, latitude, longitude, speed)
            VALUES (?, ?, ?, ?)
        ");
        $historyStmt->execute([$deviceId, $latitude, $longitude, $speed]);

        echo json_encode([
            'success' => true,
            'message' => 'New GPS device registered and data saved',
            'device_id' => $deviceId,
            'timestamp' => date(DATE_ATOM)
        ]);
    }
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

