<?php

session_start();
require_once '../config/db_connection.php';


if (!isset($_SESSION['user_id'])) {
    header("Location: ../login.php");
    exit();
}

function cps_msg_key() {
    $dbn = isset($GLOBALS['dbname']) ? (string)$GLOBALS['dbname'] : 'cps';
    $usr = isset($GLOBALS['username']) ? (string)$GLOBALS['username'] : 'user';
    return hash('sha256', $dbn . '|' . $usr, true);
}
function cps_decrypt_text($b64, $ivb64) {
    $key = cps_msg_key();
    $cipher = base64_decode($b64, true);
    $iv = base64_decode($ivb64, true);
    if ($cipher === false || $iv === false) { return ''; }
    $plain = openssl_decrypt($cipher, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv);
    return $plain !== false ? $plain : '';
}

$user_id = $_SESSION['user_id'];
$query = "SELECT first_name, middle_name, last_name, role, avatar_url, email, username, contact, address, date_of_birth FROM users WHERE id = ?";
$stmt = $pdo->prepare($query);
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if ($user) {
    $first_name = htmlspecialchars($user['first_name']);
    $middle_name = htmlspecialchars($user['middle_name']);
    $last_name = htmlspecialchars($user['last_name']);
    $role = htmlspecialchars($user['role']);
    $avatar_rel = isset($user['avatar_url']) ? (string)$user['avatar_url'] : '';
    $avatar_path = ($avatar_rel !== '') ? ('../' . $avatar_rel) : '../img/rei.jfif';
    
    $full_name = $first_name;
    if (!empty($middle_name)) {
        $full_name .= " " . $middle_name;
    }
    $full_name .= " " . $last_name;
} else {

    $full_name = "User";
    $role = "TANOD";
    $avatar_path = '../img/rei.jfif';
}

$stmt = null;
?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_complaint'])) {
    header('Content-Type: application/json');
    $fid = isset($_POST['feedback_id']) ? (int)$_POST['feedback_id'] : 0;
    $nst = strtolower(trim($_POST['new_status'] ?? ''));
    $desc = trim($_POST['actions_taken'] ?? '');
    $allowed = ['pending','reviewed','resolved'];
    if ($fid > 0 && in_array($nst, $allowed, true)) {
        try {
            $stmtU = $pdo->prepare("UPDATE feedback SET status = ?, admin_response = ? WHERE id = ? AND category = 'Complaint'");
            $stmtU->execute([$nst, $desc, $fid]);
            $stmtU = null;
            echo json_encode(['ok'=>true,'status'=>$nst,'actions'=>$desc]);
        } catch (Exception $e) {
            echo json_encode(['ok'=>false,'error'=>'update_failed']);
        }
    } else {
        echo json_encode(['ok'=>false,'error'=>'invalid_input']);
    }
    exit();
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'role_messages_list') {
    header('Content-Type: application/json');
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
            id INT AUTO_INCREMENT PRIMARY KEY,
            sender_id INT NOT NULL,
            recipient_role ENUM('TANOD','SECRETARY','CAPTAIN') NOT NULL,
            message TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        try { $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'enc_message'"); $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC); if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN enc_message TEXT DEFAULT NULL"); } } catch (Exception $e) {}
        try { $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'iv'"); $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC); if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN iv VARCHAR(64) DEFAULT NULL"); } } catch (Exception $e) {}
        $stmt = $pdo->prepare("SELECT enc_message, iv, created_at FROM messages WHERE recipient_role = 'TANOD' ORDER BY created_at DESC, id DESC LIMIT 200");
        $stmt->execute([]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['message' => cps_decrypt_text($r['enc_message'] ?? '', $r['iv'] ?? ''), 'created_at' => $r['created_at']];
        }
        echo json_encode(['success'=>true,'messages'=>$out]);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'messages'=>[]]);
        exit();
    }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['request_footage'])) {
    header('Content-Type: application/json');
    $incident_ref = trim($_POST['incident_ref'] ?? '');
    $reason = trim($_POST['reason'] ?? '');
    if ($incident_ref === '') {
        echo json_encode(['ok'=>false,'error'=>'missing_incident']);
        exit();
    }
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS cctv_footage_requests (
            id INT AUTO_INCREMENT PRIMARY KEY,
            incident_ref VARCHAR(255) NOT NULL,
            reason TEXT,
            requested_by INT,
            requested_name VARCHAR(255),
            requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('pending','approved','rejected') DEFAULT 'pending'
        )");
        $stmtI = $pdo->prepare("INSERT INTO cctv_footage_requests (incident_ref, reason, requested_by, requested_name) VALUES (?, ?, ?, ?)");
        $stmtI->execute([$incident_ref, $reason, (int)$user_id, $full_name]);
        $stmtI = null;
        echo json_encode([
            'ok'=>true,
            'record'=>[
                'incident_ref'=>$incident_ref,
                'reason'=>$reason,
                'requested_name'=>$full_name,
                'requested_at'=>date('Y-m-d H:i:s'),
                'status'=>'pending'
            ]
        ]);
    } catch (Exception $e) {
        echo json_encode(['ok'=>false,'error'=>'insert_failed']);
    }
    exit();
}
?>
<?php
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $uid = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
    if ($uid <= 0) { echo json_encode(['success'=>false,'error'=>'Unauthorized']); exit(); }
    $action = $_POST['action'];
    try {
        if ($action === 'profile_get') {
            try {
                $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, username, email, contact, address, date_of_birth, role, avatar_url FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row) {
                    $stmt = $pdo->prepare("SELECT first_name, middle_name, last_name, username, email, contact, address, date_of_birth, role FROM users WHERE id = ?");
                    $stmt->execute([$uid]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
                    $row['avatar_url'] = null;
                }
                echo json_encode(['success'=>true,'profile'=>$row]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to load profile']);
                exit();
            }
        } elseif ($action === 'profile_update') {
            $first = trim($_POST['first_name'] ?? '');
            $middle = trim($_POST['middle_name'] ?? '');
            $last = trim($_POST['last_name'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $contact = trim($_POST['contact'] ?? '');
            $address = trim($_POST['address'] ?? '');
            $dob = trim($_POST['date_of_birth'] ?? '');
            if ($first === '' || $last === '' || $username === '' || $email === '' || $contact === '' || $address === '' || $dob === '') {
                echo json_encode(['success'=>false,'error'=>'Missing required fields']);
                exit();
            }
            try {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = ? OR username = ?) AND id <> ?");
                $dup->execute([$email, $username, $uid]);
                if ((int)$dup->fetchColumn() > 0) { echo json_encode(['success'=>false,'error'=>'Email or username already exists']); exit(); }
                $stmt = $pdo->prepare("UPDATE users SET first_name = ?, middle_name = ?, last_name = ?, username = ?, email = ?, contact = ?, address = ?, date_of_birth = ? WHERE id = ?");
                $stmt->execute([$first, $middle, $last, $username, $email, $contact, $address, $dob, $uid]);
                $_SESSION['user_email'] = $email;
                echo json_encode(['success'=>true]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to update profile']);
                exit();
            }
        } elseif ($action === 'profile_avatar_upload') {
            if (!isset($_FILES['avatar'])) { echo json_encode(['success'=>false,'error'=>'No file uploaded']); exit(); }
            $file = $_FILES['avatar'];
            if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) { echo json_encode(['success'=>false,'error'=>'Upload failed']); exit(); }
            $type = mime_content_type($file['tmp_name']);
            $allowed = ['image/jpeg','image/png','image/webp'];
            if (!in_array($type, $allowed, true)) { echo json_encode(['success'=>false,'error'=>'Invalid image type']); exit(); }
            $size = filesize($file['tmp_name']);
            if ($size > 5 * 1024 * 1024) { echo json_encode(['success'=>false,'error'=>'Image too large']); exit(); }
            $ext = $type === 'image/png' ? 'png' : ($type === 'image/webp' ? 'webp' : 'jpg');
            $root = dirname(__DIR__);
            $dir = $root . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'avatars';
            if (!is_dir($dir)) { @mkdir($dir, 0775, true); }
            $name = 'u'.$uid.'_'.bin2hex(random_bytes(6)).'.'.$ext;
            $dest = $dir . DIRECTORY_SEPARATOR . $name;
            if (!move_uploaded_file($file['tmp_name'], $dest)) { echo json_encode(['success'=>false,'error'=>'Failed to save image']); exit(); }
            try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS avatar_url VARCHAR(255) DEFAULT NULL"); } catch (Exception $e) {}
            $relative = 'uploads/avatars/'.$name;
            try {
                $stmt = $pdo->prepare("UPDATE users SET avatar_url = ? WHERE id = ?");
                $stmt->execute([$relative, $uid]);
                echo json_encode(['success'=>true,'avatar_url'=>$relative]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to update avatar']);
                exit();
            }
        } elseif ($action === 'security_toggle_2fa') {
            $enabled = isset($_POST['enabled']) && ($_POST['enabled'] === '1' || $_POST['enabled'] === 'true') ? 1 : 0;
            try {
                try { $pdo->exec("ALTER TABLE users ADD COLUMN IF NOT EXISTS two_factor_enabled TINYINT(1) DEFAULT 0"); } catch (Exception $e) {}
                $stmt = $pdo->prepare("UPDATE users SET two_factor_enabled = ? WHERE id = ?");
                $stmt->execute([$enabled, $uid]);
                echo json_encode(['success'=>true,'enabled'=>$enabled]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to update 2FA']);
                exit();
            }
        } elseif ($action === 'security_change_password') {
            $current = $_POST['current_password'] ?? '';
            $new = $_POST['new_password'] ?? '';
            if ($current === '' || $new === '') { echo json_encode(['success'=>false,'error'=>'Missing fields']); exit(); }
            try {
                $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                $row = $stmt->fetch(PDO::FETCH_ASSOC);
                if (!$row || !password_verify($current, $row['password'])) { echo json_encode(['success'=>false,'error'=>'Incorrect current password']); exit(); }
                $hash = password_hash($new, PASSWORD_DEFAULT, ['cost'=>12]);
                $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE id = ?");
                $stmt->execute([$hash, $uid]);
                echo json_encode(['success'=>true]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to change password']);
                exit();
            }
        } elseif ($action === 'security_change_email') {
            $newEmail = trim($_POST['new_email'] ?? '');
            if ($newEmail === '') { echo json_encode(['success'=>false,'error'=>'Missing email']); exit(); }
            try {
                $dup = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = ? AND id <> ?");
                $dup->execute([$newEmail, $uid]);
                if ((int)$dup->fetchColumn() > 0) { echo json_encode(['success'=>false,'error'=>'Email already exists']); exit(); }
                $stmt = $pdo->prepare("UPDATE users SET email = ? WHERE id = ?");
                $stmt->execute([$newEmail, $uid]);
                $_SESSION['user_email'] = $newEmail;
                echo json_encode(['success'=>true,'email'=>$newEmail]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to change email']);
                exit();
            }
        } elseif ($action === 'security_generate_key') {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, api_key_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $raw = 'sk_' . bin2hex(random_bytes(16));
                $hash = password_hash($raw, PASSWORD_DEFAULT, ['cost'=>12]);
                $stmt = $pdo->prepare("INSERT INTO api_keys (user_id, api_key_hash) VALUES (?, ?)");
                $stmt->execute([$uid, $hash]);
                echo json_encode(['success'=>true,'api_key'=>$raw]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to generate API key']);
                exit();
            }
        } elseif ($action === 'security_delete_account') {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS api_keys (id INT AUTO_INCREMENT PRIMARY KEY, user_id INT NOT NULL, api_key_hash VARCHAR(255) NOT NULL, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                $stmt = $pdo->prepare("DELETE FROM api_keys WHERE user_id = ?");
                $stmt->execute([$uid]);
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ?");
                $stmt->execute([$uid]);
                echo json_encode(['success'=>true]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'error'=>'Failed to delete account']);
                exit();
            }
        }
        echo json_encode(['success'=>false,'error'=>'Unknown action']);
        exit();
    } catch (Exception $e) {
        echo json_encode(['success'=>false,'error'=>'Server error']);
        exit();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Policing and Surveillance Management</title>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="icon" type="image/png" sizes="32x32" href="../img/cpas-logo.png">
    <link rel="stylesheet" href="../css/dashboard.css">
</head>
<body>
    <div class="dashboard-animation" id="dashboard-animation">
        <div class="animation-logo">
            <div class="animation-logo-icon">
                <img src="../img/cpas-logo.png" alt="Fire & Rescue Logo" style="width: 70px; height: 75px;">
            </div>
            <span class="animation-logo-text">Community Policing & Surveillance</span>
        </div>
        <div class="animation-progress">
            <div class="animation-progress-fill" id="animation-progress"></div>
        </div>
        <div class="animation-text" id="animation-text">Loading Dashboard...</div>
    </div>
    
    <div class="container">
        <!-- Sidebar -->
        <div class="sidebar">
            <!-- Logo -->
            <div class="logo">
                <div class="logo-icon">
                    <img src="../img/cpas-logo.png" alt="Fire & Rescue Logo" style="width: 40px; height: 45px;">
                </div>
                <span class="logo-text">Community Policing & Surveillance</span>
            </div>
            
          <!-- Menu Section -->
<div class="menu-section">
    <p class="menu-title">COMMUNITY POLICING AND SURVEILLANCE MANAGEMENT</p>
    
    <div class="menu-items">
        <a href="#" class="menu-item active" id="dashboard-menu">
            <div class="icon-box icon-bg-red">
                <i class='bx bxs-dashboard icon-red'></i>
            </div>
            <span class="font-medium">Dashboard</span>
        </a>
        <div class="menu-item" onclick="toggleSubmenu('fire-incident')">
            <div class="icon-box icon-bg-pink">
                <i class='bx  bxs-cctv icon-pink'></i>
            </div>
            <span class="font-medium">CCTV & Situation Awareness</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="fire-incident" class="submenu">
            <a href="#" class="submenu-item" id="nearby-cctv-link">Nearby CCTV Viewer</a>
            <a href="#" class="submenu-item" id="realtime-incident-alerts-link">Real-Time Incident Alerts</a>
            <a href="#" class="submenu-item" id="footage-request-log-link">Footage Request Log</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('volunteer')">
            <div class="icon-box icon-bg-blue">
                <i class='bx bxs-user-detail icon-blue'></i>
            </div>
            <span class="font-medium">Patrol Execution & Monitoring</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="volunteer" class="submenu">
            <a href="#" class="submenu-item" id="assigned-route-link">Assigned Patrol Route</a>
            <a href="#" class="submenu-item" id="patrol-activity-link">Patrol Activity Log</a>
            <a href="#" class="submenu-item" id="checkpoint-logging-link">Checkpoint Logging</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('inventory')">
            <div class="icon-box icon-bg-green">
                <i class='bx bxs-cube icon-green'></i>
            </div>
            <span class="font-medium">Community Interaction & Complaints</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="inventory" class="submenu">
            <a href="#" class="submenu-item" id="receive-complaints-link">Receive Community Complaints</a>
            <a href="#" class="submenu-item" id="complaint-resolution-link">Complaint Resolution Update</a>
            <a href="#" class="submenu-item" id="case-history-link">Case History View</a>
        </div>
        
        <div class="menu-item" onclick="toggleSubmenu('schedule')">
            <div class="icon-box icon-bg-purple">
                <i class='bx bxs-calendar icon-purple'></i>
            </div>
            <span class="font-medium">Equipment & Resource</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="schedule" class="submenu">
            <a href="#" class="submenu-item" id="assigned-equipment-link">Assigned Equipment List</a>
            <a href="#" class="submenu-item" id="equipment-checkinout-link">Equipment Check-In/Check-Out</a>
            <a href="#" class="submenu-item" id="damage-loss-link">Damage/Loss Report</a>
            <a href="#" class="submenu-item" id="inventory-status-link">Inventory Status</a>
        </div>

        <div class="menu-item" onclick="toggleSubmenu('training')">
            <div class="icon-box icon-bg-teal">
                <i class='bx bxs-graduation icon-teal'></i>
            </div>
            <span class="font-medium">Duty Assignment & Schedule</span>
            <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
            </svg>
        </div>
        <div id="training" class="submenu">
            <a href="#" class="submenu-item" id="duty-schedule-link">Duty Schedule</a>
            <a href="#" class="submenu-item" id="confirm-decline-duty-link">Confirm/Decline Duty</a>
            <a href="#" class="submenu-item" id="time-logging-link">Time-In/Time-Out Logging</a>
        </div>
    </div>

    
    <p class="menu-title" style="margin-top: 32px;">GENERAL</p>
    
    <div class="menu-items">
        <div class="menu-item" id="sidebar-settings-btn">
            <div class="icon-box icon-bg-teal">
                <i class='bx bxs-cog icon-teal'></i>
            </div>
            <span class="font-medium">Settings</span>
        </div>
        <div id="sidebar-settings-submenu" class="submenu">
            <a href="#" class="submenu-item" id="sidebar-settings-profile-link" data-target="settings-profile-section">Profile</a>
            <a href="#" class="submenu-item" id="sidebar-settings-security-link" data-target="settings-security-section">Security</a>
        </div>
        

        
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
            <!-- Header -->
            <div class="header">
                <div class="header-content">
                    <div class="search-container">
                        <div class="search-box">
                            <svg class="search-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input type="text" placeholder="Search incidents, personnel, equipment..." class="search-input">
                            <kbd class="search-shortcut">🔥</kbd>
                        </div>
                    </div>
                    
                    <div class="header-actions">
                        <button class="theme-toggle" id="theme-toggle">
                            <i class='bx bx-moon'></i>
                            <span>Dark Mode</span>
                        </button>
                        <div class="time-display" id="time-display">
                            <i class='bx bx-time time-icon'></i>
                            <span id="current-time">Loading...</span>
                        </div>
                        <button class="header-button">
                            <svg class="header-button-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"></path>
                            </svg>
                        </button>
                        <button class="header-button">
                            <svg class="header-button-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </button>
                        <div class="settings-dropdown-container">
                            <button class="header-button" id="settings-button">
                                <i class='bx bx-cog' style="font-size: 20px;"></i>
                            </button>
                            <div class="settings-dropdown-menu" id="settings-dropdown">
                                <button class="settings-dropdown-item" id="settings-profile-btn">Profile</button>
                                <button class="settings-dropdown-item" id="settings-security-btn">Security</button>
                            </div>
                        </div>
                        <div class="user-profile">
                             <img src="<?php echo htmlspecialchars($avatar_path); ?>" alt="User" class="user-avatar">
                            <div class="user-info">
                                <p class="user-name"><?php echo $full_name; ?></p>
                                <p class="user-email"><?php echo $role; ?></p>
                          </div>
                        </div>
                    </div>
                </div>
                <div id="assigned-equipment-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Assigned Equipment List</h1>
                            <p class="dashboard-subtitle">Equipment items issued to you for duty or patrol.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="assigned-equipment-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Issued Equipment</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Issued Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Equipment</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Serial/ID</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Due Back</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assigned-equipment-tbody">
                                            <?php
                                                $issued = [];
                                                try {
                                                    $stmtEQ1 = $pdo->prepare("SELECT item_name, serial_no, issued_at, due_back, status FROM equipment_issuances WHERE user_id = ? ORDER BY issued_at DESC");
                                                    $stmtEQ1->execute([$user_id]);
                                                    $issued = $stmtEQ1->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtEQ1 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmtEQ2 = $pdo->prepare("SELECT item_name, serial_no, issued_at, due_back, status FROM equipment_assignments WHERE user_id = ? ORDER BY issued_at DESC");
                                                        $stmtEQ2->execute([$user_id]);
                                                        $issued = $stmtEQ2->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmtEQ2 = null;
                                                    } catch (Exception $e2) {
                                                        try {
                                                            $stmtEQ3 = $pdo->prepare("SELECT item_name, serial_no, issued_at, due_back, status FROM issued_equipment WHERE user_id = ? ORDER BY issued_at DESC");
                                                            $stmtEQ3->execute([$user_id]);
                                                            $issued = $stmtEQ3->fetchAll(PDO::FETCH_ASSOC);
                                                            $stmtEQ3 = null;
                                                        } catch (Exception $e3) {
                                                            $issued = [];
                                                        }
                                                    }
                                                }
                                                if (!empty($issued)) {
                                                    foreach ($issued as $eq) {
                                                        $dt = htmlspecialchars(substr((string)($eq['issued_at'] ?? ''), 0, 10));
                                                        $name = htmlspecialchars($eq['item_name'] ?? '');
                                                        if ($name === '') { $name = '—'; }
                                                        $sn = htmlspecialchars($eq['serial_no'] ?? '');
                                                        if ($sn === '') { $sn = '—'; }
                                                        $due = htmlspecialchars(substr((string)($eq['due_back'] ?? ''), 0, 10));
                                                        $st = htmlspecialchars($eq['status'] ?? '');
                                                        $label = $st !== '' ? ucfirst(strtolower($st)) : 'Issued';
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $sn . '</td>';
                                                        echo '<td style="padding:10px;">' . ($due !== '' ? $due : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No assigned equipment found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">This list shows equipment currently issued to you. Contact admin for discrepancies.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="equipment-checkinout-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Equipment Check-In/Check-Out</h1>
                            <p class="dashboard-subtitle">Issued and returned equipment movements.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="equipment-checkinout-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Movements</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Equipment</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Serial/ID</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Person</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Notes</th>
                                            </tr>
                                        </thead>
                                        <tbody id="equipment-checkinout-tbody">
                                            <?php
                                                $moves = [];
                                                try {
                                                    $stmtM1 = $pdo->prepare("SELECT t.created_at, t.item_name, t.serial_no, t.action, t.notes, t.user_id, u.first_name, u.last_name FROM equipment_transactions t LEFT JOIN users u ON t.user_id = u.id ORDER BY t.created_at DESC");
                                                    $stmtM1->execute([]);
                                                    $moves = $stmtM1->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtM1 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmtM2 = $pdo->prepare("SELECT l.created_at, l.item_name, l.serial_no, l.action, l.notes, l.user_id, u.first_name, u.last_name FROM equipment_logs l LEFT JOIN users u ON l.user_id = u.id ORDER BY l.created_at DESC");
                                                        $stmtM2->execute([]);
                                                        $moves = $stmtM2->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmtM2 = null;
                                                    } catch (Exception $e2) {
                                                        try {
                                                            $stmtM3 = $pdo->prepare("SELECT created_at, item_name, serial_no, action, notes, user_name FROM equipment_checkio ORDER BY created_at DESC");
                                                            $stmtM3->execute([]);
                                                            $tmp = $stmtM3->fetchAll(PDO::FETCH_ASSOC);
                                                            foreach ($tmp as $row) {
                                                                $moves[] = [
                                                                    'created_at' => $row['created_at'] ?? null,
                                                                    'item_name' => $row['item_name'] ?? null,
                                                                    'serial_no' => $row['serial_no'] ?? null,
                                                                    'action' => $row['action'] ?? null,
                                                                    'notes' => $row['notes'] ?? null,
                                                                    'user_id' => null,
                                                                    'first_name' => null,
                                                                    'last_name' => $row['user_name'] ?? ''
                                                                ];
                                                            }
                                                            $stmtM3 = null;
                                                        } catch (Exception $e3) {
                                                            $moves = [];
                                                        }
                                                    }
                                                }
                                                if (!empty($moves)) {
                                                    foreach ($moves as $m) {
                                                        $dt = htmlspecialchars(substr((string)($m['created_at'] ?? ''), 0, 19));
                                                        $name = htmlspecialchars($m['item_name'] ?? '');
                                                        if ($name === '') { $name = '—'; }
                                                        $sn = htmlspecialchars($m['serial_no'] ?? '');
                                                        if ($sn === '') { $sn = '—'; }
                                                        $actRaw = strtolower(trim($m['action'] ?? ''));
                                                        $act = $actRaw === 'check_out' ? 'Checked Out' : ($actRaw === 'check_in' ? 'Checked In' : ucfirst($actRaw ?: '—'));
                                                        $person = trim(htmlspecialchars(($m['first_name'] ?? '').' '.($m['last_name'] ?? '')));
                                                        if ($person === '') { $person = '—'; }
                                                        $notes = htmlspecialchars($m['notes'] ?? '');
                                                        if ($notes === '') { $notes = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $sn . '</td>';
                                                        echo '<td style="padding:10px;">' . $act . '</td>';
                                                        echo '<td style="padding:10px;">' . $person . '</td>';
                                                        echo '<td style="padding:10px;">' . $notes . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No equipment movements found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Shows check-out (issued) and check-in (returned) records.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="damage-loss-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Damage/Loss Report</h1>
                            <p class="dashboard-subtitle">Report damaged, malfunctioning, or lost equipment.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="damage-loss-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Reported Incidents</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Equipment</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Serial/ID</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Description</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Reported By</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="damage-loss-tbody">
                                            <?php
                                                $reports = [];
                                                try {
                                                    $stmtD1 = $pdo->prepare("SELECT r.created_at, r.item_name, r.serial_no, r.type, r.description, r.status, r.user_id, u.first_name, u.last_name FROM equipment_damage_reports r LEFT JOIN users u ON r.user_id = u.id ORDER BY r.created_at DESC");
                                                    $stmtD1->execute([]);
                                                    $reports = $stmtD1->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtD1 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmtD2 = $pdo->prepare("SELECT d.created_at, d.item_name, d.serial_no, d.type, d.description, d.status, d.user_id, u.first_name, u.last_name FROM damage_reports d LEFT JOIN users u ON d.user_id = u.id ORDER BY d.created_at DESC");
                                                        $stmtD2->execute([]);
                                                        $reports = $stmtD2->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmtD2 = null;
                                                    } catch (Exception $e2) {
                                                        try {
                                                            $stmtD3 = $pdo->prepare("SELECT created_at, item_name, serial_no, type, description, status, reporter FROM equipment_incident_reports ORDER BY created_at DESC");
                                                            $stmtD3->execute([]);
                                                            $tmp = $stmtD3->fetchAll(PDO::FETCH_ASSOC);
                                                            foreach ($tmp as $row) {
                                                                $reports[] = [
                                                                    'created_at' => $row['created_at'] ?? null,
                                                                    'item_name' => $row['item_name'] ?? null,
                                                                    'serial_no' => $row['serial_no'] ?? null,
                                                                    'type' => $row['type'] ?? null,
                                                                    'description' => $row['description'] ?? null,
                                                                    'status' => $row['status'] ?? null,
                                                                    'first_name' => null,
                                                                    'last_name' => $row['reporter'] ?? ''
                                                                ];
                                                            }
                                                            $stmtD3 = null;
                                                        } catch (Exception $e3) {
                                                            $reports = [];
                                                        }
                                                    }
                                                }
                                                if (!empty($reports)) {
                                                    foreach ($reports as $r) {
                                                        $dt = htmlspecialchars(substr((string)($r['created_at'] ?? ''), 0, 19));
                                                        $name = htmlspecialchars($r['item_name'] ?? '');
                                                        if ($name === '') { $name = '—'; }
                                                        $sn = htmlspecialchars($r['serial_no'] ?? '');
                                                        if ($sn === '') { $sn = '—'; }
                                                        $type = htmlspecialchars(ucfirst(strtolower($r['type'] ?? '')));
                                                        if ($type === '') { $type = '—'; }
                                                        $desc = htmlspecialchars($r['description'] ?? '');
                                                        if ($desc === '') { $desc = '—'; }
                                                        $person = trim(htmlspecialchars(($r['first_name'] ?? '').' '.($r['last_name'] ?? '')));
                                                        if ($person === '') { $person = '—'; }
                                                        $status = htmlspecialchars($r['status'] ?? '');
                                                        if ($status === '') { $status = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $sn . '</td>';
                                                        echo '<td style="padding:10px;">' . $type . '</td>';
                                                        echo '<td style="padding:10px;">' . $desc . '</td>';
                                                        echo '<td style="padding:10px;">' . $person . '</td>';
                                                        echo '<td style="padding:10px;">' . $status . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="7" style="padding:14px;">No damage/loss reports found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Reports submitted by Tanods or Admins about damaged or lost items.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="inventory-status-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Inventory Status</h1>
                            <p class="dashboard-subtitle">Real-time view of barangay equipment state.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="inventory-status-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Equipment Inventory</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Equipment</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Serial/ID</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Last Updated</th>
                                            </tr>
                                        </thead>
                                        <tbody id="inventory-status-tbody">
                                            <?php
                                                $items = [];
                                                try {
                                                    $stmtI1 = $pdo->prepare("SELECT item_name, serial_no, status, updated_at FROM equipment_inventory ORDER BY item_name ASC");
                                                    $stmtI1->execute([]);
                                                    $items = $stmtI1->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtI1 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmtI2 = $pdo->prepare("SELECT item_name, serial_no, status, updated_at FROM barangay_equipment ORDER BY item_name ASC");
                                                        $stmtI2->execute([]);
                                                        $items = $stmtI2->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmtI2 = null;
                                                    } catch (Exception $e2) {
                                                        try {
                                                            $stmtI3 = $pdo->prepare("SELECT name as item_name, serial as serial_no, state as status, updated_at FROM inventory_items ORDER BY name ASC");
                                                            $stmtI3->execute([]);
                                                            $items = $stmtI3->fetchAll(PDO::FETCH_ASSOC);
                                                            $stmtI3 = null;
                                                        } catch (Exception $e3) {
                                                            $items = [];
                                                        }
                                                    }
                                                }
                                                if (!empty($items)) {
                                                    foreach ($items as $it) {
                                                        $name = htmlspecialchars($it['item_name'] ?? '');
                                                        if ($name === '') { $name = '—'; }
                                                        $sn = htmlspecialchars($it['serial_no'] ?? '');
                                                        if ($sn === '') { $sn = '—'; }
                                                        $st = strtolower(trim($it['status'] ?? ''));
                                                        $label = $st ? ucfirst($st) : '—';
                                                        $upd = htmlspecialchars(substr((string)($it['updated_at'] ?? ''), 0, 19));
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $sn . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '<td style="padding:10px;">' . ($upd !== '' ? $upd : '—') . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="4" style="padding:14px;">No inventory items found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Statuses: Available, In use, Under repair, Lost, Retired.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
                <div id="assigned-route-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Assigned Patrol Route</h1>
                            <p class="dashboard-subtitle">Your streets/zones to patrol.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="assigned-route-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Route Details</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Assigned Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Zone</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Street</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="assigned-route-tbody">
                                            <?php
                                                $routes = [];
                                                try {
                                                    $stmtAR1 = $pdo->prepare("SELECT assigned_at, zone, street FROM watch_patrol_assignments WHERE user_id = ? ORDER BY assigned_at DESC");
                                                    $stmtAR1->execute([$user_id]);
                                                    $routes = $stmtAR1->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtAR1 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmtAR2 = $pdo->prepare("SELECT assigned_at, zone, street FROM patrol_assignments WHERE user_id = ? ORDER BY assigned_at DESC");
                                                        $stmtAR2->execute([$user_id]);
                                                        $routes = $stmtAR2->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmtAR2 = null;
                                                    } catch (Exception $e2) {
                                                        $routes = [];
                                                    }
                                                }
                                                if (!empty($routes)) {
                                                    foreach ($routes as $r) {
                                                        $assigned_at = htmlspecialchars($r['assigned_at'] ?? '');
                                                        $dt = htmlspecialchars(substr($assigned_at, 0, 10));
                                                        $zone = htmlspecialchars($r['zone'] ?? '');
                                                        $street = htmlspecialchars($r['street'] ?? '');
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . ($zone !== '' ? $zone : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . ($street !== '' ? $street : '—') . '</td>';
                                                        echo '<td style="padding:10px;">Assigned</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="4" style="padding:14px;">No assigned patrol routes yet.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">This list shows the zones and streets assigned for your patrol route.</p>
                            </div>
                        </div>
                    </div>
                </div>
            
                <div id="patrol-activity-section" style="display:none;" data-user-id="<?php echo (int)$user_id; ?>">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Patrol Activity Log</h1>
                            <p class="dashboard-subtitle">Record observations, actions, and incidents.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="primary-button" id="create-notes-btn">Create Notes</button>
                            <button class="secondary-button" id="patrol-activity-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Activity Log</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date/Time</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Description</th>
                                            </tr>
                                        </thead>
                                        <tbody id="patrol-activity-tbody">
                                            <tr>
                                                <td colspan="3" style="padding:14px;">No activity notes yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Create notes for observations, actions, and incidents during patrol.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="activity-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
                    <div style="background:#fff;padding:20px;border-radius:8px;max-width:480px;width:90%;box-shadow:0 10px 25px rgba(0,0,0,0.15);">
                        <h3 style="margin-bottom:12px;">Create Patrol Note</h3>
                        <div style="margin-bottom:10px;">
                            <label style="display:block;margin-bottom:6px;">Type</label>
                            <select id="activity-type-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                <option value="Observation">Observation</option>
                                <option value="Action">Action</option>
                                <option value="Incident">Incident</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px;">
                            <label style="display:block;margin-bottom:6px;">Description</label>
                            <textarea id="activity-desc-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button class="secondary-button" id="activity-cancel-btn">Cancel</button>
                            <button class="primary-button" id="activity-save-btn">Save Note</button>
                        </div>
                    </div>
                </div>
                <div id="checkpoint-logging-section" style="display:none;" data-user-id="<?php echo (int)$user_id; ?>">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Checkpoint Logging</h1>
                            <p class="dashboard-subtitle">Record exact time and location at checkpoints.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="primary-button" id="checkpoint-log-now-btn">Log Checkpoint Now</button>
                            <button class="secondary-button" id="checkpoint-logging-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Checkpoint Records</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date/Time</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Latitude</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Longitude</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Accuracy (m)</th>
                                            </tr>
                                        </thead>
                                        <tbody id="checkpoint-logging-tbody">
                                            <tr>
                                                <td colspan="4" style="padding:14px;">No checkpoints logged yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Use the button to record your current GPS coordinates when you reach a checkpoint.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="receive-complaints-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Receive Community Complaints</h1>
                            <p class="dashboard-subtitle">View complaints submitted by users.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="receive-complaints-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Complaints</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Resident</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="receive-complaints-tbody">
                                            <?php
                                                $complaints = [];
                                                try {
                                                    $stmtC = $pdo->prepare("SELECT f.id, f.subject, f.message, f.category, f.status, f.created_at, u.first_name, u.last_name FROM feedback f LEFT JOIN users u ON f.submitted_by = u.id WHERE f.category = 'Complaint' ORDER BY f.created_at DESC");
                                                    $stmtC->execute([]);
                                                    $complaints = $stmtC->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtC = null;
                                                } catch (Exception $e) {
                                                    $complaints = [];
                                                }
                                                if (!empty($complaints)) {
                                                    foreach ($complaints as $c) {
                                                        $dt = htmlspecialchars(substr((string)($c['created_at'] ?? ''), 0, 10));
                                                        $name = trim(htmlspecialchars(($c['first_name'] ?? '').' '.($c['last_name'] ?? '')));
                                                        if ($name === '') { $name = '—'; }
                                                        $subject = htmlspecialchars($c['subject'] ?? '');
                                                        if ($subject === '') { $subject = '—'; }
                                                        $status = htmlspecialchars($c['status'] ?? 'pending');
                                                        $label = ucfirst($status);
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $subject . '</td>';
                                                        echo '<td style="padding:10px;">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="4" style="padding:14px;">No community complaints found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Complaints are listed with date, resident name, subject, and status.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="complaint-resolution-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Complaint Resolution Update</h1>
                            <p class="dashboard-subtitle">View actions taken and update complaint status.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="complaint-resolution-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Complaints</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">ID</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Resident</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Actions Taken</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="complaint-resolution-tbody">
                                            <?php
                                                $complaints2 = [];
                                                try {
                                                    $stmtCR = $pdo->prepare("SELECT f.id, f.subject, f.admin_response, f.status, u.first_name, u.last_name FROM feedback f LEFT JOIN users u ON f.submitted_by = u.id WHERE f.category = 'Complaint' ORDER BY f.created_at DESC");
                                                    $stmtCR->execute([]);
                                                    $complaints2 = $stmtCR->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtCR = null;
                                                } catch (Exception $e) {
                                                    $complaints2 = [];
                                                }
                                                if (!empty($complaints2)) {
                                                    foreach ($complaints2 as $c2) {
                                                        $id = (int)($c2['id'] ?? 0);
                                                        $name = trim(htmlspecialchars(($c2['first_name'] ?? '').' '.($c2['last_name'] ?? '')));
                                                        if ($name === '') { $name = '—'; }
                                                        $subject = htmlspecialchars($c2['subject'] ?? '');
                                                        if ($subject === '') { $subject = '—'; }
                                                        $status = htmlspecialchars($c2['status'] ?? 'pending');
                                                        $label = ucfirst($status);
                                                        $resp = htmlspecialchars($c2['admin_response'] ?? '');
                                                        if ($resp === '') { $resp = '—'; }
                                                        echo '<tr class="cr-row" data-id="'.$id.'" data-status="'.strtolower($status).'">';
                                                        echo '<td style="padding:10px;">' . $id . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $subject . '</td>';
                                                        echo '<td class="cr-status" style="padding:10px;">' . $label . '</td>';
                                                        echo '<td class="cr-response" style="padding:10px;">' . $resp . '</td>';
                                                        echo '<td style="padding:10px;"><button class="primary-button cr-update-btn">Update</button></td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="6" style="padding:14px;">No community complaints found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Select Update to record actions taken and change status.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="case-history-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Case History View</h1>
                            <p class="dashboard-subtitle">Past resolved community complaints.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="case-history-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Resolved Complaints</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Resident</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Subject</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Resolution Summary</th>
                                            </tr>
                                        </thead>
                                        <tbody id="case-history-tbody">
                                            <?php
                                                $history = [];
                                                try {
                                                    $stmtH = $pdo->prepare("SELECT f.id, f.subject, f.admin_response, f.status, f.created_at, u.first_name, u.last_name FROM feedback f LEFT JOIN users u ON f.submitted_by = u.id WHERE f.category = 'Complaint' AND f.status = 'resolved' ORDER BY f.created_at DESC");
                                                    $stmtH->execute([]);
                                                    $history = $stmtH->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtH = null;
                                                } catch (Exception $e) {
                                                    $history = [];
                                                }
                                                if (!empty($history)) {
                                                    foreach ($history as $h) {
                                                        $dt = htmlspecialchars(substr((string)($h['created_at'] ?? ''), 0, 10));
                                                        $name = trim(htmlspecialchars(($h['first_name'] ?? '').' '.($h['last_name'] ?? '')));
                                                        if ($name === '') { $name = '—'; }
                                                        $subject = htmlspecialchars($h['subject'] ?? '');
                                                        if ($subject === '') { $subject = '—'; }
                                                        $resp = htmlspecialchars($h['admin_response'] ?? '');
                                                        if ($resp === '') { $resp = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $name . '</td>';
                                                        echo '<td style="padding:10px;">' . $subject . '</td>';
                                                        echo '<td style="padding:10px;">' . $resp . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="4" style="padding:14px;">No resolved complaints found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">This view lists resolved complaints for historical reference.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="resolution-modal" style="display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.4);align-items:center;justify-content:center;">
                    <div style="background:#fff;padding:20px;border-radius:8px;max-width:520px;width:90%;box-shadow:0 10px 25px rgba(0,0,0,0.15);">
                        <h3 style="margin-bottom:12px;">Update Complaint</h3>
                        <div style="display:none;" id="resolution-complaint-id"></div>
                        <div style="margin-bottom:10px;">
                            <label style="display:block;margin-bottom:6px;">Status</label>
                            <select id="resolution-status-select" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                <option value="pending">Pending</option>
                                <option value="reviewed">Reviewed</option>
                                <option value="resolved">Resolved</option>
                            </select>
                        </div>
                        <div style="margin-bottom:14px;">
                            <label style="display:block;margin-bottom:6px;">Actions Taken</label>
                            <textarea id="resolution-desc-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                        </div>
                        <div style="display:flex;gap:8px;justify-content:flex-end;">
                            <button class="secondary-button" id="resolution-cancel-btn">Cancel</button>
                            <button class="primary-button" id="resolution-save-btn">Save Update</button>
                        </div>
                    </div>
                </div>
            
            <!-- dashboard content palitan nyo nalnag ng content na gamit sa system nyo -->
            <div class="dashboard-content">
                <div class="content-section" id="settings-profile-section" style="display:none;">
                    <div class="settings-card">
                        <div class="settings-nav">
                            <span class="settings-tab active" id="settings-tab-profile">Profile</span>
                            <span class="settings-tab" id="settings-tab-security">Security</span>
                        </div>
                        <div class="settings-title">Profile</div>
                        <div style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;">
                            <div style="display:flex;flex-direction:column;align-items:center;gap:12px;">
                                <img id="profile-avatar-preview" src="<?php echo htmlspecialchars($avatar_path); ?>" alt="Avatar" style="width:96px;height:96px;border-radius:50%;object-fit:cover;">
                                <div style="display:flex;gap:8px;">
                                    <input type="file" id="profile-avatar-input" accept="image/*">
                                    <button class="secondary-button" id="profile-avatar-upload-btn">Upload</button>
                                </div>
                                <div id="avatar-status" style="margin-top:8px;font-weight:500;"></div>
                            </div>
                            <form id="profile-form" style="flex:1;min-width:280px;display:grid;grid-template-columns:repeat(2,1fr);gap:12px;">
                                <div>
                                    <label style="display:block;margin-bottom:6px;">First Name</label>
                                    <input class="modal-input" type="text" name="first_name" placeholder="First name" required>
                                </div>
                                <div>
                                    <label style="display:block;margin-bottom:6px;">Middle Name</label>
                                    <input class="modal-input" type="text" name="middle_name" placeholder="Middle name">
                                </div>
                                <div>
                                    <label style="display:block;margin-bottom:6px;">Last Name</label>
                                    <input class="modal-input" type="text" name="last_name" placeholder="Last name" required>
                                </div>
                                <div>
                                    <label style="display:block;margin-bottom:6px;">Username</label>
                                    <input class="modal-input" type="text" name="username" placeholder="Username" required>
                                </div>
                                <div>
                                    <label style="display:block;margin-bottom:6px;">Email</label>
                                    <input class="modal-input" type="email" name="email" placeholder="Email" required>
                                </div>
                                <div>
                                    <label style="display:block;margin-bottom:6px;">Contact</label>
                                    <input class="modal-input" type="text" name="contact" placeholder="Contact" required>
                                </div>
                                <div style="grid-column:span 2;">
                                    <label style="display:block;margin-bottom:6px;">Address</label>
                                    <input class="modal-input" type="text" name="address" placeholder="Address" required>
                                </div>
                                <div>
                                    <label style="display:block;margin-bottom:6px;">Date of Birth</label>
                                    <input class="modal-input" type="date" name="date_of_birth" placeholder="YYYY-MM-DD" required>
                                </div>
                                <div style="grid-column:span 2;display:flex;justify-content:flex-end;gap:8px;">
                                    <button type="submit" class="primary-button" id="profile-save-btn">Save Changes</button>
                                </div>
                                <div id="profile-status" style="grid-column:span 2;margin-top:8px;font-weight:500;"></div>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="content-section" id="settings-security-section" style="display:none;">
                    <div class="settings-card">
                        <div class="settings-nav">
                            <span class="settings-tab">Profile</span>
                            <span class="settings-tab active">Security</span>
                        </div>
                        <div class="settings-title">Security</div>
                        <div class="settings-list">
                            <div class="settings-item">
                                <div class="settings-item-left">
                                    <div class="settings-item-icon"><i class='bx bxs-lock-alt'></i></div>
                                    <div>
                                        <div class="settings-item-title">Change Password</div>
                                        <div class="settings-item-desc" id="pwd-last-changed">Last changed unknown</div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-change-password-btn">Change</button>
                            </div>
                            <div class="settings-item">
                                <div class="settings-item-left">
                                    <div class="settings-item-icon"><i class='bx bxs-envelope'></i></div>
                                    <div>
                                        <div class="settings-item-title">Email Address</div>
                                        <div class="settings-item-desc" id="email-address"><?php echo htmlspecialchars($_SESSION['user_email'] ?? ''); ?></div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-change-email-btn">Change</button>
                            </div>
                            <div class="settings-item">
                                <div class="settings-item-left">
                                    <div class="settings-item-icon"><i class='bx bxs-key'></i></div>
                                    <div>
                                        <div class="settings-item-title">API Access</div>
                                        <div class="settings-item-desc" id="api-status">No API key generated</div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-generate-key-btn">+ Generate Key</button>
                            </div>
                            <div class="settings-item">
                                <div class="settings-item-left">
                                    <div class="settings-item-icon"><i class='bx bxs-shield-alt-2'></i></div>
                                    <div>
                                        <div class="settings-item-title">Two-Factor Authentication</div>
                                        <div class="settings-item-desc" id="tfa-status"><span class="badge badge-pending">Disabled</span></div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-enable-2fa-btn">Enable</button>
                            </div>
                            <div class="settings-item">
                                <div class="settings-item-left">
                                    <div class="settings-item-icon"><i class='bx bxs-trash'></i></div>
                                    <div>
                                        <div class="settings-item-title">Delete Account</div>
                                        <div class="settings-item-desc">Permanently remove your account</div>
                                    </div>
                                </div>
                                <button class="secondary-button" id="security-delete-account-btn" style="background:#ef4444;color:#fff;border-color:#ef4444;">Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="duty-schedule-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Duty Schedule</h1>
                            <p class="dashboard-subtitle">Your assigned patrol shifts.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="duty-schedule-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Assigned Patrol Shifts</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Shift</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Patrol Area</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="duty-schedule-tbody">
                                            <?php
                                                $assignments = [];
                                                try {
                                                    $stmt2 = $pdo->prepare("SELECT assigned_at, zone, street FROM watch_patrol_assignments WHERE user_id = ? ORDER BY assigned_at DESC");
                                                    $stmt2->execute([$user_id]);
                                                    $assignments = $stmt2->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmt2 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmt3 = $pdo->prepare("SELECT assigned_at, zone, street FROM patrol_assignments WHERE user_id = ? ORDER BY assigned_at DESC");
                                                        $stmt3->execute([$user_id]);
                                                        $assignments = $stmt3->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmt3 = null;
                                                    } catch (Exception $e2) {
                                                        $assignments = [];
                                                    }
                                                }
                                                if (!empty($assignments)) {
                                                    foreach ($assignments as $a) {
                                                        $dt = htmlspecialchars(substr((string)($a['assigned_at'] ?? ''), 0, 10));
                                                        $tm = htmlspecialchars(substr((string)($a['assigned_at'] ?? ''), 11, 5));
                                                        $hh = (int)substr($tm, 0, 2);
                                                        $shift = '—';
                                                        if ($hh >= 6 && $hh < 14) { $shift = 'Morning'; }
                                                        else if ($hh >= 14 && $hh < 22) { $shift = 'Evening'; }
                                                        else if ($tm !== '') { $shift = 'Night'; }
                                                        $zone = htmlspecialchars($a['zone'] ?? '');
                                                        $street = htmlspecialchars($a['street'] ?? '');
                                                        $area = trim($zone . (strlen($zone) && strlen($street) ? ' - ' : '') . $street);
                                                        if ($area === '') { $area = '—'; }
                                                        echo '<tr>';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $shift . '</td>';
                                                        echo '<td style="padding:10px;">' . $area . '</td>';
                                                        echo '<td style="padding:10px;">Assigned</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="4" style="padding:14px;">No assigned patrol shifts yet.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">View your assigned patrol shifts here.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="confirm-decline-duty-section" style="display:none;" data-user-id="<?php echo (int)$user_id; ?>">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Confirm/Decline Duty</h1>
                            <p class="dashboard-subtitle">Review and respond to assigned patrol shifts.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="confirm-decline-duty-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Assigned Patrol Shifts</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Shift</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Patrol Area</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="confirm-decline-duty-tbody">
                                            <?php
                                                $cd_assignments = [];
                                                try {
                                                    $stmt4 = $pdo->prepare("SELECT assigned_at, zone, street FROM watch_patrol_assignments WHERE user_id = ? ORDER BY assigned_at DESC");
                                                    $stmt4->execute([$user_id]);
                                                    $cd_assignments = $stmt4->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmt4 = null;
                                                } catch (Exception $e) {
                                                    try {
                                                        $stmt5 = $pdo->prepare("SELECT assigned_at, zone, street FROM patrol_assignments WHERE user_id = ? ORDER BY assigned_at DESC");
                                                        $stmt5->execute([$user_id]);
                                                        $cd_assignments = $stmt5->fetchAll(PDO::FETCH_ASSOC);
                                                        $stmt5 = null;
                                                    } catch (Exception $e2) {
                                                        $cd_assignments = [];
                                                    }
                                                }
                                                if (!empty($cd_assignments)) {
                                                    foreach ($cd_assignments as $a) {
                                                        $assigned_at_raw = (string)($a['assigned_at'] ?? '');
                                                        $dt = htmlspecialchars(substr($assigned_at_raw, 0, 10));
                                                        $tm = htmlspecialchars(substr($assigned_at_raw, 11, 5));
                                                        $hh = (int)substr($tm, 0, 2);
                                                        $shift = '—';
                                                        if ($hh >= 6 && $hh < 14) { $shift = 'Morning'; }
                                                        else if ($hh >= 14 && $hh < 22) { $shift = 'Evening'; }
                                                        else if ($tm !== '') { $shift = 'Night'; }
                                                        $zone = htmlspecialchars($a['zone'] ?? '');
                                                        $street = htmlspecialchars($a['street'] ?? '');
                                                        $area = trim($zone . (strlen($zone) && strlen($street) ? ' - ' : '') . $street);
                                                        if ($area === '') { $area = '—'; }
                                                        $data_assigned = htmlspecialchars($assigned_at_raw);
                                                        $data_zone = htmlspecialchars($a['zone'] ?? '');
                                                        $data_street = htmlspecialchars($a['street'] ?? '');
                                                        echo '<tr class="cd-row" data-assigned="'.$data_assigned.'" data-zone="'.$data_zone.'" data-street="'.$data_street.'">';
                                                        echo '<td style="padding:10px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style="padding:10px;">' . $shift . '</td>';
                                                        echo '<td style="padding:10px;">' . $area . '</td>';
                                                        echo '<td class="cd-status" style="padding:10px;">Pending</td>';
                                                        echo '<td style="padding:10px;"><button class="primary-button confirm-btn" style="margin-right:8px;">Confirm</button><button class="secondary-button decline-btn">Decline</button></td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan="5" style="padding:14px;">No assigned patrol shifts yet.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Instructions</h2>
                                <p style="margin-top:8px;line-height:1.6;">Confirm or decline duty assignments directly here without leaving the dashboard.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="time-logging-section" style="display:none;" data-user-id="<?php echo (int)$user_id; ?>">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Time-In/Time-Out Logging</h1>
                            <p class="dashboard-subtitle">Your actual duty hours.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="primary-button" id="time-in-now-btn">Time In Now</button>
                            <button class="secondary-button" id="time-out-now-btn">Time Out Now</button>
                            <button class="secondary-button" id="time-logging-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Duty Hours</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Date</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Time In</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Time Out</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Hours</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="time-logging-tbody">
                                            <tr>
                                                <td colspan="6" style="padding:14px;">No duty hours logged yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Log your duty start and end times here. Entries persist locally and display your computed hours.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="nearby-cctv-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Nearby CCTV Viewer</h1>
                            <p class="dashboard-subtitle">Quickly view cameras near a reported incident.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="nearby-cctv-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Find Nearby Cameras</h2>
                                <div style="display:flex;gap:8px;align-items:center;">
                                    <input id="nearby-incident-input" type="text" placeholder="Enter incident location" style="flex:1;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    <button class="primary-button" id="nearby-search-btn">Find Cameras</button>
                                </div>
                            </div>
                            <div class="card">
                                <h2 class="card-title">Nearby Cameras</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Camera</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Distance</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="nearby-cctv-tbody">
                                            <tr>
                                                <td colspan="5" style="padding:14px;">No cameras found. Enter a location and search.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div id="nearby-live-details" style="margin-top:12px;"></div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Use a connected camera or available stream to preview footage without leaving the dashboard.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="realtime-incident-alerts-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Real-Time Incident Alerts</h1>
                            <p class="dashboard-subtitle">Instant notifications for accidents, violations, and reported incidents linked to CCTV.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="primary-button" id="alerts-start-btn">Start Alerts</button>
                            <button class="secondary-button" id="alerts-stop-btn">Stop Alerts</button>
                            <button class="secondary-button" id="alerts-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Alert Feed</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Type</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Time</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Location</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Action</th>
                                            </tr>
                                        </thead>
                                        <tbody id="incident-alerts-tbody">
                                            <tr>
                                                <td colspan="4" style="padding:14px;">No alerts yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Settings</h2>
                                <div style="display:flex;gap:8px;align-items:center;margin-top:8px;">
                                    <label style="display:flex;align-items:center;gap:8px;">
                                        <input type="checkbox" id="alerts-autostart-toggle">
                                        <span>Auto-start alerts</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="footage-request-log-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Footage Request Log</h1>
                            <p class="dashboard-subtitle">Record of all CCTV footage requests.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="footage-request-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Requests</h2>
                                <div style="overflow-x:auto;">
                                    <table style="width:100%;border-collapse:collapse;">
                                        <thead>
                                            <tr>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Requested At</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Requester</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Incident</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Reason</th>
                                                <th style="text-align:left;padding:10px;border-bottom:1px solid #e5e7eb;">Status</th>
                                            </tr>
                                        </thead>
                                        <tbody id="footage-request-tbody">
                                            <?php
                                                $requests = [];
                                                try {
                                                    $pdo->exec("CREATE TABLE IF NOT EXISTS cctv_footage_requests (
                                                        id INT AUTO_INCREMENT PRIMARY KEY,
                                                        incident_ref VARCHAR(255) NOT NULL,
                                                        reason TEXT,
                                                        requested_by INT,
                                                        requested_name VARCHAR(255),
                                                        requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                                                        status ENUM('pending','approved','rejected') DEFAULT 'pending'
                                                    )");
                                                    $stmtFR = $pdo->prepare("SELECT incident_ref, reason, requested_name, requested_at, status FROM cctv_footage_requests ORDER BY requested_at DESC");
                                                    $stmtFR->execute([]);
                                                    $requests = $stmtFR->fetchAll(PDO::FETCH_ASSOC);
                                                    $stmtFR = null;
                                                } catch (Exception $e) {
                                                    $requests = [];
                                                }
                                                if (!empty($requests)) {
                                                    foreach ($requests as $r) {
                                                        $dt = htmlspecialchars(substr((string)($r['requested_at'] ?? ''), 0, 19));
                                                        $nm = htmlspecialchars($r['requested_name'] ?? '');
                                                        if ($nm === '') { $nm = '—'; }
                                                        $inc = htmlspecialchars($r['incident_ref'] ?? '');
                                                        if ($inc === '') { $inc = '—'; }
                                                        $rsn = htmlspecialchars($r['reason'] ?? '');
                                                        if ($rsn === '') { $rsn = '—'; }
                                                        $st = htmlspecialchars($r['status'] ?? 'pending');
                                                        $label = ucfirst($st);
                                                        echo '<tr>';
                                                        echo '<td style=\"padding:10px;\">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                        echo '<td style=\"padding:10px;\">' . $nm . '</td>';
                                                        echo '<td style=\"padding:10px;\">' . $inc . '</td>';
                                                        echo '<td style=\"padding:10px;\">' . $rsn . '</td>';
                                                        echo '<td style=\"padding:10px;\">' . $label . '</td>';
                                                        echo '</tr>';
                                                    }
                                                } else {
                                                    echo '<tr><td colspan=\"5\" style=\"padding:14px;\">No footage requests found.</td></tr>';
                                                }
                                            ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">New Request</h2>
                                <div style="display:flex;flex-direction:column;gap:10px;">
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Incident Reference</label>
                                        <input id="footage-incident-input" type="text" placeholder="e.g., Zone 2 - Street B, 2026-01-15" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;">
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Reason</label>
                                        <textarea id="footage-reason-input" rows="4" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;"></textarea>
                                    </div>
                                    <div>
                                        <label style="display:block;margin-bottom:6px;">Requester</label>
                                        <input type="text" value="<?php echo htmlspecialchars($full_name); ?>" disabled style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:6px;background:#f9fafb;color:#6b7280;">
                                    </div>
                                    <div style="display:flex;gap:8px;justify-content:flex-end;">
                                        <button class="primary-button" id="footage-submit-btn">Submit Request</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="dashboard-header">
                    <div>
                        <h1 class="dashboard-title">Fire & Rescue Dashboard</h1>
                        <p class="dashboard-subtitle">Monitor, manage, and coordinate fire & rescue operations.</p>
                    </div>
                    <div class="dashboard-actions">
                        <button class="primary-button">
                            <span style="font-size: 20px;">+</span>
                            New Incident
                        </button>
                        <button class="secondary-button">
                            Export Reports
                        </button>
                    </div>
                </div>
                
                <!-- Stats Cards -->
                <div class="stats-grid">
                    <div class="stat-card stat-card-primary">
                        <div class="stat-header">
                            <span class="stat-title">Active Incidents</span>
                            <button class="stat-button stat-button-primary">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">8</div>
                        <div class="stat-info">
                            <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>2 new in last hour</span>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-white">
                        <div class="stat-header">
                            <span class="stat-title">Avg Response Time</span>
                            <button class="stat-button stat-button-white">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">4.2<span style="font-size: 24px;">min</span></div>
                        <div class="stat-info">
                            <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>Improved from last month</span>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-white">
                        <div class="stat-header">
                            <span class="stat-title">Equipment Operational</span>
                            <button class="stat-button stat-button-white">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">96%</div>
                        <div class="stat-info">
                            <svg class="stat-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path>
                            </svg>
                            <span>5 units in maintenance</span>
                        </div>
                    </div>
                    
                    <div class="stat-card stat-card-white">
                        <div class="stat-header">
                            <span class="stat-title">Personnel On Duty</span>
                            <button class="stat-button stat-button-white">
                                <svg class="menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path>
                                </svg>
                            </button>
                        </div>
                        <div class="stat-value">42</div>
                        <div class="stat-info">
                            <span>Across 6 stations</span>
                        </div>
                    </div>
                </div>
                
                <!-- Main Grid -->
                <div class="main-grid">
                    <div class="left-column">
                        <div class="card">
                            <h2 class="card-title">Incident Response Analysis</h2>
                            <div class="response-chart">
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-red" style="height: 35%;"></div>
                                    <span class="chart-bar-label">Residential</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-orange" style="height: 75%;"></div>
                                    <span class="chart-bar-label">Commercial</span>
                                </div>
                                <div class="chart-bar bar-highlight">
                                    <div class="chart-bar-value bar-yellow" style="height: 90%;"></div>
                                    <span class="chart-bar-label">Vehicle</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-green" style="height: 100%;"></div>
                                    <span class="chart-bar-label">Medical</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-blue" style="height: 40%;"></div>
                                    <span class="chart-bar-label">Hazmat</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-purple" style="height: 55%;"></div>
                                    <span class="chart-bar-label">Rescue</span>
                                </div>
                                <div class="chart-bar">
                                    <div class="chart-bar-value bar-pink" style="height: 45%;"></div>
                                    <span class="chart-bar-label">Other</span>
                                </div>
                            </div>
                            <div class="progress-container">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: 75%;"></div>
                                </div>
                                <div class="progress-labels">
                                    <span>Response Goal: 5 min</span>
                                    <span>Current Avg: 4.2 min</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Actions & Incident Reports -->
                        <div class="two-column-grid">
                            <div class="card">
                                <h2 class="card-title">Quick Actions</h2>
                                <div class="quick-actions">
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-red">
                                            <i class='bx bxs-report icon-red'></i>
                                        </div>
                                        <span class="action-label">Report Incident</span>
                                    </div>
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-blue">
                                            <i class='bx bxs-cog icon-blue'></i>
                                        </div>
                                        <span class="action-label">Check Equipment</span>
                                    </div>
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-purple">
                                            <i class='bx bxs-calendar icon-purple'></i>
                                        </div>
                                        <span class="action-label">Schedule Personnel</span>
                                    </div>
                                    <div class="action-button">
                                        <div class="icon-box icon-bg-yellow">
                                            <i class='bx bxs-check-shield icon-yellow'></i>
                                        </div>
                                        <span class="action-label">Inspection Report</span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Incident Reports -->
                            <div class="card">
                                <div class="incident-header">
                                    <h2 class="card-title">Active Incidents</h2>
                                    <button class="secondary-button" style="font-size: 14px; padding: 8px 16px;">View All</button>
                                </div>
                                <div class="incident-list">
                                    <div class="incident-item">
                                        <div class="incident-icon icon-red">
                                            <i class='bx bxs-map icon-red'></i>
                                        </div>
                                        <div class="incident-info">
                                            <p class="incident-name">Structure Fire - 124 Main St</p>
                                            <p class="incident-location">Units: Engine 1, Ladder 3, Rescue 2</p>
                                        </div>
                                        <span class="status-badge status-pending">Active</span>
                                    </div>
                                    <div class="incident-item">
                                        <div class="incident-icon icon-yellow">
                                            <i class='bx bxs-car-crash icon-yellow'></i>
                                        </div>
                                        <div class="incident-info">
                                            <p class="incident-name">Vehicle Accident - Highway 101</p>
                                            <p class="incident-location">Units: Engine 4, Medic 2</p>
                                        </div>
                                        <span class="status-badge status-progress">En Route</span>
                                    </div>
                                    <div class="incident-item">
                                        <div class="incident-icon icon-blue">
                                            <i class='bx bxs-first-aid icon-blue'></i>
                                        </div>
                                        <div class="incident-info">
                                            <p class="incident-name">Medical Emergency - 58 Park Ave</p>
                                            <p class="incident-location">Units: Medic 1, Engine 2</p>
                                        </div>
                                        <span class="status-badge status-completed">Stabilized</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                   
                    <div class="right-column">
                        <div class="card">
                            <h2 class="card-title">Emergency Alerts</h2>
                            <div class="alert-card">
                                <h3 class="alert-title">High Fire Risk - Northwest District</h3>
                                <p class="alert-time">Issued: Today 10:30 AM | Expires: Tomorrow 6:00 PM</p>
                                <button class="alert-button">
                                    <svg class="button-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4z"></path>
                                    </svg>
                                    View Details
                                </button>
                            </div>
                            <div class="alert-card">
                                <h3 class="alert-title">Hydrant Maintenance - Central Area</h3>
                                <p class="alert-time">Schedule: Tomorrow 8 AM - 4 PM | 15 hydrants affected</p>
                                <button class="alert-button">
                                    <svg class="button-icon" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M9.4 16.6L4.8 12l4.6-4.6L8 6l-6 6 6 6 1.4-1.4z"></path>
                                    </svg>
                                    View Map
                                </button>
                            </div>
                        </div>
                        
                        <!-- Personnel Status -->
                        <div class="card">
                            <div class="personnel-header">
                                <h2 class="card-title">Personnel Status</h2>
                                <button class="secondary-button" style="font-size: 14px; padding: 8px 16px;">Refresh</button>
                            </div>
                            <div class="personnel-list">
                                <div class="personnel-item">
                                    <div class="personnel-icon icon-cyan">
                                        <i class='bx bxs-user icon-cyan'></i>
                                    </div>
                                    <div class="personnel-info">
                                        <p class="personnel-name">Station 1 - A Shift</p>
                                        <p class="personnel-details">8 personnel on duty | 2 available</p>
                                    </div>
                                </div>
                                <div class="personnel-item">
                                    <div class="personnel-icon icon-purple">
                                        <i class='bx bxs-user icon-purple'></i>
                                    </div>
                                    <div class="personnel-info">
                                        <p class="personnel-name">Station 2 - B Shift</p>
                                        <p class="personnel-details">7 personnel on duty | 5 available</p>
                                    </div>
                                </div>
                                <div class="personnel-item">
                                    <div class="personnel-icon icon-indigo">
                                        <i class='bx bxs-user-badge icon-indigo'></i>
                                    </div>
                                    <div class="personnel-info">
                                        <p class="personnel-name">Special Operations</p>
                                        <p class="personnel-details">12 personnel | 8 on call</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Equipment Status -->
                        <div class="card">
                            <h2 class="card-title">Equipment Status</h2>
                            <div class="equipment-container">
                                <div class="equipment-circle">
                                    <svg class="equipment-svg">
                                        <circle cx="96" cy="96" r="80" class="equipment-background"></circle>
                                        <circle cx="96" cy="96" r="80" class="equipment-fill"></circle>
                                    </svg>
                                    <div class="equipment-text">
                                        <span class="equipment-value">96%</span>
                                        <span class="equipment-label">Operational</span>
                                    </div>
                                </div>
                            </div>
                            <div class="equipment-legend">
                                <div class="legend-item">
                                    <div class="legend-dot dot-operational"></div>
                                    <span class="text-gray-600">Operational</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-dot dot-maintenance"></div>
                                    <span class="text-gray-600">Maintenance</span>
                                </div>
                                <div class="legend-item">
                                    <div class="legend-dot dot-offline"></div>
                                    <span class="text-gray-600">Offline</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const animationOverlay = document.getElementById('dashboard-animation');
            const animationProgress = document.getElementById('animation-progress');
            const animationText = document.getElementById('animation-text');
            const animationLogo = document.querySelector('.animation-logo');
            
            setTimeout(() => {
            animationLogo.style.opacity = '1';
            animationLogo.style.transform = 'translateY(0)';
            }, 10);
            
            setTimeout(() => {
            animationText.style.opacity = '1';
            }, 600);
            
            setTimeout(() => {
            animationProgress.style.width = '180%';
            }, 100);
            
            setTimeout(() => {
            animationOverlay.style.opacity = '0';
            setTimeout(() => {
                animationOverlay.style.display = 'none';
            }, 500);
            }, 3000);
        });
        
        function toggleSubmenu(id) {
            const submenu = document.getElementById(id);
            const arrow = document.querySelector(`#${id}`).previousElementSibling.querySelector('.dropdown-arrow');
            
            submenu.classList.toggle('active');
            arrow.classList.toggle('rotated');
        }
        
        document.querySelectorAll('.menu-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.menu-item').forEach(i => {
                    i.classList.remove('active');
                });
                
                this.classList.add('active');
            });
        });
        
        document.querySelectorAll('.submenu-item').forEach(item => {
            item.addEventListener('click', function() {
                document.querySelectorAll('.submenu-item').forEach(i => {
                    i.classList.remove('active');
                });
                
                this.classList.add('active');
                
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                const nc = document.getElementById('nearby-cctv-section');
                const ia = document.getElementById('realtime-incident-alerts-section');
                const fr = document.getElementById('footage-request-log-section');
                if (nc) nc.style.display = 'none';
                if (ia) ia.style.display = 'none';
                if (fr) fr.style.display = 'none';
            });
        });
        
        const dutyScheduleLink = document.getElementById('duty-schedule-link');
        const dutyScheduleSection = document.getElementById('duty-schedule-section');
        const dutyScheduleBackBtn = document.getElementById('duty-schedule-back-btn');
        const confirmDeclineLink = document.getElementById('confirm-decline-duty-link');
        const confirmDeclineSection = document.getElementById('confirm-decline-duty-section');
        const confirmDeclineBackBtn = document.getElementById('confirm-decline-duty-back-btn');
        const timeLoggingLink = document.getElementById('time-logging-link');
        const timeLoggingSection = document.getElementById('time-logging-section');
        const timeLoggingBackBtn = document.getElementById('time-logging-back-btn');
        const timeInNowBtn = document.getElementById('time-in-now-btn');
        const timeOutNowBtn = document.getElementById('time-out-now-btn');
        const timeLoggingTbody = document.getElementById('time-logging-tbody');
        const assignedRouteLink = document.getElementById('assigned-route-link');
        const assignedRouteSection = document.getElementById('assigned-route-section');
        const assignedRouteBackBtn = document.getElementById('assigned-route-back-btn');
        const patrolActivityLink = document.getElementById('patrol-activity-link');
        const patrolActivitySection = document.getElementById('patrol-activity-section');
        const patrolActivityBackBtn = document.getElementById('patrol-activity-back-btn');
        const createNotesBtn = document.getElementById('create-notes-btn');
        const patrolActivityTbody = document.getElementById('patrol-activity-tbody');
        const activityModal = document.getElementById('activity-modal');
        const activityTypeSelect = document.getElementById('activity-type-select');
        const activityDescInput = document.getElementById('activity-desc-input');
        const activitySaveBtn = document.getElementById('activity-save-btn');
        const activityCancelBtn = document.getElementById('activity-cancel-btn');
        const checkpointLoggingLink = document.getElementById('checkpoint-logging-link');
        const checkpointLoggingSection = document.getElementById('checkpoint-logging-section');
        const checkpointLoggingBackBtn = document.getElementById('checkpoint-logging-back-btn');
        const checkpointLogNowBtn = document.getElementById('checkpoint-log-now-btn');
        const checkpointLoggingTbody = document.getElementById('checkpoint-logging-tbody');
        const receiveComplaintsLink = document.getElementById('receive-complaints-link');
        const receiveComplaintsSection = document.getElementById('receive-complaints-section');
        const receiveComplaintsBackBtn = document.getElementById('receive-complaints-back-btn');
        const complaintResolutionLink = document.getElementById('complaint-resolution-link');
        const complaintResolutionSection = document.getElementById('complaint-resolution-section');
        const complaintResolutionBackBtn = document.getElementById('complaint-resolution-back-btn');
        const complaintResolutionTbody = document.getElementById('complaint-resolution-tbody');
        const resolutionModal = document.getElementById('resolution-modal');
        const resolutionStatusSelect = document.getElementById('resolution-status-select');
        const resolutionDescInput = document.getElementById('resolution-desc-input');
        const resolutionSaveBtn = document.getElementById('resolution-save-btn');
        const resolutionCancelBtn = document.getElementById('resolution-cancel-btn');
        const resolutionComplaintId = document.getElementById('resolution-complaint-id');
        const caseHistoryLink = document.getElementById('case-history-link');
        const caseHistorySection = document.getElementById('case-history-section');
        const caseHistoryBackBtn = document.getElementById('case-history-back-btn');
        const assignedEquipmentLink = document.getElementById('assigned-equipment-link');
        const assignedEquipmentSection = document.getElementById('assigned-equipment-section');
        const assignedEquipmentBackBtn = document.getElementById('assigned-equipment-back-btn');
        const equipmentCheckinoutLink = document.getElementById('equipment-checkinout-link');
        const equipmentCheckinoutSection = document.getElementById('equipment-checkinout-section');
        const equipmentCheckinoutBackBtn = document.getElementById('equipment-checkinout-back-btn');
        const damageLossLink = document.getElementById('damage-loss-link');
        const damageLossSection = document.getElementById('damage-loss-section');
        const damageLossBackBtn = document.getElementById('damage-loss-back-btn');
        const inventoryStatusLink = document.getElementById('inventory-status-link');
        const inventoryStatusSection = document.getElementById('inventory-status-section');
        const inventoryStatusBackBtn = document.getElementById('inventory-status-back-btn');
        const defaultHeader = document.querySelector('.dashboard-content > .dashboard-header');
        const statsGrid = document.querySelector('.dashboard-content > .stats-grid');
        const defaultMainGrid = document.querySelector('.dashboard-content > .main-grid');
        const nearbyCctvLink = document.getElementById('nearby-cctv-link');
        const nearbyCctvSection = document.getElementById('nearby-cctv-section');
        const nearbyCctvBackBtn = document.getElementById('nearby-cctv-back-btn');
        const nearbySearchBtn = document.getElementById('nearby-search-btn');
        const nearbyIncidentInput = document.getElementById('nearby-incident-input');
        const nearbyCctvTbody = document.getElementById('nearby-cctv-tbody');
        const nearbyLiveDetails = document.getElementById('nearby-live-details');
        const realtimeIncidentAlertsLink = document.getElementById('realtime-incident-alerts-link');
        const realtimeIncidentAlertsSection = document.getElementById('realtime-incident-alerts-section');
        const alertsStartBtn = document.getElementById('alerts-start-btn');
        const alertsStopBtn = document.getElementById('alerts-stop-btn');
        const alertsBackBtn = document.getElementById('alerts-back-btn');
        const alertsAutostartToggle = document.getElementById('alerts-autostart-toggle');
        const incidentAlertsTbody = document.getElementById('incident-alerts-tbody');
        const footageRequestLogLink = document.getElementById('footage-request-log-link');
        const footageRequestLogSection = document.getElementById('footage-request-log-section');
        const footageRequestBackBtn = document.getElementById('footage-request-back-btn');
        const footageIncidentInput = document.getElementById('footage-incident-input');
        const footageReasonInput = document.getElementById('footage-reason-input');
        const footageSubmitBtn = document.getElementById('footage-submit-btn');
        const footageRequestTbody = document.getElementById('footage-request-tbody');
        function hideDefault() {
            if (defaultHeader) defaultHeader.style.display = 'none';
            if (statsGrid) statsGrid.style.display = 'none';
            if (defaultMainGrid) defaultMainGrid.style.display = 'none';
        }
        function showDefault() {
            if (defaultHeader) defaultHeader.style.display = '';
            if (statsGrid) statsGrid.style.display = '';
            if (defaultMainGrid) defaultMainGrid.style.display = '';
        }
        if (dutyScheduleLink && dutyScheduleSection) {
            dutyScheduleLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                dutyScheduleSection.style.display = 'block';
            });
        }
        if (dutyScheduleBackBtn && dutyScheduleSection) {
            dutyScheduleBackBtn.addEventListener('click', function() {
                dutyScheduleSection.style.display = 'none';
                showDefault();
            });
        }
        function cdKeyFromRow(row){
            const userId = confirmDeclineSection ? (confirmDeclineSection.getAttribute('data-user-id') || '') : '';
            const assigned = row.getAttribute('data-assigned') || '';
            const zone = row.getAttribute('data-zone') || '';
            const street = row.getAttribute('data-street') || '';
            return 'tanod_duty_resp|' + userId + '|' + assigned + '|' + zone + '|' + street;
        }
        function hydrateConfirmDecline(){
            if (!confirmDeclineSection) return;
            const rows = confirmDeclineSection.querySelectorAll('.cd-row');
            rows.forEach(row=>{
                const key = cdKeyFromRow(row);
                let status = '';
                try { status = localStorage.getItem(key) || ''; } catch(e){ status = ''; }
                const statusCell = row.querySelector('.cd-status');
                const confirmBtn = row.querySelector('.confirm-btn');
                const declineBtn = row.querySelector('.decline-btn');
                if (statusCell){
                    if (status === 'Confirmed'){
                        statusCell.textContent = 'Confirmed';
                        if (confirmBtn) confirmBtn.disabled = true;
                        if (declineBtn) declineBtn.disabled = true;
                    } else if (status === 'Declined'){
                        statusCell.textContent = 'Declined';
                        if (confirmBtn) confirmBtn.disabled = true;
                        if (declineBtn) declineBtn.disabled = true;
                    } else {
                        statusCell.textContent = 'Pending';
                        if (confirmBtn) confirmBtn.disabled = false;
                        if (declineBtn) declineBtn.disabled = false;
                    }
                }
            });
        }
        if (confirmDeclineLink && confirmDeclineSection) {
            confirmDeclineLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                confirmDeclineSection.style.display = 'block';
                hydrateConfirmDecline();
            });
        }
        if (confirmDeclineBackBtn && confirmDeclineSection) {
            confirmDeclineBackBtn.addEventListener('click', function() {
                confirmDeclineSection.style.display = 'none';
                showDefault();
            });
        }
        if (assignedRouteLink && assignedRouteSection) {
            assignedRouteLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                assignedRouteSection.style.display = 'block';
            });
        }
        if (assignedRouteBackBtn && assignedRouteSection) {
            assignedRouteBackBtn.addEventListener('click', function() {
                assignedRouteSection.style.display = 'none';
                showDefault();
            });
        }
        function alKey(){
            const userId = patrolActivitySection ? (patrolActivitySection.getAttribute('data-user-id') || '') : '';
            return 'tanod_patrol_activity_log|' + userId;
        }
        function loadActivityNotes(){
            try {
                const raw = localStorage.getItem(alKey()) || '[]';
                const arr = JSON.parse(raw);
                if (Array.isArray(arr)) return arr;
                return [];
            } catch(e){ return []; }
        }
        function saveActivityNotes(notes){
            try { localStorage.setItem(alKey(), JSON.stringify(notes || [])); } catch(e){}
        }
        function renderActivityNotes(){
            if (!patrolActivityTbody) return;
            const notes = loadActivityNotes().slice().sort((a,b)=>{
                const ta = new Date(a.created_at || 0).getTime();
                const tb = new Date(b.created_at || 0).getTime();
                return tb - ta;
            });
            patrolActivityTbody.innerHTML = '';
            if (!notes.length){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 3;
                td.style.padding = '14px';
                td.textContent = 'No activity notes yet.';
                tr.appendChild(td);
                patrolActivityTbody.appendChild(tr);
                return;
            }
            notes.forEach(n=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                const dt = n.created_at ? new Date(n.created_at).toLocaleString() : '—';
                const type = n.type || '—';
                const desc = n.desc || '—';
                tr.appendChild(tdWith(dt));
                tr.appendChild(tdWith(type));
                tr.appendChild(tdWith(desc));
                patrolActivityTbody.appendChild(tr);
            });
        }
        if (patrolActivityLink && patrolActivitySection) {
            patrolActivityLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                patrolActivitySection.style.display = 'block';
                renderActivityNotes();
            });
        }
        if (patrolActivityBackBtn && patrolActivitySection) {
            patrolActivityBackBtn.addEventListener('click', function() {
                patrolActivitySection.style.display = 'none';
                showDefault();
            });
        }
        if (createNotesBtn && activityModal) {
            createNotesBtn.addEventListener('click', function() {
                if (activityTypeSelect) activityTypeSelect.value = 'Observation';
                if (activityDescInput) activityDescInput.value = '';
                activityModal.style.display = 'flex';
            });
        }
        if (activityCancelBtn && activityModal) {
            activityCancelBtn.addEventListener('click', function() {
                activityModal.style.display = 'none';
            });
        }
        if (activitySaveBtn && activityModal) {
            activitySaveBtn.addEventListener('click', function() {
                const type = activityTypeSelect ? activityTypeSelect.value.trim() : '';
                const desc = activityDescInput ? activityDescInput.value.trim() : '';
                if (!type || !desc){
                    activityModal.style.display = 'none';
                    return;
                }
                const notes = loadActivityNotes();
                notes.push({ type: type, desc: desc, created_at: new Date().toISOString() });
                saveActivityNotes(notes);
                activityModal.style.display = 'none';
                renderActivityNotes();
            });
        }
        if (activityModal) {
            activityModal.addEventListener('click', function(e) {
                if (e.target === activityModal) {
                    activityModal.style.display = 'none';
                }
            });
        }
        function clKey(){
            const userId = checkpointLoggingSection ? (checkpointLoggingSection.getAttribute('data-user-id') || '') : '';
            return 'tanod_checkpoint_logs|' + userId;
        }
        function loadCheckpointLogs(){
            try {
                const raw = localStorage.getItem(clKey()) || '[]';
                const arr = JSON.parse(raw);
                if (Array.isArray(arr)) return arr;
                return [];
            } catch(e){ return []; }
        }
        function saveCheckpointLogs(logs){
            try { localStorage.setItem(clKey(), JSON.stringify(logs || [])); } catch(e){}
        }
        function renderCheckpointLogs(){
            if (!checkpointLoggingTbody) return;
            const logs = loadCheckpointLogs().slice().sort((a,b)=>{
                const ta = new Date(a.ts || 0).getTime();
                const tb = new Date(b.ts || 0).getTime();
                return tb - ta;
            });
            checkpointLoggingTbody.innerHTML = '';
            if (!logs.length){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 4;
                td.style.padding = '14px';
                td.textContent = 'No checkpoints logged yet.';
                tr.appendChild(td);
                checkpointLoggingTbody.appendChild(tr);
                return;
            }
            logs.forEach(n=>{
                const tr = document.createElement('tr');
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                const dt = n.ts ? new Date(n.ts).toLocaleString() : '—';
                const lat = typeof n.lat === 'number' ? n.lat.toFixed(6) : '—';
                const lon = typeof n.lon === 'number' ? n.lon.toFixed(6) : '—';
                const acc = typeof n.acc === 'number' ? n.acc.toFixed(1) : '—';
                tr.appendChild(tdWith(dt));
                tr.appendChild(tdWith(lat));
                tr.appendChild(tdWith(lon));
                tr.appendChild(tdWith(acc));
                checkpointLoggingTbody.appendChild(tr);
            });
        }
        if (checkpointLoggingLink && checkpointLoggingSection) {
            checkpointLoggingLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                checkpointLoggingSection.style.display = 'block';
                renderCheckpointLogs();
            });
        }
        if (checkpointLoggingBackBtn && checkpointLoggingSection) {
            checkpointLoggingBackBtn.addEventListener('click', function() {
                checkpointLoggingSection.style.display = 'none';
                showDefault();
            });
        }
        if (receiveComplaintsLink && receiveComplaintsSection) {
            receiveComplaintsLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                receiveComplaintsSection.style.display = 'block';
            });
        }
        if (receiveComplaintsBackBtn && receiveComplaintsSection) {
            receiveComplaintsBackBtn.addEventListener('click', function() {
                receiveComplaintsSection.style.display = 'none';
                showDefault();
            });
        }
        if (complaintResolutionLink && complaintResolutionSection) {
            complaintResolutionLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                complaintResolutionSection.style.display = 'block';
            });
        }
        if (complaintResolutionBackBtn && complaintResolutionSection) {
            complaintResolutionBackBtn.addEventListener('click', function() {
                complaintResolutionSection.style.display = 'none';
                showDefault();
            });
        }
        if (caseHistoryLink && caseHistorySection) {
            caseHistoryLink.addEventListener('click', function(e){
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                caseHistorySection.style.display = 'block';
            });
        }
        if (caseHistoryBackBtn && caseHistorySection) {
            caseHistoryBackBtn.addEventListener('click', function(){
                caseHistorySection.style.display = 'none';
                showDefault();
            });
        }
        if (assignedEquipmentLink && assignedEquipmentSection) {
            assignedEquipmentLink.addEventListener('click', function(e){
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                assignedEquipmentSection.style.display = 'block';
            });
        }
        if (assignedEquipmentBackBtn && assignedEquipmentSection) {
            assignedEquipmentBackBtn.addEventListener('click', function(){
                assignedEquipmentSection.style.display = 'none';
                showDefault();
            });
        }
        if (complaintResolutionSection && resolutionModal) {
            complaintResolutionSection.addEventListener('click', function(e){
                const t = e.target;
                if (t && t.classList.contains('cr-update-btn')) {
                    const row = t.closest('.cr-row');
                    if (row) {
                        const id = row.getAttribute('data-id') || '';
                        const st = row.getAttribute('data-status') || 'pending';
                        const respCell = row.querySelector('.cr-response');
                        const currentResp = respCell ? respCell.textContent : '';
                        if (resolutionComplaintId) resolutionComplaintId.textContent = id;
                        if (resolutionStatusSelect) resolutionStatusSelect.value = st;
                        if (resolutionDescInput) resolutionDescInput.value = currentResp === '—' ? '' : currentResp;
                        resolutionModal.style.display = 'flex';
                    }
                }
            });
        }
        if (resolutionCancelBtn && resolutionModal) {
            resolutionCancelBtn.addEventListener('click', function() {
                resolutionModal.style.display = 'none';
            });
        }
        if (resolutionSaveBtn && resolutionModal) {
            resolutionSaveBtn.addEventListener('click', function() {
                const id = resolutionComplaintId ? resolutionComplaintId.textContent.trim() : '';
                const st = resolutionStatusSelect ? resolutionStatusSelect.value.trim() : '';
                const desc = resolutionDescInput ? resolutionDescInput.value.trim() : '';
                if (!id || !st) {
                    resolutionModal.style.display = 'none';
                    return;
                }
                const body = new URLSearchParams();
                body.set('update_complaint', '1');
                body.set('feedback_id', id);
                body.set('new_status', st);
                body.set('actions_taken', desc);
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(r=>r.json()).then(data=>{
                    if (data && data.ok) {
                        const row = complaintResolutionTbody ? complaintResolutionTbody.querySelector(`.cr-row[data-id="${id}"]`) : null;
                        if (row) {
                            const statusCell = row.querySelector('.cr-status');
                            const respCell = row.querySelector('.cr-response');
                            row.setAttribute('data-status', st);
                            if (statusCell) statusCell.textContent = st.charAt(0).toUpperCase() + st.slice(1);
                            if (respCell) respCell.textContent = desc || '—';
                        }
                    } else {
                        alert('Failed to update complaint.');
                    }
                    resolutionModal.style.display = 'none';
                }).catch(()=>{
                    alert('Failed to update complaint.');
                    resolutionModal.style.display = 'none';
                });
            });
        }
        if (checkpointLogNowBtn && checkpointLoggingSection) {
            checkpointLogNowBtn.addEventListener('click', function() {
                if (navigator.geolocation && navigator.geolocation.getCurrentPosition) {
                    navigator.geolocation.getCurrentPosition(function(pos){
                        const notes = loadCheckpointLogs();
                        notes.push({
                            ts: new Date().toISOString(),
                            lat: pos.coords.latitude,
                            lon: pos.coords.longitude,
                            acc: pos.coords.accuracy
                        });
                        saveCheckpointLogs(notes);
                        renderCheckpointLogs();
                    }, function(){
                        alert('Failed to capture location.');
                    }, { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 });
                } else {
                    alert('Geolocation not supported.');
                }
            });
        }
        if (confirmDeclineSection) {
            confirmDeclineSection.addEventListener('click', function(e){
                const t = e.target;
                if (t && t.classList.contains('confirm-btn')) {
                    const row = t.closest('.cd-row');
                    if (row) {
                        const key = cdKeyFromRow(row);
                        const statusCell = row.querySelector('.cd-status');
                        try { localStorage.setItem(key, 'Confirmed'); } catch(e){}
                        if (statusCell) statusCell.textContent = 'Confirmed';
                        const declineBtn = row.querySelector('.decline-btn');
                        t.disabled = true;
                        if (declineBtn) declineBtn.disabled = true;
                    }
                } else if (t && t.classList.contains('decline-btn')) {
                    const row = t.closest('.cd-row');
                    if (row) {
                        const key = cdKeyFromRow(row);
                        const statusCell = row.querySelector('.cd-status');
                        try { localStorage.setItem(key, 'Declined'); } catch(e){}
                        if (statusCell) statusCell.textContent = 'Declined';
                        const confirmBtn = row.querySelector('.confirm-btn');
                        t.disabled = true;
                        if (confirmBtn) confirmBtn.disabled = true;
                    }
                }
            });
        }
        function tlKey(){
            const userId = timeLoggingSection ? (timeLoggingSection.getAttribute('data-user-id') || '') : '';
            return 'tanod_time_logs|' + userId;
        }
        function loadTimeLogs(){
            try {
                const raw = localStorage.getItem(tlKey()) || '[]';
                const arr = JSON.parse(raw);
                if (Array.isArray(arr)) return arr;
                return [];
            } catch(e){ return []; }
        }
        function saveTimeLogs(logs){
            try { localStorage.setItem(tlKey(), JSON.stringify(logs || [])); } catch(e){}
        }
        function renderTimeLogs(){
            if (!timeLoggingTbody) return;
            const logs = loadTimeLogs().slice().sort((a,b)=>{
                const ta = new Date(a.check_in || a.date || 0).getTime();
                const tb = new Date(b.check_in || b.date || 0).getTime();
                return tb - ta;
            });
            timeLoggingTbody.innerHTML = '';
            if (!logs.length){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 6;
                td.style.padding = '14px';
                td.textContent = 'No duty hours logged yet.';
                tr.appendChild(td);
                timeLoggingTbody.appendChild(tr);
                return;
            }
            logs.forEach((rec, idx)=>{
                const tr = document.createElement('tr');
                tr.setAttribute('data-idx', String(idx));
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                const ci = rec.check_in ? new Date(rec.check_in) : null;
                const co = rec.check_out ? new Date(rec.check_out) : null;
                const dateStr = ci ? ci.toISOString().substring(0,10) : (rec.date || '—');
                const inStr = ci ? ci.toLocaleString() : '—';
                const outStr = co ? co.toLocaleString() : '—';
                let hours = '—';
                if (ci && co){
                    const diff = (co.getTime() - ci.getTime()) / 3600000;
                    hours = (diff > 0 ? diff : 0).toFixed(2);
                }
                const status = co ? 'Completed' : (ci ? 'In Progress' : 'Not Started');
                tr.appendChild(tdWith(dateStr));
                tr.appendChild(tdWith(inStr));
                tr.appendChild(tdWith(outStr));
                tr.appendChild(tdWith(hours));
                const statusTd = tdWith(status);
                statusTd.className = 'tl-status';
                tr.appendChild(statusTd);
                const actionTd = document.createElement('td');
                actionTd.style.padding = '10px';
                if (ci && !co){
                    const btn = document.createElement('button');
                    btn.className = 'secondary-button tl-timeout-btn';
                    btn.textContent = 'Time Out';
                    actionTd.appendChild(btn);
                } else {
                    const span = document.createElement('span');
                    span.textContent = '—';
                    actionTd.appendChild(span);
                }
                tr.appendChild(actionTd);
                timeLoggingTbody.appendChild(tr);
            });
        }
        if (timeLoggingLink && timeLoggingSection) {
            timeLoggingLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                timeLoggingSection.style.display = 'block';
                renderTimeLogs();
            });
        }
        if (equipmentCheckinoutLink && equipmentCheckinoutSection) {
            equipmentCheckinoutLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                equipmentCheckinoutSection.style.display = 'block';
            });
        }
        if (equipmentCheckinoutBackBtn && equipmentCheckinoutSection) {
            equipmentCheckinoutBackBtn.addEventListener('click', function(){
                equipmentCheckinoutSection.style.display = 'none';
                showDefault();
            });
        }
        if (damageLossLink && damageLossSection) {
            damageLossLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                hideDefault();
                damageLossSection.style.display = 'block';
            });
        }
        if (damageLossBackBtn && damageLossSection) {
            damageLossBackBtn.addEventListener('click', function(){
                damageLossSection.style.display = 'none';
                showDefault();
            });
        }
        if (inventoryStatusLink && inventoryStatusSection) {
            inventoryStatusLink.addEventListener('click', function(e) {
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                hideDefault();
                inventoryStatusSection.style.display = 'block';
            });
        }
        if (inventoryStatusBackBtn && inventoryStatusSection) {
            inventoryStatusBackBtn.addEventListener('click', function(){
                inventoryStatusSection.style.display = 'none';
                showDefault();
            });
        }
        let cctvDeviceIds = {};
        let cctvStream = null;
        function renderNearbyCameras(filterText){
            if (!nearbyCctvTbody) return;
            const cams = [
                {id:1,name:'Entrance Cam',location:'Main Gate',distance:120},
                {id:2,name:'Lobby Cam',location:'Municipal Hall Lobby',distance:240},
                {id:3,name:'Parking Cam',location:'North Parking',distance:320},
                {id:4,name:'Market Cam',location:'Public Market',distance:450},
                {id:5,name:'Plaza Cam',location:'Town Plaza',distance:600}
            ];
            const q = (filterText || '').toLowerCase();
            const list = cams.filter(c=>{
                if (!q) return true;
                return c.location.toLowerCase().includes(q) || c.name.toLowerCase().includes(q);
            });
            nearbyCctvTbody.innerHTML = '';
            if (!list.length){
                const tr = document.createElement('tr');
                const td = document.createElement('td');
                td.colSpan = 5;
                td.style.padding = '14px';
                td.textContent = 'No cameras found near the specified location.';
                tr.appendChild(td);
                nearbyCctvTbody.appendChild(tr);
                return;
            }
            list.forEach(c=>{
                const tr = document.createElement('tr');
                tr.className = 'cctv-row';
                tr.setAttribute('data-id', String(c.id));
                tr.setAttribute('data-name', c.name);
                tr.setAttribute('data-location', c.location);
                function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
                tr.appendChild(tdWith(c.name));
                tr.appendChild(tdWith(c.location));
                tr.appendChild(tdWith((c.distance || 0) + ' m'));
                const statusTd = tdWith('Offline');
                statusTd.className = 'cctv-status';
                tr.appendChild(statusTd);
                const actionTd = document.createElement('td');
                actionTd.style.padding = '10px';
                const openBtn = document.createElement('button');
                openBtn.className = 'primary-button open-cam-btn';
                openBtn.textContent = 'Open Camera';
                const connectBtn = document.createElement('button');
                connectBtn.className = 'secondary-button connect-cam-btn';
                connectBtn.textContent = 'Connect External Camera';
                connectBtn.style.marginLeft = '8px';
                actionTd.appendChild(openBtn);
                actionTd.appendChild(connectBtn);
                tr.appendChild(actionTd);
                nearbyCctvTbody.appendChild(tr);
            });
        }
        function stopCctvStream(){
            try {
                if (cctvStream && cctvStream.getTracks) {
                    cctvStream.getTracks().forEach(t=>t.stop());
                }
            } catch(e){}
            cctvStream = null;
            if (nearbyLiveDetails) nearbyLiveDetails.innerHTML = '';
        }
        function openCam(row){
            const cid = parseInt(row.getAttribute('data-id') || '0', 10);
            const deviceId = cctvDeviceIds[cid] || '';
            const constraints = { video: deviceId ? { deviceId: { exact: deviceId } } : { width: 640, height: 360 } , audio: false };
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
                if (nearbyLiveDetails) nearbyLiveDetails.textContent = 'Camera APIs not supported.';
                return;
            }
            navigator.mediaDevices.getUserMedia(constraints).then(stream=>{
                stopCctvStream();
                cctvStream = stream;
                const video = document.createElement('video');
                video.style.width = '100%';
                video.style.maxWidth = '640px';
                video.style.borderRadius = '6px';
                video.autoplay = true;
                video.playsInline = true;
                video.srcObject = stream;
                nearbyLiveDetails.innerHTML = '';
                nearbyLiveDetails.appendChild(video);
                const statusTd = row.querySelector('.cctv-status');
                if (statusTd) { statusTd.textContent = 'Online'; }
            }).catch(()=>{
                if (nearbyLiveDetails) nearbyLiveDetails.textContent = 'Failed to open camera.';
            });
        }
        function connectCam(row){
            const cid = parseInt(row.getAttribute('data-id') || '0', 10);
            if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
                alert('Camera APIs not supported.');
                return;
            }
            navigator.mediaDevices.getUserMedia({ video: true, audio: false }).then(stream=>{
                return navigator.mediaDevices.enumerateDevices();
            }).then(devs=>{
                const cam = devs.find(d=>d.kind === 'videoinput');
                if (cam){
                    cctvDeviceIds[cid] = cam.deviceId;
                    try { localStorage.setItem('tanod_cctv_device_ids', JSON.stringify(cctvDeviceIds)); } catch(e){}
                    const statusTd = row.querySelector('.cctv-status');
                    if (statusTd) statusTd.textContent = 'Online';
                } else {
                    alert('No camera found.');
                }
            }).catch(()=>{
                alert('Camera permission denied or unavailable.');
            });
        }
        if (nearbyCctvLink && nearbyCctvSection) {
            nearbyCctvLink.addEventListener('click', function(e){
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                if (realtimeIncidentAlertsSection) realtimeIncidentAlertsSection.style.display = 'none';
                if (footageRequestLogSection) footageRequestLogSection.style.display = 'none';
                hideDefault();
                nearbyCctvSection.style.display = 'block';
                stopCctvStream();
                renderNearbyCameras('');
                try {
                    const saved = localStorage.getItem('tanod_cctv_device_ids') || '{}';
                    cctvDeviceIds = JSON.parse(saved);
                } catch(e){ cctvDeviceIds = {}; }
            });
        }
        if (nearbyCctvBackBtn && nearbyCctvSection) {
            nearbyCctvBackBtn.addEventListener('click', function(){
                stopCctvStream();
                nearbyCctvSection.style.display = 'none';
                showDefault();
            });
        }
        if (nearbySearchBtn && nearbyIncidentInput) {
            nearbySearchBtn.addEventListener('click', function(){
                const q = nearbyIncidentInput.value.trim();
                renderNearbyCameras(q);
            });
        }
        if (nearbyCctvSection) {
            nearbyCctvSection.addEventListener('click', function(e){
                const t = e.target;
                if (t && t.classList.contains('open-cam-btn')) {
                    const row = t.closest('.cctv-row');
                    if (row) openCam(row);
                } else if (t && t.classList.contains('connect-cam-btn')) {
                    const row = t.closest('.cctv-row');
                    if (row) connectCam(row);
                }
            });
        }
        let alertsTimer = null;
        function appendAlert(item){
            if (!incidentAlertsTbody) return;
            if (incidentAlertsTbody.querySelector('tr') && incidentAlertsTbody.querySelector('tr').children.length === 1) {
                incidentAlertsTbody.innerHTML = '';
            }
            const tr = document.createElement('tr');
            function tdWith(nodeOrText){ const td=document.createElement('td'); td.style.padding='10px'; if (typeof nodeOrText==='string'){ td.textContent=nodeOrText; } else { td.appendChild(nodeOrText); } return td; }
            tr.appendChild(tdWith(item.type));
            tr.appendChild(tdWith(new Date(item.ts).toLocaleString()));
            tr.appendChild(tdWith(item.location));
            const btn = document.createElement('button');
            btn.className = 'secondary-button';
            btn.textContent = 'View on CCTV';
            btn.addEventListener('click', function(){
                if (nearbyCctvLink) nearbyCctvLink.click();
                if (nearbyIncidentInput) nearbyIncidentInput.value = item.location;
                renderNearbyCameras(item.location);
            });
            tr.appendChild(tdWith(btn));
            incidentAlertsTbody.appendChild(tr);
        }
        function startAlerts(){
            if (alertsTimer) return;
            alertsTimer = setInterval(function(){
                const types = ['Accident detected','Violation occurred','Incident reported'];
                const locs = ['Main Gate','Municipal Hall Lobby','North Parking','Public Market','Town Plaza','Zone 2 - Street B'];
                const item = { type: types[Math.floor(Math.random()*types.length)], location: locs[Math.floor(Math.random()*locs.length)], ts: Date.now() };
                appendAlert(item);
            }, 3500);
        }
        function stopAlerts(){
            if (alertsTimer) { clearInterval(alertsTimer); alertsTimer = null; }
        }
        if (realtimeIncidentAlertsLink && realtimeIncidentAlertsSection) {
            realtimeIncidentAlertsLink.addEventListener('click', function(e){
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                if (nearbyCctvSection) nearbyCctvSection.style.display = 'none';
                if (footageRequestLogSection) footageRequestLogSection.style.display = 'none';
                hideDefault();
                realtimeIncidentAlertsSection.style.display = 'block';
                if (alertsAutostartToggle && alertsAutostartToggle.checked) startAlerts();
            });
        }
        if (alertsBackBtn && realtimeIncidentAlertsSection) {
            alertsBackBtn.addEventListener('click', function(){
                stopAlerts();
                realtimeIncidentAlertsSection.style.display = 'none';
                showDefault();
            });
        }
        if (alertsStartBtn) {
            alertsStartBtn.addEventListener('click', function(){ startAlerts(); });
        }
        if (alertsStopBtn) {
            alertsStopBtn.addEventListener('click', function(){ stopAlerts(); });
        }
        function appendFootageRow(rec){
            if (!footageRequestTbody) return;
            const tr = document.createElement('tr');
            function tdWith(text){ const td=document.createElement('td'); td.style.padding='10px'; td.textContent=text; return td; }
            const dt = rec.requested_at ? rec.requested_at : new Date().toISOString().substring(0,19).replace('T',' ');
            tr.appendChild(tdWith(dt));
            tr.appendChild(tdWith(rec.requested_name || '—'));
            tr.appendChild(tdWith(rec.incident_ref || '—'));
            tr.appendChild(tdWith(rec.reason || '—'));
            tr.appendChild(tdWith(rec.status ? rec.status.charAt(0).toUpperCase()+rec.status.slice(1) : 'Pending'));
            footageRequestTbody.insertBefore(tr, footageRequestTbody.firstChild);
        }
        if (footageRequestLogLink && footageRequestLogSection) {
            footageRequestLogLink.addEventListener('click', function(e){
                e.preventDefault();
                if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
                if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
                if (timeLoggingSection) timeLoggingSection.style.display = 'none';
                if (assignedRouteSection) assignedRouteSection.style.display = 'none';
                if (patrolActivitySection) patrolActivitySection.style.display = 'none';
                if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
                if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
                if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
                if (caseHistorySection) caseHistorySection.style.display = 'none';
                if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
                if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
                if (damageLossSection) damageLossSection.style.display = 'none';
                if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
                if (nearbyCctvSection) nearbyCctvSection.style.display = 'none';
                if (realtimeIncidentAlertsSection) realtimeIncidentAlertsSection.style.display = 'none';
                hideDefault();
                footageRequestLogSection.style.display = 'block';
            });
        }
        if (footageRequestBackBtn && footageRequestLogSection) {
            footageRequestBackBtn.addEventListener('click', function(){
                footageRequestLogSection.style.display = 'none';
                showDefault();
            });
        }
        if (footageSubmitBtn) {
            footageSubmitBtn.addEventListener('click', function(){
                const inc = footageIncidentInput ? footageIncidentInput.value.trim() : '';
                const rsn = footageReasonInput ? footageReasonInput.value.trim() : '';
                if (!inc){
                    return;
                }
                const body = new URLSearchParams();
                body.set('request_footage', '1');
                body.set('incident_ref', inc);
                body.set('reason', rsn);
                fetch(window.location.href, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: body.toString()
                }).then(r=>r.json()).then(data=>{
                    if (data && data.ok && data.record) {
                        appendFootageRow(data.record);
                        if (footageIncidentInput) footageIncidentInput.value = '';
                        if (footageReasonInput) footageReasonInput.value = '';
                    } else {
                        alert('Failed to submit request.');
                    }
                }).catch(()=>{
                    alert('Failed to submit request.');
                });
            });
        }
        if (timeLoggingBackBtn && timeLoggingSection) {
            timeLoggingBackBtn.addEventListener('click', function() {
                timeLoggingSection.style.display = 'none';
                showDefault();
            });
        }
        if (timeInNowBtn && timeLoggingSection) {
            timeInNowBtn.addEventListener('click', function(){
                const logs = loadTimeLogs();
                const now = new Date().toISOString();
                logs.push({ check_in: now });
                saveTimeLogs(logs);
                renderTimeLogs();
            });
        }
        if (timeOutNowBtn && timeLoggingSection) {
            timeOutNowBtn.addEventListener('click', function(){
                const logs = loadTimeLogs();
                for (let i=logs.length-1; i>=0; i--){
                    const rec = logs[i];
                    if (rec && rec.check_in && !rec.check_out){
                        rec.check_out = new Date().toISOString();
                        break;
                    }
                }
                saveTimeLogs(logs);
                renderTimeLogs();
            });
        }
        if (timeLoggingSection) {
            timeLoggingSection.addEventListener('click', function(e){
                const t = e.target;
                if (t && t.classList.contains('tl-timeout-btn')){
                    const row = t.closest('tr');
                    const idx = row ? parseInt(row.getAttribute('data-idx') || '-1', 10) : -1;
                    if (idx >= 0){
                        const logs = loadTimeLogs();
                        if (logs[idx] && logs[idx].check_in && !logs[idx].check_out){
                            logs[idx].check_out = new Date().toISOString();
                            saveTimeLogs(logs);
                            renderTimeLogs();
                        }
                    }
                }
            });
        }
        
        const themeToggle = document.getElementById('theme-toggle');
        const themeIcon = themeToggle.querySelector('i');
        const themeText = themeToggle.querySelector('span');
        
        themeToggle.addEventListener('click', function() {
            document.body.classList.toggle('dark-mode');
            
            if (document.body.classList.contains('dark-mode')) {
                themeIcon.className = 'bx bx-sun';
                themeText.textContent = 'Light Mode';
            } else {
                themeIcon.className = 'bx bx-moon';
                themeText.textContent = 'Dark Mode';
            }
        });
        
        window.addEventListener('load', function() {
            const bars = document.querySelectorAll('.chart-bar-value');
            bars.forEach(bar => {
                const height = bar.style.height;
                bar.style.height = '0%';
                setTimeout(() => {
                    bar.style.height = height;
                }, 300);
            });
        });
        
        document.querySelectorAll('.card').forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-5px)';
            });
            
            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });
        
        function updateTime() {
            const now = new Date();
            const utc = now.getTime() + (now.getTimezoneOffset() * 60000);
            const gmt8 = new Date(utc + (8 * 3600000));
            
            const hours = gmt8.getHours().toString().padStart(2, '0');
            const minutes = gmt8.getMinutes().toString().padStart(2, '0');
            const seconds = gmt8.getSeconds().toString().padStart(2, '0');
            
            const timeString = `${hours}:${minutes}:${seconds} UTC+8`;
            document.getElementById('current-time').textContent = timeString;
        }
        
        updateTime();
        setInterval(updateTime, 1000);
        
        const settingsButton = document.getElementById('settings-button');
        const settingsDropdown = document.getElementById('settings-dropdown');
        const settingsContainer = document.querySelector('.settings-dropdown-container');
        if (settingsButton && settingsDropdown && settingsContainer) {
            settingsDropdown.classList.remove('active');
            settingsContainer.classList.remove('open');
            settingsButton.setAttribute('aria-expanded', 'false');
            settingsButton.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                const isOpen = settingsDropdown.classList.contains('active');
                if (isOpen) {
                    settingsDropdown.classList.remove('active');
                    settingsContainer.classList.remove('open');
                    settingsButton.setAttribute('aria-expanded','false');
                } else {
                    settingsDropdown.classList.add('active');
                    settingsContainer.classList.add('open');
                    settingsButton.setAttribute('aria-expanded','true');
                }
            });
            document.addEventListener('click', function(e){
                if (!settingsContainer.contains(e.target)) {
                    settingsDropdown.classList.remove('active');
                    settingsContainer.classList.remove('open');
                    settingsButton.setAttribute('aria-expanded','false');
                }
            });
        }
        const sidebarSettingsBtn = document.getElementById('sidebar-settings-btn');
        const sidebarSettingsSubmenu = document.getElementById('sidebar-settings-submenu');
        if (sidebarSettingsBtn && sidebarSettingsSubmenu) {
            function closeSidebarSettings() {
                sidebarSettingsSubmenu.classList.remove('active');
                sidebarSettingsBtn.setAttribute('aria-expanded', 'false');
            }
            function openSidebarSettings() {
                if (settingsDropdown) { settingsDropdown.classList.remove('active'); }
                if (settingsContainer) { settingsContainer.classList.remove('open'); }
                if (settingsButton) { settingsButton.setAttribute('aria-expanded','false'); }
                sidebarSettingsSubmenu.classList.add('active');
                sidebarSettingsBtn.setAttribute('aria-expanded', 'true');
            }
            sidebarSettingsBtn.addEventListener('click', function(e){
                e.preventDefault();
                e.stopPropagation();
                if (sidebarSettingsSubmenu.classList.contains('active')) {
                    closeSidebarSettings();
                } else {
                    openSidebarSettings();
                }
            });
            sidebarSettingsBtn.addEventListener('keydown', function(e){
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    e.stopPropagation();
                    if (sidebarSettingsSubmenu.classList.contains('active')) {
                        closeSidebarSettings();
                    } else {
                        openSidebarSettings();
                    }
                }
                if (e.key === 'Escape') {
                    closeSidebarSettings();
                }
            });
            document.addEventListener('click', function(e){
                if (!sidebarSettingsSubmenu.contains(e.target) && !sidebarSettingsBtn.contains(e.target)) {
                    closeSidebarSettings();
                }
            });
            document.addEventListener('keydown', function(e){
                if (e.key === 'Escape') {
                    closeSidebarSettings();
                }
            });
        }
        const settingsProfileBtn = document.getElementById('settings-profile-btn');
        const settingsSecurityBtn = document.getElementById('settings-security-btn');
        const sidebarSettingsProfileLink = document.getElementById('sidebar-settings-profile-link');
        const sidebarSettingsSecurityLink = document.getElementById('sidebar-settings-security-link');
        const settingsTabProfile = document.getElementById('settings-tab-profile');
        const settingsTabSecurity = document.getElementById('settings-tab-security');
        function showSection(id){ 
            if (dutyScheduleSection) dutyScheduleSection.style.display = 'none';
            if (confirmDeclineSection) confirmDeclineSection.style.display = 'none';
            if (timeLoggingSection) timeLoggingSection.style.display = 'none';
            if (assignedRouteSection) assignedRouteSection.style.display = 'none';
            if (patrolActivitySection) patrolActivitySection.style.display = 'none';
            if (checkpointLoggingSection) checkpointLoggingSection.style.display = 'none';
            if (receiveComplaintsSection) receiveComplaintsSection.style.display = 'none';
            if (complaintResolutionSection) complaintResolutionSection.style.display = 'none';
            if (caseHistorySection) caseHistorySection.style.display = 'none';
            if (assignedEquipmentSection) assignedEquipmentSection.style.display = 'none';
            if (equipmentCheckinoutSection) equipmentCheckinoutSection.style.display = 'none';
            if (damageLossSection) damageLossSection.style.display = 'none';
            if (inventoryStatusSection) inventoryStatusSection.style.display = 'none';
            if (nearbyCctvSection) nearbyCctvSection.style.display = 'none';
            if (realtimeIncidentAlertsSection) realtimeIncidentAlertsSection.style.display = 'none';
            if (footageRequestLogSection) footageRequestLogSection.style.display = 'none';
            hideDefault();
            const el=document.getElementById(id); 
            if (el) el.style.display='block'; 
        }
        if (settingsProfileBtn) settingsProfileBtn.addEventListener('click', function(){ showSection('settings-profile-section'); if (settingsDropdown) settingsDropdown.classList.remove('active'); loadProfile(); });
        if (settingsSecurityBtn) settingsSecurityBtn.addEventListener('click', function(){ showSection('settings-security-section'); if (settingsDropdown) settingsDropdown.classList.remove('active'); });
        if (settingsTabProfile) settingsTabProfile.addEventListener('click', function(){ showSection('settings-profile-section'); loadProfile(); });
        if (settingsTabSecurity) settingsTabSecurity.addEventListener('click', function(){ showSection('settings-security-section'); });
        if (sidebarSettingsProfileLink) sidebarSettingsProfileLink.addEventListener('click', function(e){ e.preventDefault(); showSection('settings-profile-section'); loadProfile(); });
        if (sidebarSettingsSecurityLink) sidebarSettingsSecurityLink.addEventListener('click', function(e){ e.preventDefault(); showSection('settings-security-section'); });
        const profileForm = document.getElementById('profile-form');
        const profileStatus = document.getElementById('profile-status');
        const avatarInput = document.getElementById('profile-avatar-input');
        const avatarUploadBtn = document.getElementById('profile-avatar-upload-btn');
        const avatarStatus = document.getElementById('avatar-status');
        const avatarPreview = document.getElementById('profile-avatar-preview');
        const headerAvatar = document.querySelector('.user-avatar');
        const profileInputsStyle = document.createElement('style');
        profileInputsStyle.textContent = '#profile-form .modal-input{font-size:18px;padding:14px 12px;}#profile-form input::placeholder{font-size:18px;opacity:.8;}';
        document.head.appendChild(profileInputsStyle);
        async function loadProfile(){
            try{
                const fd = new FormData();
                fd.append('action','profile_get');
                const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                const data = await res.json();
                if (data && data.success && data.profile){
                    const p = data.profile;
                    if (profileForm){
                        profileForm.elements['first_name'].value = p.first_name || '';
                        profileForm.elements['middle_name'].value = p.middle_name || '';
                        profileForm.elements['last_name'].value = p.last_name || '';
                        profileForm.elements['username'].value = p.username || '';
                        profileForm.elements['email'].value = p.email || '';
                        profileForm.elements['contact'].value = p.contact || '';
                        profileForm.elements['address'].value = p.address || '';
                        profileForm.elements['date_of_birth'].value = p.date_of_birth || '';
                    }
                    const url = p.avatar_url ? ('../'+p.avatar_url) : null;
                    if (url){
                        if (avatarPreview) avatarPreview.src = url;
                        if (headerAvatar) headerAvatar.src = url;
                    }
                }
            }catch(_){}
        }
        if (profileForm){
            profileForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const fd = new FormData(profileForm);
                fd.append('action','profile_update');
                try{
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (profileStatus){
                        profileStatus.textContent = data && data.success ? 'Profile updated' : (data && data.error ? data.error : 'Update failed');
                        profileStatus.style.color = data && data.success ? '#16a34a' : '#dc2626';
                    }
                }catch(_){
                    if (profileStatus){ profileStatus.textContent = 'Network error'; profileStatus.style.color = '#dc2626'; }
                }
            });
        }
        if (avatarUploadBtn){
            avatarUploadBtn.addEventListener('click', async function(){
                const file = avatarInput && avatarInput.files && avatarInput.files[0] ? avatarInput.files[0] : null;
                if (!file){ if (avatarStatus){ avatarStatus.textContent = 'Select an image'; avatarStatus.style.color = '#dc2626'; } return; }
                try{
                    const fd = new FormData();
                    fd.append('action','profile_avatar_upload');
                    fd.append('avatar', file);
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success && data.avatar_url){
                        const url = '../'+data.avatar_url;
                        if (avatarPreview) avatarPreview.src = url;
                        if (headerAvatar) headerAvatar.src = url;
                        if (avatarStatus){ avatarStatus.textContent = 'Avatar updated'; avatarStatus.style.color = '#16a34a'; }
                    } else {
                        if (avatarStatus){ avatarStatus.textContent = data && data.error ? data.error : 'Upload failed'; avatarStatus.style.color = '#dc2626'; }
                    }
                }catch(_){
                    if (avatarStatus){ avatarStatus.textContent = 'Network error'; avatarStatus.style.color = '#dc2626'; }
                }
            });
        }
        function openModal(id){ const el=document.getElementById(id); if(el) el.style.display='flex'; }
        function closeModal(id){ const el=document.getElementById(id); if(el) el.style.display='none'; }
        const genKeyBtn = document.getElementById('security-generate-key-btn');
        const apiStatusEl = document.getElementById('api-status');
        if (genKeyBtn && apiStatusEl) {
            genKeyBtn.addEventListener('click', async function(){
                try{
                    const fd = new FormData();
                    fd.append('action','security_generate_key');
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success && data.api_key){
                        apiStatusEl.textContent = 'API key generated: ' + data.api_key.slice(0,8) + '••••••••';
                        alert('Your API key: ' + data.api_key + '\\nCopy and store it securely. You will not be able to view it again.');
                    } else {
                        apiStatusEl.textContent = 'Failed to generate API key';
                    }
                }catch(_){
                    apiStatusEl.textContent = 'Network error';
                }
            });
        }
        const tfaBtn = document.getElementById('security-enable-2fa-btn');
        const tfaStatusEl = document.getElementById('tfa-status');
        let tfaEnabled = false;
        if (tfaBtn && tfaStatusEl) {
            tfaBtn.addEventListener('click', async function(){
                try{
                    const fd = new FormData();
                    fd.append('action','security_toggle_2fa');
                    fd.append('enabled', tfaEnabled ? '0' : '1');
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success){
                        tfaEnabled = !!data.enabled;
                        tfaStatusEl.innerHTML = tfaEnabled ? '<span class=\"badge badge-active\">Enabled</span>' : '<span class=\"badge badge-pending\">Disabled</span>';
                        tfaBtn.textContent = tfaEnabled ? 'Disable' : 'Enable';
                    }
                }catch(_){}
            });
        }
        const pwdBtn = document.getElementById('security-change-password-btn');
        const pwdLastChanged = document.getElementById('pwd-last-changed');
        if (pwdBtn && pwdLastChanged) {
            const pwdModal = document.createElement('div');
            pwdModal.id = 'pwd-modal';
            pwdModal.style = 'position:fixed;left:0;top:0;width:100%;height:100%;display:none;align-items:center;justify-content:center;background:rgba(17,24,39,.25);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:1000;';
            pwdModal.innerHTML = '<div style=\"background:#fff;width:520px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);\"><div style=\"display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;\"><div style=\"font-weight:600;\">Change Password</div><button class=\"secondary-button\" id=\"pwd-close\">Close</button></div><form id=\"pwd-form\" style=\"padding:16px;display:grid;gap:12px;\"><input class=\"modal-input\" type=\"password\" name=\"current_password\" placeholder=\"Current password\" required><input class=\"modal-input\" type=\"password\" name=\"new_password\" placeholder=\"New password\" required><input class=\"modal-input\" type=\"password\" name=\"confirm_password\" placeholder=\"Confirm new password\" required><div style=\"display:flex;justify-content:flex-end;gap:8px;\"><button type=\"submit\" class=\"primary-button\">Update</button></div><div id=\"pwd-status\" style=\"margin-top:8px;font-weight:500;\"></div></form></div>';
            document.body.appendChild(pwdModal);
            const pwdClose = pwdModal.querySelector('#pwd-close');
            const pwdForm = pwdModal.querySelector('#pwd-form');
            const pwdStatus = pwdModal.querySelector('#pwd-status');
            pwdBtn.addEventListener('click', function(){ openModal('pwd-modal'); });
            if (pwdClose) pwdClose.addEventListener('click', function(){ closeModal('pwd-modal'); if (pwdStatus) pwdStatus.textContent=''; });
            pwdForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const fd = new FormData(pwdForm);
                const a = fd.get('new_password'); const b = fd.get('confirm_password');
                if (String(a||'') !== String(b||'')){ if(pwdStatus){ pwdStatus.textContent = 'Passwords do not match'; pwdStatus.style.color='#dc2626'; } return; }
                fd.delete('confirm_password');
                fd.append('action','security_change_password');
                try{
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (pwdStatus){
                        pwdStatus.textContent = data && data.success ? 'Password updated' : (data && data.error ? data.error : 'Update failed');
                        pwdStatus.style.color = data && data.success ? '#16a34a' : '#dc2626';
                    }
                    if (data && data.success){ pwdLastChanged.textContent = 'Just changed'; }
                }catch(_){
                    if (pwdStatus){ pwdStatus.textContent = 'Network error'; pwdStatus.style.color = '#dc2626'; }
                }
            });
        }
        const emailBtn = document.getElementById('security-change-email-btn');
        const emailAddressEl = document.getElementById('email-address');
        if (emailBtn && emailAddressEl) {
            const emailModal = document.createElement('div');
            emailModal.id = 'email-modal';
            emailModal.style = 'position:fixed;left:0;top:0;width:100%;height:100%;display:none;align-items:center;justify-content:center;background:rgba(17,24,39,.25);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:1000;';
            emailModal.innerHTML = '<div style=\"background:#fff;width:520px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);\"><div style=\"display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;\"><div style=\"font-weight:600;\">Change Email</div><button class=\"secondary-button\" id=\"email-close\">Close</button></div><form id=\"email-form\" style=\"padding:16px;display:grid;gap:12px;\"><input class=\"modal-input\" type=\"email\" name=\"new_email\" placeholder=\"New email\" required><div style=\"display:flex;justify-content:flex-end;gap:8px;\"><button type=\"submit\" class=\"primary-button\">Update</button></div><div id=\"email-status\" style=\"margin-top:8px;font-weight:500;\"></div></form></div>';
            document.body.appendChild(emailModal);
            const emailClose = emailModal.querySelector('#email-close');
            const emailForm = emailModal.querySelector('#email-form');
            const emailStatus = emailModal.querySelector('#email-status');
            emailBtn.addEventListener('click', function(){ openModal('email-modal'); });
            if (emailClose) emailClose.addEventListener('click', function(){ closeModal('email-modal'); if (emailStatus) emailStatus.textContent=''; });
            emailForm.addEventListener('submit', async function(e){
                e.preventDefault();
                const fd = new FormData(emailForm);
                fd.append('action','security_change_email');
                try{
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (emailStatus){
                        emailStatus.textContent = data && data.success ? 'Email updated' : (data && data.error ? data.error : 'Update failed');
                        emailStatus.style.color = data && data.success ? '#16a34a' : '#dc2626';
                    }
                    if (data && data.success && data.email){ emailAddressEl.textContent = data.email; }
                }catch(_){
                    if (emailStatus){ emailStatus.textContent = 'Network error'; emailStatus.style.color = '#dc2626'; }
                }
            });
        }
        const delBtn = document.getElementById('security-delete-account-btn');
        if (delBtn) {
            const delModal = document.createElement('div');
            delModal.id = 'del-modal';
            delModal.style = 'position:fixed;left:0;top:0;width:100%;height:100%;display:none;align-items:center;justify-content:center;background:rgba(17,24,39,.25);backdrop-filter:blur(6px);-webkit-backdrop-filter:blur(6px);z-index:1000;';
            delModal.innerHTML = '<div style=\"background:#fff;width:520px;max-width:90%;border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.15);\"><div style=\"display:flex;align-items:center;justify-content:space-between;padding:14px 16px;border-bottom:1px solid #e5e7eb;\"><div style=\"font-weight:600;\">Delete Account</div><button class=\"secondary-button\" id=\"del-close\">Close</button></div><div style=\"padding:16px;display:grid;gap:12px;\"><div>This action will permanently delete your account.</div><div style=\"display:flex;justify-content:flex-end;gap:8px;\"><button class=\"secondary-button\" id=\"del-confirm\" style=\"background:#ef4444;color:#fff;border-color:#ef4444;\">Confirm Delete</button></div><div id=\"del-status\" style=\"margin-top:8px;font-weight:500;\"></div></div></div>';
            document.body.appendChild(delModal);
            const delClose = delModal.querySelector('#del-close');
            const delConfirm = delModal.querySelector('#del-confirm');
            const delStatus = delModal.querySelector('#del-status');
            delBtn.addEventListener('click', function(){ openModal('del-modal'); });
            if (delClose) delClose.addEventListener('click', function(){ closeModal('del-modal'); if (delStatus) delStatus.textContent=''; });
            if (delConfirm) delConfirm.addEventListener('click', async function(){
                try{
                    const fd = new FormData();
                    fd.append('action','security_delete_account');
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success){
                        window.location.href = '../includes/logout.php';
                    } else {
                        if (delStatus){ delStatus.textContent = data && data.error ? data.error : 'Delete failed'; delStatus.style.color = '#dc2626'; }
                    }
                }catch(_){
                    if (delStatus){ delStatus.textContent = 'Network error'; delStatus.style.color = '#dc2626'; }
                }
            });
        }
        (function(){
            const adminMessagesLink = document.getElementById('admin-messages-link');
            const adminMessagesSection = document.getElementById('admin-messages-section');
            const adminMessagesBackBtn = document.getElementById('admin-messages-back-btn');
            const roleMsgChat = document.getElementById('role-msg-chat');
            function renderRoleChat(messages){
                if (!roleMsgChat) return;
                roleMsgChat.innerHTML = '';
                const all = messages || [];
                all.forEach(m=>{
                    const bubble = document.createElement('div');
                    bubble.style.padding = '10px 12px';
                    bubble.style.borderRadius = '12px';
                    bubble.style.maxWidth = '70%';
                    bubble.style.background = '#fff';
                    bubble.style.color = '#111827';
                    bubble.style.boxShadow = '0 1px 2px rgba(0,0,0,.06)';
                    bubble.textContent = m.message || '';
                    const meta = document.createElement('div');
                    meta.style.fontSize = '12px';
                    meta.style.color = '#6b7280';
                    meta.style.marginTop = '6px';
                    const dt = m.created_at ? new Date(m.created_at) : new Date();
                    meta.textContent = dt.toLocaleTimeString([], { hour:'2-digit', minute:'2-digit' });
                    const row = document.createElement('div');
                    row.style.display = 'flex';
                    row.style.alignItems = 'center';
                    row.style.justifyContent = 'flex-start';
                    const wrap = document.createElement('div');
                    wrap.style.margin = '10px';
                    wrap.appendChild(bubble);
                    wrap.appendChild(meta);
                    row.appendChild(wrap);
                    roleMsgChat.appendChild(row);
                });
                roleMsgChat.scrollTop = roleMsgChat.scrollHeight;
            }
            async function loadRoleMessages(){
                try{
                    const fd = new FormData();
                    fd.append('action','role_messages_list');
                    const res = await fetch('tanod_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    const msgs = (data && data.success && Array.isArray(data.messages)) ? data.messages : [];
                    renderRoleChat(msgs);
                }catch(_){
                    renderRoleChat([]);
                }
            }
            let roleMsgTimer = null;
            if (adminMessagesLink && adminMessagesSection) {
                adminMessagesLink.addEventListener('click', function(e){
                    e.preventDefault();
                    document.querySelectorAll('.content-section').forEach(s => { s.style.display = 'none'; });
                    adminMessagesSection.style.display = 'block';
                    loadRoleMessages();
                    if (roleMsgTimer) { clearInterval(roleMsgTimer); }
                    roleMsgTimer = setInterval(loadRoleMessages, 2000);
                });
            }
            if (adminMessagesBackBtn && adminMessagesSection) {
                adminMessagesBackBtn.addEventListener('click', function(){
                    adminMessagesSection.style.display = 'none';
                    if (roleMsgTimer) { clearInterval(roleMsgTimer); roleMsgTimer = null; }
                    const home = document.querySelector('.dashboard-content > .dashboard-header');
                    const stats = document.querySelector('.dashboard-content > .stats-grid');
                    const main = document.querySelector('.dashboard-content > .main-grid');
                    if (home) home.style.display = '';
                    if (stats) stats.style.display = '';
                    if (main) main.style.display = '';
                });
            }
        })();
    </script>
</body>
</html>
        }
    </script>
</body>
</html>
        }
    </script>
</body>
</html>
        }
    </script>
</body>
</html>
