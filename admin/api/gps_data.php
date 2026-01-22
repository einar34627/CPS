<?php
session_start();
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
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
            assignment,
            latitude as lat,
            longitude as lng,
            status,
            speed,
            battery,
            distance_today,
            last_ping,
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
    $onPatrol = count(array_filter($units, fn($u) => $u['status'] === 'On Patrol'));
    $responding = count(array_filter($units, fn($u) => $u['status'] === 'Responding'));
    $stationary = count(array_filter($units, fn($u) => $u['status'] === 'Stationary'));
    $alerts = count(array_filter($units, fn($u) => $u['status'] === 'Needs Assistance'));
    
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

