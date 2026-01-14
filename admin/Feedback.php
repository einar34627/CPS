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

// Create feedback table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS feedback (
        id INT AUTO_INCREMENT PRIMARY KEY,
        subject VARCHAR(255) NOT NULL,
        message TEXT NOT NULL,
        category ENUM('Complaint', 'Suggestion', 'Compliment', 'Question', 'Other') DEFAULT 'Other',
        rating INT DEFAULT NULL,
        status ENUM('pending', 'reviewed', 'resolved') DEFAULT 'pending',
        admin_response TEXT DEFAULT NULL,
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
    
    if ($action === 'create_feedback') {
        $subject = trim($_POST['subject'] ?? '');
        $message_text = trim($_POST['message'] ?? '');
        $category = $_POST['category'] ?? 'Other';
        $rating = !empty($_POST['rating']) ? (int)$_POST['rating'] : null;
        
        if (empty($subject) || empty($message_text)) {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                $insert = $pdo->prepare("INSERT INTO feedback (subject, message, category, rating, submitted_by) VALUES (?, ?, ?, ?, ?)");
                $insert->execute([$subject, $message_text, $category, $rating, $user_id]);
                $message = 'Feedback submitted successfully! Thank you for your input.';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error submitting feedback: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'update_feedback') {
        $feedback_id = (int)($_POST['feedback_id'] ?? 0);
        $status = $_POST['status'] ?? 'pending';
        $admin_response = trim($_POST['admin_response'] ?? '');
        
        if ($feedback_id > 0) {
            try {
                $update = $pdo->prepare("UPDATE feedback SET status = ?, admin_response = ? WHERE id = ?");
                $update->execute([$status, $admin_response, $feedback_id]);
                $message = 'Feedback updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating feedback: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'delete_feedback') {
        $feedback_id = (int)($_POST['feedback_id'] ?? 0);
        
        if ($feedback_id > 0) {
            try {
                $delete = $pdo->prepare("DELETE FROM feedback WHERE id = ?");
                $delete->execute([$feedback_id]);
                $message = 'Feedback deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting feedback: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'bulk_delete') {
        $feedback_ids = $_POST['feedback_ids'] ?? [];
        
        if (!empty($feedback_ids) && is_array($feedback_ids)) {
            try {
                $placeholders = str_repeat('?,', count($feedback_ids) - 1) . '?';
                $delete = $pdo->prepare("DELETE FROM feedback WHERE id IN ($placeholders)");
                $delete->execute($feedback_ids);
                $count = $delete->rowCount();
                $message = "$count feedback item(s) deleted successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting feedback: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please select at least one feedback item to delete.';
            $messageType = 'error';
        }
    }
    
    if ($action === 'bulk_update_status') {
        $feedback_ids = $_POST['feedback_ids'] ?? [];
        $status = $_POST['status'] ?? 'pending';
        
        if (!empty($feedback_ids) && is_array($feedback_ids)) {
            try {
                $placeholders = str_repeat('?,', count($feedback_ids) - 1) . '?';
                $update = $pdo->prepare("UPDATE feedback SET status = ? WHERE id IN ($placeholders)");
                $params = array_merge([$status], $feedback_ids);
                $update->execute($params);
                $count = $update->rowCount();
                $message = "$count feedback item(s) updated to " . ucfirst($status) . " successfully!";
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating feedback: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please select at least one feedback item to update.';
            $messageType = 'error';
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$query = "SELECT f.*, u.first_name, u.last_name, u.email 
          FROM feedback f 
          LEFT JOIN users u ON f.submitted_by = u.id 
          WHERE 1=1";

$params = [];

if ($filter_status !== 'all') {
    $query .= " AND f.status = ?";
    $params[] = $filter_status;
}

if ($filter_category !== 'all') {
    $query .= " AND f.category = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $query .= " AND (f.subject LIKE ? OR f.message LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$query .= " ORDER BY f.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$feedbacks = $stmt->fetchAll();

// Get statistics
$totalFeedback = $pdo->query("SELECT COUNT(*) FROM feedback")->fetchColumn();
$pendingFeedback = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status = 'pending'")->fetchColumn();
$resolvedFeedback = $pdo->query("SELECT COUNT(*) FROM feedback WHERE status = 'resolved'")->fetchColumn();

// Get unique categories
$categories = $pdo->query("SELECT DISTINCT category FROM feedback WHERE category IS NOT NULL ORDER BY category")->fetchAll(PDO::FETCH_COLUMN);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feedback - CPAS</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet' integrity='sha512-cD8/5tIGaFbkZqX8N1XcNf1Den3yK7ZD7Uy4+7rgXm5uPJU2N2ZxNt5xQJNzHvqHiKm8bGR5x+7g1X8k7gZxqg==' crossorigin='anonymous'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
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
            speak: none;
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
        
        .feedbacks-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(400px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .feedback-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--glass-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .feedback-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .feedback-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }
        
        .feedback-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .feedback-category {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(197, 17, 179, 0.15);
            color: var(--primary-color);
        }
        
        .feedback-status {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending {
            background: rgba(251, 191, 36, 0.15);
            color: #b45309;
        }
        
        .status-reviewed {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .status-resolved {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .feedback-message {
            margin-bottom: 16px;
            color: var(--text-color);
            line-height: 1.6;
        }
        
        .feedback-details {
            margin-bottom: 16px;
        }
        
        .feedback-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            font-size: 14px;
            color: var(--text-light);
        }
        
        .feedback-detail-item i {
            font-size: 18px;
        }
        
        .rating-stars {
            display: flex;
            gap: 4px;
            margin-top: 8px;
        }
        
        .rating-stars i {
            font-size: 18px;
            color: #fbbf24;
        }
        
        .rating-stars i.bx-star {
            color: #d1d5db;
        }
        
        .admin-response {
            background: rgba(59, 130, 246, 0.1);
            border-left: 4px solid #2563eb;
            padding: 12px;
            border-radius: 8px;
            margin-top: 16px;
        }
        
        .admin-response-label {
            font-size: 12px;
            font-weight: 600;
            color: #2563eb;
            margin-bottom: 8px;
        }
        
        .admin-response-text {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
        }
        
        .feedback-actions {
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
        
        .feedback-card {
            position: relative;
        }
        
        .feedback-card.selected {
            border: 2px solid var(--primary-color);
            box-shadow: 0 0 0 4px rgba(197, 17, 179, 0.2);
        }
        
        .feedback-select-checkbox {
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

        .create-btn-small {
            padding: 8px 16px;
            border-radius: 10px;
            border: none;
            cursor: pointer;
            font-size: 13px;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            box-shadow: 0 3px 10px rgba(197, 17, 179, 0.25);
        }

        .create-btn-small:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 14px rgba(197, 17, 179, 0.35);
        }

        .create-btn-small i {
            font-size: 16px;
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
        
        .rating-input {
            display: flex;
            gap: 8px;
            align-items: center;
        }
        
        .rating-input i {
            font-size: 24px;
            cursor: pointer;
            color: #d1d5db;
            transition: color 0.2s;
        }
        
        .rating-input i.active {
            color: #fbbf24;
        }
        
        @media (max-width: 768px) {
            .floating-create-btn {
                bottom: 24px;
                right: 24px;
                width: 56px;
                height: 56px;
                font-size: 24px;
            }
            
            .feedbacks-grid {
                grid-template-columns: 1fr;
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
                        <a href="Feedback.php" class="submenu-item active">Feedback</a>
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
                        <h1 style="font-size: 24px; font-weight: 700;">Feedback</h1>
                        <p style="color: var(--text-light);">Manage community feedback and suggestions</p>
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
            
            <div class="content-wrapper">
                <?php if ($message): ?>
                    <div class="message <?php echo $messageType; ?>">
                        <i class='bx <?php echo $messageType === 'success' ? 'bx-check-circle' : 'bx-error-circle'; ?>'></i>
                        <span><?php echo htmlspecialchars($message); ?></span>
                    </div>
                <?php endif; ?>
                
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-label">Total Feedback</div>
                        <div class="stat-value"><?php echo number_format($totalFeedback); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Pending</div>
                        <div class="stat-value"><?php echo number_format($pendingFeedback); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Resolved</div>
                        <div class="stat-value"><?php echo number_format($resolvedFeedback); ?></div>
                    </div>
                </div>
                
                <!-- Quick Action Buttons -->
                <div class="filters-section" style="margin-bottom: 24px;">
                    <h3 style="font-size: 18px; font-weight: 600; margin-bottom: 16px; color: var(--text-color);">Quick Actions</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 12px; align-items: center;">
                        <button class="btn btn-primary" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Submit Feedback
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='?status=resolved'">
                            <i class='bx bx-check-circle'></i> View Resolved
                        </button>
                        <button class="btn btn-warning" onclick="window.location.href='?status=pending'">
                            <i class='bx bx-time'></i> View Pending
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
                        <button class="btn btn-primary" onclick="handleClick()">
                            <i class='bx bx-bell'></i> Click Button
                        </button>
                        <button class="btn btn-primary btn-Small" onclick="openCreateModal()">
                            <i class='bx bx-download'></i> Small Button
                        </button>
                        <button class="btn btn-primary btn-Small" onclick="openCreateModal()">
                            <i class='bx bx-upload'></i> Small Button
                        </button>
                        <button class="btn btn-primary btn-icon-only" title="Add" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i>
                        </button>
                        <button class="btn btn-success btn-icon-only" title="Check" onclick="window.location.href='?status=resolved'">
                            <i class='bx bx-check'></i>
                        </button>
                        <button class="btn btn-danger btn-icon-only" title="Delete" onclick="alert('Delete function - select a feedback item to delete')">
                            <i class='bx bx-trash'></i>
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
                                <option value="reviewed" <?php echo $filter_status === 'reviewed' ? 'selected' : ''; ?>>Reviewed</option>
                                <option value="resolved" <?php echo $filter_status === 'resolved' ? 'selected' : ''; ?>>Resolved</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <option value="Complaint" <?php echo $filter_category === 'Complaint' ? 'selected' : ''; ?>>Complaint</option>
                                <option value="Suggestion" <?php echo $filter_category === 'Suggestion' ? 'selected' : ''; ?>>Suggestion</option>
                                <option value="Compliment" <?php echo $filter_category === 'Compliment' ? 'selected' : ''; ?>>Compliment</option>
                                <option value="Question" <?php echo $filter_category === 'Question' ? 'selected' : ''; ?>>Question</option>
                                <option value="Other" <?php echo $filter_category === 'Other' ? 'selected' : ''; ?>>Other</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Search feedback..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class='bx bx-search'></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="create-button-section">
                    <h2 style="font-size: 20px; font-weight: 600; margin: 0;">Feedback Entries</h2>
                    <div style="display: flex; gap: 12px; flex-wrap: wrap;">
                        <button class="create-btn-large" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Submit Feedback
                        </button>
                        <button class="create-btn-small" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Small Button
                        </button>
                        <button class="btn btn-success" onclick="window.location.href='?status=resolved'">
                            <i class='bx bx-check-circle'></i> View Resolved
                        </button>
                        <button class="btn btn-warning" onclick="window.location.href='?status=pending'">
                            <i class='bx bx-time'></i> View Pending
                        </button>
                        <button class="btn btn-info" onclick="window.location.href='?status=all'">
                            <i class='bx bx-list-ul'></i> View All
                        </button>
                        <button class="btn btn-secondary" onclick="window.print()">
                            <i class='bx bx-printer'></i> Print
                        </button>
                        <button class="btn btn-outline" onclick="exportFeedback()">
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
                        <button class="btn btn-primary btn-small" onclick="selectAll()" title="Select All Items">
                            <i class='bx bx-check-square'></i> Select All
                        </button>
                        <button class="btn btn-secondary btn-small" onclick="deselectAll()" title="Deselect All Items">
                            <i class='bx bx-square'></i> Deselect All
                        </button>
                        <button class="btn btn-info btn-small" onclick="selectByStatus('pending')" title="Select Pending Items">
                            <i class='bx bx-time'></i> Select Pending
                        </button>
                        <button class="btn btn-success btn-small" onclick="selectByStatus('resolved')" title="Select Resolved Items">
                            <i class='bx bx-check-circle'></i> Select Resolved
                        </button>
                        <button class="btn btn-warning btn-small" onclick="selectByStatus('reviewed')" title="Select Reviewed Items">
                            <i class='bx bx-time-five'></i> Select Reviewed
                        </button>
                        <button class="btn btn-outline btn-small" onclick="invertSelection()" title="Invert Selection">
                            <i class='bx bx-refresh'></i> Invert Selection
                        </button>
                    </div>
                    <div class="bulk-actions" id="bulkActions">
                        <span class="selected-count" id="selectedCount">0 selected</span>
                        <button class="btn btn-success btn-small" onclick="bulkUpdateStatus('resolved')" title="Mark Selected as Resolved">
                            <i class='bx bx-check'></i> Mark Resolved
                        </button>
                        <button class="btn btn-warning btn-small" onclick="bulkUpdateStatus('reviewed')" title="Mark Selected as Reviewed">
                            <i class='bx bx-time-five'></i> Mark Reviewed
                        </button>
                        <button class="btn btn-info btn-small" onclick="bulkUpdateStatus('pending')" title="Mark Selected as Pending">
                            <i class='bx bx-history'></i> Mark Pending
                        </button>
                        <button class="btn btn-danger btn-small" onclick="bulkDelete()" title="Delete Selected Items">
                            <i class='bx bx-trash'></i> Delete Selected
                        </button>
                    </div>
                </div>
                
                <?php if (empty($feedbacks)): ?>
                    <div class="create-btn-section" style="text-align: center; padding: 48px 24px;">
                        <i class='bx bx-message-square-dots' style="font-size: 64px; color: var(--text-light); margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 8px 0; font-size: 20px; color: var(--text-color);">No feedback found</h3>
                        <p style="color: var(--text-light); margin-bottom: 24px;">Be the first to share your thoughts and suggestions.</p>
                        <button class="create-btn-large" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Submit Your First Feedback
                        </button>
                    </div>
                <?php else: ?>
                    <div class="feedbacks-grid">
                        <?php foreach ($feedbacks as $feedback): ?>
                            <div class="feedback-card" data-feedback-id="<?php echo $feedback['id']; ?>">
                                <input type="checkbox" 
                                       class="feedback-select-checkbox" 
                                       data-feedback-id="<?php echo $feedback['id']; ?>"
                                       onchange="updateSelection()">
                                <div class="feedback-header">
                                    <div>
                                        <div class="feedback-title"><?php echo htmlspecialchars($feedback['subject']); ?></div>
                                        <span class="feedback-category"><?php echo htmlspecialchars($feedback['category']); ?></span>
                                    </div>
                                    <span class="feedback-status status-<?php echo htmlspecialchars($feedback['status']); ?>">
                                        <?php echo ucfirst($feedback['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="feedback-message">
                                    <?php echo nl2br(htmlspecialchars($feedback['message'])); ?>
                                </div>
                                
                                <?php if ($feedback['rating']): ?>
                                    <div class="rating-stars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <i class='bx <?php echo $i <= $feedback['rating'] ? 'bxs-star' : 'bx-star'; ?>'></i>
                                        <?php endfor; ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="feedback-details">
                                    <?php if (!empty($feedback['first_name'])): ?>
                                        <div class="feedback-detail-item">
                                            <i class='bx bx-user'></i>
                                            <span><?php echo htmlspecialchars($feedback['first_name'] . ' ' . $feedback['last_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <div class="feedback-detail-item">
                                        <i class='bx bx-calendar'></i>
                                        <span><?php echo date('F d, Y g:i A', strtotime($feedback['created_at'])); ?></span>
                                    </div>
                                </div>
                                
                                <?php if (!empty($feedback['admin_response'])): ?>
                                    <div class="admin-response">
                                        <div class="admin-response-label">Admin Response:</div>
                                        <div class="admin-response-text"><?php echo nl2br(htmlspecialchars($feedback['admin_response'])); ?></div>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if ($role === 'ADMIN' || $role === 'EMPLOYEE'): ?>
                                    <div class="feedback-actions">
                                        <button class="btn btn-secondary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($feedback)); ?>)">
                                            <i class='bx bx-edit'></i> <?php echo empty($feedback['admin_response']) ? 'Respond' : 'Update'; ?>
                                        </button>
                                        <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this feedback?');">
                                            <input type="hidden" name="action" value="delete_feedback">
                                            <input type="hidden" name="feedback_id" value="<?php echo $feedback['id']; ?>">
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
    <div id="feedbackModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Submit Feedback</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="post" id="feedbackForm">
                <input type="hidden" name="action" id="formAction" value="create_feedback">
                <input type="hidden" name="feedback_id" id="feedbackId">
                
                <div class="form-group">
                    <label>Subject *</label>
                    <input type="text" name="subject" id="feedbackSubject" required>
                </div>
                
                <div class="form-group">
                    <label>Message *</label>
                    <textarea name="message" id="feedbackMessage" required></textarea>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <select name="category" id="feedbackCategory">
                        <option value="Complaint">Complaint</option>
                        <option value="Suggestion">Suggestion</option>
                        <option value="Compliment">Compliment</option>
                        <option value="Question">Question</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                
                <div class="form-group" id="ratingGroup">
                    <label>Rating (Optional)</label>
                    <div class="rating-input">
                        <i class='bx bx-star' data-rating="1"></i>
                        <i class='bx bx-star' data-rating="2"></i>
                        <i class='bx bx-star' data-rating="3"></i>
                        <i class='bx bx-star' data-rating="4"></i>
                        <i class='bx bx-star' data-rating="5"></i>
                    </div>
                    <input type="hidden" name="rating" id="ratingValue">
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label>Status</label>
                    <select name="status" id="feedbackStatus">
                        <option value="pending">Pending</option>
                        <option value="reviewed">Reviewed</option>
                        <option value="resolved">Resolved</option>
                    </select>
                </div>
                
                <div class="form-group" id="responseGroup" style="display: none;">
                    <label>Admin Response</label>
                    <textarea name="admin_response" id="adminResponse"></textarea>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> <span id="submitText">Submit Feedback</span>
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Floating Create Button -->
    <button class="floating-create-btn" onclick="openCreateModal()" title="Submit Feedback">
        <i class='bx bx-plus'></i>
    </button>
    <script>
        console.log('Feedback page loaded');
        function handleClick() { alert('Button clicked'); console.log('Click button'); }
        
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const arrow = submenu.previousElementSibling.querySelector('.dropdown-arrow');
            submenu.classList.toggle('active');
            if (arrow) arrow.classList.toggle('rotated');
        }
        
        function openCreateModal() {
            console.log('openCreateModal called');
            try {
                const modal = document.getElementById('feedbackModal');
                if (!modal) {
                    console.warn('Feedback modal not found!');
                    alert('Error: Feedback form not found. Please refresh the page.');
                    return;
                }
                
                document.getElementById('modalTitle').textContent = 'Submit Feedback';
                document.getElementById('formAction').value = 'create_feedback';
                document.getElementById('submitText').textContent = 'Submit Feedback';
                document.getElementById('feedbackForm').reset();
                document.getElementById('feedbackId').value = '';
                document.getElementById('statusGroup').style.display = 'none';
                document.getElementById('responseGroup').style.display = 'none';
                document.getElementById('ratingGroup').style.display = 'block';
                
                // Reset rating
                resetRating();
                
                // Re-initialize rating after modal opens
                setTimeout(function() {
                    initRating();
                }, 100);
                
                modal.classList.add('active');
                console.log('Modal opened successfully');
            } catch (error) {
                console.error('Error opening modal:', error);
                alert('Error opening feedback form. Please refresh the page.');
            }
        }
        
        function openEditModal(feedback) {
            try {
                // Handle both object and string input
                let feedbackData = feedback;
                if (typeof feedback === 'string') {
                    try {
                        feedbackData = JSON.parse(feedback);
                    } catch (e) {
                        console.error('Error parsing feedback data:', e);
                        alert('Error loading feedback data. Please try again.');
                        return;
                    }
                }
                
                document.getElementById('modalTitle').textContent = 'Update Feedback';
                document.getElementById('formAction').value = 'update_feedback';
                document.getElementById('submitText').textContent = 'Update Feedback';
                document.getElementById('feedbackId').value = feedbackData.id || '';
                document.getElementById('feedbackSubject').value = feedbackData.subject || '';
                document.getElementById('feedbackMessage').value = feedbackData.message || '';
                document.getElementById('feedbackCategory').value = feedbackData.category || 'Other';
                document.getElementById('feedbackStatus').value = feedbackData.status || 'pending';
                document.getElementById('adminResponse').value = feedbackData.admin_response || '';
                document.getElementById('statusGroup').style.display = 'block';
                document.getElementById('responseGroup').style.display = 'block';
                document.getElementById('ratingGroup').style.display = 'none';
                document.getElementById('feedbackModal').classList.add('active');
            } catch (error) {
                console.error('Error opening edit modal:', error);
                alert('Error opening feedback form. Please refresh the page.');
            }
        }
        
        function closeModal() {
            document.getElementById('feedbackModal').classList.remove('active');
        }
        
        // Close modal on outside click and ESC key
        function initModalHandlers() {
            const modal = document.getElementById('feedbackModal');
            if (modal) {
                modal.addEventListener('click', function(e) {
                    if (e.target === this) {
                        closeModal();
                    }
                });
            }
            
            // Close modal on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    const modal = document.getElementById('feedbackModal');
                    if (modal && modal.classList.contains('active')) {
                        closeModal();
                    }
                }
            });
        }
        
        // Rating functionality
        function resetRating() {
            const ratingStars = document.querySelectorAll('.rating-input i');
            const ratingValue = document.getElementById('ratingValue');
            
            if (ratingStars.length && ratingValue) {
                ratingStars.forEach(star => {
                    star.classList.remove('bxs-star', 'active');
                    star.classList.add('bx-star');
                    star.style.color = '#d1d5db';
                });
                ratingValue.value = '';
            }
        }
        
        // Track if rating is initialized to prevent duplicate listeners
        let ratingInitialized = false;
        
        function initRating() {
            const ratingStars = document.querySelectorAll('.rating-input i');
            const ratingValue = document.getElementById('ratingValue');
            
            if (!ratingStars.length || !ratingValue) return;
            
            // Remove existing listeners if already initialized
            if (ratingInitialized) {
                ratingStars.forEach(star => {
                    star.replaceWith(star.cloneNode(true));
                });
            }
            
            // Get fresh references after clone
            const freshStars = document.querySelectorAll('.rating-input i');
            
            freshStars.forEach(star => {
                // Click handler for rating selection
                star.addEventListener('click', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    if (!isNaN(rating)) {
                        ratingValue.value = rating;
                        
                        freshStars.forEach((s, index) => {
                            if (index < rating) {
                                s.classList.remove('bx-star');
                                s.classList.add('bxs-star', 'active');
                                s.style.color = '#fbbf24';
                            } else {
                                s.classList.remove('bxs-star', 'active');
                                s.classList.add('bx-star');
                                s.style.color = '#d1d5db';
                            }
                        });
                    }
                });
                
                // Hover effect
                star.addEventListener('mouseenter', function() {
                    const rating = parseInt(this.getAttribute('data-rating'));
                    if (!isNaN(rating)) {
                        freshStars.forEach((s, index) => {
                            if (index < rating) {
                                s.style.color = '#fbbf24';
                            } else {
                                s.style.color = '#d1d5db';
                            }
                        });
                    }
                });
            });
            
            const ratingInput = document.querySelector('.rating-input');
            if (ratingInput) {
                ratingInput.addEventListener('mouseleave', function() {
                    const currentRating = parseInt(ratingValue.value) || 0;
                    freshStars.forEach((s, index) => {
                        if (index < currentRating) {
                            s.style.color = '#fbbf24';
                        } else {
                            s.style.color = '#d1d5db';
                        }
                    });
                });
            }
            
            ratingInitialized = true;
        }
        
        // Make functions globally available immediately
        window.openCreateModal = openCreateModal;
        window.openEditModal = openEditModal;
        window.closeModal = closeModal;
        window.exportFeedback = exportFeedback;
        
        // Initialize everything when page loads
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', function() {
                initRating();
                initModalHandlers();
                console.log('All button handlers initialized');
            });
        } else {
            // DOM already loaded
            initRating();
            initModalHandlers();
            console.log('All button handlers initialized');
        }
        
        // Export feedback function
        function exportFeedback() {
            const status = new URLSearchParams(window.location.search).get('status') || 'all';
            const category = new URLSearchParams(window.location.search).get('category') || 'all';
            window.location.href = 'Feedback.php?export=1&status=' + status + '&category=' + category;
        }
        
        // Selection functions
        function toggleSelectAll(checked) {
            const checkboxes = document.querySelectorAll('.feedback-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = checked;
                const card = checkbox.closest('.feedback-card');
                if (checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            updateSelection();
        }
        
        function selectAll() {
            document.getElementById('selectAll').checked = true;
            toggleSelectAll(true);
        }
        
        function deselectAll() {
            document.getElementById('selectAll').checked = false;
            toggleSelectAll(false);
        }
        
        function selectByStatus(status) {
            const checkboxes = document.querySelectorAll('.feedback-select-checkbox');
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.feedback-card');
                const statusBadge = card.querySelector('.feedback-status');
                
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
        
        function invertSelection() {
            const checkboxes = document.querySelectorAll('.feedback-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = !checkbox.checked;
                const card = checkbox.closest('.feedback-card');
                if (checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
            updateSelection();
        }
        
        function updateSelection() {
            const checkboxes = document.querySelectorAll('.feedback-select-checkbox');
            const checked = Array.from(checkboxes).filter(cb => cb.checked);
            const selectedCount = checked.length;
            
            // Update count display
            document.getElementById('selectedCount').textContent = selectedCount + ' selected';
            
            // Show/hide bulk actions
            const bulkActions = document.getElementById('bulkActions');
            if (selectedCount > 0) {
                bulkActions.classList.add('active');
            } else {
                bulkActions.classList.remove('active');
            }
            
            // Update select all checkbox
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
            
            // Update card selected state
            checkboxes.forEach(checkbox => {
                const card = checkbox.closest('.feedback-card');
                if (checkbox.checked) {
                    card.classList.add('selected');
                } else {
                    card.classList.remove('selected');
                }
            });
        }
        
        function getSelectedIds() {
            const checkboxes = document.querySelectorAll('.feedback-select-checkbox:checked');
            return Array.from(checkboxes).map(cb => cb.getAttribute('data-feedback-id'));
        }
        
        function bulkDelete() {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert('Please select at least one feedback item to delete.');
                return;
            }
            
            if (!confirm(`Are you sure you want to delete ${selectedIds.length} feedback item(s)? This action cannot be undone.`)) {
                return;
            }
            
            // Create form and submit
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
                input.name = 'feedback_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        function bulkUpdateStatus(status) {
            const selectedIds = getSelectedIds();
            if (selectedIds.length === 0) {
                alert('Please select at least one feedback item to update.');
                return;
            }
            
            const statusText = status.charAt(0).toUpperCase() + status.slice(1);
            if (!confirm(`Are you sure you want to mark ${selectedIds.length} feedback item(s) as ${statusText}?`)) {
                return;
            }
            
            // Create form and submit
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
                input.name = 'feedback_ids[]';
                input.value = id;
                form.appendChild(input);
            });
            
            document.body.appendChild(form);
            form.submit();
        }
        
        // Initialize selection handlers
        document.addEventListener('DOMContentLoaded', function() {
            // Add event listeners to checkboxes
            const checkboxes = document.querySelectorAll('.feedback-select-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', updateSelection);
            });
            
            // Verify Boxicons are loaded
            checkBoxiconsLoaded();
        });
        
        // Function to check if Boxicons are loaded and ensure icons are visible
        function checkBoxiconsLoaded() {
            // Wait a bit for fonts to load
            setTimeout(function() {
                // Ensure all icons are visible and properly styled
                const allIcons = document.querySelectorAll('.bx, i.bx, [class*="bx-"]');
                allIcons.forEach(icon => {
                    icon.style.display = 'inline-block';
                    icon.style.visibility = 'visible';
                    icon.style.fontFamily = 'boxicons';
                    
                    // Force re-render
                    icon.style.opacity = '1';
                });
                
                // Check if Boxicons CSS is loaded
                const boxiconsLink = document.querySelector('link[href*="boxicons"]');
                if (!boxiconsLink) {
                    console.warn('Boxicons link not found, adding fallback...');
                    const link = document.createElement('link');
                    link.rel = 'stylesheet';
                    link.href = 'https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css';
                    link.crossOrigin = 'anonymous';
                    document.head.appendChild(link);
                }
                
                console.log('Boxicons verification completed. Found', allIcons.length, 'icons.');
            }, 500);
        }
    </script>
</body>
</html>

