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

// Create events table if it doesn't exist
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INT AUTO_INCREMENT PRIMARY KEY,
        title VARCHAR(255) NOT NULL,
        description TEXT,
        event_date DATE NOT NULL,
        event_time TIME NOT NULL,
        location VARCHAR(255) NOT NULL,
        category VARCHAR(100),
        status ENUM('scheduled', 'ongoing', 'completed', 'cancelled') DEFAULT 'scheduled',
        created_by INT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
} catch (PDOException $e) {
    // Table might already exist, continue
}

// Handle form submissions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'create_event') {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $event_time = $_POST['event_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $category = trim($_POST['category'] ?? '');
        
        if (empty($title) || empty($event_date) || empty($event_time) || empty($location)) {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        } else {
            try {
                $insert = $pdo->prepare("INSERT INTO events (title, description, event_date, event_time, location, category, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
                $insert->execute([$title, $description, $event_date, $event_time, $location, $category, $user_id]);
                $message = 'Event created successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error creating event: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
    
    if ($action === 'update_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $event_date = $_POST['event_date'] ?? '';
        $event_time = $_POST['event_time'] ?? '';
        $location = trim($_POST['location'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $status = $_POST['status'] ?? 'scheduled';
        
        if ($event_id > 0 && !empty($title) && !empty($event_date) && !empty($event_time) && !empty($location)) {
            try {
                $update = $pdo->prepare("UPDATE events SET title = ?, description = ?, event_date = ?, event_time = ?, location = ?, category = ?, status = ? WHERE id = ?");
                $update->execute([$title, $description, $event_date, $event_time, $location, $category, $status, $event_id]);
                $message = 'Event updated successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error updating event: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill in all required fields.';
            $messageType = 'error';
        }
    }
    
    if ($action === 'delete_event') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            try {
                $delete = $pdo->prepare("DELETE FROM events WHERE id = ?");
                $delete->execute([$event_id]);
                $message = 'Event deleted successfully!';
                $messageType = 'success';
            } catch (PDOException $e) {
                $message = 'Error deleting event: ' . $e->getMessage();
                $messageType = 'error';
            }
        }
    }
}

// Get filter parameters
$filter_status = $_GET['status'] ?? 'all';
$filter_category = $_GET['category'] ?? 'all';
$search = trim($_GET['search'] ?? '');

// Build query
$whereClauses = [];
$params = [];

if ($filter_status !== 'all') {
    $whereClauses[] = "status = ?";
    $params[] = $filter_status;
}

if ($filter_category !== 'all' && !empty($filter_category)) {
    $whereClauses[] = "category = ?";
    $params[] = $filter_category;
}

if (!empty($search)) {
    $whereClauses[] = "(title LIKE ? OR description LIKE ? OR location LIKE ?)";
    $searchParam = "%$search%";
    $params[] = $searchParam;
    $params[] = $searchParam;
    $params[] = $searchParam;
}

$whereSQL = !empty($whereClauses) ? "WHERE " . implode(" AND ", $whereClauses) : "";

// Fetch events
try {
    $eventsQuery = "SELECT e.*, u.first_name, u.last_name 
                    FROM events e 
                    LEFT JOIN users u ON e.created_by = u.id 
                    $whereSQL 
                    ORDER BY e.event_date DESC, e.event_time DESC";
    $eventsStmt = $pdo->prepare($eventsQuery);
    $eventsStmt->execute($params);
    $events = $eventsStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $events = [];
}

// Get statistics
try {
    $totalEvents = $pdo->query("SELECT COUNT(*) FROM events")->fetchColumn();
    $scheduledEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE status = 'scheduled'")->fetchColumn();
    $upcomingEvents = $pdo->query("SELECT COUNT(*) FROM events WHERE event_date >= CURDATE() AND status = 'scheduled'")->fetchColumn();
    $categories = $pdo->query("SELECT DISTINCT category FROM events WHERE category IS NOT NULL AND category != ''")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $totalEvents = 0;
    $scheduledEvents = 0;
    $upcomingEvents = 0;
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Event Scheduling - CPAS</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
    <style>
        .content-wrapper {
            padding: 32px;
        }
        
        .page-header {
            margin-bottom: 24px;
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
        
        .events-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .event-card {
            background: var(--glass-bg);
            backdrop-filter: var(--glass-blur);
            border: 1px solid var(--glass-border);
            border-radius: 16px;
            padding: 24px;
            box-shadow: var(--glass-shadow);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .event-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
        }
        
        .event-header {
            display: flex;
            justify-content: space-between;
            align-items: start;
            margin-bottom: 16px;
        }
        
        .event-title {
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 8px;
        }
        
        .event-category {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
            background: rgba(197, 17, 179, 0.15);
            color: var(--primary-color);
        }
        
        .event-status {
            padding: 4px 12px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-scheduled {
            background: rgba(59, 130, 246, 0.15);
            color: #2563eb;
        }
        
        .status-ongoing {
            background: rgba(16, 185, 129, 0.15);
            color: #047857;
        }
        
        .status-completed {
            background: rgba(107, 114, 128, 0.15);
            color: #374151;
        }
        
        .status-cancelled {
            background: rgba(239, 68, 68, 0.15);
            color: #b91c1c;
        }
        
        .event-details {
            margin-bottom: 16px;
        }
        
        .event-detail-item {
            display: flex;
            align-items: center;
            gap: 8px;
            margin-bottom: 8px;
            color: var(--text-light);
            font-size: 14px;
        }
        
        .event-description {
            color: var(--text-color);
            font-size: 14px;
            line-height: 1.6;
            margin-bottom: 16px;
        }
        
        .event-actions {
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
            font-weight: 500;
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
            border: 1px solid rgba(0,0,0,0.1);
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
        
        @media (max-width: 768px) {
            .floating-create-btn {
                bottom: 24px;
                right: 24px;
                width: 56px;
                height: 56px;
                font-size: 24px;
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
                        <a href="Event Sheduling.php" class="submenu-item active">Event Scheduling</a>
                        <a href="#" class="submenu-item">Feedback</a>
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
                        <h1 style="font-size: 24px; font-weight: 700;">Event Scheduling</h1>
                        <p style="color: var(--text-light);">Manage community events and awareness programs</p>
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
                        <div class="stat-label">Total Events</div>
                        <div class="stat-value"><?php echo number_format($totalEvents); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Scheduled</div>
                        <div class="stat-value"><?php echo number_format($scheduledEvents); ?></div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-label">Upcoming</div>
                        <div class="stat-value"><?php echo number_format($upcomingEvents); ?></div>
                    </div>
                </div>
                
                <div class="filters-section">
                    <form method="get" class="filters-grid">
                        <div class="filter-group">
                            <label>Status</label>
                            <select name="status">
                                <option value="all" <?php echo $filter_status === 'all' ? 'selected' : ''; ?>>All Status</option>
                                <option value="scheduled" <?php echo $filter_status === 'scheduled' ? 'selected' : ''; ?>>Scheduled</option>
                                <option value="ongoing" <?php echo $filter_status === 'ongoing' ? 'selected' : ''; ?>>Ongoing</option>
                                <option value="completed" <?php echo $filter_status === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                <option value="cancelled" <?php echo $filter_status === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Category</label>
                            <select name="category">
                                <option value="all" <?php echo $filter_category === 'all' ? 'selected' : ''; ?>>All Categories</option>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo $filter_category === $cat ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($cat); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>Search</label>
                            <input type="text" name="search" placeholder="Search events..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="filter-group">
                            <button type="submit" class="btn btn-primary" style="width: 100%;">
                                <i class='bx bx-search'></i> Filter
                            </button>
                        </div>
                    </form>
                </div>
                
                <div class="create-button-section">
                    <h2 style="font-size: 20px; font-weight: 600; margin: 0;">Events</h2>
                    <button class="create-btn-large" onclick="openCreateModal()">
                        <i class='bx bx-plus'></i> Create New Event
                    </button>
                </div>
                
                <?php if (empty($events)): ?>
                    <div class="create-btn-section" style="text-align: center; padding: 48px 24px;">
                        <i class='bx bx-calendar-x' style="font-size: 64px; color: var(--text-light); margin-bottom: 16px; display: block; opacity: 0.5;"></i>
                        <h3 style="margin: 0 0 8px 0; font-size: 20px; color: var(--text-color);">No events found</h3>
                        <p style="color: var(--text-light); margin-bottom: 24px;">Create your first event to get started.</p>
                        <button class="create-btn-large" onclick="openCreateModal()">
                            <i class='bx bx-plus'></i> Create Your First Event
                        </button>
                    </div>
                <?php else: ?>
                    <div class="events-grid">
                        <?php foreach ($events as $event): ?>
                            <div class="event-card">
                                <div class="event-header">
                                    <div>
                                        <div class="event-title"><?php echo htmlspecialchars($event['title']); ?></div>
                                        <?php if (!empty($event['category'])): ?>
                                            <span class="event-category"><?php echo htmlspecialchars($event['category']); ?></span>
                                        <?php endif; ?>
                                    </div>
                                    <span class="event-status status-<?php echo htmlspecialchars($event['status']); ?>">
                                        <?php echo ucfirst($event['status']); ?>
                                    </span>
                                </div>
                                
                                <?php if (!empty($event['description'])): ?>
                                    <div class="event-description">
                                        <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <div class="event-details">
                                    <div class="event-detail-item">
                                        <i class='bx bx-calendar'></i>
                                        <span><?php echo date('F d, Y', strtotime($event['event_date'])); ?></span>
                                    </div>
                                    <div class="event-detail-item">
                                        <i class='bx bx-time'></i>
                                        <span><?php echo date('g:i A', strtotime($event['event_time'])); ?></span>
                                    </div>
                                    <div class="event-detail-item">
                                        <i class='bx bx-map'></i>
                                        <span><?php echo htmlspecialchars($event['location']); ?></span>
                                    </div>
                                    <?php if (!empty($event['first_name'])): ?>
                                        <div class="event-detail-item">
                                            <i class='bx bx-user'></i>
                                            <span>Created by <?php echo htmlspecialchars($event['first_name'] . ' ' . $event['last_name']); ?></span>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                
                                <div class="event-actions">
                                    <button class="btn btn-secondary" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($event)); ?>)">
                                        <i class='bx bx-edit'></i> Edit
                                    </button>
                                    <form method="post" style="display: inline;" onsubmit="return confirm('Are you sure you want to delete this event?');">
                                        <input type="hidden" name="action" value="delete_event">
                                        <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                        <button type="submit" class="btn btn-danger">
                                            <i class='bx bx-trash'></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Create/Edit Modal -->
    <div id="eventModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Create New Event</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form method="post" id="eventForm">
                <input type="hidden" name="action" id="formAction" value="create_event">
                <input type="hidden" name="event_id" id="eventId">
                
                <div class="form-group">
                    <label>Event Title *</label>
                    <input type="text" name="title" id="eventTitle" required>
                </div>
                
                <div class="form-group">
                    <label>Description</label>
                    <textarea name="description" id="eventDescription"></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px;">
                    <div class="form-group">
                        <label>Event Date *</label>
                        <input type="date" name="event_date" id="eventDate" required>
                    </div>
                    <div class="form-group">
                        <label>Event Time *</label>
                        <input type="time" name="event_time" id="eventTime" required>
                    </div>
                </div>
                
                <div class="form-group">
                    <label>Location *</label>
                    <input type="text" name="location" id="eventLocation" required>
                </div>
                
                <div class="form-group">
                    <label>Category</label>
                    <input type="text" name="category" id="eventCategory" placeholder="e.g., Community Meeting, Training, Awareness">
                </div>
                
                <div class="form-group" id="statusGroup" style="display: none;">
                    <label>Status</label>
                    <select name="status" id="eventStatus">
                        <option value="scheduled">Scheduled</option>
                        <option value="ongoing">Ongoing</option>
                        <option value="completed">Completed</option>
                        <option value="cancelled">Cancelled</option>
                    </select>
                </div>
                
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class='bx bx-save'></i> Save Event
                    </button>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Floating Create Button -->
    <button class="floating-create-btn" onclick="openCreateModal()" title="Create New Event">
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
            document.getElementById('modalTitle').textContent = 'Create New Event';
            document.getElementById('formAction').value = 'create_event';
            document.getElementById('eventForm').reset();
            document.getElementById('eventId').value = '';
            document.getElementById('statusGroup').style.display = 'none';
            document.getElementById('eventModal').classList.add('active');
        }
        
        function openEditModal(event) {
            document.getElementById('modalTitle').textContent = 'Edit Event';
            document.getElementById('formAction').value = 'update_event';
            document.getElementById('eventId').value = event.id;
            document.getElementById('eventTitle').value = event.title || '';
            document.getElementById('eventDescription').value = event.description || '';
            document.getElementById('eventDate').value = event.event_date || '';
            document.getElementById('eventTime').value = event.event_time || '';
            document.getElementById('eventLocation').value = event.location || '';
            document.getElementById('eventCategory').value = event.category || '';
            document.getElementById('eventStatus').value = event.status || 'scheduled';
            document.getElementById('statusGroup').style.display = 'block';
            document.getElementById('eventModal').classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('eventModal').classList.remove('active');
        }
        
        // Close modal on outside click
        document.getElementById('eventModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });
        
        // Set minimum date to today
        document.getElementById('eventDate').setAttribute('min', new Date().toISOString().split('T')[0]);
    </script>
    
    <!-- Floating Create Button -->
    <button class="floating-create-btn" onclick="openCreateModal()" title="Create New Event">
        <i class='bx bx-plus'></i>
    </button>
</body>
</html>

