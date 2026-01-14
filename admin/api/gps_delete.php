<?php
session_start();
require_once '../../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit();
}

$data = json_decode(file_get_contents('php://input'), true);
$unitId = $data['unit_id'] ?? $_POST['unit_id'] ?? '';

if (empty($unitId)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Unit ID is required']);
    exit();
}

try {
    // Soft delete by setting is_active to 0
    $stmt = $pdo->prepare("UPDATE gps_units SET is_active = 0 WHERE unit_id = ?");
    $stmt->execute([$unitId]);
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => true,
            'message' => 'Unit deleted successfully'
        ]);
    } else {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Unit not found'
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

