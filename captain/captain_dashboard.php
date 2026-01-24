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
    $role = "USER";
    $avatar_path = '../img/rei.jfif';
}

$stmt = null;
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
        } elseif ($action === 'role_messages_list') {
            try {
                $pdo->exec("CREATE TABLE IF NOT EXISTS messages (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    sender_id INT NOT NULL,
                    recipient_role ENUM('TANOD','SECRETARY','CAPTAIN') NOT NULL,
                    message TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'enc_message'");
                    $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC);
                    if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN enc_message TEXT DEFAULT NULL"); }
                } catch (Exception $e) {}
                try {
                    $chk = $pdo->query("SHOW COLUMNS FROM messages LIKE 'iv'");
                    $ex = $chk && $chk->fetch(PDO::FETCH_ASSOC);
                    if (!$ex) { $pdo->exec("ALTER TABLE messages ADD COLUMN iv VARCHAR(64) DEFAULT NULL"); }
                } catch (Exception $e) {}
                $stmt = $pdo->prepare("SELECT enc_message, iv, message, created_at FROM messages WHERE recipient_role = 'CAPTAIN' ORDER BY created_at DESC, id DESC LIMIT 200");
                $stmt->execute([]);
                $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
                $out = [];
                foreach ($rows as $r) {
                    $text = cps_decrypt_text($r['enc_message'] ?? '', $r['iv'] ?? '');
                    if ($text === '' && !empty($r['message'])) { $text = (string)$r['message']; }
                    $out[] = ['message' => $text, 'created_at' => $r['created_at']];
                }
                echo json_encode(['success'=>true,'messages'=>$out]);
                exit();
            } catch (Exception $e) {
                echo json_encode(['success'=>false,'messages'=>[]]);
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
            <span class="animation-logo-text">Community Policing and Surveillance</span>
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
                <span class="logo-text">Community Policing and Surveillance</span>
            </div>
            
            <!-- Menu Section -->
            <div class="menu-section">
                <p class="menu-title">COMMUNITY POLICING & SURVEILLANCE MANAGEMENT</p>
                
                <div class="menu-items">
                    <a href="#" class="menu-item active" id="dashboard-menu">
                        <div class="icon-box icon-bg-red">
                            <i class='bx bxs-dashboard icon-red'></i>
                        </div>
                        <span class="font-medium">Dashboard</span>
                    </a>
                    
                    <!-- Fire & Incident Reporting -->
                    <div class="menu-item" onclick="toggleSubmenu('fire-incident')">
                        <div class="icon-box icon-bg-orange">
                            <i class='bx bxs-alarm-exclamation icon-orange'></i>
                        </div>
                        <span class="font-medium">Barangay Overview</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="fire-incident" class="submenu">
                        <a href="#" class="submenu-item" id="barangay-overview-link" data-target="barangay-overview-section">Barangay Safety Overview</a>
                        <a href="#" class="submenu-item" id="performance-indicators-link" data-target="performance-indicators-section">Performance Indicators</a>
                        <a href="#" class="submenu-item" id="events-activities-link" data-target="events-activities-section">Upcoming Events & Activities</a>
                    </div>
                    
                    <!-- Dispatch Coordination -->
                    <div class="menu-item" onclick="toggleSubmenu('dispatch')">
                        <div class="icon-box icon-bg-yellow">
                            <i class='bx bxs-truck icon-yellow'></i>
                        </div>
                        <span class="font-medium">Approval Management</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="dispatch" class="submenu">
                        <a href="#" class="submenu-item" id="incident-approval-link" data-target="incident-approval-section">Incident Approval</a>
                        <a href="#" class="submenu-item" id="request-approval-link" data-target="request-approval-section">Request Approval</a>
                        <a href="#" class="submenu-item" id="event-program-approval-link" data-target="event-program-approval-section">Event & Program Approval</a>
                    </div>
            
                    <div class="menu-item" onclick="toggleSubmenu('volunteer')">
                        <div class="icon-box icon-bg-blue">
                            <i class='bx bxs-user-detail icon-blue'></i>
                        </div>
                        <span class="font-medium">Rules and Regulations</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="volunteer" class="submenu">
                        <a href="#" class="submenu-item" id="barangay-policy-management-link" data-target="barangay-policy-management-section">Barangay Policy Management</a>
                        <a href="#" class="submenu-item" id="ordinance-tracker-link" data-target="ordinance-tracker-section">Ordinance Tracker</a>
                        <a href="#" class="submenu-item" id="legal-reference-link" data-target="legal-reference-section">Legal Reference Repository</a>
                    </div>
                    <!-- Resource Inventory Updates -->
                    <div class="menu-item" onclick="toggleSubmenu('inventory')">
                        <div class="icon-box icon-bg-green">
                            <i class='bx bxs-cube icon-green'></i>
                        </div>
                        <span class="font-medium">Community Programs Oversight</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="inventory" class="submenu">
                        <a href="#" class="submenu-item" id="volunteer-program-review-link" data-target="volunteer-program-review-section">Volunteer Program Review</a>
                        <a href="#" class="submenu-item" id="neighborhood-watch-review-link" data-target="neighborhood-watch-review-section">Neighborhood Watch Review</a>
                        <a href="#" class="submenu-item" id="official-announcements-link" data-target="official-announcements-section">Official Announcements</a>
                        <a href="#" class="submenu-item" id="feedback-review-link" data-target="feedback-review-section">Feedback Review</a>
                    </div>
                    
                    <!-- Shift & Duty Scheduling -->
                    <div class="menu-item" onclick="toggleSubmenu('schedule')">
                        <div class="icon-box icon-bg-purple">
                            <i class='bx bxs-calendar icon-purple'></i>
                        </div>
                        <span class="font-medium">Reports & Decision Support</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="schedule" class="submenu">
                        <a href="#" class="submenu-item" id="executive-summary-link" data-target="executive-summary-section">Executive Summary Reports</a>
                        <a href="#" class="submenu-item" id="risk-preparedness-link" data-target="risk-preparedness-section">Risk & Preparedness Assessment</a>
                        <a href="#" class="submenu-item" id="strategic-action-link" data-target="strategic-action-section">Strategic Action Planning Tool</a>
                        <a href="#" class="submenu-item" id="official-approval-link" data-target="official-approval-section">Official Approval</a>
                    </div>
                    <div class="menu-item" onclick="toggleSubmenu('training')">
                        <div class="icon-box icon-bg-teal">
                            <i class='bx bxs-graduation icon-teal'></i>
                    </div>
                        <span class="font-medium">Safety & Resolution Watch</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="training" class="submenu">
                        <a href="#" class="submenu-item" id="complaint-review-link" data-target="complaint-review-section">Complaint Review</a>
                        <a href="#" class="submenu-item" id="incident-review-link" data-target="incident-review-section">Incident Review</a>
                        <a href="#" class="submenu-item" id="final-disposition-reports-link" data-target="final-disposition-reports-section">Final Disposition Reports</a>
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
                    <div class="menu-item" onclick="toggleSubmenu('messages')">
                        <div class="icon-box icon-bg-blue">
                            <i class='bx bxs-chat icon-blue'></i>
                        </div>
                        <span class="font-medium">Admin Messages</span>
                        <svg class="dropdown-arrow menu-icon" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </div>
                    <div id="messages" class="submenu">
                        <a href="#" class="submenu-item" id="admin-messages-link">Admin Messages</a>
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
                
            </div>
            
            <!-- Dashboard Content -->
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
                <div class="content-section" id="admin-messages-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Admin Messages</h1>
                            <p class="dashboard-subtitle">Messages sent by Admin to Captains</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button" id="admin-messages-back-btn">Back to Dashboard</button>
                        </div>
                    </div>
                    <div class="main-grid">
                        <div class="left-column">
                            <div class="card">
                                <h2 class="card-title">Chat</h2>
                                <div id="role-msg-chat" style="padding:12px;max-height:420px;overflow-y:auto;background:#f9fafb;border-radius:8px;"></div>
                            </div>
                        </div>
                        <div class="right-column">
                            <div class="card">
                                <h2 class="card-title">Notes</h2>
                                <p style="margin-top:8px;line-height:1.6;">Messages are refreshed automatically.</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div id="default-dashboard-section">
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
                
                <div id="barangay-overview-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Barangay Safety Overview</h1>
                            <p class="dashboard-subtitle">Summary of peace-and-order situation.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Refresh</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Reported Crimes</span>
                            </div>
                            <div class="stat-value">12</div>
                            <div class="stat-info">
                                <span>Past 30 days</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Logged Incidents</span>
                            </div>
                            <div class="stat-value">27</div>
                            <div class="stat-info">
                                <span>Accidents & disturbances</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Community Complaints</span>
                            </div>
                            <div class="stat-value">18</div>
                            <div class="stat-info">
                                <span>All statuses</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Resolved Complaints</span>
                            </div>
                            <div class="stat-value">11</div>
                            <div class="stat-info">
                                <span>Resolution rate</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Summary Table</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Category</th>
                                        <th style="text-align:left; padding:8px;">Description</th>
                                        <th style="text-align:right; padding:8px;">Count</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Reported Crimes</td>
                                        <td style="padding:8px;">Index and non-index crimes</td>
                                        <td style="padding:8px; text-align:right;">12</td>
                                        <td style="padding:8px;">—</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Logged Incidents</td>
                                        <td style="padding:8px;">Accidents, disturbances</td>
                                        <td style="padding:8px; text-align:right;">27</td>
                                        <td style="padding:8px;">—</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Complaints</td>
                                        <td style="padding:8px;">Community reports</td>
                                        <td style="padding:8px; text-align:right;">18</td>
                                        <td style="padding:8px;">11 resolved, 5 pending, 2 in progress</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Complaints Status</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Complaint</th>
                                        <th style="text-align:left; padding:8px;">Category</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Noise disturbance at Zone 3</td>
                                        <td style="padding:8px;">Disturbance</td>
                                        <td style="padding:8px;">Pending</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Vandalism at covered court</td>
                                        <td style="padding:8px;">Property</td>
                                        <td style="padding:8px;">Resolved</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Traffic obstruction at Market Road</td>
                                        <td style="padding:8px;">Public order</td>
                                        <td style="padding:8px;">In Progress</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="performance-indicators-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Performance Indicators</h1>
                            <p class="dashboard-subtitle">Safety personnel performance metrics.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Refresh</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Patrols Completed</span>
                            </div>
                            <div class="stat-value">85</div>
                            <div class="stat-info">
                                <span>This month</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Volunteer Participation</span>
                            </div>
                            <div class="stat-value">62</div>
                            <div class="stat-info">
                                <span>Active volunteers</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Avg Response Time</span>
                            </div>
                            <div class="stat-value">3.8<span style="font-size: 24px;">min</span></div>
                            <div class="stat-info">
                                <span>To incidents</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Coverage</span>
                            </div>
                            <div class="stat-value">95%</div>
                            <div class="stat-info">
                                <span>Patrol area coverage</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Performance Summary</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Indicator</th>
                                        <th style="text-align:left; padding:8px;">Value</th>
                                        <th style="text-align:left; padding:8px;">Period</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Patrols completed</td>
                                        <td style="padding:8px;">85</td>
                                        <td style="padding:8px;">This month</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Volunteer participation</td>
                                        <td style="padding:8px;">62</td>
                                        <td style="padding:8px;">Active</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Average response time</td>
                                        <td style="padding:8px;">3.8 min</td>
                                        <td style="padding:8px;">Rolling 30 days</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="events-activities-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Upcoming Events & Activities</h1>
                            <p class="dashboard-subtitle">Scheduled barangay safety-related activities.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">New Event</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Schedule</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Event</th>
                                        <th style="text-align:left; padding:8px;">Date</th>
                                        <th style="text-align:left; padding:8px;">Time</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Safety Seminar</td>
                                        <td style="padding:8px;">Jan 25, 2026</td>
                                        <td style="padding:8px;">2:00 PM</td>
                                        <td style="padding:8px;">Scheduled</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Community Patrol</td>
                                        <td style="padding:8px;">Jan 20, 2026</td>
                                        <td style="padding:8px;">6:30 PM</td>
                                        <td style="padding:8px;">Scheduled</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Disaster Preparedness Drill</td>
                                        <td style="padding:8px;">Feb 05, 2026</td>
                                        <td style="padding:8px;">9:00 AM</td>
                                        <td style="padding:8px;">Planned</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="incident-approval-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Incident Approval</h1>
                            <p class="dashboard-subtitle">Official confirmation to close incidents and complaints.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Refresh</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Approval Workflow Summary</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Case ID</th>
                                        <th style="text-align:left; padding:8px;">Type</th>
                                        <th style="text-align:left; padding:8px;">Investigation</th>
                                        <th style="text-align:left; padding:8px;">Action Taken</th>
                                        <th style="text-align:left; padding:8px;">Approver</th>
                                        <th style="text-align:left; padding:8px;">Approval Date</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">INC-2026-001</td>
                                        <td style="padding:8px;">Incident</td>
                                        <td style="padding:8px;">Completed</td>
                                        <td style="padding:8px;">Patrol deployed</td>
                                        <td style="padding:8px;">Barangay Captain</td>
                                        <td style="padding:8px;">2026-01-14</td>
                                        <td style="padding:8px;">Closed</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">CMP-2026-017</td>
                                        <td style="padding:8px;">Complaint</td>
                                        <td style="padding:8px;">Completed</td>
                                        <td style="padding:8px;">Mediation held</td>
                                        <td style="padding:8px;">Barangay Captain</td>
                                        <td style="padding:8px;">2026-01-12</td>
                                        <td style="padding:8px;">Closed</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">INC-2026-009</td>
                                        <td style="padding:8px;">Incident</td>
                                        <td style="padding:8px;">In Review</td>
                                        <td style="padding:8px;">Referral to PNP</td>
                                        <td style="padding:8px;">Pending</td>
                                        <td style="padding:8px;">—</td>
                                        <td style="padding:8px;">Pending Approval</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="request-approval-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Request Approval</h1>
                            <p class="dashboard-subtitle">Controls for resource acquisition and usage approvals.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">New Request</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Resource Requests</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Request ID</th>
                                        <th style="text-align:left; padding:8px;">Item</th>
                                        <th style="text-align:right; padding:8px;">Quantity</th>
                                        <th style="text-align:left; padding:8px;">Purpose</th>
                                        <th style="text-align:left; padding:8px;">Requester</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                        <th style="text-align:left; padding:8px;">Decision</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">REQ-2026-042</td>
                                        <td style="padding:8px;">Radios</td>
                                        <td style="padding:8px; text-align:right;">8</td>
                                        <td style="padding:8px;">Patrol coordination</td>
                                        <td style="padding:8px;">Safety Unit</td>
                                        <td style="padding:8px;">Pending</td>
                                        <td style="padding:8px;">—</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">REQ-2026-045</td>
                                        <td style="padding:8px;">Flashlights</td>
                                        <td style="padding:8px; text-align:right;">20</td>
                                        <td style="padding:8px;">Night patrols</td>
                                        <td style="padding:8px;">Watch Program</td>
                                        <td style="padding:8px;">Approved</td>
                                        <td style="padding:8px;">By Captain</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">REQ-2026-051</td>
                                        <td style="padding:8px;">First Aid Kits</td>
                                        <td style="padding:8px; text-align:right;">15</td>
                                        <td style="padding:8px;">Event readiness</td>
                                        <td style="padding:8px;">Health Desk</td>
                                        <td style="padding:8px;">In Review</td>
                                        <td style="padding:8px;">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="event-program-approval-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Event & Program Approval</h1>
                            <p class="dashboard-subtitle">Official approval for barangay activities and programs.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">New Approval</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Activities Requiring Approval</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Approval ID</th>
                                        <th style="text-align:left; padding:8px;">Activity</th>
                                        <th style="text-align:left; padding:8px;">Date</th>
                                        <th style="text-align:left; padding:8px;">Organizer</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                        <th style="text-align:left; padding:8px;">Decision</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">APR-2026-010</td>
                                        <td style="padding:8px;">Disaster Preparedness Drill</td>
                                        <td style="padding:8px;">2026-02-05</td>
                                        <td style="padding:8px;">DRRMO</td>
                                        <td style="padding:8px;">Scheduled</td>
                                        <td style="padding:8px;">Approved</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">APR-2026-012</td>
                                        <td style="padding:8px;">Safety Seminar</td>
                                        <td style="padding:8px;">2026-01-25</td>
                                        <td style="padding:8px;">Safety Unit</td>
                                        <td style="padding:8px;">Planned</td>
                                        <td style="padding:8px;">In Review</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">APR-2026-016</td>
                                        <td style="padding:8px;">Community Awareness Campaign</td>
                                        <td style="padding:8px;">2026-02-12</td>
                                        <td style="padding:8px;">Barangay Council</td>
                                        <td style="padding:8px;">Pending</td>
                                        <td style="padding:8px;">—</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="barangay-policy-management-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Barangay Policy Management</h1>
                            <p class="dashboard-subtitle">Manage official policies, rules, and internal guidelines.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Add Policy</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Policy Catalog</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Policy</th>
                                        <th style="text-align:left; padding:8px;">Type</th>
                                        <th style="text-align:left; padding:8px;">Version</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                        <th style="text-align:left; padding:8px;">Last Update</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Curfew Hours</td>
                                        <td style="padding:8px;">Community Rule</td>
                                        <td style="padding:8px;">v1.3</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">2025-12-10</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Noise Control Policy</td>
                                        <td style="padding:8px;">Community Rule</td>
                                        <td style="padding:8px;">v2.1</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">2025-11-22</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Sanitation Guidelines</td>
                                        <td style="padding:8px;">Community Rule</td>
                                        <td style="padding:8px;">v1.8</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">2025-10-05</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Internal Patrol Procedure</td>
                                        <td style="padding:8px;">Internal Procedure</td>
                                        <td style="padding:8px;">v3.0</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">2025-12-02</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Incident Documentation SOP</td>
                                        <td style="padding:8px;">Internal Procedure</td>
                                        <td style="padding:8px;">v2.4</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">2025-09-18</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="ordinance-tracker-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Ordinance Tracker</h1>
                            <p class="dashboard-subtitle">Monitor ordinance enforcement and status.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Update Enforcement</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Enforcement Status</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Ordinance No.</th>
                                        <th style="text-align:left; padding:8px;">Title</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                        <th style="text-align:left; padding:8px;">Enforcement Actions</th>
                                        <th style="text-align:left; padding:8px;">Pending Areas/Cases</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">2024-05</td>
                                        <td style="padding:8px;">Curfew Ordinance</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">Patrol checks; notices issued</td>
                                        <td style="padding:8px;">Zone 5 compliance</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">2023-11</td>
                                        <td style="padding:8px;">Noise Control Ordinance</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">Warnings; fines collected</td>
                                        <td style="padding:8px;">Market vendors</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">2022-07</td>
                                        <td style="padding:8px;">Waste Segregation Ordinance</td>
                                        <td style="padding:8px;">Active</td>
                                        <td style="padding:8px;">Seminars; community reminders</td>
                                        <td style="padding:8px;">Covered court vicinity</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="legal-reference-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Legal Reference Repository</h1>
                            <p class="dashboard-subtitle">Centralized storage for legal and reference documents.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Add Document</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Document Library</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Document Title</th>
                                        <th style="text-align:left; padding:8px;">Type</th>
                                        <th style="text-align:left; padding:8px;">Source</th>
                                        <th style="text-align:left; padding:8px;">Issued/Effective</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Barangay Ordinance No. 2024-05 (Curfew)</td>
                                        <td style="padding:8px;">Ordinance</td>
                                        <td style="padding:8px;">Barangay Council</td>
                                        <td style="padding:8px;">2024-06-01</td>
                                        <td style="padding:8px;">Active</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Resolution 2025-12: Community Patrol Program</td>
                                        <td style="padding:8px;">Resolution</td>
                                        <td style="padding:8px;">Barangay Council</td>
                                        <td style="padding:8px;">2025-08-15</td>
                                        <td style="padding:8px;">Active</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">RA 10121: DRRM Act</td>
                                        <td style="padding:8px;">National Law</td>
                                        <td style="padding:8px;">Congress</td>
                                        <td style="padding:8px;">2010-05-27</td>
                                        <td style="padding:8px;">Reference</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">DILG Memo Circular 2025-01</td>
                                        <td style="padding:8px;">Memo Circular</td>
                                        <td style="padding:8px;">DILG</td>
                                        <td style="padding:8px;">2025-01-03</td>
                                        <td style="padding:8px;">Active</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Legal Advisory: Mediation Procedures</td>
                                        <td style="padding:8px;">Legal Advisory</td>
                                        <td style="padding:8px;">Barangay Legal</td>
                                        <td style="padding:8px;">2025-07-22</td>
                                        <td style="padding:8px;">Reference</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="volunteer-program-review-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Volunteer Program Review</h1>
                            <p class="dashboard-subtitle">Monitor volunteer engagement and activity participation.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Add Volunteer</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Active Volunteers</span>
                            </div>
                            <div class="stat-value">120</div>
                            <div class="stat-info">
                                <span>Currently registered</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Event Attendance</span>
                            </div>
                            <div class="stat-value">86<span style="font-size: 24px;">%</span></div>
                            <div class="stat-info">
                                <span>Last 3 events</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Patrol Attendance</span>
                            </div>
                            <div class="stat-value">68<span style="font-size: 24px;">%</span></div>
                            <div class="stat-info">
                                <span>Past 30 days</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Inactive/Dropouts</span>
                            </div>
                            <div class="stat-value">14</div>
                            <div class="stat-info">
                                <span>Needs re-engagement</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Volunteer Engagement Summary</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Metric</th>
                                        <th style="text-align:left; padding:8px;">Value</th>
                                        <th style="text-align:left; padding:8px;">Period</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Active volunteers</td>
                                        <td style="padding:8px;">120</td>
                                        <td style="padding:8px;">Current</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Event attendance</td>
                                        <td style="padding:8px;">86%</td>
                                        <td style="padding:8px;">Last 3 events</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Patrol attendance</td>
                                        <td style="padding:8px;">68%</td>
                                        <td style="padding:8px;">Past 30 days</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Inactive/dropouts</td>
                                        <td style="padding:8px;">14</td>
                                        <td style="padding:8px;">Current</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="neighborhood-watch-review-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Neighborhood Watch Review</h1>
                            <p class="dashboard-subtitle">Evaluate neighborhood watch coverage and impact.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Update Coverage</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Areas Covered</span>
                            </div>
                            <div class="stat-value">8</div>
                            <div class="stat-info">
                                <span>Active watch zones</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Reports Submitted</span>
                            </div>
                            <div class="stat-value">34</div>
                            <div class="stat-info">
                                <span>Past 30 days</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Incident Reduction</span>
                            </div>
                            <div class="stat-value">12<span style="font-size: 24px;">%</span></div>
                            <div class="stat-info">
                                <span>Compared to last month</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Active Groups</span>
                            </div>
                            <div class="stat-value">5</div>
                            <div class="stat-info">
                                <span>Barangay-wide</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Watch Group Assessment</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Area</th>
                                        <th style="text-align:left; padding:8px;">Coverage</th>
                                        <th style="text-align:left; padding:8px;">Reports</th>
                                        <th style="text-align:left; padding:8px;">Impact</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Zone 1</td>
                                        <td style="padding:8px;">High</td>
                                        <td style="padding:8px;">12</td>
                                        <td style="padding:8px;">Reduced petty theft</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Zone 3</td>
                                        <td style="padding:8px;">Medium</td>
                                        <td style="padding:8px;">8</td>
                                        <td style="padding:8px;">Fewer disturbances</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Zone 5</td>
                                        <td style="padding:8px;">Low</td>
                                        <td style="padding:8px;">3</td>
                                        <td style="padding:8px;">Minimal change</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="official-announcements-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Official Announcements</h1>
                            <p class="dashboard-subtitle">Release barangay information and advisories.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">New Announcement</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Announcements</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Title</th>
                                        <th style="text-align:left; padding:8px;">Category</th>
                                        <th style="text-align:left; padding:8px;">Date</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Barangay Assembly</td>
                                        <td style="padding:8px;">Community</td>
                                        <td style="padding:8px;">2026-01-28</td>
                                        <td style="padding:8px;">Scheduled</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Emergency Advisory: Heavy Rainfall</td>
                                        <td style="padding:8px;">Advisory</td>
                                        <td style="padding:8px;">2026-01-18</td>
                                        <td style="padding:8px;">Active</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Policy Update: Waste Segregation</td>
                                        <td style="padding:8px;">Policy</td>
                                        <td style="padding:8px;">2026-01-12</td>
                                        <td style="padding:8px;">Released</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="feedback-review-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Feedback Review</h1>
                            <p class="dashboard-subtitle">Review resident feedback, opinions, and concerns.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">View All</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Resident Feedback</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Source</th>
                                        <th style="text-align:left; padding:8px;">Topic</th>
                                        <th style="text-align:left; padding:8px;">Date</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Complaint Form</td>
                                        <td style="padding:8px;">Noise at Zone 2</td>
                                        <td style="padding:8px;">2026-01-14</td>
                                        <td style="padding:8px;">In Review</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Survey Response</td>
                                        <td style="padding:8px;">Patrol schedule preferences</td>
                                        <td style="padding:8px;">2026-01-09</td>
                                        <td style="padding:8px;">Noted</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Event Feedback</td>
                                        <td style="padding:8px;">Disaster drill comments</td>
                                        <td style="padding:8px;">2026-01-05</td>
                                        <td style="padding:8px;">Resolved</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="complaint-review-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Complaint Review Overview</h1>
                            <p class="dashboard-subtitle">Overview of complaints received and resolution metrics.</p>
                        </div>
                    </div>
                    <?php
                        $cr_total = 0;
                        $cr_resolved = 0;
                        $cr_unresolved = 0;
                        $cr_avg_display = '—';
                        try {
                            $cr_total = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE category = 'Complaint'")->fetchColumn();
                        } catch (Exception $e) {}
                        try {
                            $cr_resolved = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE category = 'Complaint' AND status = 'resolved'")->fetchColumn();
                        } catch (Exception $e) {}
                        try {
                            $cr_unresolved = (int)$pdo->query("SELECT COUNT(*) FROM feedback WHERE category = 'Complaint' AND status <> 'resolved'")->fetchColumn();
                        } catch (Exception $e) {}
                        try {
                            $stmt_cr = $pdo->query("SELECT AVG(TIMESTAMPDIFF(MINUTE, created_at, updated_at)) AS avg_minutes FROM feedback WHERE category = 'Complaint' AND status = 'resolved'");
                            $row_cr = $stmt_cr->fetch(PDO::FETCH_ASSOC);
                            $avg_minutes = $row_cr ? (float)$row_cr['avg_minutes'] : 0;
                            if ($avg_minutes > 0) {
                                $hours = $avg_minutes / 60.0;
                                if ($hours >= 24) {
                                    $days = floor($hours / 24);
                                    $rem = round($hours - ($days * 24), 1);
                                    $cr_avg_display = $days . 'd ' . $rem . 'h';
                                } else {
                                    $cr_avg_display = round($hours, 1) . 'h';
                                }
                            }
                            $stmt_cr = null;
                        } catch (Exception $e) {}
                    ?>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Total Complaints</span>
                            </div>
                            <div class="stat-value"><?php echo (int)$cr_total; ?></div>
                            <div class="stat-info">
                                <span>All time</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Resolved vs Unresolved</span>
                            </div>
                            <div class="stat-value"><?php echo (int)$cr_resolved; ?> / <?php echo (int)$cr_unresolved; ?></div>
                            <div class="stat-info">
                                <span>Resolved / Unresolved</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Avg Resolution Time</span>
                            </div>
                            <div class="stat-value"><?php echo htmlspecialchars($cr_avg_display); ?></div>
                            <div class="stat-info">
                                <span>Resolved cases</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Complaints Summary</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Metric</th>
                                        <th style="text-align:left; padding:8px;">Value</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Total filed</td>
                                        <td style="padding:8px;"><?php echo (int)$cr_total; ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Resolved</td>
                                        <td style="padding:8px;"><?php echo (int)$cr_resolved; ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Unresolved</td>
                                        <td style="padding:8px;"><?php echo (int)$cr_unresolved; ?></td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Average time to resolve</td>
                                        <td style="padding:8px;"><?php echo htmlspecialchars($cr_avg_display); ?></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="incident-review-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Incident Review</h1>
                            <p class="dashboard-subtitle">Serious incidents requiring close attention.</p>
                        </div>
                    </div>
                    <?php
                        $serious_incidents = [];
                        try {
                            $stmt_si = $pdo->prepare("SELECT id, title, category, priority, status, location, created_at FROM tips WHERE priority = 'Urgent' OR LOWER(title) LIKE '%violence%' OR LOWER(title) LIKE '%robbery%' OR LOWER(title) LIKE '%assault%' OR LOWER(title) LIKE '%accident%' OR LOWER(description) LIKE '%violence%' OR LOWER(description) LIKE '%robbery%' OR LOWER(description) LIKE '%assault%' OR LOWER(description) LIKE '%accident%' ORDER BY created_at DESC LIMIT 20");
                            $stmt_si->execute([]);
                            $serious_incidents = $stmt_si->fetchAll(PDO::FETCH_ASSOC);
                            $stmt_si = null;
                        } catch (Exception $e) {
                            $serious_incidents = [];
                        }
                    ?>
                    <div class="card">
                        <h2 class="card-title">Serious Incidents</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Date</th>
                                        <th style="text-align:left; padding:8px;">Type</th>
                                        <th style="text-align:left; padding:8px;">Title</th>
                                        <th style="text-align:left; padding:8px;">Priority</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                        <th style="text-align:left; padding:8px;">Location</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        if (!empty($serious_incidents)) {
                                            foreach ($serious_incidents as $si) {
                                                $dt = htmlspecialchars(substr((string)($si['created_at'] ?? ''), 0, 10));
                                                $cat = htmlspecialchars($si['category'] ?? '');
                                                $ttl = htmlspecialchars($si['title'] ?? '');
                                                $pri = htmlspecialchars($si['priority'] ?? '');
                                                $sts = htmlspecialchars($si['status'] ?? '');
                                                $loc = htmlspecialchars($si['location'] ?? '');
                                                echo '<tr>';
                                                echo '<td style="padding:8px;">' . ($dt !== '' ? $dt : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($cat !== '' ? $cat : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($ttl !== '' ? $ttl : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($pri !== '' ? $pri : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($sts !== '' ? ucfirst(strtolower($sts)) : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($loc !== '' ? $loc : '—') . '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="6" style="padding:14px;">No serious incidents found.</td></tr>';
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="final-disposition-reports-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Final Disposition Reports</h1>
                            <p class="dashboard-subtitle">Reports documenting case outcomes.</p>
                        </div>
                    </div>
                    <?php
                        $final_reports = [];
                        try {
                            $stmt_fr = $pdo->prepare("SELECT id, subject, admin_response, updated_at FROM feedback WHERE category = 'Complaint' AND status = 'resolved' ORDER BY updated_at DESC");
                            $stmt_fr->execute([]);
                            $final_reports = $stmt_fr->fetchAll(PDO::FETCH_ASSOC);
                            $stmt_fr = null;
                        } catch (Exception $e) {
                            $final_reports = [];
                        }
                        function infer_method($txt) {
                            $t = strtolower((string)$txt);
                            if (strpos($t, 'mediation') !== false) return 'Mediation';
                            if (strpos($t, 'arrest') !== false) return 'Arrest';
                            if (strpos($t, 'refer') !== false) return 'Referral';
                            return '—';
                        }
                    ?>
                    <div class="card">
                        <h2 class="card-title">Case Closures</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Case ID</th>
                                        <th style="text-align:left; padding:8px;">Subject</th>
                                        <th style="text-align:left; padding:8px;">Action Taken</th>
                                        <th style="text-align:left; padding:8px;">Resolution Method</th>
                                        <th style="text-align:left; padding:8px;">Date of Closure</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                        if (!empty($final_reports)) {
                                            foreach ($final_reports as $fr) {
                                                $id = (int)($fr['id'] ?? 0);
                                                $subj = htmlspecialchars($fr['subject'] ?? '');
                                                $resp = htmlspecialchars($fr['admin_response'] ?? '');
                                                $closed = htmlspecialchars(substr((string)($fr['updated_at'] ?? ''), 0, 10));
                                                $method = infer_method($fr['admin_response'] ?? '');
                                                echo '<tr>';
                                                echo '<td style="padding:8px;">' . ($id > 0 ? $id : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($subj !== '' ? $subj : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . ($resp !== '' ? $resp : '—') . '</td>';
                                                echo '<td style="padding:8px;">' . htmlspecialchars($method) . '</td>';
                                                echo '<td style="padding:8px;">' . ($closed !== '' ? $closed : '—') . '</td>';
                                                echo '</tr>';
                                            }
                                        } else {
                                            echo '<tr><td colspan="5" style="padding:14px;">No final disposition reports found.</td></tr>';
                                        }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <div id="executive-summary-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Executive Summary Reports</h1>
                            <p class="dashboard-subtitle">High-level snapshot for quick situational awareness.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Export Summary</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Incidents</span>
                            </div>
                            <div class="stat-value">27</div>
                            <div class="stat-info">
                                <span>Past 30 days</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Complaints</span>
                            </div>
                            <div class="stat-value">18</div>
                            <div class="stat-info">
                                <span>All statuses</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Activities</span>
                            </div>
                            <div class="stat-value">9</div>
                            <div class="stat-info">
                                <span>Scheduled</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Highlights</span>
                            </div>
                            <div class="stat-value">3</div>
                            <div class="stat-info">
                                <span>Major items</span>
                            </div>
                        </div>
                    </div>
                    <div class="two-column-grid">
                        <div class="card">
                            <h2 class="card-title">Major Issues & Highlights</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left; padding:8px;">Topic</th>
                                            <th style="text-align:left; padding:8px;">Details</th>
                                            <th style="text-align:left; padding:8px;">Impact</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="padding:8px;">Traffic obstruction</td>
                                            <td style="padding:8px;">Market Road congestion</td>
                                            <td style="padding:8px;">Medium</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px;">Noise complaints</td>
                                            <td style="padding:8px;">Zone 2 late-night events</td>
                                            <td style="padding:8px;">High</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px;">Patrol coverage</td>
                                            <td style="padding:8px;">95% area coverage</td>
                                            <td style="padding:8px;">Positive</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="card">
                            <h2 class="card-title">Quick Recommendations</h2>
                            <div style="overflow-x:auto;">
                                <table style="width:100%; border-collapse:collapse;">
                                    <thead>
                                        <tr>
                                            <th style="text-align:left; padding:8px;">Action</th>
                                            <th style="text-align:left; padding:8px;">Owner</th>
                                            <th style="text-align:left; padding:8px;">Due</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td style="padding:8px;">Adjust patrol schedule</td>
                                            <td style="padding:8px;">Safety Unit</td>
                                            <td style="padding:8px;">2026-01-22</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px;">Issue noise advisories</td>
                                            <td style="padding:8px;">Barangay Council</td>
                                            <td style="padding:8px;">2026-01-20</td>
                                        </tr>
                                        <tr>
                                            <td style="padding:8px;">Coordinate traffic rerouting</td>
                                            <td style="padding:8px;">Traffic Task Force</td>
                                            <td style="padding:8px;">2026-01-25</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div id="risk-preparedness-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Risk & Preparedness Assessment</h1>
                            <p class="dashboard-subtitle">Evaluate risks, resources, and community readiness.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Update Assessment</button>
                        </div>
                    </div>
                    <div class="stats-grid">
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Disaster Risk</span>
                            </div>
                            <div class="stat-value">Moderate</div>
                            <div class="stat-info">
                                <span>Floods, fires</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Equipment Availability</span>
                            </div>
                            <div class="stat-value">88<span style="font-size: 24px;">%</span></div>
                            <div class="stat-info">
                                <span>Operational</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Manpower Readiness</span>
                            </div>
                            <div class="stat-value">76<span style="font-size: 24px;">%</span></div>
                            <div class="stat-info">
                                <span>Available on call</span>
                            </div>
                        </div>
                        <div class="stat-card stat-card-white">
                            <div class="stat-header">
                                <span class="stat-title">Community Plans</span>
                            </div>
                            <div class="stat-value">3</div>
                            <div class="stat-info">
                                <span>Active response plans</span>
                            </div>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Hazard & Preparedness Matrix</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Hazard</th>
                                        <th style="text-align:left; padding:8px;">Risk Level</th>
                                        <th style="text-align:left; padding:8px;">Preparedness Index</th>
                                        <th style="text-align:left; padding:8px;">Notes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">Flood</td>
                                        <td style="padding:8px;">Medium</td>
                                        <td style="padding:8px;">0.72</td>
                                        <td style="padding:8px;">Sandbags available; evacuation plan</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Fire</td>
                                        <td style="padding:8px;">Medium</td>
                                        <td style="padding:8px;">0.81</td>
                                        <td style="padding:8px;">Hydrants serviced; drills scheduled</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Earthquake</td>
                                        <td style="padding:8px;">Low</td>
                                        <td style="padding:8px;">0.65</td>
                                        <td style="padding:8px;">Public awareness ongoing</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="strategic-action-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Strategic Action Planning Tool</h1>
                            <p class="dashboard-subtitle">Plan next actions based on system data.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">Create Action</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Action Plan</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Priority</th>
                                        <th style="text-align:left; padding:8px;">Action</th>
                                        <th style="text-align:left; padding:8px;">Responsible</th>
                                        <th style="text-align:left; padding:8px;">Follow-up</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">High</td>
                                        <td style="padding:8px;">Deploy night patrols</td>
                                        <td style="padding:8px;">Safety Unit</td>
                                        <td style="padding:8px;">2026-01-21</td>
                                        <td style="padding:8px;">Planned</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Medium</td>
                                        <td style="padding:8px;">Community noise awareness</td>
                                        <td style="padding:8px;">Barangay Council</td>
                                        <td style="padding:8px;">2026-01-24</td>
                                        <td style="padding:8px;">In Review</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">Low</td>
                                        <td style="padding:8px;">Hydrant maintenance follow-up</td>
                                        <td style="padding:8px;">Facilities</td>
                                        <td style="padding:8px;">2026-01-26</td>
                                        <td style="padding:8px;">Pending</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div id="official-approval-section" style="display:none;">
                    <div class="dashboard-header">
                        <div>
                            <h1 class="dashboard-title">Official Approval</h1>
                            <p class="dashboard-subtitle">Approve, certify, and finalize reports electronically.</p>
                        </div>
                        <div class="dashboard-actions">
                            <button class="secondary-button">New Approval</button>
                        </div>
                    </div>
                    <div class="card">
                        <h2 class="card-title">Approval Registry</h2>
                        <div style="overflow-x:auto;">
                            <table style="width:100%; border-collapse:collapse;">
                                <thead>
                                    <tr>
                                        <th style="text-align:left; padding:8px;">Document ID</th>
                                        <th style="text-align:left; padding:8px;">Title</th>
                                        <th style="text-align:left; padding:8px;">Approved By</th>
                                        <th style="text-align:left; padding:8px;">Approved On</th>
                                        <th style="text-align:left; padding:8px;">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td style="padding:8px;">DOC-ES-2026-001</td>
                                        <td style="padding:8px;">Executive Summary Jan 2026</td>
                                        <td style="padding:8px;">Barangay Captain</td>
                                        <td style="padding:8px;">2026-01-15 10:22</td>
                                        <td style="padding:8px;">Certified</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">DOC-RP-2026-003</td>
                                        <td style="padding:8px;">Risk Assessment Q1</td>
                                        <td style="padding:8px;">Barangay Captain</td>
                                        <td style="padding:8px;">2026-01-16 09:05</td>
                                        <td style="padding:8px;">Approved</td>
                                    </tr>
                                    <tr>
                                        <td style="padding:8px;">DOC-SP-2026-004</td>
                                        <td style="padding:8px;">Strategic Plan Updates</td>
                                        <td style="padding:8px;">Council Secretary</td>
                                        <td style="padding:8px;">2026-01-16 14:40</td>
                                        <td style="padding:8px;">Finalized</td>
                                    </tr>
                                </tbody>
                            </table>
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
            });
        });
        
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
        
        const sectionLinks = [
            { id: 'barangay-overview-link', target: 'barangay-overview-section' },
            { id: 'performance-indicators-link', target: 'performance-indicators-section' },
            { id: 'events-activities-link', target: 'events-activities-section' },
            { id: 'incident-approval-link', target: 'incident-approval-section' },
            { id: 'request-approval-link', target: 'request-approval-section' },
            { id: 'event-program-approval-link', target: 'event-program-approval-section' },
            { id: 'barangay-policy-management-link', target: 'barangay-policy-management-section' },
            { id: 'ordinance-tracker-link', target: 'ordinance-tracker-section' },
            { id: 'legal-reference-link', target: 'legal-reference-section' },
            { id: 'volunteer-program-review-link', target: 'volunteer-program-review-section' },
            { id: 'neighborhood-watch-review-link', target: 'neighborhood-watch-review-section' },
            { id: 'official-announcements-link', target: 'official-announcements-section' },
            { id: 'feedback-review-link', target: 'feedback-review-section' },
            { id: 'executive-summary-link', target: 'executive-summary-section' },
            { id: 'risk-preparedness-link', target: 'risk-preparedness-section' },
            { id: 'strategic-action-link', target: 'strategic-action-section' },
            { id: 'official-approval-link', target: 'official-approval-section' },
            { id: 'complaint-review-link', target: 'complaint-review-section' },
            { id: 'incident-review-link', target: 'incident-review-section' },
            { id: 'final-disposition-reports-link', target: 'final-disposition-reports-section' }
        ];
        const defaultSection = document.getElementById('default-dashboard-section');
        const sectionsMap = {
            'barangay-overview-section': document.getElementById('barangay-overview-section'),
            'performance-indicators-section': document.getElementById('performance-indicators-section'),
            'events-activities-section': document.getElementById('events-activities-section'),
            'incident-approval-section': document.getElementById('incident-approval-section'),
            'request-approval-section': document.getElementById('request-approval-section'),
            'event-program-approval-section': document.getElementById('event-program-approval-section'),
            'barangay-policy-management-section': document.getElementById('barangay-policy-management-section'),
            'ordinance-tracker-section': document.getElementById('ordinance-tracker-section'),
            'legal-reference-section': document.getElementById('legal-reference-section'),
            'volunteer-program-review-section': document.getElementById('volunteer-program-review-section'),
            'neighborhood-watch-review-section': document.getElementById('neighborhood-watch-review-section'),
            'official-announcements-section': document.getElementById('official-announcements-section'),
            'feedback-review-section': document.getElementById('feedback-review-section'),
            'executive-summary-section': document.getElementById('executive-summary-section'),
            'risk-preparedness-section': document.getElementById('risk-preparedness-section'),
            'strategic-action-section': document.getElementById('strategic-action-section'),
            'official-approval-section': document.getElementById('official-approval-section'),
            'complaint-review-section': document.getElementById('complaint-review-section'),
            'incident-review-section': document.getElementById('incident-review-section'),
            'final-disposition-reports-section': document.getElementById('final-disposition-reports-section'),
            'settings-profile-section': document.getElementById('settings-profile-section'),
            'settings-security-section': document.getElementById('settings-security-section')
        };
        function showOnly(sectionId) {
            Object.values(sectionsMap).forEach(s => s.style.display = 'none');
            if (sectionId) {
                defaultSection.style.display = 'none';
                sectionsMap[sectionId].style.display = 'block';
            } else {
                defaultSection.style.display = '';
            }
        }
        sectionLinks.forEach(l => {
            const el = document.getElementById(l.id);
            if (el) {
                el.addEventListener('click', function(e) {
                    e.preventDefault();
                    showOnly(l.target);
                });
            }
        });
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
        function showSection(id){ Object.values(sectionsMap).forEach(s => { if (s) s.style.display='none'; }); if (defaultSection) defaultSection.style.display='none'; const el=document.getElementById(id); if (el) el.style.display='block'; }
        if (settingsProfileBtn) settingsProfileBtn.addEventListener('click', function(){ showSection('settings-profile-section'); if (settingsDropdown) settingsDropdown.classList.remove('active'); loadProfile(); });
        if (settingsSecurityBtn) settingsSecurityBtn.addEventListener('click', function(){ showSection('settings-security-section'); if (settingsDropdown) settingsDropdown.classList.remove('active'); });
        if (sidebarSettingsProfileLink) sidebarSettingsProfileLink.addEventListener('click', function(e){ e.preventDefault(); showSection('settings-profile-section'); loadProfile(); });
        if (sidebarSettingsSecurityLink) sidebarSettingsSecurityLink.addEventListener('click', function(e){ e.preventDefault(); showSection('settings-security-section'); });
        const settingsTabProfile = document.getElementById('settings-tab-profile');
        const settingsTabSecurity = document.getElementById('settings-tab-security');
        if (settingsTabProfile) settingsTabProfile.addEventListener('click', function(){ showSection('settings-profile-section'); loadProfile(); });
        if (settingsTabSecurity) settingsTabSecurity.addEventListener('click', function(){ showSection('settings-security-section'); });
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
                const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
                    const data = await res.json();
                    if (data && data.success){
                        tfaEnabled = !!data.enabled;
                        tfaStatusEl.innerHTML = tfaEnabled ? '<span class="badge badge-active">Enabled</span>' : '<span class="badge badge-pending">Disabled</span>';
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
        const dashboardMenu = document.getElementById('dashboard-menu');
        if (dashboardMenu) {
            dashboardMenu.addEventListener('click', function(e) {
                e.preventDefault();
                showOnly(null);
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
                    const res = await fetch('captain_dashboard.php', { method:'POST', body: fd, credentials:'same-origin' });
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
                    showOnly(null);
                });
            }
        })();
    </script>
</body>
</html>
