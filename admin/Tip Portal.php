<?php
session_start();
require_once '../config/db_connection.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: ../login/login.php");
    exit();
}

// Get current user info
$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_name, last_name, role FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    $first_name = htmlspecialchars($user['first_name']);
    $middle_name = htmlspecialchars($user['middle_name']);
    $last_name = htmlspecialchars($user['last_name']);
    $role = htmlspecialchars($user['role']);
    
    $full_name = $first_name;
    if (!empty($middle_name)) {
        $full_name .= " " . $middle_name;
    }
    $full_name .= " " . $last_name;
} else {
    $full_name = "User";
    $role = "ADMIN";
}

$stmt = null;

// Create tips table if it doesn't exist
include 'tip-button-component.php';
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tips (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT NOT NULL,
        category ENUM('Crime Tip', 'Safety Tip', 'Suspicious Activity', 'Community Alert', 'General Information', 'Other') DEFAULT 'Other',
        priority ENUM('Low', 'Medium', 'High', 'Urgent') DEFAULT 'Medium',
        status ENUM('pending', 'under_review', 'verified', 'resolved', 'dismissed') DEFAULT 'pending',
        location VARCHAR(255) DEFAULT NULL,
        contact_info VARCHAR(255) DEFAULT NULL,
        is_anonymous TINYINT(1) DEFAULT 0,
        admin_notes TEXT DEFAULT NULL,
        submitted_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (submitted_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_tip') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $category = $_POST['category'] ?? 'Other';
        $priority = $_POST['priority'] ?? 'Medium';
        $location = trim($_POST['location'] ?? '');
        $contact_info = trim($_POST['contact_info'] ?? '');
        $is_anonymous = isset($_POST['is_anonymous']) ? 1 : 0;
        
        if (empty($title) || empty($description)) {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                $insert = $pdo->prepare("INSERT INTO tips (title, description, category, priority, location, contact_info, is_anonymous, submitted_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$title, $description, $category, $priority, $location, $contact_info, $is_anonymous, $user_id]);
                $message = 'Tip submitted successfully! Thank you for your contribution to community safety.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error submitting tip: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'update_tip') {
        $tip_id = (int)($_POST['tip_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $admin_notes = trim($_POST['admin_notes'] ?? '');
        $priority = $_POST['priority'] ?? 'Medium';
        
        if ($tip_id > 0) {
            try {
                $update = $pdo->prepare("UPDATE tips SET status = ?, admin_notes = ?, priority = ? WHERE id = ?");
                $update->execute([$status, $admin_notes, $priority, $tip_id]);
                $message = 'Tip updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating tip: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'delete_tip') {
        $tip_id = (int)($_POST['tip_id'] ?? 0);
        
        if ($tip_id > 0) {
            try {
                $delete = $pdo->prepare("DELETE FROM tips WHERE id = ?");
                $delete->execute([$tip_id]);
                $message = 'Tip deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting tip: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'bulk_delete') {
        $tip_ids = $_POST['tip_ids'] ?? [];
        
        if (!empty($tip_ids) && is_array($tip_ids)) {
            try {
                $placeholders = str_repeat('?,', count($tip_ids) - 1) . '?';
                $delete = $pdo->prepare("DELETE FROM tips WHERE id IN ($placeholders)");
                $delete->execute($tip_ids);
                $count = $delete->rowCount();
                $message = "$count tip(s) deleted successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting tips: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please select at least one tip to delete.';
            $messageType = 'error';
        }
    }
    
    if ($action === 'bulk_update_status') {
        $tip_ids = $_POST['tip_ids'] ?? [];
        $status = $_POST['status'] ?? 'pending';
        
        if (!empty($tip_ids) && is_array($tip_ids)) {
            try {
                $placeholders = str_repeat('?,', count($tip_ids) - 1) . '?';
                $update = $pdo->prepare("UPDATE tips SET status = ? WHERE id IN ($placeholders)");
                $params = array_merge([$status], $tip_ids);
                $update->execute($params);
                $count = $update->rowCount();
                $statusText = str_replace('_', ' ', ucfirst($status));
                $message = "$count tip(s) updated to " . $statusText . " successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating tips: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please select at least one tip to update.';
            $messageType = 'error';
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$filter_priority = $_GET['priority'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$query = "SELECT t.*, u.first_name, u.last_name, u.email 
          FROM tips t 
          LEFT JOIN users u ON t.submitted_by = u.id 
          WHERE 1=1";

$params = [];

if ($filter_status !== 'all') {
    $query .= " AND t.status = ?";
    $params[] = $filter_status;
}

if ($filter_category !== 'all') {
    $query .= " AND t.category = ?";
    $params[] = $filter_category;
}

if ($filter_priority !== 'all') {
    $query .= " AND t.priority = ?";
    $params[] = $filter_priority;
}

if (!empty($search)) {
    $query .= " AND (t.title LIKE ? OR t.description LIKE ? OR t.location LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$tips = $stmt->fetchAll();

// Get statistics
$totalTips = $pdo->query("SELECT COUNT(*) FROM tips")->fetchColumn();
$pendingTips = $pdo->query("SELECT COUNT(*) FROM tips WHERE status = 'pending'")->fetchColumn();
$verifiedTips = $pdo->query("SELECT COUNT(*) FROM tips WHERE status = 'verified'")->fetchColumn();
$urgentTips = $pdo->query("SELECT COUNT(*) FROM tips WHERE priority = 'Urgent'")->fetchColumn();

// Get unique categories
$categories = $pdo->query("SELECT DISTINCT category FROM tips WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tip Portal - CPAS</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet' integrity='sha512-cD8/5tIGaFbkZqX8N1XcNf1Den3yK7ZD7Uy4+7rgXm5uPJU2N2ZxNt5xQJNzHvqHiKm8bGR5x+7g1X8k7gZxqg==' crossorigin='anonymous'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        /* Anonymous Feedback and Tip Line Card Styles */
        .anonymous-card {
            background: linear-gradient(135deg, #fce7f3 0%, #fdf2f8 100%);
            border-left: 6px solid #c511b3;
            border-radius: 16px;
            padding: 24px;
            margin: 16px 0 32px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 12px rgba(197, 17, 179, 0.1);
            position: relative;
            overflow: hidden;
        }

        .anonymous-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 24px rgba(197, 17, 179, 0.2);
            border-left-width: 8px;
        }
        .anonymous-card:focus {
            outline: none;
            box-shadow: 0 0 0 3px rgba(197, 17, 179, 0.35), 0 8px 24px rgba(197, 17, 179, 0.2);
        }

        .anonymous-card-content {
            display: flex;
            align-items: center;
            gap: 20px;
            position: relative;
            z-index: 1;
        }

        .anonymous-card-icon {
            flex-shrink: 0;
        }

        .anonymous-icon-circle {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, #fce7f3 0%, #fdf2f8 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 12px rgba(197, 17, 179, 0.15);
            transition: all 0.3s ease;
        }

        .anonymous-card:hover .anonymous-icon-circle {
            transform: scale(1.1);
            box-shadow: 0 6px 16px rgba(197, 17, 179, 0.25);
        }

        .anonymous-icon-circle i {
            font-size: 32px;
            color: #ec4899;
            transition: all 0.3s ease;
        }

        .anonymous-card:hover .anonymous-icon-circle i {
            color: #c511b3;
            transform: scale(1.1);
        }

        .anonymous-card-text {
            flex: 1;
            font-size: 20px;
            font-weight: 600;
            color: #c511b3;
            line-height: 1.4;
            letter-spacing: -0.02em;
            transition: color 0.3s ease;
        }

        .anonymous-card:hover .anonymous-card-text {
            color: #a855f7;
        }

        @media (max-width: 768px) {
            .anonymous-card {
                padding: 20px;
            }
            
            .anonymous-icon-circle {
                width: 56px;
                height: 56px;
            }
            
            .anonymous-icon-circle i {
                font-size: 28px;
            }
            
            .anonymous-card-text {
                font-size: 18px;
            }
        }
        
        /* Ensure Boxicons are loaded and displayed correctly */
        @import url('https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,100..700,0..1,-50..200');
        
        /* Boxicons font face fix */
        @font-face {
            font-family: 'boxicons';
            src: url('https://unpkg.com/boxicons@2.1.4/fonts/boxicons.eot');
            src: url('https://unpkg.com/boxicons@2.1.4/fonts/boxicons.eot?#iefix') format('embedded-opentype'),
                 url('https://unpkg.com/boxicons@2.1.4/fonts/boxicons.woff2') format('woff2'),
                 url('https://unpkg.com/boxicons@2.1.4/fonts/boxicons.woff') format('woff'),
                 url('https://unpkg.com/boxicons@2.1.4/fonts/boxicons.ttf') format('truetype'),
                 url('https://unpkg.com/boxicons@2.1.4/fonts/boxicons.svg#boxicons') format('svg');
            font-weight: normal;
            font-style: normal;
            font-display: swap;
        }
        
        /* Ensure icons are visible */
        .bx {
            font-family: 'boxicons' !important;
            font-style: normal;
            font-weight: normal;
            font-variant: normal;
            line-height: 1;
            text-rendering: auto;
            display: inline-block;
            text-transform: none;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        /* Icon sizes */
        i.bx {
            font-size: inherit;
            line-height: inherit;
        }
        
        /* Button icons should be properly sized */
        .btn i.bx {
            font-size: 18px;
            line-height: 1;
            vertical-align: middle;
        }
        
        .btn-icon-only i.bx {
            font-size: 20px;
        }
        
        .btn-small i.bx {
            font-size: 14px;
        }
        
        .btn-large i.bx {
            font-size: 20px;
        }
    </style>
    <style>
        .content-wrapper {
            padding: 32px;
        }
        
        .page-title {
            font-size: 30px;
            font-weight: 700;
            margin-bottom: 8px;
        }
        
        .page-subtitle {
            color: var(--text-light);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--glass-shadow);
        }
        
        .stat-label {
            font-size: 14px;
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .stat-value {
            font-size: 36px;
            font-weight: 700;
        }
        
        .filters-section {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: var(--glass-shadow);
        }
        .toolbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            padding: 12px 16px;
            margin-bottom: 24px;
            box-shadow: var(--glass-shadow);
        }
        .toolbar-group {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
        }
        .toolbar-chip {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 14px;
            border-radius: 999px;
            background: rgba(197, 17, 179, 0.12);
            color: var(--primary-color);
            font-size: 13px;
            font-weight: 600;
        }
        .toolbar-chip i {
            font-size: 16px;
        }
        
        .filters-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            align-items: end;
        }
        
        .filter-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: var(--text-light);
            margin-bottom: 8px;
        }
        
        .filter-group select,
        .filter-group input {
            width: 100%;
            padding: 10px 12px;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.85);
            font-size: 14px;
        }
        
        .tips-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .tip-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--glass-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }
        
        .tip-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .tip-card.selected {
            border: 2px solid var(--primary-color);
            box-shadow: 0 0 0 4px rgba(197, 17, 179, 0.2);
        }
        
        .tip-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }
        
        .tip-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .tip-category {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(197, 17, 179, 0.15);
            color: var(--primary-color);
        }
        
        .tip-status {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #b45309;
        }
        
        .status-under_review {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .status-verified {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .status-resolved {
            background: rgba(34, 197, 94, 0.15);
            color: #15803d;
        }
        
        .status-dismissed {
            background: rgba(107, 114, 128, 0.15);
            color: #4b5563;
        }
        
        .tip-priority {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            margin-left: 8px;
        }
        
        .priority-low {
            background: rgba(156, 163, 175, 0.15);
            color: #6b7280;
        }
        
        .priority-medium {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .priority-high {
            background: rgba(245, 158, 11, 0.15);
            color: #d97706;
        }
        
        .priority-urgent {
            background: rgba(239, 68, 68, 0.15);
            color: #dc2626;
        }
        
        .tip-description {
            margin-bottom: 16px;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .tip-details {
            margin-bottom: 16px;
        }
        
        .tip-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .tip-detail-item i {
            font-size: 18px;
        }
        
        .admin-notes {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #2563eb;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
        }
        
        .admin-notes-label {
            font-size: 12px;
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 8px;
        }
        
        .admin-notes-text {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
        }
        
        .tip-actions {
            display: flex;
            gap: 8px;
            margin-top: 16px;
        }
        
        .btn {
            padding: 8px 16px;
            border-radius: 8px;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 6px;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(197, 17, 179, 0.3);
        }
        
        .btn-secondary {
            background: rgba(255,255,255,0.2);
            color: var(--text-color);
        }
        
        .btn-secondary:hover {
            background: rgba(255,255,255,0.3);
        }
        
        .btn-danger {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }
        
        .btn-danger:hover {
            background: rgba(239, 68, 68, 0.25);
        }
        
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
        }
        
        .btn-warning {
            background: rgba(245, 158, 11, 0.15);
            color: #92400e;
        }
        
        .btn-warning:hover {
            background: rgba(245, 158, 11, 0.25);
        }
        
        .btn-info {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .btn-info:hover {
            background: rgba(59, 130, 246, 0.25);
        }
        
        .btn-small {
            padding: 6px 12px;
            font-size: 12px;
        }
        
        .btn-large {
            padding: 16px 32px;
            font-size: 18px;
        }
        
        .btn-icon-only {
            width: 40px;
            height: 40px;
            padding: 0;
            justify-content: center;
            border-radius: 50%;
        }
        
        .btn-outline {
            background: transparent;
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
        }
        
        .btn-outline:hover {
            background: var(--primary-color);
            color: white;
        }
        
        .btn-disabled {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }
        
        /* Select Button Styles */
        .select-actions {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            margin-bottom: 16px;
            padding: 16px;
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 12px;
            box-shadow: var(--glass-shadow);
            flex-wrap: wrap;
        }
        
        .select-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
            accent-color: var(--primary-color);
        }
        
        .tip-select-checkbox {
            position: absolute;
            top: 16px;
            left: 16px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            accent-color: var(--primary-color);
            z-index: 10;
        }
        
        .bulk-actions {
            display: none;
            align-items: center;
            gap: 8px;
            margin-left: auto;
        }
        
        .bulk-actions.active {
            display: flex;
        }
        
        .selected-count {
            padding: 6px 12px;
            background: rgba(197, 17, 179, 0.15);
            color: var(--primary-color);
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }
        
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(4px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal.active {
            display: flex;
        }
        
        .modal-content {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 20px;
            padding: 32px;
            max-width: 600px;
            width: 90%;
            max-height: 90vh;
            overflow-y: auto;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .modal-title {
            font-size: 24px;
            font-weight: 700;
        }
        
        .close-btn {
            background: none;
            border: none;
            font-size: 24px;
            cursor: pointer;
            color: var(--text-light);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-color);
        }
        
        .form-group input,
        .form-group textarea,
        .form-group select {
            width: 100%;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid rgba(0,0,0,0.1);
            background: rgba(255,255,255,0.85);
            font-size: 14px;
            font-family: inherit;
        }
        
        .form-group textarea {
            min-height: 100px;
            resize: vertical;
        }
        
        .form-group.checkbox-group {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .form-group.checkbox-group input[type="checkbox"] {
            width: auto;
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .message {
            padding: 16px 20px;
            border-radius: 12px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 500;
        }
        
        .message.success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .message.error {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }
        
        .empty-state {
            text-align: center;
            padding: 48px 24px;
            color: var(--text-light);
        }
        
        .empty-state i {
            font-size: 64px;
            margin-bottom: 16px;
            opacity: 0.5;
        }
        
        .create-button-section {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 16px;
        }
        
        .create-btn-large {
            padding: 14px 28px;
            border-radius: 12px;
            border: none;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 4px 15px rgba(197, 17, 179, 0.3);
        }
        
        .create-btn-large:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 20px rgba(197, 17, 179, 0.4);
        }
        
        .create-btn-large i {
            font-size: 20px;
        }
        
        .floating-create-btn {
            position: fixed;
            bottom: 32px;
            right: 32px;
            width: 64px;
            height: 64px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            border: none;
            cursor: pointer;
            box-shadow: 0 8px 24px rgba(197, 17, 179, 0.4);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            z-index: 999;
            transition: all 0.3s ease;
        }
        
        .floating-create-btn:hover {
            transform: scale(1.1) rotate(90deg);
            box-shadow: 0 12px 32px rgba(197, 17, 179, 0.5);
        }
        
        /* Success Notification Styles */
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            background: white;
            border-radius: 12px;
            padding: 16px 20px;
            box-shadow: 0 8px 24px rgba(0,0,0,0.15);
            z-index: 10000;
            min-width: 300px;
            max-width: 400px;
            transform: translateX(400px);
            opacity: 0;
            transition: all 0.3s ease;
        }
        
        .notification.show {
            transform: translateX(0);
            opacity: 1;
        }
        
        .notification-success {
            border-left: 4px solid #10b981;
        }
        
        .notification-error {
            border-left: 4px solid #ef4444;
        }
        
        .notification-warning {
            border-left: 4px solid #f59e0b;
        }
        
        .notification-info {
            border-left: 4px solid #3b82f6;
        }
        
        .notification-content {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .notification-content i {
            font-size: 24px;
        }
        
        .notification-success .notification-content i {
            color: #10b981;
        }
        
        .notification-error .notification-content i {
            color: #ef4444;
        }
        
        .notification-warning .notification-content i {
            color: #f59e0b;
        }
        
        .notification-info .notification-content i {
            color: #3b82f6;
        }
        
        .notification-content span {
            flex: 1;
            font-weight: 500;
            color: var(--text-color, #0f172a);
        }
        
        .notification-close {
            background: none;
            border: none;
            cursor: pointer;
            color: #64748b;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 4px;
            transition: all 0.2s;
        }
        
        .notification-close:hover {
            background: rgba(0,0,0,0.05);
            color: var(--text-color, #0f172a);
        }
        
        /* Success Button Styles */
        .btn-success {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
            border: 1px solid rgba(16, 185, 129, 0.3);
        }
        
        .btn-success:hover {
            background: rgba(16, 185, 129, 0.25);
            color: #065f46;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(16, 185, 129, 0.2);
        }
        
        .btn-success:active {
            transform: translateY(0);
        }
        
        @media (max-width: 768px) {
            .floating-create-btn {
                bottom: 24px;
                right: 24px;
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            
            .tips-grid {
                grid-template-columns: 1fr;
            }
            
            .notification {
                right: 10px;
                left: 10px;
                min-width: auto;
                max-width: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <div class="logo">
                <div class="logo-icon">
                    <img src="../img/cpas-logo.png" alt="CPAS Logo" style="width: 40px; height: 45px;">
                </div>
                <span class="logo-text">Community Policing and Surveillance</span>
            </div>
            
            <div class="menu-section">
                <p class="menu-title">COMMUNITY POLICING AND SURVEILLANCE</p>
                <div class="menu-items">
                    <a href="admin_dashboard.php" class="menu-item">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bxs-dashboard icon-red'></i>
                        </div>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    
                    <div class="menu-item" onclick="toggleSubmenu('inspection')">
                        <div class="icon-box icon-bg-yellow">
                            <i class='bx bxs-check-shield icon-yellow'></i>
                        </div>
                        <span class="font-medium">Awareness and Event Tracking</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="inspection" class="submenu active">
                        <a href="Registratiom System.php" class="submenu-item">Registration System</a>
                        <a href="Event Sheduling.php" class="submenu-item">Event Scheduling</a>
                        <a href="Feedback.php" class="submenu-item">Feedback</a>
                        <a href="Tip Portal.php" class="submenu-item active">Tip Portal</a>
                    </div>
                </div>
            </div>
            
            <div class="menu-section">
                <p class="menu-title">GENERAL</p>
                <div class="menu-items">
                    <a href="../includes/logout.php" class="menu-item">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bx-log-out icon-red'></i>
                        </div>
                        <span class="font-medium">Logout</span>
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Main Content -->
        <div class="main-content">
            <div class="header">
                <div class="header-content">
                    <div>
                        <h1 style="font-size: 24px; font-weight: 700;">Tip Portal</h1>
                        <p style="color: var(--text-light);">Submit and manage community safety tips</p>
                    </div>
                    <div class="user-profile">
                        <img src="../img/rei.jfif" alt="User" class="user-avatar">
                        <div class="user-info">
                            <p class="user-name"><?php echo $full_name; ?></p>
                            <p class="user-email"><?php echo $role; ?></p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="toolbar">
                <div class="toolbar-group">
                    <?php echo createTipButton('info', 'Tip Portal', 'bx-message-square-dots', 'onclick=\"openCreateModal()\"', 'small'); ?>
                </div>
                <div class="toolbar-group">
                    <?php echo renderTipPortalToolbarButtons(); ?>
                </div>
            </div>
            
            <div class="content-wrapper">
                <?php if ($message): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <i class='bx <?php echo $messageType === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Tips</div>
                        <div class="stat-value"><?php echo number_format($totalTips); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value"><?php echo number_format($pendingTips); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Verified</div>
                        <div class="stat-value"><?php echo number_format($verifiedTips); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Urgent</div>
                        <div class="stat-value"><?php echo number_format($urgentTips); ?></div>
                    </div>
                </div>
                
                <!-- Anonymous Feedback and Tip Line Card -->
                <div class="anonymous-card" onclick="openCreateModal()" role="button" tabindex="0" aria-label="Open Anonymous Feedback and Tip Line form">
                    <div class="anonymous-card-content">
                        <div class="anonymous-card-icon" aria-hidden="true">
                            <div class="anonymous-icon-circle">
                                <i class='bx bx-file-blank'></i>
                            </div>
                        </div>
                        <div class="anonymous-card-text">
                            Anonymous Feedback and Tip Line
                        </div>
                    </div>
                </div>
                
                <!-- Quick Action Buttons -->
                <div class="filters-section" style="margin-bottom: 24px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h3 style="font-size: 18px; font-weight: 600; margin: 0; color: var(--text-color);">Quick Actions</h3>
                        <a href="create-buttons.php" class="btn btn-outline btn-small" style="text-decoration: none;">
                            <i class='bx bx-code-alt'></i> Button Creator
                        </a>
                    </div>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Submit Tip
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='?status=verified'">
                            <i class='bx bx-check-circle'></i> View Verified
                        </button>
                        <button class="btn btn-warning" onclick="window.location.href='?status=pending'">
                            <i class='bx bx-time'></i> View Pending
                        </button>
                        <button class="btn btn-danger" onclick="window.location.href='?priority=Urgent'">
                            <i class='bx bx-error-circle'></i> View Urgent
                        </button>
                        <button class="btn btn-info" onclick="window.location.href='?status=all'">
                            <i class='bx bx-list-ul'></i> View All
                        </button>
                        <button class="btn btn-secondary" onclick="window.location.reload()">
                            <i class='bx bx-refresh'></i> Refresh
                        </button>
                        <button class="btn btn-outline" onclick="window.print()">
                            <i class='bx bx-printer'></i> Print
                        </button>
                        <button class="btn btn-outline" onclick="exportTips()">
                            <i class='bx bx-download'></i> Export
                        </button>
                        <button class="btn btn-primary btn-small" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Small Button
                        </button>
                        <button class="btn btn-primary btn-large" onclick="openCreateModal()">
                            <i class='bx bx-upload'></i> Large Button
                        </button>
                        <button class="btn btn-primary btn-icon-only" title="Add Tip" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i>
                        </button>
                        <button class="btn btn-success btn-icon-only" title="Verified Tips" onclick="window.location.href='?status=verified'">
                            <i class='bx bx-check'></i>
                        </button>
                        <button class="btn btn-danger btn-icon-only" title="Urgent Tips" onclick="window.location.href='?priority=Urgent'">
                            <i class='bx bx-error-circle'></i>
                        </button>
                        <button class="btn btn-warning btn-icon-only" title="Pending Tips" onclick="window.location.href='?status=pending'">
                            <i class='bx bx-time'></i>
                        </button>
                        <button class="btn btn-info btn-icon-only" title="All Tips" onclick="window.location.href='?status=all'">
                            <i class='bx bx-list-ul'></i>
                        </button>
                        <button class="btn btn-secondary btn-icon-only" title="Refresh" onclick="window.location.reload()">
                            <i class='bx bx-refresh'></i>
                        </button>
                        <button class="btn btn-primary" onclick="window.location.href='Feedback.php'">
                            <i class='bx bx-message-square-dots'></i> Go to Feedback
                        </button>
                        <button class="btn btn-info" onclick="window.location.href='admin_dashboard.php'">
                            <i class='bx bx-home'></i> Dashboard
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='?status=resolved'">
                            <i class='bx bx-check-double'></i> View Resolved
                        </button>
                        <button class="btn btn-warning" onclick="window.location.href='?status=under_review'">
                            <i class='bx bx-search-alt'></i> Under Review
                        </button>
                        <button class="btn btn-danger" onclick="window.location.href='?status=dismissed'">
                            <i class='bx bx-x-circle'></i> View Dismissed
                        </button>
                        <button class="btn btn-outline" onclick="window.location.href='?category=Crime Tip'">
                            <i class='bx bx-shield'></i> Crime Tips
                        </button>
                        <button class="btn btn-outline" onclick="window.location.href='?category=Safety Tip'">
                            <i class='bx bx-check-shield'></i> Safety Tips
                        </button>
                        <button class="btn btn-outline" onclick="window.location.href='?category=Suspicious Activity'">
                            <i class='bx bx-error-alt'></i> Suspicious Activity
                        </button>
                        <button class="btn btn-primary" onclick="clearFilters()">
                            <i class='bx bx-filter-alt'></i> Clear Filters
                        </button>
                        <button class="btn btn-secondary" onclick="showStats()">
                            <i class='bx bx-bar-chart'></i> Show Statistics
                        </button>
                    </div>
                </div>
                
                <div class="filters-section">
                    <form method="get" class="filters-grid">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo $filter_status === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                <option value="under_review" <?php echo $filter_status === 'under_review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="verified" <?php echo $filter_status === 'verified' ? 'selected' : ''; ?>>Verified</option>
                                <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                                <option value="dismissed" <?php echo $filter_status === 'dismissed' ? 'selected' : ''; ?>>Dismissed</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="Crime Tip" <?php echo $filter_category === 'Crime Tip' ? 'selected' : ''; ?>>Crime Tip</option>
                                <option value="Safety Tip" <?php echo $filter_category === 'Safety Tip' ? 'selected' : ''; ?>>Safety Tip</option>
                                <option value="Suspicious Activity" <?php echo $filter_category === 'Suspicious Activity' ? 'selected' : ''; ?>>Suspicious Activity</option>
                                <option value="Community Alert" <?php echo $filter_category === 'Community Alert' ? 'selected' : ''; ?>>Community Alert</option>
                                <option value="General Information" <?php echo $filter_category === 'General Information' ? 'selected' : ''; ?>>General Information</option>
                                <option value="Other" <?php echo $filter_category === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Priority</label>
                            <select name="priority">
                                <option value="all" <?php echo $filter_priority === 'all' ? 'selected' : ''; ?>>All Priorities</option>
                                <option value="Low" <?php echo $filter_priority === 'Low' ? 'selected' : ''; ?>>Low</option>
                                <option value="Medium" <?php echo $filter_priority === 'Medium' ? 'selected' : ''; ?>>Medium</option>
                                <option value="High" <?php echo $filter_priority === 'High' ? 'selected' : ''; ?>>High</option>
                                <option value="Urgent" <?php echo $filter_priority === 'Urgent' ? 'selected' : ''; ?>>Urgent</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Search tips..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class='bx bx-search'></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="create-button-section" id="tips">
                    <h2 style="font-size: 20px; font-weight: 600; margin: 0;">Tip Entries</h2>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="create-btn-large" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Submit Tip
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='?status=verified'">
                            <i class='bx bx-check-circle'></i> View Verified
                        </button>
                        <button class="btn btn-warning" onclick="window.location.href='?status=pending'">
                            <i class='bx bx-time'></i> View Pending
                        </button>
                        <button class="btn btn-danger" onclick="window.location.href='?priority=Urgent'">
                            <i class='bx bx-error-circle'></i> View Urgent
                        </button>
                        <button class="btn btn-info" onclick="window.location.href='?status=all'">
                            <i class='bx bx-list-ul'></i> View All
                        </button>
                        <button class="btn btn-outline" onclick="exportTips()">
                            <i class='bx bx-download'></i> Export
                        </button>
                    </div>
                </div>
                
                <!-- Select Actions -->
                <div class="select-actions">
                    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <div style="display: flex; align-items: center; gap: 8px; padding: 8px 12px; background: rgba(197, 17, 179, 0.1); border-radius: 8px;">
                            <input type="checkbox" id="selectAll" class="select-checkbox" onchange="toggleSelectAll(this.checked)">
                            <label for="selectAll" style="cursor: pointer; font-weight: 600; color: var(--text-color); margin: 0;">
                                Select All
                            </label>
                        </div>
                        <button class="btn btn-secondary btn-small" onclick="deselectAll()" title="Deselect All Items">
                            <i class='bx bx-square'></i> Deselect All
                        </button>
                        <button class="btn btn-warning btn-small" onclick="selectByStatus('pending')" title="Select Pending Items">
                            <i class='bx bx-time'></i> Select Pending
                        </button>
                        <button class="btn btn-danger btn-small" onclick="selectByPriority('Urgent')" title="Select Urgent Items">
                            <i class='bx bx-error-circle'></i> Select Urgent
                        </button>
                    </div>
                    <div class="bulk-actions" id="bulkActions">
                        <span class="selected-count" id="selectedCount">0 selected</span>
                        <button class="btn btn-success btn-small" onclick="bulkUpdateStatus('verified')" title="Mark Selected as Verified">
                            <i class='bx bx-check'></i> Mark Verified
                        </button>
                        <button class="btn btn-warning btn-small" onclick="bulkUpdateStatus('under_review')" title="Mark Selected as Under Review">
                            <i class='bx bx-time-five'></i> Mark Under Review
                        </button>
                        <button class="btn btn-info btn-small" onclick="bulkUpdateStatus('pending')" title="Mark Selected as Pending">
                            <i class='bx bx-history'></i> Mark Pending
                        </button>
                        <button class="btn btn-danger btn-small" onclick="bulkDelete()" title="Delete Selected Items">
                            <i class='bx bx-trash'></i> Delete Selected
                        </button>
                    </div>
                </div>
                
                <?php if (empty($tips)): ?>
                    <div class="create-btn-section" style="text-align: center; padding: 48px 24px;">
                        <i class='bx bx-info-circle' style="font-size: 64px; color: var(--text-light); margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 8px 0; font-size: 20px; color: var(--text-color);">No tips found</h3>
                        <p style="color: var(--text-light); margin-bottom: 24px;">Be the first to submit a tip to help keep the community safe.</p>
                        <button class="create-btn-large" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Submit Your First Tip
                        </button>
                    </div>
                <?php else: ?>
                    <div class="tips-grid">
                        <?php foreach ($tips as $tip): ?>
                            <div class="tip-card" data-tip-id="<?php echo $tip['id']; ?>">
                                <input type="checkbox" 
                                       class="tip-select-checkbox" 
                                       data-tip-id="<?php echo $tip['id']; ?>"
                                       onchange="updateSelection()">
                                <div class="tip-header">
                                    <div>
                                        <div class="tip-title"><?php echo htmlspecialchars($tip['title']); ?></div>
                                        <div style="display: flex; align-items: center; gap: 8px; flex-wrap: wrap;">
                                            <span class="tip-category"><?php echo htmlspecialchars($tip['category']); ?></span>
                                            <span class="tip-priority priority-<?php echo strtolower($tip['priority']); ?>">
                                                <?php echo htmlspecialchars($tip['priority']); ?>
                                            </span>
                                        </div>
                                    </div>
                                    <span class="tip-status status-<?php echo htmlspecialchars($tip['status']); ?>">
                                        <?php echo ucfirst(str_replace('_', ' ', $tip['status'])); ?>
                                    </span>
                                </div>
                                
                                <div class="tip-description">
                                    <?php echo nl2br(htmlspecialchars($tip['description'])); ?>
                                </div>
                                
                                <div class="tip-details">
                                    <?php if (!empty($tip['location'])): ?>
                                        <div class="tip-detail-item">
                                            <i class='bx bx-map'></i>
                                            <span><?php echo htmlspecialchars($tip['location']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if (!empty($tip['contact_info']) && !$tip['is_anonymous']): ?>
                                        <div class="tip-detail-item">
                                            <i class='bx bx-phone'></i>
                                            <span><?php echo htmlspecialchars($tip['contact_info']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?php if ($tip['is_anonymous']): ?>
                                        <div class="tip-detail-item">
                                            <i class='bx bx-user-x'></i>
                                            <span>Anonymous Submission</span>
                                        </div>
                                    <?php elseif (!empty($tip['first_name'])): ?>
                                        <div class="tip-detail-item">
                                            <i class='bx bx-user'></i>
                                            <span><?php echo htmlspecialchars($tip['first_name'] . ' ' . $tip['last_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="tip-detail-item">
                                        <i class='bx bx-calendar'></i>
                                        <span><?php echo date('F d, Y g:i A', strtotime($tip['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($tip['admin_notes'])): ?>
                                    <div class="admin-notes">
                                        <div class="admin-notes-label">Admin Notes:</div>
                                        <div class="admin-notes-text"><?php echo nl2br(htmlspecialchars($tip['admin_notes'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($role === 'ADMIN' || $role === 'EMPLOYEE'): ?>
                                    <div class="tip-actions">
                                        <button class="btn btn-secondary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($tip)); ?>)">
                                            <i class='bx bx-edit'></i> Update
                                        </button>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this tip?');">
                                            <input type="hidden" name="action" value="delete_tip">
                                            <input type="hidden" name="tip_id" value="<?php echo $tip['id']; ?>">
                                            <button type="submit" class="btn btn-danger">
                                                <i class='bx bx-trash'></i> Delete
                                            </button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create/Edit Modal -->
    <div id="tipModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Submit Tip</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="post" id="tipForm">
                <input type="hidden" name="action" id="formAction" value="create_tip">
                <input type="hidden" name="tip_id" id="tipId">
                
                <div class="form-group">
                    <label>Title *</label>
                    <input type="text" name="title" id="tipTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Description *</label>
                    <textarea name="description" id="tipDescription" required placeholder="Provide detailed information about the tip..."></textarea>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="tipCategory">
                        <option value="Crime Tip">Crime Tip</option>
                        <option value="Safety Tip">Safety Tip</option>
                        <option value="Suspicious Activity">Suspicious Activity</option>
                        <option value="Community Alert">Community Alert</option>
                        <option value="General Information">General Information</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Priority</label>
                    <select name="priority" id="tipPriority">
                        <option value="Low">Low</option>
                        <option value="Medium" selected>Medium</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
                
                <div class="form-group">
                    <label>Location (Optional)</label>
                    <input type="text" name="location" id="tipLocation" placeholder="Enter location if applicable">
                </div>
                
                <div class="form-group">
                    <label>Contact Information (Optional)</label>
                    <input type="text" name="contact_info" id="tipContact" placeholder="Phone or email for follow-up">
                </div>
                
                <div class="form-group checkbox-group">
                    <input type="checkbox" name="is_anonymous" id="tipAnonymous" value="1">
                    <label for="tipAnonymous" style="margin: 0; cursor: pointer;">Submit anonymously</label>
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label>Status</label>
                    <select name="status" id="tipStatus">
                        <option value="pending">Pending</option>
                        <option value="under_review">Under Review</option>
                        <option value="verified">Verified</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                    </select>
                </div>
                
                <div class="form-group" id="notesGroup" style="display: none;">
                    <label>Admin Notes</label>
                    <textarea name="admin_notes" id="adminNotes"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> <span id="submitText">Submit Tip</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
<!-- c:\xampp htdoc\htdocs\CPAS\admin\Tip Portal.php -->
<a href="Tip Portal.php" class="btn btn-secondary" style="text-decoration:none;">
    <i class='bx bx-info-circle'></i> Tip Portal
</a><!-- c:\xampp htdoc\htdocs\CPAS\admin\Tip Portal.php -->
<button class="btn btn-primary" onclick="openCreateModal()">
    <i class='bx bx-plus'></i> Submit Tip
</button><!-- c:\xampp htdoc\htdocs\CPAS\admin\Tip Portal.php -->
<button class="btn btn-primary" style="border-radius:50%;width:48px;height:48px;padding:0;" onclick="openCreateModal()">
    <i class='bx bx-plus'></i>
</button><!-- c:\xampp htdoc\htdocs\CPAS\admin\Tip Portal.php -->
<button class="create-btn-large" onclick="openCreateModal()">
    <i class='bx bx-plus'></i> Submit Tip
</button>    <!-- Floating Create Button -->
    <button id="createTipBtn" class="floating-create-btn" onclick="openCreateModal()" title="Submit Tip">
        <i class='bx bx-plus'></i>
    </button>
    
    <script>
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const arrow = submenu.previousElementSibling.querySelector('.dropdown-arrow');
            submenu.classList.toggle('active');
            if (arrow) arrow.classList.toggle('rotated');
        }
        
        function openCreateModal() {
            try {
                const modal = document.getElementById('tipModal');
                if (!modal) {
                    console.error('Tip modal not found!');
                    alert('Error: Tip form not found. Please refresh the page.');
                    return;
                }
                
                document.getElementById('modalTitle').textContent = 'Submit Tip';
                document.getElementById('formAction').value = 'create_tip';
                document.getElementById('submitText').textContent = 'Submit Tip';
                document.getElementById('tipForm').reset();
                document.getElementById('tipId').value = '';
                document.getElementById('statusGroup').style.display = 'none';
                document.getElementById('notesGroup').style.display = 'none';
                
                modal.classList.add('active');
            } catch (error) {
                console.error('Error opening modal:', error);
                alert('Error opening tip form. Please refresh the page.');
            }
        }
        
        function openEditModal(tip) {
            try {
                let tipData = tip;
                if (typeof tip === 'string') {
                    try {
                        tipData = JSON.parse(tip);
                    } catch (e) {
                        console.error('Error parsing tip data:', e);
                        alert('Error loading tip data. Please try again.');
                        return;
                    }
                }
                
                document.getElementById('modalTitle').textContent = 'Update Tip';
                document.getElementById('formAction').value = 'update_tip';
                document.getElementById('submitText').textContent = 'Update Tip';
                document.getElementById('tipId').value = tipData.id || '';
                document.getElementById('tipTitle').value = tipData.title || '';
                document.getElementById('tipDescription').value = tipData.description || '';
                document.getElementById('tipCategory').value = tipData.category || 'Other';
                document.getElementById('tipPriority').value = tipData.priority || 'Medium';
                document.getElementById('tipLocation').value = tipData.location || '';
                document.getElementById('tipContact').value = tipData.contact_info || '';
                document.getElementById('tipAnonymous').checked = tipData.is_anonymous == 1;
                document.getElementById('tipStatus').value = tipData.status || 'pending';
                document.getElementById('adminNotes').value = tipData.admin_notes || '';
                document.getElementById('statusGroup').style.display = 'block';
                document.getElementById('notesGroup').style.display = 'block';
                document.getElementById('tipModal').classList.add('active');
            } catch (error) {
                console.error('Error opening edit modal:', error);
                alert('Error opening tip form. Please refresh the page.');
            }
        }
        
        function closeModal() {
            document.getElementById('tipModal').classList.remove('active');
        }
        
        // Close modal on outside click and ESC key
        function initModalHandlers() {
            const modal = document.getElementById('tipModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
            
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('tipModal');
                    if (modal && modal.classList.contains('active')) {
                        closeModal();
                    }
                }
            });
        }
        
        // Selection functions
        function toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.tip-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
                const card = checkbox.closest('.tip-card');
                if (checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            updateSelection();
        }
        
        function deselectAll() {
            document.getElementById('selectAll').checked = false;
            toggleSelectAll(false);
        }
        
        function selectByStatus(status) {
            const checkboxes = document.querySelectorAll('.tip-select-checkbox');
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.tip-card');
                const statusBadge = card.querySelector('.tip-status');
                
                if (statusBadge) {
                    const cardStatus = statusBadge.classList.contains('status-' + status);
                    if (cardStatus) {
                        checkbox.checked = true;
                        card.classList.add('selected');
                    } else {
                        checkbox.checked = false;
                        card.classList.remove('selected');
                    }
                }
            });
            updateSelection();
        }
        
        function selectByPriority(priority) {
            const checkboxes = document.querySelectorAll('.tip-select-checkbox');
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.tip-card');
                const priorityBadge = card.querySelector('.tip-priority');
                
                if (priorityBadge) {
                    const cardPriority = priorityBadge.classList.contains('priority-' + priority.toLowerCase());
                    if (cardPriority) {
                        checkbox.checked = true;
                        card.classList.add('selected');
                    } else {
                        checkbox.checked = false;
                        card.classList.remove('selected');
                    }
                }
            });
            updateSelection();
        }
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.tip-select-checkbox');
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            const selectedCount = checked.length;
            
            document.getElementById('selectedCount').textContent = selectedCount + ' selected';
            
            const bulkActions = document.getElementById('bulkActions');
            if (selectedCount > 0) {
                bulkActions.classList.add('active');
            } else {
                bulkActions.classList.remove('active');
            }
            
            const selectAllCheckbox = document.getElementById('selectAll');
            if (selectedCount === 0) {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = false;
            } else if (selectedCount === checkboxes.length) {
                selectAllCheckbox.checked = true;
                selectAllCheckbox.indeterminate = false;
            } else {
                selectAllCheckbox.checked = false;
                selectAllCheckbox.indeterminate = true;
            }
            
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.tip-card');
                if (checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }
        
        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.tip-select-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.getAttribute('data-tip-id'));
        }
        
        function bulkDelete() {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert('Please select at least one tip to delete.');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selectedIds.length} tip(s)? This action cannot be undone.`)) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_delete';
            form.appendChild(actionInput);
            
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tip_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function bulkUpdateStatus(status) {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert('Please select at least one tip to update.');
                return;
            }
            
            const statusText = status.charAt(0).toUpperCase() + status.slice(1).replace('_', ' ');
            if (!confirm(`Are you sure you want to mark ${selectedIds.length} tip(s) as ${statusText}?`)) {
                return;
            }
            
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'bulk_update_status';
            form.appendChild(actionInput);
            
            const statusInput = document.createElement('input');
            statusInput.type = 'hidden';
            statusInput.name = 'status';
            statusInput.value = status;
            form.appendChild(statusInput);
            
            selectedIds.forEach(id => {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'tip_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Initialize everything when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initModalHandlers();
            });
        } else {
            initModalHandlers();
        }
        
        // Add event listeners to checkboxes
        document.addEventListener('DOMContentLoaded', function() {
            const checkboxes = document.querySelectorAll('.tip-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelection);
            });
        });
        
        // Export tips function
        function exportTips() {
            const status = new URLSearchParams(window.location.search).get('status') || 'all';
            const category = new URLSearchParams(window.location.search).get('category') || 'all';
            const priority = new URLSearchParams(window.location.search).get('priority') || 'all';
            window.location.href = 'Tip Portal.php?export=1&status=' + status + '&category=' + category + '&priority=' + priority;
        }
        
        // Clear all filters
        function clearFilters() {
            window.location.href = 'Tip Portal.php';
        }
        
        // Show statistics
        function showStats() {
            const stats = {
                total: <?php echo $totalTips; ?>,
                pending: <?php echo $pendingTips; ?>,
                verified: <?php echo $verifiedTips; ?>,
                urgent: <?php echo $urgentTips; ?>
            };
            
            alert('Tip Statistics:\n\n' +
                  'Total Tips: ' + stats.total + '\n' +
                  'Pending: ' + stats.pending + '\n' +
                  'Verified: ' + stats.verified + '\n' +
                  'Urgent: ' + stats.urgent);
        }
        
        // Success button functions
        function showSuccessMessage() {
            showNotification('Success! Action completed successfully.', 'success');
        }
        
        function showSuccess() {
            showNotification('Operation successful!', 'success');
        }
        
        function markAllVerified() {
            if (confirm('Are you sure you want to mark all tips as verified?')) {
                showNotification('All tips marked as verified!', 'success');
                // You can add actual functionality here
            }
        }
        
        function approveSelected() {
            const selected = getSelectedIds();
            if (selected.length === 0) {
                showNotification('Please select at least one tip to approve.', 'warning');
                return;
            }
            if (confirm(`Approve ${selected.length} selected tip(s)?`)) {
                showNotification(`${selected.length} tip(s) approved successfully!`, 'success');
                // You can add actual functionality here
            }
        }
        
        // Notification system
        function showNotification(message, type = 'success') {
            // Remove existing notifications
            const existing = document.querySelector('.notification');
            if (existing) {
                existing.remove();
            }
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `notification notification-${type}`;
            notification.innerHTML = `
                <div class="notification-content">
                    <i class='bx ${type === 'success' ? 'bx-check-circle' : type === 'error' ? 'bx-error-circle' : 'bx-info-circle'}'></i>
                    <span>${message}</span>
                    <button class="notification-close" onclick="this.parentElement.parentElement.remove()">
                        <i class='bx bx-x'></i>
                    </button>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Show notification
            setTimeout(() => {
                notification.classList.add('show');
            }, 10);
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                notification.classList.remove('show');
                setTimeout(() => {
                    notification.remove();
                }, 300);
            }, 5000);
        }
        
        // Button Fix Script - Ensures all buttons work properly
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Tip Portal: Initializing buttons...');
            
            // Ensure openCreateModal is globally available
            if (typeof window.openCreateModal === 'undefined') {
                window.openCreateModal = function() {
                    try {
                        const modal = document.getElementById('tipModal');
                        if (!modal) {
                            console.error('Tip modal not found!');
                            alert('Error: Tip submission form not found. Please refresh the page.');
                            return;
                        }
                        
                        document.getElementById('modalTitle').textContent = 'Submit Tip';
                        document.getElementById('formAction').value = 'create_tip';
                        document.getElementById('submitText').textContent = 'Submit Tip';
                        document.getElementById('tipForm').reset();
                        document.getElementById('tipId').value = '';
                        document.getElementById('statusGroup').style.display = 'none';
                        document.getElementById('notesGroup').style.display = 'none';
                        
                        modal.classList.add('active');
                        console.log('Modal opened successfully');
                    } catch (error) {
                        console.error('Error opening modal:', error);
                        alert('Error opening tip form. Please refresh the page.');
                    }
                };
            }
            
            // Fix anonymous card click and keyboard access
            const anonymousCard = document.querySelector('.anonymous-card');
            if (anonymousCard) {
                anonymousCard.setAttribute('role', 'button');
                anonymousCard.setAttribute('tabindex', '0');
                anonymousCard.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    if (typeof openCreateModal === 'function') {
                        openCreateModal();
                    }
                });
                anonymousCard.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter' || e.key === ' ') {
                        e.preventDefault();
                        if (typeof openCreateModal === 'function') {
                            openCreateModal();
                        }
                    }
                });
                console.log('Anonymous card input handlers attached');
            }
            
            // Ensure all buttons with onclick work
            const buttons = document.querySelectorAll('button[onclick*="openCreateModal"]');
            buttons.forEach(button => {
                button.addEventListener('click', function(e) {
                    // Let onclick handle it, but ensure function exists
                    if (typeof openCreateModal === 'function') {
                        // Function exists, onclick will work
                    } else {
                        e.preventDefault();
                        console.error('openCreateModal function not found');
                    }
                });
            });
            
            // Test button functionality
            console.log('Tip Portal: All buttons initialized');
            console.log('openCreateModal available:', typeof openCreateModal === 'function');
            console.log('Modal element exists:', document.getElementById('tipModal') !== null);
            console.log('Anonymous card exists:', document.querySelector('.anonymous-card') !== null);

            var tipPortalBtn = document.getElementById('toolbarTipPortalBtn');
            if (tipPortalBtn) {
                tipPortalBtn.addEventListener('click', function(e) {
                    if (typeof showSuccessMessage === 'function') {
                        showSuccessMessage();
                    }
                });
            }
        });
    </script>
<?php if (!empty($message)): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotification === 'function') {
            showNotification('<?php echo htmlspecialchars($message, ENT_QUOTES); ?>', '<?php echo $messageType ?: 'info'; ?>');
        }
        var createBtn = document.getElementById('createTipBtn');
        if (createBtn && typeof openCreateModal === 'function') {
            createBtn.addEventListener('click', function(e) { openCreateModal(); });
        }
    });
</script>
<?php endif; ?>
</body>
</html>
