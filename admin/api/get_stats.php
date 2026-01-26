<?php
session_start();
require_once '../config/db_connection.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

$section = $_GET['section'] ?? 'dashboard';

function getStatsForSection($pdo, $section) {
    $stats = [];
    
    switch($section) {
        case 'member-registry':
            $stmt = $pdo->prepare("SELECT COUNT(*) as total FROM users WHERE watch_group_member = 1");
            $stmt->execute();
            $stats['total_members'] = $stmt->fetchColumn();
            break;
            
        // Add more cases for other sections
            
        default:
            // Default dashboard stats
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE is_verified = 0");
            $stmt->execute();
            $stats['pending_approvals'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM watch_observations WHERE status = 'pending'");
            $stmt->execute();
            $stats['active_incidents'] = $stmt->fetchColumn();
            
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users");
            $stmt->execute();
            $stats['system_users'] = $stmt->fetchColumn();
    }
    
    return $stats;
}

$stats = getStatsForSection($pdo, $section);
echo json_encode(['success' => true, 'stats' => $stats]);
?>