<?php
session_start();
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

date_default_timezone_set('Asia/Manila');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);

if (!$data) {
    $data = $_POST;
}

try {
    $unitId = $data['unit_id'] ?? '';
    $callsign = $data['callsign'] ?? '';
    $assignmentArea = $data['assignment_area'] ?? ($data['assignment'] ?? '');
    $unitType = $data['unit_type'] ?? null;
    $duration = $data['duration'] ?? null;
    $latitude = floatval($data['latitude'] ?? 0);
    $longtitude = isset($data['longtitude']) ? floatval($data['longtitude']) : null;
    $longitude = isset($data['longitude']) ? floatval($data['longitude']) : ($longtitude ?? 0);
    $dateOnly = $data['date'] ?? null;
    $timeOnly = $data['time'] ?? null;
    $speed = floatval($data['speed'] ?? 0);
    $battery = intval($data['battery'] ?? 100);
    $distanceToday = floatval($data['distance_today'] ?? 0);
    $lastPing = ($dateOnly && $timeOnly) ? ($dateOnly . ' ' . $timeOnly) : ($data['last_ping'] ?? date('Y-m-d H:i:s'));
    $isEdit = isset($data['editing_unit_id']) && $data['editing_unit_id'] !== '';

    if (empty($unitId) || empty($callsign)) {
        throw new Exception('Unit ID and Callsign are required');
    }

    // Validate coordinates
    if ($latitude < -90 || $latitude > 90 || $longitude < -180 || $longitude > 180) {
        throw new Exception('Invalid coordinates');
    }

    // Battery removed

    // Convert last_ping to MySQL datetime format
    if (is_string($lastPing)) {
        $lastPingDate = new DateTime($lastPing);
        $lastPing = $lastPingDate->format('Y-m-d H:i:s');
    }
    // Skip schema adjustments to improve performance

    if ($isEdit && isset($data['editing_unit_id'])) {
        // Update existing unit
        $editingUnitId = $data['editing_unit_id'];
        
        // Check if new unit_id conflicts with another unit
        if ($editingUnitId !== $unitId) {
            $checkStmt = $pdo->prepare("SELECT unit_id FROM gps_units WHERE unit_id = ? AND unit_id != ?");
            $checkStmt->execute([$unitId, $editingUnitId]);
            if ($checkStmt->fetch()) {
                throw new Exception('Another unit already uses this ID');
            }
        }
        
        $stmt = $pdo->prepare("
            UPDATE gps_units 
            SET unit_id = ?, callsign = ?, assignment = ?, assignment_area = ?, unit_type = ?, duration = ?, 
                latitude = ?, longitude = ?, longtitude = ?, 
                distance_today = ?, last_ping = ?, `date` = ?, `time` = ?
            WHERE unit_id = ?
        ");
        $stmt->execute([
            $unitId, $callsign, ($assignmentArea ?: ''), $assignmentArea, $unitType, $duration,
            $latitude, $longitude, $longtitude,
            $distanceToday, $lastPing, $dateOnly, $timeOnly, $editingUnitId
        ]);
    } else {
        // Insert new unit
        $checkStmt = $pdo->prepare("SELECT unit_id FROM gps_units WHERE unit_id = ?");
        $checkStmt->execute([$unitId]);
        if ($checkStmt->fetch()) {
            throw new Exception('This unit ID already exists');
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO gps_units 
            (unit_id, callsign, assignment, assignment_area, unit_type, duration, latitude, longitude, longtitude, distance_today, last_ping, `date`, `time`, is_active)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)
        ");
        $stmt->execute([
            $unitId, $callsign, ($assignmentArea ?: ''), $assignmentArea, $unitType, $duration,
            $latitude, $longitude, $longtitude, $distanceToday, $lastPing, $dateOnly, $timeOnly
        ]);
    }

    echo json_encode([
        'success' => true,
        'message' => $isEdit ? 'Unit updated successfully' : 'Unit added successfully'
    ]);
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>

