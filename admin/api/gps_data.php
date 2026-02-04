<?php
session_start();
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

function getProvidedApiKey() {
    $hdr = isset($_SERVER['HTTP_X_API_KEY']) ? trim($_SERVER['HTTP_X_API_KEY']) : '';
    if (!$hdr && isset($_SERVER['HTTP_AUTHORIZATION'])) {
        $auth = trim($_SERVER['HTTP_AUTHORIZATION']);
        if (stripos($auth, 'Bearer ') === 0) {
            $hdr = substr($auth, 7);
        }
    }
    $qry = isset($_GET['api_key']) ? trim($_GET['api_key']) : '';
    return $hdr ?: $qry;
}
function getValidApiKey() {
    $key = '';
    try { $key = getenv('CPAS_API_KEY') ?: ''; } catch (\Throwable $e) {}
    if (!$key) {
        $cfg = __DIR__ . '/../../config/api_config.php';
        if (file_exists($cfg)) {
            include $cfg;
            if (isset($API_KEY) && is_string($API_KEY)) {
                $key = trim($API_KEY);
            }
        }
    }
    return $key;
}
$providedKey = getProvidedApiKey();
$validKey = getValidApiKey();
$hasValidApiKey = ($providedKey !== '' && $validKey !== '' && hash_equals($validKey, $providedKey));

if (!isset($_SESSION['user_id']) && !$hasValidApiKey) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

date_default_timezone_set('Asia/Manila');

try {
    $stmt = $pdo->query("
        SELECT 
            unit_id as id,
            callsign,
            COALESCE(assignment_area, assignment) as assignment,
            latitude as lat,
            COALESCE(longtitude, longitude) as lng,
            unit_type as type,
            duration,
            distance_today,
            last_ping,
            `date`,
            `time`,
            TIMESTAMPDIFF(SECOND, last_ping, NOW()) as seconds_since_ping
        FROM gps_units 
        WHERE is_active = 1
        ORDER BY callsign
    ");
    $units = $stmt->fetchAll();
    
    // Format last_ping as ISO string
    foreach ($units as &$unit) {
        $unit['last_ping'] = date(DATE_ATOM, strtotime($unit['last_ping']));
    }
    
    // Additional statistics
    $onPatrol = 0;
    $responding = 0;
    $stationary = 0;
    $alerts = 0;
    
    // Total and offline devices
    $totalDevices = 0;
    $offlineDevices = 0;
    try {
        $totalStmt = $pdo->query("SELECT COUNT(*) AS total FROM gps_units");
        $totalRow = $totalStmt->fetch();
        $totalDevices = intval($totalRow['total'] ?? 0);
        
        $offlineStmt = $pdo->query("SELECT COUNT(*) AS offline FROM gps_units WHERE is_active = 0");
        $offlineRow = $offlineStmt->fetch();
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
        'total_distance' => array_reduce($units, fn($carry, $item) => $carry + ($item['distance_today'] ?? 0), 0),
        'timestamp' => date(DATE_ATOM)
    ];
    
    echo json_encode([
        'success' => true,
        'units' => $units,
        'stats' => $stats
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error: ' . $e->getMessage()
    ]);
}
?>

